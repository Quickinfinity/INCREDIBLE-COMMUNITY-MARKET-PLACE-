<?php
/**
 * Cron task handler — scheduled maintenance and automation.
 *
 * Hooks into the cron events registered by Activator:
 * - jqme_daily_maintenance  (once per day)
 * - jqme_hourly_tasks       (once per hour)
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cron {

	public function __construct() {
		add_action( 'jqme_daily_maintenance', [ $this, 'daily' ] );
		add_action( 'jqme_hourly_tasks', [ $this, 'hourly' ] );
	}

	/**
	 * Daily maintenance tasks.
	 */
	public function daily(): void {
		// Recalculate all provider trust scores.
		Analytics\Ranking::recalculate_all();

		// Update all listing aggregate stats.
		Analytics\Ranking::update_all_listing_stats();

		// Expire overdue reviews (publish submitted side, mark expired side).
		Reviews\Review::expire_overdue();

		// Auto-close expired claims.
		Claims\Claim::auto_close_expired();

		// Clean up old notifications (older than retention window).
		$this->purge_old_notifications();

		// Log daily maintenance run.
		AuditLogger::log( 'cron_daily_maintenance', 'system', 0, null, null, 'Daily maintenance completed' );
	}

	/**
	 * Hourly tasks.
	 */
	public function hourly(): void {
		// Process pending payouts that have passed their hold period.
		$this->process_ready_payouts();

		// Check for upcoming review deadlines and send reminders.
		$this->send_review_reminders();

		// Check for claim response deadlines approaching.
		$this->send_claim_deadline_reminders();

		// Release deposits for completed bookings past the release window.
		$this->auto_release_deposits();
	}

	/**
	 * Process payouts that are past their hold_until date.
	 */
	private function process_ready_payouts(): void {
		global $wpdb;

		$payouts = Core::table( 'payouts' );
		$now     = current_time( 'mysql' );

		$ready = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$payouts}
			 WHERE status = 'queued' AND hold_until IS NOT NULL AND hold_until <= %s
			 LIMIT 50",
			$now
		) );

		foreach ( $ready as $payout ) {
			try {
				$processor = new Payments\PayoutProcessor();
				$processor->execute_payout( (int) $payout->id );
			} catch ( \Exception $e ) {
				AuditLogger::log( 'payout_failed', 'payout', (int) $payout->id, null, null, $e->getMessage() );
			}
		}
	}

	/**
	 * Send reminders for reviews approaching their deadline.
	 */
	private function send_review_reminders(): void {
		global $wpdb;

		$reviews = Core::table( 'reviews' );
		$now     = current_time( 'mysql' );

		// Find reviews due within 24 hours that haven't been submitted.
		$upcoming = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$reviews}
			 WHERE overall_rating = 0
			   AND deadline_at IS NOT NULL
			   AND deadline_at > %s
			   AND deadline_at <= DATE_ADD(%s, INTERVAL 24 HOUR)
			   AND status NOT IN ('expired', 'published')",
			$now, $now
		) );

		foreach ( $upcoming as $review ) {
			$notification = new Notifications\NotificationEngine();
			$notification->send( (int) $review->reviewer_id, 'review_reminder', [
				'review_id'  => $review->id,
				'booking_id' => $review->booking_id,
				'deadline'   => $review->deadline_at,
			] );
		}
	}

	/**
	 * Send reminders for claim response deadlines.
	 */
	private function send_claim_deadline_reminders(): void {
		global $wpdb;

		$claims   = Core::table( 'claims' );
		$bookings = Core::table( 'bookings' );
		$now      = current_time( 'mysql' );

		// Claims with customer response deadline approaching.
		$customer_deadlines = $wpdb->get_results( $wpdb->prepare(
			"SELECT c.*, b.customer_id
			 FROM {$claims} c
			 INNER JOIN {$bookings} b ON c.booking_id = b.id
			 WHERE c.customer_response_deadline IS NOT NULL
			   AND c.customer_response_deadline > %s
			   AND c.customer_response_deadline <= DATE_ADD(%s, INTERVAL 12 HOUR)
			   AND c.status = %s",
			$now, $now, StatusEnums::CLAIM_SUBMITTED
		) );

		foreach ( $customer_deadlines as $claim ) {
			$notification = new Notifications\NotificationEngine();
			$notification->send( (int) $claim->customer_id, 'claim_response_deadline', [
				'claim_id' => $claim->id,
				'deadline' => $claim->customer_response_deadline,
			] );
		}
	}

	/**
	 * Auto-release deposits for completed bookings past the release window.
	 */
	private function auto_release_deposits(): void {
		global $wpdb;

		$deposits = Core::table( 'deposits' );
		$bookings = Core::table( 'bookings' );
		$now      = current_time( 'mysql' );

		$release_hours = (int) Settings\Settings::get( 'deposits', 'auto_release_hours' );
		if ( $release_hours <= 0 ) {
			$release_hours = 72; // Default 3 days.
		}

		$completed_statuses = [
			StatusEnums::RENTAL_COMPLETED,
			StatusEnums::SERVICE_COMPLETED,
		];
		$in = "'" . implode( "','", $completed_statuses ) . "'";

		$releasable = $wpdb->get_results( $wpdb->prepare(
			"SELECT d.* FROM {$deposits} d
			 INNER JOIN {$bookings} b ON d.booking_id = b.id
			 WHERE d.status = 'authorized'
			   AND b.status IN ({$in})
			   AND b.completed_at IS NOT NULL
			   AND DATE_ADD(b.completed_at, INTERVAL %d HOUR) <= %s
			 LIMIT 50",
			$release_hours, $now
		) );

		foreach ( $releasable as $deposit ) {
			try {
				$manager = new Payments\DepositManager();
				$manager->release( (int) $deposit->id, 'Auto-released: release window passed' );
			} catch ( \Exception $e ) {
				AuditLogger::log( 'deposit_auto_release_failed', 'deposit', (int) $deposit->id, null, null, $e->getMessage() );
			}
		}
	}

	/**
	 * Purge old read notifications beyond retention window.
	 */
	private function purge_old_notifications(): void {
		global $wpdb;

		$notifications = Core::table( 'notifications' );
		$retention     = (int) Settings\Settings::get( 'reporting', 'retention_window_days' );
		if ( $retention <= 0 ) {
			$retention = 730;
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $retention * DAY_IN_SECONDS ) );

		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$notifications} WHERE is_read = 1 AND created_at < %s",
			$cutoff
		) );
	}
}
