<?php
/**
 * AJAX handlers for WebHero Custom Search
 *
 * @package WebHero
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AJAX handler for custom search with rate limiting.
 *
 * @since 1.0.0
 * @return void
 */
function webhero_cs_ajax_handler() {
    // Verify nonce
    if ( ! check_ajax_referer( 'webhero_cs_nonce', 'security', false ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed.', 'webhero' ) ) );
        return;
    }
    
    // Get search query and pagination parameters
    $search_query = isset( $_POST['search_query'] ) ? sanitize_text_field( wp_unslash( $_POST['search_query'] ) ) : '';
    $debug_mode = (isset($_POST['debug']) && $_POST['debug'] === 'true') || (isset($_POST['debug_mode']) && $_POST['debug_mode']);
    $debug_info = array();
    
    // Rate limiting
    $rate_limit_key = 'webhero_cs_rate_limit_' . webhero_cs_get_client_ip();
    $rate_limit = get_transient( $rate_limit_key );
    
    if ( $rate_limit && $rate_limit >= 10 ) {
        wp_send_json_error( array( 'message' => __( 'Rate limit exceeded. Please try again later.', 'webhero' ) ) );
        return;
    }
    
    // Increment rate limit
    if ( $rate_limit ) {
        set_transient( $rate_limit_key, $rate_limit + 1, 60 ); // 1 minute expiration
    } else {
        set_transient( $rate_limit_key, 1, 60 );
    }
    
    // Initialize debug info if needed
    $debug_info = array();
    // Check for debug parameter coming from JavaScript
    $debug_mode = (isset($_POST['debug']) && $_POST['debug'] === 'true') || (isset($_POST['debug_mode']) && $_POST['debug_mode']);
    
    // Set debug flag in URL if debug mode is active
    if ($debug_mode) {
        $_GET['debug'] = 'true';
    }
    
    // Special case for 'collections' keyword
    if ( $search_query === 'collections' ) {
        $collection_results = webhero_cs_get_collection_results( '' );
        wp_send_json_success( array(
            'collection_content' => $collection_results,
            'debug' => $debug_mode ? array( 'query' => 'collections', 'special_case' => true ) : null,
        ) );
        return;
    }
    
    if ( $debug_mode ) {
        $debug_info['search_query'] = $search_query;
    }
    
    // Special case for 'rolex' keyword
    if ( strtolower( $search_query ) === 'rolex' ) {
        // Get post results using the special handling in webhero_cs_get_post_results
        // This now handles the special 'rolex' page case
        $post_results = webhero_cs_get_post_results( $search_query );
        
        // For 'rolex' search we ONLY want to show the page post type, not collections
        wp_send_json_success( array(
            'post_content' => array(
                'html' => $post_results['html'],
                'has_results' => $post_results['has_results']
            ),
            'collection_content' => array('html' => '', 'has_results' => false),
            'debug' => $debug_mode ? array_merge($debug_info, $post_results['debug_info'] ?? []) : null,
        ) );
        return;
    }
    
    // Normal search flow
    $collection_results = webhero_cs_get_collection_results( $search_query );
    $post_results = webhero_cs_get_post_results( $search_query );
    
    // If in debug mode, add debug information
    if ($debug_mode) {
        $debug_info['search_query'] = $search_query;
        $debug_info['debug_enabled'] = true;
        
        // Make sure debug info is passed from functions
        if (!isset($collection_results['debug_info'])) {
            $collection_results['debug_info'] = array();
        }
        
        if (!isset($post_results['debug_info'])) {
            $post_results['debug_info'] = array();
        }
        
        // Add extra info
        $debug_info['timestamp'] = date('Y-m-d H:i:s');
    }
    
    // Return results
    wp_send_json_success( array(
        'collection_content' => $collection_results,
        'post_content' => $post_results,
        'debug' => $debug_mode ? $debug_info : null,
    ) );
}
add_action( 'wp_ajax_webhero_cs_ajax_handler', 'webhero_cs_ajax_handler' );
add_action( 'wp_ajax_nopriv_webhero_cs_ajax_handler', 'webhero_cs_ajax_handler' );
