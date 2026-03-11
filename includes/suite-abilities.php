<?php
/**
 * Suite Management Abilities
 *
 * Global status and registration information for the entire Abilities ecosystem.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WordPress_Abilities_Suite
 */

defined( 'ABSPATH' ) || exit;

// Register Suite abilities
add_action( 'wp_abilities_api_init', function() {

	wp_register_ability( 'suite/get-status', array(
		'label'               => 'Get Suite Status',
		'description'         => 'Get unified status of all active ability modules, permissions, and license state across Core and Fluent suites.',
		'category'            => 'settings',
		'execute_callback'    => 'wp_abilities_suite_get_status',
		'permission_callback' => function() {
			return current_user_can( 'manage_options' );
		},
		'output_schema'       => array(
			'type'       => 'object',
			'properties' => array(
				'core' => array(
					'type' => 'object',
					'properties' => array(
						'version'     => array( 'type' => 'string' ),
						'permissions' => array( 'type' => 'object' ),
						'pro_active'  => array( 'type' => 'boolean' ),
					),
				),
				'fluent' => array(
					'type' => 'object',
					'properties' => array(
						'active_plugins' => array( 'type' => 'object' ),
						'enabled_modules' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					),
				),
				'environment' => array(
					'type' => 'object',
					'properties' => array(
						'wp_version' => array( 'type' => 'string' ),
						'php_version' => array( 'type' => 'string' ),
						'multisite'  => array( 'type' => 'boolean' ),
					),
				),
			),
		),
		'meta'                => array(
			'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true, 'permission' => 'read' ),
			'tier'        => 'free',
			'mcp'         => array( 'public' => true, 'type' => 'tool' ),
		),
	) );

} );

/**
 * Execute callback for suite/get-status.
 */
function wp_abilities_suite_get_status() {
	$status = array(
		'core' => array(
			'version'     => WP_ABILITIES_SUITE_VERSION,
			'permissions' => wp_abilities_suite_get_permissions_summary(),
			'pro_active'  => WP_Abilities_Suite_License_Manager::is_pro_active(),
		),
		'environment' => array(
			'wp_version'  => get_bloginfo( 'version' ),
			'php_version' => PHP_VERSION,
			'multisite'   => is_multisite(),
		),
	);

	// Add Fluent info if available
	if ( function_exists( 'fluent_abilities_active_modules' ) ) {
		$status['fluent'] = array(
			'active_plugins'  => fluent_abilities_active_modules(),
			'enabled_modules' => function_exists( 'fluent_abilities_get_enabled_modules' ) ? fluent_abilities_get_enabled_modules() : array(),
		);
	}

	return $status;
}

/**
 * Helper to get a clean permissions summary.
 */
function wp_abilities_suite_get_permissions_summary() {
	$modules = wp_abilities_suite_module_labels();
	$summary = array();

	foreach ( $modules as $slug => $label ) {
		$perms = wp_abilities_suite_get_permissions( $slug );
		$active_perms = array();
		foreach ( $perms as $op => $enabled ) {
			if ( $enabled ) {
				$active_perms[] = $op;
			}
		}
		$summary[ $label ] = $active_perms;
	}

	return $summary;
}
