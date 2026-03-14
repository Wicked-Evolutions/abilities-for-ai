<?php
/**
 * SureCart Suite for Abilities for AI
 *
 * SureCart e-commerce abilities — products, orders, customers, subscriptions, and store management.
 * SureCart is a trademark of SureCart Inc. This module is not affiliated with or endorsed by SureCart Inc.
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

// Detection deferred to plugins_loaded — SureCart may load after Abilities for AI.
add_action( 'plugins_loaded', function() {

	if ( ! defined( 'SURECART_PLUGIN_FILE' ) ) {
		return;
	}

	define( 'ABILITIES_FOR_AI_SUITE_SURECART_PATH', __DIR__ . '/' );

	// Category 'surecart' is registered in ability-categories.php (must happen
	// during wp_abilities_api_categories_init which fires before suite auto-loader).

	// Load helpers.
	require_once __DIR__ . '/helpers.php';

	// Load P0 ability modules.
	require_once __DIR__ . '/product-abilities.php';
	require_once __DIR__ . '/order-abilities.php';
	require_once __DIR__ . '/customer-abilities.php';
	require_once __DIR__ . '/subscription-abilities.php';

}, 99 ); // Late priority — after most plugins have loaded.
