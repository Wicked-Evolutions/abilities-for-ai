<?php
/**
 * Plugin Name: Abilities Suite for WordPress
 * Plugin URI:  https://github.com/Influencentricity/abilities-suite-for-wordpress
 * Description: 111 native WordPress abilities across 18 modules — content, blocks, meta, settings, cron, themes, patterns, site health, REST discovery, menus, filesystem, and more. Powers AI control through the official Abilities API.
 * Version: 3.7.0
 * Author: Influencentricity
 * Author URI: https://influencentricity.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires PHP: 7.4
 * Requires at least: 6.9
 * Network: true
 */

defined( 'ABSPATH' ) || exit;

// Define plugin constants
define( 'WP_ABILITIES_SUITE_VERSION', '3.7.0' );
define( 'WP_ABILITIES_SUITE_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_ABILITIES_SUITE_URL', plugin_dir_url( __FILE__ ) );

// Load shared helpers.
require_once WP_ABILITIES_SUITE_PATH . 'includes/helpers.php';

// Load permission toggles system.
require_once WP_ABILITIES_SUITE_PATH . 'includes/permissions.php';

// Load Pro tier infrastructure.
require_once WP_ABILITIES_SUITE_PATH . 'includes/license-manager.php';
require_once WP_ABILITIES_SUITE_PATH . 'includes/tier-gate.php';

// Load ability categories FIRST (required before abilities).
require_once WP_ABILITIES_SUITE_PATH . 'includes/ability-categories.php';

// Original v2.0 ability modules.
require_once WP_ABILITIES_SUITE_PATH . 'includes/content-abilities.php';
require_once WP_ABILITIES_SUITE_PATH . 'includes/taxonomy-abilities.php';
require_once WP_ABILITIES_SUITE_PATH . 'includes/plugin-abilities.php';
require_once WP_ABILITIES_SUITE_PATH . 'includes/media-abilities.php';
require_once WP_ABILITIES_SUITE_PATH . 'includes/user-abilities.php';
require_once WP_ABILITIES_SUITE_PATH . 'includes/comment-abilities.php';
require_once WP_ABILITIES_SUITE_PATH . 'includes/menu-abilities.php';

// New v3.0 ability modules.
require_once WP_ABILITIES_SUITE_PATH . 'includes/blocks-abilities.php';
require_once WP_ABILITIES_SUITE_PATH . 'includes/patterns-abilities.php';
require_once WP_ABILITIES_SUITE_PATH . 'includes/meta-abilities.php';
require_once WP_ABILITIES_SUITE_PATH . 'includes/settings-abilities.php';
require_once WP_ABILITIES_SUITE_PATH . 'includes/site-health-abilities.php';
require_once WP_ABILITIES_SUITE_PATH . 'includes/transients-abilities.php';
require_once WP_ABILITIES_SUITE_PATH . 'includes/cron-abilities.php';
require_once WP_ABILITIES_SUITE_PATH . 'includes/themes-abilities.php';
require_once WP_ABILITIES_SUITE_PATH . 'includes/rest-discovery-abilities.php';
require_once WP_ABILITIES_SUITE_PATH . 'includes/rewrite-abilities.php';
require_once WP_ABILITIES_SUITE_PATH . 'includes/filesystem-abilities.php';

// Load admin dashboard
if ( is_admin() ) {
    require_once WP_ABILITIES_SUITE_PATH . 'admin/dashboard.php';
}

// Activation hook
register_activation_hook( __FILE__, function( $network_wide = false ) {
    // Set default options
    if ( is_multisite() && $network_wide ) {
        update_site_option( 'wp_abilities_suite_version', WP_ABILITIES_SUITE_VERSION );

        // Iterate all sites to set per-site permissions.
        $site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
        foreach ( $site_ids as $site_id ) {
            switch_to_blog( $site_id );
            if ( false === get_option( 'wp_abilities_suite_permissions' ) ) {
                update_option( 'wp_abilities_suite_permissions', wp_abilities_suite_permission_defaults() );
            }
            restore_current_blog();
        }
    } else {
        update_option( 'wp_abilities_suite_version', WP_ABILITIES_SUITE_VERSION );

        // Set default permissions if not already set.
        if ( false === get_option( 'wp_abilities_suite_permissions' ) ) {
            update_option( 'wp_abilities_suite_permissions', wp_abilities_suite_permission_defaults() );
        }
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
