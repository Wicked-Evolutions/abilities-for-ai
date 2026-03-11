<?php
/**
 * Backward-Compatible Aliases
 *
 * Provides `Abilities_For_AI_Registrar` as an alias for the namespaced
 * Registrar class. All existing module files that use
 * `new Abilities_For_AI_Registrar()` continue to work without modification
 * after PSR-4 adoption.
 *
 * Load order: After autoloader registration. Before module files.
 * *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WickedEvolutions\AbilitiesForAI
 */

defined( 'ABSPATH' ) || exit;

// Alias the namespaced Registrar to the legacy global class name.
if ( ! class_exists( 'Abilities_For_AI_Registrar' ) ) {
	class_alias(
		\WickedEvolutions\AbilitiesForAI\Core\Registrar::class,
		'Abilities_For_AI_Registrar'
	);
}
