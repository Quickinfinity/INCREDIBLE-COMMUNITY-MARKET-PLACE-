<?php
/**
 * Admin payouts page — view and manage provider payouts.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JQME\Core;

global $wpdb;

$status_filter = sanitize_text_field( $_GET['status'] ?? '' );
$search        = sanitize_text_field( $_GET['s'] ?? '' );
$paged         = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page      = 25;

$payouts   = Core::table( 'payouts' );
$providers = Core::table( 'providers' );
$bookings  = Core::table( 'bookings' );

$where  = [];
$values = [];

if ( $status_filter ) {
	$where[]  = 'p.status = %s';
	$values[] = $status_filter;
}
if ( $search ) {
	$like     = '%' . $wpdb->esc_like( $search ) . '%';
	$where[]  = '(pr.company_name LIKE %s OR u.display_name LIKE %s OR p.gateway_payout_id LIKE %s)';
	$values[] = $like;
	$values[] = $like;
	$values[] = $like;
}

$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

$count_sql = "SELECT COUNT(*) FROM {$payouts} p
			  LEFT JOIN {$providers} pr ON p.provider_id = pr.id
			  LEFT JOIN {$wpdb->users} u ON pr.user_id = u.ID
			  {$where_sql}";

$total = ! empty( $values )
	? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) )
	: (int) $wpdb->get_var( $count_sql );

$sql = "SELECT p.*, pr.company_name, u.display_name as provider_name, b.booking_number
		FROM {$payouts} p
		LEFT JOIN {$providers} pr ON p.provider_id = pr.id
		LEFT JOIN {$wpdb->users} u ON pr.user_id = u.ID
		LEFT JOIN {$bookings} b ON p.booking_id = b.id
		{$where_sql}
		ORDER BY p.created_at DESC
		LIMIT %d OFFSET %d";

$values[] = $per_page;
$values[] = ( $paged - 1 ) * $per_page;

$results = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );

$notice = sanitize_text_field( $_GET['jqme_notice'] ?? '' );

$status_labels = [
	'not_ready' => __( 'Not Ready', 'jq-marketplace-engine' ),
	'queued'    => __( 'Queued', 'jq-marketplace-engine' ),
	'sent'      => __( 'Sent', 'jq-marketplace-engine' ),
	'failed'    => __( 'Failed', 'jq-marketplace-engine' ),
];
$status_colors = [
	'not_ready' => '#999',
	'queued'    => '#f0ad4e',
	'sent'      => '#28a745',
	'failed'    => '#dc3545',
];
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Payouts', 'jq-marketplace-engine' ); ?></h1>

	<?php if ( $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( ucwords( str_replace( '_', ' ', $notice ) ) ); ?></p></div>
	<?php endif; ?>

	<!-- Filters -->
	<div style="display:flex; gap:8px; margin-bottom:16px; align-items:center;">
		<?php foreach ( [ '' => __( 'All', 'jq-marketplace-engine' ) ] + $status_labels as $sk => $sl ) : ?>
			<a href="<?php echo esc_url( add_query_arg( [ 'status' => $sk, 'paged' => 1 ] ) ); ?>"
			   class="button <?php echo $status_filter === $sk ? 'button-primary' : ''; ?>">
				<?php echo esc_html( $sl ); ?>
			</a>
		<?php endforeach; ?>

		<form method="get" style="margin-left:auto; display:flex; gap:4px;">
			<input type="hidden" name="page" value="jqme-payouts">
			<input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search...', 'jq-marketplace-engine' ); ?>">
			<button type="submit" class="button"><?php esc_html_e( 'Search', 'jq-marketplace-engine' ); ?></button>
		</form>
	</div>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ID', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Provider', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Booking', 'jq-marketplace-engine' ); ?></th>
				<th style="text-align:right;"><?php esc_html_e( 'Amount', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Status', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Hold Until', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Sent At', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Created', 'jq-marketplace-engine' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $results ) ) : ?>
				<tr><td colspan="8"><?php esc_html_e( 'No payouts found.', 'jq-marketplace-engine' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $results as $p ) : ?>
					<tr>
						<td>#<?php echo esc_html( $p->id ); ?></td>
						<td><?php echo esc_html( $p->company_name ?: $p->provider_name ?: '—' ); ?></td>
						<td>
							<?php if ( $p->booking_number ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-bookings&id=' . $p->booking_id ) ); ?>">
									<?php echo esc_html( $p->booking_number ); ?>
								</a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td style="text-align:right; font-weight:600;">$<?php echo esc_html( number_format( (float) $p->amount, 2 ) ); ?></td>
						<td>
							<span style="color:<?php echo $status_colors[ $p->status ] ?? '#666'; ?>; font-weight:600;">
								<?php echo esc_html( $status_labels[ $p->status ] ?? ucwords( str_replace( '_', ' ', $p->status ) ) ); ?>
							</span>
							<?php if ( 'failed' === $p->status && $p->failure_reason ) : ?>
								<br><small style="color:#dc3545;"><?php echo esc_html( $p->failure_reason ); ?></small>
							<?php endif; ?>
						</td>
						<td><?php echo $p->hold_until ? esc_html( date_i18n( 'M j, Y g:ia', strtotime( $p->hold_until ) ) ) : '—'; ?></td>
						<td><?php echo $p->sent_at ? esc_html( date_i18n( 'M j, Y g:ia', strtotime( $p->sent_at ) ) ) : '—'; ?></td>
						<td><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $p->created_at ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php
	$total_pages = ceil( $total / $per_page );
	if ( $total_pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
					<?php if ( $i === $paged ) : ?>
						<strong><?php echo $i; ?></strong>
					<?php else : ?>
						<a href="<?php echo esc_url( add_query_arg( 'paged', $i ) ); ?>"><?php echo $i; ?></a>
					<?php endif; ?>
				<?php endfor; ?>
			</div>
		</div>
	<?php endif; ?>
</div>
