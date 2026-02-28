<?php
/**
 * Claim handler — form processing, admin actions, and shortcode registration.
 *
 * Handles:
 * - Provider/customer filing a claim
 * - Evidence upload
 * - Admin settle/deny/close actions
 * - Condition report submissions
 *
 * Registers shortcodes:
 * - [jqme_file_claim]       — File a new claim
 * - [jqme_claim_detail]     — View a claim
 * - [jqme_condition_report] — Submit condition report
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME\Claims;

use JQME\StatusEnums;
use JQME\ConditionReports\ConditionReport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ClaimHandler {

	public function __construct() {
		// Claim actions.
		add_action( 'admin_post_jqme_file_claim', [ $this, 'handle_file_claim' ] );
		add_action( 'admin_post_jqme_add_evidence', [ $this, 'handle_add_evidence' ] );
		add_action( 'admin_post_jqme_withdraw_claim', [ $this, 'handle_withdraw_claim' ] );

		// Condition report actions.
		add_action( 'admin_post_jqme_submit_condition_report', [ $this, 'handle_submit_condition_report' ] );

		// Admin claim actions.
		add_action( 'admin_post_jqme_claim_settle', [ $this, 'handle_admin_settle' ] );
		add_action( 'admin_post_jqme_claim_deny', [ $this, 'handle_admin_deny' ] );
		add_action( 'admin_post_jqme_claim_close', [ $this, 'handle_admin_close' ] );
		add_action( 'admin_post_jqme_claim_set_status', [ $this, 'handle_admin_set_status' ] );

		// Auto-file claim on condition mismatch.
		add_action( 'jqme_condition_mismatch_flagged', [ $this, 'auto_suggest_claim' ], 10, 2 );

		// Shortcodes.
		add_shortcode( 'jqme_file_claim', [ $this, 'render_file_claim' ] );
		add_shortcode( 'jqme_claim_detail', [ $this, 'render_claim_detail' ] );
		add_shortcode( 'jqme_condition_report', [ $this, 'render_condition_report' ] );
	}

	/* ---------------------------------------------------------------
	 * CLAIM ACTIONS
	 * ------------------------------------------------------------- */

	public function handle_file_claim(): void {
		check_admin_referer( 'jqme_file_claim' );

		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in.', 'jq-marketplace-engine' ) );
		}

		$booking_id = absint( $_POST['booking_id'] ?? 0 );
		$booking    = \JQME\Bookings\Booking::get( $booking_id );

		if ( ! $booking ) {
			wp_safe_redirect( add_query_arg( 'jqme_error', 'invalid_booking', wp_get_referer() ) );
			exit;
		}

		// Verify user is party to this booking.
		$user_id  = get_current_user_id();
		$provider = \JQME\Providers\Provider::get( $booking->provider_id );
		$is_party = ( (int) $booking->customer_id === $user_id )
					|| ( $provider && (int) $provider->user_id === $user_id );

		if ( ! $is_party && ! current_user_can( 'jqme_manage_claims' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'jq-marketplace-engine' ) );
		}

		$claim_id = Claim::file( [
			'booking_id'       => $booking_id,
			'claim_type'       => sanitize_text_field( $_POST['claim_type'] ?? 'damage' ),
			'description'      => sanitize_textarea_field( $_POST['description'] ?? '' ),
			'amount_requested' => floatval( $_POST['amount_requested'] ?? 0 ),
		] );

		if ( ! $claim_id ) {
			wp_safe_redirect( add_query_arg( 'jqme_error', 'claim_failed', wp_get_referer() ) );
			exit;
		}

		// Handle evidence file uploads.
		if ( ! empty( $_FILES['evidence_files']['name'][0] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';

			foreach ( $_FILES['evidence_files']['name'] as $key => $name ) {
				if ( empty( $name ) ) {
					continue;
				}

				$file = [
					'name'     => $_FILES['evidence_files']['name'][ $key ],
					'type'     => $_FILES['evidence_files']['type'][ $key ],
					'tmp_name' => $_FILES['evidence_files']['tmp_name'][ $key ],
					'error'    => $_FILES['evidence_files']['error'][ $key ],
					'size'     => $_FILES['evidence_files']['size'][ $key ],
				];

				$upload = wp_handle_sideload( $file, [ 'test_form' => false ] );
				if ( ! empty( $upload['url'] ) ) {
					Claim::add_evidence( $claim_id, [
						'type'        => 'photo',
						'file_url'    => $upload['url'],
						'description' => sanitize_text_field( $_POST['evidence_description'] ?? '' ),
					] );
				}
			}
		}

		wp_safe_redirect( add_query_arg( [
			'jqme_notice' => 'claim_filed',
			'claim_id'    => $claim_id,
		], wp_get_referer() ) );
		exit;
	}

	public function handle_add_evidence(): void {
		$claim_id = absint( $_POST['claim_id'] ?? 0 );
		check_admin_referer( 'jqme_add_evidence_' . $claim_id );

		$claim = Claim::get( $claim_id );
		if ( ! $claim ) {
			wp_die( esc_html__( 'Claim not found.', 'jq-marketplace-engine' ) );
		}

		if ( ! empty( $_FILES['evidence_file']['name'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$upload = wp_handle_sideload( $_FILES['evidence_file'], [ 'test_form' => false ] );

			if ( ! empty( $upload['url'] ) ) {
				Claim::add_evidence( $claim_id, [
					'type'        => sanitize_text_field( $_POST['evidence_type'] ?? 'photo' ),
					'file_url'    => $upload['url'],
					'description' => sanitize_textarea_field( $_POST['evidence_description'] ?? '' ),
				] );
			}
		}

		wp_safe_redirect( add_query_arg( 'jqme_notice', 'evidence_added', wp_get_referer() ) );
		exit;
	}

	public function handle_withdraw_claim(): void {
		$claim_id = absint( $_POST['claim_id'] ?? 0 );
		check_admin_referer( 'jqme_withdraw_claim_' . $claim_id );

		$claim = Claim::get( $claim_id );
		if ( ! $claim || (int) $claim->filed_by !== get_current_user_id() ) {
			wp_die( esc_html__( 'Permission denied.', 'jq-marketplace-engine' ) );
		}

		Claim::set_status( $claim_id, StatusEnums::CLAIM_WITHDRAWN, 'Withdrawn by filer' );

		wp_safe_redirect( add_query_arg( 'jqme_notice', 'claim_withdrawn', wp_get_referer() ) );
		exit;
	}

	/* ---------------------------------------------------------------
	 * CONDITION REPORT ACTIONS
	 * ------------------------------------------------------------- */

	public function handle_submit_condition_report(): void {
		check_admin_referer( 'jqme_submit_condition_report' );

		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in.', 'jq-marketplace-engine' ) );
		}

		$photo_urls = [];
		if ( ! empty( $_FILES['condition_photos']['name'][0] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';

			foreach ( $_FILES['condition_photos']['name'] as $key => $name ) {
				if ( empty( $name ) ) {
					continue;
				}
				$file = [
					'name'     => $_FILES['condition_photos']['name'][ $key ],
					'type'     => $_FILES['condition_photos']['type'][ $key ],
					'tmp_name' => $_FILES['condition_photos']['tmp_name'][ $key ],
					'error'    => $_FILES['condition_photos']['error'][ $key ],
					'size'     => $_FILES['condition_photos']['size'][ $key ],
				];
				$upload = wp_handle_sideload( $file, [ 'test_form' => false ] );
				if ( ! empty( $upload['url'] ) ) {
					$photo_urls[] = $upload['url'];
				}
			}
		}

		// Build checklist from POST data.
		$checklist = [];
		$template  = ConditionReport::get_checklist_template();
		foreach ( array_keys( $template ) as $ck ) {
			$checklist[ $ck ] = sanitize_text_field( $_POST['checklist'][ $ck ] ?? '' );
		}

		$report_id = ConditionReport::submit( [
			'booking_id'      => absint( $_POST['booking_id'] ?? 0 ),
			'report_type'     => sanitize_text_field( $_POST['report_type'] ?? '' ),
			'condition_grade' => sanitize_text_field( $_POST['condition_grade'] ?? '' ),
			'notes'           => sanitize_textarea_field( $_POST['notes'] ?? '' ),
			'photo_urls'      => $photo_urls,
			'checklist'       => $checklist,
		] );

		if ( ! $report_id ) {
			wp_safe_redirect( add_query_arg( 'jqme_error', 'report_failed', wp_get_referer() ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( 'jqme_notice', 'report_submitted', wp_get_referer() ) );
		exit;
	}

	/* ---------------------------------------------------------------
	 * ADMIN ACTIONS
	 * ------------------------------------------------------------- */

	public function handle_admin_settle(): void {
		$claim_id = absint( $_POST['claim_id'] ?? 0 );
		check_admin_referer( 'jqme_claim_settle_' . $claim_id );

		if ( ! current_user_can( 'jqme_manage_claims' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'jq-marketplace-engine' ) );
		}

		$amount = floatval( $_POST['settled_amount'] ?? 0 );
		$notes  = sanitize_textarea_field( $_POST['resolution_notes'] ?? '' );

		Claim::settle( $claim_id, $amount, $notes );

		wp_safe_redirect( admin_url( 'admin.php?page=jqme-claims&action=view&id=' . $claim_id . '&jqme_notice=claim_settled' ) );
		exit;
	}

	public function handle_admin_deny(): void {
		$claim_id = absint( $_POST['claim_id'] ?? 0 );
		check_admin_referer( 'jqme_claim_deny_' . $claim_id );

		if ( ! current_user_can( 'jqme_manage_claims' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'jq-marketplace-engine' ) );
		}

		$notes = sanitize_textarea_field( $_POST['resolution_notes'] ?? '' );
		Claim::set_status( $claim_id, StatusEnums::CLAIM_DENIED, $notes );

		wp_safe_redirect( admin_url( 'admin.php?page=jqme-claims&action=view&id=' . $claim_id . '&jqme_notice=claim_denied' ) );
		exit;
	}

	public function handle_admin_close(): void {
		$claim_id = absint( $_POST['claim_id'] ?? 0 );
		check_admin_referer( 'jqme_claim_close_' . $claim_id );

		if ( ! current_user_can( 'jqme_manage_claims' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'jq-marketplace-engine' ) );
		}

		$notes = sanitize_textarea_field( $_POST['resolution_notes'] ?? '' );
		Claim::set_status( $claim_id, StatusEnums::CLAIM_CLOSED, $notes );

		wp_safe_redirect( admin_url( 'admin.php?page=jqme-claims&action=view&id=' . $claim_id . '&jqme_notice=claim_closed' ) );
		exit;
	}

	public function handle_admin_set_status(): void {
		$claim_id = absint( $_POST['claim_id'] ?? 0 );
		check_admin_referer( 'jqme_claim_set_status_' . $claim_id );

		if ( ! current_user_can( 'jqme_manage_claims' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'jq-marketplace-engine' ) );
		}

		$new_status = sanitize_text_field( $_POST['new_status'] ?? '' );
		$notes      = sanitize_textarea_field( $_POST['status_notes'] ?? '' );

		if ( $new_status ) {
			Claim::set_status( $claim_id, $new_status, $notes ?: 'Manual admin override' );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=jqme-claims&action=view&id=' . $claim_id . '&jqme_notice=status_updated' ) );
		exit;
	}

	/**
	 * When a condition mismatch is flagged, notify the provider to file a claim.
	 */
	public function auto_suggest_claim( int $report_id, int $booking_id ): void {
		// This fires a notification — the actual claim must still be filed manually.
		do_action( 'jqme_condition_mismatch_needs_claim', $report_id, $booking_id );
	}

	/* ---------------------------------------------------------------
	 * SHORTCODE RENDERERS
	 * ------------------------------------------------------------- */

	public function render_file_claim( $atts ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to file a claim.', 'jq-marketplace-engine' ) . '</p>';
		}
		ob_start();
		include JQME_PLUGIN_DIR . 'templates/file-claim.php';
		return ob_get_clean();
	}

	public function render_claim_detail( $atts ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view this claim.', 'jq-marketplace-engine' ) . '</p>';
		}
		ob_start();
		include JQME_PLUGIN_DIR . 'templates/claim-detail.php';
		return ob_get_clean();
	}

	public function render_condition_report( $atts ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to submit a condition report.', 'jq-marketplace-engine' ) . '</p>';
		}
		ob_start();
		include JQME_PLUGIN_DIR . 'templates/condition-report-form.php';
		return ob_get_clean();
	}
}
