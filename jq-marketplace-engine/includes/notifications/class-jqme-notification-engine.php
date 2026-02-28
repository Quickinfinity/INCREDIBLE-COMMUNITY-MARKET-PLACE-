<?php
/**
 * Notification engine — in-app and email notifications for all marketplace events.
 *
 * Listens for WordPress actions fired by other modules and sends
 * notifications based on admin settings.
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME\Notifications;

use JQME\Core;
use JQME\Settings\Settings;
use JQME\StatusEnums;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NotificationEngine {

	public function __construct() {
		// Booking events.
		add_action( 'jqme_booking_created', [ $this, 'on_booking_created' ], 10, 2 );
		add_action( 'jqme_booking_status_changed', [ $this, 'on_booking_status_changed' ], 10, 3 );

		// Provider events.
		add_action( 'jqme_provider_application_submitted', [ $this, 'on_provider_applied' ], 10, 3 );
		add_action( 'jqme_provider_status_changed', [ $this, 'on_provider_status_changed' ], 10, 3 );
		add_action( 'jqme_notify_admin_new_application', [ $this, 'notify_admin_new_application' ] );

		// Listing events.
		add_action( 'jqme_listing_status_changed', [ $this, 'on_listing_status_changed' ], 10, 3 );

		// Verification events.
		add_action( 'jqme_verification_status_changed', [ $this, 'on_verification_changed' ], 10, 3 );

		// Payment events.
		add_action( 'jqme_refund_processed', [ $this, 'on_refund_processed' ], 10, 3 );
		add_action( 'jqme_deposit_captured', [ $this, 'on_deposit_captured' ], 10, 2 );
		add_action( 'jqme_deposit_released', [ $this, 'on_deposit_released' ], 10, 2 );
	}

	/* ---------------------------------------------------------------
	 * EVENT HANDLERS
	 * ------------------------------------------------------------- */

	public function on_booking_created( int $booking_id, string $type ): void {
		if ( ! Settings::get( 'automation', 'email_booking_request' ) ) {
			return;
		}

		$booking = \JQME\Bookings\Booking::get( $booking_id );
		if ( ! $booking ) {
			return;
		}

		// Notify provider of new booking request.
		$provider = \JQME\Providers\Provider::get( $booking->provider_id );
		if ( $provider ) {
			$this->send( $provider->user_id, 'booking_request', [
				'subject' => sprintf( __( 'New Booking Request: %s', 'jq-marketplace-engine' ), $booking->booking_number ),
				'booking' => $booking,
			] );
		}

		// Dashboard notification for customer.
		$this->create_dashboard_notification(
			$booking->customer_id,
			'booking_submitted',
			__( 'Your booking request has been submitted.', 'jq-marketplace-engine' ),
			'booking',
			$booking_id
		);
	}

	public function on_booking_status_changed( int $booking_id, string $old, string $new ): void {
		$booking = \JQME\Bookings\Booking::get( $booking_id );
		if ( ! $booking ) {
			return;
		}

		$provider = \JQME\Providers\Provider::get( $booking->provider_id );

		// Notify based on new status.
		switch ( $new ) {
			case StatusEnums::RENTAL_APPROVED_PENDING_PAYMENT:
			case StatusEnums::SERVICE_APPROVED_PENDING_PAYMENT:
				$this->send( $booking->customer_id, 'booking_approved', [
					'subject' => sprintf( __( 'Booking Approved: %s — Payment Required', 'jq-marketplace-engine' ), $booking->booking_number ),
					'booking' => $booking,
				] );
				break;

			case StatusEnums::RENTAL_CONFIRMED:
			case StatusEnums::SERVICE_CONFIRMED:
				if ( $provider ) {
					$this->send( $provider->user_id, 'booking_confirmed', [
						'subject' => sprintf( __( 'Booking Confirmed: %s', 'jq-marketplace-engine' ), $booking->booking_number ),
						'booking' => $booking,
					] );
				}
				$this->send( $booking->customer_id, 'booking_confirmed', [
					'subject' => sprintf( __( 'Booking Confirmed: %s', 'jq-marketplace-engine' ), $booking->booking_number ),
					'booking' => $booking,
				] );
				break;

			case StatusEnums::RENTAL_CANCELLED_BY_CUSTOMER:
			case StatusEnums::SERVICE_CANCELLED_BY_CUSTOMER:
				if ( $provider && Settings::get( 'automation', 'email_cancellation_notice' ) ) {
					$this->send( $provider->user_id, 'booking_cancelled', [
						'subject' => sprintf( __( 'Booking Cancelled: %s', 'jq-marketplace-engine' ), $booking->booking_number ),
						'booking' => $booking,
						'cancelled_by' => 'customer',
					] );
				}
				break;

			case StatusEnums::RENTAL_CANCELLED_BY_PROVIDER:
			case StatusEnums::SERVICE_CANCELLED_BY_PROVIDER:
				if ( Settings::get( 'automation', 'email_cancellation_notice' ) ) {
					$this->send( $booking->customer_id, 'booking_cancelled', [
						'subject' => sprintf( __( 'Booking Cancelled: %s', 'jq-marketplace-engine' ), $booking->booking_number ),
						'booking' => $booking,
						'cancelled_by' => 'provider',
					] );
				}
				break;

			case StatusEnums::RENTAL_OVERDUE:
				if ( Settings::get( 'automation', 'email_overdue_reminder' ) ) {
					$this->send( $booking->customer_id, 'booking_overdue', [
						'subject' => sprintf( __( 'Overdue Return: %s', 'jq-marketplace-engine' ), $booking->booking_number ),
						'booking' => $booking,
					] );
				}
				break;

			case StatusEnums::RENTAL_COMPLETED:
			case StatusEnums::SERVICE_COMPLETED:
			case StatusEnums::SALE_COMPLETED:
				// Trigger review reminders.
				if ( Settings::get( 'automation', 'email_review_reminder' ) ) {
					$this->send( $booking->customer_id, 'review_reminder', [
						'subject' => __( 'How was your experience? Leave a review.', 'jq-marketplace-engine' ),
						'booking' => $booking,
					] );
					if ( $provider ) {
						$this->send( $provider->user_id, 'review_reminder', [
							'subject' => __( 'Leave a review for your customer.', 'jq-marketplace-engine' ),
							'booking' => $booking,
						] );
					}
				}
				break;

			case StatusEnums::RENTAL_NO_SHOW_CUSTOMER:
			case StatusEnums::SERVICE_NO_SHOW_CUSTOMER:
				if ( Settings::get( 'automation', 'email_no_show_notice' ) ) {
					$this->send( $booking->customer_id, 'no_show', [
						'subject' => sprintf( __( 'No-Show Recorded: %s', 'jq-marketplace-engine' ), $booking->booking_number ),
						'booking' => $booking,
					] );
				}
				break;
		}
	}

	public function on_provider_applied( int $provider_id, int $user_id, array $data ): void {
		$this->create_dashboard_notification(
			$user_id,
			'application_received',
			__( 'Your provider application has been received. We will review it shortly.', 'jq-marketplace-engine' ),
			'provider',
			$provider_id
		);
	}

	public function on_provider_status_changed( int $provider_id, string $old, string $new ): void {
		$provider = \JQME\Providers\Provider::get( $provider_id );
		if ( ! $provider ) {
			return;
		}

		$subject = match ( $new ) {
			StatusEnums::PROVIDER_APPROVED  => __( 'Your Provider Application Has Been Approved!', 'jq-marketplace-engine' ),
			StatusEnums::PROVIDER_REJECTED  => __( 'Provider Application Update', 'jq-marketplace-engine' ),
			StatusEnums::PROVIDER_SUSPENDED => __( 'Your Provider Account Has Been Suspended', 'jq-marketplace-engine' ),
			default => null,
		};

		if ( $subject ) {
			$this->send( $provider->user_id, 'provider_status', [
				'subject'  => $subject,
				'provider' => $provider,
				'status'   => $new,
			] );
		}
	}

	public function notify_admin_new_application( int $provider_id ): void {
		if ( ! Settings::get( 'automation', 'admin_escalation_alerts' ) ) {
			return;
		}

		$admin_email = get_option( 'admin_email' );
		$provider    = \JQME\Providers\Provider::get( $provider_id );

		if ( $provider ) {
			$this->send_email( $admin_email, __( 'New Provider Application', 'jq-marketplace-engine' ),
				sprintf(
					__( "New provider application from %s (%s).\n\nReview it in the WordPress admin under Marketplace → Providers.", 'jq-marketplace-engine' ),
					$provider->company_name,
					$provider->contact_email
				)
			);
		}
	}

	public function on_listing_status_changed( int $listing_id, string $old, string $new ): void {
		$listing = \JQME\Listings\Listing::get( $listing_id );
		if ( ! $listing ) {
			return;
		}

		$provider = \JQME\Providers\Provider::get( $listing->provider_id );
		if ( ! $provider ) {
			return;
		}

		if ( StatusEnums::LISTING_PUBLISHED === $new ) {
			$this->create_dashboard_notification(
				$provider->user_id,
				'listing_published',
				sprintf( __( 'Your listing "%s" is now live!', 'jq-marketplace-engine' ), $listing->title ),
				'listing',
				$listing_id
			);
		} elseif ( StatusEnums::LISTING_NEEDS_CHANGES === $new ) {
			$this->send( $provider->user_id, 'listing_needs_changes', [
				'subject' => sprintf( __( 'Changes Requested: %s', 'jq-marketplace-engine' ), $listing->title ),
				'listing' => $listing,
			] );
		}
	}

	public function on_verification_changed( int $verification_id, string $old, string $new ): void {
		$verification = \JQME\Listings\Verification::get( $verification_id );
		if ( ! $verification ) {
			return;
		}

		$provider = \JQME\Providers\Provider::get( $verification->provider_id );
		if ( ! $provider ) {
			return;
		}

		if ( StatusEnums::VERIFY_VERIFIED === $new ) {
			$this->create_dashboard_notification(
				$provider->user_id,
				'equipment_verified',
				__( 'Your equipment has been verified.', 'jq-marketplace-engine' ),
				'verification',
				$verification_id
			);
		} elseif ( StatusEnums::VERIFY_REJECTED === $new ) {
			$this->send( $provider->user_id, 'verification_rejected', [
				'subject' => __( 'Equipment Verification Rejected', 'jq-marketplace-engine' ),
				'verification' => $verification,
			] );
		}
	}

	public function on_refund_processed( int $booking_id, float $amount, bool $partial ): void {
		$booking = \JQME\Bookings\Booking::get( $booking_id );
		if ( ! $booking ) {
			return;
		}

		$this->send( $booking->customer_id, 'refund_processed', [
			'subject' => sprintf( __( 'Refund Processed: $%.2f', 'jq-marketplace-engine' ), $amount ),
			'booking' => $booking,
			'amount'  => $amount,
			'partial' => $partial,
		] );
	}

	public function on_deposit_captured( int $deposit_id, float $amount ): void {
		$deposit = \JQME\Payments\DepositManager::get( $deposit_id );
		if ( ! $deposit ) {
			return;
		}

		$booking = \JQME\Bookings\Booking::get( $deposit->booking_id );
		if ( $booking ) {
			$this->send( $booking->customer_id, 'deposit_captured', [
				'subject' => sprintf( __( 'Deposit Captured: $%.2f', 'jq-marketplace-engine' ), $amount ),
				'booking' => $booking,
				'amount'  => $amount,
			] );
		}
	}

	public function on_deposit_released( int $deposit_id, float $amount ): void {
		$deposit = \JQME\Payments\DepositManager::get( $deposit_id );
		if ( ! $deposit ) {
			return;
		}

		$booking = \JQME\Bookings\Booking::get( $deposit->booking_id );
		if ( $booking ) {
			$this->send( $booking->customer_id, 'deposit_released', [
				'subject' => sprintf( __( 'Deposit Released: $%.2f', 'jq-marketplace-engine' ), $amount ),
				'booking' => $booking,
				'amount'  => $amount,
			] );
		}
	}

	/* ---------------------------------------------------------------
	 * CORE SEND METHODS
	 * ------------------------------------------------------------- */

	/**
	 * Send a notification to a user (email + dashboard).
	 */
	private function send( int $user_id, string $type, array $data ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		// Dashboard notification.
		$this->create_dashboard_notification(
			$user_id,
			$type,
			$data['subject'] ?? '',
			$data['booking']->booking_type ?? $data['listing']->listing_type ?? '',
			$data['booking']->id ?? $data['listing']->id ?? $data['provider']->id ?? 0
		);

		// Email notification.
		$this->send_email( $user->user_email, $data['subject'] ?? '', $this->build_email_body( $type, $data ) );
	}

	/**
	 * Create an in-app dashboard notification.
	 */
	private function create_dashboard_notification(
		int $user_id,
		string $type,
		string $message,
		string $related_type = '',
		int $related_id = 0
	): void {
		global $wpdb;

		$wpdb->insert( Core::table( 'notifications' ), [
			'user_id'             => $user_id,
			'notification_type'   => $type,
			'channel'             => 'dashboard',
			'subject'             => $message,
			'body'                => '',
			'related_object_type' => $related_type,
			'related_object_id'   => $related_id,
			'created_at'          => current_time( 'mysql' ),
		] );
	}

	/**
	 * Send an email using wp_mail.
	 */
	private function send_email( string $to, string $subject, string $body ): bool {
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
		$platform = Settings::get( 'global', 'platform_name' );
		$full_subject = "[{$platform}] {$subject}";

		return wp_mail( $to, $full_subject, $body, $headers );
	}

	/**
	 * Build email body from template type and data.
	 */
	private function build_email_body( string $type, array $data ): string {
		$template_file = JQME_PLUGIN_DIR . "templates/emails/{$type}.php";

		if ( file_exists( $template_file ) ) {
			ob_start();
			extract( $data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
			include $template_file;
			return ob_get_clean();
		}

		// Fallback: simple text email.
		$platform = Settings::get( 'global', 'platform_name' );
		$body = '<div style="font-family: sans-serif; max-width: 600px; margin: 0 auto;">';
		$body .= '<h2>' . esc_html( $data['subject'] ?? '' ) . '</h2>';

		if ( isset( $data['booking'] ) ) {
			$b = $data['booking'];
			$body .= '<p>' . sprintf(
				__( 'Booking: %s<br>Amount: $%s<br>Status: %s', 'jq-marketplace-engine' ),
				esc_html( $b->booking_number ),
				esc_html( number_format( (float) $b->total_amount, 2 ) ),
				esc_html( $b->status )
			) . '</p>';
		}

		$body .= '<hr><p style="color:#888;font-size:12px;">' . esc_html( $platform ) . '</p>';
		$body .= '</div>';

		return $body;
	}

	/* ---------------------------------------------------------------
	 * QUERY NOTIFICATIONS
	 * ------------------------------------------------------------- */

	/**
	 * Get unread notifications for a user.
	 */
	public static function get_unread( int $user_id, int $limit = 20 ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM " . Core::table( 'notifications' ) . "
			 WHERE user_id = %d AND is_read = 0 AND channel = 'dashboard'
			 ORDER BY created_at DESC LIMIT %d",
			$user_id, $limit
		) );
	}

	/**
	 * Mark notifications as read.
	 */
	public static function mark_read( array $ids ): void {
		global $wpdb;
		if ( empty( $ids ) ) {
			return;
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$wpdb->query( $wpdb->prepare(
			"UPDATE " . Core::table( 'notifications' ) . " SET is_read = 1, read_at = %s WHERE id IN ({$placeholders})",
			array_merge( [ current_time( 'mysql' ) ], array_map( 'absint', $ids ) )
		) );
	}

	/**
	 * Count unread notifications for a user.
	 */
	public static function count_unread( int $user_id ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM " . Core::table( 'notifications' ) . " WHERE user_id = %d AND is_read = 0",
			$user_id
		) );
	}
}
