<?php
/**
 * Admin booking detail page — shows full booking info with admin actions.
 *
 * Loaded by bookings.php when action=view.
 * Expects $booking to be set by the parent file.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JQME\StatusEnums;
use JQME\Core;
use JQME\Payments\DepositManager;

global $wpdb;

$booking_types = StatusEnums::listing_types();
$all_statuses  = array_merge(
	StatusEnums::rental_booking_statuses(),
	StatusEnums::service_booking_statuses(),
	StatusEnums::sale_order_statuses()
);

$status_label = $all_statuses[ $booking->status ] ?? ucwords( str_replace( '_', ' ', $booking->status ) );
$type_label   = $booking_types[ $booking->booking_type ] ?? $booking->booking_type;

// Load related data.
$customer = get_userdata( $booking->customer_id );
$provider = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM " . Core::table( 'providers' ) . " WHERE id = %d", $booking->provider_id
) );
$listing = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM " . Core::table( 'listings' ) . " WHERE id = %d", $booking->listing_id
) );

// Transactions for this booking.
$transactions = $wpdb->get_results( $wpdb->prepare(
	"SELECT * FROM " . Core::table( 'transactions' ) . " WHERE booking_id = %d ORDER BY created_at DESC",
	$booking->id
) );

// Deposits.
$deposits = DepositManager::get_for_booking( $booking->id );

// Audit trail.
$audit_trail = $wpdb->get_results( $wpdb->prepare(
	"SELECT * FROM " . Core::table( 'audit_log' ) . " WHERE object_type = 'booking' AND object_id = %d ORDER BY created_at DESC LIMIT 25",
	$booking->id
) );

$badge_class = match ( true ) {
	str_contains( $booking->status, 'completed' )  => 'jqme-badge--success',
	str_contains( $booking->status, 'confirmed' ),
	str_contains( $booking->status, 'active' )      => 'jqme-badge--info',
	str_contains( $booking->status, 'requested' ),
	str_contains( $booking->status, 'pending' )     => 'jqme-badge--warning',
	str_contains( $booking->status, 'cancelled' ),
	str_contains( $booking->status, 'dispute' ),
	str_contains( $booking->status, 'overdue' )     => 'jqme-badge--danger',
	default                                          => 'jqme-badge--muted',
};

$notice = sanitize_text_field( $_GET['jqme_notice'] ?? '' );
?>

<div class="wrap">
	<h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-bookings' ) ); ?>">&larr; <?php esc_html_e( 'Bookings', 'jq-marketplace-engine' ); ?></a>
		&mdash; <?php echo esc_html( $booking->booking_number ); ?>
	</h1>

	<?php if ( $notice ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( ucwords( str_replace( '_', ' ', $notice ) ) ); ?></p>
		</div>
	<?php endif; ?>

	<div style="display:grid; grid-template-columns:2fr 1fr; gap:20px; margin-top:20px;">

		<!-- Left column: Booking details -->
		<div>
			<!-- Booking Overview -->
			<div class="card" style="max-width:none;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Booking Details', 'jq-marketplace-engine' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Booking Number', 'jq-marketplace-engine' ); ?></th>
						<td><strong><?php echo esc_html( $booking->booking_number ); ?></strong></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Type', 'jq-marketplace-engine' ); ?></th>
						<td><?php echo esc_html( $type_label ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Status', 'jq-marketplace-engine' ); ?></th>
						<td><span class="jqme-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $status_label ); ?></span></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Listing', 'jq-marketplace-engine' ); ?></th>
						<td>
							<?php if ( $listing ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-listings&action=view&id=' . $listing->id ) ); ?>">
									<?php echo esc_html( $listing->title ); ?>
								</a>
							<?php else : ?>
								#<?php echo esc_html( $booking->listing_id ); ?>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Fulfillment', 'jq-marketplace-engine' ); ?></th>
						<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $booking->fulfillment_mode ?? 'pickup' ) ) ); ?></td>
					</tr>
					<?php if ( $booking->delivery_address ) : ?>
						<tr>
							<th><?php esc_html_e( 'Delivery Address', 'jq-marketplace-engine' ); ?></th>
							<td><?php echo esc_html( $booking->delivery_address ); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( $booking->date_start ) : ?>
						<tr>
							<th><?php esc_html_e( 'Start Date', 'jq-marketplace-engine' ); ?></th>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $booking->date_start ) ) ); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( $booking->date_end ) : ?>
						<tr>
							<th><?php esc_html_e( 'End Date', 'jq-marketplace-engine' ); ?></th>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $booking->date_end ) ) ); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( $booking->customer_notes ) : ?>
						<tr>
							<th><?php esc_html_e( 'Customer Notes', 'jq-marketplace-engine' ); ?></th>
							<td><?php echo esc_html( $booking->customer_notes ); ?></td>
						</tr>
					<?php endif; ?>
				</table>
			</div>

			<!-- Financial Summary -->
			<div class="card" style="max-width:none; margin-top:15px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Financial Summary', 'jq-marketplace-engine' ); ?></h2>
				<table class="widefat" style="border:0;">
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Subtotal', 'jq-marketplace-engine' ); ?></td>
							<td style="text-align:right;">$<?php echo esc_html( number_format( (float) $booking->subtotal, 2 ) ); ?></td>
						</tr>
						<?php if ( (float) $booking->delivery_fee > 0 ) : ?>
							<tr>
								<td><?php esc_html_e( 'Delivery Fee', 'jq-marketplace-engine' ); ?></td>
								<td style="text-align:right;">$<?php echo esc_html( number_format( (float) $booking->delivery_fee, 2 ) ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( (float) $booking->shipping_fee > 0 ) : ?>
							<tr>
								<td><?php esc_html_e( 'Shipping Fee', 'jq-marketplace-engine' ); ?></td>
								<td style="text-align:right;">$<?php echo esc_html( number_format( (float) $booking->shipping_fee, 2 ) ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( (float) $booking->travel_fee > 0 ) : ?>
							<tr>
								<td><?php esc_html_e( 'Travel Fee', 'jq-marketplace-engine' ); ?></td>
								<td style="text-align:right;">$<?php echo esc_html( number_format( (float) $booking->travel_fee, 2 ) ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( (float) $booking->discount_amount > 0 ) : ?>
							<tr>
								<td><?php esc_html_e( 'Discount', 'jq-marketplace-engine' ); ?></td>
								<td style="text-align:right; color:#d63638;">-$<?php echo esc_html( number_format( (float) $booking->discount_amount, 2 ) ); ?></td>
							</tr>
						<?php endif; ?>
						<tr>
							<td><?php esc_html_e( 'Platform Fee', 'jq-marketplace-engine' ); ?></td>
							<td style="text-align:right;">$<?php echo esc_html( number_format( (float) $booking->platform_fee, 2 ) ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Processing Fee', 'jq-marketplace-engine' ); ?></td>
							<td style="text-align:right;">$<?php echo esc_html( number_format( (float) $booking->processing_fee, 2 ) ); ?></td>
						</tr>
						<?php if ( (float) $booking->deposit_amount > 0 ) : ?>
							<tr>
								<td><?php esc_html_e( 'Security Deposit', 'jq-marketplace-engine' ); ?></td>
								<td style="text-align:right;">$<?php echo esc_html( number_format( (float) $booking->deposit_amount, 2 ) ); ?></td>
							</tr>
						<?php endif; ?>
						<tr style="font-weight:bold; border-top:2px solid #ccc;">
							<td><?php esc_html_e( 'Customer Total', 'jq-marketplace-engine' ); ?></td>
							<td style="text-align:right;">$<?php echo esc_html( number_format( (float) $booking->total_amount, 2 ) ); ?></td>
						</tr>
						<tr style="color:#00a32a;">
							<td><?php esc_html_e( 'Provider Payout', 'jq-marketplace-engine' ); ?></td>
							<td style="text-align:right;">$<?php echo esc_html( number_format( (float) $booking->provider_payout, 2 ) ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- Transactions -->
			<?php if ( ! empty( $transactions ) ) : ?>
				<div class="card" style="max-width:none; margin-top:15px;">
					<h2 style="margin-top:0;"><?php esc_html_e( 'Transactions', 'jq-marketplace-engine' ); ?></h2>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Type', 'jq-marketplace-engine' ); ?></th>
								<th><?php esc_html_e( 'Gateway', 'jq-marketplace-engine' ); ?></th>
								<th><?php esc_html_e( 'Amount', 'jq-marketplace-engine' ); ?></th>
								<th><?php esc_html_e( 'Status', 'jq-marketplace-engine' ); ?></th>
								<th><?php esc_html_e( 'Date', 'jq-marketplace-engine' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $transactions as $txn ) : ?>
								<tr>
									<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $txn->transaction_type ) ) ); ?></td>
									<td><code><?php echo esc_html( $txn->gateway ); ?></code></td>
									<td>$<?php echo esc_html( number_format( (float) $txn->amount, 2 ) ); ?> <?php echo esc_html( strtoupper( $txn->currency ) ); ?></td>
									<td><?php echo esc_html( ucwords( $txn->status ) ); ?></td>
									<td><?php echo esc_html( date_i18n( 'M j, Y g:ia', strtotime( $txn->created_at ) ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<!-- Deposits -->
			<?php if ( ! empty( $deposits ) ) : ?>
				<div class="card" style="max-width:none; margin-top:15px;">
					<h2 style="margin-top:0;"><?php esc_html_e( 'Security Deposits', 'jq-marketplace-engine' ); ?></h2>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'ID', 'jq-marketplace-engine' ); ?></th>
								<th><?php esc_html_e( 'Amount', 'jq-marketplace-engine' ); ?></th>
								<th><?php esc_html_e( 'Captured', 'jq-marketplace-engine' ); ?></th>
								<th><?php esc_html_e( 'Released', 'jq-marketplace-engine' ); ?></th>
								<th><?php esc_html_e( 'Status', 'jq-marketplace-engine' ); ?></th>
								<th><?php esc_html_e( 'Created', 'jq-marketplace-engine' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$dep_statuses = StatusEnums::deposit_statuses();
							foreach ( $deposits as $dep ) : ?>
								<tr>
									<td>#<?php echo esc_html( $dep->id ); ?></td>
									<td>$<?php echo esc_html( number_format( (float) $dep->amount, 2 ) ); ?></td>
									<td>$<?php echo esc_html( number_format( (float) $dep->captured_amount, 2 ) ); ?></td>
									<td>$<?php echo esc_html( number_format( (float) $dep->released_amount, 2 ) ); ?></td>
									<td><?php echo esc_html( $dep_statuses[ $dep->status ] ?? $dep->status ); ?></td>
									<td><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $dep->created_at ) ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<!-- Audit Trail -->
			<?php if ( ! empty( $audit_trail ) ) : ?>
				<div class="card" style="max-width:none; margin-top:15px;">
					<h2 style="margin-top:0;"><?php esc_html_e( 'Audit Trail', 'jq-marketplace-engine' ); ?></h2>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Action', 'jq-marketplace-engine' ); ?></th>
								<th><?php esc_html_e( 'From', 'jq-marketplace-engine' ); ?></th>
								<th><?php esc_html_e( 'To', 'jq-marketplace-engine' ); ?></th>
								<th><?php esc_html_e( 'Notes', 'jq-marketplace-engine' ); ?></th>
								<th><?php esc_html_e( 'User', 'jq-marketplace-engine' ); ?></th>
								<th><?php esc_html_e( 'Date', 'jq-marketplace-engine' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $audit_trail as $entry ) :
								$audit_user = $entry->user_id ? get_userdata( $entry->user_id ) : null;
							?>
								<tr>
									<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $entry->action ) ) ); ?></td>
									<td><small><?php echo esc_html( $entry->old_value ?? '—' ); ?></small></td>
									<td><small><?php echo esc_html( $entry->new_value ?? '—' ); ?></small></td>
									<td><small><?php echo esc_html( $entry->notes ?? '' ); ?></small></td>
									<td><small><?php echo $audit_user ? esc_html( $audit_user->display_name ) : '—'; ?></small></td>
									<td><small><?php echo esc_html( date_i18n( 'M j, g:ia', strtotime( $entry->created_at ) ) ); ?></small></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>

		<!-- Right column: Parties + Admin Actions -->
		<div>
			<!-- Customer Info -->
			<div class="card" style="max-width:none;">
				<h3 style="margin-top:0;"><?php esc_html_e( 'Customer', 'jq-marketplace-engine' ); ?></h3>
				<?php if ( $customer ) : ?>
					<p>
						<strong><?php echo esc_html( $customer->display_name ); ?></strong><br>
						<?php echo esc_html( $customer->user_email ); ?>
					</p>
					<a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $customer->ID ) ); ?>" class="button button-small">
						<?php esc_html_e( 'View User', 'jq-marketplace-engine' ); ?>
					</a>
				<?php else : ?>
					<p><?php esc_html_e( 'Customer not found.', 'jq-marketplace-engine' ); ?></p>
				<?php endif; ?>
			</div>

			<!-- Provider Info -->
			<div class="card" style="max-width:none; margin-top:15px;">
				<h3 style="margin-top:0;"><?php esc_html_e( 'Provider', 'jq-marketplace-engine' ); ?></h3>
				<?php if ( $provider ) : ?>
					<p>
						<strong><?php echo esc_html( $provider->company_name ); ?></strong><br>
						<?php echo esc_html( $provider->contact_name ); ?><br>
						<small><?php echo esc_html( $provider->contact_email ); ?></small>
					</p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-providers&action=view&id=' . $provider->id ) ); ?>" class="button button-small">
						<?php esc_html_e( 'View Provider', 'jq-marketplace-engine' ); ?>
					</a>
				<?php else : ?>
					<p><?php esc_html_e( 'Provider not found.', 'jq-marketplace-engine' ); ?></p>
				<?php endif; ?>
			</div>

			<!-- Admin Actions -->
			<div class="card" style="max-width:none; margin-top:15px;">
				<h3 style="margin-top:0;"><?php esc_html_e( 'Admin Actions', 'jq-marketplace-engine' ); ?></h3>

				<?php
				// Show contextual actions based on current status.
				$pending_statuses = [
					StatusEnums::RENTAL_REQUESTED,
					StatusEnums::RENTAL_PENDING_PROVIDER_APPROVAL,
					StatusEnums::SERVICE_REQUESTED,
					StatusEnums::SERVICE_PENDING_PROVIDER_APPROVAL,
				];
				$active_statuses = [
					StatusEnums::RENTAL_CONFIRMED,
					StatusEnums::RENTAL_CHECKED_OUT,
					StatusEnums::RENTAL_ACTIVE,
					StatusEnums::SERVICE_CONFIRMED,
					StatusEnums::SERVICE_IN_PROGRESS,
				];
				$completable = [
					StatusEnums::RENTAL_RETURNED_PENDING_INSPECTION,
					StatusEnums::SERVICE_AWAITING_COMPLETION,
					StatusEnums::SALE_DELIVERED,
				];
				?>

				<?php if ( in_array( $booking->status, $pending_statuses, true ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:8px;">
						<?php wp_nonce_field( 'jqme_booking_approve_' . $booking->id ); ?>
						<input type="hidden" name="action" value="jqme_booking_approve">
						<input type="hidden" name="booking_id" value="<?php echo esc_attr( $booking->id ); ?>">
						<button type="submit" class="button button-primary" style="width:100%;">
							<?php esc_html_e( 'Approve Booking', 'jq-marketplace-engine' ); ?>
						</button>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:8px;">
						<?php wp_nonce_field( 'jqme_booking_decline_' . $booking->id ); ?>
						<input type="hidden" name="action" value="jqme_booking_decline">
						<input type="hidden" name="booking_id" value="<?php echo esc_attr( $booking->id ); ?>">
						<button type="submit" class="button" style="width:100%;">
							<?php esc_html_e( 'Decline Booking', 'jq-marketplace-engine' ); ?>
						</button>
					</form>
				<?php endif; ?>

				<?php if ( in_array( $booking->status, $completable, true ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:8px;">
						<?php wp_nonce_field( 'jqme_booking_complete_' . $booking->id ); ?>
						<input type="hidden" name="action" value="jqme_booking_complete">
						<input type="hidden" name="booking_id" value="<?php echo esc_attr( $booking->id ); ?>">
						<button type="submit" class="button button-primary" style="width:100%;">
							<?php esc_html_e( 'Mark Completed', 'jq-marketplace-engine' ); ?>
						</button>
					</form>
				<?php endif; ?>

				<?php if ( in_array( $booking->status, $active_statuses, true ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:8px;">
						<?php wp_nonce_field( 'jqme_booking_cancel_' . $booking->id ); ?>
						<input type="hidden" name="action" value="jqme_booking_cancel">
						<input type="hidden" name="booking_id" value="<?php echo esc_attr( $booking->id ); ?>">
						<button type="submit" class="button" style="width:100%; color:#d63638;">
							<?php esc_html_e( 'Cancel Booking', 'jq-marketplace-engine' ); ?>
						</button>
					</form>
				<?php endif; ?>

				<!-- Manual status override -->
				<hr>
				<details>
					<summary style="cursor:pointer; font-weight:600;"><?php esc_html_e( 'Manual Status Override', 'jq-marketplace-engine' ); ?></summary>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px;">
						<?php wp_nonce_field( 'jqme_booking_set_status_' . $booking->id ); ?>
						<input type="hidden" name="action" value="jqme_booking_set_status">
						<input type="hidden" name="booking_id" value="<?php echo esc_attr( $booking->id ); ?>">
						<select name="new_status" style="width:100%; margin-bottom:8px;">
							<?php
							// Show relevant statuses based on booking type.
							$relevant_statuses = match ( $booking->booking_type ) {
								StatusEnums::TYPE_EQUIPMENT_RENTAL => StatusEnums::rental_booking_statuses(),
								StatusEnums::TYPE_SERVICE_BOOKING  => StatusEnums::service_booking_statuses(),
								StatusEnums::TYPE_EQUIPMENT_SALE   => StatusEnums::sale_order_statuses(),
								default                            => StatusEnums::rental_booking_statuses(),
							};
							foreach ( $relevant_statuses as $sk => $sl ) : ?>
								<option value="<?php echo esc_attr( $sk ); ?>" <?php selected( $booking->status, $sk ); ?>>
									<?php echo esc_html( $sl ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<textarea name="status_notes" placeholder="<?php esc_attr_e( 'Notes (optional)', 'jq-marketplace-engine' ); ?>" style="width:100%; margin-bottom:8px;" rows="2"></textarea>
						<button type="submit" class="button" style="width:100%;">
							<?php esc_html_e( 'Update Status', 'jq-marketplace-engine' ); ?>
						</button>
					</form>
				</details>
			</div>

			<!-- Timestamps -->
			<div class="card" style="max-width:none; margin-top:15px;">
				<h3 style="margin-top:0;"><?php esc_html_e( 'Timeline', 'jq-marketplace-engine' ); ?></h3>
				<table style="width:100%; font-size:12px;">
					<tr><td><?php esc_html_e( 'Created', 'jq-marketplace-engine' ); ?></td><td><?php echo $booking->created_at ? esc_html( date_i18n( 'M j, Y g:ia', strtotime( $booking->created_at ) ) ) : '—'; ?></td></tr>
					<?php if ( $booking->checked_out_at ) : ?>
						<tr><td><?php esc_html_e( 'Checked Out', 'jq-marketplace-engine' ); ?></td><td><?php echo esc_html( date_i18n( 'M j, Y g:ia', strtotime( $booking->checked_out_at ) ) ); ?></td></tr>
					<?php endif; ?>
					<?php if ( ! empty( $booking->returned_at ) ) : ?>
						<tr><td><?php esc_html_e( 'Returned', 'jq-marketplace-engine' ); ?></td><td><?php echo esc_html( date_i18n( 'M j, Y g:ia', strtotime( $booking->returned_at ) ) ); ?></td></tr>
					<?php endif; ?>
					<?php if ( $booking->completed_at ) : ?>
						<tr><td><?php esc_html_e( 'Completed', 'jq-marketplace-engine' ); ?></td><td><?php echo esc_html( date_i18n( 'M j, Y g:ia', strtotime( $booking->completed_at ) ) ); ?></td></tr>
					<?php endif; ?>
					<?php if ( $booking->cancelled_at ) : ?>
						<tr><td><?php esc_html_e( 'Cancelled', 'jq-marketplace-engine' ); ?></td><td><?php echo esc_html( date_i18n( 'M j, Y g:ia', strtotime( $booking->cancelled_at ) ) ); ?></td></tr>
					<?php endif; ?>
				</table>
			</div>
		</div>
	</div>
</div>
