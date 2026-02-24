<?php
/**
 * Plugin Name: WordPress Abilities Suite
 * Description: Complete native WordPress AI control through the Abilities API — content, blocks, meta, settings, cron, themes, patterns, site health, REST discovery, menus, and more.
 * Version: 3.1.0
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
define( 'WP_ABILITIES_SUITE_VERSION', '3.1.0' );
define( 'WP_ABILITIES_SUITE_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_ABILITIES_SUITE_URL', plugin_dir_url( __FILE__ ) );

// Load shared helpers.
require_once WP_ABILITIES_SUITE_PATH . 'includes/helpers.php';

// Load permission toggles system.
require_once WP_ABILITIES_SUITE_PATH . 'includes/permissions.php';

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

    // Set default permissions if not already set.
    if ( false === get_option( 'wp_abilities_suite_permissions' ) ) {
        update_option( 'wp_abilities_suite_permissions', wp_abilities_suite_permission_defaults() );
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
