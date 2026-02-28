<?php
/**
 * Condition report model — pre-handoff and return inspection for equipment rentals.
 *
 * Condition reports document equipment state at two checkpoints:
 * 1. Pre-handoff (before customer receives equipment)
 * 2. Return (when equipment comes back)
 *
 * Mismatch between the two triggers a claim flag.
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME\ConditionReports;

use JQME\Core;
use JQME\AuditLogger;
use JQME\StatusEnums;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ConditionReport {

	/**
	 * Get a condition report by ID.
	 */
	public static function get( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . Core::table( 'condition_reports' ) . " WHERE id = %d", $id
		) ) ?: null;
	}

	/**
	 * Get all condition reports for a booking.
	 */
	public static function get_for_booking( int $booking_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM " . Core::table( 'condition_reports' ) . " WHERE booking_id = %d ORDER BY created_at ASC",
			$booking_id
		) );
	}

	/**
	 * Get a specific report type for a booking.
	 *
	 * @param string $report_type pre_handoff|return
	 */
	public static function get_by_type( int $booking_id, string $report_type ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . Core::table( 'condition_reports' ) . " WHERE booking_id = %d AND report_type = %s ORDER BY created_at DESC LIMIT 1",
			$booking_id,
			$report_type
		) ) ?: null;
	}

	/**
	 * Submit a condition report.
	 *
	 * @param array $data {
	 *     @type int    $booking_id     Booking ID.
	 *     @type string $report_type    pre_handoff|return
	 *     @type string $condition_grade excellent|good|fair|poor|damaged
	 *     @type string $notes          Inspector notes.
	 *     @type array  $photo_urls     Array of photo URLs.
	 *     @type array  $checklist      Key/value checklist items.
	 * }
	 */
	public static function submit( array $data ): int|false {
		global $wpdb;

		$booking_id = absint( $data['booking_id'] ?? 0 );
		$booking    = \JQME\Bookings\Booking::get( $booking_id );

		if ( ! $booking ) {
			return false;
		}

		$report_type = sanitize_text_field( $data['report_type'] ?? '' );
		if ( ! in_array( $report_type, [ 'pre_handoff', 'return' ], true ) ) {
			return false;
		}

		// Determine submitter role.
		$provider = \JQME\Providers\Provider::get( $booking->provider_id );
		$user_id  = get_current_user_id();
		$role     = ( $provider && (int) $provider->user_id === $user_id ) ? 'provider' : 'customer';

		$photo_urls = isset( $data['photo_urls'] ) ? array_map( 'esc_url_raw', (array) $data['photo_urls'] ) : [];
		$checklist  = isset( $data['checklist'] ) ? (array) $data['checklist'] : [];

		// Determine status.
		$status = 'pre_handoff' === $report_type
			? StatusEnums::CONDITION_PRE_HANDOFF_COMPLETE
			: StatusEnums::CONDITION_RETURN_COMPLETE;

		$result = $wpdb->insert( Core::table( 'condition_reports' ), [
			'booking_id'       => $booking_id,
			'report_type'      => $report_type,
			'submitted_by'     => $user_id,
			'submitted_by_role' => $role,
			'condition_grade'  => sanitize_text_field( $data['condition_grade'] ?? '' ),
			'notes'            => sanitize_textarea_field( $data['notes'] ?? '' ),
			'photo_urls'       => wp_json_encode( $photo_urls ),
			'checklist_data'   => wp_json_encode( $checklist ),
			'status'           => $status,
			'created_at'       => current_time( 'mysql' ),
			'updated_at'       => current_time( 'mysql' ),
		] );

		if ( false === $result ) {
			return false;
		}

		$report_id = (int) $wpdb->insert_id;

		// Auto-detect mismatch on return reports.
		if ( 'return' === $report_type ) {
			self::check_mismatch( $report_id, $booking_id );
		}

		AuditLogger::log(
			'condition_report_submitted',
			'condition_report',
			$report_id,
			null,
			$status,
			sprintf( 'Booking #%d, type: %s, grade: %s', $booking_id, $report_type, $data['condition_grade'] ?? '' )
		);

		do_action( 'jqme_condition_report_submitted', $report_id, $booking_id, $report_type );

		return $report_id;
	}

	/**
	 * Compare return report against pre-handoff to detect mismatches.
	 */
	private static function check_mismatch( int $return_report_id, int $booking_id ): void {
		global $wpdb;

		$pre = self::get_by_type( $booking_id, 'pre_handoff' );
		$ret = self::get( $return_report_id );

		if ( ! $pre || ! $ret ) {
			return;
		}

		$grade_order = [ 'excellent' => 5, 'good' => 4, 'fair' => 3, 'poor' => 2, 'damaged' => 1 ];
		$pre_score   = $grade_order[ $pre->condition_grade ] ?? 3;
		$ret_score   = $grade_order[ $ret->condition_grade ] ?? 3;

		// Flag mismatch if return condition is worse than pre-handoff.
		if ( $ret_score < $pre_score ) {
			$wpdb->update( Core::table( 'condition_reports' ), [
				'mismatch_flagged' => 1,
				'mismatch_notes'   => sprintf(
					'Condition downgrade detected: %s -> %s',
					$pre->condition_grade,
					$ret->condition_grade
				),
				'status'           => StatusEnums::CONDITION_MISMATCH_FLAGGED,
				'updated_at'       => current_time( 'mysql' ),
			], [ 'id' => $return_report_id ] );

			AuditLogger::log(
				'condition_mismatch_flagged',
				'condition_report',
				$return_report_id,
				$pre->condition_grade,
				$ret->condition_grade,
				"Booking #{$booking_id}"
			);

			do_action( 'jqme_condition_mismatch_flagged', $return_report_id, $booking_id );
		}
	}

	/**
	 * Flag a report manually (admin action).
	 */
	public static function flag_mismatch( int $report_id, string $notes = '' ): bool {
		global $wpdb;

		$report = self::get( $report_id );
		if ( ! $report ) {
			return false;
		}

		$result = $wpdb->update( Core::table( 'condition_reports' ), [
			'mismatch_flagged' => 1,
			'mismatch_notes'   => sanitize_textarea_field( $notes ),
			'status'           => StatusEnums::CONDITION_MISMATCH_FLAGGED,
			'updated_at'       => current_time( 'mysql' ),
		], [ 'id' => $report_id ] );

		if ( false !== $result ) {
			AuditLogger::log( 'condition_report_flagged', 'condition_report', $report_id, null, null, $notes );
		}

		return false !== $result;
	}

	/**
	 * Query condition reports.
	 */
	public static function query( array $args = [] ): array {
		global $wpdb;

		$defaults = [
			'booking_id'  => 0,
			'report_type' => '',
			'status'      => '',
			'flagged'     => null,
			'limit'       => 20,
			'offset'      => 0,
		];

		$args  = wp_parse_args( $args, $defaults );
		$table = Core::table( 'condition_reports' );
		$btable = Core::table( 'bookings' );
		$where = [];
		$values = [];

		if ( $args['booking_id'] ) {
			$where[]  = 'cr.booking_id = %d';
			$values[] = $args['booking_id'];
		}
		if ( $args['report_type'] ) {
			$where[]  = 'cr.report_type = %s';
			$values[] = $args['report_type'];
		}
		if ( $args['status'] ) {
			$where[]  = 'cr.status = %s';
			$values[] = $args['status'];
		}
		if ( null !== $args['flagged'] ) {
			$where[]  = 'cr.mismatch_flagged = %d';
			$values[] = $args['flagged'] ? 1 : 0;
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$sql = "SELECT cr.*, b.booking_number
				FROM {$table} cr
				LEFT JOIN {$btable} b ON cr.booking_id = b.id
				{$where_sql}
				ORDER BY cr.created_at DESC
				LIMIT %d OFFSET %d";

		$values[] = $args['limit'];
		$values[] = $args['offset'];

		return $wpdb->get_results( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Get the standard checklist items for equipment condition reporting.
	 */
	public static function get_checklist_template(): array {
		return [
			'exterior_condition'   => __( 'Exterior Condition (scratches, dents, wear)', 'jq-marketplace-engine' ),
			'operational_function' => __( 'Operational / Functional Test', 'jq-marketplace-engine' ),
			'accessories_complete' => __( 'All Accessories & Attachments Present', 'jq-marketplace-engine' ),
			'cleaning_status'      => __( 'Cleaning / Residue Status', 'jq-marketplace-engine' ),
			'safety_equipment'     => __( 'Safety Equipment / Guards', 'jq-marketplace-engine' ),
			'fluid_levels'         => __( 'Fluid Levels / Consumables', 'jq-marketplace-engine' ),
			'electrical_cords'     => __( 'Electrical Cords / Hoses', 'jq-marketplace-engine' ),
			'hour_meter'           => __( 'Hour Meter / Usage Reading', 'jq-marketplace-engine' ),
		];
	}
}
