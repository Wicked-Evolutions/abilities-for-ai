<?php
/**
 * Permission Toggles — Settings API Registration
 *
 * Handles WordPress Settings API registration, sanitization, and
 * ability count calculations for the permission toggles system.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the permissions setting with WordPress Settings API.
 */
add_action( 'admin_init', function() {
	register_setting(
		'abilities_for_ai_permissions_group',
		'abilities_for_ai_permissions',
		array(
			'type'              => 'object',
			'sanitize_callback' => 'abilities_for_ai_sanitize_permissions',
			'default'           => abilities_for_ai_permission_defaults(),
		)
	);
});

/**
 * Sanitize the permissions array on save.
 *
 * Ensures only valid modules and operation types are stored,
 * and all values are strict booleans.
 *
 * @param mixed $input Raw POST input.
 * @return array Sanitized permissions.
 */
function abilities_for_ai_sanitize_permissions( $input ) {
	$defaults  = abilities_for_ai_permission_defaults();
	$sanitized = array();

	// Module-level permissions.
	foreach ( $defaults as $module => $ops ) {
		$sanitized[ $module ] = array();
		foreach ( $ops as $op => $default_val ) {
			// Checkbox: present in POST = checked (true), absent = unchecked (false).
			$sanitized[ $module ][ $op ] = ! empty( $input[ $module ][ $op ] );
		}
	}

	// Per-ability overrides.
	// Only store overrides that DIFFER from the module-level permission.
	// This prevents stale overrides when module toggles change.
	$category_to_module = abilities_for_ai_module_prefix_map();
	$overrides = array();
	if ( ! empty( $input['_overrides'] ) && is_array( $input['_overrides'] ) ) {
		foreach ( $input['_overrides'] as $ability_name => $enabled ) {
			$ability_name = preg_replace( '/[^a-z0-9\-\/]/', '', $ability_name );
			if ( empty( $ability_name ) ) {
				continue;
			}
			$ability_enabled = ! empty( $enabled );

			// Determine the module for this ability from the name prefix.
			$parts  = explode( '/', $ability_name );
			$module = $category_to_module[ $parts[0] ] ?? null;
			if ( ! $module ) {
				continue;
			}

			// Get the module-level permission for this ability's operation type.
			$module_perms   = $sanitized[ $module ] ?? array();
			// We need to know the op type. Abilities registered via the form have their op
			// embedded in their checkbox placement. Since we only have the name, we check
			// all three ops — if module has the op enabled but ability is disabled, store override.
			// Simplification: store only if ability is disabled AND module would allow it.
			$module_has_read   = ! empty( $module_perms['read'] );
			$module_has_write  = ! empty( $module_perms['write'] );
			$module_has_delete = ! empty( $module_perms['delete'] );
			$module_would_allow = $module_has_read || $module_has_write || $module_has_delete;

			// Only store if ability is OFF but module is ON (actual override).
			if ( ! $ability_enabled && $module_would_allow ) {
				$overrides[ $ability_name ] = false;
			}
		}
	}

	if ( ! empty( $overrides ) ) {
		$sanitized['_overrides'] = $overrides;
	}

	return $sanitized;
}

/**
 * Count abilities per module and operation type.
 *
 * Scans ability files to determine how many abilities exist per read/write/delete
 * for each module. Used by the admin UI to show "X of Y abilities enabled".
 *
 * @return array Module counts: [ 'content' => ['read' => 5, 'write' => 2, 'delete' => 1, 'total' => 8], ... ]
 */
function abilities_for_ai_get_ability_counts() {
	$category_to_module = abilities_for_ai_module_prefix_map();

	$counts = array();
	foreach ( array_keys( abilities_for_ai_permission_defaults() ) as $module ) {
		$counts[ $module ] = array( 'read' => 0, 'write' => 0, 'delete' => 0, 'total' => 0 );
	}

	if ( ! function_exists( 'wp_get_abilities' ) ) {
		return $counts;
	}

	$abilities = wp_get_abilities();
	foreach ( $abilities as $name => $ability ) {
		if ( ! is_object( $ability ) || ! method_exists( $ability, 'get_category' ) ) {
			continue;
		}

		$category = $ability->get_category();
		$module   = $category_to_module[ $category ] ?? null;
		if ( ! $module || ! isset( $counts[ $module ] ) ) {
			continue;
		}

		$meta = $ability->get_meta();
		$readonly    = ! empty( $meta['annotations']['readonly'] );
		$destructive = ! empty( $meta['annotations']['destructive'] );

		if ( $readonly ) {
			$counts[ $module ]['read']++;
		} elseif ( $destructive ) {
			$counts[ $module ]['delete']++;
		} else {
			$counts[ $module ]['write']++;
		}
		$counts[ $module ]['total']++;
	}

	return $counts;
}

/**
 * Calculate total enabled abilities based on current permissions.
 *
 * @return array [ 'enabled' => int, 'total' => int ]
 */
function abilities_for_ai_enabled_count() {
	if ( ! function_exists( 'wp_get_abilities' ) ) {
		return array( 'enabled' => 0, 'total' => 0 );
	}

	$category_to_module = abilities_for_ai_module_prefix_map();

	$abilities = wp_get_abilities();
	$enabled   = 0;
	$total     = 0;

	foreach ( $abilities as $name => $ability ) {
		if ( ! is_object( $ability ) || ! method_exists( $ability, 'get_category' ) ) {
			continue;
		}

		$category = $ability->get_category();
		$module   = $category_to_module[ $category ] ?? null;
		if ( ! $module ) {
			continue;
		}

		$meta        = $ability->get_meta();
		$readonly    = ! empty( $meta['annotations']['readonly'] );
		$destructive = ! empty( $meta['annotations']['destructive'] );
		$op          = $destructive ? 'delete' : ( $readonly ? 'read' : 'write' );

		$total++;
		if ( abilities_for_ai_ability_enabled( $name, $module, $op ) ) {
			$enabled++;
		}
	}

	return array( 'enabled' => $enabled, 'total' => $total );
}
