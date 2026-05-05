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

	public function test_filter_pins_curlopt_resolve() {
		$result = wp_abilities_prepare_safe_fetch( 'https://8.8.8.8/' );
		$this->assertIsArray( $result );

		$args = ( $result['filter'] )( array() );
		$this->assertArrayHasKey( 'curl', $args );
		$this->assertArrayHasKey( CURLOPT_RESOLVE, $args['curl'] );
		$this->assertContains( '8.8.8.8:443:8.8.8.8', $args['curl'][ CURLOPT_RESOLVE ] );
		$this->assertContains( '8.8.8.8:80:8.8.8.8', $args['curl'][ CURLOPT_RESOLVE ] );
		$this->assertTrue( $args['reject_unsafe_urls'] );
		$this->assertSame( 3, $args['redirection'] );
	}
}
