<?php
/**
 * Admin settings page — tabbed interface for all 18 setting groups.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JQME\Settings\Settings;

// Handle form submission.
if ( isset( $_POST['jqme_settings_nonce'] ) && wp_verify_nonce( $_POST['jqme_settings_nonce'], 'jqme_save_settings' ) ) {
	if ( current_user_can( 'jqme_manage_settings' ) ) {
		$group  = sanitize_text_field( $_POST['jqme_settings_group'] ?? '' );
		$values = $_POST['jqme'] ?? [];

		if ( $group && is_array( $values ) ) {
			$sanitized = jqme_sanitize_settings_group( $group, $values );
			Settings::update( $group, $sanitized );
			Settings::flush_cache();
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'jq-marketplace-engine' ) . '</p></div>';
		}
	}
}

$groups       = Settings::all_group_keys();
$labels       = Settings::group_labels();
$current_tab  = sanitize_text_field( $_GET['tab'] ?? 'global' );

if ( ! in_array( $current_tab, $groups, true ) ) {
	$current_tab = 'global';
}

$settings = Settings::get_group( $current_tab );
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Marketplace Settings', 'jq-marketplace-engine' ); ?></h1>

	<nav class="nav-tab-wrapper">
		<?php foreach ( $groups as $group_key ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-settings&tab=' . $group_key ) ); ?>"
			   class="nav-tab <?php echo $current_tab === $group_key ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $labels[ $group_key ] ?? ucwords( str_replace( '_', ' ', $group_key ) ) ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<form method="post" action="">
		<?php wp_nonce_field( 'jqme_save_settings', 'jqme_settings_nonce' ); ?>
		<input type="hidden" name="jqme_settings_group" value="<?php echo esc_attr( $current_tab ); ?>">

		<table class="form-table" role="presentation">
			<?php
			$field_defs = jqme_get_field_definitions( $current_tab );
			foreach ( $field_defs as $key => $field ) :
				$value = $settings[ $key ] ?? $field['default'] ?? '';
			?>
				<tr>
					<th scope="row">
						<label for="jqme_<?php echo esc_attr( $key ); ?>">
							<?php echo esc_html( $field['label'] ); ?>
						</label>
					</th>
					<td>
						<?php jqme_render_settings_field( $key, $field, $value ); ?>
						<?php if ( ! empty( $field['description'] ) ) : ?>
							<p class="description"><?php echo esc_html( $field['description'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>

		<?php submit_button( __( 'Save Settings', 'jq-marketplace-engine' ) ); ?>
	</form>
</div>

<?php
/**
 * Render a single settings field based on its type.
 */
function jqme_render_settings_field( string $key, array $field, mixed $value ): void {
	$name = "jqme[{$key}]";
	$id   = "jqme_{$key}";
	$type = $field['type'] ?? 'text';

	switch ( $type ) {
		case 'toggle':
			printf(
				'<label><input type="checkbox" name="%s" id="%s" value="1" %s> %s</label>',
				esc_attr( $name ),
				esc_attr( $id ),
				checked( $value, true, false ),
				esc_html( $field['toggle_label'] ?? __( 'Enabled', 'jq-marketplace-engine' ) )
			);
			break;

		case 'number':
			printf(
				'<input type="number" name="%s" id="%s" value="%s" class="regular-text" step="%s" min="%s" max="%s">',
				esc_attr( $name ),
				esc_attr( $id ),
				esc_attr( $value ),
				esc_attr( $field['step'] ?? 'any' ),
				esc_attr( $field['min'] ?? '0' ),
				esc_attr( $field['max'] ?? '' )
			);
			break;

		case 'select':
			printf( '<select name="%s" id="%s">', esc_attr( $name ), esc_attr( $id ) );
			foreach ( ( $field['options'] ?? [] ) as $opt_value => $opt_label ) {
				printf(
					'<option value="%s" %s>%s</option>',
					esc_attr( $opt_value ),
					selected( $value, $opt_value, false ),
					esc_html( $opt_label )
				);
			}
			echo '</select>';
			break;

		case 'textarea':
			printf(
				'<textarea name="%s" id="%s" class="large-text" rows="4">%s</textarea>',
				esc_attr( $name ),
				esc_attr( $id ),
				esc_textarea( $value )
			);
			break;

		case 'text':
		default:
			printf(
				'<input type="text" name="%s" id="%s" value="%s" class="regular-text">',
				esc_attr( $name ),
				esc_attr( $id ),
				esc_attr( $value )
			);
			break;
	}
}

/**
 * Get field definitions for each setting group.
 * This maps each setting key to its type, label, and UI metadata.
 */
function jqme_get_field_definitions( string $group ): array {
	$defs = [
		'global' => [
			'enable_equipment_rentals'       => [ 'type' => 'toggle', 'label' => __( 'Enable Equipment Rentals', 'jq-marketplace-engine' ) ],
			'enable_equipment_sales'         => [ 'type' => 'toggle', 'label' => __( 'Enable Equipment Sales', 'jq-marketplace-engine' ) ],
			'enable_service_bookings'        => [ 'type' => 'toggle', 'label' => __( 'Enable Service Bookings', 'jq-marketplace-engine' ) ],
			'platform_fee_percent'           => [ 'type' => 'number', 'label' => __( 'Platform Fee (%)', 'jq-marketplace-engine' ), 'step' => '0.1', 'min' => '0', 'max' => '50' ],
			'processing_fee_paid_by'         => [ 'type' => 'select', 'label' => __( 'Processing Fee Paid By', 'jq-marketplace-engine' ), 'options' => [ 'customer' => 'Customer', 'provider' => 'Provider', 'split' => 'Split' ] ],
			'default_currency'               => [ 'type' => 'text', 'label' => __( 'Default Currency', 'jq-marketplace-engine' ) ],
			'tax_behavior'                   => [ 'type' => 'select', 'label' => __( 'Tax Behavior', 'jq-marketplace-engine' ), 'options' => [ 'none' => 'None', 'inclusive' => 'Inclusive', 'exclusive' => 'Exclusive' ] ],
			'default_timezone'               => [ 'type' => 'text', 'label' => __( 'Default Timezone', 'jq-marketplace-engine' ) ],
			'default_distance_unit'          => [ 'type' => 'select', 'label' => __( 'Distance Unit', 'jq-marketplace-engine' ), 'options' => [ 'miles' => 'Miles', 'km' => 'Kilometers' ] ],
			'provider_approval_required'     => [ 'type' => 'toggle', 'label' => __( 'Provider Approval Required', 'jq-marketplace-engine' ) ],
			'customer_verification_required' => [ 'type' => 'toggle', 'label' => __( 'Customer Verification Required', 'jq-marketplace-engine' ) ],
			'admin_approval_first_listing'   => [ 'type' => 'toggle', 'label' => __( 'Admin Must Approve First Listing', 'jq-marketplace-engine' ) ],
			'admin_approval_every_listing'   => [ 'type' => 'toggle', 'label' => __( 'Admin Must Approve Every Listing', 'jq-marketplace-engine' ) ],
			'instant_book_allowed'           => [ 'type' => 'toggle', 'label' => __( 'Instant Book Allowed', 'jq-marketplace-engine' ) ],
			'request_to_book_default'        => [ 'type' => 'toggle', 'label' => __( 'Request-to-Book Default', 'jq-marketplace-engine' ) ],
			'platform_name'                  => [ 'type' => 'text', 'label' => __( 'Platform Name', 'jq-marketplace-engine' ) ],
			'platform_facilitator_disclaimer' => [ 'type' => 'textarea', 'label' => __( 'Facilitator Disclaimer', 'jq-marketplace-engine' ) ],
		],
		'payments' => [
			'payment_gateway'                  => [ 'type' => 'select', 'label' => __( 'Payment Gateway', 'jq-marketplace-engine' ), 'options' => [ 'stripe' => 'Stripe', 'paypal' => 'PayPal', 'manual' => 'Manual' ] ],
			'connected_account_required'       => [ 'type' => 'toggle', 'label' => __( 'Connected Account Required Before Publishing', 'jq-marketplace-engine' ) ],
			'payout_delay_days'                => [ 'type' => 'number', 'label' => __( 'Payout Delay (Days)', 'jq-marketplace-engine' ), 'step' => '1', 'min' => '0' ],
			'rolling_reserve_enabled'          => [ 'type' => 'toggle', 'label' => __( 'Rolling Reserve', 'jq-marketplace-engine' ) ],
			'rolling_reserve_percent'          => [ 'type' => 'number', 'label' => __( 'Rolling Reserve %', 'jq-marketplace-engine' ), 'step' => '1', 'min' => '0', 'max' => '100' ],
			'payout_hold_on_flagged'           => [ 'type' => 'toggle', 'label' => __( 'Hold Payouts on Flagged Accounts', 'jq-marketplace-engine' ) ],
			'partial_capture_allowed'          => [ 'type' => 'toggle', 'label' => __( 'Partial Capture Allowed', 'jq-marketplace-engine' ) ],
			'deposit_auth_vs_capture_default'  => [ 'type' => 'select', 'label' => __( 'Deposit Default Mode', 'jq-marketplace-engine' ), 'options' => [ 'authorize' => 'Authorize Only', 'capture' => 'Capture Immediately' ] ],
			'deposit_auto_release_days'        => [ 'type' => 'number', 'label' => __( 'Deposit Auto-Release (Days)', 'jq-marketplace-engine' ), 'step' => '1', 'min' => '1' ],
			'failed_payment_retry_count'       => [ 'type' => 'number', 'label' => __( 'Failed Payment Retry Count', 'jq-marketplace-engine' ), 'step' => '1', 'min' => '0' ],
			'failed_payment_retry_interval_hours' => [ 'type' => 'number', 'label' => __( 'Retry Interval (Hours)', 'jq-marketplace-engine' ), 'step' => '1', 'min' => '1' ],
		],
		'claims' => [
			'claim_window_hours'               => [ 'type' => 'number', 'label' => __( 'Claim Window (Hours)', 'jq-marketplace-engine' ), 'step' => '1', 'min' => '1' ],
			'claim_evidence_required'          => [ 'type' => 'toggle', 'label' => __( 'Evidence Required', 'jq-marketplace-engine' ) ],
			'claim_min_photo_count'            => [ 'type' => 'number', 'label' => __( 'Minimum Photo Count', 'jq-marketplace-engine' ), 'step' => '1', 'min' => '0' ],
			'admin_mediation_mode'             => [ 'type' => 'toggle', 'label' => __( 'Admin Mediation Mode', 'jq-marketplace-engine' ) ],
			'facilitator_disclaimer'           => [ 'type' => 'textarea', 'label' => __( 'Claims Disclaimer Text', 'jq-marketplace-engine' ) ],
			'customer_response_window_hours'   => [ 'type' => 'number', 'label' => __( 'Customer Response Window (Hours)', 'jq-marketplace-engine' ), 'step' => '1', 'min' => '1' ],
			'provider_rebuttal_window_hours'   => [ 'type' => 'number', 'label' => __( 'Provider Rebuttal Window (Hours)', 'jq-marketplace-engine' ), 'step' => '1', 'min' => '1' ],
			'auto_close_claim_days'            => [ 'type' => 'number', 'label' => __( 'Auto-Close Claims After (Days)', 'jq-marketplace-engine' ), 'step' => '1', 'min' => '1' ],
			'max_claim_cap_percent'            => [ 'type' => 'number', 'label' => __( 'Max Claim Cap (% of Deposit)', 'jq-marketplace-engine' ), 'step' => '1', 'min' => '0', 'max' => '100' ],
			'documentation_retention_days'     => [ 'type' => 'number', 'label' => __( 'Document Retention (Days)', 'jq-marketplace-engine' ), 'step' => '1', 'min' => '30' ],
		],
		'late_return' => [
			'grace_period_hours'               => [ 'type' => 'number', 'label' => __( 'Grace Period (Hours)', 'jq-marketplace-engine' ), 'step' => '0.5', 'min' => '0' ],
			'late_fee_formula_type'            => [ 'type' => 'select', 'label' => __( 'Late Fee Formula', 'jq-marketplace-engine' ), 'options' => [ 'flat' => 'Flat', 'hourly' => 'Hourly', 'daily' => 'Daily', 'percent' => 'Percent of Day Rate' ] ],
			'late_fee_flat_amount'             => [ 'type' => 'number', 'label' => __( 'Flat Late Fee ($)', 'jq-marketplace-engine' ), 'step' => '0.01', 'min' => '0' ],
			'late_fee_hourly_amount'           => [ 'type' => 'number', 'label' => __( 'Hourly Late Fee ($)', 'jq-marketplace-engine' ), 'step' => '0.01', 'min' => '0' ],
			'late_fee_daily_amount'            => [ 'type' => 'number', 'label' => __( 'Daily Late Fee ($)', 'jq-marketplace-engine' ), 'step' => '0.01', 'min' => '0' ],
			'late_fee_percent_of_day_rate'     => [ 'type' => 'number', 'label' => __( 'Late Fee (% of Day Rate)', 'jq-marketplace-engine' ), 'step' => '1', 'min' => '0', 'max' => '200' ],
			'max_late_fee_cap'                 => [ 'type' => 'number', 'label' => __( 'Max Late Fee Cap ($)', 'jq-marketplace-engine' ), 'step' => '0.01', 'min' => '0', 'description' => __( '0 = no cap', 'jq-marketplace-engine' ) ],
			'auto_extension_allowed'           => [ 'type' => 'toggle', 'label' => __( 'Auto Extension Allowed', 'jq-marketplace-engine' ) ],
			'auto_extension_rate_multiplier'   => [ 'type' => 'number', 'label' => __( 'Auto Extension Rate Multiplier', 'jq-marketplace-engine' ), 'step' => '0.1', 'min' => '1' ],
			'force_provider_approval_extension' => [ 'type' => 'toggle', 'label' => __( 'Require Provider Approval for Extension', 'jq-marketplace-engine' ) ],
			'overdue_reminder_cadence_hours'   => [ 'type' => 'number', 'label' => __( 'Overdue Reminder Cadence (Hours)', 'jq-marketplace-engine' ), 'step' => '1', 'min' => '1' ],
		],
		'cancellation' => [
			'provider_cancel_window_hours'         => [ 'type' => 'number', 'label' => __( 'Provider Cancel Window (Hours)', 'jq-marketplace-engine' ), 'step' => '1', 'min' => '0' ],
			'customer_cancel_full_refund_hours'     => [ 'type' => 'number', 'label' => __( 'Full Refund Window (Hours Before Start)', 'jq-marketplace-engine' ), 'step' => '1', 'min' => '0' ],
			'customer_cancel_partial_refund_hours'  => [ 'type' => 'number', 'label' => __( 'Partial Refund Window (Hours)', 'jq-marketplace-engine' ), 'step' => '1', 'min' => '0' ],
			'customer_cancel_partial_refund_percent' => [ 'type' => 'number', 'label' => __( 'Partial Refund %', 'jq-marketplace-engine' ), 'step' => '1', 'min' => '0', 'max' => '100' ],
			'no_show_definition_minutes'           => [ 'type' => 'number', 'label' => __( 'No-Show After (Minutes)', 'jq-marketplace-engine' ), 'step' => '1', 'min' => '1' ],
			'no_show_customer_penalty_percent'     => [ 'type' => 'number', 'label' => __( 'Customer No-Show Penalty %', 'jq-marketplace-engine' ), 'step' => '1', 'min' => '0', 'max' => '100' ],
			'reschedule_allowed'                   => [ 'type' => 'toggle', 'label' => __( 'Rescheduling Allowed', 'jq-marketplace-engine' ) ],
			'reschedule_deadline_hours'            => [ 'type' => 'number', 'label' => __( 'Reschedule Deadline (Hours Before)', 'jq-marketplace-engine' ), 'step' => '1', 'min' => '0' ],
			'one_time_courtesy_reschedule'         => [ 'type' => 'toggle', 'label' => __( 'One-Time Courtesy Reschedule', 'jq-marketplace-engine' ) ],
		],
		'reviews' => [
			'mandatory_two_way_reviews'       => [ 'type' => 'toggle', 'label' => __( 'Mandatory Two-Way Reviews', 'jq-marketplace-engine' ) ],
			'review_window_days'              => [ 'type' => 'number', 'label' => __( 'Review Window (Days)', 'jq-marketplace-engine' ), 'step' => '1', 'min' => '1' ],
			'review_prompt_cadence_days'      => [ 'type' => 'number', 'label' => __( 'Review Prompt Cadence (Days)', 'jq-marketplace-engine' ), 'step' => '1', 'min' => '1' ],
			'min_rating_warning_threshold'    => [ 'type' => 'number', 'label' => __( 'Warning Threshold (Stars)', 'jq-marketplace-engine' ), 'step' => '0.5', 'min' => '1', 'max' => '5' ],
			'auto_flag_low_rated_threshold'   => [ 'type' => 'number', 'label' => __( 'Auto-Flag Threshold (Stars)', 'jq-marketplace-engine' ), 'step' => '0.5', 'min' => '1', 'max' => '5' ],
			'hidden_pending_mutual_review'    => [ 'type' => 'toggle', 'label' => __( 'Hide Reviews Until Both Submitted', 'jq-marketplace-engine' ) ],
			'public_review_visibility'        => [ 'type' => 'select', 'label' => __( 'Review Visibility', 'jq-marketplace-engine' ), 'options' => [ 'after_mutual' => 'After Mutual Submission', 'immediate' => 'Immediate', 'admin_approved' => 'Admin Approved' ] ],
		],
		'delivery' => [
			'global_delivery_enabled'           => [ 'type' => 'toggle', 'label' => __( 'Delivery Enabled Globally', 'jq-marketplace-engine' ) ],
			'provider_delivery_override_allowed' => [ 'type' => 'toggle', 'label' => __( 'Providers Can Override Delivery', 'jq-marketplace-engine' ) ],
			'default_delivery_radius_miles'     => [ 'type' => 'number', 'label' => __( 'Default Delivery Radius (Miles)', 'jq-marketplace-engine' ), 'step' => '1', 'min' => '0' ],
			'delivery_fee_formula'              => [ 'type' => 'select', 'label' => __( 'Delivery Fee Formula', 'jq-marketplace-engine' ), 'options' => [ 'flat' => 'Flat Fee', 'per_mile' => 'Per Mile', 'tiered' => 'Tiered' ] ],
			'mileage_fee_per_mile'              => [ 'type' => 'number', 'label' => __( 'Mileage Fee ($/Mile)', 'jq-marketplace-engine' ), 'step' => '0.01', 'min' => '0' ],
			'minimum_delivery_charge'           => [ 'type' => 'number', 'label' => __( 'Minimum Delivery Charge ($)', 'jq-marketplace-engine' ), 'step' => '0.01', 'min' => '0' ],
			'delivery_scheduling_buffer_hours'  => [ 'type' => 'number', 'label' => __( 'Scheduling Buffer (Hours)', 'jq-marketplace-engine' ), 'step' => '1', 'min' => '0' ],
			'pickup_instructions_required'      => [ 'type' => 'toggle', 'label' => __( 'Pickup Instructions Required', 'jq-marketplace-engine' ) ],
			'return_instructions_required'      => [ 'type' => 'toggle', 'label' => __( 'Return Instructions Required', 'jq-marketplace-engine' ) ],
		],
	];

	return $defs[ $group ] ?? jqme_auto_field_definitions( $group );
}

/**
 * Auto-generate field definitions from the settings defaults for groups
 * that don't have explicit UI definitions yet.
 */
function jqme_auto_field_definitions( string $group ): array {
	$defaults = Settings::defaults( $group );
	$fields   = [];

	foreach ( $defaults as $key => $value ) {
		$label = ucwords( str_replace( '_', ' ', $key ) );

		if ( is_bool( $value ) ) {
			$fields[ $key ] = [ 'type' => 'toggle', 'label' => $label ];
		} elseif ( is_int( $value ) || is_float( $value ) ) {
			$fields[ $key ] = [ 'type' => 'number', 'label' => $label, 'step' => is_float( $value ) ? '0.01' : '1', 'min' => '0' ];
		} elseif ( is_array( $value ) ) {
			$fields[ $key ] = [ 'type' => 'textarea', 'label' => $label, 'description' => __( 'JSON format', 'jq-marketplace-engine' ) ];
		} else {
			$fields[ $key ] = [ 'type' => 'text', 'label' => $label ];
		}
	}

	return $fields;
}

/**
 * Sanitize settings values based on their expected types.
 */
function jqme_sanitize_settings_group( string $group, array $raw ): array {
	$defaults  = Settings::defaults( $group );
	$sanitized = [];

	foreach ( $defaults as $key => $default_value ) {
		if ( is_bool( $default_value ) ) {
			$sanitized[ $key ] = isset( $raw[ $key ] ) && $raw[ $key ];
		} elseif ( is_int( $default_value ) ) {
			$sanitized[ $key ] = isset( $raw[ $key ] ) ? intval( $raw[ $key ] ) : $default_value;
		} elseif ( is_float( $default_value ) ) {
			$sanitized[ $key ] = isset( $raw[ $key ] ) ? floatval( $raw[ $key ] ) : $default_value;
		} elseif ( is_array( $default_value ) ) {
			if ( isset( $raw[ $key ] ) && is_string( $raw[ $key ] ) ) {
				$decoded = json_decode( stripslashes( $raw[ $key ] ), true );
				$sanitized[ $key ] = is_array( $decoded ) ? $decoded : $default_value;
			} elseif ( isset( $raw[ $key ] ) && is_array( $raw[ $key ] ) ) {
				$sanitized[ $key ] = array_map( 'sanitize_text_field', $raw[ $key ] );
			} else {
				$sanitized[ $key ] = $default_value;
			}
		} else {
			$sanitized[ $key ] = isset( $raw[ $key ] ) ? sanitize_text_field( $raw[ $key ] ) : $default_value;
		}
	}

	return $sanitized;
}
