<?php
/**
 * Permission Toggles — Settings API Registration
 *
 * Handles WordPress Settings API registration, sanitization, and
 * ability count calculations for the permission toggles system.
 *
 * @package WordPress_Abilities_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the permissions setting with WordPress Settings API.
 */
add_action( 'admin_init', function() {
	register_setting(
		'wp_abilities_suite_permissions_group',
		'wp_abilities_suite_permissions',
		array(
			'type'              => 'object',
			'sanitize_callback' => 'wp_abilities_suite_sanitize_permissions',
			'default'           => wp_abilities_suite_permission_defaults(),
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
function wp_abilities_suite_sanitize_permissions( $input ) {
	$defaults  = wp_abilities_suite_permission_defaults();
	$sanitized = array();

	foreach ( $defaults as $module => $ops ) {
		$sanitized[ $module ] = array();
		foreach ( $ops as $op => $default_val ) {
			// Checkbox: present in POST = checked (true), absent = unchecked (false).
			$sanitized[ $module ][ $op ] = ! empty( $input[ $module ][ $op ] );
		}
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
function wp_abilities_suite_get_ability_counts() {
	// Map category slugs to modules for counting.
	$category_to_module = array(
		'content'    => 'content',
		'taxonomies' => 'taxonomies',
		'plugins'    => 'plugins',
		'media'      => 'media',
		'users'      => 'users',
		'comments'   => 'comments',
		'menus'      => 'menus',
		'blocks'     => 'blocks',
		'patterns'   => 'patterns',
		'meta'       => 'meta',
		'settings'   => 'settings',
		'site-health' => 'site-health',
		'cache'      => 'cache',
		'cron'       => 'cron',
		'themes'     => 'themes',
		'rest'       => 'rest',
		'rewrite'    => 'rewrite',
		'filesystem' => 'filesystem',
	);

	$counts = array();
	foreach ( array_keys( wp_abilities_suite_permission_defaults() ) as $module ) {
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
function wp_abilities_suite_enabled_count() {
	$counts  = wp_abilities_suite_get_ability_counts();
	$enabled = 0;
	$total   = 0;

	foreach ( $counts as $module => $module_counts ) {
		$perms = wp_abilities_suite_get_permissions( $module );
		$total += $module_counts['total'];

		if ( ! empty( $perms['read'] ) ) {
			$enabled += $module_counts['read'];
		}
		if ( ! empty( $perms['write'] ) ) {
			$enabled += $module_counts['write'];
		}
		if ( ! empty( $perms['delete'] ) ) {
			$enabled += $module_counts['delete'];
		}
	}

	return array( 'enabled' => $enabled, 'total' => $total );
}
