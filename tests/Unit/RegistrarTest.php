<?php
/**
 * Unit Tests — Registrar Class
 *
 * Tests that the Registrar correctly builds wp_register_ability() calls,
 * injects annotations, respects tier/permission toggles, and that the
 * class alias (WP_Abilities_Suite_Registrar) resolves correctly.
 *
 * @package WordPress_Abilities_Suite\Tests\Unit
 */

use PHPUnit\Framework\TestCase;
use WickedEvolutions\AbilitiesSuite\Core\Registrar;

class RegistrarTest extends TestCase {

	protected function setUp(): void {
		// Reset the global abilities registry between tests.
		global $_wp_registered_abilities, $_wp_options_store;
		$_wp_registered_abilities = array();
		// All perms ON by default (no saved overrides).
		$_wp_options_store = array();
	}

	// ── Class alias ───────────────────────────────────────────────────────────

	public function test_legacy_class_alias_exists() {
		$this->assertTrue( class_exists( 'WP_Abilities_Suite_Registrar' ) );
	}

	public function test_legacy_alias_is_same_as_namespaced() {
		$this->assertSame(
			Registrar::class,
			get_class( new \WP_Abilities_Suite_Registrar( 'cron', 'manage_options' ) )
		);
	}

	// ── read() ────────────────────────────────────────────────────────────────

	public function test_read_registers_ability() {
		$reg = new Registrar( 'cron', 'manage_options' );
		$reg->read( 'cron/list-events', array(
			'label'       => 'List Cron Events',
			'description' => 'Returns all scheduled cron events.',
			'callback'    => function() { return array(); },
		) );

		$abilities = wp_get_abilities();
		$this->assertArrayHasKey( 'cron/list-events', $abilities );
	}

	public function test_read_sets_readonly_annotation() {
		$reg = new Registrar( 'cron', 'manage_options' );
		$reg->read( 'cron/list-events', array(
			'label'       => 'Test',
			'description' => 'Test',
			'callback'    => function() {},
		) );

		$abilities = wp_get_abilities();
		$annotations = $abilities['cron/list-events']['meta']['annotations'];
		$this->assertTrue( $annotations['readonly'] );
		$this->assertFalse( $annotations['destructive'] );
	}

	public function test_read_sets_free_tier() {
		$reg = new Registrar( 'cron', 'manage_options' );
		$reg->read( 'cron/list-events', array(
			'label' => 'T', 'description' => 'T', 'callback' => function() {},
		) );

		$abilities = wp_get_abilities();
		$this->assertSame( 'free', $abilities['cron/list-events']['meta']['tier'] );
	}

	public function test_read_sets_show_in_rest() {
		$reg = new Registrar( 'cron', 'manage_options' );
		$reg->read( 'cron/list-events', array(
			'label' => 'T', 'description' => 'T', 'callback' => function() {},
		) );

		$abilities = wp_get_abilities();
		$this->assertTrue( $abilities['cron/list-events']['meta']['show_in_rest'] );
	}

	public function test_read_uses_module_as_default_category() {
		$reg = new Registrar( 'cron', 'manage_options' );
		$reg->read( 'cron/list-events', array(
			'label' => 'T', 'description' => 'T', 'callback' => function() {},
		) );

		$abilities = wp_get_abilities();
		$this->assertSame( 'cron', $abilities['cron/list-events']['category'] );
	}

	public function test_read_allows_category_override() {
		$reg = new Registrar( 'content', 'manage_options' );
		$reg->read( 'content/list-posts', array(
			'label' => 'T', 'description' => 'T', 'callback' => function() {},
			'category' => 'posts',
		) );

		$abilities = wp_get_abilities();
		$this->assertSame( 'posts', $abilities['content/list-posts']['category'] );
	}

	// ── write() ───────────────────────────────────────────────────────────────

	public function test_write_sets_pro_tier() {
		$reg = new Registrar( 'content', 'manage_options' );
		$reg->write( 'content/create-post', array(
			'label' => 'T', 'description' => 'T', 'callback' => function() {},
		) );

		$abilities = wp_get_abilities();
		$this->assertSame( 'pro', $abilities['content/create-post']['meta']['tier'] );
	}

	public function test_write_sets_write_annotation() {
		$reg = new Registrar( 'content', 'manage_options' );
		$reg->write( 'content/create-post', array(
			'label' => 'T', 'description' => 'T', 'callback' => function() {},
		) );

		$abilities = wp_get_abilities();
		$annotations = $abilities['content/create-post']['meta']['annotations'];
		$this->assertFalse( $annotations['readonly'] );
		$this->assertFalse( $annotations['destructive'] );
		$this->assertSame( 'write', $annotations['permission'] );
	}

	// ── delete() ─────────────────────────────────────────────────────────────

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_delete_sets_destructive_annotation() {
		// Must run in separate process to avoid static cache from earlier tests.
		// 'blocks' module has delete=false by default, but we override it here.
		global $_wp_options_store;
		$_wp_options_store['wp_abilities_suite_permissions'] = array(
			'media' => array( 'read' => true, 'write' => true, 'delete' => true ),
		);

		$reg = new Registrar( 'media', 'manage_options' );
		$reg->delete( 'media/delete-file', array(
			'label' => 'T', 'description' => 'T', 'callback' => function() {},
		) );

		$abilities = wp_get_abilities();
		$this->assertArrayHasKey( 'media/delete-file', $abilities );
		$annotations = $abilities['media/delete-file']['meta']['annotations'];
		$this->assertTrue( $annotations['destructive'] );
		$this->assertSame( 'delete', $annotations['permission'] );
	}

	// ── annotation overrides ──────────────────────────────────────────────────

	public function test_annotation_override_idempotent_false() {
		$reg = new Registrar( 'content', 'manage_options' );
		$reg->write( 'content/create-post', array(
			'label' => 'T', 'description' => 'T', 'callback' => function() {},
			'annotations' => array( 'idempotent' => false ),
		) );

		$abilities = wp_get_abilities();
		$this->assertFalse( $abilities['content/create-post']['meta']['annotations']['idempotent'] );
	}

	// ── output_schema ─────────────────────────────────────────────────────────

	public function test_output_schema_included_when_provided() {
		$reg = new Registrar( 'cron', 'manage_options' );
		$reg->read( 'cron/list-events', array(
			'label' => 'T', 'description' => 'T', 'callback' => function() {},
			'output_schema' => array( 'type' => 'object' ),
		) );

		$abilities = wp_get_abilities();
		$this->assertArrayHasKey( 'output_schema', $abilities['cron/list-events'] );
	}

	public function test_output_schema_omitted_when_not_provided() {
		$reg = new Registrar( 'cron', 'manage_options' );
		$reg->read( 'cron/list-events', array(
			'label' => 'T', 'description' => 'T', 'callback' => function() {},
		) );

		$abilities = wp_get_abilities();
		$this->assertArrayNotHasKey( 'output_schema', $abilities['cron/list-events'] );
	}

}
