<?php
/**
 * Plugin Name:       JQ Marketplace Engine
 * Plugin URI:        https://joequick.com
 * Description:       Gated marketplace for equipment rentals, equipment sales, and service bookings. Platform facilitator model.
 * Version:           0.1.0
 * Author:            Joe Quick / Incredible Products, LLC
 * Author URI:        https://joequick.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       jq-marketplace-engine
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'JQME_VERSION', '0.1.0' );
define( 'JQME_PLUGIN_FILE', __FILE__ );
define( 'JQME_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JQME_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'JQME_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'JQME_DB_VERSION', '1.0.0' );
define( 'JQME_TABLE_PREFIX', 'jq_marketplace_' );

// Minimum requirements.
define( 'JQME_MIN_WP_VERSION', '6.0' );
define( 'JQME_MIN_PHP_VERSION', '8.0' );
define( 'JQME_MIN_WC_VERSION', '7.0' );

/**
 * Check minimum requirements before loading.
 */
function jqme_check_requirements(): bool {
	$errors = [];

	if ( version_compare( PHP_VERSION, JQME_MIN_PHP_VERSION, '<' ) ) {
		$errors[] = sprintf(
			'JQ Marketplace Engine requires PHP %s or higher. You are running PHP %s.',
			JQME_MIN_PHP_VERSION,
			PHP_VERSION
		);
	}

	global $wp_version;
	if ( version_compare( $wp_version, JQME_MIN_WP_VERSION, '<' ) ) {
		$errors[] = sprintf(
			'JQ Marketplace Engine requires WordPress %s or higher.',
			JQME_MIN_WP_VERSION
		);
	}

	if ( ! empty( $errors ) ) {
		add_action( 'admin_notices', function () use ( $errors ) {
			foreach ( $errors as $error ) {
				printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $error ) );
			}
		} );
		return false;
	}

	return true;
}

/**
 * Autoloader for plugin classes.
 *
 * Maps JQME namespace to includes/ directory.
 * Class JQME\Database\Schema => includes/database/class-jqme-schema.php
 */
spl_autoload_register( function ( string $class ) {
	$prefix    = 'JQME\\';
	$base_dir  = JQME_PLUGIN_DIR . 'includes/';

	if ( strpos( $class, $prefix ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, strlen( $prefix ) );
	$parts          = explode( '\\', $relative_class );
	$class_name     = array_pop( $parts );

	// Convert namespace to directory path (lowercase).
	$sub_dir = '';
	if ( ! empty( $parts ) ) {
		$sub_dir = strtolower( implode( '/', $parts ) ) . '/';
	}

	// Convert class name: StatusEnums => class-jqme-status-enums.php
	$file_name = 'class-jqme-' . strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', $class_name ) ) . '.php';
	$file      = $base_dir . $sub_dir . $file_name;

	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

// Activation and deactivation hooks (must be registered before init).
register_activation_hook( __FILE__, [ 'JQME\\Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'JQME\\Deactivator', 'deactivate' ] );

/**
 * Boot the plugin after WordPress has loaded.
 */
add_action( 'plugins_loaded', function () {
	if ( ! jqme_check_requirements() ) {
		return;
	}

	$plugin = JQME\Core::instance();
	$plugin->run();
}, 10 );
