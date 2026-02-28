<?php
/**
 * Template: Create listing form.
 *
 * Used by [jqme_create_listing] shortcode.
 * Dynamic fields show/hide based on selected listing type via JS.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JQME\StatusEnums;
use JQME\Providers\Provider;

$provider       = Provider::get_by_user( get_current_user_id() );
$allowed_types  = json_decode( $provider->allowed_listing_types ?? '[]', true ) ?: array_keys( StatusEnums::listing_types() );
$listing_types  = StatusEnums::listing_types();
$notice         = sanitize_text_field( $_GET['jqme_notice'] ?? '' );
?>

<div class="jqme-form-wrap">
	<?php if ( 'listing_created' === $notice ) : ?>
		<div class="jqme-notice jqme-notice--success">
			<p><?php esc_html_e( 'Listing created as a draft. Complete all fields, then submit for review.', 'jq-marketplace-engine' ); ?></p>
		</div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Create New Listing', 'jq-marketplace-engine' ); ?></h2>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="jqme-form" id="jqme-listing-form">
		<?php wp_nonce_field( 'jqme_create_listing' ); ?>
		<input type="hidden" name="action" value="jqme_create_listing">

		<!-- Listing Type Selector -->
		<p>
			<label for="listing_type"><?php esc_html_e( 'Listing Type', 'jq-marketplace-engine' ); ?> <span class="required">*</span></label>
			<select name="listing_type" id="listing_type" required>
				<option value=""><?php esc_html_e( '— Select Type —', 'jq-marketplace-engine' ); ?></option>
				<?php foreach ( $listing_types as $key => $label ) : ?>
					<?php if ( in_array( $key, $allowed_types, true ) ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endif; ?>
				<?php endforeach; ?>
			</select>
		</p>

		<!-- Common Fields -->
		<h3><?php esc_html_e( 'Basic Information', 'jq-marketplace-engine' ); ?></h3>

		<p>
			<label for="title"><?php esc_html_e( 'Title', 'jq-marketplace-engine' ); ?> <span class="required">*</span></label>
			<input type="text" name="title" id="title" required>
		</p>
		<p>
			<label for="description"><?php esc_html_e( 'Description', 'jq-marketplace-engine' ); ?></label>
			<textarea name="description" id="description" rows="6"></textarea>
		</p>
		<p>
			<label for="category"><?php esc_html_e( 'Category', 'jq-marketplace-engine' ); ?></label>
			<input type="text" name="category" id="category">
		</p>

		<!-- Equipment Fields (rental + sale) -->
		<div class="jqme-fields-equipment" style="display:none;">
			<h3><?php esc_html_e( 'Equipment Details', 'jq-marketplace-engine' ); ?></h3>
			<p>
				<label for="brand"><?php esc_html_e( 'Brand', 'jq-marketplace-engine' ); ?></label>
				<input type="text" name="brand" id="brand">
			</p>
			<p>
				<label for="model"><?php esc_html_e( 'Model', 'jq-marketplace-engine' ); ?></label>
				<input type="text" name="model" id="model">
			</p>
			<p>
				<label for="serial_number"><?php esc_html_e( 'Serial Number', 'jq-marketplace-engine' ); ?></label>
				<input type="text" name="serial_number" id="serial_number">
			</p>
			<p>
				<label for="condition_grade"><?php esc_html_e( 'Condition', 'jq-marketplace-engine' ); ?></label>
				<select name="condition_grade" id="condition_grade">
					<option value=""><?php esc_html_e( '— Select —', 'jq-marketplace-engine' ); ?></option>
					<option value="new"><?php esc_html_e( 'New', 'jq-marketplace-engine' ); ?></option>
					<option value="like_new"><?php esc_html_e( 'Like New', 'jq-marketplace-engine' ); ?></option>
					<option value="excellent"><?php esc_html_e( 'Excellent', 'jq-marketplace-engine' ); ?></option>
					<option value="good"><?php esc_html_e( 'Good', 'jq-marketplace-engine' ); ?></option>
					<option value="fair"><?php esc_html_e( 'Fair', 'jq-marketplace-engine' ); ?></option>
				</select>
			</p>
			<p>
				<label for="included_accessories"><?php esc_html_e( 'Included Accessories', 'jq-marketplace-engine' ); ?></label>
				<textarea name="included_accessories" id="included_accessories" rows="3"></textarea>
			</p>
			<p>
				<label for="safety_notes"><?php esc_html_e( 'Safety Notes', 'jq-marketplace-engine' ); ?></label>
				<textarea name="safety_notes" id="safety_notes" rows="3"></textarea>
			</p>
		</div>

		<!-- Rental Pricing Fields -->
		<div class="jqme-fields-rental" style="display:none;">
			<h3><?php esc_html_e( 'Rental Pricing', 'jq-marketplace-engine' ); ?></h3>
			<p>
				<label for="day_rate"><?php esc_html_e( 'Day Rate ($)', 'jq-marketplace-engine' ); ?></label>
				<input type="number" name="day_rate" id="day_rate" step="0.01" min="0">
			</p>
			<p>
				<label for="weekend_rate"><?php esc_html_e( 'Weekend Rate ($)', 'jq-marketplace-engine' ); ?></label>
				<input type="number" name="weekend_rate" id="weekend_rate" step="0.01" min="0">
			</p>
			<p>
				<label for="week_rate"><?php esc_html_e( 'Week Rate ($)', 'jq-marketplace-engine' ); ?></label>
				<input type="number" name="week_rate" id="week_rate" step="0.01" min="0">
			</p>
			<p>
				<label for="month_rate"><?php esc_html_e( 'Month Rate ($)', 'jq-marketplace-engine' ); ?></label>
				<input type="number" name="month_rate" id="month_rate" step="0.01" min="0">
			</p>
			<p>
				<label for="deposit_amount"><?php esc_html_e( 'Deposit ($)', 'jq-marketplace-engine' ); ?></label>
				<input type="number" name="deposit_amount" id="deposit_amount" step="0.01" min="0">
			</p>
			<p>
				<label for="min_rental_days"><?php esc_html_e( 'Minimum Rental (Days)', 'jq-marketplace-engine' ); ?></label>
				<input type="number" name="min_rental_days" id="min_rental_days" value="1" min="1">
			</p>
		</div>

		<!-- Sale Pricing Fields -->
		<div class="jqme-fields-sale" style="display:none;">
			<h3><?php esc_html_e( 'Sale Pricing', 'jq-marketplace-engine' ); ?></h3>
			<p>
				<label for="asking_price"><?php esc_html_e( 'Asking Price ($)', 'jq-marketplace-engine' ); ?></label>
				<input type="number" name="asking_price" id="asking_price" step="0.01" min="0">
			</p>
			<p>
				<label for="quantity"><?php esc_html_e( 'Quantity Available', 'jq-marketplace-engine' ); ?></label>
				<input type="number" name="quantity" id="quantity" value="1" min="1">
			</p>
			<p>
				<label>
					<input type="checkbox" name="offers_allowed" value="1">
					<?php esc_html_e( 'Accept offers', 'jq-marketplace-engine' ); ?>
				</label>
			</p>
		</div>

		<!-- Service Pricing Fields -->
		<div class="jqme-fields-service" style="display:none;">
			<h3><?php esc_html_e( 'Service Details', 'jq-marketplace-engine' ); ?></h3>
			<p>
				<label for="certification_level"><?php esc_html_e( 'Certification Level', 'jq-marketplace-engine' ); ?></label>
				<input type="text" name="certification_level" id="certification_level">
			</p>
			<p>
				<label for="hourly_rate"><?php esc_html_e( 'Hourly Rate ($)', 'jq-marketplace-engine' ); ?></label>
				<input type="number" name="hourly_rate" id="hourly_rate" step="0.01" min="0">
			</p>
			<p>
				<label for="half_day_rate"><?php esc_html_e( 'Half-Day Rate ($)', 'jq-marketplace-engine' ); ?></label>
				<input type="number" name="half_day_rate" id="half_day_rate" step="0.01" min="0">
			</p>
			<p>
				<label for="full_day_rate"><?php esc_html_e( 'Full-Day Rate ($)', 'jq-marketplace-engine' ); ?></label>
				<input type="number" name="full_day_rate" id="full_day_rate" step="0.01" min="0">
			</p>
			<p>
				<label for="deliverables"><?php esc_html_e( 'Deliverables', 'jq-marketplace-engine' ); ?></label>
				<textarea name="deliverables" id="deliverables" rows="3"></textarea>
			</p>
			<p>
				<label for="min_booking_hours"><?php esc_html_e( 'Minimum Booking (Hours)', 'jq-marketplace-engine' ); ?></label>
				<input type="number" name="min_booking_hours" id="min_booking_hours" value="1" step="0.5" min="0.5">
			</p>
		</div>

		<!-- Fulfillment -->
		<h3><?php esc_html_e( 'Fulfillment', 'jq-marketplace-engine' ); ?></h3>
		<p>
			<label><input type="checkbox" name="pickup_available" value="1" checked> <?php esc_html_e( 'Pickup available', 'jq-marketplace-engine' ); ?></label>
		</p>
		<p>
			<label><input type="checkbox" name="delivery_available" value="1"> <?php esc_html_e( 'Delivery available', 'jq-marketplace-engine' ); ?></label>
		</p>
		<div class="jqme-fields-service" style="display:none;">
			<p>
				<label><input type="checkbox" name="onsite_available" value="1"> <?php esc_html_e( 'On-site service', 'jq-marketplace-engine' ); ?></label>
			</p>
			<p>
				<label><input type="checkbox" name="virtual_available" value="1"> <?php esc_html_e( 'Virtual / remote', 'jq-marketplace-engine' ); ?></label>
			</p>
		</div>
		<div class="jqme-fields-sale" style="display:none;">
			<p>
				<label><input type="checkbox" name="shipping_available" value="1"> <?php esc_html_e( 'Shipping available', 'jq-marketplace-engine' ); ?></label>
			</p>
		</div>

		<!-- Photos -->
		<h3><?php esc_html_e( 'Photos', 'jq-marketplace-engine' ); ?></h3>
		<p>
			<label for="listing_images"><?php esc_html_e( 'Upload images', 'jq-marketplace-engine' ); ?></label>
			<input type="file" name="listing_images[]" id="listing_images" multiple accept="image/*">
		</p>

		<p>
			<button type="submit" class="jqme-btn jqme-btn--primary"><?php esc_html_e( 'Save as Draft', 'jq-marketplace-engine' ); ?></button>
		</p>
	</form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	var typeSelect = document.getElementById('listing_type');
	if (!typeSelect) return;

	typeSelect.addEventListener('change', function() {
		var type = this.value;
		// Hide all type-specific sections.
		document.querySelectorAll('.jqme-fields-equipment, .jqme-fields-rental, .jqme-fields-sale, .jqme-fields-service').forEach(function(el) {
			el.style.display = 'none';
		});
		// Show relevant sections.
		if (type === 'equipment_rental') {
			document.querySelectorAll('.jqme-fields-equipment, .jqme-fields-rental').forEach(function(el) { el.style.display = ''; });
		} else if (type === 'equipment_sale') {
			document.querySelectorAll('.jqme-fields-equipment, .jqme-fields-sale').forEach(function(el) { el.style.display = ''; });
		} else if (type === 'service_booking') {
			document.querySelectorAll('.jqme-fields-service').forEach(function(el) { el.style.display = ''; });
		}
	});
});
</script>
