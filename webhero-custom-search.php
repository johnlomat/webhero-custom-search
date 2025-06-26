<?php
/**
 * Plugin Name: WebHero Custom Search
 * Plugin URI: https://webhero.com
 * Description: Enhanced search functionality with AJAX and improved relevance-based results display
 * Version: 1.1.0
 * Author: WebHero
 * Author URI: https://webhero.com
 * Text Domain: webhero
 * Domain Path: /languages
 *
 * @package WebHero
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'WEBHERO_CS_VERSION', '1.1.0' );
define( 'WEBHERO_CS_PATH', plugin_dir_path( __FILE__ ) );
define( 'WEBHERO_CS_URL', plugin_dir_url( __FILE__ ) );

// Include required files
require_once WEBHERO_CS_PATH . 'includes/core-functions.php';
require_once WEBHERO_CS_PATH . 'includes/search-functions.php';
require_once WEBHERO_CS_PATH . 'includes/ajax-handlers.php';
require_once WEBHERO_CS_PATH . 'includes/shortcodes.php';
require_once WEBHERO_CS_PATH . 'includes/template-functions.php';
require_once WEBHERO_CS_PATH . 'includes/assets.php';

/**
 * Initialize the plugin
 */
function webhero_cs_init() {
    // Load text domain for translations
    load_plugin_textdomain( 'webhero', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'webhero_cs_init' );

/**
 * Register activation hook
 */
function webhero_cs_activate() {
    // Flush rewrite rules on activation
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'webhero_cs_activate' );

/**
 * Register deactivation hook
 */
function webhero_cs_deactivate() {
    // Flush rewrite rules on deactivation
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'webhero_cs_deactivate' );
