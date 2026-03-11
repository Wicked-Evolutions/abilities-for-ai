<?php
/**
 * Tier Gate — Wraps Pro ability callbacks with license verification.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wrap an execute_callback with a Pro license check.
 *
 * If the license is not active, returns a Pro Required error.
 * If the license IS active, calls the original callback.
 *
 * @param string   $ability_name The ability slug (for error messages).
 * @param callable $callback     The original execute_callback.
 * @return callable Wrapped callback.
 */
function abilities_for_ai_pro_gate( $ability_name, $callback ) {
	return function( $params ) use ( $ability_name, $callback ) {
		if ( ! Abilities_For_AI_License_Manager::is_pro_active() ) {
			return Abilities_For_AI_License_Manager::pro_required_error( $ability_name );
		}
		return $callback( $params );
	};
}
