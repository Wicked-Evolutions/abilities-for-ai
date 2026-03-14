<?php
/**
 * Astra Suite Loader
 *
 * Integrates Astra Theme + Astra Pro abilities into Abilities for AI.
 * Detection is deferred to wp_abilities_api_init because the theme constant
 * (ASTRA_THEME_VERSION) is not yet defined when plugins load.
 *
 * This is an independent integration and is not affiliated with or endorsed by Brainstorm Force.
 *
 * @package Abilities_For_AI
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

// === DEFERRED LOADING ===
// Plugins load before themes, so ASTRA_THEME_VERSION is not available yet.
// We hook into wp_abilities_api_init (fires on 'init' priority 10) where the
// theme is fully loaded. Helpers are loaded just-in-time inside the callback.
add_action( 'wp_abilities_api_init', function() {

	// Detection — bail if Astra theme is not active.
	if ( ! defined( 'ASTRA_THEME_VERSION' ) ) {
		return;
	}

	// Only load once.
	if ( defined( 'ABILITIES_FOR_AI_SUITE_ASTRA_PATH' ) ) {
		return;
	}
	define( 'ABILITIES_FOR_AI_SUITE_ASTRA_PATH', __DIR__ . '/' );

	// Helpers (shared functions used by ability files).
	require_once __DIR__ . '/helpers.php';

	// Ability modules — each file creates a registrar and registers abilities
	// directly (not inside another add_action), since we are already inside
	// wp_abilities_api_init.
	require_once __DIR__ . '/settings-abilities.php';
	require_once __DIR__ . '/layout-abilities.php';
	require_once __DIR__ . '/header-footer-abilities.php';
	require_once __DIR__ . '/custom-layout-abilities.php';
	require_once __DIR__ . '/pro-abilities.php';
	require_once __DIR__ . '/global-styles-abilities.php';
	require_once __DIR__ . '/blog-archive-abilities.php';
	require_once __DIR__ . '/breadcrumb-scroll-perf-abilities.php';
	require_once __DIR__ . '/page-header-abilities.php';

}, 5 ); // Priority 5: run before default (10) so other hooks can see our abilities.
