<?php
/**
 * Database schema manager.
 *
 * Creates and upgrades all custom marketplace tables using dbDelta().
 * Tables use InnoDB for foreign key support and proper transaction handling.
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema {

	/**
	 * Get the full prefixed table name.
	 */
	private function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . JQME_TABLE_PREFIX . $name;
	}

	/**
	 * Install or upgrade all tables.
	 */
	public function install(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = $this->get_providers_sql( $charset_collate )
			. $this->get_provider_meta_sql( $charset_collate )
			. $this->get_policy_profiles_sql( $charset_collate )
			. $this->get_listings_sql( $charset_collate )
			. $this->get_listing_meta_sql( $charset_collate )
			. $this->get_listing_assets_sql( $charset_collate )
			. $this->get_verifications_sql( $charset_collate )
			. $this->get_availability_sql( $charset_collate )
			. $this->get_bookings_sql( $charset_collate )
			. $this->get_booking_items_sql( $charset_collate )
			. $this->get_transactions_sql( $charset_collate )
			. $this->get_deposits_sql( $charset_collate )
			. $this->get_payouts_sql( $charset_collate )
			. $this->get_condition_reports_sql( $charset_collate )
			. $this->get_claims_sql( $charset_collate )
			. $this->get_claim_evidence_sql( $charset_collate )
			. $this->get_reviews_sql( $charset_collate )
			. $this->get_notifications_sql( $charset_collate )
			. $this->get_audit_log_sql( $charset_collate );

		dbDelta( $sql );
	}

	/* ---------------------------------------------------------------
	 * TABLE DEFINITIONS
	 * ------------------------------------------------------------- */

	/**
	 * Providers — approved members who can list equipment/services.
	 */
	private function get_providers_sql( string $charset_collate ): string {
		$table = $this->table( 'providers' );
		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			company_name varchar(255) NOT NULL DEFAULT '',
			contact_name varchar(255) NOT NULL DEFAULT '',
			contact_email varchar(255) NOT NULL DEFAULT '',
			contact_phone varchar(50) NOT NULL DEFAULT '',
			address_line1 varchar(255) NOT NULL DEFAULT '',
			address_line2 varchar(255) NOT NULL DEFAULT '',
			city varchar(100) NOT NULL DEFAULT '',
			state varchar(100) NOT NULL DEFAULT '',
			zip varchar(20) NOT NULL DEFAULT '',
			country varchar(10) NOT NULL DEFAULT 'US',
			latitude decimal(10,7) DEFAULT NULL,
			longitude decimal(10,7) DEFAULT NULL,
			service_radius_miles int(10) unsigned NOT NULL DEFAULT 50,
			can_deliver tinyint(1) NOT NULL DEFAULT 0,
			delivery_radius_miles int(10) unsigned NOT NULL DEFAULT 0,
			payout_account_id varchar(255) NOT NULL DEFAULT '',
			payout_account_status varchar(50) NOT NULL DEFAULT 'not_connected',
			insurance_verified tinyint(1) NOT NULL DEFAULT 0,
			insurance_expiry date DEFAULT NULL,
			trust_score decimal(3,2) NOT NULL DEFAULT 0.00,
			status varchar(50) NOT NULL DEFAULT 'pending_application',
			allowed_listing_types text DEFAULT NULL,
			listing_limit int(10) unsigned NOT NULL DEFAULT 0,
			suspension_reason text DEFAULT NULL,
			applied_at datetime DEFAULT NULL,
			approved_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY user_id (user_id),
			KEY status (status),
			KEY zip (zip),
			KEY latitude_longitude (latitude, longitude)
		) {$charset_collate};\n\n";
	}

	/**
	 * Provider meta — flexible key/value storage for provider-specific data.
	 */
	private function get_provider_meta_sql( string $charset_collate ): string {
		$table = $this->table( 'provider_meta' );
		return "CREATE TABLE {$table} (
			meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			provider_id bigint(20) unsigned NOT NULL,
			meta_key varchar(255) NOT NULL,
			meta_value longtext DEFAULT NULL,
			PRIMARY KEY  (meta_id),
			KEY provider_id (provider_id),
			KEY meta_key (meta_key(191))
		) {$charset_collate};\n\n";
	}

	/**
	 * Policy profiles — reusable rule sets assigned to listings/providers.
	 */
	private function get_policy_profiles_sql( string $charset_collate ): string {
		$table = $this->table( 'policy_profiles' );
		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			slug varchar(255) NOT NULL,
			type varchar(50) NOT NULL DEFAULT 'general',
			applies_to varchar(50) NOT NULL DEFAULT 'all',
			rules longtext NOT NULL,
			is_default tinyint(1) NOT NULL DEFAULT 0,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY type (type),
			KEY applies_to (applies_to),
			KEY is_default (is_default)
		) {$charset_collate};\n\n";
	}

	/**
	 * Listings — equipment rentals, equipment sales, service bookings.
	 */
	private function get_listings_sql( string $charset_collate ): string {
		$table = $this->table( 'listings' );
		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			provider_id bigint(20) unsigned NOT NULL,
			listing_type varchar(30) NOT NULL,
			title varchar(255) NOT NULL,
			slug varchar(255) NOT NULL DEFAULT '',
			description longtext DEFAULT NULL,
			category varchar(100) NOT NULL DEFAULT '',
			subcategory varchar(100) NOT NULL DEFAULT '',
			status varchar(50) NOT NULL DEFAULT 'draft',
			featured tinyint(1) NOT NULL DEFAULT 0,
			brand varchar(255) NOT NULL DEFAULT '',
			model varchar(255) NOT NULL DEFAULT '',
			serial_number varchar(255) NOT NULL DEFAULT '',
			condition_grade varchar(50) NOT NULL DEFAULT '',
			day_rate decimal(10,2) DEFAULT NULL,
			weekend_rate decimal(10,2) DEFAULT NULL,
			week_rate decimal(10,2) DEFAULT NULL,
			month_rate decimal(10,2) DEFAULT NULL,
			asking_price decimal(10,2) DEFAULT NULL,
			hourly_rate decimal(10,2) DEFAULT NULL,
			half_day_rate decimal(10,2) DEFAULT NULL,
			full_day_rate decimal(10,2) DEFAULT NULL,
			deposit_amount decimal(10,2) NOT NULL DEFAULT 0.00,
			quantity int(10) unsigned NOT NULL DEFAULT 1,
			pickup_available tinyint(1) NOT NULL DEFAULT 1,
			delivery_available tinyint(1) NOT NULL DEFAULT 0,
			shipping_available tinyint(1) NOT NULL DEFAULT 0,
			virtual_available tinyint(1) NOT NULL DEFAULT 0,
			onsite_available tinyint(1) NOT NULL DEFAULT 0,
			delivery_radius_miles int(10) unsigned NOT NULL DEFAULT 0,
			delivery_fee decimal(10,2) NOT NULL DEFAULT 0.00,
			shipping_fee decimal(10,2) NOT NULL DEFAULT 0.00,
			travel_fee_rules text DEFAULT NULL,
			service_radius_miles int(10) unsigned NOT NULL DEFAULT 0,
			min_rental_days int(10) unsigned NOT NULL DEFAULT 1,
			max_rental_days int(10) unsigned NOT NULL DEFAULT 365,
			min_booking_hours decimal(5,2) NOT NULL DEFAULT 1.00,
			lead_time_hours int(10) unsigned NOT NULL DEFAULT 24,
			turnaround_buffer_hours int(10) unsigned NOT NULL DEFAULT 4,
			cancellation_policy_id bigint(20) unsigned DEFAULT NULL,
			insurance_policy_id bigint(20) unsigned DEFAULT NULL,
			return_policy_id bigint(20) unsigned DEFAULT NULL,
			late_fee_rule text DEFAULT NULL,
			included_accessories text DEFAULT NULL,
			optional_accessories text DEFAULT NULL,
			safety_notes text DEFAULT NULL,
			deliverables text DEFAULT NULL,
			prep_checklist text DEFAULT NULL,
			certification_level varchar(100) NOT NULL DEFAULT '',
			warranty_disclaimer text DEFAULT NULL,
			offers_allowed tinyint(1) NOT NULL DEFAULT 0,
			contract_file_url varchar(500) NOT NULL DEFAULT '',
			operating_instructions_url varchar(500) NOT NULL DEFAULT '',
			average_rating decimal(3,2) NOT NULL DEFAULT 0.00,
			review_count int(10) unsigned NOT NULL DEFAULT 0,
			view_count bigint(20) unsigned NOT NULL DEFAULT 0,
			booking_count int(10) unsigned NOT NULL DEFAULT 0,
			published_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY provider_id (provider_id),
			KEY listing_type (listing_type),
			KEY status (status),
			KEY category (category),
			KEY featured (featured),
			KEY slug (slug(191))
		) {$charset_collate};\n\n";
	}

	/**
	 * Listing meta — flexible key/value for listing-specific data.
	 */
	private function get_listing_meta_sql( string $charset_collate ): string {
		$table = $this->table( 'listing_meta' );
		return "CREATE TABLE {$table} (
			meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			listing_id bigint(20) unsigned NOT NULL,
			meta_key varchar(255) NOT NULL,
			meta_value longtext DEFAULT NULL,
			PRIMARY KEY  (meta_id),
			KEY listing_id (listing_id),
			KEY meta_key (meta_key(191))
		) {$charset_collate};\n\n";
	}

	/**
	 * Listing assets — photos, documents, manuals linked to a listing.
	 */
	private function get_listing_assets_sql( string $charset_collate ): string {
		$table = $this->table( 'listing_assets' );
		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			listing_id bigint(20) unsigned NOT NULL,
			asset_type varchar(30) NOT NULL DEFAULT 'image',
			file_url varchar(500) NOT NULL,
			file_name varchar(255) NOT NULL DEFAULT '',
			mime_type varchar(100) NOT NULL DEFAULT '',
			sort_order int(10) unsigned NOT NULL DEFAULT 0,
			is_primary tinyint(1) NOT NULL DEFAULT 0,
			uploaded_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY listing_id (listing_id),
			KEY asset_type (asset_type)
		) {$charset_collate};\n\n";
	}

	/**
	 * Verifications — serial numbers, ownership proof, certifications.
	 */
	private function get_verifications_sql( string $charset_collate ): string {
		$table = $this->table( 'verifications' );
		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			listing_id bigint(20) unsigned DEFAULT NULL,
			provider_id bigint(20) unsigned NOT NULL,
			verification_type varchar(50) NOT NULL,
			serial_number varchar(255) NOT NULL DEFAULT '',
			document_urls text DEFAULT NULL,
			notes text DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'not_submitted',
			reviewed_by bigint(20) unsigned DEFAULT NULL,
			reviewed_at datetime DEFAULT NULL,
			expires_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY listing_id (listing_id),
			KEY provider_id (provider_id),
			KEY status (status),
			KEY verification_type (verification_type)
		) {$charset_collate};\n\n";
	}

	/**
	 * Availability — calendar blocks for rental/service listings.
	 */
	private function get_availability_sql( string $charset_collate ): string {
		$table = $this->table( 'availability' );
		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			listing_id bigint(20) unsigned NOT NULL,
			date_start date NOT NULL,
			date_end date NOT NULL,
			block_type varchar(30) NOT NULL DEFAULT 'available',
			booking_id bigint(20) unsigned DEFAULT NULL,
			notes varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY listing_id (listing_id),
			KEY date_start (date_start),
			KEY date_end (date_end),
			KEY block_type (block_type),
			KEY booking_id (booking_id)
		) {$charset_collate};\n\n";
	}

	/**
	 * Bookings — covers equipment rentals, service bookings, and sale orders.
	 */
	private function get_bookings_sql( string $charset_collate ): string {
		$table = $this->table( 'bookings' );
		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_number varchar(30) NOT NULL,
			booking_type varchar(30) NOT NULL,
			listing_id bigint(20) unsigned NOT NULL,
			provider_id bigint(20) unsigned NOT NULL,
			customer_id bigint(20) unsigned NOT NULL,
			status varchar(50) NOT NULL DEFAULT 'inquiry',
			date_start datetime DEFAULT NULL,
			date_end datetime DEFAULT NULL,
			pickup_or_delivery varchar(20) NOT NULL DEFAULT 'pickup',
			fulfillment_mode varchar(30) NOT NULL DEFAULT 'pickup',
			delivery_address text DEFAULT NULL,
			delivery_fee decimal(10,2) NOT NULL DEFAULT 0.00,
			shipping_fee decimal(10,2) NOT NULL DEFAULT 0.00,
			travel_fee decimal(10,2) NOT NULL DEFAULT 0.00,
			subtotal decimal(10,2) NOT NULL DEFAULT 0.00,
			discount_amount decimal(10,2) NOT NULL DEFAULT 0.00,
			platform_fee decimal(10,2) NOT NULL DEFAULT 0.00,
			processing_fee decimal(10,2) NOT NULL DEFAULT 0.00,
			tax_amount decimal(10,2) NOT NULL DEFAULT 0.00,
			deposit_amount decimal(10,2) NOT NULL DEFAULT 0.00,
			total_amount decimal(10,2) NOT NULL DEFAULT 0.00,
			provider_payout decimal(10,2) NOT NULL DEFAULT 0.00,
			currency varchar(3) NOT NULL DEFAULT 'USD',
			cancellation_policy_id bigint(20) unsigned DEFAULT NULL,
			customer_notes text DEFAULT NULL,
			provider_notes text DEFAULT NULL,
			admin_notes text DEFAULT NULL,
			cancelled_by varchar(20) DEFAULT NULL,
			cancelled_at datetime DEFAULT NULL,
			cancellation_reason text DEFAULT NULL,
			checked_out_at datetime DEFAULT NULL,
			returned_at datetime DEFAULT NULL,
			completed_at datetime DEFAULT NULL,
			customer_contract_accepted tinyint(1) NOT NULL DEFAULT 0,
			platform_terms_accepted tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY booking_number (booking_number),
			KEY booking_type (booking_type),
			KEY listing_id (listing_id),
			KEY provider_id (provider_id),
			KEY customer_id (customer_id),
			KEY status (status),
			KEY date_start (date_start),
			KEY date_end (date_end)
		) {$charset_collate};\n\n";
	}

	/**
	 * Booking items — line items within a booking (for multi-item or package bookings).
	 */
	private function get_booking_items_sql( string $charset_collate ): string {
		$table = $this->table( 'booking_items' );
		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL,
			listing_id bigint(20) unsigned NOT NULL,
			item_type varchar(30) NOT NULL DEFAULT 'primary',
			description varchar(255) NOT NULL DEFAULT '',
			quantity int(10) unsigned NOT NULL DEFAULT 1,
			unit_price decimal(10,2) NOT NULL DEFAULT 0.00,
			total_price decimal(10,2) NOT NULL DEFAULT 0.00,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY listing_id (listing_id)
		) {$charset_collate};\n\n";
	}

	/**
	 * Transactions — payment events (charges, refunds, captures).
	 */
	private function get_transactions_sql( string $charset_collate ): string {
		$table = $this->table( 'transactions' );
		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL,
			transaction_type varchar(30) NOT NULL,
			gateway varchar(50) NOT NULL DEFAULT '',
			gateway_transaction_id varchar(255) NOT NULL DEFAULT '',
			amount decimal(10,2) NOT NULL DEFAULT 0.00,
			currency varchar(3) NOT NULL DEFAULT 'USD',
			status varchar(30) NOT NULL DEFAULT 'pending',
			metadata text DEFAULT NULL,
			error_message text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY transaction_type (transaction_type),
			KEY gateway_transaction_id (gateway_transaction_id(191)),
			KEY status (status)
		) {$charset_collate};\n\n";
	}

	/**
	 * Deposits — hold/capture/release tracking for equipment rentals.
	 */
	private function get_deposits_sql( string $charset_collate ): string {
		$table = $this->table( 'deposits' );
		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL,
			amount decimal(10,2) NOT NULL DEFAULT 0.00,
			captured_amount decimal(10,2) NOT NULL DEFAULT 0.00,
			released_amount decimal(10,2) NOT NULL DEFAULT 0.00,
			currency varchar(3) NOT NULL DEFAULT 'USD',
			status varchar(30) NOT NULL DEFAULT 'pending',
			gateway_auth_id varchar(255) NOT NULL DEFAULT '',
			auth_expires_at datetime DEFAULT NULL,
			captured_at datetime DEFAULT NULL,
			released_at datetime DEFAULT NULL,
			release_reason varchar(100) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY status (status)
		) {$charset_collate};\n\n";
	}

	/**
	 * Payouts — provider payout tracking.
	 */
	private function get_payouts_sql( string $charset_collate ): string {
		$table = $this->table( 'payouts' );
		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			provider_id bigint(20) unsigned NOT NULL,
			booking_id bigint(20) unsigned DEFAULT NULL,
			amount decimal(10,2) NOT NULL DEFAULT 0.00,
			currency varchar(3) NOT NULL DEFAULT 'USD',
			status varchar(30) NOT NULL DEFAULT 'not_ready',
			gateway varchar(50) NOT NULL DEFAULT '',
			gateway_payout_id varchar(255) NOT NULL DEFAULT '',
			hold_until datetime DEFAULT NULL,
			queued_at datetime DEFAULT NULL,
			sent_at datetime DEFAULT NULL,
			failed_at datetime DEFAULT NULL,
			failure_reason text DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY provider_id (provider_id),
			KEY booking_id (booking_id),
			KEY status (status),
			KEY hold_until (hold_until)
		) {$charset_collate};\n\n";
	}

	/**
	 * Condition reports — pre-handoff and post-return evidence for equipment.
	 */
	private function get_condition_reports_sql( string $charset_collate ): string {
		$table = $this->table( 'condition_reports' );
		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL,
			report_type varchar(30) NOT NULL,
			submitted_by bigint(20) unsigned NOT NULL,
			submitted_by_role varchar(20) NOT NULL DEFAULT 'provider',
			condition_grade varchar(30) NOT NULL DEFAULT '',
			notes text DEFAULT NULL,
			photo_urls text DEFAULT NULL,
			checklist_data longtext DEFAULT NULL,
			status varchar(30) NOT NULL DEFAULT 'not_started',
			mismatch_flagged tinyint(1) NOT NULL DEFAULT 0,
			mismatch_notes text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY report_type (report_type),
			KEY status (status)
		) {$charset_collate};\n\n";
	}

	/**
	 * Claims — damage claims, disputes, and resolution tracking.
	 */
	private function get_claims_sql( string $charset_collate ): string {
		$table = $this->table( 'claims' );
		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			claim_number varchar(30) NOT NULL,
			booking_id bigint(20) unsigned NOT NULL,
			filed_by bigint(20) unsigned NOT NULL,
			filed_by_role varchar(20) NOT NULL DEFAULT 'provider',
			claim_type varchar(50) NOT NULL DEFAULT 'damage',
			description text NOT NULL,
			amount_requested decimal(10,2) NOT NULL DEFAULT 0.00,
			amount_settled decimal(10,2) NOT NULL DEFAULT 0.00,
			status varchar(50) NOT NULL DEFAULT 'draft',
			customer_response_deadline datetime DEFAULT NULL,
			provider_rebuttal_deadline datetime DEFAULT NULL,
			resolution_notes text DEFAULT NULL,
			resolved_by bigint(20) unsigned DEFAULT NULL,
			resolved_at datetime DEFAULT NULL,
			auto_close_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY claim_number (claim_number),
			KEY booking_id (booking_id),
			KEY filed_by (filed_by),
			KEY status (status),
			KEY auto_close_at (auto_close_at)
		) {$charset_collate};\n\n";
	}

	/**
	 * Claim evidence — photos, documents, and notes attached to claims.
	 */
	private function get_claim_evidence_sql( string $charset_collate ): string {
		$table = $this->table( 'claim_evidence' );
		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			claim_id bigint(20) unsigned NOT NULL,
			submitted_by bigint(20) unsigned NOT NULL,
			evidence_type varchar(30) NOT NULL DEFAULT 'photo',
			file_url varchar(500) NOT NULL DEFAULT '',
			description text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY claim_id (claim_id),
			KEY submitted_by (submitted_by)
		) {$charset_collate};\n\n";
	}

	/**
	 * Reviews — mutual two-way reviews for all transaction types.
	 */
	private function get_reviews_sql( string $charset_collate ): string {
		$table = $this->table( 'reviews' );
		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL,
			listing_id bigint(20) unsigned NOT NULL,
			reviewer_id bigint(20) unsigned NOT NULL,
			reviewee_id bigint(20) unsigned NOT NULL,
			reviewer_role varchar(20) NOT NULL,
			booking_type varchar(30) NOT NULL,
			overall_rating tinyint(1) unsigned NOT NULL DEFAULT 0,
			rating_categories text DEFAULT NULL,
			title varchar(255) NOT NULL DEFAULT '',
			body text DEFAULT NULL,
			provider_response text DEFAULT NULL,
			provider_response_at datetime DEFAULT NULL,
			status varchar(30) NOT NULL DEFAULT 'pending_both',
			flagged tinyint(1) NOT NULL DEFAULT 0,
			flag_reason text DEFAULT NULL,
			published_at datetime DEFAULT NULL,
			deadline_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY listing_id (listing_id),
			KEY reviewer_id (reviewer_id),
			KEY reviewee_id (reviewee_id),
			KEY status (status),
			KEY overall_rating (overall_rating)
		) {$charset_collate};\n\n";
	}

	/**
	 * Notifications — in-app alerts, email queue, reminder tracking.
	 */
	private function get_notifications_sql( string $charset_collate ): string {
		$table = $this->table( 'notifications' );
		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			notification_type varchar(50) NOT NULL,
			channel varchar(20) NOT NULL DEFAULT 'dashboard',
			subject varchar(255) NOT NULL DEFAULT '',
			body text DEFAULT NULL,
			related_object_type varchar(50) NOT NULL DEFAULT '',
			related_object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			is_read tinyint(1) NOT NULL DEFAULT 0,
			sent_at datetime DEFAULT NULL,
			read_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY notification_type (notification_type),
			KEY channel (channel),
			KEY is_read (is_read),
			KEY related_object_type_id (related_object_type, related_object_id)
		) {$charset_collate};\n\n";
	}

	/**
	 * Audit log — immutable record of all status changes and admin actions.
	 */
	private function get_audit_log_sql( string $charset_collate ): string {
		$table = $this->table( 'audit_log' );
		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(100) NOT NULL,
			object_type varchar(50) NOT NULL,
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			old_value text DEFAULT NULL,
			new_value text DEFAULT NULL,
			context text DEFAULT NULL,
			ip_address varchar(45) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY action (action),
			KEY object_type_id (object_type, object_id),
			KEY created_at (created_at)
		) {$charset_collate};\n\n";
	}
}
