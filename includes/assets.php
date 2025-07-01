<?php
/**
 * Asset handling for WebHero Custom Search
 *
 * @package WebHero
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueue custom search scripts and styles.
 *
 * @since 1.0.0
 * @return void
 */
function webhero_cs_enqueue_scripts() {
    // Enqueue CSS
    wp_enqueue_style(
        'webhero-custom-search',
        WEBHERO_CS_URL . 'assets/css/custom-search.css',
        array(),
        WEBHERO_CS_VERSION
    );
    
    // Enqueue JavaScript
    wp_enqueue_script(
        'webhero-custom-search',
        WEBHERO_CS_URL . 'assets/js/custom-search.js',
        array( 'jquery' ),
        WEBHERO_CS_VERSION,
        true
    );
    
    // Localize script with AJAX URL and nonce
    wp_localize_script(
        'webhero-custom-search',
        'webhero_cs_params',
        array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'webhero_cs_nonce' ),
            'home_url' => home_url( '/search/' ),
            'i18n'     => array(
                'no_results'      => __( 'No results found.', 'webhero' ),
                'loading'         => __( 'Loading...', 'webhero' ),
                'search_too_long' => __( 'Your search query is too long. Please limit it to 30 characters or fewer.', 'webhero' ),
            ),
        )
    );
}
add_action( 'wp_enqueue_scripts', 'webhero_cs_enqueue_scripts' );
