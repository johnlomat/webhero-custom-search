<?php
/**
 * Custom Search Implementation
 *
 * @package WebHero
 * @since 1.4
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get client IP address, checking multiple possible headers.
 *
 * Helps with rate limiting behind proxies/load balancers.
 *
 * @return string
 */
function get_client_ip() {
    $ip_headers = array(
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    );
    
    foreach ( $ip_headers as $header ) {
        if ( isset( $_SERVER[ $header ] ) ) {
            // A header might contain multiple IPs
            foreach ( explode( ',', $_SERVER[ $header ] ) as $ip ) {
                $ip = trim( $ip );
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }
    }
    return 'unknown';
}

/**
 * Generate custom search form HTML.
 *
 * @since 1.2
 * @return string The search form HTML.
 */
function custom_search_form() {
    ob_start();
	$current_url = $_SERVER['REQUEST_URI'];
	$is_ms = strpos($current_url, 'ms/') !== false;
    ?>
    <form role="search" method="get" class="custom-search-form" aria-label="Site Search" action="<?php echo esc_url( home_url( '/search/' ) ); ?>">
        <div class="search-input-wrapper">
            <input type="text"
                   name="q"
                   aria-label="Search input"
                   placeholder="<?php if ($is_ms) {
							echo esc_attr_e( 'Cari...', 'webhero' );
						} else {
							echo esc_attr_e( 'Search...', 'webhero' );
						}
								?>"
                   value="<?php echo isset( $_GET['q'] ) ? esc_attr( $_GET['q'] ) : ''; ?>">
            <span class="clear-search" role="button" tabindex="0" aria-label="Clear search">&times;</span>
        </div>
        <button type="submit">
            <span class="search-text"><?php if ($is_ms) {
									echo esc_html_e( 'Cari', 'webhero' ); 
								}else{
									echo esc_html_e( 'Search', 'webhero' ); 
								}
				?></span>
            <span class="spinner"></span>
        </button>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode( 'custom_search', 'custom_search_form' );

/**
 * Generate custom search results HTML.
 *
 * If the search query is longer than 30 characters, display a message and stop.
 *
 * @since 1.2
 * @return string The search results HTML.
 */
function custom_search_results() {
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
    if ( strtolower( $search_query ) === 'rolex' ) {
        // Get the page with 'rolex' slug
        $rolex_page = get_posts(array(
            'name' => 'rolex',
            'post_type' => 'page',
            'posts_per_page' => 1
        ));
        
        if ( !empty($rolex_page) ) {
            $rolex_page = $rolex_page[0];
			$current_url = $_SERVER['REQUEST_URI'];
			$is_ms = strpos($current_url, 'ms/') !== false;
            ?>
            <div class="custom-search-results">
                <h1 class="search-title">
                    <?php
					if ($is_ms) {
					printf(
						esc_html__( 'Hasil Carian untuk: %s', 'webhero' ),
						'<span>' . esc_html( $search_query ) . '</span>'
					);
					} else {
					printf(
						esc_html__( 'Search Results for: %s', 'webhero' ),
						'<span>' . esc_html( $search_query ) . '</span>'
					);
					}
					?>
                </h1>
                
                <div class="post-results">
                    <div class="articles-grid">
                        <article id="post-<?php echo $rolex_page->ID; ?>" <?php post_class('', $rolex_page->ID); ?>>
                            <?php if ( has_post_thumbnail( $rolex_page->ID ) ) : ?>
                                <div class="post-thumbnail">
                                    <a href="<?php echo esc_url( get_permalink( $rolex_page->ID ) ); ?>">
                                        <?php echo get_the_post_thumbnail( $rolex_page->ID, 'medium', array( 'class' => 'featured-image' ) ); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            <footer class="entry-footer">
                                <a href="<?php echo esc_url( get_permalink( $rolex_page->ID ) ); ?>" class="read-more">
                                    <?php if ($is_ms) {
										echo esc_html_e( 'Maklumat lanjut', 'webhero' ); 
									} else {
										echo esc_html_e( 'Read More', 'webhero' ); 
									}
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
	$current_url = $_SERVER['REQUEST_URI'];
	$is_ms = strpos($current_url, 'ms/') !== false;
    ?>
    <div class="custom-search-results">
        <?php if ( ! empty( $search_query ) ) : ?>
            <h1 class="search-title">
                <?php
				if ($is_ms) {
                printf(
                    esc_html__( 'Hasil Carian untuk: %s', 'webhero' ),
                    '<span>' . esc_html( $search_query ) . '</span>'
                );
				} else {
				printf(
                    esc_html__( 'Search Results for: %s', 'webhero' ),
                    '<span>' . esc_html( $search_query ) . '</span>'
                );
				}
                ?>
            </h1>
        <?php else : ?>
            <h1 class="search-title">
				<?php if ($is_ms) {
					echo esc_html_e( 'Semua Jam Tangan dan Artikel', 'webhero' );
				} else {
					echo esc_html_e( 'All Watches and Articles', 'webhero' );
				}
				?>
			</h1>
        <?php endif; ?>

        <?php 
        // Get collection (product categories) results
        $collection_results = get_collection_results( $search_query );
        if ( $collection_results['has_results'] ) : ?>
            <div class="collection-results">
                <h2 class="section-title">
                    <?php if ($is_ms) {
                        echo esc_html_e( 'Koleksi', 'webhero' );
                    } else {
                        echo esc_html_e( 'Collections', 'webhero' );
                    } ?>
                </h2>
                <p class="search-description">
                    <?php if($is_ms) {
                        echo esc_html_e( 'Terokai koleksi kami berdasarkan carian anda', 'webhero' );
                    } else {
                        echo esc_html_e( 'Explore our collections based on your search', 'webhero' );
                    } ?>
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
        if (isset($collection_results) && !empty($collection_results['has_results']) && $collection_results['has_results'] === true) {
            $hide_watches_section = true;
        }
        
        $product_results = get_product_results( $search_query, $paged_products );

        if ( $product_results['has_results'] && !$hide_watches_section ) : ?>
            <div class="product-results">
                <h2 class="section-title">
					<?php if ($is_ms) {
						echo esc_html_e( 'Jam Tangan', 'webhero' );
					} else {
						echo esc_html_e( 'Watches', 'webhero' );
					} ?>
				</h2>
                <p class="search-description">
					<?php if($is_ms) {
						echo esc_html_e( 'Semak imbas jam tangan Rolex berdasarkan carian anda', 'webhero' );
					} else {
						echo esc_html_e( 'Browse Rolex watches based on your search', 'webhero' );
					} ?>
				</p>
                <div class="loading-indicator" style="display: none;"><?php esc_html_e( 'Loading...', 'webhero' ); ?></div>
                <div class="products-container">
                    <?php echo wp_kses_post( $product_results['html'] ); ?>
                </div>
                <div class="product-pagination">
                    <?php echo wp_kses_post( get_product_pagination( $search_query, $paged_products, $product_results['query'] ) ); ?>
                </div>
            </div>
        <?php endif; ?>

        <?php 
		$current_url = $_SERVER['REQUEST_URI'];
		$is_ms = strpos($current_url, 'ms/') !== false;
        $post_results = get_post_results( $search_query, $paged_posts );
        if ( $post_results['has_results'] ) : ?>
            <div class="post-results">
                <h2 class="section-title"><?php if ($is_ms) {
							echo esc_html_e( 'Artikel', 'webhero' ); 
						} else {
							echo esc_html_e( 'Articles', 'webhero' ); 
						}
						?></h2>
                <p class="search-description"><?php if ($is_ms) {
							echo esc_html_e( 'Terokai artikel yang berkaitan dengan carian anda', 'webhero' ); 
						} else {
							echo esc_html_e( 'Explore articles related to your search', 'webhero' ); 
						}
						?></p>
                <div class="loading-indicator" style="display: none;"><?php esc_html_e( 'Loading...', 'webhero' ); ?></div>
                <div class="articles-container">
                    <?php echo wp_kses_post( $post_results['html'] ); ?>
                </div>
                <div class="post-pagination">
                    <?php echo wp_kses_post( get_post_pagination( $search_query, $paged_posts ) ); ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ( ! $collection_results['has_results'] && ! $product_results['has_results'] && ! $post_results['has_results'] && ! empty( $search_query ) ) : ?>
            <div class="no-results-message">
                <h2>Your search didn’t return any results.</h2>
                <p>We invite you to explore these exceptional collections:</p>
                <ul>
                    <li>Discover our <a href="https://www.sweecheong.com.my/rolex/watches/" target="_blank">Featured Collection</a>, where timeless elegance awaits.</li>
                    <li>Explore the <a href="https://www.sweecheong.com.my/rolex/new-watches/" target="_blank">New Watches</a> to experience the latest innovations from Rolex.</li>
                    <li>If you need further assistance, our team is ready to assist you—<a href="https://www.sweecheong.com.my/rolex/contact-kota-bharu/" target="_blank">Contact Us</a> for personalized guidance.</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'custom_search_results', 'custom_search_results' );

/**
 * Get product search results using a combined SQL query.
 *
 * @since 1.2
 * @param string $search_query
 * @param int    $paged
 * @return array
 */
function get_product_results( $search_query, $paged ) {
    global $wpdb;
    
    $posts_per_page = 15;
    $offset         = ( $paged - 1 ) * $posts_per_page;
    $search_query   = trim( $search_query );
    
    if ( empty( $search_query ) ) {
        $rolex_category = get_term_by( 'slug', 'rolex', 'product_cat' );
        if ( ! $rolex_category ) {
            $rolex_category = get_term_by( 'name', 'Rolex', 'product_cat' );
        }
        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => $posts_per_page,
            'paged'          => $paged,
            'tax_query'      => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $rolex_category ? $rolex_category->term_id : 0,
                ),
            ),
        );
        $product_query = new WP_Query( $args );
        ob_start();
        if ( $product_query->have_posts() ) :
            echo '<ul class="products columns-3">';
            while ( $product_query->have_posts() ) :
                $product_query->the_post();
                wc_get_template_part( 'content', 'product' );
            endwhile;
            echo '</ul>';
        else :
            echo '<p>' . esc_html__( 'No watches found.', 'webhero' ) . '</p>';
        endif;
        wp_reset_postdata();
        return array(
            'html'        => ob_get_clean(),
            'query'       => $product_query,
            'has_results' => $product_query->have_posts(),
        );
    }
    
    $rolex_category = get_term_by( 'slug', 'rolex', 'product_cat' );
    if ( ! $rolex_category ) {
        $rolex_category = get_term_by( 'name', 'Rolex', 'product_cat' );
    }
    if ( ! $rolex_category ) {
        return array(
            'html'        => '<p>' . esc_html__( 'No watches found.', 'webhero' ) . '</p>',
            'query'       => null,
            'has_results' => false,
        );
    }
    
    // Limit the number of variations to avoid extremely large queries
    $search_variations = array(
        $search_query,
        str_replace( '-', ' ', $search_query ),
        str_replace( ' ', '-', $search_query ),
        str_replace( array( '-', ' ' ), '', $search_query )
    );
    $search_variations = array_unique( array_filter( $search_variations ) );
    $search_variations = array_slice( $search_variations, 0, 3 ); // limit to first 3
    
    $title_conditions = array();
    $params = array();
    foreach ( $search_variations as $term ) {
        $like = '%' . $wpdb->esc_like( $term ) . '%';
        $title_conditions[] = "p.post_title LIKE %s";
        $params[] = $like;
    }
    $title_clause = implode( ' OR ', $title_conditions );
    
    $acf_fields = array(
        'model_name',
        'diameter_and_material',
        'reference',
        'model_case',
        'water-resistance',
        'bezel',
        'dial',
        'bracelet',
        'movement',
        'calibre',
        'power_reserve',
        'certification',
        'feature_1_title',
        'feature_2_title',
        'feature_3_title',
        'family_name',
        'family_intro',
    );
    
    $meta_conditions = array();
    foreach ( $search_variations as $term ) {
        $like = '%' . $wpdb->esc_like( $term ) . '%';
        foreach ( $acf_fields as $field ) {
            $meta_conditions[] = "(pm.meta_key = %s AND pm.meta_value LIKE %s)";
            $params[] = $field;
            $params[] = $like;
        }
    }
    $meta_clause = implode( ' OR ', $meta_conditions );
    
    $combined_clause = '(' . $title_clause . ' OR ' . $meta_clause . ')';
    
    $sql = "SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->term_relationships} tr ON (p.ID = tr.object_id)
            INNER JOIN {$wpdb->term_taxonomy} tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id)
            LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id)
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND tt.taxonomy = 'product_cat'
            AND tt.term_id = %d
            AND $combined_clause
            LIMIT %d OFFSET %d";
    
    array_unshift( $params, $rolex_category->term_id );
    $params[] = $posts_per_page;
    $params[] = $offset;
    
    $prepared_sql = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $sql ), $params ) );
    $product_ids  = $wpdb->get_col( $prepared_sql );
    
    if ( empty( $product_ids ) ) {
        return array(
            'html'        => '<p>' . esc_html__( 'No watches found.', 'webhero' ) . '</p>',
            'query'       => null,
            'has_results' => false,
        );
    }
    $current_lang = apply_filters( 'wpml_current_language', null );
    $query_args = array(
        'post_type'      => 'product',
        'posts_per_page' => $posts_per_page,
        'paged'          => $paged,
        'post__in'       => $product_ids,
        'orderby'        => 'post__in',
		'lang'           => $current_lang,
    );
    
    $product_query = new WP_Query( $query_args );
    
    ob_start();
    if ( $product_query->have_posts() ) :
        echo '<ul class="products columns-3">';
        while ( $product_query->have_posts() ) :
            $product_query->the_post();
            wc_get_template_part( 'content', 'product' );
        endwhile;
        echo '</ul>';
    else :
        echo '<p>' . esc_html__( 'No watches found.', 'webhero' ) . '</p>';
    endif;
    wp_reset_postdata();
    
    return array(
        'html'        => ob_get_clean(),
        'query'       => $product_query,
        'has_results' => $product_query->have_posts(),
    );
}

/**
 * Get product pagination.
 *
 * @since 1.2
 * @param string   $search_query
 * @param int      $paged
 * @param WP_Query $product_query
 * @return string
 */
function get_product_pagination( $search_query, $paged, $product_query ) {
    if ( ! $product_query || ! $product_query->max_num_pages ) {
        return '';
    }
    $pagination = paginate_links( array(
        'base'      => add_query_arg( 'paged_products', '%#%' ),
        'format'    => '?paged_products=%#%',
        'current'   => $paged,
        'total'     => $product_query->max_num_pages,
        'prev_text' => '«',
        'next_text' => '»',
        'type'      => 'array',
        'mid_size'  => 1,
        'end_size'  => 1,
    ) );
    if ( $pagination ) {
        $output = '<nav class="woocommerce-pagination">';
        $output .= '<ul class="pagination">';
        foreach ( $pagination as $page_link ) {
            $is_current = strpos( $page_link, 'current' ) !== false || strpos( $page_link, 'aria-current' ) !== false;
            $output .= '<li class="page-item' . ( $is_current ? ' active' : '' ) . '">' . $page_link . '</li>';
        }
        $output .= '</ul>';
        $output .= '</nav>';
        return $output;
    }
    return '';
}

/**
 * Get post search results.
 *
 * @since 1.2
 * @param string $search_query
 * @param int    $paged
 * @return array
 */
function get_post_results( $search_query, $paged ) {
    global $wpdb;
    
    $posts_per_page = 15;
    $search_query   = trim( $search_query );
    $search_variations = array(
        $search_query,
        str_replace( '-', ' ', $search_query ),
        str_replace( ' ', '-', $search_query ),
        str_replace( array( '-', ' ' ), '', $search_query )
    );
    $search_variations = array_unique( array_filter( $search_variations ) );
    
    // No limit here; we keep fallback the same to avoid major logic changes
    $conditions = array();
    foreach ( $search_variations as $term ) {
        $like_term = '%' . $wpdb->esc_like( $term ) . '%';
        $conditions[] = $wpdb->prepare( "(post_title LIKE %s OR post_content LIKE %s)", $like_term, $like_term );
    }
    
    $post_ids = array();
    
    // Only execute the query if we have conditions
    if ( ! empty( $conditions ) ) {
        $where_clause = implode( ' OR ', $conditions );
        
        $post_ids = $wpdb->get_col(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'post'
             AND post_status = 'publish'
             AND (" . $where_clause . ")"
        );
    }
    $current_lang = apply_filters( 'wpml_current_language', null );

    $post_args = array(
        'post_type'      => 'post',
        'posts_per_page' => $posts_per_page,
        'paged'          => $paged,
		'lang'           => $current_lang,
    );
    
    if ( ! empty( $post_ids ) ) {
        $post_args['post__in'] = $post_ids;
        $post_args['orderby']  = 'post__in';
    } else if ( ! empty( $search_variations ) ) {
        // Only add meta_query if we have search terms
        $meta_query = array( 'relation' => 'OR' );
        foreach ( $search_variations as $term ) {
            $meta_query[] = array(
                'value'   => $term,
                'compare' => 'LIKE',
            );
        }
        $post_args['meta_query'] = $meta_query;
    } else {
        // If we have no search terms, just return recent posts
        $post_args['orderby'] = 'date';
        $post_args['order'] = 'DESC';
    }
    
    $post_query = new WP_Query( $post_args );
    $current_url = $_SERVER['REQUEST_URI'];
	$is_ms = strpos($current_url, 'ms/') !== false;
    ob_start();
    if ( $post_query->have_posts() ) :
        echo '<div class="articles-grid">';
        while ( $post_query->have_posts() ) :
            $post_query->the_post();
            ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <?php if ( has_post_thumbnail() ) : ?>
                    <div class="post-thumbnail">
                        <a href="<?php the_permalink(); ?>">
                            <?php the_post_thumbnail( 'medium', array( 'class' => 'featured-image' ) ); ?>
                        </a>
                    </div>
                <?php endif; ?>
                <header class="entry-header">
                    <h2 class="entry-title">
                        <a href="<?php the_permalink(); ?>" rel="bookmark"><?php the_title(); ?></a>
                    </h2>
                </header>
                <footer class="entry-footer">
                    <a href="<?php the_permalink(); ?>" class="read-more">
						<?php if ($is_ms) {
							echo esc_html_e( 'Maklumat lanjut', 'webhero' ); 
						} else {
							echo esc_html_e( 'Read More', 'webhero' ); 
						}
						?>
                    </a>
                </footer>
            </article>
            <?php
        endwhile;
        echo '</div>';
    else :
        echo '<p>' . esc_html__( 'No articles found.', 'webhero' ) . '</p>';
    endif;
    wp_reset_postdata();
    
    return array(
        'html'        => ob_get_clean(),
        'has_results' => $post_query->have_posts(),
    );
}

/**
 * Get post pagination.
 *
 * @since 1.2
 * @param string $search_query
 * @param int    $paged
 * @return string
 */
function get_post_pagination( $search_query, $paged ) {
    $post_args = array(
        'post_type'      => 'post',
        'posts_per_page' => 15,
        'paged'          => $paged,
        's'              => $search_query,
    );
    
    $post_query = new WP_Query( $post_args );
    
    $pagination = paginate_links( array(
        'base'      => add_query_arg( 'paged_posts', '%#%' ),
        'format'    => '?paged_posts=%#%',
        'current'   => $paged,
        'total'     => $post_query->max_num_pages,
        'prev_text' => '«',
        'next_text' => '»',
        'type'      => 'array',
        'mid_size'  => 1,
        'end_size'  => 1,
    ) );
    
    if ( $pagination ) {
        $output = '<nav class="navigation pagination" role="navigation">';
        $output .= '<ul class="pagination">';
        foreach ( $pagination as $page_link ) {
            $is_current = strpos( $page_link, 'current' ) !== false || strpos( $page_link, 'aria-current' ) !== false;
            $output .= '<li class="page-item' . ( $is_current ? ' active' : '' ) . '">' . $page_link . '</li>';
        }
        $output .= '</ul>';
        $output .= '</nav>';
        return $output;
    }
    return '';
}

/**
 * AJAX handler for custom search with rate limiting.
 *
 * @since 1.2
 * @return void
 */
function custom_search_ajax() {
    check_ajax_referer( 'custom_search_nonce', 'security' );
    
    $search_query   = isset( $_POST['search_query'] ) ? sanitize_text_field( wp_unslash( $_POST['search_query'] ) ) : '';
    $paged_products = isset( $_POST['paged_products'] ) ? absint( $_POST['paged_products'] ) : 1;
    $paged_posts    = isset( $_POST['paged_posts'] ) ? absint( $_POST['paged_posts'] ) : 1;
    
    // Only apply rate limiting for new searches (when both page numbers are 1)
    // This allows pagination to work without hitting the rate limit
    if ($paged_products === 1 && $paged_posts === 1 && !empty($search_query)) {
        // Replace old user_ip logic with get_client_ip()
        $user_ip = get_client_ip();
        $transient_key = 'custom_search_rate_' . md5( $user_ip );
        $rate_data     = get_transient( $transient_key );
        if ( ! $rate_data ) {
            $rate_data = array( 'count' => 0 );
        }
        if ( $rate_data['count'] >= 5 ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Search limit exceeded. Please wait a minute before trying again.', 'webhero' ) ) );
        }
        $rate_data['count']++;
        set_transient( $transient_key, $rate_data, 60 );
    }
    
    // Special handling for 'rolex' search
    if ( strtolower( $search_query ) === 'rolex' ) {
        // Get the page with 'rolex' slug
        $rolex_page = get_page_by_path( 'rolex' );
        $current_url = $_SERVER['REQUEST_URI'];
		$is_ms = strpos($current_url, 'ms/') !== false;
        if ( $rolex_page ) {
            ob_start();
            ?>
            <div class="articles-grid">
                <article id="post-<?php echo $rolex_page->ID; ?>" <?php post_class('', $rolex_page->ID); ?>>
                    <?php if ( has_post_thumbnail( $rolex_page->ID ) ) : ?>
                        <div class="post-thumbnail">
                            <a href="<?php echo esc_url( get_permalink( $rolex_page->ID ) ); ?>">
                                <?php echo get_the_post_thumbnail( $rolex_page->ID, 'medium', array( 'class' => 'featured-image' ) ); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    <footer class="entry-footer">
                        <a href="<?php echo esc_url( get_permalink( $rolex_page->ID ) ); ?>" class="read-more">
                            <?php if ($is_ms) {
                                echo esc_html_e( 'Maklumat lanjut', 'webhero' ); 
                            } else {
                                echo esc_html_e( 'Read More', 'webhero' ); 
                            }
                            ?>
                        </a>
                    </footer>
                </article>
            </div>
            <?php
            $rolex_html = ob_get_clean();
            
            wp_send_json_success( array(
                'special_rolex_result' => true,
                'rolex_html'           => $rolex_html,
                'has_results'          => true
            ) );
            return;
        }
    }
    
    $product_results = get_product_results( $search_query, $paged_products );
    $post_results    = get_post_results( $search_query, $paged_posts );
    
    $collection_results = get_collection_results( $search_query );
    
    wp_send_json_success( array(
        'collection_content' => $collection_results,
        'product_content'    => $product_results,
        'product_pagination' => get_product_pagination( $search_query, $paged_products, $product_results['query'] ),
        'post_content'       => $post_results,
        'post_pagination'    => get_post_pagination( $search_query, $paged_posts ),
        'has_results'        => ( $collection_results['has_results'] || $product_results['has_results'] || $post_results['has_results'] )
    ) );
}
add_action( 'wp_ajax_custom_search_ajax', 'custom_search_ajax' );
add_action( 'wp_ajax_nopriv_custom_search_ajax', 'custom_search_ajax' );

/**
 * Get collection (product category) search results.
 *
 * @since 1.3
 * @param string $search_query
 * @return array
 */
function get_collection_results( $search_query ) {
    global $wpdb;
    
    $search_query = trim( $search_query );
    if ( empty( $search_query ) ) {
        return array(
            'html'        => '',
            'has_results' => false,
        );
    }
    
    $search_variations = array(
        $search_query,
        str_replace( '-', ' ', $search_query ),
        str_replace( ' ', '-', $search_query ),
        str_replace( array( '-', ' ' ), '', $search_query )
    );
    $search_variations = array_unique( array_filter( $search_variations ) );
    
    $conditions = array();
    $params = array();
    
    foreach ( $search_variations as $term ) {
        $like = '%' . $wpdb->esc_like( $term ) . '%';
        $conditions[] = $wpdb->prepare( "(t.name LIKE %s OR t.slug LIKE %s)", $like, $like );
    }
    
    $where_clause = implode( ' OR ', $conditions );
    
    $sql = "
        SELECT  t.term_id, 
                t.name, 
                t.slug, 
                tt.taxonomy,
                tt.term_taxonomy_id,
                COUNT(*) AS product_count
        FROM    {$wpdb->terms} AS t
        INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
        WHERE   tt.taxonomy = 'product_cat' 
                AND ($where_clause)
        GROUP BY t.term_id
        LIMIT 6
    ";
    
    $categories = $wpdb->get_results( $sql );
    
    // Filter out any duplicates by name (case insensitive)
    $unique_names = array();
    $unique_categories = array();
    
    foreach ($categories as $category) {
        $lowercase_name = strtolower($category->name);
        if (!in_array($lowercase_name, $unique_names)) {
            $unique_names[] = $lowercase_name;
            $unique_categories[] = $category;
        }
    }
    
    $categories = $unique_categories;
    
    if ( empty( $categories ) ) {
        return array(
            'html'        => '',
            'has_results' => false,
        );
    }
    
    $current_url = $_SERVER['REQUEST_URI'];
    $is_ms = strpos($current_url, 'ms/') !== false;
    
    ob_start();
    echo '<div class="articles-grid">';
    
    foreach ( $categories as $category ) {
        $term_id = $category->term_id;
        
        // First try to get category link using get_term_link with the term object instead of just ID
        $category_obj = get_term($term_id, 'product_cat');
        if (!is_wp_error($category_obj)) {
            $category_link = get_term_link($category_obj);
        } else {
            // Fallback to trying by ID
            $category_link = get_term_link($term_id, 'product_cat');
        }
        
        // Final check if get_term_link returned an error
        if (is_wp_error($category_link)) {
            $category_link = home_url('/product-category/' . $category->slug); // Fallback URL using slug
        }
        
        $thumbnail_id = get_term_meta($term_id, 'thumbnail_id', true);
        if (!$thumbnail_id) {
            // Try to get a product image from this category as fallback
            $products = get_posts(array(
                'post_type' => 'product',
                'numberposts' => 1,
                'tax_query' => array(array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $term_id
                ))
            ));
            if (!empty($products) && has_post_thumbnail($products[0]->ID)) {
                $thumbnail_id = get_post_thumbnail_id($products[0]->ID);
            }
        }
        
        $image = $thumbnail_id ? wp_get_attachment_image($thumbnail_id, 'full', false, array('class' => 'featured-image')) : '';
        
        ?>
        <article id="category-<?php echo esc_attr($term_id); ?>" class="product-category">
            <div class="post-thumbnail">
                <a href="<?php echo esc_url($category_link); ?>">
                    <?php if ($image): ?>
                        <?php echo $image; ?>
                    <?php else: ?>
                        <div class="placeholder-image"></div>
                    <?php endif; ?>
                </a>
            </div>
            <header class="entry-header">
                <h2 class="entry-title">
                    <a href="<?php echo esc_url($category_link); ?>" rel="bookmark"><?php echo esc_html($category->name); ?></a>
                </h2>
            </header>
            <footer class="entry-footer">
                <a href="<?php echo esc_url($category_link); ?>" class="read-more">
                    <?php if ($is_ms) {
                        echo esc_html_e('Ketahui lagi', 'webhero');
                    } else {
                        echo esc_html_e('Discover more', 'webhero');
                    }
                    ?>
                </a>
            </footer>
        </article>
        <?php
    }
    
    echo '</div>';
    
    return array(
        'html'        => ob_get_clean(),
        'has_results' => true,
    );
}

/**
 * Enqueue custom search inline scripts.
 *
 * @since 1.2
 * Adds a helper for correct pagination with prev/next.
 */
function enqueue_custom_search_inline_scripts() {
    wp_enqueue_script( 'jquery' );
    $ajax_nonce = wp_create_nonce( 'custom_search_nonce' );
    
    // Helper function to handle prev/next text
    $custom_js = "
    jQuery(document).ready(function($) {
        function getPageNumber(link) {
            var text = link.text().trim();
            var current = parseInt(link.closest('.pagination').find('.active').text()) || 1;
            if (text === '«') {
                return current > 1 ? current - 1 : 1;
            } else if (text === '»') {
                return current + 1;
            }
            return parseInt(text) || 1;
        }
        
        var searchForm = $('.custom-search-form');
        var searchButton = searchForm.find('button');
        var searchResults = $('.custom-search-results');
        var productResults = $('.product-results');
        var postResults = $('.post-results');
        var searchTitle = $('.search-title');

        // Clear search field functionality
        $('.search-input-wrapper input[type=\"text\"]').on('input', function() {
            var \$this = $(this);
            if (\$this.val().length > 0) {
                \$this.siblings('.clear-search').show();
            } else {
                \$this.siblings('.clear-search').hide();
            }
        });
        $('.clear-search').on('click', function() {
            var \$input = $(this).siblings('input[type=\"text\"]');
            \$input.val('').trigger('input').focus();
        });

        function performSearch(query, pagedProducts, pagedPosts, updateProducts, updatePosts, isNewSearch) {
            searchResults.addClass('loading');
            searchButton.prop('disabled', true).addClass('loading');
            
            // Default to true if not specified (backwards compatibility)
            isNewSearch = (typeof isNewSearch !== 'undefined') ? isNewSearch : false;
            
            $.ajax({
                url: '" . admin_url( 'admin-ajax.php' ) . "',
                type: 'POST',
                data: {
                    action: 'custom_search_ajax',
                    search_query: query,
                    paged_products: pagedProducts,
                    paged_posts: pagedPosts,
                    security: '" . $ajax_nonce . "'
                },
                success: function(response) {
					const currentUrl = window.location.href;
                    if (!response.success) {
                        alert(response.data.message);
                        searchResults.removeClass('loading');
                        searchButton.prop('disabled', false).removeClass('loading');
                        return;
                    }
                    
                    // Only reset everything for a brand new search, not for pagination
                    if (isNewSearch) {
                        $('.collection-results').hide();
                        productResults.hide();
                        postResults.hide();
                    }
                    
                    // Handle special Rolex result
                    if (response.data.special_rolex_result) {
                        // Set post results with special Rolex content
                        postResults.html(response.data.rolex_html);
                        postResults.show();
                        
                        // Update search title - always refresh reference first
                        var searchTitle = $('.search-title');
                        if (searchTitle.length > 0) {
                            if (currentUrl.includes('ms/')) {
                                searchTitle.text('Hasil Carian untuk: ' + query);
                            } else {
                                searchTitle.text('Search Results for: ' + query);
                            }
                        }
                        
                        searchResults.removeClass('loading');
                        searchButton.prop('disabled', false).removeClass('loading');
                        
                        // Update URL
                        var newUrl = window.location.protocol + '//' + window.location.host + window.location.pathname;
                        if (query) newUrl += '?q=' + encodeURIComponent(query);
                        window.history.pushState({path: newUrl}, '', newUrl);
                        return;
                    }
                    
                    // Normal search results handling for non-Rolex searches
                    // Track if we should hide watches section
                    var hideWatchesSection = false;
                    
                    // First, handle collection results - only hide watches if collections actually have results
                    if (response.data.collection_content && response.data.collection_content.has_results === true) {
                        var collectionResults = $('.collection-results');
                        
                        // If collection results section doesn't exist, create it
                        if (collectionResults.length === 0) {
                            collectionResults = $('<div class=\"collection-results\"></div>');
                            
                            // Insert after search title
                            var searchTitle = $('.search-title');
                            if (searchTitle.length > 0) {
                                searchTitle.after(collectionResults);
                            } else {
                                // Fallback if search title not found
                                searchResults.prepend(collectionResults);
                            }
                            
                            // Add section title
                            var titleElement = $('<h2 class=\"section-title\"></h2>');
                            if (currentUrl.includes('ms/')) {
                                titleElement.text('Koleksi');
                            } else {
                                titleElement.text('Collections');
                            }
                            collectionResults.append(titleElement);
                            
                            // Add description
                            var descElement = $('<p class=\"search-description\"></p>');
                            if (currentUrl.includes('ms/')) {
                                descElement.text('Terokai koleksi kami berdasarkan carian anda');
                            } else {
                                descElement.text('Explore our collections based on your search');
                            }
                            collectionResults.append(descElement);
                            
                            // Add loading indicator
                            var loadingElement = $('<div class=\"loading-indicator\" style=\"display: none;\">Loading...</div>');
                            collectionResults.append(loadingElement);
                            
                            // Add collection container
                            var collectionContainer = $('<div class=\"collection-container\"></div>');
                            collectionResults.append(collectionContainer);
                        }
                        
                        collectionResults.find('.collection-container').html(response.data.collection_content.html);
                        collectionResults.show();
                        
                        // Hide watches section when collections are present
                        if (response.data.collection_content.html) {
                            hideWatchesSection = true;
                        }
                    }
                    
                    if (updateProducts && response.data.product_content) {
                        if (response.data.product_content.has_results) {
                            productResults.find('.products-container').html(response.data.product_content.html);
                            productResults.find('.product-pagination').html(response.data.product_pagination);
                            
                            // Only hide watches if we have collections with actual results
                            if (hideWatchesSection) {
                                productResults.hide();
                            } else {
                                productResults.show();
                            }
                        } else {
                            // No product results
                            productResults.hide();
                        }
                    }
                    
                    if (updatePosts) {
                        // First, ensure the post results section has the correct structure
                        if (postResults.find('.articles-container').length === 0) {
                            // Create a new div for post results section
                            postResults.empty();
                            
                            // Add section title
                            var titleElement = document.createElement('h2');
                            titleElement.className = 'section-title';
                            titleElement.textContent = 'Articles';
                            postResults.append(titleElement);
                            
                            // Add description
                            var descElement = document.createElement('p');
                            descElement.className = 'search-description';
                            descElement.textContent = 'Explore articles related to your search';
                            postResults.append(descElement);
                            
                            // Add loading indicator
                            var loadingElement = document.createElement('div');
                            loadingElement.className = 'loading-indicator';
                            loadingElement.style.display = 'none';
                            loadingElement.textContent = 'Loading...';
                            postResults.append(loadingElement);
                            
                            // Add articles container
                            var articlesContainer = document.createElement('div');
                            articlesContainer.className = 'articles-container';
                            postResults.append(articlesContainer);
                            
                            // Add pagination container
                            var paginationElement = document.createElement('div');
                            paginationElement.className = 'post-pagination';
                            postResults.append(paginationElement);
                        }
                        
                        if (response.data.post_content.has_results) {
                            postResults.find('.articles-container').html(response.data.post_content.html);
                            postResults.find('.post-pagination').html(response.data.post_pagination);
                            postResults.show();
                        }
                    }
                    // Always refresh the searchTitle reference before using it
                    var searchTitle = $('.search-title');
                    if (searchTitle.length > 0) {
                        if (query && query.trim() !== '') {
                            if (currentUrl.includes('ms/')) {
                                searchTitle.text('Hasil Carian untuk: ' + query);
                            } else {
                                searchTitle.text('Search Results for: ' + query);
                            }
                        } else {
                            if (currentUrl.includes('ms/')) {
                                searchTitle.text('Semua Jam Tangan dan Artikel');
                            } else {
                                searchTitle.text('All Watches and Articles');
                            }
                        }
                    }
                    searchResults.removeClass('loading');
                    searchButton.prop('disabled', false).removeClass('loading');
                    
                    var newUrl = window.location.protocol + '//' + window.location.host + window.location.pathname;
                    var params = [];
                    if (query) params.push('q=' + encodeURIComponent(query));
                    if (pagedProducts > 1) params.push('paged_products=' + pagedProducts);
                    if (pagedPosts > 1) params.push('paged_posts=' + pagedPosts);
                    if (params.length) newUrl += '?' + params.join('&');
                    window.history.pushState({path: newUrl}, '', newUrl);
                },
                error: function(xhr, status, error) {
                    searchResults.removeClass('loading');
                    searchButton.prop('disabled', false).removeClass('loading');
                    console.error('AJAX Error:', status, error);
                    alert('An error occurred. Please try again.');
                }
            });
        }

        searchForm.on('submit', function(e) {
            e.preventDefault();
            var query = $(this).find('input[name=\"q\"]').val();
            // This is a new search, so we'll pass true for isNewSearch
            performSearch(query, 1, 1, true, true, true);
        });

        $(document).on('click', '.product-pagination a, .post-pagination a', function(e) {
            e.preventDefault();
            var link = $(this);
            var query = searchForm.find('input[name=\"q\"]').val();
            var isProduct = link.closest('.product-pagination').length;
            
            var pagedProducts = isProduct ? getPageNumber(link) : (productResults.find('.product-pagination .active').text() || 1);
            var pagedPosts = !isProduct ? getPageNumber(link) : (postResults.find('.post-pagination .active').text() || 1);
            
            // This is pagination, not a new search, so pass false for isNewSearch
            performSearch(query, pagedProducts, pagedPosts, isProduct, !isProduct, false);
        });
    });
    ";
    
    wp_add_inline_script( 'jquery', $custom_js );
}
add_action( 'wp_enqueue_scripts', 'enqueue_custom_search_inline_scripts' );

/**
 * Add custom search styles.
 *
 * @since 1.2
 */
function add_custom_search_styles() {
    ?>
    <style>
        .custom-search-form {
            width: 100%;
            max-width: 750px;
            margin: 0 auto 20px auto;
            position: relative;
            display: flex;
            align-items: center;
            border: 1px solid #ddd;
            border-radius: 9999px;
            background-color: #fff;
            padding: 10px;
        }
        .search-input-wrapper {
            position: relative;
            flex-grow: 1;
            margin-right: 12px;
        }
        .search-input-wrapper input[type="text"] {
            width: 100%;
            border: none;
            outline: none;
            padding: 16px 20px;
            font-size: 16px;
            border-radius: 9999px;
            background-color: transparent;
        }
        .search-input-wrapper input[type="text"]::placeholder {
            color: #999;
        }
        .clear-search {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
            color: #999;
            cursor: pointer;
            display: none;
        }
        .custom-search-form button {
            position: relative;
            border: none;
            border-radius: 9999px;
            background-color: #127749;
            color: #fff;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            padding: 16px 24px;
            display: flex;
            align-items: center;
        }
        .custom-search-form button:hover {
            background-color: #0e623b;
        }
        .custom-search-form button .spinner {
            display: none;
            margin-left: 8px;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .custom-search-form button.loading .spinner {
            display: inline-block;
        }
        .no-results-message {
            text-align: left;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #f9f9f9;
            margin: 20px auto;
            max-width: 600px;
        }
        .no-results-message h2 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #333;
        }
        .no-results-message p {
            font-size: 16px;
            margin-bottom: 8px;
            line-height: 1.5;
            color: #666;
        }
        .no-results-message ul {
            list-style-type: disc;
            margin: 0 0 0 20px;
            padding: 0;
        }
        .no-results-message li {
            margin-bottom: 8px;
        }
        .no-results-message a {
            color: #127749;
            text-decoration: underline;
        }
        :root {
            --primary-color-1: #127749;
            --primary-color-2: #452c1e;
            --primary-color-3: #333333;
            --light-grey: #f8f8f8;
            --font-display: 'Libre Franklin', Helvetica, Arial, Lucida, sans-serif;
            --font-text: 'Montserrat', Helvetica, Arial, Lucida, sans-serif;
        }
        .custom-search-results {
            font-family: var(--font-text);
        }
        .custom-search-results .search-title {
            font-family: var(--font-display);
            font-size: 50px;
            color: var(--primary-color-3);
            line-height: 1.2;
            font-weight: 700;
            margin-bottom: 30px;
            text-align: center;
        }
        .custom-search-results {
            display: flex;
            flex-direction: column;
            gap: 40px;
        }
        .custom-search-results .section-title {
            font-family: var(--font-display);
            font-size: 36px;
            color: var(--primary-color-3);
            line-height: 1.2;
            font-weight: 700;
        }
        .custom-search-results .search-description {
            font-family: var(--font-display);
            font-size: 20px;
            color: var(--primary-color-3);
            margin-bottom: 20px;
            font-weight: 300;
        }
        .custom-search-results .rlx-body24 {
            font-size: 24px;
            line-height: 1.2;
        }
        .custom-search-results .rlx-legend16 {
            font-size: 16px;
            font-weight: 300;
            line-height: 1.1;
        }
        .custom-search-results .product-pagination,
        .custom-search-results .post-pagination {
            margin-top: 20px;
            text-align: center;
        }
        .custom-search-results .pagination {
            list-style-type: none;
            padding: 0;
            display: inline-flex;
            width: 100%;
            justify-content: center;
            padding-left: 0;
        }
        .custom-search-results .pagination .page-item {
            margin: 0 2px;
            list-style: none;
        }
        .custom-search-results .pagination .page-item a,
        .custom-search-results .pagination .page-item span {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 32px;
            height: 32px;
            padding: 4px;
            background: #f0f0f0;
            color: var(--primary-color-3);
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }
        .custom-search-results .pagination .page-item.active span {
            background: var(--primary-color-1);
            color: white;
        }
        .custom-search-results .pagination .page-item a:hover {
            background: var(--primary-color-1);
            color: white;
        }
        
        /* Placeholder image for categories without thumbnails */
        .custom-search-results .placeholder-image {
            background-color: #f0f0f0;
            width: 100%;
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .custom-search-results .pagination .prev,
        .custom-search-results .pagination .next {
            font-weight: bold;
        }
        .custom-search-results .product-results ul.products {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            list-style: none;
            padding: 0;
        }
        .custom-search-results .product-results ul.products li.product a {
            display: block;
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 20px 50px 50px 50px;
            background-color: var(--light-grey);
            transition: box-shadow 0.3s ease;
            text-decoration: none;
        }
        .custom-search-results .product-results ul.products li.product:hover {
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .custom-search-results .product-results ul.products li.product .et_shop_image {
            position: relative;
            display: block;
        }
        .custom-search-results .product-results ul.products li.product img {
            width: 100%;
            height: auto;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .custom-search-results .product-results ul.products li.product .woocommerce-loop-product__title .rlx-body24 {
            margin-bottom: 5px;
        }
        .custom-search-results .product-results ul.products li.product .woocommerce-loop-product__title span {
            display: block;
        }
        .custom-search-results .articles-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        .custom-search-results .post-results article,
        .custom-search-results .collection-results article {
            border: 1px solid #eee;
            padding: 15px;
            border-radius: 5px;
        }
        .custom-search-results .post-results .post-thumbnail,
        .custom-search-results .collection-results .post-thumbnail {
            margin-bottom: 15px;
        }
        .custom-search-results .post-results .post-thumbnail img {
            width: 100%;
            height: auto;
            border-radius: 5px;
        }
        .custom-search-results .post-results .entry-title,
        .custom-search-results .collection-results .entry-title {
            font-size: 18px;
            margin-bottom: 10px;
        }
        .custom-search-results .post-results .entry-title a,
        .custom-search-results .collection-results .entry-title a {
            color: var(--primary-color-3);
            text-decoration: none;
        }
        .custom-search-results .post-results .entry-title a:hover,
        .custom-search-results .collection-results .entry-title a:hover {
            color: var(--primary-color-1);
        }
        .custom-search-results .post-results .read-more,
        .custom-search-results .collection-results .read-more {
            display: inline-block;
            padding: 5px 10px;
            background-color: var(--primary-color-1);
            color: white;
            text-decoration: none;
            border-radius: 3px;
        }
        .custom-search-results .post-results .read-more:hover,
        .custom-search-results .collection-results .read-more:hover {
            background-color: var(--primary-color-1);
        }
        @media (max-width: 768px) {
            .custom-search-results .product-results ul.products {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 767px) {
            .custom-search-results .search-title {
                font-size: 30px;
            }
            .custom-search-results .section-title {
                font-size: 24px;
            }
            .custom-search-results .search-description {
                font-size: 18px;
            }
            .custom-search-results ul.products li.product .woocommerce-loop-product__title span.rlx-body24 {
                font-size: 18px;
            }
            .custom-search-results ul.products li.product .woocommerce-loop-product__title span.rlx-legend16 {
                font-size: 12px;
            }
            .custom-search-results .product-results ul.products li.product a {
                padding: 0;
            }
            .custom-search-results .product-results ul.products li.product .woocommerce-loop-product__title {
                padding: 0px 20px 30px 20px;
            }
        }
        @media (max-width: 480px) {
            .custom-search-results .articles-grid {
                grid-template-columns: 1fr;
            }
        }
        .custom-search-results.loading .loading-indicator {
            display: block;
        }
        .custom-search-results.loading .products-container,
        .custom-search-results.loading .articles-container {
            opacity: 0.5;
        }
        .custom-search-results .loading-indicator {
            text-align: center;
            padding: 20px;
            font-style: italic;
        }
    </style>
    <?php
}
add_action( 'wp_head', 'add_custom_search_styles' );

/**
 * Remove price from WooCommerce product loops.
 */
function remove_loop_price() {
    remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
}
add_action( 'init', 'remove_loop_price' );

/**
 * Remove "Add to Cart" button from WooCommerce product loops.
 */
function remove_loop_add_to_cart() {
    remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
}
add_action( 'init', 'remove_loop_add_to_cart' );

/**
 * Add rewrite rule for custom search.
 */
function custom_search_rewrite_rule() {
    add_rewrite_rule(
        '^search/?$',
        'index.php?pagename=search&q=$matches[1]',
        'top'
    );
    
    // Add a virtual page for search
    add_filter('page_template', 'get_custom_search_template');
    add_action('init', 'register_custom_search_page');
}
add_action( 'init', 'custom_search_rewrite_rule' );

/**
 * Register virtual page for search
 */
function register_custom_search_page() {
    $post_data = array(
        'post_title'    => 'Search',
        'post_name'     => 'search',
        'post_status'   => 'publish',
        'post_type'     => 'page',
        'post_content'  => '[custom_search][custom_search_results]',
    );
    
    // Check if the page already exists
    $existing_page = get_page_by_path('search');
    if (!$existing_page) {
        wp_insert_post($post_data);
    }
}

/**
 * Get custom search template
 */
function get_custom_search_template($template) {
    if (is_page('search')) {
        status_header(200);
        return $template;
    }
    return $template;
}

/**
 * Ensure search parameters are preserved
 */
function preserve_search_query_vars($query_vars) {
    $query_vars[] = 'q';
    $query_vars[] = 'paged_products';
    $query_vars[] = 'paged_posts';
    return $query_vars;
}
add_filter('query_vars', 'preserve_search_query_vars');

// Flush rewrite rules on theme activation.
add_action( 'after_switch_theme', 'flush_rewrite_rules' );