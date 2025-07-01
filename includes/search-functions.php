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
 * Filter for product search by title
 *
 * @param string $where Where clause
 * @return string Modified where clause
 */
function webhero_cs_filter_products_by_title($where) {
    global $wpdb;
    if (isset($GLOBALS['wp_query']->query_vars['_title_query'])) {
        $where .= ' ' . $GLOBALS['wp_query']->query_vars['_title_query'];
        // Debug output
        if (isset($_GET['debug']) && $_GET['debug'] == 'true') {
            error_log('Modified SQL WHERE clause: ' . $where);
        }
    }
    return $where;
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
 * @param bool   $calculate_only
 * @return array
 */
function webhero_cs_get_post_results( $search_query, $calculate_only = false ) {
    // Set posts per page
    $posts_per_page = 15;
    
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
    
    // Initialize variables to prevent undefined variable errors
    $scored_posts = array();
    
    // Trim and convert the search query to lowercase
    $search_query = strtolower( trim( $search_query ) );
    $post_query = null;
    $scored_posts = array();
    $scored_categories = array(); // Initialize to prevent NULL errors with usort
    
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
                            <?php esc_html_e( 'Read More', 'webhero' ); ?>
                        </a>
                    </footer>
                </article>
            </div>
            <?php
            $results['html'] = ob_get_clean();
            $results['has_results'] = true;
            return $results;
        }
    }
    
    // Begin processing
    if ( empty( $search_query ) ) {
        // For empty queries, we want to show all posts
        $results['has_results'] = true;
        $results['debug_info'][] = 'Empty search query detected, showing all posts';
        
        // Get all Rolex posts for empty search query
        $query_args = array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $posts_per_page,
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
        
        // For empty queries, consider all posts as having positive score
        foreach ( $post_query->posts as $post ) {
            $scored_posts[] = array(
                'ID'    => $post->ID,
                'score' => 10, // Give a positive score to all posts for empty query
                'match_reasons' => array('default_result' => true),
            );
        }
    } else {
        // Apply relevance-based search for non-empty queries
        $min_search_length = 3;
        
        // Skip relevance search if search query too short (unless it's a model number/ID)
        if ( strlen( $search_query ) < $min_search_length && !preg_match('/^[a-z0-9]{2,}$/i', $search_query) ) {
            $query_args = array(
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => $posts_per_page,
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
                $word_boundary_pattern = '/\b' . preg_quote( $search_query, '/' ) . '\b/i';
                
                // NEW ALGORITHM: Find products matching the search term and show their 3rd level categories
                $product_categories = array();
                
                // Query for products that match the search term in title
                $args = array(
                    'post_type'      => 'product',
                    'post_status'    => 'publish',
                    'posts_per_page' => 20,
                    'fields'         => 'ids', // Only get post IDs for efficiency
                    'meta_query'     => array(
                        'relation' => 'OR',
                        array(
                            'key'     => '_sku',
                            'value'   => $search_query,
                            'compare' => 'LIKE'
                        )
                    ),
                );
                
                // Add title search
                global $wpdb;
                $search_term = $wpdb->esc_like($search_query);
                $like = '%' . $search_term . '%';
                
                // More specific matching for model numbers with flexible spacing
                if (strlen($search_query) <= 6) { // Increased limit to catch slightly longer model numbers
                    // Step 1: Add flexible spacing between letters and numbers
                    $flexible_term = preg_replace('/([a-zA-Z])(\d)/', '$1[ -]*$2', $search_term);
                    
                    // Step 2: Add flexible spacing between digits (for multi-digit numbers like m12)
                    $flexible_term = preg_replace('/(\d)(\d)/', '$1[ -]*$2', $flexible_term);
                    $post_title_sql = $wpdb->prepare("AND ({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_title REGEXP %s)", 
                                                      $like, $flexible_term);
                } else {
                    $post_title_sql = $wpdb->prepare("AND {$wpdb->posts}.post_title LIKE %s", $like);
                }
                
                $args['_title_query'] = $post_title_sql;
                if (isset($_GET['debug']) && $_GET['debug'] == 'true') {
                    $results['debug_info'][] = 'Product search SQL clause: ' . $post_title_sql;
                }
                
                add_filter('posts_where', 'webhero_cs_filter_products_by_title');
                
                $products_query = new WP_Query($args);
                remove_filter('posts_where', 'webhero_cs_filter_products_by_title');
                
                if ($is_debug) {
                    $results['debug_info'][] = 'Searching for products with title matching: ' . $search_query;
                    $results['debug_info'][] = 'Found ' . $products_query->post_count . ' matching products';
                }
                
                // ARTICLE/POST SEARCH: Score each post based on relevance to search term
                $all_posts = get_posts( $all_posts_args );
                
                if ($is_debug) {
                    $results['debug_info'][] = 'Total Rolex posts: ' . count($all_posts);
                }
                
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
                    $search_query_lower = strtolower( $search_query );
                    
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
                    
                    // If score meets minimum threshold, include in results
                    if ( $score >= $min_score ) {
                        $scored_posts[] = array(
                            'ID' => $post_id,
                            'score' => $score,
                            'match_reasons' => $match_reasons,
                        );
                        
                        // Add debug info if enabled
                        if ( $is_debug ) {
                            $results['debug_info'][] = sprintf(
                                'POST: %s, Score: %d, Reasons: %s',
                                get_the_title( $post_id ),
                                $score,
                                implode( ', ', $match_reasons )
                            );
                        }
                    }
                    // In debug mode, show excluded posts too
                    else if ( $is_debug && !empty($match_reasons) ) {
                        $results['debug_info'][] = sprintf(
                            'EXCLUDED POST: %s - %s',
                            get_the_title( $post_id ),
                            implode( ', ', $match_reasons )
                        );
                    }
                }
                
                // Sort posts by score (descending)
                if (isset($scored_posts) && is_array($scored_posts) && !empty($scored_posts)) {
                    usort($scored_posts, function($a, $b) {
                        if ($a['score'] === $b['score']) {
                            // If scores are equal, sort by ID (which often correlates with publish date)
                            return $b['ID'] - $a['ID']; // Newer posts first
                        }
                        return $b['score'] - $a['score'];
                    });
                } else {
                    $scored_posts = array();
                }
                
                // Debug output for post counts
                if ( $is_debug ) {
                    $results['debug_info'][] = 'Found ' . count($scored_posts) . ' posts with positive scores';
                    if (!empty($scored_posts)) {
                        $results['debug_info'][] = 'Top post: ' . get_the_title($scored_posts[0]['ID']) . 
                        ' with score: ' . $scored_posts[0]['score'];
                    }
                }
                
                // Prepare post query with the sorted post IDs for HTML generation
                if (!empty($scored_posts)) {
                    $post_ids = wp_list_pluck($scored_posts, 'ID');
                    $post_query = new WP_Query(array(
                        'post__in' => $post_ids,
                        'post_type' => 'post',
                        'posts_per_page' => $posts_per_page,
                        'orderby' => 'post__in', // Preserve our custom sorted order
                    ));
                    $results['has_results'] = true;
                } else {
                    $post_query = new WP_Query(array('post__in' => array(0))); // Empty result
                }
                
                // Now also look for categories (product search)
                if ($products_query->have_posts()) {
                    $product_match_weight = 7; // Weight for categories found via product title match
                    
                    // Track categories we've already processed to avoid duplicates
                    $processed_category_ids = array();
                    
                    foreach ($products_query->posts as $product_id) {
                        // Get the product title for debugging and checking exact matches
                        $product_title = strtolower(get_the_title($product_id));
                        $is_exact_product_match = ($product_title === $search_query);
                        $exact_product_bonus = $is_exact_product_match ? 3 : 0;
                        
                        // Get all categories for this product
                        $terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'all'));
                        
                        foreach ($terms as $term) {
                            // Skip if we've already processed this category
                            if (isset($processed_category_ids[$term->term_id])) {
                                continue;
                            }
                            
                            // We need to check if this is a 3rd level category under Rolex
                            $ancestors = get_ancestors($term->term_id, 'product_cat', 'taxonomy');
                            
                            // A 3rd level category will have exactly 2 ancestors (parent and grandparent)
                            if (count($ancestors) == 2) {
                                // Make sure the topmost ancestor is the Rolex category
                                $top_ancestor = end($ancestors);
                                if ($top_ancestor == $rolex_term->term_id) {
                                    // Add to processed categories
                                    $processed_category_ids[$term->term_id] = true;
                                    
                                    // Add this category to product match results with appropriate score
                                    $found_in_third_level = false;
                                    foreach ($all_third_level_cats as $existing_cat) {
                                        if ($existing_cat->term_id == $term->term_id) {
                                            $found_in_third_level = true;
                                            break;
                                        }
                                    }
                                    
                                    if ($found_in_third_level) {
                                        $scored_categories[] = array(
                                            'category' => $term,
                                            'score' => $product_match_weight + $exact_product_bonus,
                                            'match_reasons' => array(
                                                'Product title match' . ($is_exact_product_match ? ' (exact)' : '') . 
                                                ' (+' . ($product_match_weight + $exact_product_bonus) . '): ' . get_the_title($product_id)
                                            )
                                        );
                                        
                                        if ($is_debug) {
                                            $results['debug_info'][] = 'Found category via product: ' . $term->name . 
                                                ' through product: ' . get_the_title($product_id) . 
                                                ' with score: ' . ($product_match_weight + $exact_product_bonus);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                
                // Process each category using the standard relevance-based scoring
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
                    } else {
                        if ( strpos( $category_name, $search_query ) !== false ) {
                            $score += $name_contains_weight;
                            $match_reasons[] = 'Name contains term (+' . $name_contains_weight . ')';
                        }
                    }
                    
                    // Description contains search term
                    if ( !empty($category_desc) && strpos( $category_desc, $search_query ) !== false ) {
                        $score += $description_contains_weight;
                        $match_reasons[] = 'Description contains term (+' . $description_contains_weight . ')';
                    }
                    
                    // If score meets minimum threshold, include in results
                    if ( $score >= $min_score ) {
                        $scored_categories[] = array(
                            'category' => $category,
                            'score' => $score,
                            'match_reasons' => $match_reasons,
                        );
                    }
                    // In debug mode, show excluded categories
                    else if ( $is_debug && !empty($match_reasons) ) {
                        $results['debug_info'][] = sprintf(
                            'EXCLUDED Category: %s - %s',
                            $category->name,
                            implode( ', ', $match_reasons )
                        );
                    }
                }
                
                // Sort categories by score (descending)
                if (isset($scored_categories) && is_array($scored_categories) && !empty($scored_categories)) {
                    usort($scored_categories, function($a, $b) {
                        if ($a['score'] === $b['score']) {
                            // If scores are equal, sort alphabetically by name
                            return strcasecmp($a['category']->name, $b['category']->name);
                        }
                        return $b['score'] - $a['score'];
                    });
                } else {
                    $scored_categories = array(); // Initialize if it doesn't exist
                }
                
                // Add debug info for categories
                if ( $is_debug && isset($scored_categories) && is_array($scored_categories) ) {
                    foreach ( $scored_categories as $scored_category ) {
                        $results['debug_info'][] = sprintf(
                            'Category: %s, Score: %d, Reasons: %s',
                            $scored_category['category']->name,
                            $scored_category['score'],
                            implode( ', ', $scored_category['match_reasons'] )
                        );
                    }
                }
                
                // Filter out categories with low scores if scored categories exist
                $positive_score_categories = array();
                if (isset($scored_categories) && is_array($scored_categories)) {
                    $positive_score_categories = array_filter($scored_categories, function($cat_data) use ($search_query) {
                        // Include categories with positive score OR if it's an initial page load with empty search
                        return $cat_data['score'] > 0 || $search_query === '';
                    });
                }
                
                // Limit to a reasonable number of categories
                $max_categories = 20;
                $limited_categories = !empty($positive_score_categories) ? array_slice($positive_score_categories, 0, $max_categories) : array();
                
                // Extract just the category objects for HTML generation
                $categories_for_display = !empty($limited_categories) ? array_map(function($item) {
                    return $item['category'];
                }, $limited_categories) : array();
                
                // Mark whether we found any results
                $has_positive_scores = isset($positive_score_categories) && !empty($positive_score_categories);
                $results['has_results'] = $has_positive_scores || empty(trim($search_query));
                
                // Add debug info about counts
                if ($is_debug) {
                    $results['debug_info'][] = "Found " . count($positive_score_categories) . " categories with positive scores";
                    if (!empty($positive_score_categories)) {
                        $results['debug_info'][] = "Top category: " . $positive_score_categories[0]['category']->name . 
                            " with score: " . $positive_score_categories[0]['score'];
                    }
                }
            }
        }
    }
    // No need to track category counts in the post results function
    // This was mistakenly added from the collection results function
    
    // If calculate_only, return now without generating HTML
    if ($calculate_only) {
        return $results;
    }
    
    // Generate HTML for post results
    if (!empty($scored_posts)) {
        $results['has_results'] = true;
        ob_start();
        ?>
        <div class="articles-grid">
        <?php
        // Make sure post_query exists and has posts
        if (isset($post_query) && $post_query->have_posts()) :
            while ($post_query->have_posts()) :
                $post_query->the_post();
                $post_id = get_the_ID();

                // Get score for this post
                $score = 0;
                foreach ($scored_posts as $scored_post) {
                    if ($scored_post['ID'] == $post_id) {
                        $score = $scored_post['score'];
                        break;
                    }
                }

                // Only show posts with positive scores or for empty search queries
                if ($score > 0 || $search_query === '') :
                ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                    <?php if (has_post_thumbnail()) : ?>
                        <div class="post-thumbnail">
                            <a href="<?php the_permalink(); ?>">
                                <?php the_post_thumbnail('medium'); ?>
                            </a>
                        </div>
                    <?php else : ?>
                        <div class="post-thumbnail">
                            <a href="<?php the_permalink(); ?>">
                                <img src="<?php echo esc_url(WEBHERO_CS_URL . 'assets/images/placeholder.jpg'); ?>" alt="<?php the_title_attribute(); ?>">
                            </a>
                        </div>
                    <?php endif; ?>
                    <header class="entry-header">
                        <h2 class="entry-title">
                            <a href="<?php echo esc_url(get_permalink()); ?>" rel="bookmark"><?php the_title(); ?></a>
                        </h2>
                    </header>
                    <footer class="entry-footer">
                        <a href="<?php echo esc_url(get_permalink()); ?>" class="read-more">
                            <?php echo esc_html__('Read More', 'webhero'); ?>
                        </a>
                    </footer>
                </article>
                <?php endif; ?>
            <?php endwhile; ?>
        <?php 
        else : ?>
            <div class="no-results"><?php esc_html_e('No articles found for your search.', 'webhero'); ?></div>
        <?php endif; ?>
        </div>
        <?php
        wp_reset_postdata();
        $results['html'] = ob_get_clean();
    } else {
        ob_start();
        ?>
        <div class="no-article-results">
            <?php esc_html_e('No articles found matching your search.', 'webhero'); ?>
        </div>
        <?php
        $results['html'] = ob_get_clean();
    }
    
    // Just in case any database queries were run
    wp_reset_postdata();
    
    return $results;
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
    
    // Removed hardcoded Malaysian language detection
    
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
                        <?php echo esc_html_e('Discover more', 'webhero'); ?>
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
    // Prepare results
    $results = array(
        'has_results' => false,
        'html'        => '',
        'debug_info'  => array(),
    );
    
    // Initialize variables we'll use later
    $positive_score_categories = array();
    $all_third_level_cats = array();
    $categories_for_display = array();
    
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
            $product_title_match_weight = 7;
            
            // Is it a short search term? If so, use word boundary matching
            $is_short_term = strlen($search_query) <= 3;
            $word_boundary_pattern = '/\b' . preg_quote($search_query, '/') . '\b/i';
            
            // First, find all products that match the search query in title
            // This is crucial for model number searches like "m126"
            $matching_product_ids = array();
            $matching_product_categories = array();
            
            // Query for products with titles matching the search query
            global $wpdb;
            $search_term = $wpdb->esc_like($search_query);
            $like = '%' . $search_term . '%';
            
            // For short model numbers, use more flexible matching
            if (strlen($search_query) <= 6) {
                // Create flexible pattern for model numbers like m126
                $flexible_term = preg_replace('/([a-zA-Z])(\d)/', '$1[ -]*$2', $search_term);
                $flexible_term = preg_replace('/(\d)(\d)/', '$1[ -]*$2', $flexible_term);
                
                // Get products matching either LIKE or REGEXP pattern
                $products = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT ID FROM {$wpdb->posts} 
                         WHERE post_type = 'product' 
                         AND post_status = 'publish' 
                         AND (post_title LIKE %s OR post_title REGEXP %s)",
                        $like,
                        $flexible_term
                    )
                );
            } else {
                // For longer terms, simple LIKE search is sufficient
                $products = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT ID FROM {$wpdb->posts} 
                         WHERE post_type = 'product' 
                         AND post_status = 'publish' 
                         AND post_title LIKE %s",
                        $like
                    )
                );
            }
            
            // Get all product IDs that match
            if (!empty($products)) {
                foreach ($products as $product) {
                    $matching_product_ids[] = $product->ID;
                }
                
                if ($is_debug) {
                    $results['debug_info'][] = 'Found ' . count($matching_product_ids) . ' products with titles matching "' . $search_query . '"';
                }
                
                // Now get the categories these products belong to
                foreach ($matching_product_ids as $product_id) {
                    $product_cats = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'all'));
                    if (!empty($product_cats)) {
                        foreach ($product_cats as $cat) {
                            // Get all ancestors to find the 3rd level category
                            $ancestors = get_ancestors($cat->term_id, 'product_cat', 'taxonomy');
                            
                            // If this is already a 3rd level cat under Rolex
                            if (in_array($cat->term_id, array_map(function($term) { return $term->term_id; }, $all_third_level_cats))) {
                                if (!isset($matching_product_categories[$cat->term_id])) {
                                    $matching_product_categories[$cat->term_id] = array(
                                        'category' => $cat,
                                        'product_count' => 1,
                                        'products' => array($product_id)
                                    );
                                } else {
                                    $matching_product_categories[$cat->term_id]['product_count']++;
                                    $matching_product_categories[$cat->term_id]['products'][] = $product_id;
                                }
                            } 
                            // Check if any ancestor is a 3rd level cat under Rolex
                            else if (!empty($ancestors)) {
                                foreach ($all_third_level_cats as $third_level) {
                                    if (in_array($third_level->term_id, $ancestors)) {
                                        if (!isset($matching_product_categories[$third_level->term_id])) {
                                            $matching_product_categories[$third_level->term_id] = array(
                                                'category' => $third_level,
                                                'product_count' => 1,
                                                'products' => array($product_id)
                                            );
                                        } else {
                                            $matching_product_categories[$third_level->term_id]['product_count']++;
                                            $matching_product_categories[$third_level->term_id]['products'][] = $product_id;
                                        }
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
                
                if ($is_debug && !empty($matching_product_categories)) {
                    $results['debug_info'][] = 'Found products in ' . count($matching_product_categories) . ' different collections';
                }
            }
            
            // Initialize scored categories array
            $scored_categories = array();
            
            // Score each category based on name and description matches
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
                
                // Check if this category has matching products (with search term in product title)
                if (!empty($matching_product_categories) && isset($matching_product_categories[$category->term_id])) {
                    $match_data = $matching_product_categories[$category->term_id];
                    $product_count = $match_data['product_count'];
                    
                    // Add points based on how many products match (up to a max)
                    $products_score = min($product_count * 2, $product_title_match_weight);
                    $score += $products_score;
                    
                    // Add product info to match reasons
                    $first_product = get_the_title($match_data['products'][0]);
                    if ($product_count == 1) {
                        $match_reasons[] = "Product title match: {$first_product} (+{$products_score})";
                    } else {
                        $match_reasons[] = "{$product_count} product title matches inc. {$first_product} (+{$products_score})";
                    }
                }
                
                // Description contains search term
                if ( strpos( $category_desc, $search_query ) !== false ) {
                    $score += $description_contains_weight;
                    $match_reasons[] = 'Description contains term (+' . $description_contains_weight . ')';
                }
                
                // If score meets minimum threshold or debug mode is enabled, include in results
                if ( $score > 0 || $is_debug ) {
                    $scored_categories[] = array(
                        'category' => $category,
                        'score' => $score,
                        'match_reasons' => $match_reasons,
                    );
                    
                    // Only add debug info for positive scores
                    if ( $is_debug && $score > 0 ) {
                        $results['debug_info'][] = sprintf(
                            'Category: %s, Score: %d, Reasons: %s',
                            $category->name,
                            $score,
                            implode( ', ', $match_reasons )
                        );
                    }
                }
            }
            
            // Sort by score (descending)
            usort( $scored_categories, function( $a, $b ) {
                if ( $a['score'] === $b['score'] ) {
                    // If scores are equal, sort by ID (which often correlates with publish date)
                    return $b['category']->term_id - $a['category']->term_id; // Newer posts first
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
