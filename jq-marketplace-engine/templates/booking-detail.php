<?php
/**
 * Template: Front-end booking detail view.
 *
 * Used by [jqme_booking_detail] shortcode.
 * Shows booking details to both customer and provider.
 * Expects ?booking_number= in the URL.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JQME\Bookings\Booking;
use JQME\Providers\Provider;
use JQME\StatusEnums;
use JQME\Payments\DepositManager;

$booking_number = sanitize_text_field( $_GET['booking_number'] ?? '' );
$booking        = $booking_number ? Booking::get_by_number( $booking_number ) : null;

if ( ! $booking ) {
	echo '<div class="jqme-notice jqme-notice--error">' . esc_html__( 'Booking not found.', 'jq-marketplace-engine' ) . '</div>';
	return;
}

// Verify the current user is either the customer or the provider.
$current_user = get_current_user_id();
$provider     = Provider::get_by_user( $current_user );
$is_customer  = (int) $booking->customer_id === $current_user;
$is_provider  = $provider && (int) $booking->provider_id === (int) $provider->id;

if ( ! $is_customer && ! $is_provider && ! current_user_can( 'jqme_manage_bookings' ) ) {
	echo '<div class="jqme-notice jqme-notice--error">' . esc_html__( 'You do not have permission to view this booking.', 'jq-marketplace-engine' ) . '</div>';
	return;
}

$all_statuses = array_merge(
	StatusEnums::rental_booking_statuses(),
	StatusEnums::service_booking_statuses(),
	StatusEnums::sale_order_statuses()
);
$types        = StatusEnums::listing_types();
$status_label = $all_statuses[ $booking->status ] ?? ucwords( str_replace( '_', ' ', $booking->status ) );
$type_label   = $types[ $booking->booking_type ] ?? $booking->booking_type;

$notice = sanitize_text_field( $_GET['jqme_notice'] ?? '' );

// Load related data.
$customer_user = get_userdata( $booking->customer_id );
$provider_data = $provider && $is_provider ? $provider : \JQME\Providers\Provider::get( $booking->provider_id );

// Deposits for this booking.
$deposits = DepositManager::get_for_booking( $booking->id );
$dep_statuses = StatusEnums::deposit_statuses();

// Determine if actions are available.
$cancellable_by_customer = $is_customer && in_array( $booking->status, [
	StatusEnums::RENTAL_REQUESTED,
	StatusEnums::RENTAL_PENDING_PROVIDER_APPROVAL,
	StatusEnums::RENTAL_APPROVED_PENDING_PAYMENT,
	StatusEnums::SERVICE_REQUESTED,
	StatusEnums::SERVICE_PENDING_PROVIDER_APPROVAL,
], true );

$approvable_by_provider = $is_provider && in_array( $booking->status, [
	StatusEnums::RENTAL_REQUESTED,
	StatusEnums::RENTAL_PENDING_PROVIDER_APPROVAL,
	StatusEnums::SERVICE_REQUESTED,
	StatusEnums::SERVICE_PENDING_PROVIDER_APPROVAL,
], true );
?>

<div class="jqme-booking-detail">
	<?php if ( $notice ) : ?>
		<div class="jqme-notice jqme-notice--success">
			<?php echo esc_html( ucwords( str_replace( '_', ' ', $notice ) ) ); ?>
		</div>
	<?php endif; ?>

	<!-- Header -->
	<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
		<div>
			<h2 style="margin:0;"><?php echo esc_html( $booking->booking_number ); ?></h2>
			<p style="margin:4px 0; color:#666;">
				<span class="jqme-badge"><?php echo esc_html( $type_label ); ?></span>
				<span class="jqme-badge"><?php echo esc_html( $status_label ); ?></span>
			</p>
		</div>
		<?php if ( $is_customer ) : ?>
			<a href="<?php echo esc_url( remove_query_arg( 'booking_number' ) ); ?>" class="jqme-btn jqme-btn--small">&larr; <?php esc_html_e( 'Back to Bookings', 'jq-marketplace-engine' ); ?></a>
		<?php endif; ?>
	</div>

	<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
		<!-- Left: Booking info -->
		<div>
			<div class="jqme-card">
				<h3><?php esc_html_e( 'Booking Details', 'jq-marketplace-engine' ); ?></h3>
				<table class="jqme-detail-table">
					<tr>
						<td><strong><?php esc_html_e( 'Listing', 'jq-marketplace-engine' ); ?></strong></td>
						<td><?php echo esc_html( $booking->listing_title ?? '#' . $booking->listing_id ); ?></td>
					</tr>
					<?php if ( $booking->date_start ) : ?>
						<tr>
							<td><strong><?php esc_html_e( 'Start', 'jq-marketplace-engine' ); ?></strong></td>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $booking->date_start ) ) ); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( $booking->date_end ) : ?>
						<tr>
							<td><strong><?php esc_html_e( 'End', 'jq-marketplace-engine' ); ?></strong></td>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $booking->date_end ) ) ); ?></td>
						</tr>
					<?php endif; ?>
					<tr>
						<td><strong><?php esc_html_e( 'Fulfillment', 'jq-marketplace-engine' ); ?></strong></td>
						<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $booking->fulfillment_mode ?? 'pickup' ) ) ); ?></td>
					</tr>
					<?php if ( $booking->delivery_address ) : ?>
						<tr>
							<td><strong><?php esc_html_e( 'Delivery To', 'jq-marketplace-engine' ); ?></strong></td>
							<td><?php echo esc_html( $booking->delivery_address ); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( $booking->customer_notes ) : ?>
						<tr>
							<td><strong><?php esc_html_e( 'Notes', 'jq-marketplace-engine' ); ?></strong></td>
							<td><?php echo esc_html( $booking->customer_notes ); ?></td>
						</tr>
					<?php endif; ?>
				</table>
			</div>

			<!-- Other party info -->
			<div class="jqme-card" style="margin-top:16px;">
				<?php if ( $is_customer && $provider_data ) : ?>
					<h3><?php esc_html_e( 'Provider', 'jq-marketplace-engine' ); ?></h3>
					<p>
						<strong><?php echo esc_html( $provider_data->company_name ); ?></strong><br>
						<?php echo esc_html( $provider_data->contact_name ); ?>
					</p>
				<?php elseif ( $is_provider && $customer_user ) : ?>
					<h3><?php esc_html_e( 'Customer', 'jq-marketplace-engine' ); ?></h3>
					<p>
						<strong><?php echo esc_html( $customer_user->display_name ); ?></strong><br>
						<?php echo esc_html( $customer_user->user_email ); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>

		<!-- Right: Financials + Actions -->
		<div>
			<div class="jqme-card">
				<h3><?php esc_html_e( 'Financial Summary', 'jq-marketplace-engine' ); ?></h3>
				<table class="jqme-detail-table" style="width:100%;">
					<tr>
						<td><?php esc_html_e( 'Subtotal', 'jq-marketplace-engine' ); ?></td>
						<td style="text-align:right;">$<?php echo esc_html( number_format( (float) $booking->subtotal, 2 ) ); ?></td>
					</tr>
					<?php if ( (float) $booking->delivery_fee > 0 ) : ?>
						<tr>
							<td><?php esc_html_e( 'Delivery', 'jq-marketplace-engine' ); ?></td>
							<td style="text-align:right;">$<?php echo esc_html( number_format( (float) $booking->delivery_fee, 2 ) ); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( (float) $booking->shipping_fee > 0 ) : ?>
						<tr>
							<td><?php esc_html_e( 'Shipping', 'jq-marketplace-engine' ); ?></td>
							<td style="text-align:right;">$<?php echo esc_html( number_format( (float) $booking->shipping_fee, 2 ) ); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( (float) $booking->discount_amount > 0 ) : ?>
						<tr>
							<td><?php esc_html_e( 'Discount', 'jq-marketplace-engine' ); ?></td>
							<td style="text-align:right; color:#d63638;">-$<?php echo esc_html( number_format( (float) $booking->discount_amount, 2 ) ); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( $is_customer ) : ?>
						<tr>
							<td><?php esc_html_e( 'Fees', 'jq-marketplace-engine' ); ?></td>
							<td style="text-align:right;">$<?php echo esc_html( number_format( (float) $booking->platform_fee + (float) $booking->processing_fee, 2 ) ); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( (float) $booking->deposit_amount > 0 ) : ?>
						<tr>
							<td><?php esc_html_e( 'Security Deposit', 'jq-marketplace-engine' ); ?></td>
							<td style="text-align:right;">$<?php echo esc_html( number_format( (float) $booking->deposit_amount, 2 ) ); ?></td>
						</tr>
					<?php endif; ?>
					<tr style="font-weight:bold; border-top:2px solid #ddd;">
						<?php if ( $is_customer ) : ?>
							<td><?php esc_html_e( 'Total', 'jq-marketplace-engine' ); ?></td>
							<td style="text-align:right;">$<?php echo esc_html( number_format( (float) $booking->total_amount, 2 ) ); ?></td>
						<?php elseif ( $is_provider ) : ?>
							<td><?php esc_html_e( 'Your Payout', 'jq-marketplace-engine' ); ?></td>
							<td style="text-align:right; color:#00a32a;">$<?php echo esc_html( number_format( (float) $booking->provider_payout, 2 ) ); ?></td>
						<?php endif; ?>
					</tr>
				</table>
			</div>

			<!-- Deposit status -->
			<?php if ( ! empty( $deposits ) ) : ?>
				<div class="jqme-card" style="margin-top:16px;">
					<h3><?php esc_html_e( 'Deposit Status', 'jq-marketplace-engine' ); ?></h3>
					<?php foreach ( $deposits as $dep ) : ?>
						<p>
							$<?php echo esc_html( number_format( (float) $dep->amount, 2 ) ); ?> —
							<span class="jqme-badge"><?php echo esc_html( $dep_statuses[ $dep->status ] ?? $dep->status ); ?></span>
							<?php if ( (float) $dep->captured_amount > 0 ) : ?>
								<br><small><?php printf( esc_html__( 'Captured: $%s', 'jq-marketplace-engine' ), number_format( (float) $dep->captured_amount, 2 ) ); ?></small>
							<?php endif; ?>
						</p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<!-- Actions -->
			<?php if ( $approvable_by_provider || $cancellable_by_customer ) : ?>
				<div class="jqme-card" style="margin-top:16px;">
					<h3><?php esc_html_e( 'Actions', 'jq-marketplace-engine' ); ?></h3>

					<?php if ( $approvable_by_provider ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:8px;">
							<?php wp_nonce_field( 'jqme_booking_approve_provider_' . $booking->id ); ?>
							<input type="hidden" name="action" value="jqme_booking_approve_provider">
							<input type="hidden" name="booking_id" value="<?php echo esc_attr( $booking->id ); ?>">
							<button type="submit" class="jqme-btn jqme-btn--primary" style="width:100%;">
								<?php esc_html_e( 'Approve Booking', 'jq-marketplace-engine' ); ?>
							</button>
						</form>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'jqme_booking_decline_provider_' . $booking->id ); ?>
							<input type="hidden" name="action" value="jqme_booking_decline_provider">
							<input type="hidden" name="booking_id" value="<?php echo esc_attr( $booking->id ); ?>">
							<div class="jqme-field" style="margin-bottom:8px;">
								<textarea name="decline_reason" rows="2" placeholder="<?php esc_attr_e( 'Reason for declining (optional)', 'jq-marketplace-engine' ); ?>" style="width:100%;"></textarea>
							</div>
							<button type="submit" class="jqme-btn jqme-btn--danger" style="width:100%;"
									onclick="return confirm('<?php esc_attr_e( 'Decline this booking?', 'jq-marketplace-engine' ); ?>');">
								<?php esc_html_e( 'Decline Booking', 'jq-marketplace-engine' ); ?>
							</button>
						</form>
					<?php endif; ?>

					<?php if ( $cancellable_by_customer ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'jqme_booking_cancel_customer_' . $booking->id ); ?>
							<input type="hidden" name="action" value="jqme_booking_cancel_customer">
							<input type="hidden" name="booking_id" value="<?php echo esc_attr( $booking->id ); ?>">
							<div class="jqme-field" style="margin-bottom:8px;">
								<textarea name="cancel_reason" rows="2" placeholder="<?php esc_attr_e( 'Reason for cancellation (optional)', 'jq-marketplace-engine' ); ?>" style="width:100%;"></textarea>
							</div>
							<button type="submit" class="jqme-btn jqme-btn--danger" style="width:100%;"
									onclick="return confirm('<?php esc_attr_e( 'Cancel this booking?', 'jq-marketplace-engine' ); ?>');">
								<?php esc_html_e( 'Cancel Booking', 'jq-marketplace-engine' ); ?>
							</button>
						</form>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<!-- Timeline -->
			<div class="jqme-card" style="margin-top:16px;">
				<h3><?php esc_html_e( 'Timeline', 'jq-marketplace-engine' ); ?></h3>
				<div style="font-size:0.9em;">
					<p><?php esc_html_e( 'Created:', 'jq-marketplace-engine' ); ?> <?php echo esc_html( date_i18n( 'M j, Y g:ia', strtotime( $booking->created_at ) ) ); ?></p>
					<?php if ( ! empty( $booking->checked_out_at ) ) : ?>
						<p><?php esc_html_e( 'Checked Out:', 'jq-marketplace-engine' ); ?> <?php echo esc_html( date_i18n( 'M j, Y g:ia', strtotime( $booking->checked_out_at ) ) ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $booking->returned_at ) ) : ?>
						<p><?php esc_html_e( 'Returned:', 'jq-marketplace-engine' ); ?> <?php echo esc_html( date_i18n( 'M j, Y g:ia', strtotime( $booking->returned_at ) ) ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $booking->completed_at ) ) : ?>
						<p><?php esc_html_e( 'Completed:', 'jq-marketplace-engine' ); ?> <?php echo esc_html( date_i18n( 'M j, Y g:ia', strtotime( $booking->completed_at ) ) ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $booking->cancelled_at ) ) : ?>
						<p style="color:#d63638;"><?php esc_html_e( 'Cancelled:', 'jq-marketplace-engine' ); ?> <?php echo esc_html( date_i18n( 'M j, Y g:ia', strtotime( $booking->cancelled_at ) ) ); ?></p>
						<?php if ( ! empty( $booking->cancellation_reason ) ) : ?>
							<p><small><?php echo esc_html( $booking->cancellation_reason ); ?></small></p>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>
