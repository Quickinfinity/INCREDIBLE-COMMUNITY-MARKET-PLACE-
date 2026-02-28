<?php
/**
 * Admin providers page — list, filter, and manage provider applications.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JQME\Providers\Provider;
use JQME\StatusEnums;

$status_filter = sanitize_text_field( $_GET['status'] ?? '' );
$search        = sanitize_text_field( $_GET['s'] ?? '' );
$paged         = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page      = 20;
$offset        = ( $paged - 1 ) * $per_page;

// Get counts for filter tabs.
$counts = [];
foreach ( array_keys( StatusEnums::provider_statuses() ) as $s ) {
	$counts[ $s ] = Provider::count_by_status( $s );
}
$counts['all'] = array_sum( $counts );

// Query providers.
$providers = Provider::query( [
	'status'  => $status_filter,
	'search'  => $search,
	'limit'   => $per_page,
	'offset'  => $offset,
	'orderby' => 'applied_at',
	'order'   => 'DESC',
] );

$statuses = StatusEnums::provider_statuses();

// Handle notices.
$notice = sanitize_text_field( $_GET['jqme_notice'] ?? '' );
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Provider Applications', 'jq-marketplace-engine' ); ?></h1>

	<?php if ( $notice ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( ucwords( str_replace( '_', ' ', $notice ) ) ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Status filter tabs -->
	<ul class="subsubsub">
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-providers' ) ); ?>"
			   class="<?php echo empty( $status_filter ) ? 'current' : ''; ?>">
				<?php printf( '%s <span class="count">(%d)</span>', esc_html__( 'All', 'jq-marketplace-engine' ), $counts['all'] ); ?>
			</a> |
		</li>
		<?php foreach ( $statuses as $key => $label ) : ?>
			<?php if ( $counts[ $key ] > 0 ) : ?>
				<li>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-providers&status=' . $key ) ); ?>"
					   class="<?php echo $status_filter === $key ? 'current' : ''; ?>">
						<?php printf( '%s <span class="count">(%d)</span>', esc_html( $label ), $counts[ $key ] ); ?>
					</a> |
				</li>
			<?php endif; ?>
		<?php endforeach; ?>
	</ul>

	<!-- Search -->
	<form method="get" action="">
		<input type="hidden" name="page" value="jqme-providers">
		<?php if ( $status_filter ) : ?>
			<input type="hidden" name="status" value="<?php echo esc_attr( $status_filter ); ?>">
		<?php endif; ?>
		<p class="search-box">
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search providers...', 'jq-marketplace-engine' ); ?>">
			<input type="submit" class="button" value="<?php esc_attr_e( 'Search', 'jq-marketplace-engine' ); ?>">
		</p>
	</form>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Company', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Contact', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Location', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Listing Types', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Status', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Applied', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'jq-marketplace-engine' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $providers ) ) : ?>
				<tr>
					<td colspan="7"><?php esc_html_e( 'No providers found.', 'jq-marketplace-engine' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $providers as $p ) : ?>
					<?php
					$allowed_types = json_decode( $p->allowed_listing_types ?? '[]', true ) ?: [];
					$type_labels   = array_map( function ( $t ) {
						$types = StatusEnums::listing_types();
						return $types[ $t ] ?? $t;
					}, $allowed_types );
					$badge_class = match ( $p->status ) {
						StatusEnums::PROVIDER_APPROVED           => 'jqme-badge--success',
						StatusEnums::PROVIDER_PENDING_APPLICATION,
						StatusEnums::PROVIDER_PENDING_REVIEW     => 'jqme-badge--warning',
						StatusEnums::PROVIDER_SUSPENDED,
						StatusEnums::PROVIDER_REJECTED           => 'jqme-badge--danger',
						default                                  => 'jqme-badge--muted',
					};
					?>
					<tr>
						<td>
							<strong>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-providers&action=view&id=' . $p->id ) ); ?>">
									<?php echo esc_html( $p->company_name ); ?>
								</a>
							</strong>
							<br>
							<small><?php echo esc_html( $p->display_name ?? $p->user_login ?? '' ); ?></small>
						</td>
						<td>
							<?php echo esc_html( $p->contact_name ); ?><br>
							<small><?php echo esc_html( $p->contact_email ); ?></small>
						</td>
						<td><?php echo esc_html( trim( $p->city . ', ' . $p->state . ' ' . $p->zip, ', ' ) ); ?></td>
						<td><?php echo esc_html( implode( ', ', $type_labels ) ?: '—' ); ?></td>
						<td><span class="jqme-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $statuses[ $p->status ] ?? $p->status ); ?></span></td>
						<td><?php echo $p->applied_at ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $p->applied_at ) ) ) : '—'; ?></td>
						<td>
							<?php if ( in_array( $p->status, [ StatusEnums::PROVIDER_PENDING_APPLICATION, StatusEnums::PROVIDER_PENDING_REVIEW ], true ) ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<?php wp_nonce_field( 'jqme_provider_approve_' . $p->id ); ?>
									<input type="hidden" name="action" value="jqme_provider_approve">
									<input type="hidden" name="provider_id" value="<?php echo esc_attr( $p->id ); ?>">
									<button type="submit" class="button button-primary button-small"><?php esc_html_e( 'Approve', 'jq-marketplace-engine' ); ?></button>
								</form>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<?php wp_nonce_field( 'jqme_provider_reject_' . $p->id ); ?>
									<input type="hidden" name="action" value="jqme_provider_reject">
									<input type="hidden" name="provider_id" value="<?php echo esc_attr( $p->id ); ?>">
									<button type="submit" class="button button-small"><?php esc_html_e( 'Reject', 'jq-marketplace-engine' ); ?></button>
								</form>
							<?php elseif ( StatusEnums::PROVIDER_APPROVED === $p->status ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<?php wp_nonce_field( 'jqme_provider_suspend_' . $p->id ); ?>
									<input type="hidden" name="action" value="jqme_provider_suspend">
									<input type="hidden" name="provider_id" value="<?php echo esc_attr( $p->id ); ?>">
									<button type="submit" class="button button-small"><?php esc_html_e( 'Suspend', 'jq-marketplace-engine' ); ?></button>
								</form>
							<?php elseif ( StatusEnums::PROVIDER_SUSPENDED === $p->status ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<?php wp_nonce_field( 'jqme_provider_reactivate_' . $p->id ); ?>
									<input type="hidden" name="action" value="jqme_provider_reactivate">
									<input type="hidden" name="provider_id" value="<?php echo esc_attr( $p->id ); ?>">
									<button type="submit" class="button button-small"><?php esc_html_e( 'Reactivate', 'jq-marketplace-engine' ); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
