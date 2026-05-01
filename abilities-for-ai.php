<?php
/**
 * Plugin Name: Abilities for AI
 * Plugin URI:  https://github.com/Wicked-Evolutions/abilities-for-ai
 * Description: Powers AI control of WordPress through the WordPress Abilities API. Native operations across content, blocks, meta, settings, cron, themes, patterns, site health, REST discovery, menus, filesystem, knowledge, users, revisions, multisite, and supported third-party plugins.
 * Version: 1.9.0
 * Author: Wicked Evolutions
 * Author URI: https://wickedevolutions.com
 * Copyright: Copyright (C) 2026 Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires PHP: 8.1
 * Requires at least: 6.9
 * Network: true
 */

defined( 'ABSPATH' ) || exit;

// Define plugin constants (guarded — WordPress updater can re-include this file).
if ( ! defined( 'ABILITIES_FOR_AI_VERSION' ) ) {
	define( 'ABILITIES_FOR_AI_VERSION', '1.9.0' );
}
if ( ! defined( 'ABILITIES_FOR_AI_PATH' ) ) {
	define( 'ABILITIES_FOR_AI_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'ABILITIES_FOR_AI_URL' ) ) {
	define( 'ABILITIES_FOR_AI_URL', plugin_dir_url( __FILE__ ) );
}

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
require_once ABILITIES_FOR_AI_PATH . 'includes/block-helpers.php';

// Load permission toggles system.
require_once ABILITIES_FOR_AI_PATH . 'includes/permissions.php';

// Load Pro tier infrastructure.
require_once ABILITIES_FOR_AI_PATH . 'includes/license-manager.php';
require_once ABILITIES_FOR_AI_PATH . 'includes/tier-gate.php';

// compat.php provides `Abilities_For_AI_Registrar` alias for the namespaced class —
// must load after autoloader + helpers so the class and its dependencies exist.
require_once ABILITIES_FOR_AI_PATH . 'includes/compat.php';

// Load custom ability classes (must load after WP core, before ability modules).
require_once ABILITIES_FOR_AI_PATH . 'includes/class-multisite-ability.php';

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
require_once ABILITIES_FOR_AI_PATH . 'includes/theme-enqueue-abilities.php';
require_once ABILITIES_FOR_AI_PATH . 'includes/rest-discovery-abilities.php';
require_once ABILITIES_FOR_AI_PATH . 'includes/rewrite-abilities.php';
require_once ABILITIES_FOR_AI_PATH . 'includes/filesystem-abilities.php';
require_once ABILITIES_FOR_AI_PATH . 'includes/revision-abilities.php';
require_once ABILITIES_FOR_AI_PATH . 'includes/multisite-abilities.php';

// Diagnostic scripts — compiled multi-module reports.
require_once ABILITIES_FOR_AI_PATH . 'includes/diagnostic-abilities.php';

// Editorial scripts — content analysis and editorial intelligence.
require_once ABILITIES_FOR_AI_PATH . 'includes/editorial-abilities.php';

// Knowledge layer — auto-loads .md docs from knowledge/ as read-only abilities (v0.0.1 fallback).
require_once ABILITIES_FOR_AI_PATH . 'includes/knowledge-abilities.php';

// Knowledge Layer v0.0.2 — CRUD abilities backed by kl_* database tables.
require_once ABILITIES_FOR_AI_PATH . 'includes/knowledge-layer-abilities.php';

// Knowledge Layer v0.3.1 — Tag CRUD + assign/unassign abilities.
require_once ABILITIES_FOR_AI_PATH . 'includes/knowledge-tag-abilities.php';

// Knowledge Layer REST API — admin SPA controllers under abilities-kl/v1.
require_once ABILITIES_FOR_AI_PATH . 'includes/knowledge-rest.php';

// Knowledge Layer — automatic ability execution logging via core hooks.
require_once ABILITIES_FOR_AI_PATH . 'includes/activity-logger.php';

// Knowledge Layer — MCP boundary event logging via adapter observability hooks.
require_once ABILITIES_FOR_AI_PATH . 'includes/boundary-logger.php';

// Auto-load third-party plugin suites.
// Each suite lives in includes/suites/{slug}/loader.php and handles
// its own detection (class_exists / defined checks). If the parent
// plugin is not active, the loader returns early — zero runtime cost.
foreach ( glob( ABILITIES_FOR_AI_PATH . 'includes/suites/*/loader.php' ) as $suite_loader ) {
	require_once $suite_loader;
}

// Load plugin updater — checks FluentCart for new versions.
require_once ABILITIES_FOR_AI_PATH . 'includes/updater/class-plugin-updater.php';

new Abilities_For_AI_Plugin_Updater( array(
	'slug'                 => 'abilities-for-ai',
	'basename'             => plugin_basename( __FILE__ ),
	'version'              => ABILITIES_FOR_AI_VERSION,
	'item_id'              => Abilities_For_AI_License_Manager::get_product_id(),
	'api_url'              => Abilities_For_AI_License_Manager::STORE_URL,
	'license_key_callback' => array( 'Abilities_For_AI_License_Manager', 'get_license_key' ),
	'github_repo'          => 'Wicked-Evolutions/abilities-for-ai',
	'show_check_update'    => true,
) );

// Load admin pages
if ( is_admin() ) {
    require_once ABILITIES_FOR_AI_PATH . 'admin/dashboard.php';
    require_once ABILITIES_FOR_AI_PATH . 'admin/knowledge-layer.php';
}

// Activation hook
register_activation_hook( __FILE__, function( $network_wide = false ) {
    // Set default options
    if ( is_multisite() && $network_wide ) {
        update_site_option( 'abilities_for_ai_version', ABILITIES_FOR_AI_VERSION );

        // Iterate all sites to set per-site permissions + Knowledge Layer tables.
        $site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
        foreach ( $site_ids as $site_id ) {
            switch_to_blog( $site_id );
            if ( false === get_option( 'abilities_for_ai_permissions' ) ) {
                update_option( 'abilities_for_ai_permissions', abilities_for_ai_permission_defaults() );
            }
            // Knowledge Layer: create tables + seed per site.
            \WickedEvolutions\AbilitiesForAI\Knowledge\Schema::up();
            \WickedEvolutions\AbilitiesForAI\Knowledge\Seeder::seed();
            restore_current_blog();
        }
    } else {
        update_option( 'abilities_for_ai_version', ABILITIES_FOR_AI_VERSION );

        // Set default permissions if not already set.
        if ( false === get_option( 'abilities_for_ai_permissions' ) ) {
            update_option( 'abilities_for_ai_permissions', abilities_for_ai_permission_defaults() );
        }

        // Knowledge Layer: create tables + seed.
        \WickedEvolutions\AbilitiesForAI\Knowledge\Schema::up();
        \WickedEvolutions\AbilitiesForAI\Knowledge\Seeder::seed();
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

// Knowledge Layer: check for schema migrations on plugin update (no reactivation needed).
add_action( 'plugins_loaded', function() {
    \WickedEvolutions\AbilitiesForAI\Knowledge\Schema::maybe_migrate();
}, 5 );

// New multisite subsite: set default permissions automatically.
add_action( 'wp_initialize_site', function( $new_site ) {
    $site_id = $new_site->blog_id;
    switch_to_blog( $site_id );
    if ( false === get_option( 'abilities_for_ai_permissions' ) ) {
        update_option( 'abilities_for_ai_permissions', abilities_for_ai_permission_defaults() );
    }
    // Knowledge Layer: create tables + seed for new subsite.
    \WickedEvolutions\AbilitiesForAI\Knowledge\Schema::up();
    \WickedEvolutions\AbilitiesForAI\Knowledge\Seeder::seed();
    restore_current_blog();
}, 200 ); // Priority 200: run after WordPress core finishes site setup.
