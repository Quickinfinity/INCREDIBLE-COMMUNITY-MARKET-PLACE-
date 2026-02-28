<?php
/**
 * Listing request handler — processes front-end and admin form submissions.
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME\Listings;

use JQME\StatusEnums;
use JQME\Providers\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ListingHandler {

	public function __construct() {
		// Provider form handlers.
		add_action( 'admin_post_jqme_create_listing', [ $this, 'handle_create' ] );
		add_action( 'admin_post_jqme_update_listing', [ $this, 'handle_update' ] );
		add_action( 'admin_post_jqme_submit_listing', [ $this, 'handle_submit' ] );
		add_action( 'admin_post_jqme_submit_verification', [ $this, 'handle_submit_verification' ] );

		// Admin moderation handlers.
		add_action( 'admin_post_jqme_listing_approve', [ $this, 'handle_admin_approve' ] );
		add_action( 'admin_post_jqme_listing_reject', [ $this, 'handle_admin_reject' ] );
		add_action( 'admin_post_jqme_listing_request_changes', [ $this, 'handle_admin_request_changes' ] );
		add_action( 'admin_post_jqme_listing_suspend', [ $this, 'handle_admin_suspend' ] );

		// Admin verification handlers.
		add_action( 'admin_post_jqme_verification_approve', [ $this, 'handle_verify_approve' ] );
		add_action( 'admin_post_jqme_verification_reject', [ $this, 'handle_verify_reject' ] );

		// Auto-publish after verification.
		add_action( 'jqme_equipment_verified', [ $this, 'maybe_publish_after_verification' ], 10, 2 );

		// Shortcodes.
		add_shortcode( 'jqme_create_listing', [ $this, 'render_create_form' ] );
		add_shortcode( 'jqme_edit_listing', [ $this, 'render_edit_form' ] );
		add_shortcode( 'jqme_my_listings', [ $this, 'render_my_listings' ] );
		add_shortcode( 'jqme_browse_listings', [ $this, 'render_browse_listings' ] );
	}

	/* ---------------------------------------------------------------
	 * PROVIDER: CREATE / UPDATE / SUBMIT
	 * ------------------------------------------------------------- */

	public function handle_create(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Access denied.', 'jq-marketplace-engine' ) );
		}

		check_admin_referer( 'jqme_create_listing' );

		$provider = Provider::get_by_user( get_current_user_id() );
		if ( ! $provider || StatusEnums::PROVIDER_APPROVED !== $provider->status ) {
			wp_die( esc_html__( 'You must be an approved provider.', 'jq-marketplace-engine' ) );
		}

		$listing_type = sanitize_text_field( $_POST['listing_type'] ?? '' );
		$data = $this->extract_listing_data( $_POST );

		$listing_id = Listing::create( $provider->id, $listing_type, $data );

		if ( false === $listing_id ) {
			wp_safe_redirect( add_query_arg( 'jqme_notice', 'create_failed', wp_get_referer() ) );
			exit;
		}

		// Handle image uploads.
		$this->process_uploads( $listing_id );

		wp_safe_redirect( add_query_arg(
			[ 'jqme_notice' => 'listing_created', 'listing_id' => $listing_id ],
			wp_get_referer()
		) );
		exit;
	}

	public function handle_update(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Access denied.', 'jq-marketplace-engine' ) );
		}

		$listing_id = absint( $_POST['listing_id'] ?? 0 );
		check_admin_referer( 'jqme_update_listing_' . $listing_id );

		$listing = Listing::get( $listing_id );
		if ( ! $listing ) {
			wp_die( esc_html__( 'Listing not found.', 'jq-marketplace-engine' ) );
		}

		// Verify ownership.
		$provider = Provider::get_by_user( get_current_user_id() );
		if ( ! $provider || (int) $listing->provider_id !== (int) $provider->id ) {
			if ( ! current_user_can( 'jqme_edit_any_listing' ) ) {
				wp_die( esc_html__( 'Access denied.', 'jq-marketplace-engine' ) );
			}
		}

		$data = $this->extract_listing_data( $_POST );
		Listing::update( $listing_id, $data );

		$this->process_uploads( $listing_id );

		wp_safe_redirect( add_query_arg( 'jqme_notice', 'listing_updated', wp_get_referer() ) );
		exit;
	}

	public function handle_submit(): void {
		$listing_id = absint( $_POST['listing_id'] ?? 0 );
		check_admin_referer( 'jqme_submit_listing_' . $listing_id );

		$listing = Listing::get( $listing_id );
		if ( ! $listing ) {
			wp_die( esc_html__( 'Listing not found.', 'jq-marketplace-engine' ) );
		}

		$provider = Provider::get_by_user( get_current_user_id() );
		if ( ! $provider || (int) $listing->provider_id !== (int) $provider->id ) {
			wp_die( esc_html__( 'Access denied.', 'jq-marketplace-engine' ) );
		}

		$result = Listing::submit( $listing_id );
		$notice = $result ? 'listing_submitted' : 'submit_failed';

		wp_safe_redirect( add_query_arg( 'jqme_notice', $notice, wp_get_referer() ) );
		exit;
	}

	public function handle_submit_verification(): void {
		$listing_id = absint( $_POST['listing_id'] ?? 0 );
		check_admin_referer( 'jqme_submit_verification_' . $listing_id );

		$listing = Listing::get( $listing_id );
		if ( ! $listing ) {
			wp_die( esc_html__( 'Listing not found.', 'jq-marketplace-engine' ) );
		}

		$provider = Provider::get_by_user( get_current_user_id() );
		if ( ! $provider || (int) $listing->provider_id !== (int) $provider->id ) {
			wp_die( esc_html__( 'Access denied.', 'jq-marketplace-engine' ) );
		}

		$data = [
			'verification_type' => 'serial',
			'serial_number'     => sanitize_text_field( $_POST['serial_number'] ?? '' ),
			'document_urls'     => array_filter( array_map( 'esc_url_raw', $_POST['document_urls'] ?? [] ) ),
			'notes'             => sanitize_textarea_field( $_POST['verification_notes'] ?? '' ),
		];

		$result = Verification::submit( $listing_id, $provider->id, $data );
		$notice = $result ? 'verification_submitted' : 'verification_failed';

		wp_safe_redirect( add_query_arg( 'jqme_notice', $notice, wp_get_referer() ) );
		exit;
	}

	/* ---------------------------------------------------------------
	 * ADMIN: MODERATION
	 * ------------------------------------------------------------- */

	public function handle_admin_approve(): void {
		$this->admin_listing_action( 'approve' );
	}

	public function handle_admin_reject(): void {
		$this->admin_listing_action( 'reject' );
	}

	public function handle_admin_request_changes(): void {
		$this->admin_listing_action( 'request_changes' );
	}

	public function handle_admin_suspend(): void {
		$this->admin_listing_action( 'suspend' );
	}

	private function admin_listing_action( string $action ): void {
		if ( ! current_user_can( 'jqme_manage_listings' ) ) {
			wp_die( esc_html__( 'Access denied.', 'jq-marketplace-engine' ) );
		}

		$listing_id = absint( $_POST['listing_id'] ?? 0 );
		check_admin_referer( "jqme_listing_{$action}_{$listing_id}" );

		$reason = sanitize_textarea_field( $_POST['reason'] ?? '' );

		$result = match ( $action ) {
			'approve'         => Listing::approve( $listing_id ),
			'reject'          => Listing::set_status( $listing_id, StatusEnums::LISTING_SUSPENDED, $reason ),
			'request_changes' => Listing::request_changes( $listing_id, $reason ),
			'suspend'         => Listing::set_status( $listing_id, StatusEnums::LISTING_SUSPENDED, $reason ),
			default           => false,
		};

		$notice = $result ? "{$action}_success" : "{$action}_failed";

		wp_safe_redirect( add_query_arg(
			[ 'page' => 'jqme-listings', 'jqme_notice' => $notice ],
			admin_url( 'admin.php' )
		) );
		exit;
	}

	/* ---------------------------------------------------------------
	 * ADMIN: VERIFICATION
	 * ------------------------------------------------------------- */

	public function handle_verify_approve(): void {
		if ( ! current_user_can( 'jqme_verify_equipment' ) ) {
			wp_die( esc_html__( 'Access denied.', 'jq-marketplace-engine' ) );
		}

		$verification_id = absint( $_POST['verification_id'] ?? 0 );
		check_admin_referer( 'jqme_verification_approve_' . $verification_id );

		$notes = sanitize_textarea_field( $_POST['notes'] ?? '' );
		Verification::approve( $verification_id, $notes );

		wp_safe_redirect( add_query_arg(
			[ 'page' => 'jqme-verifications', 'jqme_notice' => 'verification_approved' ],
			admin_url( 'admin.php' )
		) );
		exit;
	}

	public function handle_verify_reject(): void {
		if ( ! current_user_can( 'jqme_verify_equipment' ) ) {
			wp_die( esc_html__( 'Access denied.', 'jq-marketplace-engine' ) );
		}

		$verification_id = absint( $_POST['verification_id'] ?? 0 );
		check_admin_referer( 'jqme_verification_reject_' . $verification_id );

		$notes = sanitize_textarea_field( $_POST['notes'] ?? '' );
		Verification::reject( $verification_id, $notes );

		wp_safe_redirect( add_query_arg(
			[ 'page' => 'jqme-verifications', 'jqme_notice' => 'verification_rejected' ],
			admin_url( 'admin.php' )
		) );
		exit;
	}

	/**
	 * Auto-publish listing after equipment verification if admin approval isn't required per-listing.
	 */
	public function maybe_publish_after_verification( int $listing_id, int $verification_id ): void {
		$listing = Listing::get( $listing_id );
		if ( ! $listing ) {
			return;
		}

		// Only auto-publish if listing was in verified or submitted status.
		$auto_publish_from = [ StatusEnums::LISTING_VERIFIED, StatusEnums::LISTING_SUBMITTED, StatusEnums::LISTING_UNDER_REVIEW ];
		if ( in_array( $listing->status, $auto_publish_from, true ) ) {
			Listing::set_status( $listing_id, StatusEnums::LISTING_PUBLISHED );
		}
	}

	/* ---------------------------------------------------------------
	 * SHORTCODES
	 * ------------------------------------------------------------- */

	public function render_create_form(): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in.', 'jq-marketplace-engine' ) . '</p>';
		}

		$provider = Provider::get_by_user( get_current_user_id() );
		if ( ! $provider || StatusEnums::PROVIDER_APPROVED !== $provider->status ) {
			return '<p>' . esc_html__( 'You must be an approved provider to create listings.', 'jq-marketplace-engine' ) . '</p>';
		}

		ob_start();
		$template = JQME_PLUGIN_DIR . 'templates/listing-create-form.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		return ob_get_clean();
	}

	public function render_edit_form(): string {
		$listing_id = absint( $_GET['listing_id'] ?? 0 );
		if ( ! $listing_id ) {
			return '<p>' . esc_html__( 'No listing specified.', 'jq-marketplace-engine' ) . '</p>';
		}

		$listing = Listing::get( $listing_id );
		if ( ! $listing ) {
			return '<p>' . esc_html__( 'Listing not found.', 'jq-marketplace-engine' ) . '</p>';
		}

		$provider = Provider::get_by_user( get_current_user_id() );
		if ( ! $provider || (int) $listing->provider_id !== (int) $provider->id ) {
			return '<p>' . esc_html__( 'Access denied.', 'jq-marketplace-engine' ) . '</p>';
		}

		ob_start();
		$template = JQME_PLUGIN_DIR . 'templates/listing-edit-form.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		return ob_get_clean();
	}

	public function render_my_listings(): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in.', 'jq-marketplace-engine' ) . '</p>';
		}

		$provider = Provider::get_by_user( get_current_user_id() );
		if ( ! $provider ) {
			return '<p>' . esc_html__( 'Provider account not found.', 'jq-marketplace-engine' ) . '</p>';
		}

		ob_start();
		$template = JQME_PLUGIN_DIR . 'templates/my-listings.php';
		if ( file_exists( $template ) ) {
			$listings = Listing::query( [ 'provider_id' => $provider->id, 'limit' => 100 ] );
			include $template;
		}
		return ob_get_clean();
	}

	public function render_browse_listings( $atts ): string {
		$atts = shortcode_atts( [ 'type' => '' ], $atts, 'jqme_browse_listings' );
		ob_start();
		include JQME_PLUGIN_DIR . 'templates/browse-listings.php';
		return ob_get_clean();
	}

	/* ---------------------------------------------------------------
	 * HELPERS
	 * ------------------------------------------------------------- */

	private function extract_listing_data( array $post ): array {
		// Pass through raw — Listing::create() and Listing::update() handle sanitization.
		return $post;
	}

	private function process_uploads( int $listing_id ): void {
		if ( empty( $_FILES['listing_images'] ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$files = $_FILES['listing_images'];

		// Handle multiple file upload array.
		if ( is_array( $files['name'] ) ) {
			$count = count( $files['name'] );
			for ( $i = 0; $i < $count; $i++ ) {
				if ( UPLOAD_ERR_OK !== $files['error'][ $i ] ) {
					continue;
				}

				$file_array = [
					'name'     => $files['name'][ $i ],
					'type'     => $files['type'][ $i ],
					'tmp_name' => $files['tmp_name'][ $i ],
					'error'    => $files['error'][ $i ],
					'size'     => $files['size'][ $i ],
				];

				$attachment_id = media_handle_sideload( $file_array, 0 );
				if ( ! is_wp_error( $attachment_id ) ) {
					Listing::add_asset( $listing_id, [
						'asset_type' => 'image',
						'file_url'   => wp_get_attachment_url( $attachment_id ),
						'file_name'  => $files['name'][ $i ],
						'mime_type'  => $files['type'][ $i ],
						'sort_order' => $i,
						'is_primary' => 0 === $i,
					] );
				}
			}
		}
	}
}
