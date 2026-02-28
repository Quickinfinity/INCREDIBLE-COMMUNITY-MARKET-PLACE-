<?php
/**
 * Payment gateway abstraction.
 *
 * Defines the interface all payment gateways must implement.
 * The plugin ships with a Stripe Connect adapter. Other gateways
 * can be added by extending this abstract class.
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class PaymentGateway {

	/** @var string Gateway identifier. */
	protected string $id = '';

	/** @var string Display name. */
	protected string $name = '';

	/**
	 * Get the gateway ID.
	 */
	public function get_id(): string {
		return $this->id;
	}

	/**
	 * Get the gateway display name.
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Check if the gateway is configured and ready.
	 */
	abstract public function is_ready(): bool;

	/**
	 * Create a connected account for a provider (marketplace onboarding).
	 *
	 * @param int   $provider_id Provider ID.
	 * @param array $data        Account creation data (email, country, etc.).
	 * @return array{account_id: string, onboarding_url: string}|false
	 */
	abstract public function create_connected_account( int $provider_id, array $data ): array|false;

	/**
	 * Get the onboarding/dashboard URL for a connected account.
	 */
	abstract public function get_account_link( string $account_id, string $type = 'onboarding' ): string|false;

	/**
	 * Check if a connected account has completed onboarding.
	 */
	abstract public function is_account_active( string $account_id ): bool;

	/**
	 * Create a payment intent / charge.
	 *
	 * @param array $args {
	 *     @type int    $amount_cents        Total in cents.
	 *     @type string $currency            3-letter currency code.
	 *     @type string $connected_account   Provider's connected account ID.
	 *     @type int    $platform_fee_cents  Platform fee in cents.
	 *     @type string $description         Charge description.
	 *     @type array  $metadata            Key/value metadata.
	 *     @type bool   $capture             Whether to capture immediately (vs authorize only).
	 * }
	 * @return array{id: string, client_secret: string, status: string}|false
	 */
	abstract public function create_payment( array $args ): array|false;

	/**
	 * Capture a previously authorized payment.
	 *
	 * @param string $payment_id      Gateway payment/intent ID.
	 * @param int    $amount_cents    Amount to capture (0 = full amount).
	 * @return array{id: string, status: string, captured_amount: int}|false
	 */
	abstract public function capture_payment( string $payment_id, int $amount_cents = 0 ): array|false;

	/**
	 * Cancel an authorized (uncaptured) payment.
	 */
	abstract public function cancel_payment( string $payment_id ): bool;

	/**
	 * Refund a captured payment.
	 *
	 * @param string $payment_id   Gateway payment ID.
	 * @param int    $amount_cents Amount to refund in cents (0 = full).
	 * @param string $reason       Refund reason.
	 * @return array{id: string, status: string, amount: int}|false
	 */
	abstract public function refund_payment( string $payment_id, int $amount_cents = 0, string $reason = '' ): array|false;

	/**
	 * Create a payout to a connected account.
	 *
	 * @param string $account_id  Connected account ID.
	 * @param int    $amount_cents Amount in cents.
	 * @param string $currency    Currency code.
	 * @return array{id: string, status: string}|false
	 */
	abstract public function create_payout( string $account_id, int $amount_cents, string $currency = 'USD' ): array|false;

	/**
	 * Retrieve payment details from the gateway.
	 */
	abstract public function get_payment( string $payment_id ): array|false;
}
