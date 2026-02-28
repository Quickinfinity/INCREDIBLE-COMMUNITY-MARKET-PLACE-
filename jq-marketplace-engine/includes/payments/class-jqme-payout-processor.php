<?php
/**
 * Payout processor — manages provider payouts with configurable delays.
 *
 * Payout flow:
 * 1. Booking completes → payout record created with hold_until date
 * 2. Claim window passes → payout moves to queued
 * 3. Cron processes queued payouts → sends to provider via gateway
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME\Payments;

use JQME\Core;
use JQME\AuditLogger;
use JQME\StatusEnums;
use JQME\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PayoutProcessor {

	/**
	 * Create a payout record for a completed booking.
	 * The payout won't be sent until the hold period expires.
	 */
	public static function create_pending( int $booking_id ): int|false {
		global $wpdb;

		$booking = \JQME\Bookings\Booking::get( $booking_id );
		if ( ! $booking ) {
			return false;
		}

		// Don't create duplicate payouts.
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM " . Core::table( 'payouts' ) . " WHERE booking_id = %d AND status NOT IN ('reversed', 'failed')",
			$booking_id
		) );
		if ( $existing ) {
			return (int) $existing;
		}

		$delay_days   = (int) Settings::get( 'payments', 'payout_delay_days' );
		$claim_hours  = (int) Settings::get( 'claims', 'claim_window_hours' );

		// Hold until = max(payout delay, claim window).
		$hold_seconds = max( $delay_days * DAY_IN_SECONDS, $claim_hours * HOUR_IN_SECONDS );
		$hold_until   = gmdate( 'Y-m-d H:i:s', time() + $hold_seconds );

		// Check if provider is flagged.
		$provider = \JQME\Providers\Provider::get( $booking->provider_id );
		$is_flagged = $provider && in_array( $provider->status, [
			StatusEnums::PROVIDER_RESTRICTED,
			StatusEnums::PROVIDER_SUSPENDED,
		], true );

		$initial_status = $is_flagged && Settings::get( 'payments', 'payout_hold_on_flagged' )
			? StatusEnums::PAYOUT_MANUAL_REVIEW
			: StatusEnums::PAYOUT_PENDING_HOLD;

		$result = $wpdb->insert( Core::table( 'payouts' ), [
			'provider_id' => $booking->provider_id,
			'booking_id'  => $booking_id,
			'amount'      => $booking->provider_payout,
			'currency'    => $booking->currency,
			'status'      => $initial_status,
			'hold_until'  => $hold_until,
			'created_at'  => current_time( 'mysql' ),
			'updated_at'  => current_time( 'mysql' ),
		] );

		if ( false === $result ) {
			return false;
		}

		$payout_id = (int) $wpdb->insert_id;

		AuditLogger::log( 'payout_created', 'payout', $payout_id, null, $initial_status,
			sprintf( 'Booking #%d, amount: $%.2f, hold until: %s', $booking_id, $booking->provider_payout, $hold_until )
		);

		return $payout_id;
	}

	/**
	 * Process payouts that are ready to be sent.
	 * Called by cron (jqme_hourly_tasks).
	 */
	public static function process_queue( PaymentGateway $gateway ): array {
		global $wpdb;

		$table = Core::table( 'payouts' );
		$now   = current_time( 'mysql' );

		// Move held payouts to queued if hold period expired.
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET status = %s, updated_at = %s WHERE status = %s AND hold_until <= %s",
			StatusEnums::PAYOUT_QUEUED,
			$now,
			StatusEnums::PAYOUT_PENDING_HOLD,
			$now
		) );

		// Also move claim-window payouts if no open claims.
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$table} p SET p.status = %s, p.updated_at = %s
			 WHERE p.status = %s AND p.hold_until <= %s
			 AND NOT EXISTS (
			   SELECT 1 FROM " . Core::table( 'claims' ) . " c
			   WHERE c.booking_id = p.booking_id
			   AND c.status NOT IN ('closed', 'denied', 'withdrawn')
			 )",
			StatusEnums::PAYOUT_QUEUED,
			$now,
			StatusEnums::PAYOUT_PENDING_CLAIM,
			$now
		) );

		// Get all queued payouts.
		$queued = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.*, pr.payout_account_id
			 FROM {$table} p
			 LEFT JOIN " . Core::table( 'providers' ) . " pr ON p.provider_id = pr.id
			 WHERE p.status = %s
			 ORDER BY p.created_at ASC
			 LIMIT 50",
			StatusEnums::PAYOUT_QUEUED
		) );

		$results = [ 'sent' => 0, 'failed' => 0 ];

		foreach ( $queued as $payout ) {
			if ( empty( $payout->payout_account_id ) ) {
				self::set_status( $payout->id, StatusEnums::PAYOUT_FAILED, 'No connected payout account' );
				$results['failed']++;
				continue;
			}

			$gateway_result = $gateway->create_payout(
				$payout->payout_account_id,
				FeeCalculator::to_cents( floatval( $payout->amount ) ),
				$payout->currency
			);

			if ( $gateway_result ) {
				$wpdb->update( $table, [
					'status'            => StatusEnums::PAYOUT_SENT,
					'gateway'           => $gateway->get_id(),
					'gateway_payout_id' => $gateway_result['id'],
					'sent_at'           => current_time( 'mysql' ),
					'updated_at'        => current_time( 'mysql' ),
				], [ 'id' => $payout->id ] );

				AuditLogger::log( 'payout_sent', 'payout', $payout->id, StatusEnums::PAYOUT_QUEUED, StatusEnums::PAYOUT_SENT );

				$results['sent']++;
			} else {
				self::set_status( $payout->id, StatusEnums::PAYOUT_FAILED, 'Gateway payout failed' );
				$results['failed']++;
			}
		}

		return $results;
	}

	/**
	 * Set payout status.
	 */
	public static function set_status( int $payout_id, string $new_status, ?string $notes = null ): bool {
		global $wpdb;

		$payout = self::get( $payout_id );
		if ( ! $payout ) {
			return false;
		}

		$old_status = $payout->status;

		$update = [
			'status'     => $new_status,
			'updated_at' => current_time( 'mysql' ),
		];

		if ( $notes ) {
			$update['notes'] = sanitize_textarea_field( $notes );
		}

		if ( StatusEnums::PAYOUT_FAILED === $new_status ) {
			$update['failed_at']      = current_time( 'mysql' );
			$update['failure_reason'] = $notes ?? '';
		}

		$result = $wpdb->update( Core::table( 'payouts' ), $update, [ 'id' => $payout_id ] );

		if ( false !== $result ) {
			AuditLogger::status_change( 'payout', $payout_id, $old_status, $new_status, $notes );
		}

		return false !== $result;
	}

	/**
	 * Get a payout record.
	 */
	public static function get( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . Core::table( 'payouts' ) . " WHERE id = %d", $id
		) ) ?: null;
	}

	/**
	 * Query payouts.
	 */
	public static function query( array $args = [] ): array {
		global $wpdb;

		$defaults = [
			'provider_id' => 0,
			'status'      => '',
			'limit'       => 20,
			'offset'      => 0,
		];

		$args  = wp_parse_args( $args, $defaults );
		$table = Core::table( 'payouts' );
		$ptable = Core::table( 'providers' );
		$btable = Core::table( 'bookings' );
		$where = [];
		$values = [];

		if ( $args['provider_id'] ) {
			$where[]  = 'p.provider_id = %d';
			$values[] = $args['provider_id'];
		}
		if ( $args['status'] ) {
			$where[]  = 'p.status = %s';
			$values[] = $args['status'];
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$sql = "SELECT p.*, pr.company_name as provider_name, b.booking_number
				FROM {$table} p
				LEFT JOIN {$ptable} pr ON p.provider_id = pr.id
				LEFT JOIN {$btable} b ON p.booking_id = b.id
				{$where_sql}
				ORDER BY p.created_at DESC
				LIMIT %d OFFSET %d";

		$values[] = $args['limit'];
		$values[] = $args['offset'];

		return $wpdb->get_results( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Count payouts by status.
	 */
	public static function count_by_status( string $status = '' ): int {
		global $wpdb;
		$table = Core::table( 'payouts' );
		if ( $status ) {
			return (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status = %s", $status
			) );
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
}
