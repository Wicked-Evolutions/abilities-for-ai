/**
 * Knowledge Layer — App Entry Point
 *
 * Placeholder for the Vue SPA build (Issue #65).
 * The abilitiesKL global is available via wp_localize_script.
 */
( function() {
	'use strict';
	if ( window.abilitiesKL ) {
		// eslint-disable-next-line no-console
		console.log( 'Knowledge Layer shell loaded', abilitiesKL.version );
	}
} )();
