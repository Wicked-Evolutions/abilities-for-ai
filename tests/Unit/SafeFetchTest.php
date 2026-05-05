<?php
/**
 * Unit Tests — SSRF-guarded safe-fetch helpers
 *
 * Covers the preflight portion of wp_abilities_prepare_safe_fetch():
 * scheme validation, host parsing, private-IP rejection (IPv4 + IPv6).
 *
 * The runtime portion (CURLOPT_RESOLVE pinning, redirect handling under
 * reject_unsafe_urls) requires real WP HTTP plumbing and is exercised by
 * the live wickedevolutions verification matrix tracked in the PR body.
 *
 * @package Abilities_For_AI\Tests\Unit
 */

use PHPUnit\Framework\TestCase;

class SafeFetchTest extends TestCase {

	// ── Scheme validation ────────────────────────────────────────────────────

	public function test_rejects_ftp_scheme() {
		$result = wp_abilities_prepare_safe_fetch( 'ftp://example.com/file' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	public function test_rejects_javascript_scheme() {
		$result = wp_abilities_prepare_safe_fetch( 'javascript:alert(1)' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	public function test_rejects_file_scheme() {
		$result = wp_abilities_prepare_safe_fetch( 'file:///etc/passwd' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	public function test_rejects_missing_scheme() {
		$result = wp_abilities_prepare_safe_fetch( 'example.com/file' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	// ── Direct private IP literals (preflight rejection) ─────────────────────

	public function test_rejects_loopback_ipv4_literal() {
		$result = wp_abilities_prepare_safe_fetch( 'http://127.0.0.1/x' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
	}

	public function test_rejects_link_local_metadata_ip() {
		$result = wp_abilities_prepare_safe_fetch( 'http://169.254.169.254/latest/meta-data/' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
	}

	public function test_rejects_rfc1918_10() {
		$result = wp_abilities_prepare_safe_fetch( 'https://10.0.0.5/admin' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
	}

	public function test_rejects_rfc1918_192() {
		$result = wp_abilities_prepare_safe_fetch( 'https://192.168.1.1/' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
	}

	public function test_rejects_rfc1918_172() {
		$result = wp_abilities_prepare_safe_fetch( 'http://172.16.0.1/' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
	}

	public function test_rejects_zero_network() {
		$result = wp_abilities_prepare_safe_fetch( 'http://0.0.0.0/' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
	}

	// ── IPv6 private literals ────────────────────────────────────────────────

	public function test_rejects_ipv6_loopback() {
		$result = wp_abilities_prepare_safe_fetch( 'http://[::1]/' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
	}

	public function test_rejects_ipv6_unique_local() {
		$result = wp_abilities_prepare_safe_fetch( 'http://[fc00::1]/' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
	}

	public function test_rejects_ipv4_mapped_loopback() {
		$result = wp_abilities_prepare_safe_fetch( 'http://[::ffff:127.0.0.1]/' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
	}

	// ── Host structure ───────────────────────────────────────────────────────

	public function test_rejects_empty_host() {
		$result = wp_abilities_prepare_safe_fetch( 'http:///path' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	// ── Public host accepted ─────────────────────────────────────────────────

	public function test_accepts_public_ip_literal() {
		// 8.8.8.8 is a public IP — preflight should pass.
		$result = wp_abilities_prepare_safe_fetch( 'https://8.8.8.8/' );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'filter', $result );
		$this->assertArrayHasKey( 'url', $result );
		$this->assertIsCallable( $result['filter'] );
		$this->assertSame( 'https://8.8.8.8/', $result['url'] );
	}

	public function test_filter_pins_curlopt_resolve_and_disables_native_redirects() {
		$result = wp_abilities_prepare_safe_fetch( 'https://8.8.8.8/' );
		$this->assertIsArray( $result );

		$args = ( $result['filter'] )( array() );
		$this->assertArrayHasKey( 'curl', $args );
		$this->assertArrayHasKey( CURLOPT_RESOLVE, $args['curl'] );
		$this->assertContains( '8.8.8.8:443:8.8.8.8', $args['curl'][ CURLOPT_RESOLVE ] );
		$this->assertContains( '8.8.8.8:80:8.8.8.8', $args['curl'][ CURLOPT_RESOLVE ] );
		$this->assertTrue( $args['reject_unsafe_urls'] );
		// Native redirects must be off — the helper does manual per-hop validation.
		$this->assertSame( 0, $args['redirection'] );
		$this->assertFalse( $args['curl'][ CURLOPT_FOLLOWLOCATION ] );
	}

	// ── DNS-rebind structural proof ──────────────────────────────────────────
	//
	// CURLOPT_RESOLVE pinning locks the IP at preflight time. Even if the
	// authoritative DNS record flips between preflight and the actual TCP
	// request (the textbook DNS-rebind attack), curl uses the pinned IP — the
	// rebind cannot pivot the connection. These tests use a fake resolver
	// (test-only seam on wp_abilities_prepare_safe_fetch) to model the rebind.

	public function test_rebind_pin_uses_first_resolution_only() {
		// Resolver returns a public IP first, then a private IP on subsequent calls
		// — modelling a DNS rebind between preflight and the actual fetch.
		$resolutions = array( '8.8.8.8', '127.0.0.1', '127.0.0.1' );
		$resolver = function ( $host ) use ( &$resolutions ) {
			return array_shift( $resolutions ) ?? $host;
		};

		$prep = wp_abilities_prepare_safe_fetch( 'https://test.example/path', $resolver );
		$this->assertIsArray( $prep, 'Preflight should accept (first resolution was public).' );

		// The pinned IP must be the first resolution (8.8.8.8), not a subsequent one.
		$args = ( $prep['filter'] )( array() );
		$this->assertContains( 'test.example:443:8.8.8.8', $args['curl'][ CURLOPT_RESOLVE ] );
		$this->assertContains( 'test.example:80:8.8.8.8',  $args['curl'][ CURLOPT_RESOLVE ] );

		// Crucially, the private IPs queued behind the first one were never
		// consulted — the closure captures the preflight result by value.
		$this->assertSame(
			array( '127.0.0.1', '127.0.0.1' ),
			$resolutions,
			'Resolver should only have been called once during preflight; rebind targets remain in queue.'
		);
	}

	public function test_rebind_caught_when_preflight_resolves_to_private() {
		// If the rebind happens to land on the preflight call instead of the
		// fetch call, the private-IP rejection at preflight blocks the request
		// before any HTTP traffic. Together with the pin-at-preflight test
		// above, this covers both halves of the rebind defense.
		$resolver = function ( $host ) { return '127.0.0.1'; };

		$result = wp_abilities_prepare_safe_fetch( 'https://test.example/', $resolver );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
	}
}
