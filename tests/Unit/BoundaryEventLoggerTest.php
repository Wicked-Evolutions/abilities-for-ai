<?php
/**
 * Unit Tests — BoundaryEventLogger
 *
 * Covers the pure parts of BoundaryEventLogger: event-name allowlist
 * gating, IP truncation, and the detail-JSON allowlist filter.
 * The DB-dependent persist() path is exercised by integration tests.
 *
 * @package Abilities_For_AI\Tests\Unit
 */

use PHPUnit\Framework\TestCase;
use WickedEvolutions\AbilitiesForAI\Knowledge\BoundaryEventLogger;

// Stubs for functions used inside the writer that aren't in the base
// stubs file. Kept local to this test so we don't pollute other tests.
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value, $flags = 0, $depth = 512 ) {
		return json_encode( $value, $flags, $depth );
	}
}
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type = 'mysql', $gmt = 0 ) {
		return gmdate( 'Y-m-d H:i:s' );
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		return $value;
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_-]/i', '', (string) $key );
	}
}

class BoundaryEventLoggerTest extends TestCase {

	public function test_event_severity_table_lists_v01_taxonomy() {
		$expected = array(
			'boundary.session.init',
			'boundary.session.terminated',
			'boundary.auth.denied',
			'boundary.transport.error',
			'boundary.rate_limit_hit',
		);
		$this->assertSame(
			$expected,
			array_keys( BoundaryEventLogger::EVENT_SEVERITY ),
			'v0.1 event taxonomy must be exactly these five — no additions, no drops.'
		);
	}

	public function test_record_event_ignores_non_boundary_events() {
		$logger = new BoundaryEventLogger();
		// Should silently no-op without touching $wpdb (which is null in unit mode).
		$logger->record_event( 'mcp.request', array( 'method' => 'tools/list' ) );
		$logger->record_event( 'auth.success', array( 'user_id' => 1 ) );
		$logger->record_event( '', array() );
		$this->assertTrue( true ); // No exception means the gate held.
	}

	public function test_record_event_ignores_unknown_boundary_events() {
		$logger = new BoundaryEventLogger();
		// Unknown boundary.* event — must drop, not insert.
		// Without $wpdb in unit mode, persist would throw if it reached the
		// table check, so a clean return proves the allowlist held.
		$logger->record_event( 'boundary.something.new', array() );
		$this->assertTrue( true );
	}

	// ── IP truncation ────────────────────────────────────────────────────

	public function test_truncate_ipv4_zeros_last_octet() {
		$this->assertSame( '203.0.113.0', BoundaryEventLogger::truncate_ip( '203.0.113.42' ) );
	}

	public function test_truncate_ipv4_already_truncated() {
		$this->assertSame( '10.0.0.0', BoundaryEventLogger::truncate_ip( '10.0.0.0' ) );
	}

	public function test_truncate_ipv6_zeros_past_48_bits() {
		// 2001:db8::1 has prefix 2001:0db8:0000 in /48 — everything past zeroed.
		$out = BoundaryEventLogger::truncate_ip( '2001:db8::1' );
		$this->assertNotEmpty( $out );
		$this->assertSame( '2001:db8::', $out );
	}

	public function test_truncate_invalid_ip_returns_empty() {
		$this->assertSame( '', BoundaryEventLogger::truncate_ip( 'not-an-ip' ) );
		$this->assertSame( '', BoundaryEventLogger::truncate_ip( '' ) );
		$this->assertSame( '', BoundaryEventLogger::truncate_ip( '999.999.999.999' ) );
	}

	// ── detail_json allowlist ────────────────────────────────────────────

	public function test_build_detail_json_allowlists_safe_fields() {
		$reflect = new ReflectionMethod( BoundaryEventLogger::class, 'build_detail_json' );
		$reflect->setAccessible( true );

		$json = $reflect->invoke( null, array(
			'name'            => 'tools/call',
			'protocolVersion' => '2025-06-18',
			'arguments_count' => 3,
			'reason'          => 'permission denied',
		) );

		$this->assertNotNull( $json );
		$decoded = json_decode( $json, true );
		$this->assertSame( 'tools/call', $decoded['name'] );
		$this->assertSame( '2025-06-18', $decoded['protocolVersion'] );
		$this->assertSame( 3, $decoded['arguments_count'] );
		$this->assertSame( 'permission denied', $decoded['reason'] );
	}

	public function test_build_detail_json_drops_non_allowlisted_keys() {
		$reflect = new ReflectionMethod( BoundaryEventLogger::class, 'build_detail_json' );
		$reflect->setAccessible( true );

		// These would carry sensitive content (email, password, raw params)
		// even if they slipped past upstream sanitization. The allowlist
		// must drop them before writing to detail_json.
		$json = $reflect->invoke( null, array(
			'name'           => 'users/list',
			'email'          => 'jacob@willow.se',
			'password'       => 'should-never-leak',
			'access_token'   => 'sk_live_xyz',
			'arguments'      => array( 'email' => 'leak@example.com' ),
			'response_body'  => 'big leak',
			'arguments_count' => 5,
		) );

		$this->assertNotNull( $json );
		$decoded = json_decode( $json, true );
		$this->assertSame( 'users/list', $decoded['name'] );
		$this->assertSame( 5, $decoded['arguments_count'] );
		$this->assertArrayNotHasKey( 'email', $decoded );
		$this->assertArrayNotHasKey( 'password', $decoded );
		$this->assertArrayNotHasKey( 'access_token', $decoded );
		$this->assertArrayNotHasKey( 'arguments', $decoded );
		$this->assertArrayNotHasKey( 'response_body', $decoded );
	}

	public function test_build_detail_json_returns_null_when_empty_after_filter() {
		$reflect = new ReflectionMethod( BoundaryEventLogger::class, 'build_detail_json' );
		$reflect->setAccessible( true );

		$json = $reflect->invoke( null, array( 'email' => 'a@b.com', 'password' => 'x' ) );
		$this->assertNull( $json );

		$json = $reflect->invoke( null, array() );
		$this->assertNull( $json );
	}

	public function test_build_detail_json_drops_non_scalar_array_elements() {
		$reflect = new ReflectionMethod( BoundaryEventLogger::class, 'build_detail_json' );
		$reflect->setAccessible( true );

		$json = $reflect->invoke( null, array(
			'arguments_keys' => array( 'email', new stdClass(), array( 'nested' ), 'phone' ),
		) );

		$decoded = json_decode( $json, true );
		// Only the two scalar strings make it through.
		$this->assertSame( array( 'email', 'phone' ), $decoded['arguments_keys'] );
	}

	public function test_build_detail_json_caps_oversized_payload() {
		$reflect = new ReflectionMethod( BoundaryEventLogger::class, 'build_detail_json' );
		$reflect->setAccessible( true );

		// reason is allowlisted; stuff it with a huge string.
		$big  = str_repeat( 'A', 10000 );
		$json = $reflect->invoke( null, array( 'reason' => $big ) );

		$this->assertNotNull( $json );
		$this->assertLessThanOrEqual( 4096, strlen( $json ) );
	}
}
