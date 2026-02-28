<?php
/**
 * Booking handler — form processing, admin actions, and shortcode registration.
 *
 * Hooks into admin_post actions for:
 * - Customer booking requests
 * - Provider approve/decline
 * - Admin booking management
 *
 * Registers front-end shortcodes:
 * - [jqme_booking_request]  — Booking request form
 * - [jqme_my_bookings]      — Customer booking list
 * - [jqme_provider_bookings] — Provider booking list
 * - [jqme_booking_detail]   — Single booking view
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME\Bookings;

use JQME\StatusEnums;
use JQME\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BookingHandler {

	public function __construct() {
		// Customer actions.
		add_action( 'admin_post_jqme_booking_request', [ $this, 'handle_booking_request' ] );
		add_action( 'admin_post_jqme_booking_cancel_customer', [ $this, 'handle_cancel_customer' ] );

		// Provider actions.
		add_action( 'admin_post_jqme_booking_approve_provider', [ $this, 'handle_provider_approve' ] );
		add_action( 'admin_post_jqme_booking_decline_provider', [ $this, 'handle_provider_decline' ] );

		// Admin actions (from booking detail page).
		add_action( 'admin_post_jqme_booking_approve', [ $this, 'handle_admin_approve' ] );
		add_action( 'admin_post_jqme_booking_decline', [ $this, 'handle_admin_decline' ] );
		add_action( 'admin_post_jqme_booking_complete', [ $this, 'handle_admin_complete' ] );
		add_action( 'admin_post_jqme_booking_cancel', [ $this, 'handle_admin_cancel' ] );
		add_action( 'admin_post_jqme_booking_set_status', [ $this, 'handle_admin_set_status' ] );

		// Shortcodes.
		add_shortcode( 'jqme_booking_request', [ $this, 'render_booking_request' ] );
		add_shortcode( 'jqme_my_bookings', [ $this, 'render_my_bookings' ] );
		add_shortcode( 'jqme_provider_bookings', [ $this, 'render_provider_bookings' ] );
		add_shortcode( 'jqme_booking_detail', [ $this, 'render_booking_detail' ] );
	}

	/* ---------------------------------------------------------------
	 * CUSTOMER ACTIONS
	 * ------------------------------------------------------------- */

	/**
	 * Handle a new booking request from a customer.
	 */
	public function handle_booking_request(): void {
		check_admin_referer( 'jqme_booking_request' );

		if ( ! current_user_can( 'jqme_create_bookings' ) ) {
			wp_die( esc_html__( 'You do not have permission to create bookings.', 'jq-marketplace-engine' ) );
		}

		$listing_id = absint( $_POST['listing_id'] ?? 0 );
		$listing    = \JQME\Listings\Listing::get( $listing_id );

		if ( ! $listing || StatusEnums::LISTING_PUBLISHED !== $listing->status ) {
			wp_safe_redirect( add_query_arg( 'jqme_error', 'invalid_listing', wp_get_referer() ) );
			exit;
		}

		$booking_id = Booking::create( $listing->listing_type, [
			'listing_id'               => $listing_id,
			'provider_id'              => $listing->provider_id,
			'customer_id'              => get_current_user_id(),
			'date_start'               => sanitize_text_field( $_POST['date_start'] ?? '' ),
			'date_end'                 => sanitize_text_field( $_POST['date_end'] ?? '' ),
			'subtotal'                 => floatval( $_POST['subtotal'] ?? 0 ),
			'delivery_fee'             => floatval( $_POST['delivery_fee'] ?? 0 ),
			'travel_fee'               => floatval( $_POST['travel_fee'] ?? 0 ),
			'shipping_fee'             => floatval( $_POST['shipping_fee'] ?? 0 ),
			'deposit_amount'           => floatval( $_POST['deposit_amount'] ?? 0 ),
			'fulfillment_mode'         => sanitize_text_field( $_POST['fulfillment_mode'] ?? 'pickup' ),
			'delivery_address'         => sanitize_textarea_field( $_POST['delivery_address'] ?? '' ),
			'customer_notes'           => sanitize_textarea_field( $_POST['customer_notes'] ?? '' ),
			'item_description'         => $listing->title,
			'platform_terms_accepted'  => ! empty( $_POST['platform_terms_accepted'] ),
			'customer_contract_accepted' => ! empty( $_POST['customer_contract_accepted'] ),
		] );

		if ( ! $booking_id ) {
			wp_safe_redirect( add_query_arg( 'jqme_error', 'booking_failed', wp_get_referer() ) );
			exit;
		}

		$redirect = add_query_arg( [
			'jqme_notice' => 'booking_submitted',
			'booking_id'  => $booking_id,
		], wp_get_referer() );

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Customer cancels their own booking.
	 */
	public function handle_cancel_customer(): void {
		$booking_id = absint( $_POST['booking_id'] ?? 0 );
		check_admin_referer( 'jqme_booking_cancel_customer_' . $booking_id );

		$booking = Booking::get( $booking_id );
		if ( ! $booking || (int) $booking->customer_id !== get_current_user_id() ) {
			wp_die( esc_html__( 'Invalid booking.', 'jq-marketplace-engine' ) );
		}

		$reason = sanitize_textarea_field( $_POST['cancel_reason'] ?? '' );
		Booking::cancel_by_customer( $booking_id, $reason );

		wp_safe_redirect( add_query_arg( 'jqme_notice', 'booking_cancelled', wp_get_referer() ) );
		exit;
	}

	/* ---------------------------------------------------------------
	 * PROVIDER ACTIONS
	 * ------------------------------------------------------------- */

	/**
	 * Provider approves a booking.
	 */
	public function handle_provider_approve(): void {
		$booking_id = absint( $_POST['booking_id'] ?? 0 );
		check_admin_referer( 'jqme_booking_approve_provider_' . $booking_id );

		$booking  = Booking::get( $booking_id );
		$provider = \JQME\Providers\Provider::get_by_user( get_current_user_id() );

		if ( ! $booking || ! $provider || (int) $booking->provider_id !== (int) $provider->id ) {
			wp_die( esc_html__( 'Invalid booking.', 'jq-marketplace-engine' ) );
		}

		Booking::approve( $booking_id );

		wp_safe_redirect( add_query_arg( 'jqme_notice', 'booking_approved', wp_get_referer() ) );
		exit;
	}

	/**
	 * Provider declines a booking.
	 */
	public function handle_provider_decline(): void {
		$booking_id = absint( $_POST['booking_id'] ?? 0 );
		check_admin_referer( 'jqme_booking_decline_provider_' . $booking_id );

		$booking  = Booking::get( $booking_id );
		$provider = \JQME\Providers\Provider::get_by_user( get_current_user_id() );

		if ( ! $booking || ! $provider || (int) $booking->provider_id !== (int) $provider->id ) {
			wp_die( esc_html__( 'Invalid booking.', 'jq-marketplace-engine' ) );
		}

		$reason = sanitize_textarea_field( $_POST['decline_reason'] ?? '' );
		Booking::decline( $booking_id, $reason );

		wp_safe_redirect( add_query_arg( 'jqme_notice', 'booking_declined', wp_get_referer() ) );
		exit;
	}

	/* ---------------------------------------------------------------
	 * ADMIN ACTIONS
	 * ------------------------------------------------------------- */

	public function handle_admin_approve(): void {
		$booking_id = absint( $_POST['booking_id'] ?? 0 );
		check_admin_referer( 'jqme_booking_approve_' . $booking_id );

		if ( ! current_user_can( 'jqme_manage_bookings' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'jq-marketplace-engine' ) );
		}

		Booking::approve( $booking_id );
		wp_safe_redirect( admin_url( 'admin.php?page=jqme-bookings&action=view&id=' . $booking_id . '&jqme_notice=booking_approved' ) );
		exit;
	}

	public function handle_admin_decline(): void {
		$booking_id = absint( $_POST['booking_id'] ?? 0 );
		check_admin_referer( 'jqme_booking_decline_' . $booking_id );

		if ( ! current_user_can( 'jqme_manage_bookings' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'jq-marketplace-engine' ) );
		}

		Booking::decline( $booking_id );
		wp_safe_redirect( admin_url( 'admin.php?page=jqme-bookings&action=view&id=' . $booking_id . '&jqme_notice=booking_declined' ) );
		exit;
	}

	public function handle_admin_complete(): void {
		$booking_id = absint( $_POST['booking_id'] ?? 0 );
		check_admin_referer( 'jqme_booking_complete_' . $booking_id );

		if ( ! current_user_can( 'jqme_manage_bookings' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'jq-marketplace-engine' ) );
		}

		Booking::complete( $booking_id );
		wp_safe_redirect( admin_url( 'admin.php?page=jqme-bookings&action=view&id=' . $booking_id . '&jqme_notice=booking_completed' ) );
		exit;
	}

	public function handle_admin_cancel(): void {
		$booking_id = absint( $_POST['booking_id'] ?? 0 );
		check_admin_referer( 'jqme_booking_cancel_' . $booking_id );

		if ( ! current_user_can( 'jqme_manage_bookings' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'jq-marketplace-engine' ) );
		}

		Booking::cancel_by_customer( $booking_id, 'Cancelled by admin' );
		wp_safe_redirect( admin_url( 'admin.php?page=jqme-bookings&action=view&id=' . $booking_id . '&jqme_notice=booking_cancelled' ) );
		exit;
	}

	public function handle_admin_set_status(): void {
		$booking_id = absint( $_POST['booking_id'] ?? 0 );
		check_admin_referer( 'jqme_booking_set_status_' . $booking_id );

		if ( ! current_user_can( 'jqme_manage_bookings' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'jq-marketplace-engine' ) );
		}

		$new_status = sanitize_text_field( $_POST['new_status'] ?? '' );
		$notes      = sanitize_textarea_field( $_POST['status_notes'] ?? '' );

		if ( $new_status ) {
			Booking::set_status( $booking_id, $new_status, $notes ?: 'Manual admin override' );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=jqme-bookings&action=view&id=' . $booking_id . '&jqme_notice=status_updated' ) );
		exit;
	}

	/* ---------------------------------------------------------------
	 * SHORTCODE RENDERERS
	 * ------------------------------------------------------------- */

	/**
	 * [jqme_booking_request] — Booking request form.
	 */
	public function render_booking_request( $atts ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to request a booking.', 'jq-marketplace-engine' ) . '</p>';
		}

		ob_start();
		include JQME_PLUGIN_DIR . 'templates/booking-request-form.php';
		return ob_get_clean();
	}

	/**
	 * [jqme_my_bookings] — Customer's booking list.
	 */
	public function render_my_bookings( $atts ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your bookings.', 'jq-marketplace-engine' ) . '</p>';
		}

		ob_start();
		include JQME_PLUGIN_DIR . 'templates/my-bookings.php';
		return ob_get_clean();
	}

	/**
	 * [jqme_provider_bookings] — Provider's incoming booking list.
	 */
	public function render_provider_bookings( $atts ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view bookings.', 'jq-marketplace-engine' ) . '</p>';
		}

		$provider = \JQME\Providers\Provider::get_by_user( get_current_user_id() );
		if ( ! $provider ) {
			return '<p>' . esc_html__( 'You are not a registered provider.', 'jq-marketplace-engine' ) . '</p>';
		}

		ob_start();
		include JQME_PLUGIN_DIR . 'templates/provider-bookings.php';
		return ob_get_clean();
	}

	/**
	 * [jqme_booking_detail] — Single booking detail view.
	 */
	public function render_booking_detail( $atts ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view this booking.', 'jq-marketplace-engine' ) . '</p>';
		}

		ob_start();
		include JQME_PLUGIN_DIR . 'templates/booking-detail.php';
		return ob_get_clean();
	}
}
