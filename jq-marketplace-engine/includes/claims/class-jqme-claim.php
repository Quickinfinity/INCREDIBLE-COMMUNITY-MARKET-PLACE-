<?php
/**
 * Claim model — damage claims, disputes, and resolution tracking.
 *
 * Claim flow:
 * 1. Provider files a claim (or system auto-files on condition mismatch)
 * 2. Customer gets response deadline
 * 3. Evidence is submitted by both parties
 * 4. Admin reviews and settles (or auto-closes if customer doesn't respond)
 * 5. Deposit is partially/fully captured based on resolution
 *
 * The platform does NOT act as judge — it facilitates communication
 * and provides deposit capture mechanics.
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME\Claims;

use JQME\Core;
use JQME\AuditLogger;
use JQME\StatusEnums;
use JQME\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Claim {

	/**
	 * Get a claim by ID.
	 */
	public static function get( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . Core::table( 'claims' ) . " WHERE id = %d", $id
		) ) ?: null;
	}

	/**
	 * Get a claim by claim number.
	 */
	public static function get_by_number( string $number ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . Core::table( 'claims' ) . " WHERE claim_number = %s", $number
		) ) ?: null;
	}

	/**
	 * Get claims for a booking.
	 */
	public static function get_for_booking( int $booking_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM " . Core::table( 'claims' ) . " WHERE booking_id = %d ORDER BY created_at DESC",
			$booking_id
		) );
	}

	/**
	 * File a new claim.
	 */
	public static function file( array $data ): int|false {
		global $wpdb;

		$booking_id = absint( $data['booking_id'] ?? 0 );
		$booking    = \JQME\Bookings\Booking::get( $booking_id );

		if ( ! $booking ) {
			return false;
		}

		// Check claim window.
		$claim_hours = (int) Settings::get( 'claims', 'claim_window_hours' );
		if ( $claim_hours > 0 && $booking->completed_at ) {
			$deadline = strtotime( $booking->completed_at ) + ( $claim_hours * HOUR_IN_SECONDS );
			if ( time() > $deadline ) {
				return false; // Claim window expired.
			}
		}

		// Check for existing open claim.
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM " . Core::table( 'claims' ) . " WHERE booking_id = %d AND status NOT IN ('closed', 'denied', 'withdrawn')",
			$booking_id
		) );
		if ( $existing ) {
			return false; // Already has an open claim.
		}

		$user_id = get_current_user_id();
		$provider = \JQME\Providers\Provider::get( $booking->provider_id );
		$role = ( $provider && (int) $provider->user_id === $user_id ) ? 'provider' : 'customer';

		$claim_number = self::generate_claim_number();

		// Set response deadline.
		$response_hours = (int) Settings::get( 'claims', 'response_deadline_hours' );
		$response_deadline = gmdate( 'Y-m-d H:i:s', time() + ( $response_hours * HOUR_IN_SECONDS ) );

		// Auto-close deadline (if no response).
		$auto_close_days = (int) Settings::get( 'claims', 'auto_close_days' );
		$auto_close_at = $auto_close_days > 0
			? gmdate( 'Y-m-d H:i:s', time() + ( $auto_close_days * DAY_IN_SECONDS ) )
			: null;

		$result = $wpdb->insert( Core::table( 'claims' ), [
			'claim_number'              => $claim_number,
			'booking_id'                => $booking_id,
			'filed_by'                  => $user_id,
			'filed_by_role'             => $role,
			'claim_type'                => sanitize_text_field( $data['claim_type'] ?? 'damage' ),
			'description'               => sanitize_textarea_field( $data['description'] ?? '' ),
			'amount_requested'          => floatval( $data['amount_requested'] ?? 0 ),
			'status'                    => StatusEnums::CLAIM_SUBMITTED,
			'customer_response_deadline' => 'provider' === $role ? $response_deadline : null,
			'provider_rebuttal_deadline' => 'customer' === $role ? $response_deadline : null,
			'auto_close_at'             => $auto_close_at,
			'created_at'                => current_time( 'mysql' ),
			'updated_at'                => current_time( 'mysql' ),
		] );

		if ( false === $result ) {
			return false;
		}

		$claim_id = (int) $wpdb->insert_id;

		// Set the booking status to dispute_hold.
		\JQME\Bookings\Booking::set_status( $booking_id, StatusEnums::RENTAL_DISPUTE_HOLD, "Claim #{$claim_number} filed" );

		AuditLogger::log( 'claim_filed', 'claim', $claim_id, null, StatusEnums::CLAIM_SUBMITTED,
			sprintf( 'Booking #%s, type: %s, amount: $%.2f', $booking->booking_number, $data['claim_type'] ?? 'damage', $data['amount_requested'] ?? 0 )
		);

		do_action( 'jqme_claim_filed', $claim_id, $booking_id );

		return $claim_id;
	}

	/**
	 * Transition claim status.
	 */
	public static function set_status( int $claim_id, string $new_status, ?string $notes = null ): bool {
		global $wpdb;

		$claim = self::get( $claim_id );
		if ( ! $claim ) {
			return false;
		}

		$old_status = $claim->status;
		if ( $old_status === $new_status ) {
			return true;
		}

		$update = [
			'status'     => $new_status,
			'updated_at' => current_time( 'mysql' ),
		];

		if ( $notes ) {
			$update['resolution_notes'] = sanitize_textarea_field( $notes );
		}

		// Track resolution.
		$resolved_statuses = [
			StatusEnums::CLAIM_SETTLED_DIRECTLY,
			StatusEnums::CLAIM_DEPOSIT_PARTIAL_CAPTURE,
			StatusEnums::CLAIM_DEPOSIT_FULL_CAPTURE,
			StatusEnums::CLAIM_DENIED,
			StatusEnums::CLAIM_CLOSED,
		];

		if ( in_array( $new_status, $resolved_statuses, true ) ) {
			$update['resolved_by'] = get_current_user_id();
			$update['resolved_at'] = current_time( 'mysql' );
		}

		$result = $wpdb->update( Core::table( 'claims' ), $update, [ 'id' => $claim_id ] );

		if ( false !== $result ) {
			AuditLogger::status_change( 'claim', $claim_id, $old_status, $new_status, $notes );
			do_action( 'jqme_claim_status_changed', $claim_id, $old_status, $new_status );
		}

		return false !== $result;
	}

	/**
	 * Settle a claim with a specific amount.
	 */
	public static function settle( int $claim_id, float $settled_amount, string $notes = '' ): bool {
		global $wpdb;

		$claim = self::get( $claim_id );
		if ( ! $claim ) {
			return false;
		}

		$wpdb->update( Core::table( 'claims' ), [
			'amount_settled' => $settled_amount,
			'updated_at'     => current_time( 'mysql' ),
		], [ 'id' => $claim_id ] );

		// Determine resolution type.
		$booking = \JQME\Bookings\Booking::get( $claim->booking_id );
		$deposit_amount = $booking ? floatval( $booking->deposit_amount ) : 0;

		if ( $settled_amount <= 0 ) {
			$new_status = StatusEnums::CLAIM_DENIED;
		} elseif ( $settled_amount >= $deposit_amount && $deposit_amount > 0 ) {
			$new_status = StatusEnums::CLAIM_DEPOSIT_FULL_CAPTURE;
		} elseif ( $settled_amount > 0 ) {
			$new_status = $deposit_amount > 0
				? StatusEnums::CLAIM_DEPOSIT_PARTIAL_CAPTURE
				: StatusEnums::CLAIM_SETTLED_DIRECTLY;
		} else {
			$new_status = StatusEnums::CLAIM_SETTLED_DIRECTLY;
		}

		self::set_status( $claim_id, $new_status, $notes );

		do_action( 'jqme_claim_settled', $claim_id, $settled_amount );

		return true;
	}

	/**
	 * Add evidence to a claim.
	 */
	public static function add_evidence( int $claim_id, array $data ): int|false {
		global $wpdb;

		$claim = self::get( $claim_id );
		if ( ! $claim ) {
			return false;
		}

		$result = $wpdb->insert( Core::table( 'claim_evidence' ), [
			'claim_id'      => $claim_id,
			'submitted_by'  => get_current_user_id(),
			'evidence_type' => sanitize_text_field( $data['type'] ?? 'photo' ),
			'file_url'      => esc_url_raw( $data['file_url'] ?? '' ),
			'description'   => sanitize_textarea_field( $data['description'] ?? '' ),
			'created_at'    => current_time( 'mysql' ),
		] );

		if ( false === $result ) {
			return false;
		}

		$evidence_id = (int) $wpdb->insert_id;

		AuditLogger::log( 'claim_evidence_added', 'claim', $claim_id, null, $evidence_id );

		return $evidence_id;
	}

	/**
	 * Get evidence for a claim.
	 */
	public static function get_evidence( int $claim_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT e.*, u.display_name as submitter_name
			 FROM " . Core::table( 'claim_evidence' ) . " e
			 LEFT JOIN {$wpdb->users} u ON e.submitted_by = u.ID
			 WHERE e.claim_id = %d ORDER BY e.created_at ASC",
			$claim_id
		) );
	}

	/**
	 * Auto-close expired claims (called by cron).
	 */
	public static function auto_close_expired(): int {
		global $wpdb;

		$table = Core::table( 'claims' );
		$now   = current_time( 'mysql' );

		$expired = $wpdb->get_results( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE auto_close_at IS NOT NULL AND auto_close_at <= %s AND status NOT IN ('closed', 'denied', 'withdrawn', 'settled_directly', 'deposit_partial_capture', 'deposit_full_capture')",
			$now
		) );

		$closed = 0;
		foreach ( $expired as $c ) {
			if ( self::set_status( $c->id, StatusEnums::CLAIM_CLOSED, 'Auto-closed: response deadline expired' ) ) {
				$closed++;
			}
		}

		return $closed;
	}

	/**
	 * Query claims.
	 */
	public static function query( array $args = [] ): array {
		global $wpdb;

		$defaults = [
			'booking_id' => 0,
			'filed_by'   => 0,
			'status'     => '',
			'claim_type' => '',
			'search'     => '',
			'limit'      => 20,
			'offset'     => 0,
		];

		$args  = wp_parse_args( $args, $defaults );
		$table = Core::table( 'claims' );
		$btable = Core::table( 'bookings' );
		$where = [];
		$values = [];

		if ( $args['booking_id'] ) {
			$where[]  = 'c.booking_id = %d';
			$values[] = $args['booking_id'];
		}
		if ( $args['filed_by'] ) {
			$where[]  = 'c.filed_by = %d';
			$values[] = $args['filed_by'];
		}
		if ( $args['status'] ) {
			$where[]  = 'c.status = %s';
			$values[] = $args['status'];
		}
		if ( $args['claim_type'] ) {
			$where[]  = 'c.claim_type = %s';
			$values[] = $args['claim_type'];
		}
		if ( $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(c.claim_number LIKE %s OR c.description LIKE %s)';
			$values[] = $like;
			$values[] = $like;
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$sql = "SELECT c.*, b.booking_number, u.display_name as filed_by_name
				FROM {$table} c
				LEFT JOIN {$btable} b ON c.booking_id = b.id
				LEFT JOIN {$wpdb->users} u ON c.filed_by = u.ID
				{$where_sql}
				ORDER BY c.created_at DESC
				LIMIT %d OFFSET %d";

		$values[] = $args['limit'];
		$values[] = $args['offset'];

		return $wpdb->get_results( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Count claims with optional filters.
	 */
	public static function count( array $filters = [] ): int {
		global $wpdb;
		$table = Core::table( 'claims' );
		$where = [];
		$values = [];

		foreach ( [ 'status', 'claim_type', 'booking_id' ] as $f ) {
			if ( ! empty( $filters[ $f ] ) ) {
				$type = is_numeric( $filters[ $f ] ) ? '%d' : '%s';
				$where[]  = "{$f} = {$type}";
				$values[] = $filters[ $f ];
			}
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		if ( ! empty( $values ) ) {
			return (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} {$where_sql}", $values
			) );
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Generate unique claim number.
	 */
	private static function generate_claim_number(): string {
		return 'CLM-' . strtoupper( wp_generate_password( 8, false, false ) );
	}
}
