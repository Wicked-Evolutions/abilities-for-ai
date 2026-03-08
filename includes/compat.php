<?php
/**
 * Backward-Compatible Aliases
 *
 * Provides `WP_Abilities_Suite_Registrar` as an alias for the namespaced
 * Registrar class. All existing module files that use
 * `new WP_Abilities_Suite_Registrar()` continue to work without modification
 * after PSR-4 adoption.
 *
 * Load order: After autoloader registration. Before module files.
 *
 * @package WickedEvolutions\AbilitiesSuite
 */

defined( 'ABSPATH' ) || exit;

// Alias the namespaced Registrar to the legacy global class name.
if ( ! class_exists( 'WP_Abilities_Suite_Registrar' ) ) {
	class_alias(
		\WickedEvolutions\AbilitiesSuite\Core\Registrar::class,
		'WP_Abilities_Suite_Registrar'
	);
}
