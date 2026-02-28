<?php
/**
 * Template: Condition report submission form.
 *
 * Used by [jqme_condition_report] shortcode.
 * Expects ?booking_id= and ?report_type= in URL.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JQME\Bookings\Booking;
use JQME\ConditionReports\ConditionReport;
use JQME\StatusEnums;

$booking_id  = absint( $_GET['booking_id'] ?? 0 );
$report_type = sanitize_text_field( $_GET['report_type'] ?? '' );
$booking     = $booking_id ? Booking::get( $booking_id ) : null;

if ( ! $booking || ! in_array( $report_type, [ 'pre_handoff', 'return' ], true ) ) {
	echo '<div class="jqme-notice jqme-notice--error">' . esc_html__( 'Invalid booking or report type.', 'jq-marketplace-engine' ) . '</div>';
	return;
}

$notice    = sanitize_text_field( $_GET['jqme_notice'] ?? '' );
$error     = sanitize_text_field( $_GET['jqme_error'] ?? '' );
$checklist = ConditionReport::get_checklist_template();
$type_label = 'pre_handoff' === $report_type
	? __( 'Pre-Handoff Inspection', 'jq-marketplace-engine' )
	: __( 'Return Inspection', 'jq-marketplace-engine' );

$grades = [
	'excellent' => __( 'Excellent — Like new, no visible wear', 'jq-marketplace-engine' ),
	'good'      => __( 'Good — Minor cosmetic wear, fully functional', 'jq-marketplace-engine' ),
	'fair'      => __( 'Fair — Visible wear, operational', 'jq-marketplace-engine' ),
	'poor'      => __( 'Poor — Significant wear, may need repair', 'jq-marketplace-engine' ),
	'damaged'   => __( 'Damaged — Non-functional or major damage', 'jq-marketplace-engine' ),
];
?>

<div class="jqme-condition-report">
	<h2><?php echo esc_html( $type_label ); ?></h2>
	<p><?php printf( esc_html__( 'Booking: %s', 'jq-marketplace-engine' ), '<strong>' . esc_html( $booking->booking_number ) . '</strong>' ); ?></p>

	<?php if ( 'report_submitted' === $notice ) : ?>
		<div class="jqme-notice jqme-notice--success"><?php esc_html_e( 'Condition report submitted successfully.', 'jq-marketplace-engine' ); ?></div>
	<?php endif; ?>
	<?php if ( $error ) : ?>
		<div class="jqme-notice jqme-notice--error"><?php echo esc_html( ucwords( str_replace( '_', ' ', $error ) ) ); ?></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="jqme-form">
		<?php wp_nonce_field( 'jqme_submit_condition_report' ); ?>
		<input type="hidden" name="action" value="jqme_submit_condition_report">
		<input type="hidden" name="booking_id" value="<?php echo esc_attr( $booking_id ); ?>">
		<input type="hidden" name="report_type" value="<?php echo esc_attr( $report_type ); ?>">

		<!-- Overall condition grade -->
		<div class="jqme-field">
			<label for="condition_grade"><?php esc_html_e( 'Overall Condition Grade', 'jq-marketplace-engine' ); ?> <span class="required">*</span></label>
			<select id="condition_grade" name="condition_grade" required>
				<option value=""><?php esc_html_e( '— Select —', 'jq-marketplace-engine' ); ?></option>
				<?php foreach ( $grades as $gk => $gl ) : ?>
					<option value="<?php echo esc_attr( $gk ); ?>"><?php echo esc_html( $gl ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<!-- Checklist -->
		<div class="jqme-field">
			<label><?php esc_html_e( 'Inspection Checklist', 'jq-marketplace-engine' ); ?></label>
			<?php foreach ( $checklist as $key => $label ) : ?>
				<div style="margin-bottom:8px; padding:8px; background:#f9fafb; border-radius:4px;">
					<label style="font-weight:400;"><?php echo esc_html( $label ); ?></label>
					<select name="checklist[<?php echo esc_attr( $key ); ?>]" style="width:100%; margin-top:4px;">
						<option value="pass"><?php esc_html_e( 'Pass', 'jq-marketplace-engine' ); ?></option>
						<option value="minor_issue"><?php esc_html_e( 'Minor Issue', 'jq-marketplace-engine' ); ?></option>
						<option value="major_issue"><?php esc_html_e( 'Major Issue', 'jq-marketplace-engine' ); ?></option>
						<option value="na"><?php esc_html_e( 'N/A', 'jq-marketplace-engine' ); ?></option>
					</select>
				</div>
			<?php endforeach; ?>
		</div>

		<!-- Photos -->
		<div class="jqme-field">
			<label for="condition_photos"><?php esc_html_e( 'Photos (recommended)', 'jq-marketplace-engine' ); ?></label>
			<input type="file" id="condition_photos" name="condition_photos[]" multiple accept="image/*">
			<p style="font-size:12px; color:#666;"><?php esc_html_e( 'Upload photos documenting the current condition. Multiple files allowed.', 'jq-marketplace-engine' ); ?></p>
		</div>

		<!-- Notes -->
		<div class="jqme-field">
			<label for="notes"><?php esc_html_e( 'Additional Notes', 'jq-marketplace-engine' ); ?></label>
			<textarea id="notes" name="notes" rows="4" placeholder="<?php esc_attr_e( 'Note any specific issues, damage, missing parts...', 'jq-marketplace-engine' ); ?>"></textarea>
		</div>

		<button type="submit" class="jqme-btn jqme-btn--primary">
			<?php esc_html_e( 'Submit Condition Report', 'jq-marketplace-engine' ); ?>
		</button>
	</form>
</div>
