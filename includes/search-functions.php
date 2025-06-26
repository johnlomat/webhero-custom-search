<?php
/**
 * Search functions for WebHero Custom Search
 *
 * @package WebHero
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Filter search results to only show Rolex products and content
 *
 * @since 1.1.0
 * @param array $args WP_Query arguments
 * @return array Modified query arguments
 */
function webhero_cs_filter_to_rolex( $args ) {
    // If tax_query is already set
    if ( isset( $args['tax_query'] ) && is_array( $args['tax_query'] ) ) {
        // Add relation if not already set
        if ( ! isset( $args['tax_query']['relation'] ) ) {
            $args['tax_query']['relation'] = 'AND';
        }
        
        // Add Rolex filter
        $args['tax_query'][] = array(
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => 'rolex',
        );
    } else {
        // Create new tax_query
        $args['tax_query'] = array(
            'relation' => 'AND',
            array(
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => 'rolex',
            ),
        );
    }
    
    return $args;
}

/**
 * Get post search results with improved relevance.
 *
 * @since 1.0.0
 * @param string $search_query
 * @param int    $paged
 * @return array
 */
function webhero_cs_get_post_results( $search_query, $paged ) {
    // Set up pagination
    $posts_per_page = 6;
    
    // Create the query
    $query_args = array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => $posts_per_page,
        'paged'          => $paged,
        's'              => $search_query,
        'tax_query'      => array(
            array(
                'taxonomy' => 'category',
                'field'    => 'slug',
                'terms'    => 'rolex',
            ),
        ),
    );
    
    // Apply search relevance improvements from memory
    $post_query = new WP_Query( $query_args );
    
    // Prepare the results
    $results = array(
        'has_results' => false,
        'html'        => '',
        'found_posts' => 0,
        'query'       => $post_query,
    );
    
    if ( $post_query->have_posts() ) {
        $results['has_results'] = true;
        $results['found_posts'] = $post_query->found_posts;
        
        ob_start();
        ?>
        <div class="articles-grid">
        <?php
        while ( $post_query->have_posts() ) {
            $post_query->the_post();
                
            $image = wp_get_attachment_image_src( get_post_thumbnail_id(), 'medium' );
            $image_url = $image ? $image[0] : '';
            ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <?php if ( $image_url ) : ?>
                    <div class="post-thumbnail">
                        <a href="<?php echo esc_url( get_permalink() ); ?>">
                            <img decoding="async" width="300" height="200" src="<?php echo esc_url( $image_url ); ?>" class="featured-image wp-post-image" alt="<?php echo esc_attr( get_the_title() ); ?>">
                        </a>
                    </div>
                <?php endif; ?>
                <header class="entry-header">
                    <h2 class="entry-title">
                        <a href="<?php echo esc_url( get_permalink() ); ?>" rel="bookmark"><?php the_title(); ?></a>
                    </h2>
                </header>
                <footer class="entry-footer">
                    <a href="<?php echo esc_url( get_permalink() ); ?>" class="read-more">
                        <?php echo esc_html__( 'Read More', 'webhero' ); ?>
                    </a>
                </footer>
            </article>
            <?php
        }
        ?>
        </div>
        <?php
        $results['html'] = ob_get_clean();
        wp_reset_postdata();
    }
    
    return $results;
}

/**
 * Get post pagination.
 *
 * @since 1.0.0
 * @param string $search_query
 * @param int    $paged
 * @return string
 */
function webhero_cs_get_post_pagination( $search_query, $paged ) {
    $posts_per_page = 6;
    
    $query_args = array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => $posts_per_page,
        'paged'          => 1,
        's'              => $search_query,
        'tax_query'      => array(
            array(
                'taxonomy' => 'category',
                'field'    => 'slug',
                'terms'    => 'rolex',
            ),
        ),
    );
    
    // Apply search relevance improvements
    $post_query = new WP_Query( $query_args );
    
    $total_pages = $post_query->max_num_pages;
    
    if ( $total_pages <= 1 ) {
        return '';
    }
    
    $pagination = '<ul class="pagination">';
    
    // Previous page
    if ( $paged > 1 ) {
        $pagination .= '<li class="page-item"><a href="#" aria-label="Previous">&laquo;</a></li>';
    } else {
        $pagination .= '<li class="page-item disabled"><span>&laquo;</span></li>';
    }
    
    // Page numbers
    $start_page = max( 1, $paged - 2 );
    $end_page = min( $total_pages, $paged + 2 );
    
    for ( $i = $start_page; $i <= $end_page; $i++ ) {
        if ( $i == $paged ) {
            $pagination .= '<li class="page-item active"><span>' . $i . '</span></li>';
        } else {
            $pagination .= '<li class="page-item"><a href="#">' . $i . '</a></li>';
        }
    }
    
    // Next page
    if ( $paged < $total_pages ) {
        $pagination .= '<li class="page-item"><a href="#" aria-label="Next">&raquo;</a></li>';
    } else {
        $pagination .= '<li class="page-item disabled"><span>&raquo;</span></li>';
    }
    
    $pagination .= '</ul>';
    
    return $pagination;
}

/**
 * Generate HTML for category results.
 *
 * @since 1.0.0
 * @param array $categories Array of category term objects
 * @return string HTML output
 */
function webhero_cs_generate_categories_html( $categories ) {
    if ( empty( $categories ) ) {
        return '';
    }
    
    $current_url = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
    $is_ms = false !== strpos( $current_url, 'ms/' );
    
    ob_start();
    ?>
    <div class="articles-grid">
        <?php foreach ( $categories as $category ) : 
            // Get thumbnail
            $thumbnail_id = get_term_meta( $category->term_id, 'thumbnail_id', true );
            $image = wp_get_attachment_image_src( $thumbnail_id, 'medium' );
            $image_url = $image ? $image[0] : wc_placeholder_img_src();
            
            // Get product count
            $product_count = $category->count;
            if ( $product_count == 0 ) {
                $child_cats = get_terms( array(
                    'taxonomy' => 'product_cat',
                    'hide_empty' => true,
                    'parent' => $category->term_id
                ));
                
                foreach ( $child_cats as $child_cat ) {
                    $product_count += $child_cat->count;
                }
            }
            
            // Don't show categories with zero products
            if ( $product_count <= 0 ) {
                continue;
            }
            // Get category link
            $category_link = get_term_link( $category );
            ?>
            <article id="category-<?php echo esc_attr($category->term_id); ?>" class="product-category">
                <div class="post-thumbnail">
                    <a href="<?php echo esc_url($category_link); ?>">
                        <?php if ( $image_url ) : ?>
                            <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($category->name); ?>" />
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
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Get collection (product category) search results.
 *
 * @since 1.0.0
 * @param string $search_query
 * @return array
 */
function webhero_cs_get_collection_results( $search_query ) {
    // Prepare the results
    $results = array(
        'has_results' => false,
        'html'        => '',
    );
    
    // Get parent Rolex category
    $rolex_term = get_term_by( 'slug', 'rolex', 'product_cat' );
    if ( ! $rolex_term ) {
        return $results; // Return empty results if Rolex category not found
    }
    
    $grandchildren_categories = array();
    
    // Get all second-level children first (to find their children)
    $second_level_categories = get_terms( array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => true,
        'parent'     => $rolex_term->term_id,
        'exclude'    => array( get_option( 'default_product_cat', 0 ) ),
    ) );
    
    // If we don't have any second-level categories, return empty results
    if ( empty( $second_level_categories ) ) {
        return $results;
    }
    
    // If search query is empty or specifically 'collections' or 'rolex', show all third-level categories
    if ( empty( $search_query ) || strtolower( $search_query ) === 'collections' || strtolower( $search_query ) === 'rolex' ) {
        // Get all grandchildren (third-level) categories
        foreach ( $second_level_categories as $child ) {
            $third_level_cats = get_terms( array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => true,
                'parent'     => $child->term_id,
                'exclude'    => array( get_option( 'default_product_cat', 0 ) ),
                'orderby'    => 'name',
                'order'      => 'ASC',
            ) );
            
            if ( ! empty( $third_level_cats ) ) {
                $grandchildren_categories = array_merge( $grandchildren_categories, $third_level_cats );
            }
        }
    } else {
        // For specific search query, filter third-level categories by the search term
        foreach ( $second_level_categories as $child ) {
            $third_level_cats = get_terms( array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => true,
                'parent'     => $child->term_id,
                'exclude'    => array( get_option( 'default_product_cat', 0 ) ),
                'orderby'    => 'name',
                'order'      => 'ASC',
            ) );
            
            foreach ( $third_level_cats as $grandchild ) {
                if ( stripos( $grandchild->name, $search_query ) !== false || 
                     stripos( $grandchild->description, $search_query ) !== false ) {
                    $grandchildren_categories[] = $grandchild;
                }
            }
        }
        
        // If no direct third-level matches, try searching in second-level categories
        // and include all their children
        if ( empty( $grandchildren_categories ) ) {
            foreach ( $second_level_categories as $child ) {
                if ( stripos( $child->name, $search_query ) !== false || 
                     stripos( $child->description, $search_query ) !== false ) {
                    $matching_third_level = get_terms( array(
                        'taxonomy'   => 'product_cat',
                        'hide_empty' => true,
                        'parent'     => $child->term_id,
                        'exclude'    => array( get_option( 'default_product_cat', 0 ) ),
                    ) );
                    
                    if ( ! empty( $matching_third_level ) ) {
                        $grandchildren_categories = array_merge( $grandchildren_categories, $matching_third_level );
                    }
                }
            }
        }
        
        // If still no results, fall back to showing all third-level categories
        if ( empty( $grandchildren_categories ) ) {
            foreach ( $second_level_categories as $child ) {
                $third_level_cats = get_terms( array(
                    'taxonomy'   => 'product_cat',
                    'hide_empty' => true,
                    'parent'     => $child->term_id,
                    'exclude'    => array( get_option( 'default_product_cat', 0 ) ),
                    'orderby'    => 'name',
                    'order'      => 'ASC',
                ) );
                
                if ( ! empty( $third_level_cats ) ) {
                    $grandchildren_categories = array_merge( $grandchildren_categories, $third_level_cats );
                }
            }
        }
    }
    
    // Generate the HTML for the results
    if ( ! empty( $grandchildren_categories ) ) {
        $results['has_results'] = true;
        $results['html'] = webhero_cs_generate_categories_html( $grandchildren_categories );
    }
    
    return $results;
}
