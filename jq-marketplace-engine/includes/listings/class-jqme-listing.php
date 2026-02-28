<?php
/**
 * Listing model — CRUD and business logic for marketplace listings.
 *
 * Supports all three listing types: equipment_rental, equipment_sale, service_booking.
 * Handles creation, updates, status transitions, moderation, and queries.
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME\Listings;

use JQME\Core;
use JQME\AuditLogger;
use JQME\StatusEnums;
use JQME\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Listing {

	/**
	 * Get a listing by ID.
	 */
	public static function get( int $id ): ?object {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . Core::table( 'listings' ) . " WHERE id = %d",
			$id
		) );
		return $row ?: null;
	}

	/**
	 * Get a listing by slug.
	 */
	public static function get_by_slug( string $slug ): ?object {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . Core::table( 'listings' ) . " WHERE slug = %s",
			$slug
		) );
		return $row ?: null;
	}

	/**
	 * Create a new listing.
	 *
	 * @param int    $provider_id  Provider ID.
	 * @param string $listing_type One of: equipment_rental, equipment_sale, service_booking.
	 * @param array  $data         Listing data.
	 * @return int|false Listing ID or false on failure.
	 */
	public static function create( int $provider_id, string $listing_type, array $data ): int|false {
		global $wpdb;

		// Validate listing type.
		$valid_types = array_keys( StatusEnums::listing_types() );
		if ( ! in_array( $listing_type, $valid_types, true ) ) {
			return false;
		}

		// Check listing limit.
		$provider = \JQME\Providers\Provider::get( $provider_id );
		if ( ! $provider || StatusEnums::PROVIDER_APPROVED !== $provider->status ) {
			return false;
		}

		$limit = (int) $provider->listing_limit;
		if ( $limit > 0 ) {
			$current_count = self::count_by_provider( $provider_id );
			if ( $current_count >= $limit ) {
				return false;
			}
		}

		$title = sanitize_text_field( $data['title'] ?? '' );
		if ( empty( $title ) ) {
			return false;
		}

		$slug = self::generate_unique_slug( $title );

		$insert = [
			'provider_id'         => $provider_id,
			'listing_type'        => $listing_type,
			'title'               => $title,
			'slug'                => $slug,
			'description'         => wp_kses_post( $data['description'] ?? '' ),
			'category'            => sanitize_text_field( $data['category'] ?? '' ),
			'subcategory'         => sanitize_text_field( $data['subcategory'] ?? '' ),
			'status'              => StatusEnums::LISTING_DRAFT,
			'brand'               => sanitize_text_field( $data['brand'] ?? '' ),
			'model'               => sanitize_text_field( $data['model'] ?? '' ),
			'serial_number'       => sanitize_text_field( $data['serial_number'] ?? '' ),
			'condition_grade'     => sanitize_text_field( $data['condition_grade'] ?? '' ),

			// Rental pricing.
			'day_rate'            => self::sanitize_price( $data['day_rate'] ?? null ),
			'weekend_rate'        => self::sanitize_price( $data['weekend_rate'] ?? null ),
			'week_rate'           => self::sanitize_price( $data['week_rate'] ?? null ),
			'month_rate'          => self::sanitize_price( $data['month_rate'] ?? null ),

			// Sale pricing.
			'asking_price'        => self::sanitize_price( $data['asking_price'] ?? null ),

			// Service pricing.
			'hourly_rate'         => self::sanitize_price( $data['hourly_rate'] ?? null ),
			'half_day_rate'       => self::sanitize_price( $data['half_day_rate'] ?? null ),
			'full_day_rate'       => self::sanitize_price( $data['full_day_rate'] ?? null ),

			'deposit_amount'      => self::sanitize_price( $data['deposit_amount'] ?? 0 ),
			'quantity'            => absint( $data['quantity'] ?? 1 ),

			// Fulfillment.
			'pickup_available'    => ! empty( $data['pickup_available'] ) ? 1 : 0,
			'delivery_available'  => ! empty( $data['delivery_available'] ) ? 1 : 0,
			'shipping_available'  => ! empty( $data['shipping_available'] ) ? 1 : 0,
			'virtual_available'   => ! empty( $data['virtual_available'] ) ? 1 : 0,
			'onsite_available'    => ! empty( $data['onsite_available'] ) ? 1 : 0,
			'delivery_radius_miles' => absint( $data['delivery_radius_miles'] ?? 0 ),
			'delivery_fee'        => self::sanitize_price( $data['delivery_fee'] ?? 0 ),
			'shipping_fee'        => self::sanitize_price( $data['shipping_fee'] ?? 0 ),
			'service_radius_miles' => absint( $data['service_radius_miles'] ?? 0 ),

			// Time constraints.
			'min_rental_days'     => absint( $data['min_rental_days'] ?? 1 ),
			'max_rental_days'     => absint( $data['max_rental_days'] ?? 365 ),
			'min_booking_hours'   => floatval( $data['min_booking_hours'] ?? 1 ),
			'lead_time_hours'     => absint( $data['lead_time_hours'] ?? 24 ),
			'turnaround_buffer_hours' => absint( $data['turnaround_buffer_hours'] ?? 4 ),

			// Policy links.
			'cancellation_policy_id' => absint( $data['cancellation_policy_id'] ?? 0 ) ?: null,
			'insurance_policy_id'    => absint( $data['insurance_policy_id'] ?? 0 ) ?: null,
			'return_policy_id'       => absint( $data['return_policy_id'] ?? 0 ) ?: null,

			// Text fields.
			'late_fee_rule'          => sanitize_textarea_field( $data['late_fee_rule'] ?? '' ),
			'included_accessories'   => sanitize_textarea_field( $data['included_accessories'] ?? '' ),
			'optional_accessories'   => sanitize_textarea_field( $data['optional_accessories'] ?? '' ),
			'safety_notes'           => sanitize_textarea_field( $data['safety_notes'] ?? '' ),
			'deliverables'           => sanitize_textarea_field( $data['deliverables'] ?? '' ),
			'prep_checklist'         => sanitize_textarea_field( $data['prep_checklist'] ?? '' ),
			'certification_level'    => sanitize_text_field( $data['certification_level'] ?? '' ),
			'warranty_disclaimer'    => sanitize_textarea_field( $data['warranty_disclaimer'] ?? '' ),
			'offers_allowed'         => ! empty( $data['offers_allowed'] ) ? 1 : 0,
			'travel_fee_rules'       => sanitize_textarea_field( $data['travel_fee_rules'] ?? '' ),

			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		];

		$result = $wpdb->insert( Core::table( 'listings' ), $insert );

		if ( false === $result ) {
			return false;
		}

		$listing_id = (int) $wpdb->insert_id;

		// Store meta.
		if ( ! empty( $data['meta'] ) && is_array( $data['meta'] ) ) {
			foreach ( $data['meta'] as $key => $value ) {
				self::update_meta( $listing_id, sanitize_key( $key ), $value );
			}
		}

		AuditLogger::log( 'listing_created', 'listing', $listing_id, null, StatusEnums::LISTING_DRAFT );

		do_action( 'jqme_listing_created', $listing_id, $provider_id, $listing_type );

		return $listing_id;
	}

	/**
	 * Update listing fields.
	 */
	public static function update( int $listing_id, array $data ): bool {
		global $wpdb;

		$listing = self::get( $listing_id );
		if ( ! $listing ) {
			return false;
		}

		// Don't allow type changes after creation.
		unset( $data['listing_type'], $data['provider_id'] );

		$update = [ 'updated_at' => current_time( 'mysql' ) ];

		// Sanitize each field based on its type.
		$text_fields = [
			'title', 'category', 'subcategory', 'brand', 'model',
			'serial_number', 'condition_grade', 'certification_level',
		];
		$textarea_fields = [
			'description', 'late_fee_rule', 'included_accessories', 'optional_accessories',
			'safety_notes', 'deliverables', 'prep_checklist', 'warranty_disclaimer',
			'travel_fee_rules',
		];
		$price_fields = [
			'day_rate', 'weekend_rate', 'week_rate', 'month_rate', 'asking_price',
			'hourly_rate', 'half_day_rate', 'full_day_rate', 'deposit_amount',
			'delivery_fee', 'shipping_fee',
		];
		$int_fields = [
			'quantity', 'delivery_radius_miles', 'service_radius_miles',
			'min_rental_days', 'max_rental_days', 'lead_time_hours', 'turnaround_buffer_hours',
		];
		$bool_fields = [
			'pickup_available', 'delivery_available', 'shipping_available',
			'virtual_available', 'onsite_available', 'offers_allowed', 'featured',
		];

		foreach ( $text_fields as $f ) {
			if ( array_key_exists( $f, $data ) ) {
				$update[ $f ] = sanitize_text_field( $data[ $f ] );
			}
		}
		foreach ( $textarea_fields as $f ) {
			if ( array_key_exists( $f, $data ) ) {
				$update[ $f ] = 'description' === $f ? wp_kses_post( $data[ $f ] ) : sanitize_textarea_field( $data[ $f ] );
			}
		}
		foreach ( $price_fields as $f ) {
			if ( array_key_exists( $f, $data ) ) {
				$update[ $f ] = self::sanitize_price( $data[ $f ] );
			}
		}
		foreach ( $int_fields as $f ) {
			if ( array_key_exists( $f, $data ) ) {
				$update[ $f ] = absint( $data[ $f ] );
			}
		}
		foreach ( $bool_fields as $f ) {
			if ( array_key_exists( $f, $data ) ) {
				$update[ $f ] = ! empty( $data[ $f ] ) ? 1 : 0;
			}
		}

		if ( array_key_exists( 'min_booking_hours', $data ) ) {
			$update['min_booking_hours'] = floatval( $data['min_booking_hours'] );
		}

		if ( isset( $data['slug'] ) ) {
			$update['slug'] = sanitize_title( $data['slug'] );
		}

		$result = $wpdb->update(
			Core::table( 'listings' ),
			$update,
			[ 'id' => $listing_id ]
		);

		if ( false !== $result ) {
			AuditLogger::log( 'listing_updated', 'listing', $listing_id );
			do_action( 'jqme_listing_updated', $listing_id );
		}

		return false !== $result;
	}

	/**
	 * Transition listing status with validation and audit logging.
	 */
	public static function set_status( int $listing_id, string $new_status, ?string $reason = null ): bool {
		global $wpdb;

		$listing = self::get( $listing_id );
		if ( ! $listing ) {
			return false;
		}

		$valid = array_keys( StatusEnums::listing_statuses() );
		if ( ! in_array( $new_status, $valid, true ) ) {
			return false;
		}

		$old_status = $listing->status;
		if ( $old_status === $new_status ) {
			return true;
		}

		$update = [
			'status'     => $new_status,
			'updated_at' => current_time( 'mysql' ),
		];

		if ( StatusEnums::LISTING_PUBLISHED === $new_status && ! $listing->published_at ) {
			$update['published_at'] = current_time( 'mysql' );
		}

		$result = $wpdb->update(
			Core::table( 'listings' ),
			$update,
			[ 'id' => $listing_id ]
		);

		if ( false === $result ) {
			return false;
		}

		AuditLogger::status_change( 'listing', $listing_id, $old_status, $new_status, $reason );

		do_action( 'jqme_listing_status_changed', $listing_id, $old_status, $new_status );
		do_action( "jqme_listing_{$new_status}", $listing_id, $old_status );

		return true;
	}

	/**
	 * Submit a listing for review.
	 */
	public static function submit( int $listing_id ): bool {
		$listing = self::get( $listing_id );
		if ( ! $listing ) {
			return false;
		}

		// Only drafts and needs_changes can be submitted.
		$submittable = [ StatusEnums::LISTING_DRAFT, StatusEnums::LISTING_NEEDS_CHANGES ];
		if ( ! in_array( $listing->status, $submittable, true ) ) {
			return false;
		}

		// Equipment rentals/sales require serial number before submission.
		if ( in_array( $listing->listing_type, [ StatusEnums::TYPE_EQUIPMENT_RENTAL, StatusEnums::TYPE_EQUIPMENT_SALE ], true ) ) {
			if ( Settings::get( 'equipment_verification', 'serial_required' ) && empty( $listing->serial_number ) ) {
				return false;
			}
		}

		return self::set_status( $listing_id, StatusEnums::LISTING_SUBMITTED );
	}

	/**
	 * Admin: approve a listing (optionally after verification).
	 */
	public static function approve( int $listing_id ): bool {
		$listing = self::get( $listing_id );
		if ( ! $listing ) {
			return false;
		}

		// Equipment needs verification first.
		if ( in_array( $listing->listing_type, [ StatusEnums::TYPE_EQUIPMENT_RENTAL, StatusEnums::TYPE_EQUIPMENT_SALE ], true ) ) {
			if ( Settings::get( 'equipment_verification', 'serial_admin_review_required' ) ) {
				$verification = Verification::get_for_listing( $listing_id );
				if ( ! $verification || StatusEnums::VERIFY_VERIFIED !== $verification->status ) {
					// Move to verified first, then publish.
					return self::set_status( $listing_id, StatusEnums::LISTING_VERIFIED );
				}
			}
		}

		return self::set_status( $listing_id, StatusEnums::LISTING_PUBLISHED );
	}

	/**
	 * Admin: request changes on a listing.
	 */
	public static function request_changes( int $listing_id, string $reason = '' ): bool {
		return self::set_status( $listing_id, StatusEnums::LISTING_NEEDS_CHANGES, $reason );
	}

	/**
	 * Delete a listing (soft delete = archive).
	 */
	public static function archive( int $listing_id ): bool {
		return self::set_status( $listing_id, StatusEnums::LISTING_ARCHIVED );
	}

	/**
	 * Query listings with filters.
	 */
	public static function query( array $args = [] ): array {
		global $wpdb;

		$defaults = [
			'provider_id'  => 0,
			'listing_type' => '',
			'status'       => '',
			'category'     => '',
			'search'       => '',
			'featured'     => null,
			'orderby'      => 'created_at',
			'order'        => 'DESC',
			'limit'        => 20,
			'offset'       => 0,
		];

		$args  = wp_parse_args( $args, $defaults );
		$table = Core::table( 'listings' );
		$ptable = Core::table( 'providers' );
		$where = [];
		$values = [];

		if ( $args['provider_id'] ) {
			$where[]  = 'l.provider_id = %d';
			$values[] = $args['provider_id'];
		}
		if ( $args['listing_type'] ) {
			$where[]  = 'l.listing_type = %s';
			$values[] = $args['listing_type'];
		}
		if ( $args['status'] ) {
			if ( is_array( $args['status'] ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $args['status'] ), '%s' ) );
				$where[]      = "l.status IN ({$placeholders})";
				$values       = array_merge( $values, $args['status'] );
			} else {
				$where[]  = 'l.status = %s';
				$values[] = $args['status'];
			}
		}
		if ( $args['category'] ) {
			$where[]  = 'l.category = %s';
			$values[] = $args['category'];
		}
		if ( $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(l.title LIKE %s OR l.description LIKE %s OR l.brand LIKE %s OR l.model LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}
		if ( null !== $args['featured'] ) {
			$where[]  = 'l.featured = %d';
			$values[] = $args['featured'] ? 1 : 0;
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$allowed_orderby = [ 'created_at', 'published_at', 'title', 'day_rate', 'asking_price', 'average_rating', 'view_count' ];
		$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$sql = "SELECT l.*, p.company_name as provider_name, p.city as provider_city, p.state as provider_state
				FROM {$table} l
				LEFT JOIN {$ptable} p ON l.provider_id = p.id
				{$where_sql}
				ORDER BY l.{$orderby} {$order}
				LIMIT %d OFFSET %d";

		$values[] = $args['limit'];
		$values[] = $args['offset'];

		return $wpdb->get_results( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Count listings with optional filters.
	 */
	public static function count( array $filters = [] ): int {
		global $wpdb;

		$table  = Core::table( 'listings' );
		$where  = [];
		$values = [];

		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $filters['status'];
		}
		if ( ! empty( $filters['listing_type'] ) ) {
			$where[]  = 'listing_type = %s';
			$values[] = $filters['listing_type'];
		}
		if ( ! empty( $filters['provider_id'] ) ) {
			$where[]  = 'provider_id = %d';
			$values[] = $filters['provider_id'];
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		if ( ! empty( $values ) ) {
			return (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} {$where_sql}",
				$values
			) );
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where_sql}" ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Count listings for a specific provider.
	 */
	public static function count_by_provider( int $provider_id ): int {
		return self::count( [ 'provider_id' => $provider_id ] );
	}

	/**
	 * Generate a unique slug from a title.
	 */
	private static function generate_unique_slug( string $title ): string {
		global $wpdb;

		$slug  = sanitize_title( $title );
		$table = Core::table( 'listings' );

		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE slug = %s",
			$slug
		) );

		if ( $existing > 0 ) {
			$slug .= '-' . wp_generate_password( 4, false, false );
		}

		return $slug;
	}

	/**
	 * Sanitize a price value.
	 */
	private static function sanitize_price( mixed $value ): ?float {
		if ( null === $value || '' === $value ) {
			return null;
		}
		$price = floatval( $value );
		return $price >= 0 ? round( $price, 2 ) : null;
	}

	/* ---------------------------------------------------------------
	 * LISTING ASSETS
	 * ------------------------------------------------------------- */

	/**
	 * Add an asset (image/document) to a listing.
	 */
	public static function add_asset( int $listing_id, array $data ): int|false {
		global $wpdb;

		$result = $wpdb->insert( Core::table( 'listing_assets' ), [
			'listing_id'  => $listing_id,
			'asset_type'  => sanitize_text_field( $data['asset_type'] ?? 'image' ),
			'file_url'    => esc_url_raw( $data['file_url'] ?? '' ),
			'file_name'   => sanitize_file_name( $data['file_name'] ?? '' ),
			'mime_type'   => sanitize_text_field( $data['mime_type'] ?? '' ),
			'sort_order'  => absint( $data['sort_order'] ?? 0 ),
			'is_primary'  => ! empty( $data['is_primary'] ) ? 1 : 0,
			'uploaded_at' => current_time( 'mysql' ),
		] );

		return false !== $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get all assets for a listing.
	 */
	public static function get_assets( int $listing_id, string $type = '' ): array {
		global $wpdb;

		$table = Core::table( 'listing_assets' );

		if ( $type ) {
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$table} WHERE listing_id = %d AND asset_type = %s ORDER BY sort_order ASC",
				$listing_id,
				$type
			) );
		}

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE listing_id = %d ORDER BY sort_order ASC",
			$listing_id
		) );
	}

	/**
	 * Delete an asset.
	 */
	public static function delete_asset( int $asset_id ): bool {
		global $wpdb;
		return false !== $wpdb->delete( Core::table( 'listing_assets' ), [ 'id' => $asset_id ] );
	}

	/* ---------------------------------------------------------------
	 * LISTING META
	 * ------------------------------------------------------------- */

	public static function get_meta( int $listing_id, string $key, bool $single = true ): mixed {
		global $wpdb;
		$table = Core::table( 'listing_meta' );
		if ( $single ) {
			return $wpdb->get_var( $wpdb->prepare(
				"SELECT meta_value FROM {$table} WHERE listing_id = %d AND meta_key = %s LIMIT 1",
				$listing_id, $key
			) );
		}
		return $wpdb->get_col( $wpdb->prepare(
			"SELECT meta_value FROM {$table} WHERE listing_id = %d AND meta_key = %s",
			$listing_id, $key
		) );
	}

	public static function update_meta( int $listing_id, string $key, mixed $value ): bool {
		global $wpdb;
		$table    = Core::table( 'listing_meta' );
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT meta_id FROM {$table} WHERE listing_id = %d AND meta_key = %s LIMIT 1",
			$listing_id, $key
		) );
		$meta_value = is_array( $value ) || is_object( $value ) ? wp_json_encode( $value ) : $value;
		if ( $existing ) {
			return false !== $wpdb->update( $table, [ 'meta_value' => $meta_value ], [ 'meta_id' => $existing ] );
		}
		return false !== $wpdb->insert( $table, [
			'listing_id' => $listing_id,
			'meta_key'   => $key,
			'meta_value' => $meta_value,
		] );
	}

	public static function delete_meta( int $listing_id, string $key ): bool {
		global $wpdb;
		return false !== $wpdb->delete( Core::table( 'listing_meta' ), [
			'listing_id' => $listing_id,
			'meta_key'   => $key,
		] );
	}
}
