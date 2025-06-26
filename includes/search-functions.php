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
    
    // Check if debug mode is enabled
    $is_debug = isset( $_GET['debug'] ) && 'true' === $_GET['debug'];
    
    // Prepare the results
    $results = array(
        'has_results' => false,
        'html'        => '',
        'found_posts' => 0,
        'query'       => null,
        'debug_info'  => array(),
    );
    
    // Trim and convert the search query to lowercase
    $search_query = strtolower( trim( $search_query ) );
    $post_query = null;
    $scored_posts = array();
    
    // Begin processing
    if ( empty( $search_query ) ) {
        // For empty queries, we want to show all posts
        $results['has_results'] = true;
        
        // Get all Rolex posts for empty search query
        $query_args = array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $posts_per_page,
            'paged'          => $paged,
            'tax_query'      => array(
                array(
                    'taxonomy' => 'category',
                    'field'    => 'slug',
                    'terms'    => 'rolex',
                ),
            ),
        );
        
        if ( $is_debug ) {
            $results['debug_info'][] = 'Empty search query: showing all Rolex posts';
        }
        
        $post_query = new WP_Query( $query_args );
    } else {
        // Apply relevance-based search for non-empty queries
        $min_search_length = 3;
        
        // Skip relevance search if search query too short (unless it's a model number/ID)
        if ( strlen( $search_query ) < $min_search_length && !preg_match('/^[a-z0-9]{2,}$/i', $search_query) ) {
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
            
            if ( $is_debug ) {
                $results['debug_info'][] = 'Search query too short (minimum ' . $min_search_length . ' characters required)';
            }
            
            $post_query = new WP_Query( $query_args );
        } else {
            // Get all posts in Rolex category
            $all_posts_args = array(
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => -1, // Get all posts for manual relevance sorting
                'fields'         => 'ids', // Only get post IDs for efficiency
                'tax_query'      => array(
                    array(
                        'taxonomy' => 'category',
                        'field'    => 'slug',
                        'terms'    => 'rolex',
                    ),
                ),
                'no_found_rows'  => true, // Skip pagination count for better performance
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            );
            
            $all_posts = get_posts( $all_posts_args );
            
            if ( empty( $all_posts ) ) {
                $post_query = new WP_Query( array('post__in' => array(0)) ); // Empty result
                if ( $is_debug ) {
                    $results['debug_info'][] = 'No posts found in Rolex category';
                }
            } else {
                // Relevance scoring weights
                $exact_title_match_weight = 10;
                $title_starts_with_weight = 8;
                $title_contains_weight = 5;
                $content_contains_weight = 3;
                
                // Lower threshold for debug mode
                $min_score = $is_debug ? 0 : 1;
                
                // Apply word boundary matching for short terms
                $is_short_term = strlen( $search_query ) <= 4;
                $word_boundary_pattern = '/\b' . preg_quote( strtolower( $search_query ), '/' ) . '\b/i';
                $search_query_lower = strtolower( $search_query );
                
                foreach ( $all_posts as $post_id ) {
                    $score = 0;
                    $match_reasons = array();
                    
                    // Get post title and content
                    $post_title = strtolower( get_the_title( $post_id ) );
                    $original_content = get_post_field( 'post_content', $post_id );

                    // Extract alt texts for debugging
                    $alt_texts = array();
                    $img_tag_content = '';
                    if ( $is_debug ) {
                        preg_match_all('/alt=["\']([^"\']*)["\']/', $original_content, $alt_matches);
                        if (!empty($alt_matches[1])) {
                            $alt_texts = $alt_matches[1];
                        }
                        
                        // Save full img tag content for later reference
                        preg_match_all('/<img[^>]+>/i', $original_content, $img_matches);
                        if (!empty($img_matches[0])) {
                            $img_tag_content = implode("\n", $img_matches[0]);
                        }
                    }
                    
                    // Extract ONLY content from allowed tags (p, headings, figcaption)
                    $visible_content = '';
                    
                    // Extract paragraph content
                    preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $original_content, $p_matches);
                    if (!empty($p_matches[1])) {
                        $visible_content .= implode("\n", $p_matches[1]) . "\n";
                    }
                    
                    // Extract heading content (h1-h6)
                    preg_match_all('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $original_content, $h_matches);
                    if (!empty($h_matches[1])) {
                        $visible_content .= implode("\n", $h_matches[1]) . "\n";
                    }
                    
                    // Extract figcaption content
                    preg_match_all('/<figcaption[^>]*>(.*?)<\/figcaption>/is', $original_content, $figcaption_matches);
                    if (!empty($figcaption_matches[1])) {
                        $visible_content .= implode("\n", $figcaption_matches[1]);
                    }
                    
                    // Clean content - strip shortcodes first
                    $visible_content = strip_shortcodes( $visible_content );
                    // Remove HTML tags that might be nested
                    $visible_content = strip_tags( $visible_content );
                    // Remove HTML comments
                    $visible_content = preg_replace( '/<!--(.|\s)*?-->/', '', $visible_content );
                    $post_content = strtolower( $visible_content );
                    
                    // Exact title match
                    if ( $post_title === $search_query_lower ) {
                        $score += $exact_title_match_weight;
                        $match_reasons[] = 'Exact title match (+' . $exact_title_match_weight . ')';
                    }
                    
                    // Title starts with search term
                    if ( strpos( $post_title, $search_query_lower ) === 0 ) {
                        $score += $title_starts_with_weight;
                        $match_reasons[] = 'Title starts with term (+' . $title_starts_with_weight . ')';
                    }
                    
                    // Title contains search term (with word boundary check for short terms)
                    if ( $is_short_term ) {
                        if ( preg_match( $word_boundary_pattern, $post_title ) ) {
                            $score += $title_contains_weight;
                            $match_reasons[] = 'Title contains term (word boundary) (+' . $title_contains_weight . ')';
                        }
                    } else if ( strpos( $post_title, $search_query_lower ) !== false ) {
                        $score += $title_contains_weight;
                        $match_reasons[] = 'Title contains term (+' . $title_contains_weight . ')';
                    }
                    
                    // Content contains search term
                    if ( strpos( $post_content, $search_query_lower ) !== false ) {
                        $score += $content_contains_weight;
                        $match_reasons[] = 'Content contains term (+' . $content_contains_weight . ')';
                    }

                    // Check if post has alt text containing the search term but not counted for scoring
                    if ( $is_debug && !empty($alt_texts) ) {
                        foreach ( $alt_texts as $alt_text ) {
                            if ( strpos( strtolower($alt_text), $search_query_lower ) !== false ) {
                                $match_reasons[] = 'WARNING: Alt text contains search term but NOT counted for scoring';
                                break;
                            }
                        }
                    }
                    
                    // If score meets minimum threshold or debug mode, include in results
                    // If score meets minimum threshold, include in results
                    if ( $score >= $min_score ) {
                        $scored_posts[] = array(
                            'ID' => $post_id,
                            'score' => $score,
                            'match_reasons' => $match_reasons,
                        );
                    }
                    // In debug mode, show excluded posts with alt text matches but no content/title matches
                    else if ( $is_debug && !empty($match_reasons) ) {
                        $results['debug_info'][] = sprintf(
                            'EXCLUDED Post ID: %d, Title: %s - %s',
                            $post_id,
                            get_the_title( $post_id ),
                            implode( ', ', $match_reasons )
                        );
                    }
                }
                
                // Sort by score (descending)
                usort( $scored_posts, function( $a, $b ) {
                    if ( $a['score'] === $b['score'] ) {
                        // If scores are equal, sort by ID (which often correlates with publish date)
                        return $b['ID'] - $a['ID']; // Newer posts first
                    }
                    return $b['score'] - $a['score'];
                } );
                
                // Add debug info
                if ( $is_debug ) {
                    foreach ( $scored_posts as $scored_post ) {
                        $results['debug_info'][] = sprintf(
                            'Post ID: %d, Title: %s, Score: %d, Reasons: %s',
                            $scored_post['ID'],
                            get_the_title( $scored_post['ID'] ),
                            $scored_post['score'],
                            implode( ', ', $scored_post['match_reasons'] )
                        );
                    }
                }
                
                // Extract post IDs for pagination
                $post_ids = array_map( function($item) { return $item['ID']; }, $scored_posts );
                
                // Apply pagination
                $offset = ( $paged - 1 ) * $posts_per_page;
                $paged_post_ids = array_slice( $post_ids, $offset, $posts_per_page );
                
                // If no results, set an empty array
                if ( empty( $paged_post_ids ) ) {
                    $paged_post_ids = array( 0 ); // Will return no results
                }
                
                // Create the final query with our manually sorted posts
                $query_args = array(
                    'post_type'      => 'post',
                    'post_status'    => 'publish',
                    'posts_per_page' => $posts_per_page,
                    'post__in'       => $paged_post_ids,
                    'orderby'        => 'post__in', // Preserve our custom sort order
                );
                
                $post_query = new WP_Query( $query_args );
                
                // Set total found posts for pagination
                $post_query->found_posts = count( $post_ids );
                $post_query->max_num_pages = ceil( count( $post_ids ) / $posts_per_page );
            }
        }
    }
    
    // Save query to results
    $results['query'] = $post_query;
    
    // Generate HTML output
    // Check if we have any posts with positive scores
    $has_positive_scores = false;
    foreach ( $scored_posts as $post_data ) {
        if ( $post_data['score'] > 0 ) {
            $has_positive_scores = true;
            break;
        }
    }
    
    // Set has_results flag based on positive scores or empty query
    if ( empty( $search_query ) ) {
        // Always show posts for empty queries (initial page load)
        $results['has_results'] = true;
    } else if ( $post_query->found_posts > 0 && $has_positive_scores ) {
        // Show posts with positive scores for actual searches
        $results['has_results'] = true;
    } else {
        // Hide when no positive scores for actual searches
        $results['has_results'] = false;
        
        if ( $is_debug ) {
            $results['debug_info'][] = 'No posts found with positive scores - section will be hidden';
        }
    }
        
    if ( $results['has_results'] ) {
        $found_posts = $post_query->found_posts;
        
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
    
    // Always show collections for empty search query (initial page load)
    if ( empty( trim( $search_query ) ) ) {
        $results['has_results'] = true;
    }
    
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
        
        // For empty queries, we treat each category as having a positive score
        foreach ( $all_third_level_cats as $cat ) {
            $scored_categories[] = [
                'category' => $cat,
                'score' => 1, // Assign a positive score for initial load
                'match_reasons' => ['Initial page load']
            ];
        }
        
        // Set has_results to true for initial page load
        $results['has_results'] = true;
        
        if ( $is_debug ) {
            $results['debug_info'][] = 'Showing all third-level categories on initial load, sorted alphabetically';
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
            // For short search terms, initialize empty array so no categories are shown
            // This prevents ALL categories from showing up for short search terms
            $grandchildren_categories = [];
        } else {
            // Scoring weights for different match types
            $exact_name_match_weight = 10;
            $name_starts_with_weight = 8;
            $name_contains_weight = 5;
            $description_contains_weight = 3;
            
            // Apply word boundary matching for short terms
            $is_short_term = strlen( $search_query ) <= 4;
            $word_boundary_pattern = '/\b' . preg_quote( $search_query, '/' ) . '\b/i';
            
            // Check if we're looking for specific product categories
            // For exact matching, we'll rely on the existing score-based matching
            
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
            
            // ALWAYS filter out zero scores for display (regardless of debug mode)
            // but keep them in debug info
            $scored_categories_for_display = array_filter( $scored_categories, function( $item ) {
                return $item['score'] > 0;
            } );
            
            // Add debug information for all items (including zero scores)
            if ( $is_debug ) {
                foreach ( $scored_categories as $scored_item ) {
                    $results['debug_info'][] = sprintf(
                        'Category: %s, Score: %d, Reasons: %s',
                        $scored_item['category']->name,
                        $scored_item['score'],
                        implode( ', ', $scored_item['match_reasons'] )
                    );
                }
            }
            
            // Extract the category objects from the filtered array (only positive scores)
            $grandchildren_categories = [];
            foreach ( $scored_categories_for_display as $scored_item ) {
                // Only include items with positive scores
                if ($scored_item['score'] > 0) {
                    $grandchildren_categories[] = $scored_item['category'];
                }
            }
            
            // Check if we have any categories with positive scores
            $has_positive_scores = false;
            foreach ( $scored_categories as $cat_data ) {
                if ( $cat_data['score'] > 0 ) {
                    $has_positive_scores = true;
                    break;
                }
            }
            
            // If it's an empty query, always show all collections
            if ( empty( $search_query ) ) {
                $results['has_results'] = true;
            }
            // For actual searches, only show if we have positive scores
            else if ( !$has_positive_scores ) {
                $results['has_results'] = false;
                
                if ( $is_debug ) {
                    $results['debug_info'][] = 'No matches found with positive scores - section will be hidden';
                }
                // Don't return any categories when there are no matches with positive scores
                $grandchildren_categories = [];
            }
        }
    }
    
    // ONLY use categories with POSITIVE scores for HTML generation
    $positive_score_categories = [];
    
    // Remember we only want to show ALL categories on empty query (initial load)
    if (empty($search_query)) {
        foreach ($scored_categories as $scored_item) {
            $positive_score_categories[] = $scored_item['category'];
        }
    } else {
        // For actual searches, strict filtering - ONLY positive scores
        foreach ($scored_categories as $scored_item) {
            if ($scored_item['score'] > 0) {
                $positive_score_categories[] = $scored_item['category'];
            }
        }
    }
    
    // Only proceed if we have categories with positive scores
    if ( ! empty( $positive_score_categories ) ) {
        $results['has_results'] = true;
        $results['html'] = webhero_cs_generate_categories_html( $positive_score_categories );
    } else {
        $results['has_results'] = false;
        $results['html'] = '';
    }
    
    return $results;
}
