<?php
/**
 * Suite Loader Template
 *
 * Copy this to includes/suites/{your-suite}/loader.php
 * Replace the detection check, category registration, and module requires.
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

// === DETECTION ===
// Return early if the parent plugin is not active. Zero runtime cost.
// Examples:
//   if ( ! defined( 'ASTRA_THEME_VERSION' ) ) return;
//   if ( ! class_exists( 'UAGB_Loader' ) ) return;
//   if ( ! defined( 'SURECART_PLUGIN_FILE' ) ) return;
//   if ( ! class_exists( 'PrestoPlayer\\Plugin' ) ) return;

// === CONSTANTS ===
// define( 'ABILITIES_FOR_AI_SUITE_EXAMPLE_PATH', __DIR__ . '/' );

// === CATEGORY REGISTRATION ===
// IMPORTANT: Suite categories MUST be registered in includes/ability-categories.php
// (inside abilities_for_ai_register_categories()), NOT here. WP core only allows
// wp_register_ability_category() during the wp_abilities_api_categories_init hook,
// which fires BEFORE the suite auto-loader runs. Add your category there with
// a detection guard (class_exists / defined check).

// === MODULE LOADING ===
// require_once __DIR__ . '/product-abilities.php';
// require_once __DIR__ . '/settings-abilities.php';
