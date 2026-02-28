<?php
/**
 * Template: Provider dashboard.
 *
 * Used by [jqme_provider_dashboard] shortcode.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JQME\Providers\Provider;
use JQME\Listings\Listing;
use JQME\StatusEnums;

$provider = Provider::get_by_user( get_current_user_id() );
if ( ! $provider ) {
	return;
}

$listings       = Listing::query( [ 'provider_id' => $provider->id, 'limit' => 10 ] );
$listing_count  = Listing::count_by_provider( $provider->id );
$published      = Listing::count( [ 'provider_id' => $provider->id, 'status' => StatusEnums::LISTING_PUBLISHED ] );
$drafts         = Listing::count( [ 'provider_id' => $provider->id, 'status' => StatusEnums::LISTING_DRAFT ] );
$pending        = Listing::count( [ 'provider_id' => $provider->id, 'status' => StatusEnums::LISTING_SUBMITTED ] );

$allowed_types = json_decode( $provider->allowed_listing_types ?? '[]', true ) ?: [];
$statuses      = StatusEnums::listing_statuses();
$types         = StatusEnums::listing_types();
?>

<div class="jqme-dashboard">
	<h2><?php printf( esc_html__( 'Welcome, %s', 'jq-marketplace-engine' ), esc_html( $provider->contact_name ?: $provider->company_name ) ); ?></h2>

	<div class="jqme-dashboard-stats" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:12px; margin:20px 0;">
		<div class="jqme-stat">
			<strong><?php echo esc_html( $published ); ?></strong>
			<span><?php esc_html_e( 'Published Listings', 'jq-marketplace-engine' ); ?></span>
		</div>
		<div class="jqme-stat">
			<strong><?php echo esc_html( $drafts ); ?></strong>
			<span><?php esc_html_e( 'Drafts', 'jq-marketplace-engine' ); ?></span>
		</div>
		<div class="jqme-stat">
			<strong><?php echo esc_html( $pending ); ?></strong>
			<span><?php esc_html_e( 'Pending Review', 'jq-marketplace-engine' ); ?></span>
		</div>
		<div class="jqme-stat">
			<strong><?php echo esc_html( $listing_count ); ?></strong>
			<span><?php esc_html_e( 'Total Listings', 'jq-marketplace-engine' ); ?></span>
		</div>
	</div>

	<h3><?php esc_html_e( 'Your Listings', 'jq-marketplace-engine' ); ?></h3>

	<?php if ( empty( $listings ) ) : ?>
		<p><?php esc_html_e( 'You have no listings yet.', 'jq-marketplace-engine' ); ?></p>
	<?php else : ?>
		<table class="jqme-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Title', 'jq-marketplace-engine' ); ?></th>
					<th><?php esc_html_e( 'Type', 'jq-marketplace-engine' ); ?></th>
					<th><?php esc_html_e( 'Status', 'jq-marketplace-engine' ); ?></th>
					<th><?php esc_html_e( 'Created', 'jq-marketplace-engine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $listings as $l ) : ?>
					<tr>
						<td><?php echo esc_html( $l->title ); ?></td>
						<td><?php echo esc_html( $types[ $l->listing_type ] ?? $l->listing_type ); ?></td>
						<td><?php echo esc_html( $statuses[ $l->status ] ?? $l->status ); ?></td>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $l->created_at ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h3><?php esc_html_e( 'Quick Links', 'jq-marketplace-engine' ); ?></h3>
	<ul>
		<li><a href="#"><?php esc_html_e( 'Create New Listing', 'jq-marketplace-engine' ); ?></a></li>
		<li><a href="#"><?php esc_html_e( 'Edit Profile', 'jq-marketplace-engine' ); ?></a></li>
		<li><a href="#"><?php esc_html_e( 'Booking Requests', 'jq-marketplace-engine' ); ?></a></li>
		<li><a href="#"><?php esc_html_e( 'Payout Settings', 'jq-marketplace-engine' ); ?></a></li>
	</ul>
</div>
