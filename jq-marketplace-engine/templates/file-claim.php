<?php
/**
 * Template: File a claim form.
 *
 * Used by [jqme_file_claim] shortcode.
 * Expects ?booking_id= in URL.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JQME\Bookings\Booking;
use JQME\StatusEnums;

$booking_id = absint( $_GET['booking_id'] ?? 0 );
$booking    = $booking_id ? Booking::get( $booking_id ) : null;

if ( ! $booking ) {
	echo '<div class="jqme-notice jqme-notice--error">' . esc_html__( 'Booking not found.', 'jq-marketplace-engine' ) . '</div>';
	return;
}

$notice = sanitize_text_field( $_GET['jqme_notice'] ?? '' );
$error  = sanitize_text_field( $_GET['jqme_error'] ?? '' );

$claim_types = [
	'damage'            => __( 'Equipment Damage', 'jq-marketplace-engine' ),
	'missing_parts'     => __( 'Missing Parts / Accessories', 'jq-marketplace-engine' ),
	'not_as_described'  => __( 'Not As Described', 'jq-marketplace-engine' ),
	'service_issue'     => __( 'Service Quality Issue', 'jq-marketplace-engine' ),
	'late_return'       => __( 'Late Return', 'jq-marketplace-engine' ),
	'other'             => __( 'Other', 'jq-marketplace-engine' ),
];
?>

<div class="jqme-file-claim">
	<h2><?php esc_html_e( 'File a Claim', 'jq-marketplace-engine' ); ?></h2>
	<p><?php printf( esc_html__( 'Booking: %s', 'jq-marketplace-engine' ), '<strong>' . esc_html( $booking->booking_number ) . '</strong>' ); ?></p>

	<?php if ( 'claim_filed' === $notice ) : ?>
		<div class="jqme-notice jqme-notice--success"><?php esc_html_e( 'Your claim has been filed. Both parties will be notified.', 'jq-marketplace-engine' ); ?></div>
	<?php endif; ?>
	<?php if ( $error ) : ?>
		<div class="jqme-notice jqme-notice--error"><?php echo esc_html( ucwords( str_replace( '_', ' ', $error ) ) ); ?></div>
	<?php endif; ?>

	<div class="jqme-card" style="background:#fff8e5; border-color:#ffc107; margin-bottom:20px;">
		<p style="margin:0;"><strong><?php esc_html_e( 'Important:', 'jq-marketplace-engine' ); ?></strong>
		<?php esc_html_e( 'The marketplace facilitates communication between parties. We do not act as a judge, insurer, or guarantor. Filing a claim initiates a resolution process between you and the other party.', 'jq-marketplace-engine' ); ?></p>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="jqme-form">
		<?php wp_nonce_field( 'jqme_file_claim' ); ?>
		<input type="hidden" name="action" value="jqme_file_claim">
		<input type="hidden" name="booking_id" value="<?php echo esc_attr( $booking_id ); ?>">

		<div class="jqme-field">
			<label for="claim_type"><?php esc_html_e( 'Claim Type', 'jq-marketplace-engine' ); ?> <span class="required">*</span></label>
			<select id="claim_type" name="claim_type" required>
				<?php foreach ( $claim_types as $ck => $cl ) : ?>
					<option value="<?php echo esc_attr( $ck ); ?>"><?php echo esc_html( $cl ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="jqme-field">
			<label for="description"><?php esc_html_e( 'Description', 'jq-marketplace-engine' ); ?> <span class="required">*</span></label>
			<textarea id="description" name="description" rows="5" required
					  placeholder="<?php esc_attr_e( 'Describe the issue in detail. Include what happened, when, and any relevant context...', 'jq-marketplace-engine' ); ?>"></textarea>
		</div>

		<div class="jqme-field">
			<label for="amount_requested"><?php esc_html_e( 'Amount Requested ($)', 'jq-marketplace-engine' ); ?></label>
			<input type="number" id="amount_requested" name="amount_requested" step="0.01" min="0"
				   max="<?php echo esc_attr( $booking->deposit_amount ?: $booking->total_amount ); ?>"
				   placeholder="0.00">
			<p style="font-size:12px; color:#666;">
				<?php if ( (float) $booking->deposit_amount > 0 ) : ?>
					<?php printf( esc_html__( 'Security deposit available: $%s', 'jq-marketplace-engine' ), number_format( (float) $booking->deposit_amount, 2 ) ); ?>
				<?php endif; ?>
			</p>
		</div>

		<div class="jqme-field">
			<label for="evidence_files"><?php esc_html_e( 'Evidence (photos, documents)', 'jq-marketplace-engine' ); ?></label>
			<input type="file" id="evidence_files" name="evidence_files[]" multiple accept="image/*,.pdf,.doc,.docx">
			<p style="font-size:12px; color:#666;"><?php esc_html_e( 'Upload photos or documents supporting your claim. Multiple files allowed.', 'jq-marketplace-engine' ); ?></p>
		</div>

		<button type="submit" class="jqme-btn jqme-btn--primary">
			<?php esc_html_e( 'Submit Claim', 'jq-marketplace-engine' ); ?>
		</button>
	</form>
</div>
