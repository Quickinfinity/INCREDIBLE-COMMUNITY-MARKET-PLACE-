<?php
/**
 * Fired during plugin deactivation.
 *
 * Cleans up scheduled events. Does NOT drop tables or remove roles
 * (that's handled by uninstall.php if the user deletes the plugin).
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Deactivator {

	/**
	 * Run deactivation tasks.
	 */
	public static function deactivate(): void {
		// Clear scheduled cron events.
		wp_clear_scheduled_hook( 'jqme_daily_maintenance' );
		wp_clear_scheduled_hook( 'jqme_hourly_tasks' );

		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}
