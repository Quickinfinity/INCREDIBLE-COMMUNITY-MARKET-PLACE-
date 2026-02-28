<?php
/**
 * Template: Provider application form.
 *
 * Used by [jqme_provider_application] shortcode.
 * Theme override: copy to yourtheme/jq-marketplace-engine/provider-application-form.php
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JQME\StatusEnums;

$user         = wp_get_current_user();
$listing_types = StatusEnums::listing_types();
$notice       = sanitize_text_field( $_GET['jqme_notice'] ?? '' );
?>

<div class="jqme-form-wrap">
	<?php if ( 'application_submitted' === $notice ) : ?>
		<div class="jqme-notice jqme-notice--success">
			<p><?php esc_html_e( 'Your application has been submitted. We will review it and get back to you soon.', 'jq-marketplace-engine' ); ?></p>
		</div>
	<?php elseif ( 'missing_fields' === $notice ) : ?>
		<div class="jqme-notice jqme-notice--error">
			<p><?php esc_html_e( 'Please fill in all required fields.', 'jq-marketplace-engine' ); ?></p>
		</div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Apply to Become a Provider', 'jq-marketplace-engine' ); ?></h2>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="jqme-form">
		<?php wp_nonce_field( 'jqme_provider_apply' ); ?>
		<input type="hidden" name="action" value="jqme_provider_apply">

		<h3><?php esc_html_e( 'Business Information', 'jq-marketplace-engine' ); ?></h3>

		<p>
			<label for="company_name"><?php esc_html_e( 'Company Name', 'jq-marketplace-engine' ); ?> <span class="required">*</span></label>
			<input type="text" name="company_name" id="company_name" required>
		</p>
		<p>
			<label for="contact_name"><?php esc_html_e( 'Contact Name', 'jq-marketplace-engine' ); ?> <span class="required">*</span></label>
			<input type="text" name="contact_name" id="contact_name" value="<?php echo esc_attr( $user->display_name ); ?>" required>
		</p>
		<p>
			<label for="contact_email"><?php esc_html_e( 'Contact Email', 'jq-marketplace-engine' ); ?> <span class="required">*</span></label>
			<input type="email" name="contact_email" id="contact_email" value="<?php echo esc_attr( $user->user_email ); ?>" required>
		</p>
		<p>
			<label for="contact_phone"><?php esc_html_e( 'Phone', 'jq-marketplace-engine' ); ?></label>
			<input type="tel" name="contact_phone" id="contact_phone">
		</p>
		<p>
			<label for="website"><?php esc_html_e( 'Website', 'jq-marketplace-engine' ); ?></label>
			<input type="url" name="website" id="website">
		</p>
		<p>
			<label for="years_in_business"><?php esc_html_e( 'Years in Business', 'jq-marketplace-engine' ); ?></label>
			<input type="number" name="years_in_business" id="years_in_business" min="0" max="100">
		</p>

		<h3><?php esc_html_e( 'Location', 'jq-marketplace-engine' ); ?></h3>

		<p>
			<label for="address_line1"><?php esc_html_e( 'Address', 'jq-marketplace-engine' ); ?></label>
			<input type="text" name="address_line1" id="address_line1">
		</p>
		<p>
			<label for="city"><?php esc_html_e( 'City', 'jq-marketplace-engine' ); ?> <span class="required">*</span></label>
			<input type="text" name="city" id="city" required>
		</p>
		<p>
			<label for="state"><?php esc_html_e( 'State', 'jq-marketplace-engine' ); ?> <span class="required">*</span></label>
			<input type="text" name="state" id="state" required>
		</p>
		<p>
			<label for="zip"><?php esc_html_e( 'ZIP Code', 'jq-marketplace-engine' ); ?> <span class="required">*</span></label>
			<input type="text" name="zip" id="zip" required>
		</p>
		<p>
			<label for="service_radius_miles"><?php esc_html_e( 'Service Radius (Miles)', 'jq-marketplace-engine' ); ?></label>
			<input type="number" name="service_radius_miles" id="service_radius_miles" value="50" min="1" max="500">
		</p>

		<h3><?php esc_html_e( 'Delivery', 'jq-marketplace-engine' ); ?></h3>

		<p>
			<label>
				<input type="checkbox" name="can_deliver" value="1">
				<?php esc_html_e( 'I can deliver equipment', 'jq-marketplace-engine' ); ?>
			</label>
		</p>
		<p>
			<label for="delivery_radius_miles"><?php esc_html_e( 'Delivery Radius (Miles)', 'jq-marketplace-engine' ); ?></label>
			<input type="number" name="delivery_radius_miles" id="delivery_radius_miles" value="25" min="0" max="500">
		</p>

		<h3><?php esc_html_e( 'What would you like to list?', 'jq-marketplace-engine' ); ?></h3>

		<?php foreach ( $listing_types as $type_key => $type_label ) : ?>
			<p>
				<label>
					<input type="checkbox" name="listing_types[]" value="<?php echo esc_attr( $type_key ); ?>">
					<?php echo esc_html( $type_label ); ?>
				</label>
			</p>
		<?php endforeach; ?>

		<h3><?php esc_html_e( 'Additional Notes', 'jq-marketplace-engine' ); ?></h3>

		<p>
			<label for="application_notes"><?php esc_html_e( 'Tell us about your business and what you plan to list:', 'jq-marketplace-engine' ); ?></label>
			<textarea name="application_notes" id="application_notes" rows="5"></textarea>
		</p>

		<p>
			<button type="submit" class="jqme-btn jqme-btn--primary"><?php esc_html_e( 'Submit Application', 'jq-marketplace-engine' ); ?></button>
		</p>
	</form>
</div>
