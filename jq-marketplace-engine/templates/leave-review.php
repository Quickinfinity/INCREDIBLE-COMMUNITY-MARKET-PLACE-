<?php
/**
 * Template: Leave a review form.
 *
 * Used by [jqme_leave_review] shortcode.
 * Expects ?review_id= in URL.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JQME\Reviews\Review;
use JQME\StatusEnums;

$review_id = absint( $_GET['review_id'] ?? 0 );
$review    = $review_id ? Review::get( $review_id ) : null;

if ( ! $review || (int) $review->reviewer_id !== get_current_user_id() ) {
	echo '<div class="jqme-notice jqme-notice--error">' . esc_html__( 'Review not found or not assigned to you.', 'jq-marketplace-engine' ) . '</div>';
	return;
}

if ( $review->overall_rating > 0 ) {
	echo '<div class="jqme-notice jqme-notice--info">' . esc_html__( 'You have already submitted this review.', 'jq-marketplace-engine' ) . '</div>';
	return;
}

$notice  = sanitize_text_field( $_GET['jqme_notice'] ?? '' );
$error   = sanitize_text_field( $_GET['jqme_error'] ?? '' );
$booking = \JQME\Bookings\Booking::get( $review->booking_id );

$is_customer = 'customer' === $review->reviewer_role;
$reviewee    = get_userdata( $review->reviewee_id );

// Rating categories vary by type.
$categories = $is_customer
	? [
		'equipment_quality' => __( 'Equipment Quality / Service Quality', 'jq-marketplace-engine' ),
		'communication'     => __( 'Communication', 'jq-marketplace-engine' ),
		'punctuality'       => __( 'Punctuality / Timeliness', 'jq-marketplace-engine' ),
		'value'             => __( 'Value for Money', 'jq-marketplace-engine' ),
	]
	: [
		'care_of_equipment' => __( 'Care of Equipment', 'jq-marketplace-engine' ),
		'communication'     => __( 'Communication', 'jq-marketplace-engine' ),
		'punctuality'       => __( 'Punctuality', 'jq-marketplace-engine' ),
		'professionalism'   => __( 'Professionalism', 'jq-marketplace-engine' ),
	];
?>

<div class="jqme-leave-review">
	<h2>
		<?php if ( $is_customer ) : ?>
			<?php esc_html_e( 'Review Your Experience', 'jq-marketplace-engine' ); ?>
		<?php else : ?>
			<?php esc_html_e( 'Review the Customer', 'jq-marketplace-engine' ); ?>
		<?php endif; ?>
	</h2>

	<?php if ( $booking ) : ?>
		<p><?php printf( esc_html__( 'Booking: %s', 'jq-marketplace-engine' ), '<strong>' . esc_html( $booking->booking_number ) . '</strong>' ); ?></p>
	<?php endif; ?>

	<?php if ( $reviewee ) : ?>
		<p><?php printf( esc_html__( 'Reviewing: %s', 'jq-marketplace-engine' ), '<strong>' . esc_html( $reviewee->display_name ) . '</strong>' ); ?></p>
	<?php endif; ?>

	<?php if ( $review->deadline_at ) : ?>
		<p style="font-size:13px; color:#856404;">
			<?php printf( esc_html__( 'Review deadline: %s', 'jq-marketplace-engine' ), date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $review->deadline_at ) ) ); ?>
		</p>
	<?php endif; ?>

	<div class="jqme-card" style="background:#f0f6fc; margin-bottom:16px;">
		<p style="margin:0; font-size:13px;"><?php esc_html_e( 'Reviews are hidden until both parties submit (or the deadline passes). This ensures honest, unbiased feedback.', 'jq-marketplace-engine' ); ?></p>
	</div>

	<?php if ( 'review_submitted' === $notice ) : ?>
		<div class="jqme-notice jqme-notice--success"><?php esc_html_e( 'Thank you! Your review has been submitted.', 'jq-marketplace-engine' ); ?></div>
	<?php endif; ?>
	<?php if ( $error ) : ?>
		<div class="jqme-notice jqme-notice--error"><?php echo esc_html( ucwords( str_replace( '_', ' ', $error ) ) ); ?></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="jqme-form">
		<?php wp_nonce_field( 'jqme_submit_review_' . $review->id ); ?>
		<input type="hidden" name="action" value="jqme_submit_review">
		<input type="hidden" name="review_id" value="<?php echo esc_attr( $review->id ); ?>">

		<!-- Overall rating -->
		<div class="jqme-field">
			<label><?php esc_html_e( 'Overall Rating', 'jq-marketplace-engine' ); ?> <span class="required">*</span></label>
			<div class="jqme-star-rating" style="font-size:28px; cursor:pointer;">
				<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
					<label style="cursor:pointer;">
						<input type="radio" name="overall_rating" value="<?php echo $i; ?>" required style="display:none;">
						<span class="jqme-star" data-value="<?php echo $i; ?>">&#9734;</span>
					</label>
				<?php endfor; ?>
			</div>
		</div>

		<!-- Category ratings -->
		<?php foreach ( $categories as $ck => $cl ) : ?>
			<div class="jqme-field">
				<label><?php echo esc_html( $cl ); ?></label>
				<select name="rating_categories[<?php echo esc_attr( $ck ); ?>]">
					<option value=""><?php esc_html_e( '— Rate —', 'jq-marketplace-engine' ); ?></option>
					<option value="5"><?php esc_html_e( '5 — Excellent', 'jq-marketplace-engine' ); ?></option>
					<option value="4"><?php esc_html_e( '4 — Good', 'jq-marketplace-engine' ); ?></option>
					<option value="3"><?php esc_html_e( '3 — Average', 'jq-marketplace-engine' ); ?></option>
					<option value="2"><?php esc_html_e( '2 — Below Average', 'jq-marketplace-engine' ); ?></option>
					<option value="1"><?php esc_html_e( '1 — Poor', 'jq-marketplace-engine' ); ?></option>
				</select>
			</div>
		<?php endforeach; ?>

		<div class="jqme-field">
			<label for="review_title"><?php esc_html_e( 'Review Title', 'jq-marketplace-engine' ); ?></label>
			<input type="text" id="review_title" name="review_title" placeholder="<?php esc_attr_e( 'Summarize your experience...', 'jq-marketplace-engine' ); ?>">
		</div>

		<div class="jqme-field">
			<label for="review_body"><?php esc_html_e( 'Your Review', 'jq-marketplace-engine' ); ?></label>
			<textarea id="review_body" name="review_body" rows="5" placeholder="<?php esc_attr_e( 'Share details about your experience...', 'jq-marketplace-engine' ); ?>"></textarea>
		</div>

		<button type="submit" class="jqme-btn jqme-btn--primary">
			<?php esc_html_e( 'Submit Review', 'jq-marketplace-engine' ); ?>
		</button>
	</form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	var stars = document.querySelectorAll('.jqme-star');
	stars.forEach(function(star) {
		star.addEventListener('click', function() {
			var val = parseInt(this.dataset.value);
			stars.forEach(function(s) {
				s.innerHTML = parseInt(s.dataset.value) <= val ? '&#9733;' : '&#9734;';
				s.style.color = parseInt(s.dataset.value) <= val ? '#f0ad4e' : '#ccc';
			});
		});
		star.addEventListener('mouseenter', function() {
			var val = parseInt(this.dataset.value);
			stars.forEach(function(s) {
				s.style.color = parseInt(s.dataset.value) <= val ? '#f0ad4e' : '#ccc';
			});
		});
	});
	document.querySelector('.jqme-star-rating').addEventListener('mouseleave', function() {
		var checked = document.querySelector('input[name="overall_rating"]:checked');
		var val = checked ? parseInt(checked.value) : 0;
		stars.forEach(function(s) {
			s.innerHTML = parseInt(s.dataset.value) <= val ? '&#9733;' : '&#9734;';
			s.style.color = parseInt(s.dataset.value) <= val ? '#f0ad4e' : '#ccc';
		});
	});
});
</script>
