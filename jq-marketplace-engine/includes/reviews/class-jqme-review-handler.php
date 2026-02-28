<?php
/**
 * Review handler — form processing, hooks, admin moderation, shortcodes.
 *
 * Registers:
 * - [jqme_leave_review]    — Review submission form
 * - [jqme_pending_reviews] — User's pending reviews
 * - [jqme_listing_reviews] — Display reviews for a listing
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME\Reviews;

use JQME\StatusEnums;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ReviewHandler {

	public function __construct() {
		// Auto-create review pairs when bookings complete.
		add_action( 'jqme_booking_completed', [ $this, 'on_booking_completed' ] );
		add_action( 'jqme_booking_' . StatusEnums::RENTAL_COMPLETED, [ $this, 'on_booking_completed_status' ] );
		add_action( 'jqme_booking_' . StatusEnums::SERVICE_COMPLETED, [ $this, 'on_booking_completed_status' ] );
		add_action( 'jqme_booking_' . StatusEnums::SALE_COMPLETED, [ $this, 'on_booking_completed_status' ] );

		// Form actions.
		add_action( 'admin_post_jqme_submit_review', [ $this, 'handle_submit_review' ] );
		add_action( 'admin_post_jqme_provider_response', [ $this, 'handle_provider_response' ] );
		add_action( 'admin_post_jqme_flag_review', [ $this, 'handle_flag_review' ] );

		// Admin moderation actions.
		add_action( 'admin_post_jqme_review_unflag', [ $this, 'handle_admin_unflag' ] );
		add_action( 'admin_post_jqme_review_flag', [ $this, 'handle_admin_flag' ] );

		// Shortcodes.
		add_shortcode( 'jqme_leave_review', [ $this, 'render_leave_review' ] );
		add_shortcode( 'jqme_pending_reviews', [ $this, 'render_pending_reviews' ] );
		add_shortcode( 'jqme_listing_reviews', [ $this, 'render_listing_reviews' ] );
	}

	/* ---------------------------------------------------------------
	 * BOOKING COMPLETION HOOKS
	 * ------------------------------------------------------------- */

	public function on_booking_completed( int $booking_id ): void {
		Review::create_for_booking( $booking_id );
	}

	public function on_booking_completed_status( int $booking_id ): void {
		Review::create_for_booking( $booking_id );
	}

	/* ---------------------------------------------------------------
	 * FORM ACTIONS
	 * ------------------------------------------------------------- */

	public function handle_submit_review(): void {
		$review_id = absint( $_POST['review_id'] ?? 0 );
		check_admin_referer( 'jqme_submit_review_' . $review_id );

		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in.', 'jq-marketplace-engine' ) );
		}

		$result = Review::submit( $review_id, [
			'overall_rating'    => absint( $_POST['overall_rating'] ?? 0 ),
			'rating_categories' => $_POST['rating_categories'] ?? [],
			'title'             => sanitize_text_field( $_POST['review_title'] ?? '' ),
			'body'              => sanitize_textarea_field( $_POST['review_body'] ?? '' ),
		] );

		if ( ! $result ) {
			wp_safe_redirect( add_query_arg( 'jqme_error', 'review_failed', wp_get_referer() ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( 'jqme_notice', 'review_submitted', wp_get_referer() ) );
		exit;
	}

	public function handle_provider_response(): void {
		$review_id = absint( $_POST['review_id'] ?? 0 );
		check_admin_referer( 'jqme_provider_response_' . $review_id );

		$review   = Review::get( $review_id );
		$provider = \JQME\Providers\Provider::get_by_user( get_current_user_id() );

		if ( ! $review || ! $provider || (int) $review->reviewee_id !== get_current_user_id() ) {
			wp_die( esc_html__( 'Permission denied.', 'jq-marketplace-engine' ) );
		}

		$response = sanitize_textarea_field( $_POST['provider_response'] ?? '' );
		Review::add_provider_response( $review_id, $response );

		wp_safe_redirect( add_query_arg( 'jqme_notice', 'response_added', wp_get_referer() ) );
		exit;
	}

	public function handle_flag_review(): void {
		$review_id = absint( $_POST['review_id'] ?? 0 );
		check_admin_referer( 'jqme_flag_review_' . $review_id );

		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in.', 'jq-marketplace-engine' ) );
		}

		$reason = sanitize_textarea_field( $_POST['flag_reason'] ?? '' );
		Review::flag( $review_id, $reason );

		wp_safe_redirect( add_query_arg( 'jqme_notice', 'review_flagged', wp_get_referer() ) );
		exit;
	}

	/* ---------------------------------------------------------------
	 * ADMIN MODERATION
	 * ------------------------------------------------------------- */

	public function handle_admin_unflag(): void {
		$review_id = absint( $_POST['review_id'] ?? 0 );
		check_admin_referer( 'jqme_review_unflag_' . $review_id );

		if ( ! current_user_can( 'jqme_manage_reviews' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'jq-marketplace-engine' ) );
		}

		Review::unflag( $review_id );
		wp_safe_redirect( admin_url( 'admin.php?page=jqme-reviews&jqme_notice=review_unflagged' ) );
		exit;
	}

	public function handle_admin_flag(): void {
		$review_id = absint( $_POST['review_id'] ?? 0 );
		check_admin_referer( 'jqme_review_flag_' . $review_id );

		if ( ! current_user_can( 'jqme_manage_reviews' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'jq-marketplace-engine' ) );
		}

		$reason = sanitize_textarea_field( $_POST['flag_reason'] ?? '' );
		Review::flag( $review_id, $reason );
		wp_safe_redirect( admin_url( 'admin.php?page=jqme-reviews&jqme_notice=review_flagged' ) );
		exit;
	}

	/* ---------------------------------------------------------------
	 * SHORTCODE RENDERERS
	 * ------------------------------------------------------------- */

	public function render_leave_review( $atts ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to leave a review.', 'jq-marketplace-engine' ) . '</p>';
		}
		ob_start();
		include JQME_PLUGIN_DIR . 'templates/leave-review.php';
		return ob_get_clean();
	}

	public function render_pending_reviews( $atts ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your pending reviews.', 'jq-marketplace-engine' ) . '</p>';
		}
		ob_start();
		include JQME_PLUGIN_DIR . 'templates/pending-reviews.php';
		return ob_get_clean();
	}

	public function render_listing_reviews( $atts ): string {
		ob_start();
		include JQME_PLUGIN_DIR . 'templates/listing-reviews.php';
		return ob_get_clean();
	}
}
