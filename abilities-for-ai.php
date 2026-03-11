<?php
/**
 * Plugin Name: Abilities for AI
 * Plugin URI:  https://github.com/Wicked-Evolutions/abilities-for-ai
 * Description: 138 abilities across 18 modules — content, blocks, meta, settings, cron, themes, patterns, site health, REST discovery, menus, filesystem, and more. Powers AI control through the WordPress Abilities API.
 * Version: 1.0.0
 * Author: Wicked Evolutions
 * Author URI: https://wickedevolutions.com
 * Copyright: Copyright (C) 2026 Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires PHP: 8.0
 * Requires at least: 6.9
 * Network: true
 */

defined( 'ABSPATH' ) || exit;

// Define plugin constants
define( 'ABILITIES_FOR_AI_VERSION', '1.0.0' );
define( 'ABILITIES_FOR_AI_PATH', plugin_dir_path( __FILE__ ) );
define( 'ABILITIES_FOR_AI_URL', plugin_dir_url( __FILE__ ) );

// PSR-4 autoloader (composer-generated, with graceful fallback).
$autoloader = ABILITIES_FOR_AI_PATH . 'vendor/autoload.php';
if ( file_exists( $autoloader ) ) {
	require_once $autoloader;
} else {
	// Fallback: manual class map for environments without composer install.
	// CI release ZIPs always include vendor/; this fallback is for dev clones only.
	spl_autoload_register( function( $class ) {
		$prefix = 'WickedEvolutions\\AbilitiesForAI\\';
		if ( strpos( $class, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$file     = ABILITIES_FOR_AI_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	} );
}

// Load shared helpers and schema builders.
require_once ABILITIES_FOR_AI_PATH . 'includes/helpers.php';
require_once ABILITIES_FOR_AI_PATH . 'includes/schemas.php';

// Load permission toggles system.
require_once ABILITIES_FOR_AI_PATH . 'includes/permissions.php';

// Load Pro tier infrastructure.
require_once ABILITIES_FOR_AI_PATH . 'includes/license-manager.php';
require_once ABILITIES_FOR_AI_PATH . 'includes/tier-gate.php';

// compat.php provides `Abilities_For_AI_Registrar` alias for the namespaced class —
// must load after autoloader + helpers so the class and its dependencies exist.
require_once ABILITIES_FOR_AI_PATH . 'includes/compat.php';

// Load ability categories FIRST (required before abilities).
require_once ABILITIES_FOR_AI_PATH . 'includes/ability-categories.php';

// Ability modules.
require_once ABILITIES_FOR_AI_PATH . 'includes/status-abilities.php';
require_once ABILITIES_FOR_AI_PATH . 'includes/content-abilities.php';
require_once ABILITIES_FOR_AI_PATH . 'includes/taxonomy-abilities.php';
require_once ABILITIES_FOR_AI_PATH . 'includes/plugin-abilities.php';
require_once ABILITIES_FOR_AI_PATH . 'includes/media-abilities.php';
require_once ABILITIES_FOR_AI_PATH . 'includes/user-abilities.php';
require_once ABILITIES_FOR_AI_PATH . 'includes/comment-abilities.php';
require_once ABILITIES_FOR_AI_PATH . 'includes/menu-abilities.php';

// Additional ability modules.
require_once ABILITIES_FOR_AI_PATH . 'includes/blocks-abilities.php';
require_once ABILITIES_FOR_AI_PATH . 'includes/patterns-abilities.php';
require_once ABILITIES_FOR_AI_PATH . 'includes/meta-abilities.php';
require_once ABILITIES_FOR_AI_PATH . 'includes/settings-abilities.php';
require_once ABILITIES_FOR_AI_PATH . 'includes/site-health-abilities.php';
require_once ABILITIES_FOR_AI_PATH . 'includes/transients-abilities.php';
require_once ABILITIES_FOR_AI_PATH . 'includes/cron-abilities.php';
require_once ABILITIES_FOR_AI_PATH . 'includes/themes-abilities.php';
require_once ABILITIES_FOR_AI_PATH . 'includes/rest-discovery-abilities.php';
require_once ABILITIES_FOR_AI_PATH . 'includes/rewrite-abilities.php';
require_once ABILITIES_FOR_AI_PATH . 'includes/filesystem-abilities.php';

// Load admin dashboard
if ( is_admin() ) {
    require_once ABILITIES_FOR_AI_PATH . 'admin/dashboard.php';
}

// Activation hook
register_activation_hook( __FILE__, function( $network_wide = false ) {
    // Set default options
    if ( is_multisite() && $network_wide ) {
        update_site_option( 'abilities_for_ai_version', ABILITIES_FOR_AI_VERSION );

        // Iterate all sites to set per-site permissions.
        $site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
        foreach ( $site_ids as $site_id ) {
            switch_to_blog( $site_id );
            if ( false === get_option( 'abilities_for_ai_permissions' ) ) {
                update_option( 'abilities_for_ai_permissions', abilities_for_ai_permission_defaults() );
            }
            restore_current_blog();
        }
    } else {
        update_option( 'abilities_for_ai_version', ABILITIES_FOR_AI_VERSION );

        // Set default permissions if not already set.
        if ( false === get_option( 'abilities_for_ai_permissions' ) ) {
            update_option( 'abilities_for_ai_permissions', abilities_for_ai_permission_defaults() );
        }
    }

    // Flush cache
    wp_cache_flush();

    error_log( 'Abilities for AI: Activated v' . ABILITIES_FOR_AI_VERSION );
});

// Deactivation hook
register_deactivation_hook( __FILE__, function() {
    wp_cache_flush();
    error_log( 'Abilities for AI: Deactivated' );
});

// New multisite subsite: set default permissions automatically.
add_action( 'wp_initialize_site', function( $new_site ) {
    $site_id = $new_site->blog_id;
    switch_to_blog( $site_id );
    if ( false === get_option( 'abilities_for_ai_permissions' ) ) {
        update_option( 'abilities_for_ai_permissions', abilities_for_ai_permission_defaults() );
    }
    restore_current_blog();
}, 200 ); // Priority 200: run after WordPress core finishes site setup.
