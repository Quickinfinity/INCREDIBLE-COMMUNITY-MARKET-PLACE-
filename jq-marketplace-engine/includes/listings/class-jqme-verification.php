<?php
/**
 * Verification model — equipment serial verification and provider certification tracking.
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME\Listings;

use JQME\Core;
use JQME\AuditLogger;
use JQME\StatusEnums;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Verification {

	/**
	 * Get a verification record by ID.
	 */
	public static function get( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . Core::table( 'verifications' ) . " WHERE id = %d",
			$id
		) ) ?: null;
	}

	/**
	 * Get the verification record for a specific listing.
	 */
	public static function get_for_listing( int $listing_id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . Core::table( 'verifications' ) . " WHERE listing_id = %d ORDER BY created_at DESC LIMIT 1",
			$listing_id
		) ) ?: null;
	}

	/**
	 * Submit a verification request for a listing.
	 */
	public static function submit( int $listing_id, int $provider_id, array $data ): int|false {
		global $wpdb;

		$insert = [
			'listing_id'        => $listing_id,
			'provider_id'       => $provider_id,
			'verification_type' => sanitize_text_field( $data['verification_type'] ?? 'serial' ),
			'serial_number'     => sanitize_text_field( $data['serial_number'] ?? '' ),
			'document_urls'     => wp_json_encode( array_map( 'esc_url_raw', $data['document_urls'] ?? [] ) ),
			'notes'             => sanitize_textarea_field( $data['notes'] ?? '' ),
			'status'            => StatusEnums::VERIFY_PENDING_SERIAL,
			'created_at'        => current_time( 'mysql' ),
			'updated_at'        => current_time( 'mysql' ),
		];

		$result = $wpdb->insert( Core::table( 'verifications' ), $insert );

		if ( false === $result ) {
			return false;
		}

		$verification_id = (int) $wpdb->insert_id;

		AuditLogger::log(
			'verification_submitted',
			'verification',
			$verification_id,
			null,
			StatusEnums::VERIFY_PENDING_SERIAL,
			"Listing #{$listing_id}"
		);

		do_action( 'jqme_verification_submitted', $verification_id, $listing_id );

		return $verification_id;
	}

	/**
	 * Set verification status.
	 */
	public static function set_status( int $verification_id, string $new_status, ?string $notes = null, ?int $reviewer_id = null ): bool {
		global $wpdb;

		$verification = self::get( $verification_id );
		if ( ! $verification ) {
			return false;
		}

		$valid = array_keys( StatusEnums::verification_statuses() );
		if ( ! in_array( $new_status, $valid, true ) ) {
			return false;
		}

		$old_status = $verification->status;

		$update = [
			'status'     => $new_status,
			'updated_at' => current_time( 'mysql' ),
		];

		if ( $notes ) {
			$update['notes'] = sanitize_textarea_field( $notes );
		}

		if ( $reviewer_id ) {
			$update['reviewed_by'] = $reviewer_id;
			$update['reviewed_at'] = current_time( 'mysql' );
		}

		$result = $wpdb->update(
			Core::table( 'verifications' ),
			$update,
			[ 'id' => $verification_id ]
		);

		if ( false === $result ) {
			return false;
		}

		AuditLogger::status_change( 'verification', $verification_id, $old_status, $new_status, $notes );

		do_action( 'jqme_verification_status_changed', $verification_id, $old_status, $new_status );

		// If verified, update the listing serial verification and potentially auto-publish.
		if ( StatusEnums::VERIFY_VERIFIED === $new_status && $verification->listing_id ) {
			do_action( 'jqme_equipment_verified', $verification->listing_id, $verification_id );
		}

		return true;
	}

	/**
	 * Approve a verification (admin action).
	 */
	public static function approve( int $verification_id, string $notes = '' ): bool {
		return self::set_status(
			$verification_id,
			StatusEnums::VERIFY_VERIFIED,
			$notes,
			get_current_user_id()
		);
	}

	/**
	 * Reject a verification (admin action).
	 */
	public static function reject( int $verification_id, string $notes = '' ): bool {
		return self::set_status(
			$verification_id,
			StatusEnums::VERIFY_REJECTED,
			$notes,
			get_current_user_id()
		);
	}

	/**
	 * Query verification records.
	 */
	public static function query( array $args = [] ): array {
		global $wpdb;

		$defaults = [
			'status'            => '',
			'verification_type' => '',
			'provider_id'       => 0,
			'orderby'           => 'created_at',
			'order'             => 'DESC',
			'limit'             => 20,
			'offset'            => 0,
		];

		$args  = wp_parse_args( $args, $defaults );
		$table = Core::table( 'verifications' );
		$ltable = Core::table( 'listings' );
		$ptable = Core::table( 'providers' );
		$where = [];
		$values = [];

		if ( $args['status'] ) {
			$where[]  = 'v.status = %s';
			$values[] = $args['status'];
		}
		if ( $args['verification_type'] ) {
			$where[]  = 'v.verification_type = %s';
			$values[] = $args['verification_type'];
		}
		if ( $args['provider_id'] ) {
			$where[]  = 'v.provider_id = %d';
			$values[] = $args['provider_id'];
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$sql = "SELECT v.*, l.title as listing_title, l.listing_type, p.company_name as provider_name
				FROM {$table} v
				LEFT JOIN {$ltable} l ON v.listing_id = l.id
				LEFT JOIN {$ptable} p ON v.provider_id = p.id
				{$where_sql}
				ORDER BY v.created_at DESC
				LIMIT %d OFFSET %d";

		$values[] = $args['limit'];
		$values[] = $args['offset'];

		return $wpdb->get_results( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Count verifications by status.
	 */
	public static function count_by_status( string $status = '' ): int {
		global $wpdb;
		$table = Core::table( 'verifications' );
		if ( $status ) {
			return (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status = %s",
				$status
			) );
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
}
