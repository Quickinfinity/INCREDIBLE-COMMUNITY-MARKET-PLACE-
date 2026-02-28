<?php
/**
 * Admin verification queue — serial number and equipment verification.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JQME\Listings\Verification;
use JQME\StatusEnums;

$status_filter = sanitize_text_field( $_GET['status'] ?? '' );
$paged         = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page      = 20;
$offset        = ( $paged - 1 ) * $per_page;

$statuses = StatusEnums::verification_statuses();

$pending_count  = Verification::count_by_status( StatusEnums::VERIFY_PENDING_SERIAL );
$docs_count     = Verification::count_by_status( StatusEnums::VERIFY_PENDING_DOCS );
$verified_count = Verification::count_by_status( StatusEnums::VERIFY_VERIFIED );
$rejected_count = Verification::count_by_status( StatusEnums::VERIFY_REJECTED );
$total          = Verification::count_by_status();

$verifications = Verification::query( [
	'status' => $status_filter,
	'limit'  => $per_page,
	'offset' => $offset,
] );

$notice = sanitize_text_field( $_GET['jqme_notice'] ?? '' );
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Equipment Verification Queue', 'jq-marketplace-engine' ); ?></h1>

	<?php if ( $notice ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( ucwords( str_replace( '_', ' ', $notice ) ) ); ?></p>
		</div>
	<?php endif; ?>

	<ul class="subsubsub">
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-verifications' ) ); ?>" class="<?php echo empty( $status_filter ) ? 'current' : ''; ?>">All (<?php echo $total; ?>)</a> | </li>
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-verifications&status=pending_serial_review' ) ); ?>" class="<?php echo 'pending_serial_review' === $status_filter ? 'current' : ''; ?>">Pending Review (<?php echo $pending_count; ?>)</a> | </li>
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-verifications&status=pending_docs' ) ); ?>" class="<?php echo 'pending_docs' === $status_filter ? 'current' : ''; ?>">Pending Docs (<?php echo $docs_count; ?>)</a> | </li>
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-verifications&status=verified' ) ); ?>" class="<?php echo 'verified' === $status_filter ? 'current' : ''; ?>">Verified (<?php echo $verified_count; ?>)</a> | </li>
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-verifications&status=rejected' ) ); ?>" class="<?php echo 'rejected' === $status_filter ? 'current' : ''; ?>">Rejected (<?php echo $rejected_count; ?>)</a></li>
	</ul>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Listing', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Provider', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Serial Number', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Type', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Status', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Documents', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Submitted', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'jq-marketplace-engine' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $verifications ) ) : ?>
				<tr><td colspan="8"><?php esc_html_e( 'No verification requests found.', 'jq-marketplace-engine' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $verifications as $v ) : ?>
					<?php
					$docs      = json_decode( $v->document_urls ?? '[]', true ) ?: [];
					$badge_class = match ( $v->status ) {
						StatusEnums::VERIFY_VERIFIED       => 'jqme-badge--success',
						StatusEnums::VERIFY_PENDING_SERIAL,
						StatusEnums::VERIFY_PENDING_DOCS   => 'jqme-badge--warning',
						StatusEnums::VERIFY_REJECTED       => 'jqme-badge--danger',
						default                            => 'jqme-badge--muted',
					};
					?>
					<tr>
						<td><strong><?php echo esc_html( $v->listing_title ?? '#' . $v->listing_id ); ?></strong></td>
						<td><?php echo esc_html( $v->provider_name ?? '#' . $v->provider_id ); ?></td>
						<td><code><?php echo esc_html( $v->serial_number ); ?></code></td>
						<td><?php echo esc_html( $v->verification_type ); ?></td>
						<td><span class="jqme-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $statuses[ $v->status ] ?? $v->status ); ?></span></td>
						<td><?php echo count( $docs ); ?> file(s)</td>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $v->created_at ) ) ); ?></td>
						<td>
							<?php if ( in_array( $v->status, [ StatusEnums::VERIFY_PENDING_SERIAL, StatusEnums::VERIFY_PENDING_DOCS ], true ) ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<?php wp_nonce_field( 'jqme_verification_approve_' . $v->id ); ?>
									<input type="hidden" name="action" value="jqme_verification_approve">
									<input type="hidden" name="verification_id" value="<?php echo esc_attr( $v->id ); ?>">
									<button type="submit" class="button button-primary button-small"><?php esc_html_e( 'Verify', 'jq-marketplace-engine' ); ?></button>
								</form>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<?php wp_nonce_field( 'jqme_verification_reject_' . $v->id ); ?>
									<input type="hidden" name="action" value="jqme_verification_reject">
									<input type="hidden" name="verification_id" value="<?php echo esc_attr( $v->id ); ?>">
									<button type="submit" class="button button-small"><?php esc_html_e( 'Reject', 'jq-marketplace-engine' ); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
