<?php
/**
 * Template: Provider's listings list.
 *
 * Used by [jqme_my_listings] shortcode.
 * $listings variable is set by the shortcode handler before include.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JQME\StatusEnums;

$statuses = StatusEnums::listing_statuses();
$types    = StatusEnums::listing_types();
$notice   = sanitize_text_field( $_GET['jqme_notice'] ?? '' );
?>

<div class="jqme-my-listings">
	<?php if ( $notice ) : ?>
		<div class="jqme-notice jqme-notice--success">
			<p><?php echo esc_html( ucwords( str_replace( '_', ' ', $notice ) ) ); ?></p>
		</div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'My Listings', 'jq-marketplace-engine' ); ?></h2>

	<?php if ( empty( $listings ) ) : ?>
		<p><?php esc_html_e( 'You have no listings yet. Create your first listing to get started.', 'jq-marketplace-engine' ); ?></p>
	<?php else : ?>
		<table class="jqme-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Title', 'jq-marketplace-engine' ); ?></th>
					<th><?php esc_html_e( 'Type', 'jq-marketplace-engine' ); ?></th>
					<th><?php esc_html_e( 'Price', 'jq-marketplace-engine' ); ?></th>
					<th><?php esc_html_e( 'Status', 'jq-marketplace-engine' ); ?></th>
					<th><?php esc_html_e( 'Views', 'jq-marketplace-engine' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'jq-marketplace-engine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $listings as $l ) : ?>
					<?php
					$price = match ( $l->listing_type ) {
						StatusEnums::TYPE_EQUIPMENT_RENTAL => $l->day_rate ? '$' . number_format( (float) $l->day_rate, 2 ) . '/day' : '—',
						StatusEnums::TYPE_EQUIPMENT_SALE   => $l->asking_price ? '$' . number_format( (float) $l->asking_price, 2 ) : '—',
						StatusEnums::TYPE_SERVICE_BOOKING  => $l->hourly_rate ? '$' . number_format( (float) $l->hourly_rate, 2 ) . '/hr' : '—',
						default => '—',
					};
					?>
					<tr>
						<td><strong><?php echo esc_html( $l->title ); ?></strong></td>
						<td><?php echo esc_html( $types[ $l->listing_type ] ?? $l->listing_type ); ?></td>
						<td><?php echo esc_html( $price ); ?></td>
						<td><?php echo esc_html( $statuses[ $l->status ] ?? $l->status ); ?></td>
						<td><?php echo esc_html( number_format( (int) $l->view_count ) ); ?></td>
						<td>
							<a href="?listing_id=<?php echo esc_attr( $l->id ); ?>"><?php esc_html_e( 'Edit', 'jq-marketplace-engine' ); ?></a>
							<?php if ( in_array( $l->status, [ StatusEnums::LISTING_DRAFT, StatusEnums::LISTING_NEEDS_CHANGES ], true ) ) : ?>
								| <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<?php wp_nonce_field( 'jqme_submit_listing_' . $l->id ); ?>
									<input type="hidden" name="action" value="jqme_submit_listing">
									<input type="hidden" name="listing_id" value="<?php echo esc_attr( $l->id ); ?>">
									<button type="submit" class="jqme-btn-link"><?php esc_html_e( 'Submit for Review', 'jq-marketplace-engine' ); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
