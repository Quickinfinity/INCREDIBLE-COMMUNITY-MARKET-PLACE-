<?php
/**
 * Admin claims page — list, filter, and manage damage claims.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JQME\Claims\Claim;
use JQME\StatusEnums;

$action = sanitize_text_field( $_GET['action'] ?? '' );

// Single claim detail view.
if ( 'view' === $action ) {
	$claim_id = absint( $_GET['id'] ?? 0 );
	$claim    = $claim_id ? Claim::get( $claim_id ) : null;

	if ( ! $claim ) {
		echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Claim not found.', 'jq-marketplace-engine' ) . '</p></div></div>';
		return;
	}

	include __DIR__ . '/claim-detail.php';
	return;
}

// --- List view ---
$status_filter = sanitize_text_field( $_GET['status'] ?? '' );
$search        = sanitize_text_field( $_GET['s'] ?? '' );
$paged         = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page      = 20;

$notice = sanitize_text_field( $_GET['jqme_notice'] ?? '' );
$claim_statuses = StatusEnums::claim_statuses();

// Tab counts.
$tab_statuses = [
	StatusEnums::CLAIM_SUBMITTED              => __( 'Submitted', 'jq-marketplace-engine' ),
	StatusEnums::CLAIM_AWAITING_CUSTOMER      => __( 'Awaiting Customer', 'jq-marketplace-engine' ),
	StatusEnums::CLAIM_AWAITING_PROVIDER      => __( 'Awaiting Provider', 'jq-marketplace-engine' ),
	StatusEnums::CLAIM_EVIDENCE_UNDER_REVIEW  => __( 'Under Review', 'jq-marketplace-engine' ),
	StatusEnums::CLAIM_CLOSED                 => __( 'Closed', 'jq-marketplace-engine' ),
];

$tab_counts = [];
foreach ( array_keys( $tab_statuses ) as $s ) {
	$tab_counts[ $s ] = Claim::count( [ 'status' => $s ] );
}
$total_count = Claim::count();

$claims = Claim::query( [
	'status' => $status_filter,
	'search' => $search,
	'limit'  => $per_page,
	'offset' => ( $paged - 1 ) * $per_page,
] );
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Claims', 'jq-marketplace-engine' ); ?></h1>

	<?php if ( $notice ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( ucwords( str_replace( '_', ' ', $notice ) ) ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Status tabs -->
	<ul class="subsubsub">
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-claims' ) ); ?>"
			   class="<?php echo empty( $status_filter ) ? 'current' : ''; ?>">
				<?php printf( '%s <span class="count">(%d)</span>', esc_html__( 'All', 'jq-marketplace-engine' ), $total_count ); ?>
			</a> |
		</li>
		<?php foreach ( $tab_statuses as $key => $label ) : ?>
			<?php if ( ( $tab_counts[ $key ] ?? 0 ) > 0 ) : ?>
				<li>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-claims&status=' . $key ) ); ?>"
					   class="<?php echo $status_filter === $key ? 'current' : ''; ?>">
						<?php printf( '%s <span class="count">(%d)</span>', esc_html( $label ), $tab_counts[ $key ] ); ?>
					</a> |
				</li>
			<?php endif; ?>
		<?php endforeach; ?>
	</ul>

	<!-- Search -->
	<form method="get" action="">
		<input type="hidden" name="page" value="jqme-claims">
		<?php if ( $status_filter ) : ?>
			<input type="hidden" name="status" value="<?php echo esc_attr( $status_filter ); ?>">
		<?php endif; ?>
		<p class="search-box">
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search claims...', 'jq-marketplace-engine' ); ?>">
			<input type="submit" class="button" value="<?php esc_attr_e( 'Search', 'jq-marketplace-engine' ); ?>">
		</p>
	</form>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width:110px;"><?php esc_html_e( 'Claim #', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Booking', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Filed By', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Type', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Requested', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Settled', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Status', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Filed', 'jq-marketplace-engine' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $claims ) ) : ?>
				<tr><td colspan="8"><?php esc_html_e( 'No claims found.', 'jq-marketplace-engine' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $claims as $c ) :
					$badge = match ( true ) {
						str_contains( $c->status, 'closed' ),
						str_contains( $c->status, 'denied' )    => 'jqme-badge--muted',
						str_contains( $c->status, 'settled' ),
						str_contains( $c->status, 'capture' )   => 'jqme-badge--success',
						str_contains( $c->status, 'submitted' ),
						str_contains( $c->status, 'awaiting' )  => 'jqme-badge--warning',
						str_contains( $c->status, 'review' )    => 'jqme-badge--info',
						default                                   => 'jqme-badge--muted',
					};
				?>
					<tr>
						<td>
							<strong>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-claims&action=view&id=' . $c->id ) ); ?>">
									<?php echo esc_html( $c->claim_number ); ?>
								</a>
							</strong>
						</td>
						<td>
							<?php if ( $c->booking_number ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-bookings&action=view&id=' . $c->booking_id ) ); ?>">
									<?php echo esc_html( $c->booking_number ); ?>
								</a>
							<?php else : ?>
								#<?php echo esc_html( $c->booking_id ); ?>
							<?php endif; ?>
						</td>
						<td>
							<?php echo esc_html( $c->filed_by_name ?? '—' ); ?>
							<br><small><?php echo esc_html( ucfirst( $c->filed_by_role ) ); ?></small>
						</td>
						<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $c->claim_type ) ) ); ?></td>
						<td>$<?php echo esc_html( number_format( (float) $c->amount_requested, 2 ) ); ?></td>
						<td>$<?php echo esc_html( number_format( (float) $c->amount_settled, 2 ) ); ?></td>
						<td><span class="jqme-badge <?php echo esc_attr( $badge ); ?>"><?php echo esc_html( $claim_statuses[ $c->status ] ?? $c->status ); ?></span></td>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $c->created_at ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
