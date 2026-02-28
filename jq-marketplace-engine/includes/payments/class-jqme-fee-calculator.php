<?php
/**
 * Fee calculator — computes platform fee, processing fee, deposits, and provider payout.
 *
 * All calculations use the admin-configurable settings from the policy engine.
 * Amounts are in dollars (float). Convert to cents when passing to gateway.
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME\Payments;

use JQME\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FeeCalculator {

	/**
	 * Calculate full fee breakdown for a booking.
	 *
	 * @param array $args {
	 *     @type float  $subtotal         Base price before fees.
	 *     @type float  $delivery_fee     Delivery/shipping fee.
	 *     @type float  $travel_fee       Travel fee (services).
	 *     @type float  $deposit_amount   Deposit amount (equipment rentals).
	 *     @type float  $discount         Discount amount.
	 *     @type string $listing_type     equipment_rental|equipment_sale|service_booking
	 * }
	 * @return array Fee breakdown.
	 */
	public static function calculate( array $args ): array {
		$subtotal      = max( 0, floatval( $args['subtotal'] ?? 0 ) );
		$delivery_fee  = max( 0, floatval( $args['delivery_fee'] ?? 0 ) );
		$travel_fee    = max( 0, floatval( $args['travel_fee'] ?? 0 ) );
		$shipping_fee  = max( 0, floatval( $args['shipping_fee'] ?? 0 ) );
		$deposit       = max( 0, floatval( $args['deposit_amount'] ?? 0 ) );
		$discount      = max( 0, floatval( $args['discount'] ?? 0 ) );

		// Platform fee.
		$platform_fee_percent = floatval( Settings::get( 'global', 'platform_fee_percent' ) );
		$platform_fee = round( $subtotal * ( $platform_fee_percent / 100 ), 2 );

		// Processing fee estimate (Stripe standard: 2.9% + $0.30).
		$processing_fee_percent = 2.9;
		$processing_fee_flat    = 0.30;

		// Feeable total = subtotal + delivery + travel + shipping - discount.
		$feeable_total = $subtotal + $delivery_fee + $travel_fee + $shipping_fee - $discount;
		$feeable_total = max( 0, $feeable_total );

		$processing_fee = round( ( $feeable_total * ( $processing_fee_percent / 100 ) ) + $processing_fee_flat, 2 );

		// Who pays processing fee?
		$fee_paid_by = Settings::get( 'global', 'processing_fee_paid_by' );
		$customer_processing_fee = 0.00;
		$provider_processing_fee = 0.00;

		if ( 'customer' === $fee_paid_by ) {
			$customer_processing_fee = $processing_fee;
		} elseif ( 'provider' === $fee_paid_by ) {
			$provider_processing_fee = $processing_fee;
		} else { // split
			$customer_processing_fee = round( $processing_fee / 2, 2 );
			$provider_processing_fee = $processing_fee - $customer_processing_fee;
		}

		// Customer total = feeable + processing fee charged to customer.
		$customer_total = round( $feeable_total + $customer_processing_fee, 2 );

		// Provider payout = subtotal + delivery/travel/shipping - platform fee - provider's processing share.
		$provider_payout = round(
			$subtotal + $delivery_fee + $travel_fee + $shipping_fee - $discount - $platform_fee - $provider_processing_fee,
			2
		);
		$provider_payout = max( 0, $provider_payout );

		return [
			'subtotal'                => $subtotal,
			'delivery_fee'            => $delivery_fee,
			'travel_fee'              => $travel_fee,
			'shipping_fee'            => $shipping_fee,
			'discount'                => $discount,
			'platform_fee'            => $platform_fee,
			'platform_fee_percent'    => $platform_fee_percent,
			'processing_fee'          => $processing_fee,
			'customer_processing_fee' => $customer_processing_fee,
			'provider_processing_fee' => $provider_processing_fee,
			'processing_fee_paid_by'  => $fee_paid_by,
			'deposit_amount'          => $deposit,
			'customer_total'          => $customer_total,
			'provider_payout'         => $provider_payout,
			'currency'                => Settings::get( 'global', 'default_currency' ),
		];
	}

	/**
	 * Calculate rental price for a given duration.
	 *
	 * Uses the most favorable rate for the customer:
	 * month_rate > week_rate > weekend_rate > day_rate
	 */
	public static function calculate_rental_price( object $listing, int $days ): float {
		$month_rate   = floatval( $listing->month_rate ?? 0 );
		$week_rate    = floatval( $listing->week_rate ?? 0 );
		$weekend_rate = floatval( $listing->weekend_rate ?? 0 );
		$day_rate     = floatval( $listing->day_rate ?? 0 );

		if ( $day_rate <= 0 ) {
			return 0;
		}

		$total = 0;
		$remaining = $days;

		// Fill with months first.
		if ( $month_rate > 0 && $remaining >= 28 ) {
			$months    = intdiv( $remaining, 28 );
			$total    += $months * $month_rate;
			$remaining -= $months * 28;
		}

		// Fill with weeks.
		if ( $week_rate > 0 && $remaining >= 7 ) {
			$weeks     = intdiv( $remaining, 7 );
			$total    += $weeks * $week_rate;
			$remaining -= $weeks * 7;
		}

		// Remaining days at day rate.
		$total += $remaining * $day_rate;

		return round( $total, 2 );
	}

	/**
	 * Calculate service price for a given duration.
	 */
	public static function calculate_service_price( object $listing, float $hours ): float {
		$hourly    = floatval( $listing->hourly_rate ?? 0 );
		$half_day  = floatval( $listing->half_day_rate ?? 0 );
		$full_day  = floatval( $listing->full_day_rate ?? 0 );

		// Use best rate.
		if ( $full_day > 0 && $hours >= 8 ) {
			$days      = floor( $hours / 8 );
			$remaining = $hours - ( $days * 8 );
			$total     = $days * $full_day;

			if ( $remaining >= 4 && $half_day > 0 ) {
				$total += $half_day;
				$remaining -= 4;
			}
			$total += $remaining * $hourly;
			return round( $total, 2 );
		}

		if ( $half_day > 0 && $hours >= 4 ) {
			$halves    = floor( $hours / 4 );
			$remaining = $hours - ( $halves * 4 );
			return round( ( $halves * $half_day ) + ( $remaining * $hourly ), 2 );
		}

		return round( $hours * $hourly, 2 );
	}

	/**
	 * Calculate late fee based on settings.
	 */
	public static function calculate_late_fee( float $day_rate, float $hours_late ): float {
		$formula = Settings::get( 'late_return', 'late_fee_formula_type' );
		$cap     = floatval( Settings::get( 'late_return', 'max_late_fee_cap' ) );

		$fee = match ( $formula ) {
			'flat'    => floatval( Settings::get( 'late_return', 'late_fee_flat_amount' ) ),
			'hourly'  => $hours_late * floatval( Settings::get( 'late_return', 'late_fee_hourly_amount' ) ),
			'daily'   => ceil( $hours_late / 24 ) * floatval( Settings::get( 'late_return', 'late_fee_daily_amount' ) ),
			'percent' => ceil( $hours_late / 24 ) * ( $day_rate * floatval( Settings::get( 'late_return', 'late_fee_percent_of_day_rate' ) ) / 100 ),
			default   => 0,
		};

		if ( $cap > 0 ) {
			$fee = min( $fee, $cap );
		}

		return round( max( 0, $fee ), 2 );
	}

	/**
	 * Convert dollars to cents for gateway calls.
	 */
	public static function to_cents( float $amount ): int {
		return (int) round( $amount * 100 );
	}
}
