<?php
/**
 * Custom roles and capabilities for the marketplace.
 *
 * Three roles:
 * - jqme_provider: approved members who list equipment/services
 * - jqme_customer: members who book/buy
 * - administrator: gets all jqme_ capabilities added
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME\Roles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Roles {

	/**
	 * Create custom roles and add capabilities to administrator.
	 * Called on plugin activation.
	 */
	public function create_roles(): void {
		$this->add_provider_role();
		$this->add_customer_role();
		$this->add_admin_capabilities();
	}

	/**
	 * Provider role — inherits subscriber + marketplace provider caps.
	 */
	private function add_provider_role(): void {
		remove_role( 'jqme_provider' );

		add_role( 'jqme_provider', __( 'Marketplace Provider', 'jq-marketplace-engine' ), [
			// WordPress base.
			'read'                     => true,
			'upload_files'             => true,
			'edit_posts'               => false,

			// Provider capabilities.
			'jqme_manage_own_profile'  => true,
			'jqme_create_listings'     => true,
			'jqme_edit_own_listings'   => true,
			'jqme_delete_own_listings' => true,
			'jqme_view_own_bookings'   => true,
			'jqme_manage_own_bookings' => true,
			'jqme_view_own_calendar'   => true,
			'jqme_manage_own_calendar' => true,
			'jqme_submit_condition_reports' => true,
			'jqme_respond_to_claims'   => true,
			'jqme_submit_reviews'      => true,
			'jqme_view_own_payouts'    => true,
			'jqme_view_own_reports'    => true,
			'jqme_upload_contracts'    => true,
			'jqme_manage_own_payout_account' => true,
		] );
	}

	/**
	 * Customer role — inherits subscriber + marketplace customer caps.
	 */
	private function add_customer_role(): void {
		remove_role( 'jqme_customer' );

		add_role( 'jqme_customer', __( 'Marketplace Customer', 'jq-marketplace-engine' ), [
			// WordPress base.
			'read'                     => true,
			'upload_files'             => true,
			'edit_posts'               => false,

			// Customer capabilities.
			'jqme_browse_listings'     => true,
			'jqme_request_bookings'    => true,
			'jqme_view_own_bookings'   => true,
			'jqme_view_own_orders'     => true,
			'jqme_save_listings'       => true,
			'jqme_submit_reviews'      => true,
			'jqme_file_claims'         => true,
			'jqme_submit_evidence'     => true,
			'jqme_manage_own_payment_methods' => true,
			'jqme_submit_condition_reports'    => true,
		] );
	}

	/**
	 * Grant all marketplace capabilities to administrators.
	 */
	private function add_admin_capabilities(): void {
		$admin_role = get_role( 'administrator' );
		if ( ! $admin_role ) {
			return;
		}

		$admin_caps = self::admin_capabilities();
		foreach ( $admin_caps as $cap ) {
			$admin_role->add_cap( $cap );
		}
	}

	/**
	 * Full list of admin-level marketplace capabilities.
	 */
	public static function admin_capabilities(): array {
		return [
			// Top-level.
			'jqme_manage_marketplace',

			// Providers.
			'jqme_manage_providers',
			'jqme_approve_providers',
			'jqme_suspend_providers',

			// Listings.
			'jqme_manage_listings',
			'jqme_approve_listings',
			'jqme_edit_any_listing',
			'jqme_delete_any_listing',
			'jqme_feature_listings',

			// Verifications.
			'jqme_manage_verifications',
			'jqme_verify_equipment',

			// Bookings.
			'jqme_manage_bookings',
			'jqme_override_booking_status',
			'jqme_cancel_any_booking',

			// Payments.
			'jqme_manage_payments',
			'jqme_process_payouts',
			'jqme_issue_refunds',
			'jqme_manage_deposits',

			// Claims.
			'jqme_manage_claims',
			'jqme_resolve_claims',

			// Reviews.
			'jqme_manage_reviews',
			'jqme_moderate_reviews',
			'jqme_delete_reviews',

			// Settings.
			'jqme_manage_settings',
			'jqme_manage_policies',
			'jqme_manage_pricing',

			// Reports.
			'jqme_view_reports',
			'jqme_export_data',
			'jqme_view_audit_log',
		];
	}

	/**
	 * Check if current user has a specific marketplace capability.
	 */
	public static function current_user_can( string $capability ): bool {
		return current_user_can( $capability );
	}

	/**
	 * Get the marketplace role for a given user.
	 */
	public static function get_user_marketplace_role( int $user_id ): string {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return '';
		}

		if ( in_array( 'administrator', $user->roles, true ) ) {
			return 'admin';
		}
		if ( in_array( 'jqme_provider', $user->roles, true ) ) {
			return 'provider';
		}
		if ( in_array( 'jqme_customer', $user->roles, true ) ) {
			return 'customer';
		}

		return '';
	}
}
