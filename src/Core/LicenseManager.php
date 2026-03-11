<?php
/**
 * License Manager — Namespaced Static Wrapper
 *
 * Provides a namespaced interface for the license manager.
 * The procedural class Abilities_For_AI_License_Manager is defined in
 * includes/license-manager.php for backward compat.
 * *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WickedEvolutions\AbilitiesForAI
 */

namespace WickedEvolutions\AbilitiesForAI\Core;

defined( 'ABSPATH' ) || exit;

class LicenseManager {

	/**
	 * Check if a Pro license is currently active.
	 *
	 * @return bool
	 */
	public static function is_pro_active() {
		return \Abilities_For_AI_License_Manager::is_pro_active();
	}

	/**
	 * Activate a license key.
	 *
	 * @param string $license_key
	 * @return true|\WP_Error
	 */
	public static function activate( $license_key ) {
		return \Abilities_For_AI_License_Manager::activate( $license_key );
	}

	/**
	 * Deactivate the current license.
	 *
	 * @return bool
	 */
	public static function deactivate() {
		return \Abilities_For_AI_License_Manager::deactivate();
	}

	/**
	 * Get the Pro-required error response.
	 *
	 * @param string $ability_name
	 * @return \WP_Error
	 */
	public static function pro_required_error( $ability_name ) {
		return \Abilities_For_AI_License_Manager::pro_required_error( $ability_name );
	}

	/**
	 * Get current license status for admin UI.
	 *
	 * @return array
	 */
	public static function get_status() {
		return \Abilities_For_AI_License_Manager::get_status();
	}
}
