<?php
/**
 * Template: Front-end claim detail view.
 *
 * Used by [jqme_claim_detail] shortcode.
 * Expects ?claim_number= in URL.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JQME\Claims\Claim;
use JQME\Providers\Provider;
use JQME\StatusEnums;

$claim_number = sanitize_text_field( $_GET['claim_number'] ?? '' );
$claim        = $claim_number ? Claim::get_by_number( $claim_number ) : null;

if ( ! $claim ) {
	echo '<div class="jqme-notice jqme-notice--error">' . esc_html__( 'Claim not found.', 'jq-marketplace-engine' ) . '</div>';
	return;
}

$booking  = \JQME\Bookings\Booking::get( $claim->booking_id );
$user_id  = get_current_user_id();
$provider = $booking ? Provider::get( $booking->provider_id ) : null;

$is_filer    = (int) $claim->filed_by === $user_id;
$is_customer = $booking && (int) $booking->customer_id === $user_id;
$is_provider = $provider && (int) $provider->user_id === $user_id;

if ( ! $is_customer && ! $is_provider && ! current_user_can( 'jqme_manage_claims' ) ) {
	echo '<div class="jqme-notice jqme-notice--error">' . esc_html__( 'You do not have permission to view this claim.', 'jq-marketplace-engine' ) . '</div>';
	return;
}

$claim_statuses = StatusEnums::claim_statuses();
$status_label   = $claim_statuses[ $claim->status ] ?? $claim->status;
$evidence       = Claim::get_evidence( $claim->id );
$notice         = sanitize_text_field( $_GET['jqme_notice'] ?? '' );

$can_withdraw = $is_filer && in_array( $claim->status, [
	StatusEnums::CLAIM_SUBMITTED,
	StatusEnums::CLAIM_AWAITING_CUSTOMER,
	StatusEnums::CLAIM_AWAITING_PROVIDER,
], true );

$can_add_evidence = ( $is_customer || $is_provider ) && in_array( $claim->status, [
	StatusEnums::CLAIM_SUBMITTED,
	StatusEnums::CLAIM_AWAITING_CUSTOMER,
	StatusEnums::CLAIM_AWAITING_PROVIDER,
	StatusEnums::CLAIM_EVIDENCE_UNDER_REVIEW,
], true );
?>

<div class="jqme-claim-detail">
	<?php if ( $notice ) : ?>
		<div class="jqme-notice jqme-notice--success"><?php echo esc_html( ucwords( str_replace( '_', ' ', $notice ) ) ); ?></div>
	<?php endif; ?>

	<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
		<div>
			<h2 style="margin:0;"><?php echo esc_html( $claim->claim_number ); ?></h2>
			<p style="margin:4px 0;">
				<span class="jqme-badge"><?php echo esc_html( $status_label ); ?></span>
				<span class="jqme-badge"><?php echo esc_html( ucwords( str_replace( '_', ' ', $claim->claim_type ) ) ); ?></span>
			</p>
		</div>
	</div>

	<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
		<div>
			<!-- Claim info -->
			<div class="jqme-card">
				<h3><?php esc_html_e( 'Claim Details', 'jq-marketplace-engine' ); ?></h3>
				<table class="jqme-detail-table">
					<tr>
						<td><strong><?php esc_html_e( 'Booking', 'jq-marketplace-engine' ); ?></strong></td>
						<td><?php echo esc_html( $booking ? $booking->booking_number : '#' . $claim->booking_id ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Description', 'jq-marketplace-engine' ); ?></strong></td>
						<td><?php echo esc_html( $claim->description ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Amount Requested', 'jq-marketplace-engine' ); ?></strong></td>
						<td>$<?php echo esc_html( number_format( (float) $claim->amount_requested, 2 ) ); ?></td>
					</tr>
					<?php if ( (float) $claim->amount_settled > 0 ) : ?>
						<tr>
							<td><strong><?php esc_html_e( 'Amount Settled', 'jq-marketplace-engine' ); ?></strong></td>
							<td style="color:#00a32a;">$<?php echo esc_html( number_format( (float) $claim->amount_settled, 2 ) ); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( $claim->resolution_notes ) : ?>
						<tr>
							<td><strong><?php esc_html_e( 'Resolution', 'jq-marketplace-engine' ); ?></strong></td>
							<td><?php echo esc_html( $claim->resolution_notes ); ?></td>
						</tr>
					<?php endif; ?>
				</table>
			</div>

			<!-- Evidence -->
			<div class="jqme-card" style="margin-top:16px;">
				<h3><?php printf( esc_html__( 'Evidence (%d)', 'jq-marketplace-engine' ), count( $evidence ) ); ?></h3>
				<?php if ( ! empty( $evidence ) ) : ?>
					<?php foreach ( $evidence as $ev ) : ?>
						<div style="display:flex; gap:12px; padding:8px 0; border-bottom:1px solid #f3f4f6;">
							<?php if ( 'photo' === $ev->evidence_type && $ev->file_url ) : ?>
								<a href="<?php echo esc_url( $ev->file_url ); ?>" target="_blank">
									<img src="<?php echo esc_url( $ev->file_url ); ?>" style="width:80px; height:60px; object-fit:cover; border-radius:4px;">
								</a>
							<?php endif; ?>
							<div>
								<strong><?php echo esc_html( $ev->submitter_name ?? '—' ); ?></strong>
								<p style="margin:2px 0; font-size:13px;"><?php echo esc_html( $ev->description ); ?></p>
								<small><?php echo esc_html( date_i18n( 'M j, g:ia', strtotime( $ev->created_at ) ) ); ?></small>
							</div>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<p><?php esc_html_e( 'No evidence submitted.', 'jq-marketplace-engine' ); ?></p>
				<?php endif; ?>

				<?php if ( $can_add_evidence ) : ?>
					<hr>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
						<?php wp_nonce_field( 'jqme_add_evidence_' . $claim->id ); ?>
						<input type="hidden" name="action" value="jqme_add_evidence">
						<input type="hidden" name="claim_id" value="<?php echo esc_attr( $claim->id ); ?>">
						<div class="jqme-field">
							<label><?php esc_html_e( 'Add Evidence', 'jq-marketplace-engine' ); ?></label>
							<input type="file" name="evidence_file" accept="image/*,.pdf" required>
						</div>
						<div class="jqme-field">
							<textarea name="evidence_description" rows="2" placeholder="<?php esc_attr_e( 'Describe this evidence...', 'jq-marketplace-engine' ); ?>" style="width:100%;"></textarea>
						</div>
						<button type="submit" class="jqme-btn jqme-btn--small jqme-btn--primary"><?php esc_html_e( 'Upload Evidence', 'jq-marketplace-engine' ); ?></button>
					</form>
				<?php endif; ?>
			</div>
		</div>

		<div>
			<!-- Deadlines -->
			<div class="jqme-card">
				<h3><?php esc_html_e( 'Timeline', 'jq-marketplace-engine' ); ?></h3>
				<p><?php esc_html_e( 'Filed:', 'jq-marketplace-engine' ); ?> <?php echo esc_html( date_i18n( 'M j, Y g:ia', strtotime( $claim->created_at ) ) ); ?></p>
				<?php if ( $claim->customer_response_deadline ) : ?>
					<p><?php esc_html_e( 'Customer response by:', 'jq-marketplace-engine' ); ?> <?php echo esc_html( date_i18n( 'M j, Y g:ia', strtotime( $claim->customer_response_deadline ) ) ); ?></p>
				<?php endif; ?>
				<?php if ( $claim->resolved_at ) : ?>
					<p><?php esc_html_e( 'Resolved:', 'jq-marketplace-engine' ); ?> <?php echo esc_html( date_i18n( 'M j, Y g:ia', strtotime( $claim->resolved_at ) ) ); ?></p>
				<?php endif; ?>
			</div>

			<?php if ( $can_withdraw ) : ?>
				<div class="jqme-card" style="margin-top:16px;">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'jqme_withdraw_claim_' . $claim->id ); ?>
						<input type="hidden" name="action" value="jqme_withdraw_claim">
						<input type="hidden" name="claim_id" value="<?php echo esc_attr( $claim->id ); ?>">
						<button type="submit" class="jqme-btn jqme-btn--danger" style="width:100%;"
								onclick="return confirm('<?php esc_attr_e( 'Withdraw this claim?', 'jq-marketplace-engine' ); ?>');">
							<?php esc_html_e( 'Withdraw Claim', 'jq-marketplace-engine' ); ?>
						</button>
					</form>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
