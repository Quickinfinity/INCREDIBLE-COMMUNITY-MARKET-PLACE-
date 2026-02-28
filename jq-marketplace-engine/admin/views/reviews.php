<?php
/**
 * Admin reviews page — moderation, flagged review management.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JQME\Reviews\Review;
use JQME\StatusEnums;

$status_filter = sanitize_text_field( $_GET['status'] ?? '' );
$flagged_filter = isset( $_GET['flagged'] ) ? absint( $_GET['flagged'] ) : null;
$paged         = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page      = 20;

$notice = sanitize_text_field( $_GET['jqme_notice'] ?? '' );
$review_statuses = StatusEnums::review_statuses();

// Tab counts.
$tab_counts = [
	'flagged'   => Review::count( [ 'flagged' => 1 ] ),
	'published' => Review::count( [ 'status' => StatusEnums::REVIEW_PUBLISHED ] ),
	'pending'   => Review::count( [ 'status' => StatusEnums::REVIEW_PENDING_BOTH ] )
				 + Review::count( [ 'status' => StatusEnums::REVIEW_PENDING_CUSTOMER ] )
				 + Review::count( [ 'status' => StatusEnums::REVIEW_PENDING_PROVIDER ] ),
	'submitted' => Review::count( [ 'status' => StatusEnums::REVIEW_SUBMITTED ] ),
];
$total_count = Review::count();

$query_args = [
	'status' => $status_filter,
	'limit'  => $per_page,
	'offset' => ( $paged - 1 ) * $per_page,
];
if ( null !== $flagged_filter ) {
	$query_args['flagged'] = $flagged_filter;
}

$reviews = Review::query( $query_args );
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Reviews', 'jq-marketplace-engine' ); ?></h1>

	<?php if ( $notice ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( ucwords( str_replace( '_', ' ', $notice ) ) ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Filter tabs -->
	<ul class="subsubsub">
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-reviews' ) ); ?>"
			   class="<?php echo empty( $status_filter ) && null === $flagged_filter ? 'current' : ''; ?>">
				<?php printf( '%s <span class="count">(%d)</span>', esc_html__( 'All', 'jq-marketplace-engine' ), $total_count ); ?>
			</a> |
		</li>
		<?php if ( $tab_counts['flagged'] > 0 ) : ?>
			<li>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-reviews&flagged=1' ) ); ?>"
				   class="<?php echo 1 === $flagged_filter ? 'current' : ''; ?>" style="color:#d63638;">
					<?php printf( '%s <span class="count">(%d)</span>', esc_html__( 'Flagged', 'jq-marketplace-engine' ), $tab_counts['flagged'] ); ?>
				</a> |
			</li>
		<?php endif; ?>
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-reviews&status=' . StatusEnums::REVIEW_PUBLISHED ) ); ?>"
			   class="<?php echo $status_filter === StatusEnums::REVIEW_PUBLISHED ? 'current' : ''; ?>">
				<?php printf( '%s <span class="count">(%d)</span>', esc_html__( 'Published', 'jq-marketplace-engine' ), $tab_counts['published'] ); ?>
			</a> |
		</li>
		<?php if ( $tab_counts['submitted'] > 0 ) : ?>
			<li>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-reviews&status=' . StatusEnums::REVIEW_SUBMITTED ) ); ?>"
				   class="<?php echo $status_filter === StatusEnums::REVIEW_SUBMITTED ? 'current' : ''; ?>">
					<?php printf( '%s <span class="count">(%d)</span>', esc_html__( 'Submitted', 'jq-marketplace-engine' ), $tab_counts['submitted'] ); ?>
				</a> |
			</li>
		<?php endif; ?>
		<?php if ( $tab_counts['pending'] > 0 ) : ?>
			<li>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-reviews&status=' . StatusEnums::REVIEW_PENDING_BOTH ) ); ?>"
				   class="<?php echo $status_filter === StatusEnums::REVIEW_PENDING_BOTH ? 'current' : ''; ?>">
					<?php printf( '%s <span class="count">(%d)</span>', esc_html__( 'Pending', 'jq-marketplace-engine' ), $tab_counts['pending'] ); ?>
				</a>
			</li>
		<?php endif; ?>
	</ul>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width:40px;"><?php esc_html_e( 'ID', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Booking', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Reviewer', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Reviewee', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Role', 'jq-marketplace-engine' ); ?></th>
				<th style="width:60px;"><?php esc_html_e( 'Rating', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Title', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Status', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'jq-marketplace-engine' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $reviews ) ) : ?>
				<tr><td colspan="9"><?php esc_html_e( 'No reviews found.', 'jq-marketplace-engine' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $reviews as $r ) :
					$badge = match ( $r->status ) {
						StatusEnums::REVIEW_PUBLISHED      => 'jqme-badge--success',
						StatusEnums::REVIEW_HIDDEN_FLAGGED => 'jqme-badge--danger',
						StatusEnums::REVIEW_SUBMITTED      => 'jqme-badge--info',
						StatusEnums::REVIEW_EXPIRED        => 'jqme-badge--muted',
						default                            => 'jqme-badge--warning',
					};
					$stars = $r->overall_rating > 0 ? str_repeat( '&#9733;', $r->overall_rating ) . str_repeat( '&#9734;', 5 - $r->overall_rating ) : '—';
				?>
					<tr<?php echo $r->flagged ? ' style="background:#fff0f0;"' : ''; ?>>
						<td>#<?php echo esc_html( $r->id ); ?></td>
						<td>
							<?php if ( $r->booking_number ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-bookings&action=view&id=' . $r->booking_id ) ); ?>">
									<?php echo esc_html( $r->booking_number ); ?>
								</a>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $r->reviewer_name ?? '—' ); ?></td>
						<td><?php echo esc_html( $r->reviewee_name ?? '—' ); ?></td>
						<td><small><?php echo esc_html( ucfirst( $r->reviewer_role ) ); ?></small></td>
						<td style="color:#f0ad4e;"><?php echo $stars; ?></td>
						<td>
							<?php echo esc_html( $r->title ?: '—' ); ?>
							<?php if ( $r->body ) : ?>
								<br><small style="color:#666;"><?php echo esc_html( wp_trim_words( $r->body, 15 ) ); ?></small>
							<?php endif; ?>
							<?php if ( $r->flagged && $r->flag_reason ) : ?>
								<br><small style="color:#d63638;"><?php echo esc_html( $r->flag_reason ); ?></small>
							<?php endif; ?>
						</td>
						<td><span class="jqme-badge <?php echo esc_attr( $badge ); ?>"><?php echo esc_html( $review_statuses[ $r->status ] ?? $r->status ); ?></span></td>
						<td>
							<?php if ( $r->flagged ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<?php wp_nonce_field( 'jqme_review_unflag_' . $r->id ); ?>
									<input type="hidden" name="action" value="jqme_review_unflag">
									<input type="hidden" name="review_id" value="<?php echo esc_attr( $r->id ); ?>">
									<button type="submit" class="button button-small button-primary"><?php esc_html_e( 'Unflag', 'jq-marketplace-engine' ); ?></button>
								</form>
							<?php elseif ( StatusEnums::REVIEW_PUBLISHED === $r->status ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<?php wp_nonce_field( 'jqme_review_flag_' . $r->id ); ?>
									<input type="hidden" name="action" value="jqme_review_flag">
									<input type="hidden" name="review_id" value="<?php echo esc_attr( $r->id ); ?>">
									<input type="hidden" name="flag_reason" value="Admin flagged">
									<button type="submit" class="button button-small"><?php esc_html_e( 'Flag', 'jq-marketplace-engine' ); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
