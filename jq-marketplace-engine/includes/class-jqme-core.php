<?php
/**
 * Core plugin orchestrator.
 *
 * Singleton that boots all modules and manages hook registration.
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Core {

	private static ?Core $instance = null;

	/** @var array<string, object> Loaded module instances. */
	private array $modules = [];

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Boot all plugin modules and register hooks.
	 */
	public function run(): void {
		$this->load_textdomain();
		$this->check_db_version();
		$this->load_modules();
		$this->register_hooks();
	}

	/**
	 * Load plugin textdomain for i18n.
	 */
	private function load_textdomain(): void {
		load_plugin_textdomain(
			'jq-marketplace-engine',
			false,
			dirname( JQME_PLUGIN_BASENAME ) . '/languages/'
		);
	}

	/**
	 * Check if DB schema needs upgrading.
	 */
	private function check_db_version(): void {
		$installed_version = get_option( 'jqme_db_version', '0' );
		if ( version_compare( $installed_version, JQME_DB_VERSION, '<' ) ) {
			$schema = new Database\Schema();
			$schema->install();
			update_option( 'jqme_db_version', JQME_DB_VERSION );
		}
	}

	/**
	 * Instantiate and store module objects.
	 */
	private function load_modules(): void {
		$this->modules['roles']    = new Roles\Roles();
		$this->modules['settings'] = new Settings\Settings();

		// Provider and listing handlers register shortcodes and form actions.
		$this->modules['provider_handler'] = new Providers\ProviderHandler();
		$this->modules['listing_handler']  = new Listings\ListingHandler();

		// Booking handler registers booking shortcodes and form actions.
		$this->modules['booking_handler'] = new Bookings\BookingHandler();

		// Notification engine listens to booking/provider/listing lifecycle hooks.
		$this->modules['notifications'] = new Notifications\NotificationEngine();

		// Claims and condition reports handler.
		$this->modules['claim_handler'] = new Claims\ClaimHandler();

		// Review handler — auto-creates reviews on booking completion.
		$this->modules['review_handler'] = new Reviews\ReviewHandler();

		// Cron task handler — scheduled maintenance and automation.
		$this->modules['cron'] = new Cron();

		if ( is_admin() ) {
			$this->modules['admin'] = new Admin\Admin();
		}
	}

	/**
	 * Register global hooks shared across modules.
	 */
	private function register_hooks(): void {
		// Modules register their own hooks in their constructors or via init.
		add_action( 'init', [ $this, 'on_init' ] );
	}

	/**
	 * Runs on WordPress init action.
	 */
	public function on_init(): void {
		// Future: register CPTs, shortcodes, rewrite rules.
	}

	/**
	 * Get a loaded module by key.
	 */
	public function module( string $key ): ?object {
		return $this->modules[ $key ] ?? null;
	}

	/**
	 * Helper: get global table name with WP prefix.
	 */
	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . JQME_TABLE_PREFIX . $name;
	}
}
