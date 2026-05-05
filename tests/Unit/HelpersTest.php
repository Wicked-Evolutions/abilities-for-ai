<?php
/**
 * Unit Tests — Helper Functions
 *
 * Tests pure helper functions that have no WordPress database dependency.
 * Runs without WP_TESTS_DIR (unit mode via stubs).
 *
 * @package Abilities_For_AI\Tests\Unit
 */

use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase {

	// ── wp_abilities_pagination() ─────────────────────────────────────────────

	public function test_pagination_defaults() {
		$result = wp_abilities_pagination( array() );
		$this->assertSame( 1, $result['page'] );
		$this->assertSame( 20, $result['per_page'] );
		$this->assertSame( 0, $result['offset'] );
	}

	public function test_pagination_page_2() {
		$result = wp_abilities_pagination( array( 'page' => 2, 'per_page' => 10 ) );
		$this->assertSame( 2, $result['page'] );
		$this->assertSame( 10, $result['per_page'] );
		$this->assertSame( 10, $result['offset'] );
	}

	public function test_pagination_clamps_per_page_to_100() {
		$result = wp_abilities_pagination( array( 'per_page' => 999 ) );
		$this->assertSame( 100, $result['per_page'] );
	}

	public function test_pagination_clamps_per_page_min_to_1() {
		$result = wp_abilities_pagination( array( 'per_page' => 0 ) );
		$this->assertSame( 1, $result['per_page'] );
	}

	public function test_pagination_clamps_page_min_to_1() {
		$result = wp_abilities_pagination( array( 'page' => -5 ) );
		$this->assertSame( 1, $result['page'] );
	}

	public function test_pagination_custom_default_per_page() {
		$result = wp_abilities_pagination( array(), 50 );
		$this->assertSame( 50, $result['per_page'] );
	}

	public function test_pagination_offset_calculation() {
		$result = wp_abilities_pagination( array( 'page' => 3, 'per_page' => 25 ) );
		$this->assertSame( 50, $result['offset'] );
	}

	// ── wp_abilities_error() ──────────────────────────────────────────────────

	public function test_error_returns_wp_error() {
		$error = wp_abilities_error( 'test_code', 'Test message' );
		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertSame( 'test_code', $error->get_error_code() );
		$this->assertSame( 'Test message', $error->get_error_message() );
	}

	// ── abilities_for_ai_module_prefix_map() ──────────────────────────────────

	public function test_module_prefix_map_matches_defaults_keys() {
		$map      = abilities_for_ai_module_prefix_map();
		$defaults = abilities_for_ai_permission_defaults();
		$this->assertSame(
			array_keys( $defaults ),
			array_keys( $map ),
			'Prefix map keys must match permission_defaults() keys exactly — that is the introspection contract that keeps the sanitizer / counts / enabled-count in sync.'
		);
		foreach ( $map as $key => $value ) {
			$this->assertSame( $key, $value, "Identity convention violated for module {$key}: prefix !== module slug." );
		}
	}

	public function test_module_prefix_map_includes_v1_9_2_added_modules() {
		$map = abilities_for_ai_module_prefix_map();
		$must_be_present = array( 'knowledge', 'diagnostic', 'editorial', 'astra', 'presto-player', 'spectra', 'surecart' );
		foreach ( $must_be_present as $module ) {
			$this->assertArrayHasKey(
				$module,
				$map,
				"Module '{$module}' must be in the prefix map; it was missing in v1.9.1's hardcoded sanitizer (#145)."
			);
		}
	}

	// ── wp_abilities_is_private_ip() ──────────────────────────────────────────

	public function test_private_ip_loopback() {
		$this->assertTrue( wp_abilities_is_private_ip( '127.0.0.1' ) );
	}

	public function test_private_ip_rfc1918_10() {
		$this->assertTrue( wp_abilities_is_private_ip( '10.0.0.1' ) );
	}

	public function test_private_ip_rfc1918_172() {
		$this->assertTrue( wp_abilities_is_private_ip( '172.16.0.1' ) );
	}

	public function test_private_ip_rfc1918_192() {
		$this->assertTrue( wp_abilities_is_private_ip( '192.168.1.1' ) );
	}

	public function test_public_ip_not_private() {
		$this->assertFalse( wp_abilities_is_private_ip( '8.8.8.8' ) );
	}

	public function test_public_ip_not_private_2() {
		$this->assertFalse( wp_abilities_is_private_ip( '1.1.1.1' ) );
	}

	// ── abilities_for_ai_permission_defaults() ──────────────────────────────

	public function test_permission_defaults_returns_array() {
		$defaults = abilities_for_ai_permission_defaults();
		$this->assertIsArray( $defaults );
		$this->assertArrayHasKey( 'content', $defaults );
		$this->assertArrayHasKey( 'cron', $defaults );
		$this->assertArrayHasKey( 'filesystem', $defaults );
	}

	public function test_permission_defaults_content_read_on() {
		$defaults = abilities_for_ai_permission_defaults();
		$this->assertTrue( $defaults['content']['read'] );
	}

	public function test_permission_defaults_filesystem_write_on() {
		// Public-alpha posture (J's Answer A, 2026-04-25): filesystem-write
		// stays enabled by default. Operators handle compliance via the
		// per-ability permission settings UI; the alpha trusts early users
		// to know what they're doing. Visibility is the safety surface, not
		// closed defaults.
		$defaults = abilities_for_ai_permission_defaults();
		$this->assertTrue( $defaults['filesystem']['write'] );
	}

	public function test_permission_defaults_cron_write_on() {
		// Public-alpha posture (J's Answer A, 2026-04-25): cron-write stays
		// enabled by default. Same reasoning as filesystem-write above.
		$defaults = abilities_for_ai_permission_defaults();
		$this->assertTrue( $defaults['cron']['read'] );
		$this->assertTrue( $defaults['cron']['write'] );
	}

	// ── abilities_for_ai_get_permissions() ─────────────────────────────────

	public function test_get_permissions_merges_saved_with_defaults() {
		// The stubs return an empty option by default.
		$perms = abilities_for_ai_get_permissions( 'content' );
		$this->assertArrayHasKey( 'read', $perms );
		$this->assertArrayHasKey( 'write', $perms );
		$this->assertArrayHasKey( 'delete', $perms );
	}

	public function test_get_permissions_unknown_module_defaults_to_read() {
		$perms = abilities_for_ai_get_permissions( 'nonexistent-module' );
		$this->assertTrue( $perms['read'] );
		$this->assertArrayNotHasKey( 'write', $perms );
	}
}
