<?php
/**
 * Audit logger — immutable record of all status changes and admin actions.
 *
 * Every state transition in the marketplace is logged here.
 * Records are write-only — never updated or deleted outside of uninstall.
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AuditLogger {

	/**
	 * Log an action to the audit table.
	 *
	 * @param string      $action      Action identifier (e.g. 'provider_approved', 'listing_published').
	 * @param string      $object_type Object type (e.g. 'provider', 'listing', 'booking').
	 * @param int         $object_id   ID of the affected object.
	 * @param mixed       $old_value   Previous value/state (will be JSON-encoded if not string).
	 * @param mixed       $new_value   New value/state (will be JSON-encoded if not string).
	 * @param string|null $context     Optional additional context.
	 * @param int         $user_id     User who performed the action (0 = system).
	 */
	public static function log(
		string $action,
		string $object_type,
		int $object_id = 0,
		mixed $old_value = null,
		mixed $new_value = null,
		?string $context = null,
		int $user_id = 0
	): void {
		global $wpdb;

		if ( 0 === $user_id ) {
			$user_id = get_current_user_id();
		}

		$wpdb->insert(
			Core::table( 'audit_log' ),
			[
				'user_id'     => $user_id,
				'action'      => $action,
				'object_type' => $object_type,
				'object_id'   => $object_id,
				'old_value'   => is_string( $old_value ) ? $old_value : wp_json_encode( $old_value ),
				'new_value'   => is_string( $new_value ) ? $new_value : wp_json_encode( $new_value ),
				'context'     => $context,
				'ip_address'  => self::get_ip(),
				'created_at'  => current_time( 'mysql' ),
			],
			[ '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' ]
		);
	}

	/**
	 * Shorthand for status transitions.
	 */
	public static function status_change(
		string $object_type,
		int $object_id,
		string $old_status,
		string $new_status,
		?string $context = null
	): void {
		self::log(
			$object_type . '_status_changed',
			$object_type,
			$object_id,
			$old_status,
			$new_status,
			$context
		);
	}

	/**
	 * Get user's IP address.
	 */
	private static function get_ip(): string {
		$headers = [
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		];

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				// X-Forwarded-For can contain multiple IPs — take the first.
				if ( str_contains( $ip, ',' ) ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '';
	}

	/**
	 * Query audit log entries.
	 *
	 * @param array $args Filter arguments.
	 * @return array
	 */
	public static function query( array $args = [] ): array {
		global $wpdb;

		$defaults = [
			'object_type' => '',
			'object_id'   => 0,
			'action'      => '',
			'user_id'     => 0,
			'limit'       => 50,
			'offset'      => 0,
			'order'       => 'DESC',
		];

		$args  = wp_parse_args( $args, $defaults );
		$table = Core::table( 'audit_log' );
		$where = [];
		$values = [];

		if ( $args['object_type'] ) {
			$where[]  = 'object_type = %s';
			$values[] = $args['object_type'];
		}
		if ( $args['object_id'] ) {
			$where[]  = 'object_id = %d';
			$values[] = $args['object_id'];
		}
		if ( $args['action'] ) {
			$where[]  = 'action = %s';
			$values[] = $args['action'];
		}
		if ( $args['user_id'] ) {
			$where[]  = 'user_id = %d';
			$values[] = $args['user_id'];
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$order     = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

		$sql = "SELECT * FROM {$table} {$where_sql} ORDER BY created_at {$order} LIMIT %d OFFSET %d";
		$values[] = $args['limit'];
		$values[] = $args['offset'];

		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values ); // phpcs:ignore WordPress.DB.PreparedSQL
		}

		return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
}
