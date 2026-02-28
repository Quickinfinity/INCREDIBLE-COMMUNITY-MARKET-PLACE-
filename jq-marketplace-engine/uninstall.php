<?php
/**
 * Fired when the plugin is deleted via the WordPress admin.
 *
 * Drops all custom tables, removes options, and removes custom roles.
 * This is DESTRUCTIVE — only runs on full plugin deletion, not deactivation.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$prefix = $wpdb->prefix . 'jq_marketplace_';

// All custom tables — order matters for foreign keys.
$tables = [
	'audit_log',
	'notifications',
	'claim_evidence',
	'claims',
	'condition_reports',
	'reviews',
	'payouts',
	'deposits',
	'transactions',
	'booking_items',
	'bookings',
	'availability',
	'verifications',
	'listing_assets',
	'listing_meta',
	'listings',
	'policy_profiles',
	'provider_meta',
	'providers',
];

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
}

// Remove all plugin options.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'jqme_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL

// Remove custom roles.
remove_role( 'jqme_provider' );
remove_role( 'jqme_customer' );

// Remove custom capabilities from administrator.
$admin_role = get_role( 'administrator' );
if ( $admin_role ) {
	$caps = [
		'jqme_manage_marketplace',
		'jqme_manage_providers',
		'jqme_manage_listings',
		'jqme_manage_bookings',
		'jqme_manage_payments',
		'jqme_manage_claims',
		'jqme_manage_reviews',
		'jqme_manage_settings',
		'jqme_view_reports',
		'jqme_manage_policies',
	];
	foreach ( $caps as $cap ) {
		$admin_role->remove_cap( $cap );
	}
}
