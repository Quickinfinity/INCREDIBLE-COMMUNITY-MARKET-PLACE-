<?php
/**
 * Review model — mandatory two-way review system.
 *
 * Review flow:
 * 1. Booking completes → review records created for both parties
 * 2. Both customer and provider must submit within the review window
 * 3. Reviews are hidden until both parties submit (or deadline expires)
 * 4. Published simultaneously to prevent retaliation bias
 * 5. Admin can moderate flagged reviews
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME\Reviews;

use JQME\Core;
use JQME\AuditLogger;
use JQME\StatusEnums;
use JQME\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Review {

	/**
	 * Get a review by ID.
	 */
	public static function get( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . Core::table( 'reviews' ) . " WHERE id = %d", $id
		) ) ?: null;
	}

	/**
	 * Create review placeholders when a booking completes.
	 * Creates two records: one for the customer to review the provider,
	 * one for the provider to review the customer.
	 */
	public static function create_for_booking( int $booking_id ): array {
		global $wpdb;

		$booking = \JQME\Bookings\Booking::get( $booking_id );
		if ( ! $booking ) {
			return [];
		}

		// Don't create duplicates.
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM " . Core::table( 'reviews' ) . " WHERE booking_id = %d",
			$booking_id
		) );
		if ( $existing > 0 ) {
			return [];
		}

		$review_days = (int) Settings::get( 'reviews', 'review_window_days' );
		$deadline    = gmdate( 'Y-m-d H:i:s', time() + ( $review_days * DAY_IN_SECONDS ) );

		$provider = \JQME\Providers\Provider::get( $booking->provider_id );
		$provider_user_id = $provider ? (int) $provider->user_id : 0;

		$ids = [];

		// Customer reviews provider.
		$wpdb->insert( Core::table( 'reviews' ), [
			'booking_id'    => $booking_id,
			'listing_id'    => $booking->listing_id,
			'reviewer_id'   => $booking->customer_id,
			'reviewee_id'   => $provider_user_id,
			'reviewer_role' => 'customer',
			'booking_type'  => $booking->booking_type,
			'status'        => StatusEnums::REVIEW_PENDING_BOTH,
			'deadline_at'   => $deadline,
			'created_at'    => current_time( 'mysql' ),
			'updated_at'    => current_time( 'mysql' ),
		] );
		$ids[] = (int) $wpdb->insert_id;

		// Provider reviews customer.
		$wpdb->insert( Core::table( 'reviews' ), [
			'booking_id'    => $booking_id,
			'listing_id'    => $booking->listing_id,
			'reviewer_id'   => $provider_user_id,
			'reviewee_id'   => $booking->customer_id,
			'reviewer_role' => 'provider',
			'booking_type'  => $booking->booking_type,
			'status'        => StatusEnums::REVIEW_PENDING_BOTH,
			'deadline_at'   => $deadline,
			'created_at'    => current_time( 'mysql' ),
			'updated_at'    => current_time( 'mysql' ),
		] );
		$ids[] = (int) $wpdb->insert_id;

		AuditLogger::log( 'reviews_created', 'booking', $booking_id, null, count( $ids ),
			sprintf( 'Review deadline: %s', $deadline )
		);

		return $ids;
	}

	/**
	 * Submit a review (customer or provider fills in their review).
	 */
	public static function submit( int $review_id, array $data ): bool {
		global $wpdb;

		$review = self::get( $review_id );
		if ( ! $review ) {
			return false;
		}

		// Only the assigned reviewer can submit.
		if ( (int) $review->reviewer_id !== get_current_user_id() ) {
			return false;
		}

		// Check deadline.
		if ( $review->deadline_at && time() > strtotime( $review->deadline_at ) ) {
			return false;
		}

		// Validate rating.
		$rating = absint( $data['overall_rating'] ?? 0 );
		if ( $rating < 1 || $rating > 5 ) {
			return false;
		}

		$categories = isset( $data['rating_categories'] ) ? (array) $data['rating_categories'] : [];

		$wpdb->update( Core::table( 'reviews' ), [
			'overall_rating'    => $rating,
			'rating_categories' => ! empty( $categories ) ? wp_json_encode( $categories ) : null,
			'title'             => sanitize_text_field( $data['title'] ?? '' ),
			'body'              => sanitize_textarea_field( $data['body'] ?? '' ),
			'updated_at'        => current_time( 'mysql' ),
		], [ 'id' => $review_id ] );

		// Check if both reviews for this booking are now submitted.
		$pending = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM " . Core::table( 'reviews' ) . " WHERE booking_id = %d AND overall_rating = 0",
			$review->booking_id
		) );

		if ( 0 === (int) $pending ) {
			// Both submitted — publish both.
			self::publish_pair( $review->booking_id );
		} else {
			// Update status to reflect who has submitted.
			$other_role = 'customer' === $review->reviewer_role ? 'provider' : 'customer';
			$new_status = 'customer' === $other_role
				? StatusEnums::REVIEW_PENDING_CUSTOMER
				: StatusEnums::REVIEW_PENDING_PROVIDER;

			// Update all reviews for this booking.
			$wpdb->query( $wpdb->prepare(
				"UPDATE " . Core::table( 'reviews' ) . " SET status = %s, updated_at = %s WHERE booking_id = %d AND overall_rating = 0",
				$new_status,
				current_time( 'mysql' ),
				$review->booking_id
			) );

			// Mark the submitted one.
			$wpdb->update( Core::table( 'reviews' ), [
				'status'     => StatusEnums::REVIEW_SUBMITTED,
				'updated_at' => current_time( 'mysql' ),
			], [ 'id' => $review_id ] );
		}

		AuditLogger::log( 'review_submitted', 'review', $review_id, null, $rating );

		do_action( 'jqme_review_submitted', $review_id );

		return true;
	}

	/**
	 * Publish both reviews for a booking simultaneously.
	 */
	private static function publish_pair( int $booking_id ): void {
		global $wpdb;

		$now = current_time( 'mysql' );

		$wpdb->query( $wpdb->prepare(
			"UPDATE " . Core::table( 'reviews' ) . "
			 SET status = %s, published_at = %s, updated_at = %s
			 WHERE booking_id = %d AND flagged = 0 AND overall_rating > 0",
			StatusEnums::REVIEW_PUBLISHED,
			$now,
			$now,
			$booking_id
		) );

		do_action( 'jqme_reviews_published', $booking_id );
	}

	/**
	 * Expire unpublished reviews past their deadline (called by cron).
	 */
	public static function expire_overdue(): int {
		global $wpdb;

		$table = Core::table( 'reviews' );
		$now   = current_time( 'mysql' );

		// Find reviews past deadline that haven't been submitted.
		$expired = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, booking_id FROM {$table} WHERE deadline_at IS NOT NULL AND deadline_at <= %s AND overall_rating = 0 AND status NOT IN ('expired', 'published')",
			$now
		) );

		$count = 0;
		$processed_bookings = [];

		foreach ( $expired as $r ) {
			$wpdb->update( $table, [
				'status'     => StatusEnums::REVIEW_EXPIRED,
				'updated_at' => $now,
			], [ 'id' => $r->id ] );
			$count++;

			// If the other party DID submit, publish their review.
			if ( ! in_array( $r->booking_id, $processed_bookings, true ) ) {
				$submitted = $wpdb->get_results( $wpdb->prepare(
					"SELECT id FROM {$table} WHERE booking_id = %d AND overall_rating > 0 AND status != %s",
					$r->booking_id,
					StatusEnums::REVIEW_PUBLISHED
				) );

				foreach ( $submitted as $s ) {
					$wpdb->update( $table, [
						'status'       => StatusEnums::REVIEW_PUBLISHED,
						'published_at' => $now,
						'updated_at'   => $now,
					], [ 'id' => $s->id ] );
				}

				$processed_bookings[] = $r->booking_id;
			}
		}

		return $count;
	}

	/**
	 * Provider responds to a review.
	 */
	public static function add_provider_response( int $review_id, string $response ): bool {
		global $wpdb;

		$review = self::get( $review_id );
		if ( ! $review || 'customer' !== $review->reviewer_role ) {
			return false; // Only respond to customer reviews.
		}

		$result = $wpdb->update( Core::table( 'reviews' ), [
			'provider_response'    => sanitize_textarea_field( $response ),
			'provider_response_at' => current_time( 'mysql' ),
			'updated_at'           => current_time( 'mysql' ),
		], [ 'id' => $review_id ] );

		return false !== $result;
	}

	/**
	 * Flag a review for admin review.
	 */
	public static function flag( int $review_id, string $reason = '' ): bool {
		global $wpdb;

		$review = self::get( $review_id );
		if ( ! $review ) {
			return false;
		}

		$result = $wpdb->update( Core::table( 'reviews' ), [
			'flagged'     => 1,
			'flag_reason' => sanitize_textarea_field( $reason ),
			'status'      => StatusEnums::REVIEW_HIDDEN_FLAGGED,
			'updated_at'  => current_time( 'mysql' ),
		], [ 'id' => $review_id ] );

		if ( false !== $result ) {
			AuditLogger::log( 'review_flagged', 'review', $review_id, null, null, $reason );
		}

		return false !== $result;
	}

	/**
	 * Unflag and republish a review.
	 */
	public static function unflag( int $review_id ): bool {
		global $wpdb;

		$result = $wpdb->update( Core::table( 'reviews' ), [
			'flagged'     => 0,
			'flag_reason' => null,
			'status'      => StatusEnums::REVIEW_PUBLISHED,
			'updated_at'  => current_time( 'mysql' ),
		], [ 'id' => $review_id ] );

		return false !== $result;
	}

	/**
	 * Get reviews for a listing (published only).
	 */
	public static function get_for_listing( int $listing_id, int $limit = 20 ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT r.*, u.display_name as reviewer_name
			 FROM " . Core::table( 'reviews' ) . " r
			 LEFT JOIN {$wpdb->users} u ON r.reviewer_id = u.ID
			 WHERE r.listing_id = %d AND r.status = %s AND r.reviewer_role = 'customer'
			 ORDER BY r.published_at DESC LIMIT %d",
			$listing_id,
			StatusEnums::REVIEW_PUBLISHED,
			$limit
		) );
	}

	/**
	 * Get average rating for a listing.
	 */
	public static function get_listing_average( int $listing_id ): array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT AVG(overall_rating) as avg_rating, COUNT(*) as review_count
			 FROM " . Core::table( 'reviews' ) . "
			 WHERE listing_id = %d AND status = %s AND reviewer_role = 'customer' AND overall_rating > 0",
			$listing_id,
			StatusEnums::REVIEW_PUBLISHED
		) );

		return [
			'average' => $row ? round( (float) $row->avg_rating, 1 ) : 0,
			'count'   => $row ? (int) $row->review_count : 0,
		];
	}

	/**
	 * Get average rating for a provider (across all listings).
	 */
	public static function get_provider_average( int $provider_user_id ): array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT AVG(overall_rating) as avg_rating, COUNT(*) as review_count
			 FROM " . Core::table( 'reviews' ) . "
			 WHERE reviewee_id = %d AND status = %s AND reviewer_role = 'customer' AND overall_rating > 0",
			$provider_user_id,
			StatusEnums::REVIEW_PUBLISHED
		) );

		return [
			'average' => $row ? round( (float) $row->avg_rating, 1 ) : 0,
			'count'   => $row ? (int) $row->review_count : 0,
		];
	}

	/**
	 * Get pending reviews for a user.
	 */
	public static function get_pending_for_user( int $user_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT r.*, b.booking_number, l.title as listing_title
			 FROM " . Core::table( 'reviews' ) . " r
			 LEFT JOIN " . Core::table( 'bookings' ) . " b ON r.booking_id = b.id
			 LEFT JOIN " . Core::table( 'listings' ) . " l ON r.listing_id = l.id
			 WHERE r.reviewer_id = %d AND r.overall_rating = 0 AND r.status NOT IN ('expired', 'published')
			 ORDER BY r.deadline_at ASC",
			$user_id
		) );
	}

	/**
	 * Query reviews (admin).
	 */
	public static function query( array $args = [] ): array {
		global $wpdb;

		$defaults = [
			'listing_id'    => 0,
			'reviewer_id'   => 0,
			'reviewee_id'   => 0,
			'reviewer_role' => '',
			'status'        => '',
			'flagged'       => null,
			'limit'         => 20,
			'offset'        => 0,
		];

		$args  = wp_parse_args( $args, $defaults );
		$table = Core::table( 'reviews' );
		$where = [];
		$values = [];

		if ( $args['listing_id'] ) {
			$where[]  = 'r.listing_id = %d';
			$values[] = $args['listing_id'];
		}
		if ( $args['reviewer_id'] ) {
			$where[]  = 'r.reviewer_id = %d';
			$values[] = $args['reviewer_id'];
		}
		if ( $args['reviewee_id'] ) {
			$where[]  = 'r.reviewee_id = %d';
			$values[] = $args['reviewee_id'];
		}
		if ( $args['reviewer_role'] ) {
			$where[]  = 'r.reviewer_role = %s';
			$values[] = $args['reviewer_role'];
		}
		if ( $args['status'] ) {
			$where[]  = 'r.status = %s';
			$values[] = $args['status'];
		}
		if ( null !== $args['flagged'] ) {
			$where[]  = 'r.flagged = %d';
			$values[] = $args['flagged'] ? 1 : 0;
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$sql = "SELECT r.*, b.booking_number, l.title as listing_title,
				       reviewer.display_name as reviewer_name,
				       reviewee.display_name as reviewee_name
				FROM {$table} r
				LEFT JOIN " . Core::table( 'bookings' ) . " b ON r.booking_id = b.id
				LEFT JOIN " . Core::table( 'listings' ) . " l ON r.listing_id = l.id
				LEFT JOIN {$wpdb->users} reviewer ON r.reviewer_id = reviewer.ID
				LEFT JOIN {$wpdb->users} reviewee ON r.reviewee_id = reviewee.ID
				{$where_sql}
				ORDER BY r.created_at DESC
				LIMIT %d OFFSET %d";

		$values[] = $args['limit'];
		$values[] = $args['offset'];

		return $wpdb->get_results( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Count reviews with optional filters.
	 */
	public static function count( array $filters = [] ): int {
		global $wpdb;
		$table = Core::table( 'reviews' );
		$where = [];
		$values = [];

		foreach ( [ 'status', 'reviewer_role', 'listing_id' ] as $f ) {
			if ( ! empty( $filters[ $f ] ) ) {
				$type = is_numeric( $filters[ $f ] ) ? '%d' : '%s';
				$where[]  = "{$f} = {$type}";
				$values[] = $filters[ $f ];
			}
		}
		if ( isset( $filters['flagged'] ) ) {
			$where[]  = 'flagged = %d';
			$values[] = $filters['flagged'] ? 1 : 0;
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		if ( ! empty( $values ) ) {
			return (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} {$where_sql}", $values
			) );
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
}
