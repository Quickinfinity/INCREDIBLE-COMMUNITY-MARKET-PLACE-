<?php
/**
 * Provider request handler — processes front-end and admin form submissions.
 *
 * Hooks into WordPress form actions for provider applications,
 * profile updates, and admin approval/rejection.
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME\Providers;

use JQME\StatusEnums;
use JQME\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProviderHandler {

	public function __construct() {
		// Front-end form handlers.
		add_action( 'admin_post_jqme_provider_apply', [ $this, 'handle_application' ] );
		add_action( 'admin_post_jqme_provider_update_profile', [ $this, 'handle_profile_update' ] );

		// Admin action handlers.
		add_action( 'admin_post_jqme_provider_approve', [ $this, 'handle_approve' ] );
		add_action( 'admin_post_jqme_provider_reject', [ $this, 'handle_reject' ] );
		add_action( 'admin_post_jqme_provider_suspend', [ $this, 'handle_suspend' ] );
		add_action( 'admin_post_jqme_provider_reactivate', [ $this, 'handle_reactivate' ] );

		// Shortcodes.
		add_shortcode( 'jqme_provider_application', [ $this, 'render_application_form' ] );
		add_shortcode( 'jqme_provider_dashboard', [ $this, 'render_dashboard' ] );
		add_shortcode( 'jqme_provider_profile', [ $this, 'render_profile_editor' ] );
	}

	/* ---------------------------------------------------------------
	 * FRONT-END: APPLICATION
	 * ------------------------------------------------------------- */

	/**
	 * Process provider application form submission.
	 */
	public function handle_application(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in to apply.', 'jq-marketplace-engine' ) );
		}

		check_admin_referer( 'jqme_provider_apply' );

		$user_id = get_current_user_id();

		// Check if already applied.
		$existing = Provider::get_by_user( $user_id );
		if ( $existing ) {
			wp_safe_redirect( add_query_arg( 'jqme_notice', 'already_applied', wp_get_referer() ) );
			exit;
		}

		$data = [
			'company_name'         => sanitize_text_field( $_POST['company_name'] ?? '' ),
			'contact_name'         => sanitize_text_field( $_POST['contact_name'] ?? '' ),
			'contact_email'        => sanitize_email( $_POST['contact_email'] ?? '' ),
			'contact_phone'        => sanitize_text_field( $_POST['contact_phone'] ?? '' ),
			'address_line1'        => sanitize_text_field( $_POST['address_line1'] ?? '' ),
			'address_line2'        => sanitize_text_field( $_POST['address_line2'] ?? '' ),
			'city'                 => sanitize_text_field( $_POST['city'] ?? '' ),
			'state'                => sanitize_text_field( $_POST['state'] ?? '' ),
			'zip'                  => sanitize_text_field( $_POST['zip'] ?? '' ),
			'country'              => sanitize_text_field( $_POST['country'] ?? 'US' ),
			'service_radius_miles' => absint( $_POST['service_radius_miles'] ?? 50 ),
			'can_deliver'          => ! empty( $_POST['can_deliver'] ),
			'delivery_radius_miles' => absint( $_POST['delivery_radius_miles'] ?? 0 ),
			'listing_types'        => array_map( 'sanitize_text_field', $_POST['listing_types'] ?? [] ),
			'meta'                 => [
				'application_notes'   => sanitize_textarea_field( $_POST['application_notes'] ?? '' ),
				'years_in_business'   => absint( $_POST['years_in_business'] ?? 0 ),
				'website'             => esc_url_raw( $_POST['website'] ?? '' ),
			],
		];

		// Validate required fields.
		$required = [ 'company_name', 'contact_name', 'contact_email', 'city', 'state', 'zip' ];
		foreach ( $required as $field ) {
			if ( empty( $data[ $field ] ) ) {
				wp_safe_redirect( add_query_arg( 'jqme_notice', 'missing_fields', wp_get_referer() ) );
				exit;
			}
		}

		$provider_id = Provider::apply( $user_id, $data );

		if ( false === $provider_id ) {
			wp_safe_redirect( add_query_arg( 'jqme_notice', 'application_failed', wp_get_referer() ) );
			exit;
		}

		// Trigger notification to admins.
		do_action( 'jqme_notify_admin_new_application', $provider_id );

		wp_safe_redirect( add_query_arg( 'jqme_notice', 'application_submitted', wp_get_referer() ) );
		exit;
	}

	/**
	 * Process provider profile update.
	 */
	public function handle_profile_update(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in.', 'jq-marketplace-engine' ) );
		}

		check_admin_referer( 'jqme_provider_update_profile' );

		$provider = Provider::get_by_user( get_current_user_id() );
		if ( ! $provider || StatusEnums::PROVIDER_APPROVED !== $provider->status ) {
			wp_die( esc_html__( 'Access denied.', 'jq-marketplace-engine' ) );
		}

		$data = [
			'company_name'          => sanitize_text_field( $_POST['company_name'] ?? '' ),
			'contact_name'          => sanitize_text_field( $_POST['contact_name'] ?? '' ),
			'contact_email'         => sanitize_email( $_POST['contact_email'] ?? '' ),
			'contact_phone'         => sanitize_text_field( $_POST['contact_phone'] ?? '' ),
			'address_line1'         => sanitize_text_field( $_POST['address_line1'] ?? '' ),
			'address_line2'         => sanitize_text_field( $_POST['address_line2'] ?? '' ),
			'city'                  => sanitize_text_field( $_POST['city'] ?? '' ),
			'state'                 => sanitize_text_field( $_POST['state'] ?? '' ),
			'zip'                   => sanitize_text_field( $_POST['zip'] ?? '' ),
			'service_radius_miles'  => absint( $_POST['service_radius_miles'] ?? 50 ),
			'can_deliver'           => ! empty( $_POST['can_deliver'] ) ? 1 : 0,
			'delivery_radius_miles' => absint( $_POST['delivery_radius_miles'] ?? 0 ),
		];

		Provider::update_profile( $provider->id, $data );

		wp_safe_redirect( add_query_arg( 'jqme_notice', 'profile_updated', wp_get_referer() ) );
		exit;
	}

	/* ---------------------------------------------------------------
	 * ADMIN: APPROVAL / REJECTION
	 * ------------------------------------------------------------- */

	public function handle_approve(): void {
		$this->admin_action( 'approve' );
	}

	public function handle_reject(): void {
		$this->admin_action( 'reject' );
	}

	public function handle_suspend(): void {
		$this->admin_action( 'suspend' );
	}

	public function handle_reactivate(): void {
		$this->admin_action( 'reactivate' );
	}

	private function admin_action( string $action ): void {
		if ( ! current_user_can( 'jqme_manage_providers' ) ) {
			wp_die( esc_html__( 'Access denied.', 'jq-marketplace-engine' ) );
		}

		$provider_id = absint( $_POST['provider_id'] ?? $_GET['provider_id'] ?? 0 );
		if ( ! $provider_id ) {
			wp_die( esc_html__( 'Invalid provider.', 'jq-marketplace-engine' ) );
		}

		check_admin_referer( "jqme_provider_{$action}_{$provider_id}" );

		$reason = sanitize_textarea_field( $_POST['reason'] ?? '' );

		$result = match ( $action ) {
			'approve'    => Provider::approve( $provider_id ),
			'reject'     => Provider::reject( $provider_id, $reason ),
			'suspend'    => Provider::suspend( $provider_id, $reason ),
			'reactivate' => Provider::set_status( $provider_id, StatusEnums::PROVIDER_APPROVED ),
			default      => false,
		};

		$notice = $result ? "{$action}_success" : "{$action}_failed";

		wp_safe_redirect( add_query_arg(
			[ 'page' => 'jqme-providers', 'jqme_notice' => $notice ],
			admin_url( 'admin.php' )
		) );
		exit;
	}

	/* ---------------------------------------------------------------
	 * SHORTCODE RENDERERS
	 * ------------------------------------------------------------- */

	/**
	 * [jqme_provider_application] — application form for logged-in users.
	 */
	public function render_application_form(): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to apply as a provider.', 'jq-marketplace-engine' ) . '</p>';
		}

		$existing = Provider::get_by_user( get_current_user_id() );
		if ( $existing ) {
			$status_label = StatusEnums::provider_statuses()[ $existing->status ] ?? $existing->status;
			return '<p>' . sprintf(
				esc_html__( 'You have already applied. Current status: %s', 'jq-marketplace-engine' ),
				'<strong>' . esc_html( $status_label ) . '</strong>'
			) . '</p>';
		}

		ob_start();
		$template = JQME_PLUGIN_DIR . 'templates/provider-application-form.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		return ob_get_clean();
	}

	/**
	 * [jqme_provider_dashboard] — provider dashboard for approved providers.
	 */
	public function render_dashboard(): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to access your dashboard.', 'jq-marketplace-engine' ) . '</p>';
		}

		$provider = Provider::get_by_user( get_current_user_id() );
		if ( ! $provider || StatusEnums::PROVIDER_APPROVED !== $provider->status ) {
			return '<p>' . esc_html__( 'You do not have access to the provider dashboard.', 'jq-marketplace-engine' ) . '</p>';
		}

		ob_start();
		$template = JQME_PLUGIN_DIR . 'templates/provider-dashboard.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		return ob_get_clean();
	}

	/**
	 * [jqme_provider_profile] — profile editor for approved providers.
	 */
	public function render_profile_editor(): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in.', 'jq-marketplace-engine' ) . '</p>';
		}

		$provider = Provider::get_by_user( get_current_user_id() );
		if ( ! $provider ) {
			return '<p>' . esc_html__( 'Provider account not found.', 'jq-marketplace-engine' ) . '</p>';
		}

		ob_start();
		$template = JQME_PLUGIN_DIR . 'templates/provider-profile-editor.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		return ob_get_clean();
	}
}
