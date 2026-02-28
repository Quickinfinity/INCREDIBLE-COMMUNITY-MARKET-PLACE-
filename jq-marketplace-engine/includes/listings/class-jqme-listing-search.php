<?php
/**
 * Listing search engine — weighted ranking, promoted listings, filtering.
 *
 * Provides search/sort/filter for the public marketplace browse page.
 * Supports promoted listings via a boost multiplier.
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME\Listings;

use JQME\Core;
use JQME\StatusEnums;
use JQME\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ListingSearch {

	/**
	 * Search listings with weighted ranking.
	 */
	public static function search( array $args = [] ): array {
		global $wpdb;

		$defaults = [
			'query'        => '',
			'listing_type' => '',
			'category'     => '',
			'subcategory'  => '',
			'min_price'    => 0,
			'max_price'    => 0,
			'min_rating'   => 0,
			'featured'     => null,
			'provider_id'  => 0,
			'latitude'     => 0,
			'longitude'    => 0,
			'radius_miles' => 50,
			'sort'         => Settings::get( 'search', 'default_sort' ) ?: 'relevance',
			'limit'        => (int) Settings::get( 'search', 'default_results_per_page' ) ?: 20,
			'offset'       => 0,
		];

		$args = wp_parse_args( $args, $defaults );

		$listings  = Core::table( 'listings' );
		$providers = Core::table( 'providers' );
		$where     = [ 'l.status = %s' ];
		$values    = [ StatusEnums::LISTING_PUBLISHED ];
		$selects   = [ 'l.*', 'p.company_name', 'p.trust_score', 'p.city as provider_city', 'p.state as provider_state' ];

		// Hide unverified if setting says so.
		if ( Settings::get( 'search', 'hide_unverified_listings' ) ) {
			// Only show listings whose providers are approved.
			$where[]  = 'p.status = %s';
			$values[] = StatusEnums::PROVIDER_APPROVED;
		}

		// Text search.
		if ( $args['query'] ) {
			$like = '%' . $wpdb->esc_like( $args['query'] ) . '%';
			$where[]  = '(l.title LIKE %s OR l.description LIKE %s OR l.category LIKE %s OR l.brand LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		// Filters.
		if ( $args['listing_type'] ) {
			$where[]  = 'l.listing_type = %s';
			$values[] = $args['listing_type'];
		}
		if ( $args['category'] ) {
			$where[]  = 'l.category = %s';
			$values[] = $args['category'];
		}
		if ( $args['subcategory'] ) {
			$where[]  = 'l.subcategory = %s';
			$values[] = $args['subcategory'];
		}
		if ( $args['min_rating'] > 0 ) {
			$where[]  = 'l.average_rating >= %f';
			$values[] = $args['min_rating'];
		}
		if ( null !== $args['featured'] ) {
			$where[]  = 'l.featured = %d';
			$values[] = $args['featured'] ? 1 : 0;
		}
		if ( $args['provider_id'] ) {
			$where[]  = 'l.provider_id = %d';
			$values[] = $args['provider_id'];
		}

		// Price filtering (use day_rate for rentals, asking_price for sales, hourly_rate for services).
		if ( $args['min_price'] > 0 || $args['max_price'] > 0 ) {
			$price_col = 'COALESCE(l.day_rate, l.asking_price, l.hourly_rate, 0)';
			if ( $args['min_price'] > 0 ) {
				$where[]  = "{$price_col} >= %f";
				$values[] = $args['min_price'];
			}
			if ( $args['max_price'] > 0 ) {
				$where[]  = "{$price_col} <= %f";
				$values[] = $args['max_price'];
			}
		}

		// Distance filter (if coordinates provided).
		if ( $args['latitude'] && $args['longitude'] ) {
			$selects[] = sprintf(
				'(3959 * acos(cos(radians(%%f)) * cos(radians(p.latitude)) * cos(radians(p.longitude) - radians(%%f)) + sin(radians(%%f)) * sin(radians(p.latitude)))) AS distance'
			);
			// We'll inject the values separately.
			$distance_values = [
				$args['latitude'],
				$args['longitude'],
				$args['latitude'],
			];
		}

		$where_sql  = 'WHERE ' . implode( ' AND ', $where );
		$select_sql = implode( ', ', $selects );

		// Sort logic.
		$weights = [
			'featured' => (int) Settings::get( 'search', 'featured_ranking_weight' ) ?: 10,
			'review'   => (int) Settings::get( 'search', 'review_ranking_weight' ) ?: 7,
			'price'    => (int) Settings::get( 'search', 'price_ranking_weight' ) ?: 5,
		];

		switch ( $args['sort'] ) {
			case 'price_low':
				$order = 'COALESCE(l.day_rate, l.asking_price, l.hourly_rate, 0) ASC';
				break;
			case 'price_high':
				$order = 'COALESCE(l.day_rate, l.asking_price, l.hourly_rate, 0) DESC';
				break;
			case 'rating':
				$order = 'l.average_rating DESC, l.review_count DESC';
				break;
			case 'newest':
				$order = 'l.published_at DESC';
				break;
			case 'distance':
				$order = isset( $distance_values ) ? 'distance ASC' : 'l.published_at DESC';
				break;
			default: // relevance
				$order = "(l.featured * {$weights['featured']}) + (l.average_rating * {$weights['review']}) + (l.booking_count * 0.5) DESC";
				break;
		}

		$sql = "SELECT {$select_sql}
				FROM {$listings} l
				INNER JOIN {$providers} p ON l.provider_id = p.id
				{$where_sql}
				ORDER BY {$order}
				LIMIT %d OFFSET %d";

		$values[] = $args['limit'];
		$values[] = $args['offset'];

		// Handle distance values injection.
		if ( isset( $distance_values ) ) {
			$values = array_merge( $distance_values, $values );
		}

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL

		// Count total for pagination.
		$count_sql = "SELECT COUNT(*)
					  FROM {$listings} l
					  INNER JOIN {$providers} p ON l.provider_id = p.id
					  {$where_sql}";

		$count_values = array_slice( $values, 0, -2 ); // Remove limit/offset.
		if ( isset( $distance_values ) ) {
			$count_values = array_slice( $count_values, count( $distance_values ) ); // Remove distance values too.
		}

		$total = ! empty( $count_values )
			? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $count_values ) )
			: (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL

		return [
			'results' => $results,
			'total'   => $total,
			'page'    => $args['offset'] > 0 ? floor( $args['offset'] / $args['limit'] ) + 1 : 1,
			'pages'   => $args['limit'] > 0 ? ceil( $total / $args['limit'] ) : 1,
		];
	}

	/**
	 * Get available filter options (categories, price ranges, etc.).
	 */
	public static function get_filter_options( string $listing_type = '' ): array {
		global $wpdb;

		$listings = Core::table( 'listings' );
		$where = $wpdb->prepare( "WHERE status = %s", StatusEnums::LISTING_PUBLISHED );

		if ( $listing_type ) {
			$where .= $wpdb->prepare( " AND listing_type = %s", $listing_type );
		}

		$categories = $wpdb->get_col( "SELECT DISTINCT category FROM {$listings} {$where} AND category != '' ORDER BY category" ); // phpcs:ignore WordPress.DB.PreparedSQL

		$price_range = $wpdb->get_row(
			"SELECT MIN(COALESCE(day_rate, asking_price, hourly_rate, 0)) as min_price,
					MAX(COALESCE(day_rate, asking_price, hourly_rate, 0)) as max_price
			 FROM {$listings} {$where}" // phpcs:ignore WordPress.DB.PreparedSQL
		);

		return [
			'categories' => $categories ?: [],
			'min_price'  => $price_range ? (float) $price_range->min_price : 0,
			'max_price'  => $price_range ? (float) $price_range->max_price : 0,
			'types'      => StatusEnums::listing_types(),
		];
	}
}
