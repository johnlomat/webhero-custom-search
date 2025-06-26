<?php
/**
 * Shortcodes for WebHero Custom Search
 *
 * @package WebHero
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generate custom search form HTML.
 *
 * @since 1.0.0
 * @return string The search form HTML.
 */
function webhero_cs_form() {
	ob_start();

	$search_query = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	$current_url  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	$is_ms        = false !== strpos( $current_url, 'ms/' );
	?>
	<form role="search" method="get" class="custom-search-form" aria-label="<?php esc_attr_e( 'Site Search', 'webhero' ); ?>" action="<?php echo esc_url( home_url( '/search/' ) ); ?>">
		<div class="search-input-wrapper">
			<input type="text"
				name="q"
				aria-label="<?php esc_attr_e( 'Search input', 'webhero' ); ?>"
				placeholder="<?php $is_ms ? esc_attr_e( 'Cari...', 'webhero' ) : esc_attr_e( 'Search...', 'webhero' ); ?>"
				value="<?php echo esc_attr( $search_query ); ?>">
			<span class="clear-search" role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Clear search', 'webhero' ); ?>">&times;</span>
		</div>
		<button type="submit">
			<span class="search-text">
				<?php $is_ms ? esc_html_e( 'Cari', 'webhero' ) : esc_html_e( 'Search', 'webhero' ); ?>
			</span>
			<span class="spinner"></span>
		</button>
	</form>
	<?php
	return ob_get_clean();
}
add_shortcode( 'custom_search', 'webhero_cs_form' );

/**
 * Generate custom search results HTML.
 *
 * If the search query is longer than 30 characters, display a message and stop.
 * Uses AJAX to load search results for consistent experience.
 * When no query parameter is present, shows default results (all watches and articles).
 *
 * @since 1.0.0
 * @return string The search results HTML.
 */
function webhero_cs_results() {
	ob_start();
	
	$search_query = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	
	if ( strlen( $search_query ) > 30 ) {
		?>
		<div class="custom-search-results">
			<p class="search-too-long"><?php esc_html_e( 'Your search query is too long. Please limit it to 30 characters or fewer.', 'webhero' ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}
    
    // Special handling for 'rolex' search
    if ( 'rolex' === strtolower( $search_query ) ) {
        // Get the page with 'rolex' slug
        $rolex_page = get_posts(
            array(
                'name'           => 'rolex',
                'post_type'      => 'page',
                'posts_per_page' => 1,
            )
        );
        
        if ( ! empty( $rolex_page ) ) {
            $rolex_page  = $rolex_page[0];
            $current_url = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
            $is_ms       = false !== strpos( $current_url, 'ms/' );
            ?>
            <div class="custom-search-results">
                <h1 class="search-title">
                <?php
                    if ( $is_ms ) {
                        printf(
                            /* translators: %s: search query */
                            esc_html__( 'Hasil Carian untuk: %s', 'webhero' ),
                            '<span>' . esc_html( $search_query ) . '</span>'
                        );
                    } else {
                        printf(
                            /* translators: %s: search query */
                            esc_html__( 'Search Results for: %s', 'webhero' ),
                            '<span>' . esc_html( $search_query ) . '</span>'
                        );
                    }
                ?>
                </h1>
                
                <div class="post-results">
                    <div class="articles-grid">
                        <article id="post-<?php echo esc_attr( $rolex_page->ID ); ?>" <?php post_class( '', $rolex_page->ID ); ?>>
                            <?php if ( has_post_thumbnail( $rolex_page->ID ) ) : ?>
                                <div class="post-thumbnail">
                                    <a href="<?php echo esc_url( get_permalink( $rolex_page->ID ) ); ?>">
                                        <?php echo get_the_post_thumbnail( $rolex_page->ID, 'medium', array( 'class' => 'featured-image' ) ); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            <footer class="entry-footer">
                                <a href="<?php echo esc_url( get_permalink( $rolex_page->ID ) ); ?>" class="read-more">
                                    <?php if ( $is_ms ) :
                                        esc_html_e( 'Maklumat lanjut', 'webhero' );
                                    else :
                                        esc_html_e( 'Read More', 'webhero' );
                                    endif;
                                    ?>
                                </a>
                            </footer>
                        </article>
                    </div>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }
    }
	
	$paged_products = isset( $_GET['paged_products'] ) ? absint( $_GET['paged_products'] ) : 1;
	$paged_posts    = isset( $_GET['paged_posts'] ) ? absint( $_GET['paged_posts'] ) : 1;
	$current_url    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	$is_ms          = false !== strpos( $current_url, 'ms/' );
	?>
	<div class="custom-search-results">
		<?php if ( ! empty( $search_query ) ) : ?>
			<h1 class="search-title">
				<?php
				if ( $is_ms ) {
					printf(
						/* translators: %s: search query */
						esc_html__( 'Hasil Carian untuk: %s', 'webhero' ),
						'<span>' . esc_html( $search_query ) . '</span>'
					);
				} else {
					printf(
						/* translators: %s: search query */
						esc_html__( 'Search Results for: %s', 'webhero' ),
						'<span>' . esc_html( $search_query ) . '</span>'
					);
				}
				?>
			</h1>
		<?php else : ?>
			<h1 class="search-title">
				<?php 
				if ( $is_ms ) {
					esc_html_e( 'Semua Jam Tangan dan Artikel', 'webhero' );
				} else {
					esc_html_e( 'All Watches and Articles', 'webhero' );
				}
				?>
			</h1>
		<?php endif; ?>

		<?php 
		// Get collection (product categories) results
		$collection_results = webhero_cs_get_collection_results( $search_query );
		if ( $collection_results['has_results'] ) : ?>
			<div class="collection-results">
				<h2 class="section-title">
					<?php
					if ( $is_ms ) {
						esc_html_e( 'Koleksi', 'webhero' );
					} else {
						esc_html_e( 'Collections', 'webhero' );
					}
					?>
				</h2>
				<p class="search-description">
					<?php
					if ( $is_ms ) {
						esc_html_e( 'Terokai koleksi kami berdasarkan carian anda', 'webhero' );
					} else {
						esc_html_e( 'Explore our collections based on your search', 'webhero' );
					}
					?>
				</p>
				<div class="loading-indicator" style="display: none;"><?php esc_html_e( 'Loading...', 'webhero' ); ?></div>
				<div class="collection-container">
					<?php echo wp_kses_post( $collection_results['html'] ); ?>
				</div>
			</div>
		<?php endif; ?>
        
		<?php 
		// By default, show watches section
		$hide_watches_section = false;
		
		// Only hide watches section when collections are ACTUALLY shown (has results)
		if ( isset( $collection_results ) && ! empty( $collection_results['has_results'] ) && true === $collection_results['has_results'] ) {
			$hide_watches_section = true;
		}
		
		$product_results = webhero_cs_get_product_results( $search_query, $paged_products );

		if ( $product_results['has_results'] && ! $hide_watches_section ) : ?>
			<div class="product-results">
				<h2 class="section-title">
					<?php
					if ( $is_ms ) {
						esc_html_e( 'Jam Tangan', 'webhero' );
					} else {
						esc_html_e( 'Watches', 'webhero' );
					}
					?>
				</h2>
				<p class="search-description">
					<?php
					if ( $is_ms ) {
						esc_html_e( 'Semak imbas jam tangan Rolex berdasarkan carian anda', 'webhero' );
					} else {
						esc_html_e( 'Browse Rolex watches based on your search', 'webhero' );
					}
					?>
				</p>
				<div class="loading-indicator" style="display: none;"><?php esc_html_e( 'Loading...', 'webhero' ); ?></div>
				<div class="products-container">
					<?php echo wp_kses_post( $product_results['html'] ); ?>
				</div>
				<div class="product-pagination">
					<?php echo wp_kses_post( webhero_cs_get_product_pagination( $search_query, $paged_products, $product_results['query'] ) ); ?>
				</div>
			</div>
		<?php endif; ?>

		<?php
		$post_results = webhero_cs_get_post_results( $search_query, $paged_posts );
		if ( $post_results['has_results'] ) : ?>
			<div class="post-results">
				<h2 class="section-title">
					<?php
					if ( $is_ms ) {
						esc_html_e( 'Artikel', 'webhero' );
					} else {
						esc_html_e( 'Articles', 'webhero' );
					}
					?>
				</h2>
				<p class="search-description">
					<?php
					if ( $is_ms ) {
						esc_html_e( 'Terokai artikel yang berkaitan dengan carian anda', 'webhero' );
					} else {
						esc_html_e( 'Explore articles related to your search', 'webhero' );
					}
					?>
				</p>
				<div class="loading-indicator" style="display: none;"><?php esc_html_e( 'Loading...', 'webhero' ); ?></div>
				<div class="articles-container">
					<?php echo wp_kses_post( $post_results['html'] ); ?>
                </div>
                <div class="post-pagination">
                    <?php echo wp_kses_post( webhero_cs_get_post_pagination( $search_query, $paged_posts ) ); ?>
                </div>
            </div>
        <?php endif; ?>

		<?php if ( ! $collection_results['has_results'] && ! $product_results['has_results'] && ! $post_results['has_results'] && ! empty( $search_query ) ) : ?>
			<div class="no-results-message">
				<h2><?php esc_html_e( 'Your search did not return any results.', 'webhero' ); ?></h2>
				<p><?php esc_html_e( 'We invite you to explore these exceptional collections:', 'webhero' ); ?></p>
				<ul>
					<li>
						<?php
						/* translators: %s: URL to featured collection */
						printf(
							esc_html__( 'Discover our %s, where timeless elegance awaits.', 'webhero' ),
							'<a href="' . esc_url( 'https://www.sweecheong.com.my/rolex/watches/' ) . '" target="_blank">' . esc_html__( 'Featured Collection', 'webhero' ) . '</a>'
						);
						?>
					</li>
					<li>
						<?php
						/* translators: %s: URL to new watches */
						printf(
							esc_html__( 'Explore the %s to experience the latest innovations from Rolex.', 'webhero' ),
							'<a href="' . esc_url( 'https://www.sweecheong.com.my/rolex/new-watches/' ) . '" target="_blank">' . esc_html__( 'New Watches', 'webhero' ) . '</a>'
						);
						?>
					</li>
					<li>
						<?php
						/* translators: %s: URL to contact page */
						printf(
							esc_html__( 'If you need further assistance, our team is ready to assist you—%s for personalized guidance.', 'webhero' ),
							'<a href="' . esc_url( 'https://www.sweecheong.com.my/rolex/contact-kota-bharu/' ) . '" target="_blank">' . esc_html__( 'Contact Us', 'webhero' ) . '</a>'
						);
						?>
					</li>
				</ul>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'custom_search_results', 'webhero_cs_results' );
