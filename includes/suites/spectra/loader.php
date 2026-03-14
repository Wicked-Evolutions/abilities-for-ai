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

// === DETECTION ===
if ( ! class_exists( 'UAGB_Loader' ) ) {
	return;
}

// Category 'spectra' is registered in ability-categories.php (must happen
// during wp_abilities_api_categories_init which fires before suite auto-loader).

// === MODULE LOADING ===
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/block-parser-abilities.php';
require_once __DIR__ . '/image-abilities.php';
require_once __DIR__ . '/asset-abilities.php';
require_once __DIR__ . '/inspection-abilities.php';
require_once __DIR__ . '/validation-abilities.php';
require_once __DIR__ . '/reusable-block-abilities.php';
