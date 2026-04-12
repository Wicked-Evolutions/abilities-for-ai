<?php
/**
 * Knowledge Layer — Automatic Ability Execution Logger.
 *
 * Listens to wp_before_execute_ability and wp_after_execute_ability hooks
 * fired by WP_Ability::execute() in WordPress core. Records every execution
 * into the kl_activity table — zero per-ability code changes required.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Capture start times before ability execution.
 *
 * Static array keyed by ability name stores microtime(true).
 * Safe for nested/recursive calls: each name overwrites — the most
 * recent start wins, which matches the most recent after-hook call.
 */
add_action( 'wp_before_execute_ability', function( string $ability_name, $input ) {
	global $abilities_kl_activity_timers;
	if ( ! is_array( $abilities_kl_activity_timers ) ) {
		$abilities_kl_activity_timers = array();
	}
	$abilities_kl_activity_timers[ $ability_name ] = microtime( true );
}, 10, 2 );

/**
 * Record execution result after ability completes.
 */
add_action( 'wp_after_execute_ability', function( string $ability_name, $input, $result ) {
	global $wpdb, $abilities_kl_activity_timers;

	// Compute duration.
	$start       = $abilities_kl_activity_timers[ $ability_name ] ?? microtime( true );
	$duration_ms = (int) round( ( microtime( true ) - $start ) * 1000 );
	unset( $abilities_kl_activity_timers[ $ability_name ] );

	// Determine status and error code.
	$status     = 'success';
	$error_code = null;
	if ( is_wp_error( $result ) ) {
		$status     = 'error';
		$error_code = $result->get_error_code();
	}

	// Extract category from ability name (e.g. "content/list" -> "content").
	$parts    = explode( '/', $ability_name, 2 );
	$category = $parts[0] ?? '';

	// Hash the input for grouping without storing full payloads.
	$input_hash = '';
	if ( $input !== null ) {
		$input_hash = hash( 'xxh128', wp_json_encode( $input ) );
	}

	// Get MCP session ID if available (set by Abilities MCP Adapter).
	$session_id = '';
	if ( ! empty( $_SERVER['HTTP_MCP_SESSION_ID'] ) ) {
		$session_id = sanitize_text_field( wp_unslash( $_SERVER['HTTP_MCP_SESSION_ID'] ) );
	}

	$table = $wpdb->prefix . 'kl_activity';

	// Bail silently if table doesn't exist yet (pre-migration).
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
		return;
	}

	$wpdb->insert(
		$table,
		array(
			'ability_name' => $ability_name,
			'category'     => $category,
			'input_hash'   => $input_hash,
			'status'       => $status,
			'error_code'   => $error_code,
			'duration_ms'  => $duration_ms,
			'user_id'      => get_current_user_id(),
			'session_id'   => $session_id,
			'created_at'   => current_time( 'mysql', true ),
		),
		array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
	);
}, 10, 3 );
