<?php
/**
 * Admin listings moderation page.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JQME\Listings\Listing;
use JQME\StatusEnums;

$status_filter = sanitize_text_field( $_GET['status'] ?? '' );
$type_filter   = sanitize_text_field( $_GET['listing_type'] ?? '' );
$search        = sanitize_text_field( $_GET['s'] ?? '' );
$paged         = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page      = 20;
$offset        = ( $paged - 1 ) * $per_page;

$statuses    = StatusEnums::listing_statuses();
$types       = StatusEnums::listing_types();

$listings = Listing::query( [
	'status'       => $status_filter,
	'listing_type' => $type_filter,
	'search'       => $search,
	'limit'        => $per_page,
	'offset'       => $offset,
] );

// Status counts.
$status_counts = [];
foreach ( array_keys( $statuses ) as $s ) {
	$status_counts[ $s ] = Listing::count( [ 'status' => $s ] );
}
$total = array_sum( $status_counts );

$notice = sanitize_text_field( $_GET['jqme_notice'] ?? '' );
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Listing Moderation', 'jq-marketplace-engine' ); ?></h1>

	<?php if ( $notice ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( ucwords( str_replace( '_', ' ', $notice ) ) ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Status filter tabs -->
	<ul class="subsubsub">
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-listings' ) ); ?>"
			   class="<?php echo empty( $status_filter ) ? 'current' : ''; ?>">
				<?php printf( '%s (%d)', esc_html__( 'All', 'jq-marketplace-engine' ), $total ); ?>
			</a> |
		</li>
		<?php foreach ( $statuses as $key => $label ) : ?>
			<?php if ( ( $status_counts[ $key ] ?? 0 ) > 0 ) : ?>
				<li>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-listings&status=' . $key ) ); ?>"
					   class="<?php echo $status_filter === $key ? 'current' : ''; ?>">
						<?php printf( '%s (%d)', esc_html( $label ), $status_counts[ $key ] ); ?>
					</a> |
				</li>
			<?php endif; ?>
		<?php endforeach; ?>
	</ul>

	<!-- Filters -->
	<form method="get" action="">
		<input type="hidden" name="page" value="jqme-listings">
		<p class="search-box" style="display:flex; gap:8px; align-items:center;">
			<select name="listing_type">
				<option value=""><?php esc_html_e( 'All Types', 'jq-marketplace-engine' ); ?></option>
				<?php foreach ( $types as $val => $label ) : ?>
					<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $type_filter, $val ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search listings...', 'jq-marketplace-engine' ); ?>">
			<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'jq-marketplace-engine' ); ?>">
		</p>
	</form>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Title', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Type', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Provider', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Price', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Status', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Created', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'jq-marketplace-engine' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $listings ) ) : ?>
				<tr><td colspan="7"><?php esc_html_e( 'No listings found.', 'jq-marketplace-engine' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $listings as $l ) : ?>
					<?php
					$price_display = match ( $l->listing_type ) {
						StatusEnums::TYPE_EQUIPMENT_RENTAL => $l->day_rate ? '$' . number_format( (float) $l->day_rate, 2 ) . '/day' : '—',
						StatusEnums::TYPE_EQUIPMENT_SALE   => $l->asking_price ? '$' . number_format( (float) $l->asking_price, 2 ) : '—',
						StatusEnums::TYPE_SERVICE_BOOKING  => $l->hourly_rate ? '$' . number_format( (float) $l->hourly_rate, 2 ) . '/hr' : '—',
						default => '—',
					};
					$badge_class = match ( $l->status ) {
						StatusEnums::LISTING_PUBLISHED  => 'jqme-badge--success',
						StatusEnums::LISTING_SUBMITTED,
						StatusEnums::LISTING_UNDER_REVIEW => 'jqme-badge--warning',
						StatusEnums::LISTING_SUSPENDED,
						StatusEnums::LISTING_FLAGGED     => 'jqme-badge--danger',
						StatusEnums::LISTING_DRAFT       => 'jqme-badge--muted',
						default                          => 'jqme-badge--info',
					};
					?>
					<tr>
						<td><strong><?php echo esc_html( $l->title ); ?></strong></td>
						<td><?php echo esc_html( $types[ $l->listing_type ] ?? $l->listing_type ); ?></td>
						<td><?php echo esc_html( $l->provider_name ?? '—' ); ?></td>
						<td><?php echo esc_html( $price_display ); ?></td>
						<td><span class="jqme-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $statuses[ $l->status ] ?? $l->status ); ?></span></td>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $l->created_at ) ) ); ?></td>
						<td>
							<?php if ( in_array( $l->status, [ StatusEnums::LISTING_SUBMITTED, StatusEnums::LISTING_UNDER_REVIEW, StatusEnums::LISTING_VERIFIED ], true ) ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<?php wp_nonce_field( 'jqme_listing_approve_' . $l->id ); ?>
									<input type="hidden" name="action" value="jqme_listing_approve">
									<input type="hidden" name="listing_id" value="<?php echo esc_attr( $l->id ); ?>">
									<button type="submit" class="button button-primary button-small"><?php esc_html_e( 'Approve', 'jq-marketplace-engine' ); ?></button>
								</form>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<?php wp_nonce_field( 'jqme_listing_request_changes_' . $l->id ); ?>
									<input type="hidden" name="action" value="jqme_listing_request_changes">
									<input type="hidden" name="listing_id" value="<?php echo esc_attr( $l->id ); ?>">
									<button type="submit" class="button button-small"><?php esc_html_e( 'Request Changes', 'jq-marketplace-engine' ); ?></button>
								</form>
							<?php elseif ( StatusEnums::LISTING_PUBLISHED === $l->status ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<?php wp_nonce_field( 'jqme_listing_suspend_' . $l->id ); ?>
									<input type="hidden" name="action" value="jqme_listing_suspend">
									<input type="hidden" name="listing_id" value="<?php echo esc_attr( $l->id ); ?>">
									<button type="submit" class="button button-small"><?php esc_html_e( 'Suspend', 'jq-marketplace-engine' ); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
