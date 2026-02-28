<?php
/**
 * Template: Pending reviews list.
 *
 * Used by [jqme_pending_reviews] shortcode.
 * Shows reviews the logged-in user still needs to submit.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JQME\Reviews\Review;

$pending = Review::get_pending_for_user( get_current_user_id() );
$notice  = sanitize_text_field( $_GET['jqme_notice'] ?? '' );
?>

<div class="jqme-pending-reviews">
	<h2><?php esc_html_e( 'Pending Reviews', 'jq-marketplace-engine' ); ?></h2>

	<?php if ( 'review_submitted' === $notice ) : ?>
		<div class="jqme-notice jqme-notice--success"><?php esc_html_e( 'Thank you! Your review has been submitted.', 'jq-marketplace-engine' ); ?></div>
	<?php endif; ?>

	<div class="jqme-card" style="background:#f0f6fc; margin-bottom:16px;">
		<p style="margin:0; font-size:13px;"><?php esc_html_e( 'Reviews are hidden until both parties submit (or the deadline passes). This ensures honest, unbiased feedback.', 'jq-marketplace-engine' ); ?></p>
	</div>

	<?php if ( empty( $pending ) ) : ?>
		<p><?php esc_html_e( 'You have no pending reviews. Nice — you\'re all caught up!', 'jq-marketplace-engine' ); ?></p>
	<?php else : ?>
		<table class="jqme-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Booking', 'jq-marketplace-engine' ); ?></th>
					<th><?php esc_html_e( 'Listing', 'jq-marketplace-engine' ); ?></th>
					<th><?php esc_html_e( 'Your Role', 'jq-marketplace-engine' ); ?></th>
					<th><?php esc_html_e( 'Deadline', 'jq-marketplace-engine' ); ?></th>
					<th><?php esc_html_e( 'Action', 'jq-marketplace-engine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $pending as $r ) :
					$reviewee = get_userdata( $r->reviewee_id );
					$is_urgent = $r->deadline_at && ( strtotime( $r->deadline_at ) - time() ) < ( 2 * DAY_IN_SECONDS );
				?>
					<tr<?php echo $is_urgent ? ' style="background:#fff3cd;"' : ''; ?>>
						<td>
							<strong><?php echo esc_html( $r->booking_number ?? '—' ); ?></strong>
						</td>
						<td><?php echo esc_html( $r->listing_title ?? '—' ); ?></td>
						<td>
							<?php if ( 'customer' === $r->reviewer_role ) : ?>
								<?php esc_html_e( 'You are the Customer', 'jq-marketplace-engine' ); ?>
								<?php if ( $reviewee ) : ?>
									<br><small><?php printf( esc_html__( 'Reviewing: %s', 'jq-marketplace-engine' ), esc_html( $reviewee->display_name ) ); ?></small>
								<?php endif; ?>
							<?php else : ?>
								<?php esc_html_e( 'You are the Provider', 'jq-marketplace-engine' ); ?>
								<?php if ( $reviewee ) : ?>
									<br><small><?php printf( esc_html__( 'Reviewing: %s', 'jq-marketplace-engine' ), esc_html( $reviewee->display_name ) ); ?></small>
								<?php endif; ?>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $r->deadline_at ) : ?>
								<?php
								$deadline_ts = strtotime( $r->deadline_at );
								$remaining   = $deadline_ts - time();
								$days_left   = max( 0, ceil( $remaining / DAY_IN_SECONDS ) );
								?>
								<span<?php echo $is_urgent ? ' style="color:#856404; font-weight:600;"' : ''; ?>>
									<?php echo esc_html( date_i18n( get_option( 'date_format' ), $deadline_ts ) ); ?>
								</span>
								<br>
								<small>
									<?php if ( $remaining <= 0 ) : ?>
										<?php esc_html_e( 'Expired', 'jq-marketplace-engine' ); ?>
									<?php elseif ( $days_left <= 1 ) : ?>
										<?php esc_html_e( 'Less than 1 day left!', 'jq-marketplace-engine' ); ?>
									<?php else : ?>
										<?php printf( esc_html__( '%d days left', 'jq-marketplace-engine' ), $days_left ); ?>
									<?php endif; ?>
								</small>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td>
							<a href="<?php echo esc_url( add_query_arg( 'review_id', $r->id ) ); ?>"
							   class="jqme-btn jqme-btn--small jqme-btn--primary">
								<?php esc_html_e( 'Leave Review', 'jq-marketplace-engine' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
