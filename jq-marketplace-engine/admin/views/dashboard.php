<?php
/**
 * Admin dashboard — marketplace overview.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JQME\Settings\Settings;

$platform_name = Settings::get( 'global', 'platform_name' );
?>

<div class="wrap">
	<h1><?php echo esc_html( $platform_name ); ?> — <?php esc_html_e( 'Dashboard', 'jq-marketplace-engine' ); ?></h1>

	<div class="jqme-dashboard-grid">
		<div class="jqme-stat-card">
			<h3><?php esc_html_e( 'Active Providers', 'jq-marketplace-engine' ); ?></h3>
			<div class="jqme-stat-value">—</div>
		</div>
		<div class="jqme-stat-card">
			<h3><?php esc_html_e( 'Published Listings', 'jq-marketplace-engine' ); ?></h3>
			<div class="jqme-stat-value">—</div>
		</div>
		<div class="jqme-stat-card">
			<h3><?php esc_html_e( 'Active Bookings', 'jq-marketplace-engine' ); ?></h3>
			<div class="jqme-stat-value">—</div>
		</div>
		<div class="jqme-stat-card">
			<h3><?php esc_html_e( 'Pending Applications', 'jq-marketplace-engine' ); ?></h3>
			<div class="jqme-stat-value">—</div>
		</div>
		<div class="jqme-stat-card">
			<h3><?php esc_html_e( 'Open Claims', 'jq-marketplace-engine' ); ?></h3>
			<div class="jqme-stat-value">—</div>
		</div>
		<div class="jqme-stat-card">
			<h3><?php esc_html_e( 'Pending Payouts', 'jq-marketplace-engine' ); ?></h3>
			<div class="jqme-stat-value">—</div>
		</div>
	</div>

	<h2><?php esc_html_e( 'Quick Actions', 'jq-marketplace-engine' ); ?></h2>
	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-providers' ) ); ?>" class="button"><?php esc_html_e( 'Review Provider Applications', 'jq-marketplace-engine' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-verifications' ) ); ?>" class="button"><?php esc_html_e( 'Verification Queue', 'jq-marketplace-engine' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-listings' ) ); ?>" class="button"><?php esc_html_e( 'Moderate Listings', 'jq-marketplace-engine' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-settings' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Settings', 'jq-marketplace-engine' ); ?></a>
	</p>

	<hr>
	<p class="description">
		<?php echo esc_html( Settings::get( 'global', 'platform_facilitator_disclaimer' ) ); ?>
	</p>
</div>
