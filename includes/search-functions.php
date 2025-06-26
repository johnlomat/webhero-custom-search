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
        'debug_info'  => array(),
    );
    
    // Check if debug mode is enabled
    $is_debug = isset( $_GET['debug'] ) && 'true' === $_GET['debug'];
    
    // Get parent Rolex category
    $rolex_term = get_term_by( 'slug', 'rolex', 'product_cat' );
    if ( ! $rolex_term ) {
        return $results; // Return empty results if Rolex category not found
    }
    
    $grandchildren_categories = array();
    $scored_categories = array();
    
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
    
    // Collect all third-level categories for processing
    $all_third_level_cats = array();
    foreach ( $second_level_categories as $child ) {
        $third_level_cats = get_terms( array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'parent'     => $child->term_id,
            'exclude'    => array( get_option( 'default_product_cat', 0 ) ),
        ) );
        
        if ( ! empty( $third_level_cats ) ) {
            $all_third_level_cats = array_merge( $all_third_level_cats, $third_level_cats );
        }
    }
    
    // If search query is empty or specifically 'collections' or 'rolex', show all third-level categories
    if ( empty( $search_query ) || strtolower( $search_query ) === 'collections' || strtolower( $search_query ) === 'rolex' ) {
        // Sort all third-level categories alphabetically
        usort( $all_third_level_cats, function( $a, $b ) {
            return strcasecmp( $a->name, $b->name );
        } );
        
        $grandchildren_categories = $all_third_level_cats;
        
        if ( $is_debug ) {
            $results['debug_info'][] = 'Showing all third-level categories, sorted alphabetically';
        }
    } else {
        // For specific search query, apply relevance-based scoring
        $search_query = strtolower( trim( $search_query ) );
        $min_search_length = 3;
        
        // Skip if search query is too short (unless it looks like a model number)
        if ( strlen( $search_query ) < $min_search_length && !preg_match('/^[a-z0-9]{2,}$/i', $search_query) ) {
            if ( $is_debug ) {
                $results['debug_info'][] = 'Search query too short (minimum ' . $min_search_length . ' characters required)';
            }
            $grandchildren_categories = $all_third_level_cats;
        } else {
            // Scoring weights for different match types
            $exact_name_match_weight = 10;
            $name_starts_with_weight = 8;
            $name_contains_weight = 5;
            $description_contains_weight = 3;
            
            // Apply word boundary matching for short terms
            $is_short_term = strlen( $search_query ) <= 4;
            $word_boundary_pattern = '/\b' . preg_quote( $search_query, '/' ) . '\b/i';
            
            foreach ( $all_third_level_cats as $category ) {
                $score = 0;
                $match_reasons = array();
                $category_name = strtolower( $category->name );
                $category_desc = strtolower( strip_tags( $category->description ) );
                
                // Exact name match
                if ( $category_name === $search_query ) {
                    $score += $exact_name_match_weight;
                    $match_reasons[] = 'Exact name match (+' . $exact_name_match_weight . ')';
                }
                
                // Name starts with search term
                if ( strpos( $category_name, $search_query ) === 0 ) {
                    $score += $name_starts_with_weight;
                    $match_reasons[] = 'Name starts with term (+' . $name_starts_with_weight . ')';
                }
                
                // Name contains search term (with word boundary check for short terms)
                if ( $is_short_term ) {
                    if ( preg_match( $word_boundary_pattern, $category_name ) ) {
                        $score += $name_contains_weight;
                        $match_reasons[] = 'Name contains term (word boundary) (+' . $name_contains_weight . ')';
                    }
                } else if ( strpos( $category_name, $search_query ) !== false ) {
                    $score += $name_contains_weight;
                    $match_reasons[] = 'Name contains term (+' . $name_contains_weight . ')';
                }
                
                // Description contains search term
                if ( strpos( $category_desc, $search_query ) !== false ) {
                    $score += $description_contains_weight;
                    $match_reasons[] = 'Description contains term (+' . $description_contains_weight . ')';
                }
                
                // If there's a score, add to the scored array
                if ( $score > 0 || $is_debug ) {
                    // In debug mode, include even zero scores
                    $scored_categories[] = array(
                        'category' => $category,
                        'score' => $score,
                        'match_reasons' => $match_reasons,
                    );
                }
            }
            
            // Sort by score (descending)
            usort( $scored_categories, function( $a, $b ) {
                if ( $a['score'] === $b['score'] ) {
                    // If scores are equal, sort alphabetically
                    return strcasecmp( $a['category']->name, $b['category']->name );
                }
                return $b['score'] - $a['score'];
            } );
            
            // Filter out zero scores (unless in debug mode)
            $min_score = $is_debug ? 0 : 1;
            $scored_categories = array_filter( $scored_categories, function( $item ) use ( $min_score ) {
                return $item['score'] >= $min_score;
            } );
            
            // Extract the category objects from the scored array
            foreach ( $scored_categories as $scored_item ) {
                $grandchildren_categories[] = $scored_item['category'];
                
                // Add debug information
                if ( $is_debug ) {
                    $results['debug_info'][] = sprintf(
                        'Category: %s, Score: %d, Reasons: %s',
                        $scored_item['category']->name,
                        $scored_item['score'],
                        implode( ', ', $scored_item['match_reasons'] )
                    );
                }
            }
            
            // If no matches, fall back to all third-level categories
            if ( empty( $grandchildren_categories ) ) {
                $grandchildren_categories = $all_third_level_cats;
                
                if ( $is_debug ) {
                    $results['debug_info'][] = 'No matches found, showing all categories';
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
