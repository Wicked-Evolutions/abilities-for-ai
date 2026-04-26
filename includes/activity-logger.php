<?php
/**
 * Knowledge Layer — Automatic Ability Execution Logger.
 *
 * Listens to wp_before_execute_ability and wp_after_execute_ability hooks
 * fired by WP_Ability::execute() in WordPress core. Records every execution
 * into the kl_activity table — zero per-ability code changes required.
 *
 * v0.6.0 (issue #123) — extended with 8 columns of operational signal:
 * response/input size, response hash, memory delta, SQL query count,
 * caller origin, is_compiled (hybrid detection), replaced_surface.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Capture baselines before ability execution.
 *
 * Global array keyed by ability name stores start time + resource baselines.
 * Safe for nested/recursive calls: each name overwrites — the most recent
 * start wins, which matches the most recent after-hook call.
 */
add_action( 'wp_before_execute_ability', function( string $ability_name, $input ) {
	global $wpdb, $abilities_kl_activity_timers;
	if ( ! is_array( $abilities_kl_activity_timers ) ) {
		$abilities_kl_activity_timers = array();
	}

	$abilities_kl_activity_timers[ $ability_name ] = array(
		'start'           => microtime( true ),
		'memory_baseline' => memory_get_peak_usage( true ),
		'query_baseline'  => $wpdb->num_queries,
		'input_size'      => $input !== null ? strlen( wp_json_encode( $input ) ) : 0,
	);
}, 10, 2 );

/**
 * Record execution result after ability completes.
 */
add_action( 'wp_after_execute_ability', function( string $ability_name, $input, $result ) {
	global $wpdb, $abilities_kl_activity_timers;

	// Load baselines captured in before-hook. Fallback to zero values if missing
	// (shouldn't happen, but defensive — e.g. if the before-hook didn't fire).
	$baseline = $abilities_kl_activity_timers[ $ability_name ] ?? array(
		'start'           => microtime( true ),
		'memory_baseline' => memory_get_peak_usage( true ),
		'query_baseline'  => $wpdb->num_queries,
		'input_size'      => 0,
	);
	unset( $abilities_kl_activity_timers[ $ability_name ] );

	// --- Basic fields (pre-existing) -------------------------------------

	$duration_ms = (int) round( ( microtime( true ) - $baseline['start'] ) * 1000 );

	$status     = 'success';
	$error_code = null;
	if ( is_wp_error( $result ) ) {
		$status     = 'error';
		$error_code = $result->get_error_code();
	}

	$parts    = explode( '/', $ability_name, 2 );
	$category = $parts[0] ?? '';

	$input_hash = '';
	if ( $input !== null ) {
		$input_hash = hash( 'xxh128', wp_json_encode( $input ) );
	}

	$session_id = '';
	if ( ! empty( $_SERVER['HTTP_MCP_SESSION_ID'] ) ) {
		$session_id = sanitize_text_field( wp_unslash( $_SERVER['HTTP_MCP_SESSION_ID'] ) );
	}

	// --- v0.6.0 fields (issue #123) --------------------------------------

	// Response payload — size + hash (no content stored).
	$result_json         = wp_json_encode( $result );
	$response_size_bytes = $result_json !== false ? strlen( $result_json ) : 0;
	$response_hash       = $result_json !== false && $result_json !== 'null'
		? hash( 'xxh128', $result_json )
		: '';

	// Input size (captured in before-hook).
	$input_size_bytes = (int) $baseline['input_size'];

	// Memory delta — current peak minus baseline peak. Can be zero (peak
	// may have been reached before this ability ran). Can be negative in
	// theory; stored as bigint signed to handle that.
	$memory_delta_bytes = memory_get_peak_usage( true ) - $baseline['memory_baseline'];

	// SQL query count delta.
	$sql_query_count = max( 0, $wpdb->num_queries - $baseline['query_baseline'] );

	// Caller origin — priority-ordered detection.
	$caller_origin = abilities_kl_detect_caller_origin();

	// Is compiled — hybrid detection, annotation wins, heuristic fallback.
	$is_compiled = abilities_kl_detect_is_compiled( $ability_name, $result );

	// Replaced surface — read from ability meta annotation.
	$replaced_surface = abilities_kl_get_replaced_surface( $ability_name );

	// --- Write to table (bail if not migrated yet) ----------------------

	$table = $wpdb->prefix . 'kl_activity';

	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
		return;
	}

	$wpdb->insert(
		$table,
		array(
			'ability_name'        => $ability_name,
			'category'            => $category,
			'input_hash'          => $input_hash,
			'status'              => $status,
			'error_code'          => $error_code,
			'duration_ms'         => $duration_ms,
			'user_id'             => get_current_user_id(),
			'session_id'          => $session_id,
			'response_size_bytes' => $response_size_bytes,
			'response_hash'       => $response_hash,
			'input_size_bytes'    => $input_size_bytes,
			'memory_delta_bytes'  => $memory_delta_bytes,
			'sql_query_count'     => $sql_query_count,
			'caller_origin'       => $caller_origin,
			'is_compiled'         => $is_compiled ? 1 : 0,
			'replaced_surface'    => $replaced_surface,
			'created_at'          => current_time( 'mysql', true ),
		),
		array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%d', '%d', '%d', '%s', '%d', '%s', '%s' )
	);
}, 10, 3 );

/**
 * Detect the origin of the current ability call.
 *
 * Priority order (first match wins):
 *   1. HTTP_MCP_SESSION_ID header → mcp
 *   2. WP_CLI constant → cli
 *   3. DOING_CRON constant → wp-cron
 *   4. REST request context → rest  (checked before is_admin because REST
 *      requests can set is_admin to true in some contexts)
 *   5. is_admin() → wp-admin
 *   6. Default → internal
 *
 * @return string One of: mcp, rest, wp-admin, wp-cron, cli, internal.
 */
function abilities_kl_detect_caller_origin() {
	if ( ! empty( $_SERVER['HTTP_MCP_SESSION_ID'] ) ) {
		return 'mcp';
	}
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return 'cli';
	}
	if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
		return 'wp-cron';
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return 'rest';
	}
	if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
		return 'rest';
	}
	if ( is_admin() ) {
		return 'wp-admin';
	}
	return 'internal';
}

/**
 * Determine whether an ability is "compiled" — crosses multiple data
 * domains in a single call (e.g. fluent/get-user-360, diagnostic/site-overview).
 *
 * Hybrid detection:
 *   1. Explicit annotation: if the ability's meta has a 'compiled' key, use it.
 *      Abilities annotated with meta.compiled => true/false get deterministic
 *      classification regardless of response shape.
 *   2. Heuristic fallback: if no annotation, inspect the response structure.
 *      Response is considered compiled if it is an associative array/object
 *      with ≥ 3 top-level keys that each contain structured data (arrays or
 *      objects), not primitives. This catches cross-domain aggregations that
 *      haven't yet been annotated (including third-party abilities).
 *
 * The heuristic is conservative: a CRUD list returning {items, total, page}
 * has 3 keys but only one (items) contains structured data — returns false.
 * A compiled 360 returning {crm: {...}, community: {...}, forms: {...}} has
 * 3 structured-data keys — returns true.
 *
 * @param string $ability_name Name of the ability (e.g. 'content/list').
 * @param mixed  $result       Return value from the ability's execute callback.
 * @return bool True if compiled, false otherwise.
 */
function abilities_kl_detect_is_compiled( $ability_name, $result ) {
	// Priority 1: explicit annotation on the ability registration.
	if ( function_exists( 'wp_get_ability' ) ) {
		$ability = wp_get_ability( $ability_name );
		if ( $ability && method_exists( $ability, 'get_meta' ) ) {
			$meta = $ability->get_meta();
			if ( is_array( $meta ) && array_key_exists( 'compiled', $meta ) ) {
				return (bool) $meta['compiled'];
			}
		}
	}

	// Priority 2: heuristic fallback on response shape.
	if ( is_wp_error( $result ) ) {
		return false;
	}

	// Normalize object to array for inspection.
	if ( is_object( $result ) ) {
		$result = (array) $result;
	}

	if ( ! is_array( $result ) ) {
		return false;
	}

	// Must be an associative array (ability responses typically are).
	// Numeric-indexed arrays are flat lists — not compiled.
	$keys = array_keys( $result );
	if ( $keys === range( 0, count( $keys ) - 1 ) ) {
		return false;
	}

	// Count top-level keys whose values are themselves structured
	// (arrays or objects). Primitives don't count.
	$structured_key_count = 0;
	foreach ( $result as $value ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			$structured_key_count++;
			if ( $structured_key_count >= 3 ) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Read the ability's meta.replaces annotation, if set.
 *
 * Abilities can declare which wp-admin screen they replace via
 * meta.replaces => 'wp-admin/users.php' (or similar). Powers the
 * "admin panel is now optional" intelligence in the dashboard.
 *
 * Returns null if the annotation is absent, or if wp_get_ability is
 * unavailable (e.g. on very early hooks before registration).
 *
 * @param string $ability_name
 * @return string|null
 */
function abilities_kl_get_replaced_surface( $ability_name ) {
	if ( ! function_exists( 'wp_get_ability' ) ) {
		return null;
	}
	$ability = wp_get_ability( $ability_name );
	if ( ! $ability || ! method_exists( $ability, 'get_meta' ) ) {
		return null;
	}
	$meta = $ability->get_meta();
	if ( ! is_array( $meta ) || ! array_key_exists( 'replaces', $meta ) ) {
		return null;
	}
	// null/empty string stays null; actual string is stored as-is.
	$replaces = $meta['replaces'];
	if ( $replaces === null || $replaces === '' ) {
		return null;
	}
	return is_string( $replaces ) ? $replaces : null;
}
