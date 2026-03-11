<?php
/**
 * PHPUnit Bootstrap — Abilities for AI
 *
 * Two modes:
 *  1. Unit tests (no WP_TESTS_DIR) — loads stubs only. Fast, no database.
 *  2. Integration tests (WP_TESTS_DIR set) — loads WordPress test suite.
 *
 * To run integration tests locally:
 *   export WP_TESTS_DIR=/tmp/wordpress-tests-lib
 *   bin/install-wp-tests.sh abilities_test root '' localhost latest
 *   vendor/bin/phpunit --testsuite Integration
 */

define( 'ABILITIES_FOR_AI_TESTS', true );

$vendor_autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( file_exists( $vendor_autoload ) ) {
	require_once $vendor_autoload;
} else {
	// Dev clone without composer install — register manual autoloader.
	spl_autoload_register( function( $class ) {
		$prefix = 'WickedEvolutions\\AbilitiesForAI\\';
		if ( strpos( $class, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$file     = dirname( __DIR__ ) . '/src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	} );
}

$wp_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( $wp_tests_dir ) {
	// ── Integration mode: full WordPress environment ──────────────────────────
	if ( ! file_exists( $wp_tests_dir . '/includes/functions.php' ) ) {
		echo "ERROR: WP_TESTS_DIR ($wp_tests_dir) does not contain WordPress test suite.\n";
		echo "Run: bin/install-wp-tests.sh\n";
		exit( 1 );
	}

	// Load WordPress test functions.
	require_once $wp_tests_dir . '/includes/functions.php';

	// Load the plugin before WordPress initialises.
	tests_add_filter( 'muplugins_loaded', function() {
		require_once dirname( __DIR__ ) . '/abilities-for-ai.php';
	} );

	// Bootstrap WordPress.
	require_once $wp_tests_dir . '/includes/bootstrap.php';

} else {
	// ── Unit mode: stubs only, no WordPress ───────────────────────────────────
	require_once __DIR__ . '/stubs/wordpress-stubs.php';

	// Load plugin infrastructure that is safe without full WP.
	require_once dirname( __DIR__ ) . '/includes/helpers.php';
	require_once dirname( __DIR__ ) . '/includes/schemas.php';
	require_once dirname( __DIR__ ) . '/includes/compat.php';
}
