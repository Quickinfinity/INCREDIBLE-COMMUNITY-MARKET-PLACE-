<?php
/**
 * Deposit manager — authorize, capture, partial capture, and release deposits.
 *
 * Equipment rentals require a security deposit that is:
 * 1. Authorized at booking confirmation
 * 2. Held during the rental period
 * 3. Captured (full or partial) if damage claim is filed
 * 4. Released after successful return with no claims
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

class DepositManager {

	/**
	 * Create a deposit record and authorize with the gateway.
	 */
	public static function authorize( int $booking_id, float $amount, PaymentGateway $gateway ): int|false {
		global $wpdb;

		$booking = \JQME\Bookings\Booking::get( $booking_id );
		if ( ! $booking || $amount <= 0 ) {
			return false;
		}

		// Get provider's connected account.
		$provider = \JQME\Providers\Provider::get( $booking->provider_id );
		if ( ! $provider || empty( $provider->payout_account_id ) ) {
			return false;
		}

		// Authorize with the payment gateway (manual capture).
		$payment = $gateway->create_payment( [
			'amount_cents'      => FeeCalculator::to_cents( $amount ),
			'currency'          => $booking->currency,
			'connected_account' => $provider->payout_account_id,
			'platform_fee_cents' => 0, // No platform fee on deposits.
			'description'       => sprintf( 'Security deposit for booking %s', $booking->booking_number ),
			'capture'           => false, // Authorize only.
			'metadata'          => [
				'booking_id'     => $booking_id,
				'booking_number' => $booking->booking_number,
				'type'           => 'deposit',
			],
		] );

		if ( ! $payment ) {
			return false;
		}

		// Calculate auth expiry (Stripe: 7 days for most, up to 31 for specific).
		$auth_days = 7;
		$auth_expires = gmdate( 'Y-m-d H:i:s', time() + ( $auth_days * DAY_IN_SECONDS ) );

		$result = $wpdb->insert( Core::table( 'deposits' ), [
			'booking_id'      => $booking_id,
			'amount'          => $amount,
			'captured_amount' => 0,
			'released_amount' => 0,
			'currency'        => $booking->currency,
			'status'          => StatusEnums::DEPOSIT_AUTHORIZED,
			'gateway_auth_id' => $payment['id'],
			'auth_expires_at' => $auth_expires,
			'created_at'      => current_time( 'mysql' ),
			'updated_at'      => current_time( 'mysql' ),
		] );

		if ( false === $result ) {
			return false;
		}

		$deposit_id = (int) $wpdb->insert_id;

		// Record the authorization transaction.
		\JQME\Bookings\Booking::record_transaction( $booking_id, [
			'type'       => StatusEnums::TXN_AUTHORIZATION,
			'gateway'    => $gateway->get_id(),
			'gateway_id' => $payment['id'],
			'amount'     => $amount,
			'currency'   => $booking->currency,
			'status'     => 'authorized',
			'metadata'   => [ 'deposit_id' => $deposit_id ],
		] );

		AuditLogger::log( 'deposit_authorized', 'deposit', $deposit_id, null, $amount, "Booking #{$booking_id}" );

		return $deposit_id;
	}

	/**
	 * Capture a deposit (full or partial) — typically after a damage claim.
	 */
	public static function capture( int $deposit_id, float $capture_amount, PaymentGateway $gateway ): bool {
		global $wpdb;

		$deposit = self::get( $deposit_id );
		if ( ! $deposit || StatusEnums::DEPOSIT_AUTHORIZED !== $deposit->status ) {
			return false;
		}

		$max_capturable = floatval( $deposit->amount ) - floatval( $deposit->captured_amount );
		$capture_amount = min( $capture_amount, $max_capturable );

		if ( $capture_amount <= 0 ) {
			return false;
		}

		$result = $gateway->capture_payment(
			$deposit->gateway_auth_id,
			FeeCalculator::to_cents( $capture_amount )
		);

		if ( ! $result ) {
			return false;
		}

		$new_captured = floatval( $deposit->captured_amount ) + $capture_amount;
		$is_full      = $new_captured >= floatval( $deposit->amount );

		$wpdb->update( Core::table( 'deposits' ), [
			'captured_amount' => $new_captured,
			'status'          => $is_full ? StatusEnums::DEPOSIT_CAPTURED : StatusEnums::DEPOSIT_PARTIALLY_CAPTURED,
			'captured_at'     => current_time( 'mysql' ),
			'updated_at'      => current_time( 'mysql' ),
		], [ 'id' => $deposit_id ] );

		// Record the capture transaction.
		\JQME\Bookings\Booking::record_transaction( $deposit->booking_id, [
			'type'       => StatusEnums::TXN_CAPTURE,
			'gateway'    => $gateway->get_id(),
			'gateway_id' => $result['id'] ?? $deposit->gateway_auth_id,
			'amount'     => $capture_amount,
			'currency'   => $deposit->currency,
			'status'     => 'captured',
			'metadata'   => [ 'deposit_id' => $deposit_id ],
		] );

		AuditLogger::log( 'deposit_captured', 'deposit', $deposit_id, $deposit->captured_amount, $new_captured );

		do_action( 'jqme_deposit_captured', $deposit_id, $capture_amount );

		return true;
	}

	/**
	 * Release a deposit (cancel the authorization).
	 */
	public static function release( int $deposit_id, PaymentGateway $gateway, string $reason = '' ): bool {
		global $wpdb;

		$deposit = self::get( $deposit_id );
		if ( ! $deposit ) {
			return false;
		}

		// Can only release authorized or partially captured deposits.
		$releasable = [ StatusEnums::DEPOSIT_AUTHORIZED, StatusEnums::DEPOSIT_PARTIALLY_CAPTURED ];
		if ( ! in_array( $deposit->status, $releasable, true ) ) {
			return false;
		}

		$remaining = floatval( $deposit->amount ) - floatval( $deposit->captured_amount );

		if ( $remaining > 0 ) {
			// Cancel the remaining authorization.
			$gateway->cancel_payment( $deposit->gateway_auth_id );
		}

		$wpdb->update( Core::table( 'deposits' ), [
			'released_amount' => $remaining,
			'status'          => StatusEnums::DEPOSIT_RELEASED,
			'released_at'     => current_time( 'mysql' ),
			'release_reason'  => sanitize_text_field( $reason ),
			'updated_at'      => current_time( 'mysql' ),
		], [ 'id' => $deposit_id ] );

		AuditLogger::log( 'deposit_released', 'deposit', $deposit_id, null, $remaining, $reason );

		do_action( 'jqme_deposit_released', $deposit_id, $remaining );

		return true;
	}

	/**
	 * Auto-release deposits after the configured window.
	 * Called by cron (jqme_hourly_tasks).
	 */
	public static function auto_release_expired( PaymentGateway $gateway ): int {
		global $wpdb;

		$days  = (int) Settings::get( 'payments', 'deposit_auto_release_days' );
		$table = Core::table( 'deposits' );

		// Find deposits that are still authorized and past the auto-release window.
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$deposits = $wpdb->get_results( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE status = %s AND created_at < %s",
			StatusEnums::DEPOSIT_AUTHORIZED,
			$cutoff
		) );

		$released = 0;
		foreach ( $deposits as $d ) {
			if ( self::release( $d->id, $gateway, 'auto_release_expired' ) ) {
				$released++;
			}
		}

		return $released;
	}

	/**
	 * Get a deposit record.
	 */
	public static function get( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . Core::table( 'deposits' ) . " WHERE id = %d", $id
		) ) ?: null;
	}

	/**
	 * Get deposits for a booking.
	 */
	public static function get_for_booking( int $booking_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM " . Core::table( 'deposits' ) . " WHERE booking_id = %d ORDER BY created_at DESC",
			$booking_id
		) );
	}
}
