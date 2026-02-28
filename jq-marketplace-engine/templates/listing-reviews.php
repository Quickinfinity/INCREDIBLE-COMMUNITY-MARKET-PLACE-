<?php
/**
 * Template: Display published reviews for a listing.
 *
 * Used by [jqme_listing_reviews] shortcode.
 * Expects ?listing_id= in URL or shortcode attribute.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JQME\Reviews\Review;
use JQME\StatusEnums;

$listing_id = absint( $atts['listing_id'] ?? ( $_GET['listing_id'] ?? 0 ) );

if ( ! $listing_id ) {
	echo '<p>' . esc_html__( 'No listing specified.', 'jq-marketplace-engine' ) . '</p>';
	return;
}

$listing = \JQME\Listings\Listing::get( $listing_id );
if ( ! $listing ) {
	echo '<p>' . esc_html__( 'Listing not found.', 'jq-marketplace-engine' ) . '</p>';
	return;
}

$reviews = Review::get_for_listing( $listing_id );
$stats   = Review::get_listing_average( $listing_id );
$notice  = sanitize_text_field( $_GET['jqme_notice'] ?? '' );
?>

<div class="jqme-listing-reviews">
	<h2><?php printf( esc_html__( 'Reviews for %s', 'jq-marketplace-engine' ), esc_html( $listing->title ) ); ?></h2>

	<?php if ( 'response_added' === $notice ) : ?>
		<div class="jqme-notice jqme-notice--success"><?php esc_html_e( 'Your response has been posted.', 'jq-marketplace-engine' ); ?></div>
	<?php elseif ( 'review_flagged' === $notice ) : ?>
		<div class="jqme-notice jqme-notice--info"><?php esc_html_e( 'Review has been flagged for admin review.', 'jq-marketplace-engine' ); ?></div>
	<?php endif; ?>

	<!-- Rating summary -->
	<div class="jqme-card" style="margin-bottom:20px;">
		<div style="display:flex; align-items:center; gap:16px;">
			<div style="font-size:48px; font-weight:700; line-height:1;">
				<?php echo esc_html( $stats['average'] ?: '—' ); ?>
			</div>
			<div>
				<div style="font-size:24px; color:#f0ad4e;">
					<?php
					$full  = floor( $stats['average'] );
					$half  = ( $stats['average'] - $full ) >= 0.5 ? 1 : 0;
					$empty = 5 - $full - $half;
					echo str_repeat( '&#9733;', $full );
					if ( $half ) {
						echo '&#9733;';
					}
					echo str_repeat( '&#9734;', max( 0, $empty ) );
					?>
				</div>
				<div style="font-size:14px; color:#666;">
					<?php printf( esc_html( _n( '%d review', '%d reviews', $stats['count'], 'jq-marketplace-engine' ) ), $stats['count'] ); ?>
				</div>
			</div>
		</div>
	</div>

	<?php if ( empty( $reviews ) ) : ?>
		<p><?php esc_html_e( 'No reviews yet for this listing.', 'jq-marketplace-engine' ); ?></p>
	<?php else : ?>
		<?php foreach ( $reviews as $r ) :
			$categories = $r->rating_categories ? json_decode( $r->rating_categories, true ) : [];
			$is_owner   = is_user_logged_in() && (int) $r->reviewee_id === get_current_user_id();
		?>
			<div class="jqme-card jqme-review-card" style="margin-bottom:16px;">
				<!-- Review header -->
				<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
					<div>
						<strong><?php echo esc_html( $r->reviewer_name ?? __( 'Anonymous', 'jq-marketplace-engine' ) ); ?></strong>
						<span style="color:#f0ad4e; margin-left:8px;">
							<?php echo str_repeat( '&#9733;', (int) $r->overall_rating ) . str_repeat( '&#9734;', 5 - (int) $r->overall_rating ); ?>
						</span>
					</div>
					<small style="color:#999;">
						<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $r->published_at ) ) ); ?>
					</small>
				</div>

				<!-- Title -->
				<?php if ( $r->title ) : ?>
					<h4 style="margin:0 0 8px 0;"><?php echo esc_html( $r->title ); ?></h4>
				<?php endif; ?>

				<!-- Body -->
				<?php if ( $r->body ) : ?>
					<p style="margin:0 0 12px 0;"><?php echo esc_html( $r->body ); ?></p>
				<?php endif; ?>

				<!-- Category ratings -->
				<?php if ( ! empty( $categories ) ) : ?>
					<div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:12px;">
						<?php foreach ( $categories as $cat_key => $cat_val ) :
							if ( ! $cat_val ) continue;
							$cat_label = ucwords( str_replace( '_', ' ', $cat_key ) );
						?>
							<span class="jqme-badge" style="font-size:11px;">
								<?php echo esc_html( $cat_label ); ?>: <?php echo esc_html( $cat_val ); ?>/5
							</span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<!-- Provider response -->
				<?php if ( $r->provider_response ) : ?>
					<div style="background:#f8f9fa; border-left:3px solid #0073aa; padding:10px 14px; margin-top:8px;">
						<strong style="font-size:12px;"><?php esc_html_e( 'Provider Response:', 'jq-marketplace-engine' ); ?></strong>
						<p style="margin:4px 0 0 0; font-size:13px;"><?php echo esc_html( $r->provider_response ); ?></p>
						<?php if ( $r->provider_response_at ) : ?>
							<small style="color:#999;"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $r->provider_response_at ) ) ); ?></small>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<!-- Actions row -->
				<div style="display:flex; gap:8px; margin-top:12px;">
					<?php if ( $is_owner && ! $r->provider_response ) : ?>
						<!-- Provider can respond -->
						<button type="button" class="jqme-btn jqme-btn--small jqme-toggle-response" data-review="<?php echo esc_attr( $r->id ); ?>">
							<?php esc_html_e( 'Respond', 'jq-marketplace-engine' ); ?>
						</button>
					<?php endif; ?>

					<?php if ( is_user_logged_in() && (int) $r->reviewer_id !== get_current_user_id() && ! $r->flagged ) : ?>
						<!-- Flag review -->
						<button type="button" class="jqme-btn jqme-btn--small jqme-toggle-flag" data-review="<?php echo esc_attr( $r->id ); ?>" style="color:#dc3545;">
							<?php esc_html_e( 'Report', 'jq-marketplace-engine' ); ?>
						</button>
					<?php endif; ?>
				</div>

				<!-- Response form (hidden by default) -->
				<?php if ( $is_owner && ! $r->provider_response ) : ?>
					<div class="jqme-response-form" id="response-form-<?php echo esc_attr( $r->id ); ?>" style="display:none; margin-top:12px;">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'jqme_provider_response_' . $r->id ); ?>
							<input type="hidden" name="action" value="jqme_provider_response">
							<input type="hidden" name="review_id" value="<?php echo esc_attr( $r->id ); ?>">
							<textarea name="provider_response" rows="3" placeholder="<?php esc_attr_e( 'Write your response...', 'jq-marketplace-engine' ); ?>" required></textarea>
							<button type="submit" class="jqme-btn jqme-btn--small jqme-btn--primary" style="margin-top:8px;">
								<?php esc_html_e( 'Post Response', 'jq-marketplace-engine' ); ?>
							</button>
						</form>
					</div>
				<?php endif; ?>

				<!-- Flag form (hidden by default) -->
				<?php if ( is_user_logged_in() && (int) $r->reviewer_id !== get_current_user_id() && ! $r->flagged ) : ?>
					<div class="jqme-flag-form" id="flag-form-<?php echo esc_attr( $r->id ); ?>" style="display:none; margin-top:12px;">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'jqme_flag_review_' . $r->id ); ?>
							<input type="hidden" name="action" value="jqme_flag_review">
							<input type="hidden" name="review_id" value="<?php echo esc_attr( $r->id ); ?>">
							<textarea name="flag_reason" rows="2" placeholder="<?php esc_attr_e( 'Why are you reporting this review?', 'jq-marketplace-engine' ); ?>" required></textarea>
							<button type="submit" class="jqme-btn jqme-btn--small jqme-btn--danger" style="margin-top:8px;">
								<?php esc_html_e( 'Submit Report', 'jq-marketplace-engine' ); ?>
							</button>
						</form>
					</div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	document.querySelectorAll('.jqme-toggle-response').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var form = document.getElementById('response-form-' + this.dataset.review);
			if (form) form.style.display = form.style.display === 'none' ? 'block' : 'none';
		});
	});
	document.querySelectorAll('.jqme-toggle-flag').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var form = document.getElementById('flag-form-' + this.dataset.review);
			if (form) form.style.display = form.style.display === 'none' ? 'block' : 'none';
		});
	});
});
</script>
