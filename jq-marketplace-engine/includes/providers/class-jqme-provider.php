<?php
/**
 * Provider model — CRUD and business logic for marketplace providers.
 *
 * Handles application submission, approval/rejection, profile updates,
 * status transitions, and provider queries.
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME\Providers;

use JQME\Core;
use JQME\AuditLogger;
use JQME\StatusEnums;
use JQME\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Provider {

	/**
	 * Get a provider record by ID.
	 */
	public static function get( int $id ): ?object {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . Core::table( 'providers' ) . " WHERE id = %d",
			$id
		) );
		return $row ?: null;
	}

	/**
	 * Get a provider record by WordPress user ID.
	 */
	public static function get_by_user( int $user_id ): ?object {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . Core::table( 'providers' ) . " WHERE user_id = %d",
			$user_id
		) );
		return $row ?: null;
	}

	/**
	 * Submit a new provider application.
	 *
	 * @param int   $user_id  WordPress user ID.
	 * @param array $data     Application data.
	 * @return int|false Provider ID on success, false on failure.
	 */
	public static function apply( int $user_id, array $data ): int|false {
		global $wpdb;

		// Prevent duplicate applications.
		$existing = self::get_by_user( $user_id );
		if ( $existing ) {
			return false;
		}

		$allowed_types = [];
		if ( ! empty( $data['listing_types'] ) && is_array( $data['listing_types'] ) ) {
			$valid_types = array_keys( StatusEnums::listing_types() );
			$allowed_types = array_intersect( $data['listing_types'], $valid_types );
		}

		$insert = [
			'user_id'               => $user_id,
			'company_name'          => sanitize_text_field( $data['company_name'] ?? '' ),
			'contact_name'          => sanitize_text_field( $data['contact_name'] ?? '' ),
			'contact_email'         => sanitize_email( $data['contact_email'] ?? '' ),
			'contact_phone'         => sanitize_text_field( $data['contact_phone'] ?? '' ),
			'address_line1'         => sanitize_text_field( $data['address_line1'] ?? '' ),
			'address_line2'         => sanitize_text_field( $data['address_line2'] ?? '' ),
			'city'                  => sanitize_text_field( $data['city'] ?? '' ),
			'state'                 => sanitize_text_field( $data['state'] ?? '' ),
			'zip'                   => sanitize_text_field( $data['zip'] ?? '' ),
			'country'               => sanitize_text_field( $data['country'] ?? 'US' ),
			'service_radius_miles'  => absint( $data['service_radius_miles'] ?? 50 ),
			'can_deliver'           => ! empty( $data['can_deliver'] ) ? 1 : 0,
			'delivery_radius_miles' => absint( $data['delivery_radius_miles'] ?? 0 ),
			'allowed_listing_types' => wp_json_encode( $allowed_types ),
			'status'                => StatusEnums::PROVIDER_PENDING_APPLICATION,
			'applied_at'            => current_time( 'mysql' ),
			'created_at'            => current_time( 'mysql' ),
			'updated_at'            => current_time( 'mysql' ),
		];

		$result = $wpdb->insert( Core::table( 'providers' ), $insert );

		if ( false === $result ) {
			return false;
		}

		$provider_id = (int) $wpdb->insert_id;

		// Store any extra meta (insurance docs, notes, etc.).
		if ( ! empty( $data['meta'] ) && is_array( $data['meta'] ) ) {
			foreach ( $data['meta'] as $key => $value ) {
				self::update_meta( $provider_id, sanitize_key( $key ), $value );
			}
		}

		AuditLogger::log(
			'provider_application_submitted',
			'provider',
			$provider_id,
			null,
			StatusEnums::PROVIDER_PENDING_APPLICATION,
			'New provider application from user #' . $user_id
		);

		do_action( 'jqme_provider_application_submitted', $provider_id, $user_id, $data );

		return $provider_id;
	}

	/**
	 * Transition provider status with validation and audit logging.
	 */
	public static function set_status( int $provider_id, string $new_status, ?string $reason = null ): bool {
		global $wpdb;

		$provider = self::get( $provider_id );
		if ( ! $provider ) {
			return false;
		}

		$valid_statuses = array_keys( StatusEnums::provider_statuses() );
		if ( ! in_array( $new_status, $valid_statuses, true ) ) {
			return false;
		}

		$old_status = $provider->status;

		// Don't update if already in this status.
		if ( $old_status === $new_status ) {
			return true;
		}

		$update = [
			'status'     => $new_status,
			'updated_at' => current_time( 'mysql' ),
		];

		// Set approval timestamp.
		if ( StatusEnums::PROVIDER_APPROVED === $new_status && ! $provider->approved_at ) {
			$update['approved_at'] = current_time( 'mysql' );
		}

		// Store suspension/rejection reason.
		if ( in_array( $new_status, [ StatusEnums::PROVIDER_SUSPENDED, StatusEnums::PROVIDER_REJECTED ], true ) && $reason ) {
			$update['suspension_reason'] = sanitize_textarea_field( $reason );
		}

		$result = $wpdb->update(
			Core::table( 'providers' ),
			$update,
			[ 'id' => $provider_id ],
			null,
			[ '%d' ]
		);

		if ( false === $result ) {
			return false;
		}

		// Assign/remove WordPress role based on status.
		self::sync_user_role( $provider->user_id, $new_status );

		AuditLogger::status_change( 'provider', $provider_id, $old_status, $new_status, $reason );

		do_action( 'jqme_provider_status_changed', $provider_id, $old_status, $new_status );
		do_action( "jqme_provider_{$new_status}", $provider_id, $old_status );

		return true;
	}

	/**
	 * Approve a provider.
	 */
	public static function approve( int $provider_id ): bool {
		return self::set_status( $provider_id, StatusEnums::PROVIDER_APPROVED );
	}

	/**
	 * Reject a provider.
	 */
	public static function reject( int $provider_id, string $reason = '' ): bool {
		return self::set_status( $provider_id, StatusEnums::PROVIDER_REJECTED, $reason );
	}

	/**
	 * Suspend a provider.
	 */
	public static function suspend( int $provider_id, string $reason = '' ): bool {
		return self::set_status( $provider_id, StatusEnums::PROVIDER_SUSPENDED, $reason );
	}

	/**
	 * Update provider profile fields.
	 */
	public static function update_profile( int $provider_id, array $data ): bool {
		global $wpdb;

		$allowed_fields = [
			'company_name', 'contact_name', 'contact_email', 'contact_phone',
			'address_line1', 'address_line2', 'city', 'state', 'zip', 'country',
			'latitude', 'longitude', 'service_radius_miles', 'can_deliver',
			'delivery_radius_miles', 'payout_account_id', 'payout_account_status',
			'insurance_verified', 'insurance_expiry', 'allowed_listing_types',
			'listing_limit',
		];

		$update = [ 'updated_at' => current_time( 'mysql' ) ];

		foreach ( $data as $key => $value ) {
			if ( in_array( $key, $allowed_fields, true ) ) {
				$update[ $key ] = is_array( $value ) ? wp_json_encode( $value ) : $value;
			}
		}

		if ( count( $update ) <= 1 ) {
			return false; // Nothing to update besides timestamp.
		}

		$result = $wpdb->update(
			Core::table( 'providers' ),
			$update,
			[ 'id' => $provider_id ]
		);

		if ( false !== $result ) {
			AuditLogger::log( 'provider_profile_updated', 'provider', $provider_id );
			do_action( 'jqme_provider_profile_updated', $provider_id, $data );
		}

		return false !== $result;
	}

	/**
	 * Sync WordPress user role based on provider status.
	 */
	private static function sync_user_role( int $user_id, string $status ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$active_statuses = [ StatusEnums::PROVIDER_APPROVED, StatusEnums::PROVIDER_RESTRICTED ];

		if ( in_array( $status, $active_statuses, true ) ) {
			if ( ! in_array( 'jqme_provider', $user->roles, true ) ) {
				$user->add_role( 'jqme_provider' );
			}
		} else {
			$user->remove_role( 'jqme_provider' );
		}
	}

	/**
	 * Query providers with filters.
	 */
	public static function query( array $args = [] ): array {
		global $wpdb;

		$defaults = [
			'status'  => '',
			'search'  => '',
			'orderby' => 'created_at',
			'order'   => 'DESC',
			'limit'   => 20,
			'offset'  => 0,
		];

		$args  = wp_parse_args( $args, $defaults );
		$table = Core::table( 'providers' );
		$where = [];
		$values = [];

		if ( $args['status'] ) {
			$where[]  = 'p.status = %s';
			$values[] = $args['status'];
		}

		if ( $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(p.company_name LIKE %s OR p.contact_name LIKE %s OR p.contact_email LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$allowed_orderby = [ 'created_at', 'applied_at', 'company_name', 'status', 'trust_score' ];
		$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$sql = "SELECT p.*, u.user_login, u.display_name
				FROM {$table} p
				LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
				{$where_sql}
				ORDER BY p.{$orderby} {$order}
				LIMIT %d OFFSET %d";

		$values[] = $args['limit'];
		$values[] = $args['offset'];

		return $wpdb->get_results( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Count providers by status.
	 */
	public static function count_by_status( string $status = '' ): int {
		global $wpdb;

		$table = Core::table( 'providers' );

		if ( $status ) {
			return (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status = %s",
				$status
			) );
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Get/set provider meta.
	 */
	public static function get_meta( int $provider_id, string $key, bool $single = true ): mixed {
		global $wpdb;

		$table = Core::table( 'provider_meta' );

		if ( $single ) {
			return $wpdb->get_var( $wpdb->prepare(
				"SELECT meta_value FROM {$table} WHERE provider_id = %d AND meta_key = %s LIMIT 1",
				$provider_id,
				$key
			) );
		}

		return $wpdb->get_col( $wpdb->prepare(
			"SELECT meta_value FROM {$table} WHERE provider_id = %d AND meta_key = %s",
			$provider_id,
			$key
		) );
	}

	public static function update_meta( int $provider_id, string $key, mixed $value ): bool {
		global $wpdb;

		$table    = Core::table( 'provider_meta' );
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT meta_id FROM {$table} WHERE provider_id = %d AND meta_key = %s LIMIT 1",
			$provider_id,
			$key
		) );

		$meta_value = is_array( $value ) || is_object( $value ) ? wp_json_encode( $value ) : $value;

		if ( $existing ) {
			return false !== $wpdb->update(
				$table,
				[ 'meta_value' => $meta_value ],
				[ 'meta_id' => $existing ]
			);
		}

		return false !== $wpdb->insert( $table, [
			'provider_id' => $provider_id,
			'meta_key'    => $key,
			'meta_value'  => $meta_value,
		] );
	}

	public static function delete_meta( int $provider_id, string $key ): bool {
		global $wpdb;
		return false !== $wpdb->delete(
			Core::table( 'provider_meta' ),
			[ 'provider_id' => $provider_id, 'meta_key' => $key ]
		);
	}
}
