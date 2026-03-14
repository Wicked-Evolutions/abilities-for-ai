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

	// Register suite category. This add_action runs inside plugins_loaded (priority 99),
	// which fires BEFORE init. The abilities category hook is lazy — fires on first
	// registry access after init — so this callback will be registered in time.
	add_action( 'wp_abilities_api_categories_init', function() {
		wp_register_ability_category( 'surecart', array(
			'label'       => __( 'SureCart', 'abilities-for-ai' ),
			'description' => __( 'E-commerce abilities for SureCart — products, orders, customers, subscriptions, and store management. This is an independent integration and is not affiliated with or endorsed by SureCart Inc.', 'abilities-for-ai' ),
		));
	});

	// Load helpers.
	require_once __DIR__ . '/helpers.php';

	// Load P0 ability modules.
	require_once __DIR__ . '/product-abilities.php';
	require_once __DIR__ . '/order-abilities.php';
	require_once __DIR__ . '/customer-abilities.php';
	require_once __DIR__ . '/subscription-abilities.php';

}, 99 ); // Late priority — after most plugins have loaded.
