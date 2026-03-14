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
// Register suite-specific ability categories.
// add_action( 'wp_abilities_api_categories_init', function() {
//     wp_register_ability_category( 'example', array(
//         'label'       => __( 'Example Suite', 'abilities-for-ai' ),
//         'description' => __( 'Abilities for the Example plugin.', 'abilities-for-ai' ),
//     ));
// });

// === MODULE LOADING ===
// require_once __DIR__ . '/product-abilities.php';
// require_once __DIR__ . '/settings-abilities.php';
