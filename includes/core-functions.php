<?php
/**
 * Core functions for WebHero Custom Search
 *
 * @package WebHero
 * @since 1.0.0
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
function webhero_cs_get_client_ip() {
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
 * Ensure search parameters are preserved
 */
function webhero_cs_preserve_query_vars( $query_vars ) {
    $query_vars[] = 'q';
    $query_vars[] = 'paged_products';
    $query_vars[] = 'paged_posts';
    $query_vars[] = 'debug';
    return $query_vars;
}
add_filter( 'query_vars', 'webhero_cs_preserve_query_vars' );

/**
 * Remove price from WooCommerce product loops.
 */
function webhero_cs_remove_loop_price() {
    remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
}
add_action( 'init', 'webhero_cs_remove_loop_price' );

/**
 * Remove "Add to Cart" button from WooCommerce product loops.
 */
function webhero_cs_remove_loop_add_to_cart() {
    remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
}
add_action( 'init', 'webhero_cs_remove_loop_add_to_cart' );
