<?php
/**
 * Shared Helpers — Namespaced Static Interface
 *
 * Provides a namespaced static interface for helper functions.
 * Procedural functions are defined in includes/helpers.php for backward compat.
 * New code can use this class; existing module files continue using functions directly.
 *
 * @package WickedEvolutions\AbilitiesSuite
 */

namespace WickedEvolutions\AbilitiesSuite\Helpers;

defined( 'ABSPATH' ) || exit;

class Helpers {

	/**
	 * Paginate results with standard parameters.
	 *
	 * @param array $input            Input with optional 'page' and 'per_page'.
	 * @param int   $default_per_page Default items per page.
	 * @return array [ 'page', 'per_page', 'offset' ]
	 */
	public static function pagination( $input, $default_per_page = 20 ) {
		return wp_abilities_pagination( $input, $default_per_page );
	}

	/**
	 * Standard pagination input schema properties.
	 *
	 * @return array
	 */
	public static function pagination_schema() {
		return wp_abilities_suite_pagination_schema();
	}

	/**
	 * Check if Pro tier is active.
	 *
	 * @return bool
	 */
	public static function is_pro() {
		return \WP_Abilities_Suite_License_Manager::is_pro_active();
	}

	/**
	 * Get current module permissions.
	 *
	 * @param string $module Module slug.
	 * @return array
	 */
	public static function get_permissions( $module ) {
		return wp_abilities_suite_get_permissions( $module );
	}
}
