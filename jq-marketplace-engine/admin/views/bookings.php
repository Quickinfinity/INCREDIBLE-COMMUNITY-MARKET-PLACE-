<?php
/**
 * Admin bookings page — list, filter, and manage all bookings.
 *
 * Supports sub-action "view" to show a single booking detail page.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JQME\Bookings\Booking;
use JQME\StatusEnums;

$action = sanitize_text_field( $_GET['action'] ?? '' );

// Single booking detail view.
if ( 'view' === $action ) {
	$booking_id = absint( $_GET['id'] ?? 0 );
	$booking    = $booking_id ? Booking::get( $booking_id ) : null;

	if ( ! $booking ) {
		echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Booking not found.', 'jq-marketplace-engine' ) . '</p></div></div>';
		return;
	}

	// Load detail sub-view.
	include __DIR__ . '/booking-detail.php';
	return;
}

// --- List view ---
$status_filter = sanitize_text_field( $_GET['status'] ?? '' );
$type_filter   = sanitize_text_field( $_GET['booking_type'] ?? '' );
$search        = sanitize_text_field( $_GET['s'] ?? '' );
$paged         = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page      = 20;
$offset        = ( $paged - 1 ) * $per_page;

$notice = sanitize_text_field( $_GET['jqme_notice'] ?? '' );

// Status counts per type.
$booking_types = StatusEnums::listing_types();
$all_statuses  = array_merge(
	StatusEnums::rental_booking_statuses(),
	StatusEnums::service_booking_statuses(),
	StatusEnums::sale_order_statuses()
);

// Key statuses for quick-filter tabs.
$tab_statuses = [
	'requested'                  => __( 'Requested', 'jq-marketplace-engine' ),
	'pending_provider_approval'  => __( 'Pending Approval', 'jq-marketplace-engine' ),
	'confirmed'                  => __( 'Confirmed', 'jq-marketplace-engine' ),
	'checked_out'                => __( 'Checked Out', 'jq-marketplace-engine' ),
	'active'                     => __( 'Active', 'jq-marketplace-engine' ),
	'completed'                  => __( 'Completed', 'jq-marketplace-engine' ),
	'overdue'                    => __( 'Overdue', 'jq-marketplace-engine' ),
	'dispute_hold'               => __( 'Disputes', 'jq-marketplace-engine' ),
];

// Counts.
$tab_counts = [];
foreach ( array_keys( $tab_statuses ) as $s ) {
	$tab_counts[ $s ] = Booking::count( [ 'status' => $s ] );
}
$total_count = Booking::count();

// Query bookings.
$bookings = Booking::query( [
	'booking_type' => $type_filter,
	'status'       => $status_filter,
	'search'       => $search,
	'limit'        => $per_page,
	'offset'       => $offset,
] );
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Bookings', 'jq-marketplace-engine' ); ?></h1>

	<?php if ( $notice ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( ucwords( str_replace( '_', ' ', $notice ) ) ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Type filter -->
	<div style="margin:10px 0;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-bookings' ) ); ?>"
		   class="button <?php echo empty( $type_filter ) ? 'button-primary' : ''; ?>"><?php esc_html_e( 'All Types', 'jq-marketplace-engine' ); ?></a>
		<?php foreach ( $booking_types as $tk => $tl ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-bookings&booking_type=' . $tk ) ); ?>"
			   class="button <?php echo $type_filter === $tk ? 'button-primary' : ''; ?>"><?php echo esc_html( $tl ); ?></a>
		<?php endforeach; ?>
	</div>

	<!-- Status filter tabs -->
	<ul class="subsubsub">
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-bookings' . ( $type_filter ? '&booking_type=' . $type_filter : '' ) ) ); ?>"
			   class="<?php echo empty( $status_filter ) ? 'current' : ''; ?>">
				<?php printf( '%s <span class="count">(%d)</span>', esc_html__( 'All', 'jq-marketplace-engine' ), $total_count ); ?>
			</a> |
		</li>
		<?php foreach ( $tab_statuses as $key => $label ) : ?>
			<?php if ( ( $tab_counts[ $key ] ?? 0 ) > 0 ) : ?>
				<li>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-bookings&status=' . $key . ( $type_filter ? '&booking_type=' . $type_filter : '' ) ) ); ?>"
					   class="<?php echo $status_filter === $key ? 'current' : ''; ?>">
						<?php printf( '%s <span class="count">(%d)</span>', esc_html( $label ), $tab_counts[ $key ] ); ?>
					</a> |
				</li>
			<?php endif; ?>
		<?php endforeach; ?>
	</ul>

	<!-- Search -->
	<form method="get" action="">
		<input type="hidden" name="page" value="jqme-bookings">
		<?php if ( $status_filter ) : ?>
			<input type="hidden" name="status" value="<?php echo esc_attr( $status_filter ); ?>">
		<?php endif; ?>
		<?php if ( $type_filter ) : ?>
			<input type="hidden" name="booking_type" value="<?php echo esc_attr( $type_filter ); ?>">
		<?php endif; ?>
		<p class="search-box">
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search bookings...', 'jq-marketplace-engine' ); ?>">
			<input type="submit" class="button" value="<?php esc_attr_e( 'Search', 'jq-marketplace-engine' ); ?>">
		</p>
	</form>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width:120px;"><?php esc_html_e( 'Booking #', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Type', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Listing', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Customer', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Provider', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Total', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Status', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Dates', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Created', 'jq-marketplace-engine' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $bookings ) ) : ?>
				<tr>
					<td colspan="9"><?php esc_html_e( 'No bookings found.', 'jq-marketplace-engine' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $bookings as $b ) :
					$badge_class = match ( true ) {
						str_contains( $b->status, 'completed' )  => 'jqme-badge--success',
						str_contains( $b->status, 'confirmed' ),
						str_contains( $b->status, 'active' ),
						str_contains( $b->status, 'checked_out' ) => 'jqme-badge--info',
						str_contains( $b->status, 'requested' ),
						str_contains( $b->status, 'pending' )    => 'jqme-badge--warning',
						str_contains( $b->status, 'cancelled' ),
						str_contains( $b->status, 'failed' ),
						str_contains( $b->status, 'overdue' ),
						str_contains( $b->status, 'dispute' )    => 'jqme-badge--danger',
						default                                   => 'jqme-badge--muted',
					};
					$status_label = $all_statuses[ $b->status ] ?? ucwords( str_replace( '_', ' ', $b->status ) );
					$type_label   = $booking_types[ $b->booking_type ] ?? $b->booking_type;
				?>
					<tr>
						<td>
							<strong>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-bookings&action=view&id=' . $b->id ) ); ?>">
									<?php echo esc_html( $b->booking_number ); ?>
								</a>
							</strong>
						</td>
						<td><small><?php echo esc_html( $type_label ); ?></small></td>
						<td><?php echo esc_html( $b->listing_title ?? '—' ); ?></td>
						<td><?php echo esc_html( $b->customer_name ?? '—' ); ?></td>
						<td><?php echo esc_html( $b->provider_name ?? '—' ); ?></td>
						<td>$<?php echo esc_html( number_format( (float) $b->total_amount, 2 ) ); ?></td>
						<td><span class="jqme-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $status_label ); ?></span></td>
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
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $b->created_at ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php
	// Pagination.
	$total_items = Booking::count( array_filter( [
		'status'       => $status_filter,
		'booking_type' => $type_filter,
	] ) );
	$total_pages = ceil( $total_items / $per_page );
	if ( $total_pages > 1 ) :
	?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
					<?php if ( $i === $paged ) : ?>
						<span class="tablenav-pages-navspan button disabled"><?php echo $i; ?></span>
					<?php else : ?>
						<a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $i ) ); ?>"><?php echo $i; ?></a>
					<?php endif; ?>
				<?php endfor; ?>
			</div>
		</div>
	<?php endif; ?>
</div>
