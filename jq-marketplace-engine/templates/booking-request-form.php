<?php
/**
 * Template: Booking request form.
 *
 * Used by [jqme_booking_request] shortcode.
 * Expects ?listing_id= in the URL.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JQME\Listings\Listing;
use JQME\StatusEnums;
use JQME\Settings\Settings;

$listing_id = absint( $_GET['listing_id'] ?? 0 );
$listing    = $listing_id ? Listing::get( $listing_id ) : null;

if ( ! $listing || StatusEnums::LISTING_PUBLISHED !== $listing->status ) {
	echo '<div class="jqme-notice jqme-notice--error">' . esc_html__( 'Listing not found or not available.', 'jq-marketplace-engine' ) . '</div>';
	return;
}

$error  = sanitize_text_field( $_GET['jqme_error'] ?? '' );
$notice = sanitize_text_field( $_GET['jqme_notice'] ?? '' );

$type_label      = StatusEnums::listing_types()[ $listing->listing_type ] ?? $listing->listing_type;
$is_rental       = StatusEnums::TYPE_EQUIPMENT_RENTAL === $listing->listing_type;
$is_service      = StatusEnums::TYPE_SERVICE_BOOKING === $listing->listing_type;
$is_sale         = StatusEnums::TYPE_EQUIPMENT_SALE === $listing->listing_type;
$fulfillment_modes = StatusEnums::fulfillment_modes();

// Parse listing metadata for pricing.
$daily_rate   = floatval( $listing->daily_rate ?? 0 );
$weekly_rate  = floatval( $listing->weekly_rate ?? 0 );
$monthly_rate = floatval( $listing->monthly_rate ?? 0 );
$sale_price   = floatval( $listing->sale_price ?? 0 );
$hourly_rate  = floatval( $listing->hourly_rate ?? 0 );
$deposit_pct  = floatval( Settings::get( 'payments', 'deposit_percentage' ) );
?>

<div class="jqme-booking-form">
	<h2><?php printf( esc_html__( 'Request Booking: %s', 'jq-marketplace-engine' ), esc_html( $listing->title ) ); ?></h2>
	<p class="jqme-meta"><span class="jqme-badge"><?php echo esc_html( $type_label ); ?></span></p>

	<?php if ( $error ) : ?>
		<div class="jqme-notice jqme-notice--error">
			<?php echo esc_html( ucwords( str_replace( '_', ' ', $error ) ) ); ?>
		</div>
	<?php endif; ?>
	<?php if ( 'booking_submitted' === $notice ) : ?>
		<div class="jqme-notice jqme-notice--success">
			<?php esc_html_e( 'Your booking request has been submitted! You will be notified when the provider responds.', 'jq-marketplace-engine' ); ?>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="jqme-form">
		<?php wp_nonce_field( 'jqme_booking_request' ); ?>
		<input type="hidden" name="action" value="jqme_booking_request">
		<input type="hidden" name="listing_id" value="<?php echo esc_attr( $listing->id ); ?>">

		<?php if ( $is_rental || $is_service ) : ?>
			<!-- Date range -->
			<div class="jqme-form-row" style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
				<div class="jqme-field">
					<label for="date_start"><?php esc_html_e( 'Start Date', 'jq-marketplace-engine' ); ?> <span class="required">*</span></label>
					<input type="date" id="date_start" name="date_start" required min="<?php echo esc_attr( date( 'Y-m-d', strtotime( '+1 day' ) ) ); ?>">
				</div>
				<div class="jqme-field">
					<label for="date_end">
						<?php echo $is_rental ? esc_html__( 'Return Date', 'jq-marketplace-engine' ) : esc_html__( 'End Date', 'jq-marketplace-engine' ); ?>
						<span class="required">*</span>
					</label>
					<input type="date" id="date_end" name="date_end" required>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $is_rental ) : ?>
			<!-- Pricing summary for rentals -->
			<div class="jqme-pricing-info" style="background:#f0f6fc; padding:12px; border-radius:6px; margin:12px 0;">
				<h4 style="margin:0 0 8px;"><?php esc_html_e( 'Rental Rates', 'jq-marketplace-engine' ); ?></h4>
				<?php if ( $daily_rate > 0 ) : ?>
					<p style="margin:2px 0;"><?php printf( esc_html__( 'Daily: $%s', 'jq-marketplace-engine' ), number_format( $daily_rate, 2 ) ); ?></p>
				<?php endif; ?>
				<?php if ( $weekly_rate > 0 ) : ?>
					<p style="margin:2px 0;"><?php printf( esc_html__( 'Weekly: $%s', 'jq-marketplace-engine' ), number_format( $weekly_rate, 2 ) ); ?></p>
				<?php endif; ?>
				<?php if ( $monthly_rate > 0 ) : ?>
					<p style="margin:2px 0;"><?php printf( esc_html__( 'Monthly: $%s', 'jq-marketplace-engine' ), number_format( $monthly_rate, 2 ) ); ?></p>
				<?php endif; ?>
				<?php if ( $deposit_pct > 0 ) : ?>
					<p style="margin:8px 0 0; font-size:0.9em; color:#666;"><?php printf( esc_html__( 'Security deposit: %s%% of rental total (authorized, not charged)', 'jq-marketplace-engine' ), $deposit_pct ); ?></p>
				<?php endif; ?>
			</div>

			<input type="hidden" name="subtotal" id="rental_subtotal" value="0">
			<input type="hidden" name="deposit_amount" id="deposit_amount" value="0">
		<?php endif; ?>

		<?php if ( $is_sale ) : ?>
			<div class="jqme-pricing-info" style="background:#f0f6fc; padding:12px; border-radius:6px; margin:12px 0;">
				<h4 style="margin:0;"><?php printf( esc_html__( 'Price: $%s', 'jq-marketplace-engine' ), number_format( $sale_price, 2 ) ); ?></h4>
			</div>
			<input type="hidden" name="subtotal" value="<?php echo esc_attr( $sale_price ); ?>">
		<?php endif; ?>

		<?php if ( $is_service ) : ?>
			<input type="hidden" name="subtotal" id="service_subtotal" value="0">
			<?php if ( $hourly_rate > 0 ) : ?>
				<div class="jqme-pricing-info" style="background:#f0f6fc; padding:12px; border-radius:6px; margin:12px 0;">
					<p style="margin:0;"><?php printf( esc_html__( 'Hourly Rate: $%s', 'jq-marketplace-engine' ), number_format( $hourly_rate, 2 ) ); ?></p>
				</div>
			<?php endif; ?>
		<?php endif; ?>

		<!-- Fulfillment mode -->
		<div class="jqme-field">
			<label for="fulfillment_mode"><?php esc_html_e( 'Fulfillment Method', 'jq-marketplace-engine' ); ?> <span class="required">*</span></label>
			<select id="fulfillment_mode" name="fulfillment_mode" required>
				<?php
				$available_modes = json_decode( $listing->fulfillment_modes ?? '[]', true ) ?: [ 'pickup' ];
				foreach ( $available_modes as $mode ) : ?>
					<option value="<?php echo esc_attr( $mode ); ?>">
						<?php echo esc_html( $fulfillment_modes[ $mode ] ?? ucfirst( $mode ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<!-- Delivery address (shown conditionally) -->
		<div class="jqme-field" id="delivery_address_field" style="display:none;">
			<label for="delivery_address"><?php esc_html_e( 'Delivery Address', 'jq-marketplace-engine' ); ?></label>
			<textarea id="delivery_address" name="delivery_address" rows="3" placeholder="<?php esc_attr_e( 'Street address, city, state, zip', 'jq-marketplace-engine' ); ?>"></textarea>
		</div>

		<!-- Customer notes -->
		<div class="jqme-field">
			<label for="customer_notes"><?php esc_html_e( 'Notes for Provider', 'jq-marketplace-engine' ); ?></label>
			<textarea id="customer_notes" name="customer_notes" rows="3" placeholder="<?php esc_attr_e( 'Any special requests or questions...', 'jq-marketplace-engine' ); ?>"></textarea>
		</div>

		<!-- Terms -->
		<div class="jqme-field">
			<label>
				<input type="checkbox" name="platform_terms_accepted" value="1" required>
				<?php esc_html_e( 'I agree to the marketplace terms of service.', 'jq-marketplace-engine' ); ?> <span class="required">*</span>
			</label>
		</div>

		<button type="submit" class="jqme-btn jqme-btn--primary">
			<?php esc_html_e( 'Submit Booking Request', 'jq-marketplace-engine' ); ?>
		</button>
	</form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	var fulfillmentSelect = document.getElementById('fulfillment_mode');
	var deliveryField = document.getElementById('delivery_address_field');

	if (fulfillmentSelect && deliveryField) {
		fulfillmentSelect.addEventListener('change', function() {
			deliveryField.style.display = (this.value === 'delivery' || this.value === 'shipping') ? '' : 'none';
		});
		fulfillmentSelect.dispatchEvent(new Event('change'));
	}
});
</script>
