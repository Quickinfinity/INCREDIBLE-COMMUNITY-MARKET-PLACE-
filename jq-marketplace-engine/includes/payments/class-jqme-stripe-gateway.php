<?php
/**
 * Stripe Connect gateway adapter.
 *
 * Implements the PaymentGateway interface for Stripe Connect.
 * Uses Stripe's HTTP API directly (no SDK dependency) to keep the plugin lightweight.
 * Install the Stripe PHP SDK via Composer for production use.
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME\Payments;

use JQME\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StripeGateway extends PaymentGateway {

	protected string $id   = 'stripe';
	protected string $name = 'Stripe Connect';

	private string $secret_key      = '';
	private string $publishable_key = '';
	private string $api_base        = 'https://api.stripe.com/v1';

	public function __construct() {
		$this->secret_key      = get_option( 'jqme_stripe_secret_key', '' );
		$this->publishable_key = get_option( 'jqme_stripe_publishable_key', '' );
	}

	public function is_ready(): bool {
		return ! empty( $this->secret_key ) && ! empty( $this->publishable_key );
	}

	public function get_publishable_key(): string {
		return $this->publishable_key;
	}

	/* ---------------------------------------------------------------
	 * CONNECTED ACCOUNTS
	 * ------------------------------------------------------------- */

	public function create_connected_account( int $provider_id, array $data ): array|false {
		$response = $this->api_request( 'accounts', [
			'type'         => 'express',
			'country'      => $data['country'] ?? 'US',
			'email'        => $data['email'] ?? '',
			'capabilities' => [
				'card_payments' => [ 'requested' => 'true' ],
				'transfers'     => [ 'requested' => 'true' ],
			],
			'metadata'     => [
				'provider_id' => $provider_id,
				'platform'    => 'jq-marketplace-engine',
			],
		] );

		if ( ! $response || ! empty( $response['error'] ) ) {
			return false;
		}

		$account_id = $response['id'] ?? '';
		if ( ! $account_id ) {
			return false;
		}

		// Generate onboarding link.
		$link = $this->get_account_link( $account_id, 'account_onboarding' );

		return [
			'account_id'     => $account_id,
			'onboarding_url' => $link ?: '',
		];
	}

	public function get_account_link( string $account_id, string $type = 'account_onboarding' ): string|false {
		$return_url  = home_url( '/provider-dashboard/?payout_setup=complete' );
		$refresh_url = home_url( '/provider-dashboard/?payout_setup=refresh' );

		$response = $this->api_request( 'account_links', [
			'account'     => $account_id,
			'type'        => $type,
			'return_url'  => $return_url,
			'refresh_url' => $refresh_url,
		] );

		return $response['url'] ?? false;
	}

	public function is_account_active( string $account_id ): bool {
		$response = $this->api_request( "accounts/{$account_id}", [], 'GET' );

		if ( ! $response || ! empty( $response['error'] ) ) {
			return false;
		}

		return ! empty( $response['charges_enabled'] ) && ! empty( $response['payouts_enabled'] );
	}

	/* ---------------------------------------------------------------
	 * PAYMENTS
	 * ------------------------------------------------------------- */

	public function create_payment( array $args ): array|false {
		$params = [
			'amount'               => $args['amount_cents'],
			'currency'             => strtolower( $args['currency'] ?? 'usd' ),
			'description'          => $args['description'] ?? '',
			'capture_method'       => ! empty( $args['capture'] ) ? 'automatic' : 'manual',
			'metadata'             => $args['metadata'] ?? [],
		];

		// Stripe Connect: route payment to connected account with platform fee.
		if ( ! empty( $args['connected_account'] ) ) {
			$params['transfer_data'] = [
				'destination' => $args['connected_account'],
			];
			if ( ! empty( $args['platform_fee_cents'] ) ) {
				$params['application_fee_amount'] = $args['platform_fee_cents'];
			}
		}

		$response = $this->api_request( 'payment_intents', $params );

		if ( ! $response || ! empty( $response['error'] ) ) {
			return false;
		}

		return [
			'id'            => $response['id'],
			'client_secret' => $response['client_secret'] ?? '',
			'status'        => $response['status'],
		];
	}

	public function capture_payment( string $payment_id, int $amount_cents = 0 ): array|false {
		$params = [];
		if ( $amount_cents > 0 ) {
			$params['amount_to_capture'] = $amount_cents;
		}

		$response = $this->api_request( "payment_intents/{$payment_id}/capture", $params );

		if ( ! $response || ! empty( $response['error'] ) ) {
			return false;
		}

		return [
			'id'              => $response['id'],
			'status'          => $response['status'],
			'captured_amount' => $response['amount_received'] ?? $response['amount'],
		];
	}

	public function cancel_payment( string $payment_id ): bool {
		$response = $this->api_request( "payment_intents/{$payment_id}/cancel", [] );
		return ! empty( $response['id'] ) && 'canceled' === ( $response['status'] ?? '' );
	}

	public function refund_payment( string $payment_id, int $amount_cents = 0, string $reason = '' ): array|false {
		$params = [ 'payment_intent' => $payment_id ];

		if ( $amount_cents > 0 ) {
			$params['amount'] = $amount_cents;
		}
		if ( $reason ) {
			$params['reason'] = $reason; // duplicate|fraudulent|requested_by_customer
		}

		$response = $this->api_request( 'refunds', $params );

		if ( ! $response || ! empty( $response['error'] ) ) {
			return false;
		}

		return [
			'id'     => $response['id'],
			'status' => $response['status'],
			'amount' => $response['amount'],
		];
	}

	public function create_payout( string $account_id, int $amount_cents, string $currency = 'USD' ): array|false {
		$response = $this->api_request( 'payouts', [
			'amount'   => $amount_cents,
			'currency' => strtolower( $currency ),
		], 'POST', $account_id );

		if ( ! $response || ! empty( $response['error'] ) ) {
			return false;
		}

		return [
			'id'     => $response['id'],
			'status' => $response['status'],
		];
	}

	public function get_payment( string $payment_id ): array|false {
		$response = $this->api_request( "payment_intents/{$payment_id}", [], 'GET' );

		if ( ! $response || ! empty( $response['error'] ) ) {
			return false;
		}

		return $response;
	}

	/* ---------------------------------------------------------------
	 * HTTP HELPER
	 * ------------------------------------------------------------- */

	/**
	 * Make an API request to Stripe.
	 *
	 * @param string      $endpoint      API endpoint (relative).
	 * @param array       $params        Request parameters.
	 * @param string      $method        HTTP method.
	 * @param string|null $on_behalf_of  Connected account ID for on-behalf-of requests.
	 */
	private function api_request( string $endpoint, array $params = [], string $method = 'POST', ?string $on_behalf_of = null ): array|false {
		$url = "{$this->api_base}/{$endpoint}";

		$headers = [
			'Authorization' => 'Bearer ' . $this->secret_key,
			'Content-Type'  => 'application/x-www-form-urlencoded',
		];

		if ( $on_behalf_of ) {
			$headers['Stripe-Account'] = $on_behalf_of;
		}

		$args = [
			'method'  => $method,
			'headers' => $headers,
			'timeout' => 30,
		];

		if ( 'GET' === $method && ! empty( $params ) ) {
			$url .= '?' . http_build_query( $this->flatten_params( $params ) );
		} elseif ( ! empty( $params ) ) {
			$args['body'] = $this->flatten_params( $params );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			error_log( 'JQME Stripe Error: ' . $response->get_error_message() );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['error'] ) ) {
			error_log( 'JQME Stripe API Error: ' . ( $body['error']['message'] ?? 'Unknown' ) );
		}

		return $body ?: false;
	}

	/**
	 * Flatten nested arrays for Stripe's form-encoded API.
	 * e.g. ['transfer_data' => ['destination' => 'acct_xxx']] => ['transfer_data[destination]' => 'acct_xxx']
	 */
	private function flatten_params( array $params, string $prefix = '' ): array {
		$result = [];
		foreach ( $params as $key => $value ) {
			$full_key = $prefix ? "{$prefix}[{$key}]" : $key;
			if ( is_array( $value ) ) {
				$result = array_merge( $result, $this->flatten_params( $value, $full_key ) );
			} else {
				$result[ $full_key ] = $value;
			}
		}
		return $result;
	}
}
