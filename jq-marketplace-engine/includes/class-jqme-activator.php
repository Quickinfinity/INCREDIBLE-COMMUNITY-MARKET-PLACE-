<?php
/**
 * Fired during plugin activation.
 *
 * Creates database tables, registers roles, and sets default options.
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Activator {

	/**
	 * Run activation tasks.
	 */
	public static function activate(): void {
		// 1. Create/update database tables.
		$schema = new Database\Schema();
		$schema->install();
		update_option( 'jqme_db_version', JQME_DB_VERSION );

		// 2. Register custom roles and capabilities.
		$roles = new Roles\Roles();
		$roles->create_roles();

		// 3. Set default settings if not already present.
		$settings = new Settings\Settings();
		$settings->set_defaults();

		// 4. Schedule cron events.
		self::schedule_events();

		// 5. Flush rewrite rules (for any CPTs registered later).
		flush_rewrite_rules();

		// 6. Record activation.
		update_option( 'jqme_activated_at', current_time( 'mysql' ) );
	}

	/**
	 * Schedule recurring cron events.
	 */
	private static function schedule_events(): void {
		if ( ! wp_next_scheduled( 'jqme_daily_maintenance' ) ) {
			wp_schedule_event( time(), 'daily', 'jqme_daily_maintenance' );
		}
		if ( ! wp_next_scheduled( 'jqme_hourly_tasks' ) ) {
			wp_schedule_event( time(), 'hourly', 'jqme_hourly_tasks' );
		}
	}
}
