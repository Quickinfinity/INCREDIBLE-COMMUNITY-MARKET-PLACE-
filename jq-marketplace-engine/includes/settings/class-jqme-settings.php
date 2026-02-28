<?php
/**
 * Settings engine — admin-editable business rules.
 *
 * All marketplace variables are stored in wp_options under grouped keys.
 * Nothing is hardcoded. Every value has a sane default that matches
 * the build brief, but admins can change everything from the dashboard.
 *
 * Settings are organized into groups matching the 18 setting categories
 * defined in the build brief.
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	/** @var string Option key prefix. */
	const PREFIX = 'jqme_settings_';

	/** @var array<string, array> Cached settings. */
	private static array $cache = [];

	/**
	 * Get a single setting value.
	 *
	 * @param string $group   Setting group key (e.g. 'global', 'payments').
	 * @param string $key     Setting key within the group.
	 * @param mixed  $default Fallback if not set (uses built-in default if null).
	 */
	public static function get( string $group, string $key, mixed $default = null ): mixed {
		$settings = self::get_group( $group );

		if ( array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}

		if ( null !== $default ) {
			return $default;
		}

		// Fall back to the built-in default.
		$defaults = self::defaults( $group );
		return $defaults[ $key ] ?? null;
	}

	/**
	 * Get all settings for a group.
	 */
	public static function get_group( string $group ): array {
		if ( isset( self::$cache[ $group ] ) ) {
			return self::$cache[ $group ];
		}

		$stored  = get_option( self::PREFIX . $group, [] );
		$defaults = self::defaults( $group );

		// Merge stored over defaults so new defaults appear automatically.
		self::$cache[ $group ] = wp_parse_args( $stored, $defaults );

		return self::$cache[ $group ];
	}

	/**
	 * Update one or more settings in a group.
	 */
	public static function update( string $group, array $values ): bool {
		$current = self::get_group( $group );
		$merged  = array_merge( $current, $values );

		self::$cache[ $group ] = $merged;

		return update_option( self::PREFIX . $group, $merged, false );
	}

	/**
	 * Update a single setting.
	 */
	public static function set( string $group, string $key, mixed $value ): bool {
		return self::update( $group, [ $key => $value ] );
	}

	/**
	 * Clear the in-memory cache (useful after bulk updates).
	 */
	public static function flush_cache(): void {
		self::$cache = [];
	}

	/**
	 * Set all defaults on activation (only writes if option doesn't exist).
	 */
	public function set_defaults(): void {
		$groups = self::all_group_keys();

		foreach ( $groups as $group ) {
			if ( false === get_option( self::PREFIX . $group ) ) {
				add_option( self::PREFIX . $group, self::defaults( $group ), '', false );
			}
		}
	}

	/**
	 * All setting group keys.
	 */
	public static function all_group_keys(): array {
		return [
			'global',
			'membership',
			'equipment_verification',
			'service_provider',
			'pricing',
			'payments',
			'claims',
			'late_return',
			'cancellation',
			'insurance',
			'reviews',
			'delivery',
			'service_booking',
			'sales',
			'contracts',
			'automation',
			'search',
			'reporting',
		];
	}

	/**
	 * Group labels for admin UI.
	 */
	public static function group_labels(): array {
		return [
			'global'                 => __( 'Global Marketplace', 'jq-marketplace-engine' ),
			'membership'             => __( 'Membership & Access', 'jq-marketplace-engine' ),
			'equipment_verification' => __( 'Equipment Verification', 'jq-marketplace-engine' ),
			'service_provider'       => __( 'Service Providers', 'jq-marketplace-engine' ),
			'pricing'                => __( 'Pricing', 'jq-marketplace-engine' ),
			'payments'               => __( 'Payments & Payouts', 'jq-marketplace-engine' ),
			'claims'                 => __( 'Claims & Damage', 'jq-marketplace-engine' ),
			'late_return'            => __( 'Late Return / Overrun', 'jq-marketplace-engine' ),
			'cancellation'           => __( 'Cancellation & No-Show', 'jq-marketplace-engine' ),
			'insurance'              => __( 'Insurance & Compliance', 'jq-marketplace-engine' ),
			'reviews'                => __( 'Reviews & Reputation', 'jq-marketplace-engine' ),
			'delivery'               => __( 'Delivery & Logistics', 'jq-marketplace-engine' ),
			'service_booking'        => __( 'Service Bookings', 'jq-marketplace-engine' ),
			'sales'                  => __( 'Sales', 'jq-marketplace-engine' ),
			'contracts'              => __( 'Contracts & Legal', 'jq-marketplace-engine' ),
			'automation'             => __( 'Automation & Notifications', 'jq-marketplace-engine' ),
			'search'                 => __( 'Search & Merchandising', 'jq-marketplace-engine' ),
			'reporting'              => __( 'Reporting', 'jq-marketplace-engine' ),
		];
	}

	/**
	 * Default values for each setting group.
	 *
	 * These match the build brief exactly. Every one of these is admin-editable.
	 */
	public static function defaults( string $group ): array {
		$map = [
			'global'                 => self::defaults_global(),
			'membership'             => self::defaults_membership(),
			'equipment_verification' => self::defaults_equipment_verification(),
			'service_provider'       => self::defaults_service_provider(),
			'pricing'                => self::defaults_pricing(),
			'payments'               => self::defaults_payments(),
			'claims'                 => self::defaults_claims(),
			'late_return'            => self::defaults_late_return(),
			'cancellation'           => self::defaults_cancellation(),
			'insurance'              => self::defaults_insurance(),
			'reviews'                => self::defaults_reviews(),
			'delivery'               => self::defaults_delivery(),
			'service_booking'        => self::defaults_service_booking(),
			'sales'                  => self::defaults_sales(),
			'contracts'              => self::defaults_contracts(),
			'automation'             => self::defaults_automation(),
			'search'                 => self::defaults_search(),
			'reporting'              => self::defaults_reporting(),
		];

		return $map[ $group ] ?? [];
	}

	/* ===================================================================
	 * GROUP 1: GLOBAL MARKETPLACE
	 * ================================================================= */

	private static function defaults_global(): array {
		return [
			'enable_equipment_rentals'          => true,
			'enable_equipment_sales'            => true,
			'enable_service_bookings'           => true,
			'platform_fee_percent'              => 9.9,
			'processing_fee_paid_by'            => 'customer', // customer|provider|split
			'default_currency'                  => 'USD',
			'tax_behavior'                      => 'none', // none|inclusive|exclusive
			'default_timezone'                  => 'America/New_York',
			'default_distance_unit'             => 'miles', // miles|km
			'featured_listing_rules'            => 'manual', // manual|subscription|paid
			'provider_approval_required'        => true,
			'customer_verification_required'    => false,
			'admin_approval_first_listing'      => true,
			'admin_approval_every_listing'      => false,
			'instant_book_allowed'              => false,
			'request_to_book_default'           => true,
			'platform_name'                     => 'Incredible Community Marketplace',
			'platform_facilitator_disclaimer'   => 'This platform acts as a marketplace facilitator only. We are not a guarantor, insurer, or legal judge.',
		];
	}

	/* ===================================================================
	 * GROUP 2: MEMBERSHIP & ACCESS
	 * ================================================================= */

	private static function defaults_membership(): array {
		return [
			'who_may_apply_provider'          => 'any_registered', // any_registered|membership_required
			'required_membership_tier'        => '',
			'allowed_listing_types_by_tier'   => [], // tier => [types]
			'provider_seat_limit'             => 0, // 0 = unlimited
			'listing_limit_per_provider'      => 0, // 0 = unlimited
			'reapproval_after_inactivity_days' => 365,
			'suspension_threshold_claims'     => 3,
			'suspension_threshold_low_rating' => 2.0,
			'identity_verification_required'  => false,
			'business_verification_required'  => false,
		];
	}

	/* ===================================================================
	 * GROUP 3: EQUIPMENT VERIFICATION
	 * ================================================================= */

	private static function defaults_equipment_verification(): array {
		return [
			'serial_required'                 => true,
			'serial_format_regex'             => '',
			'serial_admin_review_required'    => true,
			'required_image_count'            => 4,
			'required_proof_of_ownership'     => true,
			'allowed_equipment_categories'    => [],
			'required_condition_checklist'    => true,
			'required_manual_upload'          => false,
		];
	}

	/* ===================================================================
	 * GROUP 4: SERVICE PROVIDER
	 * ================================================================= */

	private static function defaults_service_provider(): array {
		return [
			'certification_required'            => true,
			'certification_expiry_tracking'     => true,
			'background_approval_required'      => false,
			'portfolio_required'                => false,
			'min_profile_completion_percent'    => 80,
			'default_service_radius_miles'      => 50,
			'allowed_service_categories'        => [],
			'travel_charge_per_mile'            => 0.00,
			'travel_charge_minimum'             => 0.00,
		];
	}

	/* ===================================================================
	 * GROUP 5: PRICING
	 * ================================================================= */

	private static function defaults_pricing(): array {
		return [
			'default_suggested_prices'         => [], // category => [day, week, month]
			'min_price_override'               => 0.00,
			'max_price_override'               => 0.00, // 0 = no cap
			'default_deposit_percent'          => 25,
			'default_deposit_flat'             => 0.00,
			'default_weekend_multiplier'       => 1.0,
			'seasonal_pricing_enabled'         => false,
			'surge_pricing_enabled'            => false,
			'admin_can_force_floor_pricing'    => true,
			'providers_can_discount'           => true,
			'coupons_allowed'                  => false,
			'quote_request_mode_services'      => true,
		];
	}

	/* ===================================================================
	 * GROUP 6: PAYMENTS & PAYOUTS
	 * ================================================================= */

	private static function defaults_payments(): array {
		return [
			'payment_methods_enabled'          => [ 'card' ],
			'connected_account_required'       => true,
			'payout_delay_days'                => 3,
			'rolling_reserve_enabled'          => false,
			'rolling_reserve_percent'          => 0,
			'payout_hold_on_flagged'           => true,
			'partial_capture_allowed'          => true,
			'split_payout_rules'               => 'standard', // standard|custom
			'deposit_auth_vs_capture_default'  => 'authorize', // authorize|capture
			'deposit_auto_release_days'        => 7,
			'refund_priority_order'            => 'original_method', // original_method|store_credit|manual
			'failed_payment_retry_count'       => 3,
			'failed_payment_retry_interval_hours' => 24,
			'payment_gateway'                  => 'stripe', // stripe|paypal|manual
		];
	}

	/* ===================================================================
	 * GROUP 7: CLAIMS & DAMAGE
	 * ================================================================= */

	private static function defaults_claims(): array {
		return [
			'claim_window_hours'               => 72,
			'claim_evidence_required'           => true,
			'claim_min_photo_count'             => 3,
			'admin_mediation_mode'              => true,
			'facilitator_disclaimer'            => 'The platform facilitates the claims process but does not serve as insurer, guarantor, or legal judge.',
			'customer_response_window_hours'    => 48,
			'provider_rebuttal_window_hours'    => 48,
			'auto_close_claim_days'             => 30,
			'deposit_draw_order'                => 'deposit_first', // deposit_first|charge_first
			'max_claim_cap_percent'             => 100, // % of deposit
			'documentation_retention_days'      => 365,
		];
	}

	/* ===================================================================
	 * GROUP 8: LATE RETURN / OVERRUN
	 * ================================================================= */

	private static function defaults_late_return(): array {
		return [
			'grace_period_hours'               => 2,
			'late_fee_formula_type'             => 'daily', // flat|hourly|daily|percent
			'late_fee_flat_amount'              => 0.00,
			'late_fee_hourly_amount'            => 0.00,
			'late_fee_daily_amount'             => 0.00,
			'late_fee_percent_of_day_rate'      => 50,
			'max_late_fee_cap'                  => 0.00, // 0 = no cap
			'auto_extension_allowed'            => false,
			'auto_extension_rate_multiplier'    => 1.5,
			'force_provider_approval_extension' => true,
			'overdue_reminder_cadence_hours'    => 12,
		];
	}

	/* ===================================================================
	 * GROUP 9: CANCELLATION & NO-SHOW
	 * ================================================================= */

	private static function defaults_cancellation(): array {
		return [
			'provider_cancel_window_hours'         => 24,
			'customer_cancel_full_refund_hours'     => 48,
			'customer_cancel_partial_refund_hours'  => 24,
			'customer_cancel_partial_refund_percent' => 50,
			'customer_cancel_no_refund_hours'       => 0,
			'no_show_definition_minutes'            => 60,
			'no_show_customer_penalty_percent'      => 100,
			'no_show_provider_penalty_percent'      => 0,
			'reschedule_allowed'                    => true,
			'reschedule_deadline_hours'             => 24,
			'one_time_courtesy_reschedule'          => true,
			'service_specific_no_show_rules'        => [],
			'equipment_specific_no_show_rules'      => [],
		];
	}

	/* ===================================================================
	 * GROUP 10: INSURANCE & COMPLIANCE
	 * ================================================================= */

	private static function defaults_insurance(): array {
		return [
			'insurance_required'                   => false,
			'min_coverage_amount'                  => 0.00,
			'certificate_upload_required'           => false,
			'expiry_tracking_enabled'              => true,
			'waiver_required'                      => false,
			'state_specific_disclaimers'            => [],
			'provider_compliance_checklist'         => [],
			'customer_acknowledgment_checklist'     => [],
		];
	}

	/* ===================================================================
	 * GROUP 11: REVIEWS & REPUTATION
	 * ================================================================= */

	private static function defaults_reviews(): array {
		return [
			'mandatory_two_way_reviews'            => true,
			'review_window_days'                   => 14,
			'review_prompt_cadence_days'            => 3,
			'min_rating_warning_threshold'          => 3.0,
			'auto_flag_low_rated_threshold'         => 2.0,
			'hidden_pending_mutual_review'          => true,
			'review_dispute_reporting_enabled'      => true,
			'rating_categories_rental'              => [ 'equipment_condition', 'communication', 'accuracy', 'value' ],
			'rating_categories_sale'                => [ 'item_as_described', 'communication', 'shipping_speed' ],
			'rating_categories_service'             => [ 'quality_of_work', 'communication', 'professionalism', 'value' ],
			'public_review_visibility'              => 'after_mutual', // after_mutual|immediate|admin_approved
		];
	}

	/* ===================================================================
	 * GROUP 12: DELIVERY & LOGISTICS
	 * ================================================================= */

	private static function defaults_delivery(): array {
		return [
			'global_delivery_enabled'              => true,
			'provider_delivery_override_allowed'   => true,
			'default_delivery_radius_miles'        => 25,
			'delivery_fee_formula'                 => 'flat', // flat|per_mile|tiered
			'mileage_fee_per_mile'                 => 1.50,
			'minimum_delivery_charge'              => 25.00,
			'delivery_scheduling_buffer_hours'     => 24,
			'delivery_blackout_windows'            => [],
			'pickup_instructions_required'         => true,
			'return_instructions_required'         => true,
		];
	}

	/* ===================================================================
	 * GROUP 13: SERVICE BOOKING
	 * ================================================================= */

	private static function defaults_service_booking(): array {
		return [
			'virtual_bookings_enabled'             => true,
			'onsite_bookings_enabled'              => true,
			'travel_fees_enabled'                  => true,
			'consultation_call_mode'               => false,
			'booking_buffer_hours'                 => 4,
			'prep_form_required'                   => false,
			'deliverable_confirmation_required'     => true,
			'session_completion_code'               => false,
			'recurring_service_packages_allowed'    => false,
		];
	}

	/* ===================================================================
	 * GROUP 14: SALES
	 * ================================================================= */

	private static function defaults_sales(): array {
		return [
			'offers_enabled'                       => true,
			'fixed_price_enabled'                  => true,
			'shipping_enabled'                     => true,
			'local_pickup_enabled'                 => true,
			'sales_return_window_days'             => 0, // 0 = no returns
			'restocking_fee_percent'               => 0,
			'sold_item_relist_allowed'             => false,
			'item_inspection_before_sold'          => false,
		];
	}

	/* ===================================================================
	 * GROUP 15: CONTRACTS & LEGAL
	 * ================================================================= */

	private static function defaults_contracts(): array {
		return [
			'platform_terms_version'               => '1.0',
			'provider_addendum_required'            => false,
			'customer_signature_required'           => false,
			'contract_acceptance_at_checkout'       => true,
			'downloadable_pdfs_enabled'            => true,
			'per_listing_custom_contract'           => true,
			'legal_disclaimer_rental'               => '',
			'legal_disclaimer_sale'                 => '',
			'legal_disclaimer_service'              => '',
		];
	}

	/* ===================================================================
	 * GROUP 16: AUTOMATION & NOTIFICATIONS
	 * ================================================================= */

	private static function defaults_automation(): array {
		return [
			'email_booking_request'                => true,
			'email_approval_reminder'              => true,
			'email_claim_reminder'                 => true,
			'email_overdue_reminder'               => true,
			'email_review_reminder'                => true,
			'email_payout_notice'                  => true,
			'email_cancellation_notice'            => true,
			'email_no_show_notice'                 => true,
			'email_digest_summary'                 => false,
			'digest_frequency'                     => 'weekly', // daily|weekly
			'admin_escalation_alerts'              => true,
			'sms_enabled'                          => false,
			'sms_provider'                         => '', // twilio|vonage|etc
		];
	}

	/* ===================================================================
	 * GROUP 17: SEARCH & MERCHANDISING
	 * ================================================================= */

	private static function defaults_search(): array {
		return [
			'featured_ranking_weight'              => 10,
			'distance_ranking_weight'              => 8,
			'price_ranking_weight'                 => 5,
			'review_ranking_weight'                => 7,
			'hide_incomplete_profiles'             => true,
			'hide_unverified_listings'             => true,
			'promoted_listings_enabled'            => false,
			'manual_curation_mode'                 => false,
			'default_results_per_page'             => 20,
			'default_sort'                         => 'relevance', // relevance|distance|price_low|price_high|rating|newest
		];
	}

	/* ===================================================================
	 * GROUP 18: REPORTING
	 * ================================================================= */

	private static function defaults_reporting(): array {
		return [
			'export_formats'                       => [ 'csv', 'json' ],
			'retention_window_days'                => 730, // 2 years
			'dashboard_widgets_enabled'            => true,
			'provider_scorecards_enabled'          => true,
			'revenue_breakdown_enabled'            => true,
			'fee_breakdown_enabled'                => true,
			'claim_stats_enabled'                  => true,
			'utilization_stats_enabled'            => true,
			'service_conversion_stats_enabled'     => true,
		];
	}
}
