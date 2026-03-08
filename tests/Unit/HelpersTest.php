<?php
/**
 * Unit Tests — Helper Functions
 *
 * Tests pure helper functions that have no WordPress database dependency.
 * Runs without WP_TESTS_DIR (unit mode via stubs).
 *
 * @package WordPress_Abilities_Suite\Tests\Unit
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

	// ── wp_abilities_suite_permission_defaults() ──────────────────────────────

	public function test_permission_defaults_returns_array() {
		$defaults = wp_abilities_suite_permission_defaults();
		$this->assertIsArray( $defaults );
		$this->assertArrayHasKey( 'content', $defaults );
		$this->assertArrayHasKey( 'cron', $defaults );
		$this->assertArrayHasKey( 'filesystem', $defaults );
	}

	public function test_permission_defaults_content_read_on() {
		$defaults = wp_abilities_suite_permission_defaults();
		$this->assertTrue( $defaults['content']['read'] );
	}

	public function test_permission_defaults_filesystem_write_off() {
		$defaults = wp_abilities_suite_permission_defaults();
		$this->assertFalse( $defaults['filesystem']['write'] );
	}

	public function test_permission_defaults_cron_read_only() {
		$defaults = wp_abilities_suite_permission_defaults();
		$this->assertTrue( $defaults['cron']['read'] );
		$this->assertArrayNotHasKey( 'write', $defaults['cron'] );
	}

	// ── wp_abilities_suite_get_permissions() ─────────────────────────────────

	public function test_get_permissions_merges_saved_with_defaults() {
		// The stubs return an empty option by default.
		$perms = wp_abilities_suite_get_permissions( 'content' );
		$this->assertArrayHasKey( 'read', $perms );
		$this->assertArrayHasKey( 'write', $perms );
		$this->assertArrayHasKey( 'delete', $perms );
	}

	public function test_get_permissions_unknown_module_defaults_to_read() {
		$perms = wp_abilities_suite_get_permissions( 'nonexistent-module' );
		$this->assertTrue( $perms['read'] );
		$this->assertArrayNotHasKey( 'write', $perms );
	}
}
