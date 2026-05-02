<?php
/**
 * Unit Tests — Registrar Class
 *
 * Tests that the Registrar correctly builds wp_register_ability() calls,
 * injects annotations, respects tier/permission toggles, and that the
 * class alias (Abilities_For_AI_Registrar) resolves correctly.
 *
 * @package Abilities_For_AI\Tests\Unit
 */

use PHPUnit\Framework\TestCase;
use WickedEvolutions\AbilitiesForAI\Core\Registrar;

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
		$this->assertTrue( class_exists( 'Abilities_For_AI_Registrar' ) );
	}

	public function test_legacy_alias_is_same_as_namespaced() {
		$this->assertSame(
			Registrar::class,
			get_class( new \Abilities_For_AI_Registrar( 'cron', 'manage_options' ) )
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
		$_wp_options_store['abilities_for_ai_permissions'] = array(
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

	// ── input_schema default fallback (issue #135) ────────────────────────────

	public function test_no_arg_ability_default_schema_omits_properties() {
		$reg = new Registrar( 'cron', 'manage_options' );
		$reg->read( 'cron/list-events', array(
			'label' => 'T', 'description' => 'T', 'callback' => function() {},
		) );

		$abilities    = wp_get_abilities();
		$input_schema = $abilities['cron/list-events']['input_schema'];
		$this->assertSame( 'object', $input_schema['type'] );
		$this->assertArrayNotHasKey( 'properties', $input_schema );
	}

	// ── v0.6.0 (issue #123) — compiled + replaces meta ────────────────────────

	public function test_meta_compiled_flag_persisted_as_bool() {
		$reg = new Registrar( 'diagnostic', 'manage_options' );
		$reg->read( 'diagnostic/site-overview', array(
			'label' => 'T', 'description' => 'T', 'callback' => function() {},
			'compiled' => true,
			'replaces' => null,
		) );

		$abilities = wp_get_abilities();
		$meta = $abilities['diagnostic/site-overview']['meta'];
		$this->assertArrayHasKey( 'compiled', $meta );
		$this->assertTrue( $meta['compiled'] );
		$this->assertIsBool( $meta['compiled'] );
	}

	public function test_meta_compiled_false_is_persisted() {
		$reg = new Registrar( 'users', 'list_users' );
		$reg->read( 'users/list', array(
			'label' => 'T', 'description' => 'T', 'callback' => function() {},
			'compiled' => false,
			'replaces' => 'users.php',
		) );

		$abilities = wp_get_abilities();
		$meta = $abilities['users/list']['meta'];
		$this->assertArrayHasKey( 'compiled', $meta );
		$this->assertFalse( $meta['compiled'] );
	}

	public function test_meta_replaces_string_persisted() {
		$reg = new Registrar( 'users', 'list_users' );
		$reg->read( 'users/list', array(
			'label' => 'T', 'description' => 'T', 'callback' => function() {},
			'compiled' => false,
			'replaces' => 'users.php',
		) );

		$abilities = wp_get_abilities();
		$meta = $abilities['users/list']['meta'];
		$this->assertArrayHasKey( 'replaces', $meta );
		$this->assertSame( 'users.php', $meta['replaces'] );
	}

	public function test_meta_replaces_null_persisted() {
		$reg = new Registrar( 'editorial', 'manage_options' );
		$reg->read( 'editorial/site-voice', array(
			'label' => 'T', 'description' => 'T', 'callback' => function() {},
			'compiled' => true,
			'replaces' => null,
		) );

		$abilities = wp_get_abilities();
		$meta = $abilities['editorial/site-voice']['meta'];
		$this->assertArrayHasKey( 'replaces', $meta );
		$this->assertNull( $meta['replaces'] );
	}

	public function test_meta_compiled_omitted_when_not_provided() {
		$reg = new Registrar( 'cron', 'manage_options' );
		$reg->read( 'cron/list-events', array(
			'label' => 'T', 'description' => 'T', 'callback' => function() {},
		) );

		$abilities = wp_get_abilities();
		$meta = $abilities['cron/list-events']['meta'];
		$this->assertArrayNotHasKey( 'compiled', $meta );
		$this->assertArrayNotHasKey( 'replaces', $meta );
	}

}
