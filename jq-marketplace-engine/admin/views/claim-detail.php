<?php
/**
 * Admin claim detail page — full claim info with evidence and admin actions.
 *
 * Loaded by claims.php when action=view.
 * Expects $claim to be set by the parent file.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JQME\Claims\Claim;
use JQME\StatusEnums;
use JQME\Core;

$claim_statuses = StatusEnums::claim_statuses();
$status_label   = $claim_statuses[ $claim->status ] ?? $claim->status;

$booking = \JQME\Bookings\Booking::get( $claim->booking_id );
$filer   = get_userdata( $claim->filed_by );

// Evidence.
$evidence = Claim::get_evidence( $claim->id );

// Condition reports for this booking.
$condition_reports = \JQME\ConditionReports\ConditionReport::get_for_booking( $claim->booking_id );

$badge = match ( true ) {
	str_contains( $claim->status, 'settled' ),
	str_contains( $claim->status, 'capture' ) => 'jqme-badge--success',
	str_contains( $claim->status, 'submitted' ),
	str_contains( $claim->status, 'awaiting' ) => 'jqme-badge--warning',
	str_contains( $claim->status, 'review' )   => 'jqme-badge--info',
	str_contains( $claim->status, 'denied' )   => 'jqme-badge--danger',
	default                                     => 'jqme-badge--muted',
};

$notice = sanitize_text_field( $_GET['jqme_notice'] ?? '' );

// Is claim resolvable?
$open_statuses = [
	StatusEnums::CLAIM_SUBMITTED,
	StatusEnums::CLAIM_AWAITING_CUSTOMER,
	StatusEnums::CLAIM_AWAITING_PROVIDER,
	StatusEnums::CLAIM_EVIDENCE_UNDER_REVIEW,
];
$is_open = in_array( $claim->status, $open_statuses, true );
?>

<div class="wrap">
	<h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-claims' ) ); ?>">&larr; <?php esc_html_e( 'Claims', 'jq-marketplace-engine' ); ?></a>
		&mdash; <?php echo esc_html( $claim->claim_number ); ?>
	</h1>

	<?php if ( $notice ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( ucwords( str_replace( '_', ' ', $notice ) ) ); ?></p>
		</div>
	<?php endif; ?>

	<div style="display:grid; grid-template-columns:2fr 1fr; gap:20px; margin-top:20px;">
		<!-- Left column -->
		<div>
			<!-- Claim overview -->
			<div class="card" style="max-width:none;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Claim Details', 'jq-marketplace-engine' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Claim Number', 'jq-marketplace-engine' ); ?></th>
						<td><strong><?php echo esc_html( $claim->claim_number ); ?></strong></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Status', 'jq-marketplace-engine' ); ?></th>
						<td><span class="jqme-badge <?php echo esc_attr( $badge ); ?>"><?php echo esc_html( $status_label ); ?></span></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Type', 'jq-marketplace-engine' ); ?></th>
						<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $claim->claim_type ) ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Filed By', 'jq-marketplace-engine' ); ?></th>
						<td>
							<?php echo $filer ? esc_html( $filer->display_name ) : '—'; ?>
							<small>(<?php echo esc_html( ucfirst( $claim->filed_by_role ) ); ?>)</small>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Booking', 'jq-marketplace-engine' ); ?></th>
						<td>
							<?php if ( $booking ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-bookings&action=view&id=' . $booking->id ) ); ?>">
									<?php echo esc_html( $booking->booking_number ); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Description', 'jq-marketplace-engine' ); ?></th>
						<td><?php echo esc_html( $claim->description ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Amount Requested', 'jq-marketplace-engine' ); ?></th>
						<td><strong>$<?php echo esc_html( number_format( (float) $claim->amount_requested, 2 ) ); ?></strong></td>
					</tr>
					<?php if ( (float) $claim->amount_settled > 0 ) : ?>
						<tr>
							<th><?php esc_html_e( 'Amount Settled', 'jq-marketplace-engine' ); ?></th>
							<td style="color:#00a32a;"><strong>$<?php echo esc_html( number_format( (float) $claim->amount_settled, 2 ) ); ?></strong></td>
						</tr>
					<?php endif; ?>
					<?php if ( $claim->resolution_notes ) : ?>
						<tr>
							<th><?php esc_html_e( 'Resolution Notes', 'jq-marketplace-engine' ); ?></th>
							<td><?php echo esc_html( $claim->resolution_notes ); ?></td>
						</tr>
					<?php endif; ?>
				</table>
			</div>

			<!-- Evidence -->
			<div class="card" style="max-width:none; margin-top:15px;">
				<h2 style="margin-top:0;"><?php printf( esc_html__( 'Evidence (%d)', 'jq-marketplace-engine' ), count( $evidence ) ); ?></h2>
				<?php if ( empty( $evidence ) ) : ?>
					<p><?php esc_html_e( 'No evidence submitted yet.', 'jq-marketplace-engine' ); ?></p>
				<?php else : ?>
					<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:12px;">
						<?php foreach ( $evidence as $ev ) : ?>
							<div style="border:1px solid #e5e7eb; border-radius:6px; padding:10px;">
								<?php if ( 'photo' === $ev->evidence_type && $ev->file_url ) : ?>
									<a href="<?php echo esc_url( $ev->file_url ); ?>" target="_blank">
										<img src="<?php echo esc_url( $ev->file_url ); ?>" style="width:100%; height:140px; object-fit:cover; border-radius:4px;">
									</a>
								<?php else : ?>
									<p><a href="<?php echo esc_url( $ev->file_url ); ?>" target="_blank"><?php echo esc_html( $ev->evidence_type ); ?></a></p>
								<?php endif; ?>
								<p style="font-size:12px; margin:6px 0 0;">
									<strong><?php echo esc_html( $ev->submitter_name ?? '—' ); ?></strong><br>
									<?php echo esc_html( $ev->description ); ?><br>
									<small><?php echo esc_html( date_i18n( 'M j, g:ia', strtotime( $ev->created_at ) ) ); ?></small>
								</p>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<!-- Condition Reports -->
			<?php if ( ! empty( $condition_reports ) ) : ?>
				<div class="card" style="max-width:none; margin-top:15px;">
					<h2 style="margin-top:0;"><?php esc_html_e( 'Condition Reports', 'jq-marketplace-engine' ); ?></h2>
					<?php
					$cr_statuses = StatusEnums::condition_report_statuses();
					foreach ( $condition_reports as $cr ) :
						$submitter = get_userdata( $cr->submitted_by );
						$photos    = json_decode( $cr->photo_urls ?? '[]', true ) ?: [];
						$checklist = json_decode( $cr->checklist_data ?? '{}', true ) ?: [];
					?>
						<div style="border:1px solid #e5e7eb; border-radius:6px; padding:12px; margin-bottom:12px; <?php echo $cr->mismatch_flagged ? 'border-color:#d63638;' : ''; ?>">
							<div style="display:flex; justify-content:space-between;">
								<strong><?php echo esc_html( ucwords( str_replace( '_', ' ', $cr->report_type ) ) ); ?></strong>
								<span>
									<?php echo esc_html( $cr_statuses[ $cr->status ] ?? $cr->status ); ?>
									<?php if ( $cr->mismatch_flagged ) : ?>
										<span style="color:#d63638; font-weight:bold;">MISMATCH</span>
									<?php endif; ?>
								</span>
							</div>
							<p style="margin:4px 0;">
								<?php esc_html_e( 'Grade:', 'jq-marketplace-engine' ); ?> <strong><?php echo esc_html( ucfirst( $cr->condition_grade ) ); ?></strong>
								&nbsp;|&nbsp;
								<?php esc_html_e( 'By:', 'jq-marketplace-engine' ); ?> <?php echo $submitter ? esc_html( $submitter->display_name ) : '—'; ?>
								(<?php echo esc_html( ucfirst( $cr->submitted_by_role ) ); ?>)
								&nbsp;|&nbsp;
								<?php echo esc_html( date_i18n( 'M j, Y g:ia', strtotime( $cr->created_at ) ) ); ?>
							</p>
							<?php if ( $cr->notes ) : ?>
								<p style="font-size:13px;"><?php echo esc_html( $cr->notes ); ?></p>
							<?php endif; ?>
							<?php if ( $cr->mismatch_notes ) : ?>
								<p style="font-size:13px; color:#d63638;"><?php echo esc_html( $cr->mismatch_notes ); ?></p>
							<?php endif; ?>
							<?php if ( ! empty( $photos ) ) : ?>
								<div style="display:flex; gap:8px; margin-top:8px;">
									<?php foreach ( array_slice( $photos, 0, 6 ) as $url ) : ?>
										<a href="<?php echo esc_url( $url ); ?>" target="_blank">
											<img src="<?php echo esc_url( $url ); ?>" style="width:80px; height:60px; object-fit:cover; border-radius:4px;">
										</a>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<!-- Right column: Actions -->
		<div>
			<!-- Deadlines -->
			<div class="card" style="max-width:none;">
				<h3 style="margin-top:0;"><?php esc_html_e( 'Deadlines', 'jq-marketplace-engine' ); ?></h3>
				<table style="width:100%; font-size:12px;">
					<tr>
						<td><?php esc_html_e( 'Filed', 'jq-marketplace-engine' ); ?></td>
						<td><?php echo esc_html( date_i18n( 'M j, Y g:ia', strtotime( $claim->created_at ) ) ); ?></td>
					</tr>
					<?php if ( $claim->customer_response_deadline ) : ?>
						<tr>
							<td><?php esc_html_e( 'Customer Deadline', 'jq-marketplace-engine' ); ?></td>
							<td><?php echo esc_html( date_i18n( 'M j, Y g:ia', strtotime( $claim->customer_response_deadline ) ) ); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( $claim->auto_close_at ) : ?>
						<tr>
							<td><?php esc_html_e( 'Auto-Close', 'jq-marketplace-engine' ); ?></td>
							<td><?php echo esc_html( date_i18n( 'M j, Y g:ia', strtotime( $claim->auto_close_at ) ) ); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( $claim->resolved_at ) : ?>
						<tr>
							<td><?php esc_html_e( 'Resolved', 'jq-marketplace-engine' ); ?></td>
							<td><?php echo esc_html( date_i18n( 'M j, Y g:ia', strtotime( $claim->resolved_at ) ) ); ?></td>
						</tr>
					<?php endif; ?>
				</table>
			</div>

			<!-- Admin Actions -->
			<?php if ( $is_open ) : ?>
				<div class="card" style="max-width:none; margin-top:15px;">
					<h3 style="margin-top:0;"><?php esc_html_e( 'Settle Claim', 'jq-marketplace-engine' ); ?></h3>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'jqme_claim_settle_' . $claim->id ); ?>
						<input type="hidden" name="action" value="jqme_claim_settle">
						<input type="hidden" name="claim_id" value="<?php echo esc_attr( $claim->id ); ?>">
						<p>
							<label><?php esc_html_e( 'Settlement Amount', 'jq-marketplace-engine' ); ?></label><br>
							<input type="number" name="settled_amount" step="0.01" min="0"
								   value="<?php echo esc_attr( $claim->amount_requested ); ?>"
								   style="width:100%;">
						</p>
						<p>
							<label><?php esc_html_e( 'Resolution Notes', 'jq-marketplace-engine' ); ?></label><br>
							<textarea name="resolution_notes" rows="3" style="width:100%;"></textarea>
						</p>
						<button type="submit" class="button button-primary" style="width:100%;">
							<?php esc_html_e( 'Settle Claim', 'jq-marketplace-engine' ); ?>
						</button>
					</form>

					<hr>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:8px;">
						<?php wp_nonce_field( 'jqme_claim_deny_' . $claim->id ); ?>
						<input type="hidden" name="action" value="jqme_claim_deny">
						<input type="hidden" name="claim_id" value="<?php echo esc_attr( $claim->id ); ?>">
						<textarea name="resolution_notes" rows="2" style="width:100%; margin-bottom:6px;" placeholder="<?php esc_attr_e( 'Denial reason...', 'jq-marketplace-engine' ); ?>"></textarea>
						<button type="submit" class="button" style="width:100%;">
							<?php esc_html_e( 'Deny Claim', 'jq-marketplace-engine' ); ?>
						</button>
					</form>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'jqme_claim_close_' . $claim->id ); ?>
						<input type="hidden" name="action" value="jqme_claim_close">
						<input type="hidden" name="claim_id" value="<?php echo esc_attr( $claim->id ); ?>">
						<button type="submit" class="button" style="width:100%; color:#d63638;">
							<?php esc_html_e( 'Close Without Action', 'jq-marketplace-engine' ); ?>
						</button>
					</form>
				</div>
			<?php endif; ?>

			<!-- Manual Status Override -->
			<div class="card" style="max-width:none; margin-top:15px;">
				<details>
					<summary style="cursor:pointer; font-weight:600;"><?php esc_html_e( 'Manual Status Override', 'jq-marketplace-engine' ); ?></summary>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px;">
						<?php wp_nonce_field( 'jqme_claim_set_status_' . $claim->id ); ?>
						<input type="hidden" name="action" value="jqme_claim_set_status">
						<input type="hidden" name="claim_id" value="<?php echo esc_attr( $claim->id ); ?>">
						<select name="new_status" style="width:100%; margin-bottom:8px;">
							<?php foreach ( $claim_statuses as $sk => $sl ) : ?>
								<option value="<?php echo esc_attr( $sk ); ?>" <?php selected( $claim->status, $sk ); ?>>
									<?php echo esc_html( $sl ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<textarea name="status_notes" placeholder="<?php esc_attr_e( 'Notes...', 'jq-marketplace-engine' ); ?>" style="width:100%; margin-bottom:8px;" rows="2"></textarea>
						<button type="submit" class="button" style="width:100%;">
							<?php esc_html_e( 'Update Status', 'jq-marketplace-engine' ); ?>
						</button>
					</form>
				</details>
			</div>
		</div>
	</div>
</div>
