<?php
/**
 * Knowledge Layer — REST API Registration.
 *
 * Registers all KL REST controllers under the abilities-kl/v1 namespace.
 * Controllers are thin wrappers that delegate to shared KL Model classes.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

use WickedEvolutions\AbilitiesForAI\Knowledge\REST\DocumentsController;
use WickedEvolutions\AbilitiesForAI\Knowledge\REST\SessionsController;
use WickedEvolutions\AbilitiesForAI\Knowledge\REST\ObservationsController;
use WickedEvolutions\AbilitiesForAI\Knowledge\REST\TagsController;
use WickedEvolutions\AbilitiesForAI\Knowledge\REST\DashboardController;
use WickedEvolutions\AbilitiesForAI\Knowledge\REST\ActivityController;
use WickedEvolutions\AbilitiesForAI\Knowledge\REST\BoundaryController;
use WickedEvolutions\AbilitiesForAI\Knowledge\Schema;

add_action( 'rest_api_init', function() {

	// Only register if Knowledge Layer tables exist.
	if ( ! Schema::tables_exist() ) {
		return;
	}

	$controllers = array(
		new DocumentsController(),
		new SessionsController(),
		new ObservationsController(),
		new TagsController(),
		new DashboardController(),
		new ActivityController(),
		new BoundaryController(),
	);

	foreach ( $controllers as $controller ) {
		$controller->register_routes();
	}
} );
