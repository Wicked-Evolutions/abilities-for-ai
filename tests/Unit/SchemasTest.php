<?php
/**
 * Unit Tests — Schema Helper Functions
 *
 * @package WordPress_Abilities_Suite\Tests\Unit
 */

use PHPUnit\Framework\TestCase;

class SchemasTest extends TestCase {

	// ── wp_abilities_suite_schema_pagination() ────────────────────────────────

	public function test_pagination_schema_returns_page_and_per_page() {
		$schema = wp_abilities_suite_schema_pagination();
		$this->assertArrayHasKey( 'page', $schema );
		$this->assertArrayHasKey( 'per_page', $schema );
	}

	public function test_pagination_schema_types_are_integer() {
		$schema = wp_abilities_suite_schema_pagination();
		$this->assertSame( 'integer', $schema['page']['type'] );
		$this->assertSame( 'integer', $schema['per_page']['type'] );
	}

	public function test_pagination_schema_per_page_maximum_100() {
		$schema = wp_abilities_suite_schema_pagination();
		$this->assertSame( 100, $schema['per_page']['maximum'] );
	}

	public function test_pagination_schema_custom_default_per_page() {
		$schema = wp_abilities_suite_schema_pagination( 50 );
		$this->assertSame( 50, $schema['per_page']['default'] );
	}

	// ── wp_abilities_suite_schema_post_type() ─────────────────────────────────

	public function test_post_type_schema_has_pattern() {
		$schema = wp_abilities_suite_schema_post_type();
		$this->assertArrayHasKey( 'pattern', $schema );
		$this->assertSame( '^[a-z0-9_-]+$', $schema['pattern'] );
	}

	public function test_post_type_schema_default_is_post() {
		$schema = wp_abilities_suite_schema_post_type();
		$this->assertSame( 'post', $schema['default'] );
	}

	public function test_post_type_schema_custom_description() {
		$schema = wp_abilities_suite_schema_post_type( 'Custom desc' );
		$this->assertSame( 'Custom desc', $schema['description'] );
	}

	// ── wp_abilities_suite_schema_success_output() ───────────────────────────

	public function test_success_output_schema_has_success_boolean() {
		if ( ! function_exists( 'wp_abilities_suite_schema_success_output' ) ) {
			$this->markTestSkipped( 'wp_abilities_suite_schema_success_output not available' );
		}
		$schema = wp_abilities_suite_schema_success_output();
		$this->assertArrayHasKey( 'properties', $schema );
		$this->assertArrayHasKey( 'success', $schema['properties'] );
		$this->assertSame( 'boolean', $schema['properties']['success']['type'] );
	}
}
