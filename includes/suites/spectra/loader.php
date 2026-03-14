<?php
/**
 * Spectra Suite Loader
 *
 * Server-side Spectra block operations via the Abilities for AI registrar.
 * Complements the Spectra MCP server (client-side block generation).
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

// === DEFERRED LOADING ===
// Spectra (UAGB_Loader) may not be loaded yet when the glob() auto-loader runs.
// Defer detection to wp_abilities_api_init (fires on 'init') where all plugins
// are available. Ability files already use add_action('wp_abilities_api_init')
// internally, so they register correctly when included inside this callback.
add_action( 'wp_abilities_api_init', function() {

	// Detection — bail if Spectra is not active.
	if ( ! class_exists( 'UAGB_Loader' ) ) {
		return;
	}

	// Only load once.
	if ( defined( 'ABILITIES_FOR_AI_SUITE_SPECTRA_PATH' ) ) {
		return;
	}
	define( 'ABILITIES_FOR_AI_SUITE_SPECTRA_PATH', __DIR__ . '/' );

	// Category 'spectra' is registered in ability-categories.php (must happen
	// during wp_abilities_api_categories_init which fires before this hook).

	// === MODULE LOADING ===
	require_once __DIR__ . '/helpers.php';
	require_once __DIR__ . '/block-parser-abilities.php';
	require_once __DIR__ . '/image-abilities.php';
	require_once __DIR__ . '/asset-abilities.php';
	require_once __DIR__ . '/inspection-abilities.php';
	require_once __DIR__ . '/validation-abilities.php';
	require_once __DIR__ . '/reusable-block-abilities.php';
	require_once __DIR__ . '/block-operations-abilities.php';
	require_once __DIR__ . '/settings-abilities.php';

}, 5 ); // Priority 5: before default (10) so abilities are visible to other hooks.
