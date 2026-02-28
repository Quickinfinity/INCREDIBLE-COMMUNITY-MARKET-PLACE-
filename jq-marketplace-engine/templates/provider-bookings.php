<?php
/**
 * Template: Provider incoming bookings list.
 *
 * Used by [jqme_provider_bookings] shortcode.
 * Expects $provider to be available from BookingHandler.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JQME\Bookings\Booking;
use JQME\Providers\Provider;
use JQME\StatusEnums;

$provider = Provider::get_by_user( get_current_user_id() );
if ( ! $provider ) {
	return;
}

$status_filter = sanitize_text_field( $_GET['booking_status'] ?? '' );
$paged         = max( 1, absint( $_GET['bpage'] ?? 1 ) );
$per_page      = 15;

$bookings = Booking::query( [
	'provider_id' => $provider->id,
	'status'      => $status_filter,
	'limit'       => $per_page,
	'offset'      => ( $paged - 1 ) * $per_page,
] );

$total = Booking::count( [
	'provider_id' => $provider->id,
	'status'      => $status_filter ?: null,
] );

$notice = sanitize_text_field( $_GET['jqme_notice'] ?? '' );
$all_statuses = array_merge(
	StatusEnums::rental_booking_statuses(),
	StatusEnums::service_booking_statuses(),
	StatusEnums::sale_order_statuses()
);
$types = StatusEnums::listing_types();

// Count pending for badge.
$pending_count = Booking::count( [
	'provider_id' => $provider->id,
	'status'      => 'requested',
] );
?>

<div class="jqme-provider-bookings">
	<h2>
		<?php esc_html_e( 'Booking Requests', 'jq-marketplace-engine' ); ?>
		<?php if ( $pending_count > 0 ) : ?>
			<span class="jqme-badge jqme-badge--warning"><?php echo esc_html( $pending_count ); ?> <?php esc_html_e( 'pending', 'jq-marketplace-engine' ); ?></span>
		<?php endif; ?>
	</h2>

	<?php if ( $notice ) : ?>
		<div class="jqme-notice jqme-notice--success">
			<?php echo esc_html( ucwords( str_replace( '_', ' ', $notice ) ) ); ?>
		</div>
	<?php endif; ?>

	<!-- Quick filters -->
	<div class="jqme-filters" style="margin-bottom:16px;">
		<a href="<?php echo esc_url( remove_query_arg( [ 'booking_status', 'bpage' ] ) ); ?>"
		   class="jqme-btn jqme-btn--small <?php echo empty( $status_filter ) ? 'jqme-btn--primary' : ''; ?>">
			<?php esc_html_e( 'All', 'jq-marketplace-engine' ); ?>
		</a>
		<?php
		$filter_groups = [
			'requested'  => __( 'Needs Action', 'jq-marketplace-engine' ),
			'confirmed'  => __( 'Confirmed', 'jq-marketplace-engine' ),
			'checked_out' => __( 'Checked Out', 'jq-marketplace-engine' ),
			'completed'  => __( 'Completed', 'jq-marketplace-engine' ),
		];
		foreach ( $filter_groups as $fk => $fl ) : ?>
			<a href="<?php echo esc_url( add_query_arg( [ 'booking_status' => $fk, 'bpage' => 1 ] ) ); ?>"
			   class="jqme-btn jqme-btn--small <?php echo $status_filter === $fk ? 'jqme-btn--primary' : ''; ?>">
				<?php echo esc_html( $fl ); ?>
			</a>
		<?php endforeach; ?>
	</div>

	<?php if ( empty( $bookings ) ) : ?>
		<p><?php esc_html_e( 'No bookings found.', 'jq-marketplace-engine' ); ?></p>
	<?php else : ?>
		<table class="jqme-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Booking #', 'jq-marketplace-engine' ); ?></th>
					<th><?php esc_html_e( 'Customer', 'jq-marketplace-engine' ); ?></th>
					<th><?php esc_html_e( 'Listing', 'jq-marketplace-engine' ); ?></th>
					<th><?php esc_html_e( 'Dates', 'jq-marketplace-engine' ); ?></th>
					<th><?php esc_html_e( 'Payout', 'jq-marketplace-engine' ); ?></th>
					<th><?php esc_html_e( 'Status', 'jq-marketplace-engine' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'jq-marketplace-engine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $bookings as $b ) :
					$status_label = $all_statuses[ $b->status ] ?? ucwords( str_replace( '_', ' ', $b->status ) );
					$needs_action = in_array( $b->status, [
						StatusEnums::RENTAL_REQUESTED,
						StatusEnums::RENTAL_PENDING_PROVIDER_APPROVAL,
						StatusEnums::SERVICE_REQUESTED,
						StatusEnums::SERVICE_PENDING_PROVIDER_APPROVAL,
					], true );
				?>
					<tr<?php echo $needs_action ? ' style="background:#fff8e5;"' : ''; ?>>
						<td>
							<strong><?php echo esc_html( $b->booking_number ); ?></strong>
						</td>
						<td><?php echo esc_html( $b->customer_name ?? '—' ); ?></td>
						<td><?php echo esc_html( $b->listing_title ?? '—' ); ?></td>
						<td>
							<?php if ( $b->date_start ) : ?>
								<?php echo esc_html( date_i18n( 'M j', strtotime( $b->date_start ) ) ); ?>
								<?php if ( $b->date_end ) : ?>
									— <?php echo esc_html( date_i18n( 'M j', strtotime( $b->date_end ) ) ); ?>
								<?php endif; ?>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td>$<?php echo esc_html( number_format( (float) $b->provider_payout, 2 ) ); ?></td>
						<td><span class="jqme-badge"><?php echo esc_html( $status_label ); ?></span></td>
						<td>
							<?php if ( $needs_action ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<?php wp_nonce_field( 'jqme_booking_approve_provider_' . $b->id ); ?>
									<input type="hidden" name="action" value="jqme_booking_approve_provider">
									<input type="hidden" name="booking_id" value="<?php echo esc_attr( $b->id ); ?>">
									<button type="submit" class="jqme-btn jqme-btn--small jqme-btn--primary">
										<?php esc_html_e( 'Approve', 'jq-marketplace-engine' ); ?>
									</button>
								</form>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<?php wp_nonce_field( 'jqme_booking_decline_provider_' . $b->id ); ?>
									<input type="hidden" name="action" value="jqme_booking_decline_provider">
									<input type="hidden" name="booking_id" value="<?php echo esc_attr( $b->id ); ?>">
									<button type="submit" class="jqme-btn jqme-btn--small jqme-btn--danger"
											onclick="return confirm('<?php esc_attr_e( 'Decline this booking?', 'jq-marketplace-engine' ); ?>');">
										<?php esc_html_e( 'Decline', 'jq-marketplace-engine' ); ?>
									</button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php
		$total_pages = ceil( $total / $per_page );
		if ( $total_pages > 1 ) : ?>
			<div class="jqme-pagination" style="margin-top:16px;">
				<?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
					<?php if ( $i === $paged ) : ?>
						<span class="jqme-btn jqme-btn--small jqme-btn--primary"><?php echo $i; ?></span>
					<?php else : ?>
						<a class="jqme-btn jqme-btn--small" href="<?php echo esc_url( add_query_arg( 'bpage', $i ) ); ?>"><?php echo $i; ?></a>
					<?php endif; ?>
				<?php endfor; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
