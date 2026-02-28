<?php
/**
 * Template: Edit listing form.
 *
 * Used by [jqme_edit_listing] shortcode.
 * $listing variable is set by the shortcode handler before include.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JQME\StatusEnums;
use JQME\Listings\Listing;

$listing = Listing::get( absint( $_GET['listing_id'] ?? 0 ) );
if ( ! $listing ) {
	echo '<p>' . esc_html__( 'Listing not found.', 'jq-marketplace-engine' ) . '</p>';
	return;
}

$assets  = Listing::get_assets( $listing->id );
$statuses = StatusEnums::listing_statuses();
$types    = StatusEnums::listing_types();
$notice   = sanitize_text_field( $_GET['jqme_notice'] ?? '' );

$is_rental  = StatusEnums::TYPE_EQUIPMENT_RENTAL === $listing->listing_type;
$is_sale    = StatusEnums::TYPE_EQUIPMENT_SALE === $listing->listing_type;
$is_service = StatusEnums::TYPE_SERVICE_BOOKING === $listing->listing_type;
$is_equipment = $is_rental || $is_sale;
?>

<div class="jqme-form-wrap">
	<?php if ( $notice ) : ?>
		<div class="jqme-notice jqme-notice--success">
			<p><?php echo esc_html( ucwords( str_replace( '_', ' ', $notice ) ) ); ?></p>
		</div>
	<?php endif; ?>

	<h2><?php printf( esc_html__( 'Edit: %s', 'jq-marketplace-engine' ), esc_html( $listing->title ) ); ?></h2>
	<p>
		<?php esc_html_e( 'Type:', 'jq-marketplace-engine' ); ?> <strong><?php echo esc_html( $types[ $listing->listing_type ] ?? $listing->listing_type ); ?></strong>
		&mdash;
		<?php esc_html_e( 'Status:', 'jq-marketplace-engine' ); ?> <strong><?php echo esc_html( $statuses[ $listing->status ] ?? $listing->status ); ?></strong>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="jqme-form">
		<?php wp_nonce_field( 'jqme_update_listing_' . $listing->id ); ?>
		<input type="hidden" name="action" value="jqme_update_listing">
		<input type="hidden" name="listing_id" value="<?php echo esc_attr( $listing->id ); ?>">

		<h3><?php esc_html_e( 'Basic Information', 'jq-marketplace-engine' ); ?></h3>

		<p>
			<label for="title"><?php esc_html_e( 'Title', 'jq-marketplace-engine' ); ?></label>
			<input type="text" name="title" id="title" value="<?php echo esc_attr( $listing->title ); ?>" required>
		</p>
		<p>
			<label for="description"><?php esc_html_e( 'Description', 'jq-marketplace-engine' ); ?></label>
			<textarea name="description" id="description" rows="6"><?php echo esc_textarea( $listing->description ); ?></textarea>
		</p>
		<p>
			<label for="category"><?php esc_html_e( 'Category', 'jq-marketplace-engine' ); ?></label>
			<input type="text" name="category" id="category" value="<?php echo esc_attr( $listing->category ); ?>">
		</p>

		<?php if ( $is_equipment ) : ?>
			<h3><?php esc_html_e( 'Equipment Details', 'jq-marketplace-engine' ); ?></h3>
			<p>
				<label for="brand"><?php esc_html_e( 'Brand', 'jq-marketplace-engine' ); ?></label>
				<input type="text" name="brand" id="brand" value="<?php echo esc_attr( $listing->brand ); ?>">
			</p>
			<p>
				<label for="model"><?php esc_html_e( 'Model', 'jq-marketplace-engine' ); ?></label>
				<input type="text" name="model" id="model" value="<?php echo esc_attr( $listing->model ); ?>">
			</p>
			<p>
				<label for="serial_number"><?php esc_html_e( 'Serial Number', 'jq-marketplace-engine' ); ?></label>
				<input type="text" name="serial_number" id="serial_number" value="<?php echo esc_attr( $listing->serial_number ); ?>">
			</p>
			<p>
				<label for="condition_grade"><?php esc_html_e( 'Condition', 'jq-marketplace-engine' ); ?></label>
				<select name="condition_grade" id="condition_grade">
					<?php foreach ( [ '' => '— Select —', 'new' => 'New', 'like_new' => 'Like New', 'excellent' => 'Excellent', 'good' => 'Good', 'fair' => 'Fair' ] as $v => $l ) : ?>
						<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $listing->condition_grade, $v ); ?>><?php echo esc_html( $l ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
		<?php endif; ?>

		<?php if ( $is_rental ) : ?>
			<h3><?php esc_html_e( 'Rental Pricing', 'jq-marketplace-engine' ); ?></h3>
			<p><label><?php esc_html_e( 'Day Rate ($)', 'jq-marketplace-engine' ); ?></label> <input type="number" name="day_rate" value="<?php echo esc_attr( $listing->day_rate ); ?>" step="0.01" min="0"></p>
			<p><label><?php esc_html_e( 'Weekend Rate ($)', 'jq-marketplace-engine' ); ?></label> <input type="number" name="weekend_rate" value="<?php echo esc_attr( $listing->weekend_rate ); ?>" step="0.01" min="0"></p>
			<p><label><?php esc_html_e( 'Week Rate ($)', 'jq-marketplace-engine' ); ?></label> <input type="number" name="week_rate" value="<?php echo esc_attr( $listing->week_rate ); ?>" step="0.01" min="0"></p>
			<p><label><?php esc_html_e( 'Month Rate ($)', 'jq-marketplace-engine' ); ?></label> <input type="number" name="month_rate" value="<?php echo esc_attr( $listing->month_rate ); ?>" step="0.01" min="0"></p>
			<p><label><?php esc_html_e( 'Deposit ($)', 'jq-marketplace-engine' ); ?></label> <input type="number" name="deposit_amount" value="<?php echo esc_attr( $listing->deposit_amount ); ?>" step="0.01" min="0"></p>
		<?php endif; ?>

		<?php if ( $is_sale ) : ?>
			<h3><?php esc_html_e( 'Sale Pricing', 'jq-marketplace-engine' ); ?></h3>
			<p><label><?php esc_html_e( 'Asking Price ($)', 'jq-marketplace-engine' ); ?></label> <input type="number" name="asking_price" value="<?php echo esc_attr( $listing->asking_price ); ?>" step="0.01" min="0"></p>
			<p><label><?php esc_html_e( 'Quantity', 'jq-marketplace-engine' ); ?></label> <input type="number" name="quantity" value="<?php echo esc_attr( $listing->quantity ); ?>" min="1"></p>
			<p><label><input type="checkbox" name="offers_allowed" value="1" <?php checked( $listing->offers_allowed ); ?>> <?php esc_html_e( 'Accept offers', 'jq-marketplace-engine' ); ?></label></p>
		<?php endif; ?>

		<?php if ( $is_service ) : ?>
			<h3><?php esc_html_e( 'Service Details', 'jq-marketplace-engine' ); ?></h3>
			<p><label><?php esc_html_e( 'Certification Level', 'jq-marketplace-engine' ); ?></label> <input type="text" name="certification_level" value="<?php echo esc_attr( $listing->certification_level ); ?>"></p>
			<p><label><?php esc_html_e( 'Hourly Rate ($)', 'jq-marketplace-engine' ); ?></label> <input type="number" name="hourly_rate" value="<?php echo esc_attr( $listing->hourly_rate ); ?>" step="0.01" min="0"></p>
			<p><label><?php esc_html_e( 'Half-Day Rate ($)', 'jq-marketplace-engine' ); ?></label> <input type="number" name="half_day_rate" value="<?php echo esc_attr( $listing->half_day_rate ); ?>" step="0.01" min="0"></p>
			<p><label><?php esc_html_e( 'Full-Day Rate ($)', 'jq-marketplace-engine' ); ?></label> <input type="number" name="full_day_rate" value="<?php echo esc_attr( $listing->full_day_rate ); ?>" step="0.01" min="0"></p>
			<p><label><?php esc_html_e( 'Deliverables', 'jq-marketplace-engine' ); ?></label> <textarea name="deliverables" rows="3"><?php echo esc_textarea( $listing->deliverables ); ?></textarea></p>
		<?php endif; ?>

		<!-- Existing images -->
		<?php if ( ! empty( $assets ) ) : ?>
			<h3><?php esc_html_e( 'Current Images', 'jq-marketplace-engine' ); ?></h3>
			<div style="display:flex; gap:8px; flex-wrap:wrap;">
				<?php foreach ( $assets as $asset ) : ?>
					<?php if ( 'image' === $asset->asset_type ) : ?>
						<div style="position:relative;">
							<img src="<?php echo esc_url( $asset->file_url ); ?>" style="width:120px; height:120px; object-fit:cover; border-radius:4px;" alt="">
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Add More Photos', 'jq-marketplace-engine' ); ?></h3>
		<p><input type="file" name="listing_images[]" multiple accept="image/*"></p>

		<p>
			<button type="submit" class="jqme-btn jqme-btn--primary"><?php esc_html_e( 'Save Changes', 'jq-marketplace-engine' ); ?></button>
		</p>
	</form>
</div>
