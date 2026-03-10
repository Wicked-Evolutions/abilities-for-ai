<?php
/**
 * Ability Registrar — eliminates boilerplate from ability module files.
 *
 * Usage in a module file:
 *   $reg = new WP_Abilities_Suite_Registrar( 'cron', 'manage_options' );
 *   $reg->read( 'cron/list-events', [
 *       'label'        => 'List Cron Events',
 *       'description'  => '...',
 *       'input_schema' => [...],
 *       'output_schema'=> [...],
 *       'callback'     => function( $params ) { ... },
 *   ]);
 *
 * @package WordPress_Abilities_Suite
 */

defined( 'ABSPATH' ) || exit;

class WP_Abilities_Suite_Registrar {

	private $module;
	private $capability;
	private $perms;

	/**
	 * @param string $module     Module slug (e.g., 'cron', 'content').
	 * @param string $capability Default WordPress capability for this module.
	 */
	public function __construct( $module, $capability ) {
		$this->module     = $module;
		$this->capability = $capability;
		$this->perms      = wp_abilities_suite_get_permissions( $module );
	}

	/**
	 * Register a read-only ability (readonly=true, destructive=false, idempotent=true, tier=free).
	 */
	public function read( $name, $config ) {
		$this->register( $name, $config, 'read' );
	}

	/**
	 * Register a write ability (readonly=false, destructive=false, idempotent=true, tier=pro).
	 */
	public function write( $name, $config ) {
		$config = array_merge( array( 'tier' => 'pro' ), $config );
		$this->register( $name, $config, 'write' );
	}

	/**
	 * Register a delete ability (readonly=false, destructive=true, idempotent=true, tier=pro).
	 */
	public function delete( $name, $config ) {
		$config = array_merge( array( 'tier' => 'pro' ), $config );
		$this->register( $name, $config, 'delete' );
	}

	/**
	 * Internal: build the full wp_register_ability() args from compact config.
	 */
	private function register( $name, $config, $op_type ) {
		$tier       = $config['tier'] ?? ( $op_type === 'read' ? 'free' : 'pro' );
		$capability = $config['capability'] ?? $this->capability;
		$callback   = $config['callback'];

		// Determine annotations from operation type.
		$permission_map = array( 'read' => 'read', 'write' => 'write', 'delete' => 'delete' );
		$annotations    = array(
			'readonly'    => $op_type === 'read',
			'destructive' => $op_type === 'delete',
			'idempotent'  => true,
			'permission'  => $permission_map[ $op_type ] ?? 'read',
		);

		// Allow annotation overrides (e.g., idempotent=false for non-idempotent writes).
		if ( isset( $config['annotations'] ) ) {
			$annotations = array_merge( $annotations, $config['annotations'] );
		}

		// Wrap with per-ability permission gate (checked at execution time).
		$ability_name = $name;
		$module       = $this->module;
		$original_cb  = $callback;
		$callback     = static function( $input = null ) use ( $original_cb, $ability_name, $module, $op_type ) {
			if ( ! wp_abilities_suite_ability_enabled( $ability_name, $module, $op_type ) ) {
				return new \WP_Error( 'ability_disabled', sprintf( 'Ability "%s" is disabled by permission settings.', $ability_name ), array( 'status' => 403 ) );
			}
			return $original_cb( $input );
		};

		// Wrap Pro callbacks with license gate.
		if ( $tier === 'pro' ) {
			$callback = wp_abilities_suite_pro_gate( $name, $callback );
		}

		// When no input_schema is defined, WP Core invokes the callback with zero arguments.
		// Wrap the callback so it accepts an optional $input without requiring one, preventing
		// "Too few arguments" fatals from callbacks declared as function( $input ) { ... }.
		$has_input_schema = ! empty( $config['input_schema'] );
		if ( ! $has_input_schema ) {
			$callback = static function( $input = null ) use ( $callback ) {
				return $callback( $input );
			};
		}

		$args = array(
			'label'               => $config['label'],
			'description'         => $config['description'],
			'category'            => $config['category'] ?? $this->module,
			'input_schema'        => $config['input_schema'] ?? array(),
			'execute_callback'    => $callback,
			'permission_callback' => function() use ( $capability ) {
				return current_user_can( $capability );
			},
			'meta' => array(
				'show_in_rest' => true,
				'mcp'          => array( 'public' => true, 'type' => 'tool' ),
				'annotations'  => $annotations,
				'tier'         => $tier,
			),
		);

		// Only include output_schema if provided.
		if ( isset( $config['output_schema'] ) ) {
			$args['output_schema'] = $config['output_schema'];
		}

		wp_register_ability( $name, $args );
	}
}
