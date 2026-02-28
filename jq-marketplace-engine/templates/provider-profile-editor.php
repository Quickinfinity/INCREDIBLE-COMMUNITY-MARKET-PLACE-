<?php
/**
 * Template: Provider profile editor.
 *
 * Used by [jqme_provider_profile] shortcode.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notice = sanitize_text_field( $_GET['jqme_notice'] ?? '' );
?>

<div class="jqme-form-wrap">
	<?php if ( 'profile_updated' === $notice ) : ?>
		<div class="jqme-notice jqme-notice--success">
			<p><?php esc_html_e( 'Profile updated successfully.', 'jq-marketplace-engine' ); ?></p>
		</div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Edit Provider Profile', 'jq-marketplace-engine' ); ?></h2>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="jqme-form">
		<?php wp_nonce_field( 'jqme_provider_update_profile' ); ?>
		<input type="hidden" name="action" value="jqme_provider_update_profile">

		<p>
			<label for="company_name"><?php esc_html_e( 'Company Name', 'jq-marketplace-engine' ); ?></label>
			<input type="text" name="company_name" id="company_name" value="<?php echo esc_attr( $provider->company_name ); ?>">
		</p>
		<p>
			<label for="contact_name"><?php esc_html_e( 'Contact Name', 'jq-marketplace-engine' ); ?></label>
			<input type="text" name="contact_name" id="contact_name" value="<?php echo esc_attr( $provider->contact_name ); ?>">
		</p>
		<p>
			<label for="contact_email"><?php esc_html_e( 'Contact Email', 'jq-marketplace-engine' ); ?></label>
			<input type="email" name="contact_email" id="contact_email" value="<?php echo esc_attr( $provider->contact_email ); ?>">
		</p>
		<p>
			<label for="contact_phone"><?php esc_html_e( 'Phone', 'jq-marketplace-engine' ); ?></label>
			<input type="tel" name="contact_phone" id="contact_phone" value="<?php echo esc_attr( $provider->contact_phone ); ?>">
		</p>

		<h3><?php esc_html_e( 'Location', 'jq-marketplace-engine' ); ?></h3>

		<p>
			<label for="address_line1"><?php esc_html_e( 'Address', 'jq-marketplace-engine' ); ?></label>
			<input type="text" name="address_line1" id="address_line1" value="<?php echo esc_attr( $provider->address_line1 ); ?>">
		</p>
		<p>
			<label for="city"><?php esc_html_e( 'City', 'jq-marketplace-engine' ); ?></label>
			<input type="text" name="city" id="city" value="<?php echo esc_attr( $provider->city ); ?>">
		</p>
		<p>
			<label for="state"><?php esc_html_e( 'State', 'jq-marketplace-engine' ); ?></label>
			<input type="text" name="state" id="state" value="<?php echo esc_attr( $provider->state ); ?>">
		</p>
		<p>
			<label for="zip"><?php esc_html_e( 'ZIP Code', 'jq-marketplace-engine' ); ?></label>
			<input type="text" name="zip" id="zip" value="<?php echo esc_attr( $provider->zip ); ?>">
		</p>
		<p>
			<label for="service_radius_miles"><?php esc_html_e( 'Service Radius (Miles)', 'jq-marketplace-engine' ); ?></label>
			<input type="number" name="service_radius_miles" id="service_radius_miles" value="<?php echo esc_attr( $provider->service_radius_miles ); ?>" min="1">
		</p>

		<h3><?php esc_html_e( 'Delivery', 'jq-marketplace-engine' ); ?></h3>

		<p>
			<label>
				<input type="checkbox" name="can_deliver" value="1" <?php checked( $provider->can_deliver ); ?>>
				<?php esc_html_e( 'I can deliver equipment', 'jq-marketplace-engine' ); ?>
			</label>
		</p>
		<p>
			<label for="delivery_radius_miles"><?php esc_html_e( 'Delivery Radius (Miles)', 'jq-marketplace-engine' ); ?></label>
			<input type="number" name="delivery_radius_miles" id="delivery_radius_miles" value="<?php echo esc_attr( $provider->delivery_radius_miles ); ?>" min="0">
		</p>

		<p>
			<button type="submit" class="jqme-btn jqme-btn--primary"><?php esc_html_e( 'Save Profile', 'jq-marketplace-engine' ); ?></button>
		</p>
	</form>
</div>
