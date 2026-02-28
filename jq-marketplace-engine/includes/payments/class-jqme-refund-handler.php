<?php
/**
 * Refund handler — processes full and partial refunds.
 *
 * Refund logic follows the cancellation policy settings:
 * - Full refund if cancelled within the full-refund window
 * - Partial refund within the partial-refund window
 * - No refund outside the window
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

class RefundHandler {

	/**
	 * Process a refund for a booking.
	 *
	 * @param int            $booking_id   Booking to refund.
	 * @param float          $amount       Amount to refund (0 = full).
	 * @param string         $reason       Reason for refund.
	 * @param PaymentGateway $gateway      Payment gateway.
	 * @return bool
	 */
	public static function refund( int $booking_id, float $amount, string $reason, PaymentGateway $gateway ): bool {
		global $wpdb;

		$booking = \JQME\Bookings\Booking::get( $booking_id );
		if ( ! $booking ) {
			return false;
		}

		// Find the original charge transaction.
		$charge = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . Core::table( 'transactions' ) . "
			 WHERE booking_id = %d AND transaction_type = %s AND status IN ('completed', 'succeeded', 'captured')
			 ORDER BY created_at DESC LIMIT 1",
			$booking_id,
			StatusEnums::TXN_CHARGE
		) );

		if ( ! $charge || empty( $charge->gateway_transaction_id ) ) {
			return false;
		}

		$refund_amount = $amount > 0 ? $amount : floatval( $booking->total_amount );
		$is_partial    = $amount > 0 && $amount < floatval( $booking->total_amount );

		$result = $gateway->refund_payment(
			$charge->gateway_transaction_id,
			FeeCalculator::to_cents( $refund_amount ),
			'requested_by_customer'
		);

		if ( ! $result ) {
			// Record the failed refund attempt.
			\JQME\Bookings\Booking::record_transaction( $booking_id, [
				'type'       => $is_partial ? StatusEnums::TXN_PARTIAL_REFUND : StatusEnums::TXN_REFUND,
				'gateway'    => $gateway->get_id(),
				'gateway_id' => '',
				'amount'     => $refund_amount,
				'currency'   => $booking->currency,
				'status'     => 'failed',
				'error'      => 'Gateway refund failed',
			] );
			return false;
		}

		// Record successful refund transaction.
		\JQME\Bookings\Booking::record_transaction( $booking_id, [
			'type'       => $is_partial ? StatusEnums::TXN_PARTIAL_REFUND : StatusEnums::TXN_REFUND,
			'gateway'    => $gateway->get_id(),
			'gateway_id' => $result['id'],
			'amount'     => $refund_amount,
			'currency'   => $booking->currency,
			'status'     => 'completed',
			'metadata'   => [ 'reason' => $reason ],
		] );

		AuditLogger::log( 'refund_processed', 'booking', $booking_id, null, $refund_amount, $reason );

		do_action( 'jqme_refund_processed', $booking_id, $refund_amount, $is_partial );

		return true;
	}

	/**
	 * Calculate refund amount based on cancellation policy.
	 *
	 * @param object $booking The booking object.
	 * @return array{amount: float, percent: int, policy: string}
	 */
	public static function calculate_cancellation_refund( object $booking ): array {
		$now   = time();
		$start = strtotime( $booking->date_start );
		$total = floatval( $booking->total_amount );

		if ( ! $start || $start <= $now ) {
			return [ 'amount' => 0, 'percent' => 0, 'policy' => 'past_start' ];
		}

		$hours_until_start = ( $start - $now ) / HOUR_IN_SECONDS;

		$full_window    = (int) Settings::get( 'cancellation', 'customer_cancel_full_refund_hours' );
		$partial_window = (int) Settings::get( 'cancellation', 'customer_cancel_partial_refund_hours' );
		$partial_pct    = (int) Settings::get( 'cancellation', 'customer_cancel_partial_refund_percent' );

		if ( $hours_until_start >= $full_window ) {
			return [
				'amount'  => $total,
				'percent' => 100,
				'policy'  => 'full_refund',
			];
		}

		if ( $hours_until_start >= $partial_window ) {
			$refund = round( $total * ( $partial_pct / 100 ), 2 );
			return [
				'amount'  => $refund,
				'percent' => $partial_pct,
				'policy'  => 'partial_refund',
			];
		}

		return [ 'amount' => 0, 'percent' => 0, 'policy' => 'no_refund' ];
	}
}
