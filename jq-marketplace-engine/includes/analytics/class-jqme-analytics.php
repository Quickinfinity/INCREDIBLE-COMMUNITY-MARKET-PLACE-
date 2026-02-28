<?php
/**
 * Analytics engine — platform metrics, provider scorecards, revenue breakdowns.
 *
 * Provides query methods for the admin dashboard and reports page.
 * All methods are static to allow easy calling from views.
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME\Analytics;

use JQME\Core;
use JQME\StatusEnums;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Analytics {

	/**
	 * Platform overview stats for the admin dashboard.
	 */
	public static function platform_overview(): array {
		global $wpdb;

		$bookings = Core::table( 'bookings' );
		$providers = Core::table( 'providers' );
		$listings = Core::table( 'listings' );
		$reviews = Core::table( 'reviews' );
		$claims = Core::table( 'claims' );

		return [
			'total_bookings'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings}" ),
			'active_bookings'    => (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$bookings} WHERE status NOT IN (%s, %s, %s, %s)",
				StatusEnums::RENTAL_COMPLETED, StatusEnums::SERVICE_COMPLETED, StatusEnums::SALE_COMPLETED, StatusEnums::RENTAL_CANCELLED_BY_CUSTOMER
			) ),
			'total_providers'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$providers}" ),
			'approved_providers' => (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$providers} WHERE status = %s", StatusEnums::PROVIDER_APPROVED
			) ),
			'total_listings'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$listings}" ),
			'published_listings' => (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$listings} WHERE status = %s", StatusEnums::LISTING_PUBLISHED
			) ),
			'total_reviews'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$reviews} WHERE overall_rating > 0" ),
			'published_reviews'  => (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$reviews} WHERE status = %s", StatusEnums::REVIEW_PUBLISHED
			) ),
			'open_claims'        => (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$claims} WHERE status NOT IN ('closed', 'denied', 'withdrawn', 'settled_directly', 'deposit_partial_capture', 'deposit_full_capture')"
			),
		];
	}

	/**
	 * Revenue breakdown for a date range.
	 */
	public static function revenue_breakdown( string $from, string $to ): array {
		global $wpdb;

		$bookings = Core::table( 'bookings' );

		$completed_statuses = implode( "','", [
			StatusEnums::RENTAL_COMPLETED,
			StatusEnums::SERVICE_COMPLETED,
			StatusEnums::SALE_COMPLETED,
		] );

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT
				COUNT(*) as booking_count,
				COALESCE(SUM(total_amount), 0) as gross_revenue,
				COALESCE(SUM(platform_fee), 0) as platform_fees,
				COALESCE(SUM(processing_fee), 0) as processing_fees,
				COALESCE(SUM(provider_payout), 0) as provider_payouts,
				COALESCE(SUM(discount_amount), 0) as discounts,
				COALESCE(SUM(tax_amount), 0) as taxes
			 FROM {$bookings}
			 WHERE status IN ('{$completed_statuses}')
			   AND completed_at >= %s AND completed_at <= %s",
			$from, $to
		) );

		return [
			'booking_count'    => $row ? (int) $row->booking_count : 0,
			'gross_revenue'    => $row ? (float) $row->gross_revenue : 0,
			'platform_fees'    => $row ? (float) $row->platform_fees : 0,
			'processing_fees'  => $row ? (float) $row->processing_fees : 0,
			'provider_payouts' => $row ? (float) $row->provider_payouts : 0,
			'discounts'        => $row ? (float) $row->discounts : 0,
			'taxes'            => $row ? (float) $row->taxes : 0,
			'net_revenue'      => $row ? (float) $row->platform_fees : 0,
		];
	}

	/**
	 * Revenue by booking type for a date range.
	 */
	public static function revenue_by_type( string $from, string $to ): array {
		global $wpdb;

		$bookings = Core::table( 'bookings' );

		$completed_statuses = implode( "','", [
			StatusEnums::RENTAL_COMPLETED,
			StatusEnums::SERVICE_COMPLETED,
			StatusEnums::SALE_COMPLETED,
		] );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT booking_type,
					COUNT(*) as count,
					COALESCE(SUM(total_amount), 0) as revenue,
					COALESCE(SUM(platform_fee), 0) as fees
			 FROM {$bookings}
			 WHERE status IN ('{$completed_statuses}')
			   AND completed_at >= %s AND completed_at <= %s
			 GROUP BY booking_type",
			$from, $to
		) );
	}

	/**
	 * Revenue trend — monthly totals.
	 */
	public static function monthly_revenue( int $months = 12 ): array {
		global $wpdb;

		$bookings = Core::table( 'bookings' );
		$start    = gmdate( 'Y-m-01', strtotime( "-{$months} months" ) );

		$completed_statuses = implode( "','", [
			StatusEnums::RENTAL_COMPLETED,
			StatusEnums::SERVICE_COMPLETED,
			StatusEnums::SALE_COMPLETED,
		] );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT DATE_FORMAT(completed_at, '%%Y-%%m') as month,
					COUNT(*) as bookings,
					COALESCE(SUM(total_amount), 0) as revenue,
					COALESCE(SUM(platform_fee), 0) as fees
			 FROM {$bookings}
			 WHERE status IN ('{$completed_statuses}')
			   AND completed_at >= %s
			 GROUP BY DATE_FORMAT(completed_at, '%%Y-%%m')
			 ORDER BY month ASC",
			$start
		) );
	}

	/**
	 * Top providers by revenue.
	 */
	public static function top_providers( int $limit = 10 ): array {
		global $wpdb;

		$bookings  = Core::table( 'bookings' );
		$providers = Core::table( 'providers' );

		$completed_statuses = implode( "','", [
			StatusEnums::RENTAL_COMPLETED,
			StatusEnums::SERVICE_COMPLETED,
			StatusEnums::SALE_COMPLETED,
		] );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT p.id, p.company_name, p.trust_score, p.status,
					u.display_name,
					COUNT(b.id) as total_bookings,
					COALESCE(SUM(b.total_amount), 0) as total_revenue,
					COALESCE(SUM(b.platform_fee), 0) as total_fees
			 FROM {$providers} p
			 LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
			 LEFT JOIN {$bookings} b ON b.provider_id = p.id AND b.status IN ('{$completed_statuses}')
			 GROUP BY p.id
			 ORDER BY total_revenue DESC
			 LIMIT %d",
			$limit
		) );
	}

	/**
	 * Top listings by booking count.
	 */
	public static function top_listings( int $limit = 10 ): array {
		global $wpdb;

		$listings = Core::table( 'listings' );
		$bookings = Core::table( 'bookings' );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT l.id, l.title, l.listing_type, l.average_rating, l.review_count,
					l.view_count, l.booking_count,
					COALESCE(SUM(b.total_amount), 0) as total_revenue
			 FROM {$listings} l
			 LEFT JOIN {$bookings} b ON b.listing_id = l.id
			 GROUP BY l.id
			 ORDER BY l.booking_count DESC
			 LIMIT %d",
			$limit
		) );
	}

	/**
	 * Booking status distribution.
	 */
	public static function booking_status_distribution(): array {
		global $wpdb;
		$bookings = Core::table( 'bookings' );

		return $wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM {$bookings} GROUP BY status ORDER BY count DESC"
		);
	}

	/**
	 * Claim stats for a date range.
	 */
	public static function claim_stats( string $from = '', string $to = '' ): array {
		global $wpdb;
		$claims = Core::table( 'claims' );

		$where = '';
		$values = [];
		if ( $from && $to ) {
			$where = 'WHERE created_at >= %s AND created_at <= %s';
			$values = [ $from, $to ];
		}

		$base_query = "SELECT
			COUNT(*) as total_claims,
			SUM(CASE WHEN status = 'claim_submitted' THEN 1 ELSE 0 END) as submitted,
			SUM(CASE WHEN status IN ('closed', 'denied', 'withdrawn', 'settled_directly', 'deposit_partial_capture', 'deposit_full_capture') THEN 1 ELSE 0 END) as resolved,
			COALESCE(SUM(amount_requested), 0) as total_requested,
			COALESCE(SUM(amount_settled), 0) as total_settled
		 FROM {$claims} {$where}";

		if ( ! empty( $values ) ) {
			$row = $wpdb->get_row( $wpdb->prepare( $base_query, $values ) );
		} else {
			$row = $wpdb->get_row( $base_query ); // phpcs:ignore WordPress.DB.PreparedSQL
		}

		return [
			'total'          => $row ? (int) $row->total_claims : 0,
			'submitted'      => $row ? (int) $row->submitted : 0,
			'resolved'       => $row ? (int) $row->resolved : 0,
			'total_requested' => $row ? (float) $row->total_requested : 0,
			'total_settled'  => $row ? (float) $row->total_settled : 0,
		];
	}

	/**
	 * Provider scorecard — individual provider stats.
	 */
	public static function provider_scorecard( int $provider_id ): array {
		global $wpdb;

		$bookings  = Core::table( 'bookings' );
		$reviews   = Core::table( 'reviews' );
		$claims    = Core::table( 'claims' );
		$providers = Core::table( 'providers' );

		$provider = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$providers} WHERE id = %d", $provider_id
		) );

		if ( ! $provider ) {
			return [];
		}

		$completed_statuses = implode( "','", [
			StatusEnums::RENTAL_COMPLETED,
			StatusEnums::SERVICE_COMPLETED,
			StatusEnums::SALE_COMPLETED,
		] );

		$booking_stats = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) as total,
					SUM(CASE WHEN status IN ('{$completed_statuses}') THEN 1 ELSE 0 END) as completed,
					COALESCE(SUM(CASE WHEN status IN ('{$completed_statuses}') THEN total_amount ELSE 0 END), 0) as revenue,
					COALESCE(SUM(CASE WHEN status IN ('{$completed_statuses}') THEN platform_fee ELSE 0 END), 0) as fees
			 FROM {$bookings}
			 WHERE provider_id = %d",
			$provider_id
		) );

		$review_stats = $wpdb->get_row( $wpdb->prepare(
			"SELECT AVG(overall_rating) as avg_rating, COUNT(*) as count
			 FROM {$reviews}
			 WHERE reviewee_id = %d AND status = %s AND reviewer_role = 'customer' AND overall_rating > 0",
			$provider->user_id,
			StatusEnums::REVIEW_PUBLISHED
		) );

		$claim_count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$claims} c
			 INNER JOIN {$bookings} b ON c.booking_id = b.id
			 WHERE b.provider_id = %d",
			$provider_id
		) );

		return [
			'provider'       => $provider,
			'total_bookings' => $booking_stats ? (int) $booking_stats->total : 0,
			'completed'      => $booking_stats ? (int) $booking_stats->completed : 0,
			'revenue'        => $booking_stats ? (float) $booking_stats->revenue : 0,
			'fees'           => $booking_stats ? (float) $booking_stats->fees : 0,
			'avg_rating'     => $review_stats ? round( (float) $review_stats->avg_rating, 2 ) : 0,
			'review_count'   => $review_stats ? (int) $review_stats->count : 0,
			'claim_count'    => $claim_count,
			'trust_score'    => (float) $provider->trust_score,
		];
	}

	/**
	 * Utilization rate for equipment listings (% of days booked).
	 */
	public static function listing_utilization( int $listing_id, int $days = 90 ): float {
		global $wpdb;

		$bookings = Core::table( 'bookings' );
		$start    = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		$booked_days = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(DATEDIFF(LEAST(date_end, NOW()), GREATEST(date_start, %s)) + 1), 0)
			 FROM {$bookings}
			 WHERE listing_id = %d AND date_start IS NOT NULL AND date_end IS NOT NULL
			   AND status NOT LIKE '%%cancelled%%'
			   AND date_end >= %s",
			$start, $listing_id, $start
		) );

		return $days > 0 ? round( ( $booked_days / $days ) * 100, 1 ) : 0;
	}
}
