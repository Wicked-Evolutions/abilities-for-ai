<?php
/**
 * Ability Registrar — namespaced canonical class.
 *
 * Eliminates boilerplate from ability module files. Auto-injects annotations,
 * permission callbacks, tier gating, and REST visibility from compact config.
 *
 * Usage:
 *   use WickedEvolutions\AbilitiesForAI\Core\Registrar;
 *   $reg = new Registrar( 'cron', 'manage_options' );
 *   $reg->read( 'cron/list-events', array(
 *       'label'         => 'List Cron Events',
 *       'description'   => '...',
 *       'input_schema'  => array( ... ),
 *       'output_schema' => array( ... ),
 *       'callback'      => function( $params ) { ... },
 *   ) );
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WickedEvolutions\AbilitiesForAI
 */

namespace WickedEvolutions\AbilitiesForAI\Core;

defined( 'ABSPATH' ) || exit;

class Registrar {

	/** @var string Module slug (e.g., 'cron', 'content'). */
	private $module;

	/** @var string Default WordPress capability for this module. */
	private $capability;

	/** @var array Permission toggles for this module (read/write/delete). */
	private $perms;

	/**
	 * @param string $module     Module slug.
	 * @param string $capability Default WordPress capability for this module.
	 */
	public function __construct( $module, $capability ) {
		$this->module     = $module;
		$this->capability = $capability;
		$this->perms      = abilities_for_ai_get_permissions( $module );
	}

	/**
	 * Register a read-only ability (readonly=true, destructive=false, idempotent=true, tier=free).
	 *
	 * @param string $name   Ability name.
	 * @param array  $config Compact config array.
	 */
	public function read( $name, $config ) {
		$this->register( $name, $config, 'read' );
	}

	/**
	 * Register a write ability (readonly=false, destructive=false, idempotent=true, tier=pro).
	 *
	 * @param string $name   Ability name.
	 * @param array  $config Compact config array.
	 */
	public function write( $name, $config ) {
		$config = array_merge( array( 'tier' => 'pro' ), $config );
		$this->register( $name, $config, 'write' );
	}

	/**
	 * Register a delete ability (readonly=false, destructive=true, idempotent=true, tier=pro).
	 *
	 * @param string $name   Ability name.
	 * @param array  $config Compact config array.
	 */
	public function delete( $name, $config ) {
		$config = array_merge( array( 'tier' => 'pro' ), $config );
		$this->register( $name, $config, 'delete' );
	}

	/**
	 * Internal: build the full wp_register_ability() args from compact config.
	 *
	 * Supported $config keys:
	 *   label, description, category, input_schema, output_schema, callback (required),
	 *   tier        — 'free' or 'pro' (default: 'free' for read, 'pro' for write/delete)
	 *   capability  — override the module's default WP capability
	 *   annotations — partial override of auto-generated annotations array
	 *
	 * @param string $name    Ability name.
	 * @param array  $config  Compact config array.
	 * @param string $op_type Operation type: 'read', 'write', or 'delete'.
	 */
	private function register( $name, $config, $op_type ) {
		$tier       = $config['tier'] ?? ( $op_type === 'read' ? 'free' : 'pro' );
		$capability = $config['capability'] ?? $this->capability;
		$callback   = $config['callback'];

		// Determine annotations from operation type.
		$annotations = array(
			'readonly'    => $op_type === 'read',
			'destructive' => $op_type === 'delete',
			'idempotent'  => true,
			'permission'  => $op_type,
		);

		// Allow per-ability annotation overrides.
		if ( isset( $config['annotations'] ) ) {
			$annotations = array_merge( $annotations, $config['annotations'] );
		}

		// Wrap with per-ability permission gate (checked at execution time).
		$ability_name = $name;
		$module       = $this->module;
		$original_cb  = $callback;
		$callback     = static function( $input = null ) use ( $original_cb, $ability_name, $module, $op_type ) {
			if ( ! abilities_for_ai_ability_enabled( $ability_name, $module, $op_type ) ) {
				$op_label = ucfirst( $op_type );
				return new \WP_Error(
					'ability_disabled',
					sprintf(
						'%s permission is disabled for the %s module. Enable it in Abilities for AI → Permissions.',
						$op_label,
						$module
					),
					array( 'status' => 403, 'ability' => $ability_name, 'module' => $module, 'operation' => $op_type )
				);
			}
			return $original_cb( $input );
		};

		// Wrap Pro callbacks with license gate.
		if ( $tier === 'pro' ) {
			$callback = abilities_for_ai_pro_gate( $name, $callback );
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
