<?php
/**
 * Booking model — CRUD and business logic for all booking types.
 *
 * Handles rental bookings, service bookings, and sale orders.
 * Manages the full lifecycle: request → approval → payment → fulfillment → completion.
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME\Bookings;

use JQME\Core;
use JQME\AuditLogger;
use JQME\StatusEnums;
use JQME\Settings\Settings;
use JQME\Payments\FeeCalculator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Booking {

	/**
	 * Get a booking by ID.
	 */
	public static function get( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . Core::table( 'bookings' ) . " WHERE id = %d", $id
		) ) ?: null;
	}

	/**
	 * Get a booking by booking number.
	 */
	public static function get_by_number( string $number ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . Core::table( 'bookings' ) . " WHERE booking_number = %s", $number
		) ) ?: null;
	}

	/**
	 * Create a new booking request.
	 *
	 * @param string $booking_type equipment_rental|equipment_sale|service_booking
	 * @param array  $data         Booking data.
	 * @return int|false Booking ID or false.
	 */
	public static function create( string $booking_type, array $data ): int|false {
		global $wpdb;

		$listing_id  = absint( $data['listing_id'] ?? 0 );
		$customer_id = absint( $data['customer_id'] ?? get_current_user_id() );
		$provider_id = absint( $data['provider_id'] ?? 0 );

		if ( ! $listing_id || ! $customer_id || ! $provider_id ) {
			return false;
		}

		// Prevent booking own listing.
		$provider = \JQME\Providers\Provider::get( $provider_id );
		if ( $provider && (int) $provider->user_id === $customer_id ) {
			return false;
		}

		$booking_number = self::generate_booking_number();

		// Calculate fees.
		$fees = FeeCalculator::calculate( [
			'subtotal'       => floatval( $data['subtotal'] ?? 0 ),
			'delivery_fee'   => floatval( $data['delivery_fee'] ?? 0 ),
			'travel_fee'     => floatval( $data['travel_fee'] ?? 0 ),
			'shipping_fee'   => floatval( $data['shipping_fee'] ?? 0 ),
			'deposit_amount' => floatval( $data['deposit_amount'] ?? 0 ),
			'discount'       => floatval( $data['discount'] ?? 0 ),
			'listing_type'   => $booking_type,
		] );

		// Determine initial status.
		$initial_status = Settings::get( 'global', 'request_to_book_default' )
			? StatusEnums::RENTAL_REQUESTED
			: StatusEnums::RENTAL_CONFIRMED;

		$insert = [
			'booking_number'            => $booking_number,
			'booking_type'              => $booking_type,
			'listing_id'                => $listing_id,
			'provider_id'               => $provider_id,
			'customer_id'               => $customer_id,
			'status'                    => $initial_status,
			'date_start'                => $data['date_start'] ?? null,
			'date_end'                  => $data['date_end'] ?? null,
			'fulfillment_mode'          => sanitize_text_field( $data['fulfillment_mode'] ?? 'pickup' ),
			'delivery_address'          => sanitize_textarea_field( $data['delivery_address'] ?? '' ),
			'delivery_fee'              => $fees['delivery_fee'],
			'shipping_fee'              => $fees['shipping_fee'],
			'travel_fee'                => $fees['travel_fee'],
			'subtotal'                  => $fees['subtotal'],
			'discount_amount'           => $fees['discount'],
			'platform_fee'              => $fees['platform_fee'],
			'processing_fee'            => $fees['customer_processing_fee'],
			'deposit_amount'            => $fees['deposit_amount'],
			'total_amount'              => $fees['customer_total'],
			'provider_payout'           => $fees['provider_payout'],
			'currency'                  => $fees['currency'],
			'customer_notes'            => sanitize_textarea_field( $data['customer_notes'] ?? '' ),
			'platform_terms_accepted'   => ! empty( $data['platform_terms_accepted'] ) ? 1 : 0,
			'customer_contract_accepted' => ! empty( $data['customer_contract_accepted'] ) ? 1 : 0,
			'created_at'                => current_time( 'mysql' ),
			'updated_at'                => current_time( 'mysql' ),
		];

		$result = $wpdb->insert( Core::table( 'bookings' ), $insert );
		if ( false === $result ) {
			return false;
		}

		$booking_id = (int) $wpdb->insert_id;

		// Create booking line item.
		$wpdb->insert( Core::table( 'booking_items' ), [
			'booking_id'  => $booking_id,
			'listing_id'  => $listing_id,
			'item_type'   => 'primary',
			'description' => sanitize_text_field( $data['item_description'] ?? '' ),
			'quantity'    => 1,
			'unit_price'  => $fees['subtotal'],
			'total_price' => $fees['subtotal'],
			'created_at'  => current_time( 'mysql' ),
		] );

		AuditLogger::log( 'booking_created', 'booking', $booking_id, null, $initial_status );

		do_action( 'jqme_booking_created', $booking_id, $booking_type );

		return $booking_id;
	}

	/**
	 * Transition booking status with validation and audit logging.
	 */
	public static function set_status( int $booking_id, string $new_status, ?string $context = null ): bool {
		global $wpdb;

		$booking = self::get( $booking_id );
		if ( ! $booking ) {
			return false;
		}

		$old_status = $booking->status;
		if ( $old_status === $new_status ) {
			return true;
		}

		$update = [
			'status'     => $new_status,
			'updated_at' => current_time( 'mysql' ),
		];

		// Set timestamps for lifecycle events.
		$now = current_time( 'mysql' );
		switch ( $new_status ) {
			case StatusEnums::RENTAL_CHECKED_OUT:
				$update['checked_out_at'] = $now;
				break;
			case StatusEnums::RENTAL_COMPLETED:
			case StatusEnums::SERVICE_COMPLETED:
			case StatusEnums::SALE_COMPLETED:
				$update['completed_at'] = $now;
				break;
			case 'returned_pending_inspection':
				$update['returned_at'] = $now;
				break;
		}

		// Track cancellation.
		if ( str_starts_with( $new_status, 'cancelled_' ) ) {
			$update['cancelled_at'] = $now;
			if ( str_ends_with( $new_status, '_customer' ) ) {
				$update['cancelled_by'] = 'customer';
			} elseif ( str_ends_with( $new_status, '_provider' ) ) {
				$update['cancelled_by'] = 'provider';
			}
			if ( $context ) {
				$update['cancellation_reason'] = sanitize_textarea_field( $context );
			}
		}

		$result = $wpdb->update(
			Core::table( 'bookings' ),
			$update,
			[ 'id' => $booking_id ]
		);

		if ( false === $result ) {
			return false;
		}

		AuditLogger::status_change( 'booking', $booking_id, $old_status, $new_status, $context );

		do_action( 'jqme_booking_status_changed', $booking_id, $old_status, $new_status );
		do_action( "jqme_booking_{$new_status}", $booking_id, $old_status );

		return true;
	}

	/**
	 * Provider approves a booking request.
	 */
	public static function approve( int $booking_id ): bool {
		$booking = self::get( $booking_id );
		if ( ! $booking ) {
			return false;
		}

		$approvable = [ StatusEnums::RENTAL_REQUESTED, StatusEnums::RENTAL_PENDING_PROVIDER_APPROVAL,
			StatusEnums::SERVICE_REQUESTED, StatusEnums::SERVICE_PENDING_PROVIDER_APPROVAL ];

		if ( ! in_array( $booking->status, $approvable, true ) ) {
			return false;
		}

		return self::set_status( $booking_id, StatusEnums::RENTAL_APPROVED_PENDING_PAYMENT );
	}

	/**
	 * Provider declines a booking request.
	 */
	public static function decline( int $booking_id, string $reason = '' ): bool {
		$cancelled_status = StatusEnums::RENTAL_CANCELLED_BY_PROVIDER;
		return self::set_status( $booking_id, $cancelled_status, $reason );
	}

	/**
	 * Customer cancels a booking.
	 */
	public static function cancel_by_customer( int $booking_id, string $reason = '' ): bool {
		return self::set_status( $booking_id, StatusEnums::RENTAL_CANCELLED_BY_CUSTOMER, $reason );
	}

	/**
	 * Mark a booking as confirmed (payment received).
	 */
	public static function confirm( int $booking_id ): bool {
		return self::set_status( $booking_id, StatusEnums::RENTAL_CONFIRMED );
	}

	/**
	 * Mark equipment as checked out.
	 */
	public static function check_out( int $booking_id ): bool {
		return self::set_status( $booking_id, StatusEnums::RENTAL_CHECKED_OUT );
	}

	/**
	 * Mark as returned, pending inspection.
	 */
	public static function return_equipment( int $booking_id ): bool {
		return self::set_status( $booking_id, StatusEnums::RENTAL_RETURNED_PENDING_INSPECTION );
	}

	/**
	 * Complete a booking (final state before close).
	 */
	public static function complete( int $booking_id ): bool {
		$booking = self::get( $booking_id );
		if ( ! $booking ) {
			return false;
		}

		$completed = match ( $booking->booking_type ) {
			StatusEnums::TYPE_EQUIPMENT_RENTAL => StatusEnums::RENTAL_COMPLETED,
			StatusEnums::TYPE_SERVICE_BOOKING  => StatusEnums::SERVICE_COMPLETED,
			StatusEnums::TYPE_EQUIPMENT_SALE   => StatusEnums::SALE_COMPLETED,
			default => StatusEnums::RENTAL_COMPLETED,
		};

		return self::set_status( $booking_id, $completed );
	}

	/**
	 * Query bookings with filters.
	 */
	public static function query( array $args = [] ): array {
		global $wpdb;

		$defaults = [
			'booking_type' => '',
			'status'       => '',
			'provider_id'  => 0,
			'customer_id'  => 0,
			'listing_id'   => 0,
			'search'       => '',
			'orderby'      => 'created_at',
			'order'        => 'DESC',
			'limit'        => 20,
			'offset'       => 0,
		];

		$args  = wp_parse_args( $args, $defaults );
		$table = Core::table( 'bookings' );
		$ltable = Core::table( 'listings' );
		$ptable = Core::table( 'providers' );
		$where = [];
		$values = [];

		if ( $args['booking_type'] ) {
			$where[]  = 'b.booking_type = %s';
			$values[] = $args['booking_type'];
		}
		if ( $args['status'] ) {
			if ( is_array( $args['status'] ) ) {
				$ph = implode( ',', array_fill( 0, count( $args['status'] ), '%s' ) );
				$where[] = "b.status IN ({$ph})";
				$values = array_merge( $values, $args['status'] );
			} else {
				$where[]  = 'b.status = %s';
				$values[] = $args['status'];
			}
		}
		if ( $args['provider_id'] ) {
			$where[]  = 'b.provider_id = %d';
			$values[] = $args['provider_id'];
		}
		if ( $args['customer_id'] ) {
			$where[]  = 'b.customer_id = %d';
			$values[] = $args['customer_id'];
		}
		if ( $args['listing_id'] ) {
			$where[]  = 'b.listing_id = %d';
			$values[] = $args['listing_id'];
		}
		if ( $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(b.booking_number LIKE %s OR l.title LIKE %s)';
			$values[] = $like;
			$values[] = $like;
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$order     = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$sql = "SELECT b.*, l.title as listing_title, l.listing_type as listing_type_ref,
				       p.company_name as provider_name,
				       u.display_name as customer_name
				FROM {$table} b
				LEFT JOIN {$ltable} l ON b.listing_id = l.id
				LEFT JOIN {$ptable} p ON b.provider_id = p.id
				LEFT JOIN {$wpdb->users} u ON b.customer_id = u.ID
				{$where_sql}
				ORDER BY b.created_at {$order}
				LIMIT %d OFFSET %d";

		$values[] = $args['limit'];
		$values[] = $args['offset'];

		return $wpdb->get_results( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Count bookings with optional filters.
	 */
	public static function count( array $filters = [] ): int {
		global $wpdb;
		$table = Core::table( 'bookings' );
		$where = [];
		$values = [];

		foreach ( [ 'status', 'booking_type', 'provider_id', 'customer_id' ] as $f ) {
			if ( ! empty( $filters[ $f ] ) ) {
				$type = is_numeric( $filters[ $f ] ) ? '%d' : '%s';
				$where[]  = "{$f} = {$type}";
				$values[] = $filters[ $f ];
			}
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		if ( ! empty( $values ) ) {
			return (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} {$where_sql}", $values
			) );
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Generate a unique booking number.
	 */
	private static function generate_booking_number(): string {
		return 'JQ-' . strtoupper( wp_generate_password( 8, false, false ) );
	}

	/**
	 * Record a transaction (payment event) for a booking.
	 */
	public static function record_transaction( int $booking_id, array $data ): int|false {
		global $wpdb;

		$result = $wpdb->insert( Core::table( 'transactions' ), [
			'booking_id'             => $booking_id,
			'transaction_type'       => sanitize_text_field( $data['type'] ?? 'charge' ),
			'gateway'                => sanitize_text_field( $data['gateway'] ?? '' ),
			'gateway_transaction_id' => sanitize_text_field( $data['gateway_id'] ?? '' ),
			'amount'                 => floatval( $data['amount'] ?? 0 ),
			'currency'               => sanitize_text_field( $data['currency'] ?? 'USD' ),
			'status'                 => sanitize_text_field( $data['status'] ?? 'pending' ),
			'metadata'               => isset( $data['metadata'] ) ? wp_json_encode( $data['metadata'] ) : null,
			'error_message'          => sanitize_text_field( $data['error'] ?? '' ),
			'created_at'             => current_time( 'mysql' ),
			'updated_at'             => current_time( 'mysql' ),
		] );

		return false !== $result ? (int) $wpdb->insert_id : false;
	}
}
