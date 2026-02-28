<?php
/**
 * Template: Browse/search marketplace listings.
 *
 * Used by [jqme_browse_listings] shortcode.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JQME\Listings\ListingSearch;
use JQME\StatusEnums;
use JQME\Analytics\Ranking;

$query        = sanitize_text_field( $_GET['q'] ?? '' );
$listing_type = sanitize_text_field( $_GET['type'] ?? ( $atts['type'] ?? '' ) );
$category     = sanitize_text_field( $_GET['category'] ?? '' );
$sort         = sanitize_text_field( $_GET['sort'] ?? 'relevance' );
$min_price    = floatval( $_GET['min_price'] ?? 0 );
$max_price    = floatval( $_GET['max_price'] ?? 0 );
$min_rating   = floatval( $_GET['min_rating'] ?? 0 );
$paged        = max( 1, absint( $_GET['lpage'] ?? 1 ) );
$per_page     = 20;

$result = ListingSearch::search( [
	'query'        => $query,
	'listing_type' => $listing_type,
	'category'     => $category,
	'sort'         => $sort,
	'min_price'    => $min_price,
	'max_price'    => $max_price,
	'min_rating'   => $min_rating,
	'limit'        => $per_page,
	'offset'       => ( $paged - 1 ) * $per_page,
] );

$listings     = $result['results'];
$total        = $result['total'];
$total_pages  = $result['pages'];
$filter_opts  = ListingSearch::get_filter_options( $listing_type );
$types        = StatusEnums::listing_types();

$sort_options = [
	'relevance'  => __( 'Relevance', 'jq-marketplace-engine' ),
	'newest'     => __( 'Newest', 'jq-marketplace-engine' ),
	'price_low'  => __( 'Price: Low to High', 'jq-marketplace-engine' ),
	'price_high' => __( 'Price: High to Low', 'jq-marketplace-engine' ),
	'rating'     => __( 'Highest Rated', 'jq-marketplace-engine' ),
];
?>

<div class="jqme-browse-listings">

	<!-- Search & filters -->
	<form method="get" class="jqme-search-form" style="margin-bottom:20px;">
		<div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-bottom:12px;">
			<input type="text" name="q" value="<?php echo esc_attr( $query ); ?>"
				   placeholder="<?php esc_attr_e( 'Search equipment, services...', 'jq-marketplace-engine' ); ?>"
				   style="flex:1; min-width:200px;">
			<button type="submit" class="jqme-btn jqme-btn--primary"><?php esc_html_e( 'Search', 'jq-marketplace-engine' ); ?></button>
		</div>

		<div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
			<!-- Type filter -->
			<select name="type">
				<option value=""><?php esc_html_e( 'All Types', 'jq-marketplace-engine' ); ?></option>
				<?php foreach ( $types as $tk => $tl ) : ?>
					<option value="<?php echo esc_attr( $tk ); ?>" <?php selected( $listing_type, $tk ); ?>>
						<?php echo esc_html( $tl ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<!-- Category filter -->
			<?php if ( ! empty( $filter_opts['categories'] ) ) : ?>
				<select name="category">
					<option value=""><?php esc_html_e( 'All Categories', 'jq-marketplace-engine' ); ?></option>
					<?php foreach ( $filter_opts['categories'] as $cat ) : ?>
						<option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $category, $cat ); ?>>
							<?php echo esc_html( $cat ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>

			<!-- Sort -->
			<select name="sort">
				<?php foreach ( $sort_options as $sk => $sl ) : ?>
					<option value="<?php echo esc_attr( $sk ); ?>" <?php selected( $sort, $sk ); ?>>
						<?php echo esc_html( $sl ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<button type="submit" class="jqme-btn jqme-btn--small"><?php esc_html_e( 'Apply', 'jq-marketplace-engine' ); ?></button>
		</div>
	</form>

	<!-- Results count -->
	<p style="color:#666; margin-bottom:16px;">
		<?php printf( esc_html( _n( '%d listing found', '%d listings found', $total, 'jq-marketplace-engine' ) ), $total ); ?>
	</p>

	<?php if ( empty( $listings ) ) : ?>
		<div class="jqme-card" style="text-align:center; padding:40px 20px;">
			<p style="font-size:16px; color:#666;"><?php esc_html_e( 'No listings found matching your criteria.', 'jq-marketplace-engine' ); ?></p>
			<p><a href="<?php echo esc_url( remove_query_arg( [ 'q', 'type', 'category', 'sort', 'min_price', 'max_price', 'min_rating', 'lpage' ] ) ); ?>" class="jqme-btn"><?php esc_html_e( 'Clear Filters', 'jq-marketplace-engine' ); ?></a></p>
		</div>
	<?php else : ?>
		<div class="jqme-listing-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:16px;">
			<?php foreach ( $listings as $l ) :
				$price = $l->day_rate ?: $l->asking_price ?: $l->hourly_rate ?: 0;
				$price_label = $l->day_rate ? __( '/day', 'jq-marketplace-engine' )
					: ( $l->hourly_rate ? __( '/hr', 'jq-marketplace-engine' ) : '' );

				// Get primary image.
				$primary_image = '';
				$assets = \JQME\Listings\Listing::get_assets( (int) $l->id );
				if ( ! empty( $assets ) ) {
					$primary_image = $assets[0]->file_url ?? '';
				}

				$provider_tier = Ranking::get_tier( (float) ( $l->trust_score ?? 0 ) );
			?>
				<div class="jqme-card jqme-listing-card" style="overflow:hidden;">
					<?php if ( $l->featured ) : ?>
						<span style="position:absolute; top:8px; right:8px; background:#f0ad4e; color:#fff; padding:2px 8px; border-radius:3px; font-size:11px; font-weight:600;">
							<?php esc_html_e( 'Featured', 'jq-marketplace-engine' ); ?>
						</span>
					<?php endif; ?>

					<!-- Image -->
					<?php if ( $primary_image ) : ?>
						<div style="height:180px; overflow:hidden; background:#f5f5f5;">
							<img src="<?php echo esc_url( $primary_image ); ?>" alt="<?php echo esc_attr( $l->title ); ?>" style="width:100%; height:100%; object-fit:cover;">
						</div>
					<?php else : ?>
						<div style="height:180px; background:#f5f5f5; display:flex; align-items:center; justify-content:center; color:#ccc; font-size:48px;">&#9881;</div>
					<?php endif; ?>

					<div style="padding:12px;">
						<!-- Type badge -->
						<small style="color:#666;">
							<?php echo esc_html( $types[ $l->listing_type ] ?? $l->listing_type ); ?>
							<?php if ( $l->category ) : ?>
								&middot; <?php echo esc_html( $l->category ); ?>
							<?php endif; ?>
						</small>

						<h3 style="margin:4px 0 8px; font-size:16px;">
							<a href="<?php echo esc_url( add_query_arg( 'listing_id', $l->id ) ); ?>" style="color:inherit; text-decoration:none;">
								<?php echo esc_html( $l->title ); ?>
							</a>
						</h3>

						<!-- Rating -->
						<?php if ( $l->average_rating > 0 ) : ?>
							<div style="margin-bottom:8px;">
								<span style="color:#f0ad4e;">
									<?php echo str_repeat( '&#9733;', (int) round( (float) $l->average_rating ) ); ?>
									<?php echo str_repeat( '&#9734;', 5 - (int) round( (float) $l->average_rating ) ); ?>
								</span>
								<small style="color:#666;">
									(<?php echo esc_html( $l->review_count ); ?>)
								</small>
							</div>
						<?php endif; ?>

						<!-- Provider -->
						<p style="margin:0 0 8px; font-size:13px; color:#666;">
							<?php echo esc_html( $l->company_name ?: '' ); ?>
							<?php if ( $l->provider_city ) : ?>
								&middot; <?php echo esc_html( $l->provider_city ); ?>, <?php echo esc_html( $l->provider_state ); ?>
							<?php endif; ?>
						</p>

						<!-- Price -->
						<div style="display:flex; justify-content:space-between; align-items:center;">
							<?php if ( $price > 0 ) : ?>
								<span style="font-size:20px; font-weight:700; color:#333;">
									$<?php echo esc_html( number_format( (float) $price, 2 ) ); ?><?php echo esc_html( $price_label ); ?>
								</span>
							<?php else : ?>
								<span style="font-size:14px; color:#666;"><?php esc_html_e( 'Contact for pricing', 'jq-marketplace-engine' ); ?></span>
							<?php endif; ?>

							<a href="<?php echo esc_url( add_query_arg( 'listing_id', $l->id ) ); ?>" class="jqme-btn jqme-btn--small jqme-btn--primary">
								<?php esc_html_e( 'View', 'jq-marketplace-engine' ); ?>
							</a>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<!-- Pagination -->
		<?php if ( $total_pages > 1 ) : ?>
			<div class="jqme-pagination" style="margin-top:20px; text-align:center;">
				<?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
					<?php if ( $i === $paged ) : ?>
						<span class="jqme-btn jqme-btn--small jqme-btn--primary"><?php echo $i; ?></span>
					<?php else : ?>
						<a class="jqme-btn jqme-btn--small" href="<?php echo esc_url( add_query_arg( 'lpage', $i ) ); ?>"><?php echo $i; ?></a>
					<?php endif; ?>
				<?php endfor; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
