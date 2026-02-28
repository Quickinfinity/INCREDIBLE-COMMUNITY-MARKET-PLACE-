<?php
/**
 * Admin dashboard — marketplace overview with key metrics.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JQME\Analytics\Analytics;
use JQME\Analytics\Ranking;
use JQME\Settings\Settings;

$platform_name = Settings::get( 'global', 'platform_name' );
$overview      = Analytics::platform_overview();

// Revenue for current month.
$month_start = gmdate( 'Y-m-01 00:00:00' );
$month_end   = gmdate( 'Y-m-t 23:59:59' );
$month_rev   = Analytics::revenue_breakdown( $month_start, $month_end );

// Revenue trend.
$monthly_trend = Analytics::monthly_revenue( 12 );

// Top providers.
$top_providers = Analytics::top_providers( 5 );

// Claim stats.
$claim_stats = Analytics::claim_stats();
?>

<div class="wrap">
	<h1><?php echo esc_html( $platform_name ); ?> — <?php esc_html_e( 'Dashboard', 'jq-marketplace-engine' ); ?></h1>

	<!-- Overview cards -->
	<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:16px; margin:20px 0;">

		<div class="jqme-admin-card">
			<h3 style="margin:0 0 4px; font-size:13px; color:#666;"><?php esc_html_e( 'Total Bookings', 'jq-marketplace-engine' ); ?></h3>
			<div style="font-size:32px; font-weight:700;"><?php echo esc_html( number_format( $overview['total_bookings'] ) ); ?></div>
			<small style="color:#666;"><?php printf( esc_html__( '%d active', 'jq-marketplace-engine' ), $overview['active_bookings'] ); ?></small>
		</div>

		<div class="jqme-admin-card">
			<h3 style="margin:0 0 4px; font-size:13px; color:#666;"><?php esc_html_e( 'Providers', 'jq-marketplace-engine' ); ?></h3>
			<div style="font-size:32px; font-weight:700;"><?php echo esc_html( $overview['approved_providers'] ); ?></div>
			<small style="color:#666;"><?php printf( esc_html__( '%d total', 'jq-marketplace-engine' ), $overview['total_providers'] ); ?></small>
		</div>

		<div class="jqme-admin-card">
			<h3 style="margin:0 0 4px; font-size:13px; color:#666;"><?php esc_html_e( 'Listings', 'jq-marketplace-engine' ); ?></h3>
			<div style="font-size:32px; font-weight:700;"><?php echo esc_html( $overview['published_listings'] ); ?></div>
			<small style="color:#666;"><?php printf( esc_html__( '%d total', 'jq-marketplace-engine' ), $overview['total_listings'] ); ?></small>
		</div>

		<div class="jqme-admin-card">
			<h3 style="margin:0 0 4px; font-size:13px; color:#666;"><?php esc_html_e( 'This Month Revenue', 'jq-marketplace-engine' ); ?></h3>
			<div style="font-size:32px; font-weight:700;">$<?php echo esc_html( number_format( $month_rev['gross_revenue'], 2 ) ); ?></div>
			<small style="color:#666;"><?php printf( esc_html__( '%d bookings', 'jq-marketplace-engine' ), $month_rev['booking_count'] ); ?></small>
		</div>

		<div class="jqme-admin-card">
			<h3 style="margin:0 0 4px; font-size:13px; color:#666;"><?php esc_html_e( 'Platform Fees (MTD)', 'jq-marketplace-engine' ); ?></h3>
			<div style="font-size:32px; font-weight:700; color:#28a745;">$<?php echo esc_html( number_format( $month_rev['platform_fees'], 2 ) ); ?></div>
			<small style="color:#666;"><?php esc_html_e( 'Net marketplace revenue', 'jq-marketplace-engine' ); ?></small>
		</div>

		<div class="jqme-admin-card">
			<h3 style="margin:0 0 4px; font-size:13px; color:#666;"><?php esc_html_e( 'Reviews', 'jq-marketplace-engine' ); ?></h3>
			<div style="font-size:32px; font-weight:700;"><?php echo esc_html( $overview['published_reviews'] ); ?></div>
			<small style="color:#666;"><?php printf( esc_html__( '%d total submitted', 'jq-marketplace-engine' ), $overview['total_reviews'] ); ?></small>
		</div>

		<div class="jqme-admin-card" style="<?php echo $overview['open_claims'] > 0 ? 'border-left:3px solid #dc3545;' : ''; ?>">
			<h3 style="margin:0 0 4px; font-size:13px; color:#666;"><?php esc_html_e( 'Open Claims', 'jq-marketplace-engine' ); ?></h3>
			<div style="font-size:32px; font-weight:700; <?php echo $overview['open_claims'] > 0 ? 'color:#dc3545;' : ''; ?>">
				<?php echo esc_html( $overview['open_claims'] ); ?>
			</div>
			<?php if ( $overview['open_claims'] > 0 ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-claims' ) ); ?>" style="font-size:12px;">
					<?php esc_html_e( 'View claims', 'jq-marketplace-engine' ); ?> &rarr;
				</a>
			<?php endif; ?>
		</div>
	</div>

	<!-- Revenue trend -->
	<?php if ( ! empty( $monthly_trend ) ) : ?>
	<div class="jqme-admin-card" style="margin-bottom:20px;">
		<h3 style="margin-top:0;"><?php esc_html_e( 'Revenue Trend (Last 12 Months)', 'jq-marketplace-engine' ); ?></h3>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Month', 'jq-marketplace-engine' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Bookings', 'jq-marketplace-engine' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Revenue', 'jq-marketplace-engine' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Platform Fees', 'jq-marketplace-engine' ); ?></th>
					<th><?php esc_html_e( 'Bar', 'jq-marketplace-engine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$max_rev = 1;
				foreach ( $monthly_trend as $m ) {
					$max_rev = max( $max_rev, (float) $m->revenue );
				}
				foreach ( $monthly_trend as $m ) :
					$pct = round( ( (float) $m->revenue / $max_rev ) * 100 );
				?>
					<tr>
						<td><?php echo esc_html( $m->month ); ?></td>
						<td style="text-align:right;"><?php echo esc_html( $m->bookings ); ?></td>
						<td style="text-align:right;">$<?php echo esc_html( number_format( (float) $m->revenue, 2 ) ); ?></td>
						<td style="text-align:right;">$<?php echo esc_html( number_format( (float) $m->fees, 2 ) ); ?></td>
						<td>
							<div style="background:#0073aa; height:16px; width:<?php echo $pct; ?>%; border-radius:2px;"></div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>

	<!-- Two-column layout -->
	<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">

		<!-- Top Providers -->
		<div class="jqme-admin-card">
			<h3 style="margin-top:0;"><?php esc_html_e( 'Top Providers by Revenue', 'jq-marketplace-engine' ); ?></h3>
			<?php if ( empty( $top_providers ) ) : ?>
				<p><?php esc_html_e( 'No data yet.', 'jq-marketplace-engine' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Provider', 'jq-marketplace-engine' ); ?></th>
							<th style="text-align:right;"><?php esc_html_e( 'Bookings', 'jq-marketplace-engine' ); ?></th>
							<th style="text-align:right;"><?php esc_html_e( 'Revenue', 'jq-marketplace-engine' ); ?></th>
							<th style="text-align:center;"><?php esc_html_e( 'Score', 'jq-marketplace-engine' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $top_providers as $tp ) :
							$tier = Ranking::get_tier( (float) $tp->trust_score );
							$tier_colors = [ 'platinum' => '#8B5CF6', 'gold' => '#F59E0B', 'silver' => '#9CA3AF', 'bronze' => '#D97706' ];
						?>
							<tr>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-providers&id=' . $tp->id ) ); ?>">
										<?php echo esc_html( $tp->company_name ?: $tp->display_name ); ?>
									</a>
								</td>
								<td style="text-align:right;"><?php echo esc_html( $tp->total_bookings ); ?></td>
								<td style="text-align:right;">$<?php echo esc_html( number_format( (float) $tp->total_revenue, 2 ) ); ?></td>
								<td style="text-align:center;">
									<span style="color:<?php echo $tier_colors[ $tier ] ?? '#666'; ?>; font-weight:600;">
										<?php echo esc_html( number_format( (float) $tp->trust_score, 2 ) ); ?>
									</span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<!-- Claims overview -->
		<div class="jqme-admin-card">
			<h3 style="margin-top:0;"><?php esc_html_e( 'Claims Overview', 'jq-marketplace-engine' ); ?></h3>
			<table class="widefat striped">
				<tbody>
					<tr><td><?php esc_html_e( 'Total Filed', 'jq-marketplace-engine' ); ?></td><td style="text-align:right; font-weight:600;"><?php echo esc_html( $claim_stats['total'] ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Open', 'jq-marketplace-engine' ); ?></td><td style="text-align:right; font-weight:600;"><?php echo esc_html( $claim_stats['submitted'] ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Resolved', 'jq-marketplace-engine' ); ?></td><td style="text-align:right; font-weight:600;"><?php echo esc_html( $claim_stats['resolved'] ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Total Requested', 'jq-marketplace-engine' ); ?></td><td style="text-align:right; font-weight:600;">$<?php echo esc_html( number_format( $claim_stats['total_requested'], 2 ) ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Total Settled', 'jq-marketplace-engine' ); ?></td><td style="text-align:right; font-weight:600;">$<?php echo esc_html( number_format( $claim_stats['total_settled'], 2 ) ); ?></td></tr>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Quick actions -->
	<div class="jqme-admin-card">
		<h3 style="margin-top:0;"><?php esc_html_e( 'Quick Actions', 'jq-marketplace-engine' ); ?></h3>
		<div style="display:flex; gap:10px; flex-wrap:wrap;">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-providers' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Manage Providers', 'jq-marketplace-engine' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-listings' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Manage Listings', 'jq-marketplace-engine' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-bookings' ) ); ?>" class="button button-primary"><?php esc_html_e( 'View Bookings', 'jq-marketplace-engine' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-verifications' ) ); ?>" class="button"><?php esc_html_e( 'Verification Queue', 'jq-marketplace-engine' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-reviews' ) ); ?>" class="button"><?php esc_html_e( 'Moderate Reviews', 'jq-marketplace-engine' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-settings' ) ); ?>" class="button"><?php esc_html_e( 'Settings', 'jq-marketplace-engine' ); ?></a>
		</div>
	</div>

	<hr>
	<p class="description"><?php echo esc_html( Settings::get( 'global', 'platform_facilitator_disclaimer' ) ); ?></p>
</div>

<style>
.jqme-admin-card { background:#fff; border:1px solid #e0e0e0; border-radius:4px; padding:16px 20px; }
</style>
