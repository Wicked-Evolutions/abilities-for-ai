<?php
/**
 * Presto Player Suite Loader
 *
 * Video and media player abilities via Presto Player's Model API.
 * Detection deferred to wp_abilities_api_init (fires after init) because
 * Presto Player may not be autoloaded when our plugin file first loads.
 *
 * Presto Player is a trademark of Starter Starter LLC.
 * Abilities for AI is not affiliated with or endorsed by Starter Starter LLC.
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

// Category 'presto-player' is registered in ability-categories.php (must happen
// during wp_abilities_api_categories_init which fires before abilities register).

// Defer detection + loading to wp_abilities_api_init. At this point (plugin file
// load time), Presto Player's autoloader may not be registered yet. The
// wp_abilities_api_init hook fires after init, when all plugins are fully loaded.
add_action( 'wp_abilities_api_init', function() {

	// === DETECTION ===
	if ( ! class_exists( 'PrestoPlayer\Plugin' ) ) {
		return;
	}

	// === MODULE LOADING ===
	// Each file creates a registrar and registers abilities directly (not
	// inside another add_action), since we are already inside wp_abilities_api_init.
	require_once __DIR__ . '/video-abilities.php';
	require_once __DIR__ . '/preset-abilities.php';
	require_once __DIR__ . '/audio-preset-abilities.php';
	require_once __DIR__ . '/settings-abilities.php';
	require_once __DIR__ . '/email-collection-abilities.php';
	require_once __DIR__ . '/webhook-abilities.php';

	// Pro plugin abilities (only loaded when Presto Player Pro is active).
	if ( class_exists( 'PrestoPlayer\Pro\Plugin' ) ) {
		require_once __DIR__ . '/analytics-abilities.php';
		require_once __DIR__ . '/bunny-abilities.php';
	}

}, 5 ); // Priority 5: run before default (10) so abilities are available to other hooks.
