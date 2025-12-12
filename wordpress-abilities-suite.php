<?php
/**
 * Plugin Name: WordPress Abilities Suite
 * Description: Comprehensive WordPress management abilities for MCP - Content, Taxonomies, Plugins, Media, Users, and Comments
 * Version: 1.0.5
 * Author: Influencentricity
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * Network: true
 */

defined( 'ABSPATH' ) || exit;

// Define plugin constants
define( 'WP_ABILITIES_SUITE_VERSION', '1.0.5' );
define( 'WP_ABILITIES_SUITE_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_ABILITIES_SUITE_URL', plugin_dir_url( __FILE__ ) );

// Load ability categories FIRST (required before abilities)
require_once WP_ABILITIES_SUITE_PATH . 'includes/ability-categories.php';

// Load ability modules
require_once WP_ABILITIES_SUITE_PATH . 'includes/content-abilities.php';
require_once WP_ABILITIES_SUITE_PATH . 'includes/taxonomy-abilities.php';
require_once WP_ABILITIES_SUITE_PATH . 'includes/plugin-abilities.php';
require_once WP_ABILITIES_SUITE_PATH . 'includes/media-abilities.php';
require_once WP_ABILITIES_SUITE_PATH . 'includes/user-abilities.php';
require_once WP_ABILITIES_SUITE_PATH . 'includes/comment-abilities.php';

// Load admin dashboard
if ( is_admin() ) {
    require_once WP_ABILITIES_SUITE_PATH . 'admin/dashboard.php';
}

// Activation hook
register_activation_hook( __FILE__, function() {
    // Set default options
    if ( is_multisite() ) {
        update_site_option( 'wp_abilities_suite_version', WP_ABILITIES_SUITE_VERSION );
    } else {
        update_option( 'wp_abilities_suite_version', WP_ABILITIES_SUITE_VERSION );
    }

    // Flush cache
    wp_cache_flush();

    error_log( 'WordPress Abilities Suite: Activated v' . WP_ABILITIES_SUITE_VERSION );
});

// Deactivation hook
register_deactivation_hook( __FILE__, function() {
    wp_cache_flush();
    error_log( 'WordPress Abilities Suite: Deactivated' );
});
