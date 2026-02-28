<?php
/**
 * Status enumerations for all marketplace objects.
 *
 * Central source of truth for every status value used in the database.
 * Use these constants instead of raw strings to prevent typos and
 * enable IDE auto-completion.
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StatusEnums {

	/* ---------------------------------------------------------------
	 * PROVIDER ACCOUNT STATUSES
	 * ------------------------------------------------------------- */

	const PROVIDER_PENDING_APPLICATION = 'pending_application';
	const PROVIDER_PENDING_REVIEW      = 'pending_review';
	const PROVIDER_APPROVED            = 'approved';
	const PROVIDER_RESTRICTED          = 'restricted';
	const PROVIDER_SUSPENDED           = 'suspended';
	const PROVIDER_REJECTED            = 'rejected';
	const PROVIDER_ARCHIVED            = 'archived';

	public static function provider_statuses(): array {
		return [
			self::PROVIDER_PENDING_APPLICATION => __( 'Pending Application', 'jq-marketplace-engine' ),
			self::PROVIDER_PENDING_REVIEW      => __( 'Pending Review', 'jq-marketplace-engine' ),
			self::PROVIDER_APPROVED            => __( 'Approved', 'jq-marketplace-engine' ),
			self::PROVIDER_RESTRICTED          => __( 'Restricted', 'jq-marketplace-engine' ),
			self::PROVIDER_SUSPENDED           => __( 'Suspended', 'jq-marketplace-engine' ),
			self::PROVIDER_REJECTED            => __( 'Rejected', 'jq-marketplace-engine' ),
			self::PROVIDER_ARCHIVED            => __( 'Archived', 'jq-marketplace-engine' ),
		];
	}

	/* ---------------------------------------------------------------
	 * LISTING STATUSES
	 * ------------------------------------------------------------- */

	const LISTING_DRAFT           = 'draft';
	const LISTING_SUBMITTED       = 'submitted';
	const LISTING_UNDER_REVIEW    = 'under_review';
	const LISTING_NEEDS_CHANGES   = 'needs_changes';
	const LISTING_VERIFIED        = 'verified';
	const LISTING_PUBLISHED       = 'published';
	const LISTING_PAUSED          = 'paused';
	const LISTING_FLAGGED         = 'flagged';
	const LISTING_SUSPENDED       = 'suspended';
	const LISTING_ARCHIVED        = 'archived';
	const LISTING_SOLD_OUT        = 'sold_out';

	public static function listing_statuses(): array {
		return [
			self::LISTING_DRAFT         => __( 'Draft', 'jq-marketplace-engine' ),
			self::LISTING_SUBMITTED     => __( 'Submitted', 'jq-marketplace-engine' ),
			self::LISTING_UNDER_REVIEW  => __( 'Under Review', 'jq-marketplace-engine' ),
			self::LISTING_NEEDS_CHANGES => __( 'Needs Changes', 'jq-marketplace-engine' ),
			self::LISTING_VERIFIED      => __( 'Verified', 'jq-marketplace-engine' ),
			self::LISTING_PUBLISHED     => __( 'Published', 'jq-marketplace-engine' ),
			self::LISTING_PAUSED        => __( 'Paused', 'jq-marketplace-engine' ),
			self::LISTING_FLAGGED       => __( 'Flagged', 'jq-marketplace-engine' ),
			self::LISTING_SUSPENDED     => __( 'Suspended', 'jq-marketplace-engine' ),
			self::LISTING_ARCHIVED      => __( 'Archived', 'jq-marketplace-engine' ),
			self::LISTING_SOLD_OUT      => __( 'Sold Out', 'jq-marketplace-engine' ),
		];
	}

	/* ---------------------------------------------------------------
	 * LISTING TYPES
	 * ------------------------------------------------------------- */

	const TYPE_EQUIPMENT_RENTAL  = 'equipment_rental';
	const TYPE_EQUIPMENT_SALE    = 'equipment_sale';
	const TYPE_SERVICE_BOOKING   = 'service_booking';

	public static function listing_types(): array {
		return [
			self::TYPE_EQUIPMENT_RENTAL => __( 'Equipment Rental', 'jq-marketplace-engine' ),
			self::TYPE_EQUIPMENT_SALE   => __( 'Equipment Sale', 'jq-marketplace-engine' ),
			self::TYPE_SERVICE_BOOKING  => __( 'Service Booking', 'jq-marketplace-engine' ),
		];
	}

	/* ---------------------------------------------------------------
	 * EQUIPMENT VERIFICATION STATUSES
	 * ------------------------------------------------------------- */

	const VERIFY_NOT_SUBMITTED       = 'not_submitted';
	const VERIFY_PENDING_SERIAL      = 'pending_serial_review';
	const VERIFY_PENDING_DOCS        = 'pending_docs';
	const VERIFY_VERIFIED            = 'verified';
	const VERIFY_REJECTED            = 'rejected';
	const VERIFY_EXPIRED             = 'expired_reverification_required';

	public static function verification_statuses(): array {
		return [
			self::VERIFY_NOT_SUBMITTED  => __( 'Not Submitted', 'jq-marketplace-engine' ),
			self::VERIFY_PENDING_SERIAL => __( 'Pending Serial Review', 'jq-marketplace-engine' ),
			self::VERIFY_PENDING_DOCS   => __( 'Pending Documents', 'jq-marketplace-engine' ),
			self::VERIFY_VERIFIED       => __( 'Verified', 'jq-marketplace-engine' ),
			self::VERIFY_REJECTED       => __( 'Rejected', 'jq-marketplace-engine' ),
			self::VERIFY_EXPIRED        => __( 'Expired — Reverification Required', 'jq-marketplace-engine' ),
		];
	}

	/* ---------------------------------------------------------------
	 * RENTAL BOOKING STATUSES
	 * ------------------------------------------------------------- */

	const RENTAL_INQUIRY                    = 'inquiry';
	const RENTAL_REQUESTED                  = 'requested';
	const RENTAL_PENDING_PROVIDER_APPROVAL  = 'pending_provider_approval';
	const RENTAL_APPROVED_PENDING_PAYMENT   = 'approved_pending_payment';
	const RENTAL_PAYMENT_AUTHORIZED         = 'payment_authorized';
	const RENTAL_CONFIRMED                  = 'confirmed';
	const RENTAL_AWAITING_PICKUP            = 'awaiting_pickup';
	const RENTAL_CHECKED_OUT                = 'checked_out';
	const RENTAL_ACTIVE                     = 'active';
	const RENTAL_EXTENSION_REQUESTED        = 'extension_requested';
	const RENTAL_OVERDUE                    = 'overdue';
	const RENTAL_RETURN_SCHEDULED           = 'return_scheduled';
	const RENTAL_RETURNED_PENDING_INSPECTION = 'returned_pending_inspection';
	const RENTAL_COMPLETED                  = 'completed';
	const RENTAL_CANCELLED_BY_CUSTOMER      = 'cancelled_by_customer';
	const RENTAL_CANCELLED_BY_PROVIDER      = 'cancelled_by_provider';
	const RENTAL_NO_SHOW_CUSTOMER           = 'no_show_customer';
	const RENTAL_NO_SHOW_PROVIDER           = 'no_show_provider';
	const RENTAL_DISPUTE_HOLD               = 'dispute_hold';
	const RENTAL_CLOSED                     = 'closed';

	public static function rental_booking_statuses(): array {
		return [
			self::RENTAL_INQUIRY                     => __( 'Inquiry', 'jq-marketplace-engine' ),
			self::RENTAL_REQUESTED                   => __( 'Requested', 'jq-marketplace-engine' ),
			self::RENTAL_PENDING_PROVIDER_APPROVAL   => __( 'Pending Provider Approval', 'jq-marketplace-engine' ),
			self::RENTAL_APPROVED_PENDING_PAYMENT    => __( 'Approved — Pending Payment', 'jq-marketplace-engine' ),
			self::RENTAL_PAYMENT_AUTHORIZED          => __( 'Payment Authorized', 'jq-marketplace-engine' ),
			self::RENTAL_CONFIRMED                   => __( 'Confirmed', 'jq-marketplace-engine' ),
			self::RENTAL_AWAITING_PICKUP             => __( 'Awaiting Pickup', 'jq-marketplace-engine' ),
			self::RENTAL_CHECKED_OUT                 => __( 'Checked Out', 'jq-marketplace-engine' ),
			self::RENTAL_ACTIVE                      => __( 'Active', 'jq-marketplace-engine' ),
			self::RENTAL_EXTENSION_REQUESTED         => __( 'Extension Requested', 'jq-marketplace-engine' ),
			self::RENTAL_OVERDUE                     => __( 'Overdue', 'jq-marketplace-engine' ),
			self::RENTAL_RETURN_SCHEDULED            => __( 'Return Scheduled', 'jq-marketplace-engine' ),
			self::RENTAL_RETURNED_PENDING_INSPECTION => __( 'Returned — Pending Inspection', 'jq-marketplace-engine' ),
			self::RENTAL_COMPLETED                   => __( 'Completed', 'jq-marketplace-engine' ),
			self::RENTAL_CANCELLED_BY_CUSTOMER       => __( 'Cancelled by Customer', 'jq-marketplace-engine' ),
			self::RENTAL_CANCELLED_BY_PROVIDER       => __( 'Cancelled by Provider', 'jq-marketplace-engine' ),
			self::RENTAL_NO_SHOW_CUSTOMER            => __( 'No-Show (Customer)', 'jq-marketplace-engine' ),
			self::RENTAL_NO_SHOW_PROVIDER            => __( 'No-Show (Provider)', 'jq-marketplace-engine' ),
			self::RENTAL_DISPUTE_HOLD                => __( 'Dispute Hold', 'jq-marketplace-engine' ),
			self::RENTAL_CLOSED                      => __( 'Closed', 'jq-marketplace-engine' ),
		];
	}

	/* ---------------------------------------------------------------
	 * SERVICE BOOKING STATUSES
	 * ------------------------------------------------------------- */

	const SERVICE_INQUIRY                       = 'inquiry';
	const SERVICE_REQUESTED                     = 'requested';
	const SERVICE_PENDING_PROVIDER_APPROVAL     = 'pending_provider_approval';
	const SERVICE_APPROVED_PENDING_PAYMENT      = 'approved_pending_payment';
	const SERVICE_CONFIRMED                     = 'confirmed';
	const SERVICE_PREP_PENDING                  = 'prep_pending';
	const SERVICE_SCHEDULED                     = 'scheduled';
	const SERVICE_IN_PROGRESS                   = 'in_progress';
	const SERVICE_AWAITING_COMPLETION           = 'awaiting_completion_confirmation';
	const SERVICE_COMPLETED                     = 'completed';
	const SERVICE_RESCHEDULE_REQUESTED          = 'reschedule_requested';
	const SERVICE_CANCELLED_BY_CUSTOMER         = 'cancelled_by_customer';
	const SERVICE_CANCELLED_BY_PROVIDER         = 'cancelled_by_provider';
	const SERVICE_NO_SHOW_CUSTOMER              = 'no_show_customer';
	const SERVICE_NO_SHOW_PROVIDER              = 'no_show_provider';
	const SERVICE_DISPUTE_HOLD                  = 'dispute_hold';
	const SERVICE_CLOSED                        = 'closed';

	public static function service_booking_statuses(): array {
		return [
			self::SERVICE_INQUIRY                   => __( 'Inquiry', 'jq-marketplace-engine' ),
			self::SERVICE_REQUESTED                 => __( 'Requested', 'jq-marketplace-engine' ),
			self::SERVICE_PENDING_PROVIDER_APPROVAL => __( 'Pending Provider Approval', 'jq-marketplace-engine' ),
			self::SERVICE_APPROVED_PENDING_PAYMENT  => __( 'Approved — Pending Payment', 'jq-marketplace-engine' ),
			self::SERVICE_CONFIRMED                 => __( 'Confirmed', 'jq-marketplace-engine' ),
			self::SERVICE_PREP_PENDING              => __( 'Prep Pending', 'jq-marketplace-engine' ),
			self::SERVICE_SCHEDULED                 => __( 'Scheduled', 'jq-marketplace-engine' ),
			self::SERVICE_IN_PROGRESS               => __( 'In Progress', 'jq-marketplace-engine' ),
			self::SERVICE_AWAITING_COMPLETION       => __( 'Awaiting Completion Confirmation', 'jq-marketplace-engine' ),
			self::SERVICE_COMPLETED                 => __( 'Completed', 'jq-marketplace-engine' ),
			self::SERVICE_RESCHEDULE_REQUESTED      => __( 'Reschedule Requested', 'jq-marketplace-engine' ),
			self::SERVICE_CANCELLED_BY_CUSTOMER     => __( 'Cancelled by Customer', 'jq-marketplace-engine' ),
			self::SERVICE_CANCELLED_BY_PROVIDER     => __( 'Cancelled by Provider', 'jq-marketplace-engine' ),
			self::SERVICE_NO_SHOW_CUSTOMER          => __( 'No-Show (Customer)', 'jq-marketplace-engine' ),
			self::SERVICE_NO_SHOW_PROVIDER          => __( 'No-Show (Provider)', 'jq-marketplace-engine' ),
			self::SERVICE_DISPUTE_HOLD              => __( 'Dispute Hold', 'jq-marketplace-engine' ),
			self::SERVICE_CLOSED                    => __( 'Closed', 'jq-marketplace-engine' ),
		];
	}

	/* ---------------------------------------------------------------
	 * SALE ORDER STATUSES
	 * ------------------------------------------------------------- */

	const SALE_LISTED            = 'listed';
	const SALE_OFFER_RECEIVED    = 'offer_received';
	const SALE_OFFER_ACCEPTED    = 'offer_accepted';
	const SALE_CHECKOUT_PENDING  = 'checkout_pending';
	const SALE_PAID              = 'paid';
	const SALE_AWAITING_PICKUP   = 'awaiting_pickup';
	const SALE_AWAITING_SHIPMENT = 'awaiting_shipment';
	const SALE_DELIVERED         = 'delivered';
	const SALE_COMPLETED         = 'completed';
	const SALE_CANCELLED         = 'cancelled';
	const SALE_REFUNDED          = 'refunded';
	const SALE_DISPUTED          = 'disputed';
	const SALE_CLOSED            = 'closed';

	public static function sale_order_statuses(): array {
		return [
			self::SALE_LISTED            => __( 'Listed', 'jq-marketplace-engine' ),
			self::SALE_OFFER_RECEIVED    => __( 'Offer Received', 'jq-marketplace-engine' ),
			self::SALE_OFFER_ACCEPTED    => __( 'Offer Accepted', 'jq-marketplace-engine' ),
			self::SALE_CHECKOUT_PENDING  => __( 'Checkout Pending', 'jq-marketplace-engine' ),
			self::SALE_PAID              => __( 'Paid', 'jq-marketplace-engine' ),
			self::SALE_AWAITING_PICKUP   => __( 'Awaiting Pickup', 'jq-marketplace-engine' ),
			self::SALE_AWAITING_SHIPMENT => __( 'Awaiting Shipment', 'jq-marketplace-engine' ),
			self::SALE_DELIVERED         => __( 'Delivered', 'jq-marketplace-engine' ),
			self::SALE_COMPLETED         => __( 'Completed', 'jq-marketplace-engine' ),
			self::SALE_CANCELLED         => __( 'Cancelled', 'jq-marketplace-engine' ),
			self::SALE_REFUNDED          => __( 'Refunded', 'jq-marketplace-engine' ),
			self::SALE_DISPUTED          => __( 'Disputed', 'jq-marketplace-engine' ),
			self::SALE_CLOSED            => __( 'Closed', 'jq-marketplace-engine' ),
		];
	}

	/* ---------------------------------------------------------------
	 * CONDITION REPORT STATUSES
	 * ------------------------------------------------------------- */

	const CONDITION_NOT_STARTED        = 'not_started';
	const CONDITION_PRE_HANDOFF_PENDING  = 'pre_handoff_pending';
	const CONDITION_PRE_HANDOFF_COMPLETE = 'pre_handoff_complete';
	const CONDITION_RETURN_PENDING       = 'return_pending';
	const CONDITION_RETURN_COMPLETE      = 'return_complete';
	const CONDITION_MISMATCH_FLAGGED     = 'mismatch_flagged';
	const CONDITION_ARCHIVED             = 'archived';

	public static function condition_report_statuses(): array {
		return [
			self::CONDITION_NOT_STARTED          => __( 'Not Started', 'jq-marketplace-engine' ),
			self::CONDITION_PRE_HANDOFF_PENDING  => __( 'Pre-Handoff Pending', 'jq-marketplace-engine' ),
			self::CONDITION_PRE_HANDOFF_COMPLETE => __( 'Pre-Handoff Complete', 'jq-marketplace-engine' ),
			self::CONDITION_RETURN_PENDING       => __( 'Return Pending', 'jq-marketplace-engine' ),
			self::CONDITION_RETURN_COMPLETE      => __( 'Return Complete', 'jq-marketplace-engine' ),
			self::CONDITION_MISMATCH_FLAGGED     => __( 'Mismatch Flagged', 'jq-marketplace-engine' ),
			self::CONDITION_ARCHIVED             => __( 'Archived', 'jq-marketplace-engine' ),
		];
	}

	/* ---------------------------------------------------------------
	 * DAMAGE CLAIM STATUSES
	 * ------------------------------------------------------------- */

	const CLAIM_NONE                      = 'none';
	const CLAIM_DRAFT                     = 'draft';
	const CLAIM_SUBMITTED                 = 'submitted';
	const CLAIM_AWAITING_CUSTOMER         = 'awaiting_customer_response';
	const CLAIM_AWAITING_PROVIDER         = 'awaiting_provider_response';
	const CLAIM_EVIDENCE_UNDER_REVIEW     = 'evidence_under_review';
	const CLAIM_SETTLED_DIRECTLY          = 'settled_directly';
	const CLAIM_DEPOSIT_PARTIAL_CAPTURE   = 'deposit_partial_capture';
	const CLAIM_DEPOSIT_FULL_CAPTURE      = 'deposit_full_capture';
	const CLAIM_DENIED                    = 'denied';
	const CLAIM_WITHDRAWN                 = 'withdrawn';
	const CLAIM_ESCALATED_EXTERNAL        = 'escalated_external';
	const CLAIM_CLOSED                    = 'closed';

	public static function claim_statuses(): array {
		return [
			self::CLAIM_NONE                    => __( 'None', 'jq-marketplace-engine' ),
			self::CLAIM_DRAFT                   => __( 'Draft', 'jq-marketplace-engine' ),
			self::CLAIM_SUBMITTED               => __( 'Submitted', 'jq-marketplace-engine' ),
			self::CLAIM_AWAITING_CUSTOMER       => __( 'Awaiting Customer Response', 'jq-marketplace-engine' ),
			self::CLAIM_AWAITING_PROVIDER       => __( 'Awaiting Provider Response', 'jq-marketplace-engine' ),
			self::CLAIM_EVIDENCE_UNDER_REVIEW   => __( 'Evidence Under Review', 'jq-marketplace-engine' ),
			self::CLAIM_SETTLED_DIRECTLY        => __( 'Settled Directly', 'jq-marketplace-engine' ),
			self::CLAIM_DEPOSIT_PARTIAL_CAPTURE => __( 'Deposit Partial Capture', 'jq-marketplace-engine' ),
			self::CLAIM_DEPOSIT_FULL_CAPTURE    => __( 'Deposit Full Capture', 'jq-marketplace-engine' ),
			self::CLAIM_DENIED                  => __( 'Denied', 'jq-marketplace-engine' ),
			self::CLAIM_WITHDRAWN               => __( 'Withdrawn', 'jq-marketplace-engine' ),
			self::CLAIM_ESCALATED_EXTERNAL      => __( 'Escalated External', 'jq-marketplace-engine' ),
			self::CLAIM_CLOSED                  => __( 'Closed', 'jq-marketplace-engine' ),
		];
	}

	/* ---------------------------------------------------------------
	 * PAYOUT STATUSES
	 * ------------------------------------------------------------- */

	const PAYOUT_NOT_READY        = 'not_ready';
	const PAYOUT_PENDING_HOLD     = 'pending_hold_period';
	const PAYOUT_PENDING_CLAIM    = 'pending_claim_window';
	const PAYOUT_QUEUED           = 'queued';
	const PAYOUT_SENT             = 'sent';
	const PAYOUT_PARTIALLY_ADJUSTED = 'partially_adjusted';
	const PAYOUT_REVERSED         = 'reversed';
	const PAYOUT_FAILED           = 'failed';
	const PAYOUT_MANUAL_REVIEW    = 'manual_review';

	public static function payout_statuses(): array {
		return [
			self::PAYOUT_NOT_READY          => __( 'Not Ready', 'jq-marketplace-engine' ),
			self::PAYOUT_PENDING_HOLD       => __( 'Pending Hold Period', 'jq-marketplace-engine' ),
			self::PAYOUT_PENDING_CLAIM      => __( 'Pending Claim Window', 'jq-marketplace-engine' ),
			self::PAYOUT_QUEUED             => __( 'Queued', 'jq-marketplace-engine' ),
			self::PAYOUT_SENT               => __( 'Sent', 'jq-marketplace-engine' ),
			self::PAYOUT_PARTIALLY_ADJUSTED => __( 'Partially Adjusted', 'jq-marketplace-engine' ),
			self::PAYOUT_REVERSED           => __( 'Reversed', 'jq-marketplace-engine' ),
			self::PAYOUT_FAILED             => __( 'Failed', 'jq-marketplace-engine' ),
			self::PAYOUT_MANUAL_REVIEW      => __( 'Manual Review', 'jq-marketplace-engine' ),
		];
	}

	/* ---------------------------------------------------------------
	 * REVIEW STATUSES
	 * ------------------------------------------------------------- */

	const REVIEW_PENDING_BOTH     = 'pending_both';
	const REVIEW_PENDING_PROVIDER = 'pending_provider';
	const REVIEW_PENDING_CUSTOMER = 'pending_customer';
	const REVIEW_SUBMITTED        = 'submitted';
	const REVIEW_EXPIRED          = 'expired';
	const REVIEW_HIDDEN_FLAGGED   = 'hidden_flagged';
	const REVIEW_PUBLISHED        = 'published';

	public static function review_statuses(): array {
		return [
			self::REVIEW_PENDING_BOTH     => __( 'Pending Both', 'jq-marketplace-engine' ),
			self::REVIEW_PENDING_PROVIDER => __( 'Pending Provider', 'jq-marketplace-engine' ),
			self::REVIEW_PENDING_CUSTOMER => __( 'Pending Customer', 'jq-marketplace-engine' ),
			self::REVIEW_SUBMITTED        => __( 'Submitted', 'jq-marketplace-engine' ),
			self::REVIEW_EXPIRED          => __( 'Expired', 'jq-marketplace-engine' ),
			self::REVIEW_HIDDEN_FLAGGED   => __( 'Hidden (Flagged)', 'jq-marketplace-engine' ),
			self::REVIEW_PUBLISHED        => __( 'Published', 'jq-marketplace-engine' ),
		];
	}

	/* ---------------------------------------------------------------
	 * DEPOSIT STATUSES
	 * ------------------------------------------------------------- */

	const DEPOSIT_PENDING   = 'pending';
	const DEPOSIT_AUTHORIZED = 'authorized';
	const DEPOSIT_CAPTURED  = 'captured';
	const DEPOSIT_PARTIALLY_CAPTURED = 'partially_captured';
	const DEPOSIT_RELEASED  = 'released';
	const DEPOSIT_EXPIRED   = 'expired';
	const DEPOSIT_FAILED    = 'failed';

	public static function deposit_statuses(): array {
		return [
			self::DEPOSIT_PENDING             => __( 'Pending', 'jq-marketplace-engine' ),
			self::DEPOSIT_AUTHORIZED          => __( 'Authorized', 'jq-marketplace-engine' ),
			self::DEPOSIT_CAPTURED            => __( 'Captured', 'jq-marketplace-engine' ),
			self::DEPOSIT_PARTIALLY_CAPTURED  => __( 'Partially Captured', 'jq-marketplace-engine' ),
			self::DEPOSIT_RELEASED            => __( 'Released', 'jq-marketplace-engine' ),
			self::DEPOSIT_EXPIRED             => __( 'Expired', 'jq-marketplace-engine' ),
			self::DEPOSIT_FAILED              => __( 'Failed', 'jq-marketplace-engine' ),
		];
	}

	/* ---------------------------------------------------------------
	 * TRANSACTION TYPES
	 * ------------------------------------------------------------- */

	const TXN_CHARGE          = 'charge';
	const TXN_AUTHORIZATION   = 'authorization';
	const TXN_CAPTURE         = 'capture';
	const TXN_REFUND          = 'refund';
	const TXN_PARTIAL_REFUND  = 'partial_refund';
	const TXN_PAYOUT          = 'payout';
	const TXN_FEE             = 'fee';

	public static function transaction_types(): array {
		return [
			self::TXN_CHARGE         => __( 'Charge', 'jq-marketplace-engine' ),
			self::TXN_AUTHORIZATION  => __( 'Authorization', 'jq-marketplace-engine' ),
			self::TXN_CAPTURE        => __( 'Capture', 'jq-marketplace-engine' ),
			self::TXN_REFUND         => __( 'Refund', 'jq-marketplace-engine' ),
			self::TXN_PARTIAL_REFUND => __( 'Partial Refund', 'jq-marketplace-engine' ),
			self::TXN_PAYOUT         => __( 'Payout', 'jq-marketplace-engine' ),
			self::TXN_FEE            => __( 'Fee', 'jq-marketplace-engine' ),
		];
	}

	/* ---------------------------------------------------------------
	 * AVAILABILITY BLOCK TYPES
	 * ------------------------------------------------------------- */

	const AVAIL_AVAILABLE = 'available';
	const AVAIL_BLOCKED   = 'blocked';
	const AVAIL_BOOKED    = 'booked';
	const AVAIL_BLACKOUT  = 'blackout';
	const AVAIL_BUFFER    = 'buffer';

	public static function availability_block_types(): array {
		return [
			self::AVAIL_AVAILABLE => __( 'Available', 'jq-marketplace-engine' ),
			self::AVAIL_BLOCKED   => __( 'Blocked', 'jq-marketplace-engine' ),
			self::AVAIL_BOOKED    => __( 'Booked', 'jq-marketplace-engine' ),
			self::AVAIL_BLACKOUT  => __( 'Blackout', 'jq-marketplace-engine' ),
			self::AVAIL_BUFFER    => __( 'Buffer', 'jq-marketplace-engine' ),
		];
	}

	/* ---------------------------------------------------------------
	 * NOTIFICATION CHANNELS
	 * ------------------------------------------------------------- */

	const NOTIFY_DASHBOARD = 'dashboard';
	const NOTIFY_EMAIL     = 'email';
	const NOTIFY_SMS       = 'sms';

	/* ---------------------------------------------------------------
	 * FULFILLMENT MODES
	 * ------------------------------------------------------------- */

	const FULFILL_PICKUP    = 'pickup';
	const FULFILL_DELIVERY  = 'delivery';
	const FULFILL_SHIPPING  = 'shipping';
	const FULFILL_ONSITE    = 'onsite';
	const FULFILL_VIRTUAL   = 'virtual';
	const FULFILL_HYBRID    = 'hybrid';

	public static function fulfillment_modes(): array {
		return [
			self::FULFILL_PICKUP   => __( 'Pickup', 'jq-marketplace-engine' ),
			self::FULFILL_DELIVERY => __( 'Delivery', 'jq-marketplace-engine' ),
			self::FULFILL_SHIPPING => __( 'Shipping', 'jq-marketplace-engine' ),
			self::FULFILL_ONSITE   => __( 'On-Site', 'jq-marketplace-engine' ),
			self::FULFILL_VIRTUAL  => __( 'Virtual / Remote', 'jq-marketplace-engine' ),
			self::FULFILL_HYBRID   => __( 'Hybrid', 'jq-marketplace-engine' ),
		];
	}
}
