<?php
/**
 * Admin reports page — revenue breakdown, booking stats, provider scorecards.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JQME\Analytics\Analytics;
use JQME\Analytics\Ranking;

$current_tab = sanitize_text_field( $_GET['tab'] ?? 'revenue' );
$tabs = [
	'revenue'   => __( 'Revenue', 'jq-marketplace-engine' ),
	'bookings'  => __( 'Bookings', 'jq-marketplace-engine' ),
	'providers' => __( 'Provider Scorecards', 'jq-marketplace-engine' ),
	'listings'  => __( 'Top Listings', 'jq-marketplace-engine' ),
];

// Date range.
$from = sanitize_text_field( $_GET['from'] ?? gmdate( 'Y-m-01' ) );
$to   = sanitize_text_field( $_GET['to'] ?? gmdate( 'Y-m-d' ) );
$from_dt = $from . ' 00:00:00';
$to_dt   = $to . ' 23:59:59';
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Reports', 'jq-marketplace-engine' ); ?></h1>

	<!-- Tabs -->
	<nav class="nav-tab-wrapper" style="margin-bottom:20px;">
		<?php foreach ( $tabs as $tk => $tl ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'tab', $tk ) ); ?>"
			   class="nav-tab <?php echo $current_tab === $tk ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tl ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<!-- Date range picker -->
	<form method="get" style="margin-bottom:20px; display:flex; gap:8px; align-items:center;">
		<input type="hidden" name="page" value="jqme-reports">
		<input type="hidden" name="tab" value="<?php echo esc_attr( $current_tab ); ?>">
		<label><?php esc_html_e( 'From:', 'jq-marketplace-engine' ); ?></label>
		<input type="date" name="from" value="<?php echo esc_attr( $from ); ?>">
		<label><?php esc_html_e( 'To:', 'jq-marketplace-engine' ); ?></label>
		<input type="date" name="to" value="<?php echo esc_attr( $to ); ?>">
		<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'jq-marketplace-engine' ); ?></button>
	</form>

	<?php if ( 'revenue' === $current_tab ) : ?>
		<?php
		$rev      = Analytics::revenue_breakdown( $from_dt, $to_dt );
		$by_type  = Analytics::revenue_by_type( $from_dt, $to_dt );
		?>

		<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:16px; margin-bottom:20px;">
			<div class="jqme-admin-card">
				<h4 style="margin:0 0 4px; font-size:12px; color:#666;"><?php esc_html_e( 'Gross Revenue', 'jq-marketplace-engine' ); ?></h4>
				<div style="font-size:28px; font-weight:700;">$<?php echo esc_html( number_format( $rev['gross_revenue'], 2 ) ); ?></div>
			</div>
			<div class="jqme-admin-card">
				<h4 style="margin:0 0 4px; font-size:12px; color:#666;"><?php esc_html_e( 'Platform Fees', 'jq-marketplace-engine' ); ?></h4>
				<div style="font-size:28px; font-weight:700; color:#28a745;">$<?php echo esc_html( number_format( $rev['platform_fees'], 2 ) ); ?></div>
			</div>
			<div class="jqme-admin-card">
				<h4 style="margin:0 0 4px; font-size:12px; color:#666;"><?php esc_html_e( 'Processing Fees', 'jq-marketplace-engine' ); ?></h4>
				<div style="font-size:28px; font-weight:700;">$<?php echo esc_html( number_format( $rev['processing_fees'], 2 ) ); ?></div>
			</div>
			<div class="jqme-admin-card">
				<h4 style="margin:0 0 4px; font-size:12px; color:#666;"><?php esc_html_e( 'Provider Payouts', 'jq-marketplace-engine' ); ?></h4>
				<div style="font-size:28px; font-weight:700;">$<?php echo esc_html( number_format( $rev['provider_payouts'], 2 ) ); ?></div>
			</div>
			<div class="jqme-admin-card">
				<h4 style="margin:0 0 4px; font-size:12px; color:#666;"><?php esc_html_e( 'Completed Bookings', 'jq-marketplace-engine' ); ?></h4>
				<div style="font-size:28px; font-weight:700;"><?php echo esc_html( $rev['booking_count'] ); ?></div>
			</div>
		</div>

		<?php if ( ! empty( $by_type ) ) : ?>
			<h3><?php esc_html_e( 'Revenue by Booking Type', 'jq-marketplace-engine' ); ?></h3>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Type', 'jq-marketplace-engine' ); ?></th>
						<th style="text-align:right;"><?php esc_html_e( 'Bookings', 'jq-marketplace-engine' ); ?></th>
						<th style="text-align:right;"><?php esc_html_e( 'Revenue', 'jq-marketplace-engine' ); ?></th>
						<th style="text-align:right;"><?php esc_html_e( 'Platform Fees', 'jq-marketplace-engine' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php $types = \JQME\StatusEnums::listing_types(); ?>
					<?php foreach ( $by_type as $bt ) : ?>
						<tr>
							<td><?php echo esc_html( $types[ $bt->booking_type ] ?? $bt->booking_type ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( $bt->count ); ?></td>
							<td style="text-align:right;">$<?php echo esc_html( number_format( (float) $bt->revenue, 2 ) ); ?></td>
							<td style="text-align:right;">$<?php echo esc_html( number_format( (float) $bt->fees, 2 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

	<?php elseif ( 'bookings' === $current_tab ) : ?>
		<?php $distribution = Analytics::booking_status_distribution(); ?>

		<h3><?php esc_html_e( 'Booking Status Distribution', 'jq-marketplace-engine' ); ?></h3>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Status', 'jq-marketplace-engine' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Count', 'jq-marketplace-engine' ); ?></th>
					<th><?php esc_html_e( 'Bar', 'jq-marketplace-engine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$max_count = 1;
				foreach ( $distribution as $d ) {
					$max_count = max( $max_count, (int) $d->count );
				}
				foreach ( $distribution as $d ) :
					$pct = round( ( (int) $d->count / $max_count ) * 100 );
				?>
					<tr>
						<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $d->status ) ) ); ?></td>
						<td style="text-align:right;"><?php echo esc_html( $d->count ); ?></td>
						<td><div style="background:#0073aa; height:16px; width:<?php echo $pct; ?>%; border-radius:2px;"></div></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

	<?php elseif ( 'providers' === $current_tab ) : ?>
		<?php $top = Analytics::top_providers( 25 ); ?>

		<h3><?php esc_html_e( 'Provider Scorecards', 'jq-marketplace-engine' ); ?></h3>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Provider', 'jq-marketplace-engine' ); ?></th>
					<th><?php esc_html_e( 'Status', 'jq-marketplace-engine' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Bookings', 'jq-marketplace-engine' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Revenue', 'jq-marketplace-engine' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Fees Earned', 'jq-marketplace-engine' ); ?></th>
					<th style="text-align:center;"><?php esc_html_e( 'Trust Score', 'jq-marketplace-engine' ); ?></th>
					<th style="text-align:center;"><?php esc_html_e( 'Tier', 'jq-marketplace-engine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $top as $tp ) :
					$tier = Ranking::get_tier( (float) $tp->trust_score );
					$tier_labels = Ranking::tier_labels();
					$tier_colors = [ 'platinum' => '#8B5CF6', 'gold' => '#F59E0B', 'silver' => '#9CA3AF', 'bronze' => '#D97706' ];
				?>
					<tr>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-providers&id=' . $tp->id ) ); ?>">
								<?php echo esc_html( $tp->company_name ?: $tp->display_name ); ?>
							</a>
						</td>
						<td><span class="jqme-badge"><?php echo esc_html( ucwords( str_replace( '_', ' ', $tp->status ) ) ); ?></span></td>
						<td style="text-align:right;"><?php echo esc_html( $tp->total_bookings ); ?></td>
						<td style="text-align:right;">$<?php echo esc_html( number_format( (float) $tp->total_revenue, 2 ) ); ?></td>
						<td style="text-align:right;">$<?php echo esc_html( number_format( (float) $tp->total_fees, 2 ) ); ?></td>
						<td style="text-align:center; font-weight:600;"><?php echo esc_html( number_format( (float) $tp->trust_score, 2 ) ); ?></td>
						<td style="text-align:center;">
							<span style="color:<?php echo $tier_colors[ $tier ] ?? '#666'; ?>; font-weight:600;">
								<?php echo esc_html( $tier_labels[ $tier ] ?? $tier ); ?>
							</span>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

	<?php elseif ( 'listings' === $current_tab ) : ?>
		<?php $top_listings = Analytics::top_listings( 25 ); ?>

		<h3><?php esc_html_e( 'Top Listings', 'jq-marketplace-engine' ); ?></h3>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Listing', 'jq-marketplace-engine' ); ?></th>
					<th><?php esc_html_e( 'Type', 'jq-marketplace-engine' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Bookings', 'jq-marketplace-engine' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Revenue', 'jq-marketplace-engine' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Views', 'jq-marketplace-engine' ); ?></th>
					<th style="text-align:center;"><?php esc_html_e( 'Rating', 'jq-marketplace-engine' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Reviews', 'jq-marketplace-engine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$types = \JQME\StatusEnums::listing_types();
				foreach ( $top_listings as $tl ) : ?>
					<tr>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-listings&id=' . $tl->id ) ); ?>">
								<?php echo esc_html( $tl->title ); ?>
							</a>
						</td>
						<td><small><?php echo esc_html( $types[ $tl->listing_type ] ?? $tl->listing_type ); ?></small></td>
						<td style="text-align:right;"><?php echo esc_html( $tl->booking_count ); ?></td>
						<td style="text-align:right;">$<?php echo esc_html( number_format( (float) $tl->total_revenue, 2 ) ); ?></td>
						<td style="text-align:right;"><?php echo esc_html( number_format( (int) $tl->view_count ) ); ?></td>
						<td style="text-align:center; color:#f0ad4e;"><?php echo esc_html( number_format( (float) $tl->average_rating, 1 ) ); ?></td>
						<td style="text-align:right;"><?php echo esc_html( $tl->review_count ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<style>
.jqme-admin-card { background:#fff; border:1px solid #e0e0e0; border-radius:4px; padding:16px 20px; }
</style>
