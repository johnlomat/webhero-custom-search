<?php
/**
 * Template functions for WebHero Custom Search
 *
 * @package WebHero
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add body class for search page
 * 
 * @param array $classes
 * @return array
 */
function webhero_cs_add_body_class( $classes ) {
    if ( is_page( 'search' ) ) {
        $classes[] = 'webhero-search-page';
    }
    return $classes;
}
add_filter( 'body_class', 'webhero_cs_add_body_class' );
