<?php
/**
 * Provider ranking — trust score calculation and tier assignment.
 *
 * Trust score is a 0.00–5.00 composite based on:
 * - Average review rating (weighted)
 * - Booking completion rate
 * - Claim history (penalty)
 * - Account age/tenure
 * - Verification completeness
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME\Analytics;

use JQME\Core;
use JQME\StatusEnums;
use JQME\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ranking {

	/**
	 * Recalculate trust score for a specific provider.
	 */
	public static function recalculate( int $provider_id ): float {
		global $wpdb;

		$providers = Core::table( 'providers' );
		$bookings  = Core::table( 'bookings' );
		$reviews   = Core::table( 'reviews' );
		$claims    = Core::table( 'claims' );
		$verifications = Core::table( 'verifications' );

		$provider = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$providers} WHERE id = %d", $provider_id
		) );

		if ( ! $provider ) {
			return 0;
		}

		// Component 1: Average review rating (0–5), weight 40%.
		$avg_rating = (float) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(AVG(overall_rating), 0)
			 FROM {$reviews}
			 WHERE reviewee_id = %d AND status = %s AND reviewer_role = 'customer' AND overall_rating > 0",
			$provider->user_id,
			StatusEnums::REVIEW_PUBLISHED
		) );

		// Component 2: Booking completion rate (0–5), weight 25%.
		$total_bookings = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$bookings} WHERE provider_id = %d", $provider_id
		) );

		$completed_statuses = [
			StatusEnums::RENTAL_COMPLETED,
			StatusEnums::SERVICE_COMPLETED,
			StatusEnums::SALE_COMPLETED,
		];
		$completed_in = "'" . implode( "','", $completed_statuses ) . "'";

		$completed_bookings = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$bookings} WHERE provider_id = %d AND status IN ({$completed_in})",
			$provider_id
		) );

		$completion_rate = $total_bookings > 0
			? ( $completed_bookings / $total_bookings ) * 5
			: 2.5; // Neutral for new providers.

		// Component 3: Claim penalty (0 claims = 5, each open/settled claim reduces score).
		$claim_count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$claims} c
			 INNER JOIN {$bookings} b ON c.booking_id = b.id
			 WHERE b.provider_id = %d AND c.status NOT IN ('withdrawn', 'denied')",
			$provider_id
		) );

		$claim_penalty = max( 0, 5 - ( $claim_count * 0.5 ) );

		// Component 4: Account tenure (0–5 based on months active, capped at 24 months).
		$months_active = 0;
		if ( $provider->approved_at ) {
			$months_active = max( 0, ( time() - strtotime( $provider->approved_at ) ) / ( 30 * DAY_IN_SECONDS ) );
		}
		$tenure_score = min( 5, ( $months_active / 24 ) * 5 );

		// Component 5: Verification completeness (0–5).
		$total_verifications = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$verifications} WHERE provider_id = %d", $provider_id
		) );
		$approved_verifications = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$verifications} WHERE provider_id = %d AND status = 'approved'", $provider_id
		) );

		$verification_score = $total_verifications > 0
			? ( $approved_verifications / $total_verifications ) * 5
			: 2.5;

		// Weighted composite.
		$trust_score = round(
			( $avg_rating * 0.40 ) +
			( $completion_rate * 0.25 ) +
			( $claim_penalty * 0.15 ) +
			( $tenure_score * 0.10 ) +
			( $verification_score * 0.10 ),
			2
		);

		// Clamp to 0–5.
		$trust_score = max( 0, min( 5, $trust_score ) );

		// Persist.
		$wpdb->update( $providers, [
			'trust_score' => $trust_score,
			'updated_at'  => current_time( 'mysql' ),
		], [ 'id' => $provider_id ] );

		return $trust_score;
	}

	/**
	 * Recalculate trust scores for all active providers (cron job).
	 */
	public static function recalculate_all(): int {
		global $wpdb;

		$providers = Core::table( 'providers' );
		$active = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM {$providers} WHERE status = %s",
			StatusEnums::PROVIDER_APPROVED
		) );

		$count = 0;
		foreach ( $active as $pid ) {
			self::recalculate( (int) $pid );
			$count++;
		}

		return $count;
	}

	/**
	 * Update listing aggregate stats (average_rating, review_count, booking_count).
	 */
	public static function update_listing_stats( int $listing_id ): void {
		global $wpdb;

		$listings = Core::table( 'listings' );
		$reviews  = Core::table( 'reviews' );
		$bookings = Core::table( 'bookings' );

		$review_data = $wpdb->get_row( $wpdb->prepare(
			"SELECT COALESCE(AVG(overall_rating), 0) as avg, COUNT(*) as cnt
			 FROM {$reviews}
			 WHERE listing_id = %d AND status = %s AND reviewer_role = 'customer' AND overall_rating > 0",
			$listing_id,
			StatusEnums::REVIEW_PUBLISHED
		) );

		$booking_count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$bookings} WHERE listing_id = %d",
			$listing_id
		) );

		$wpdb->update( $listings, [
			'average_rating' => $review_data ? round( (float) $review_data->avg, 2 ) : 0,
			'review_count'   => $review_data ? (int) $review_data->cnt : 0,
			'booking_count'  => $booking_count,
			'updated_at'     => current_time( 'mysql' ),
		], [ 'id' => $listing_id ] );
	}

	/**
	 * Update all listing stats (cron job).
	 */
	public static function update_all_listing_stats(): int {
		global $wpdb;

		$listings = Core::table( 'listings' );
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM {$listings} WHERE status = %s",
			StatusEnums::LISTING_PUBLISHED
		) );

		foreach ( $ids as $id ) {
			self::update_listing_stats( (int) $id );
		}

		return count( $ids );
	}

	/**
	 * Get provider tier based on trust score.
	 */
	public static function get_tier( float $trust_score ): string {
		if ( $trust_score >= 4.5 ) {
			return 'platinum';
		} elseif ( $trust_score >= 3.5 ) {
			return 'gold';
		} elseif ( $trust_score >= 2.5 ) {
			return 'silver';
		} else {
			return 'bronze';
		}
	}

	/**
	 * Tier labels for display.
	 */
	public static function tier_labels(): array {
		return [
			'platinum' => __( 'Platinum', 'jq-marketplace-engine' ),
			'gold'     => __( 'Gold', 'jq-marketplace-engine' ),
			'silver'   => __( 'Silver', 'jq-marketplace-engine' ),
			'bronze'   => __( 'Bronze', 'jq-marketplace-engine' ),
		];
	}
}
