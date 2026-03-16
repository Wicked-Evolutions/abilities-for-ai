<?php
/**
 * Theme Enqueue Abilities
 *
 * Manages CSS/JS asset enqueuing via WordPress options — no PHP file writes needed.
 * The wp_enqueue_scripts hook handler runs on every page load independently of the
 * Abilities API. The abilities themselves are just CRUD on the option.
 *
 * Copyright (C) 2026 Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────────────────────
// INFRASTRUCTURE — runs on every frontend page load.
// ─────────────────────────────────────────────────────────────

/**
 * Evaluate whether an enqueue condition is met for the current request.
 *
 * @param array $condition { type: string, value: string }
 * @return bool
 */
function abilities_for_ai_evaluate_enqueue_condition( array $condition ): bool {
	$type  = $condition['type'] ?? '';
	$value = $condition['value'] ?? '';

	switch ( $type ) {
		case 'template':
			return is_page() && get_page_template_slug() === $value;
		case 'post_type':
			return is_singular( $value );
		case 'always':
			return true;
		default:
			return false;
	}
}

/**
 * Enqueue assets from the abilities_theme_enqueue_rules option.
 *
 * Fires on wp_enqueue_scripts — independent of the Abilities API.
 */
add_action( 'wp_enqueue_scripts', function() {
	$rules = get_option( 'abilities_theme_enqueue_rules', array() );
	if ( empty( $rules ) || ! is_array( $rules ) ) {
		return;
	}

	$theme_dir = get_stylesheet_directory();
	$theme_uri = get_stylesheet_directory_uri();

	foreach ( $rules as $rule ) {
		if ( empty( $rule['handle'] ) || empty( $rule['src'] ) || empty( $rule['type'] ) ) {
			continue;
		}

		$src  = $theme_uri . '/' . ltrim( $rule['src'], '/' );
		$file = $theme_dir . '/' . ltrim( $rule['src'], '/' );

		// File must exist on disk.
		if ( ! file_exists( $file ) ) {
			continue;
		}

		// Evaluate condition (if any).
		if ( ! empty( $rule['condition'] ) && is_array( $rule['condition'] ) ) {
			if ( ! abilities_for_ai_evaluate_enqueue_condition( $rule['condition'] ) ) {
				continue;
			}
		}

		// Version handling.
		$version = $rule['version'] ?? null;
		if ( $version === 'filemtime' ) {
			$version = filemtime( $file );
		}

		// Enqueue.
		if ( $rule['type'] === 'style' ) {
			wp_enqueue_style(
				$rule['handle'],
				$src,
				$rule['deps'] ?? array(),
				$version,
				$rule['media'] ?? 'all'
			);
		} elseif ( $rule['type'] === 'script' ) {
			wp_enqueue_script(
				$rule['handle'],
				$src,
				$rule['deps'] ?? array(),
				$version,
				$rule['in_footer'] ?? true
			);
		}
	}
} );

// ─────────────────────────────────────────────────────────────
// ABILITIES — CRUD on the enqueue rules option.
// ─────────────────────────────────────────────────────────────

add_action( 'wp_abilities_api_init', function() {
	$reg = new Abilities_For_AI_Registrar( 'themes', 'switch_themes' );

	// ── theme/enqueue-asset (write) ──
	$reg->write( 'themes/enqueue-asset', array(
		'label'       => 'Enqueue Theme Asset',
		'description' => 'Register a CSS or JS file from the active theme to be loaded on the frontend. Rules are stored in a WordPress option and persist across sessions. Idempotent — calling with the same handle updates the existing rule.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'handle', 'src', 'type' ),
			'properties' => array(
				'handle' => array(
					'type'        => 'string',
					'description' => 'Unique handle for the asset (e.g. "the-mirror-landing-page")',
				),
				'src' => array(
					'type'        => 'string',
					'description' => 'Path relative to active theme root (e.g. "assets/css/landing-page.css")',
				),
				'type' => array(
					'type'        => 'string',
					'enum'        => array( 'style', 'script' ),
					'description' => 'Asset type: style (CSS) or script (JS)',
				),
				'deps' => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Dependency handles. Optional.',
				),
				'version' => array(
					'type'        => 'string',
					'description' => 'Version string or "filemtime" for auto-versioning from file modification time. Default: "filemtime"',
				),
				'condition_type' => array(
					'type'        => 'string',
					'enum'        => array( 'template', 'post_type', 'always' ),
					'description' => 'When to load: "template" (specific page template), "post_type" (specific post type), "always" (every page). Default: "always"',
				),
				'condition_value' => array(
					'type'        => 'string',
					'description' => 'Value for the condition (template slug or post type name). Required unless condition_type is "always".',
				),
				'in_footer' => array(
					'type'        => 'boolean',
					'description' => 'Load script in footer. Scripts only. Default: true',
				),
				'media' => array(
					'type'        => 'string',
					'description' => 'Media attribute for stylesheets. Default: "all"',
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'rule'        => array( 'type' => 'object' ),
			'total_rules' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$input = (array) $input;

			$handle = sanitize_text_field( $input['handle'] );
			$src    = sanitize_text_field( $input['src'] );
			$type   = sanitize_text_field( $input['type'] );

			// Validate src: no traversal, must be relative.
			if ( strpos( $src, '..' ) !== false || strpos( $src, '/' ) === 0 ) {
				return new \WP_Error( 'ability_invalid_input', 'Source path must be relative to the theme root and cannot contain "..".' );
			}

			// Validate file exists.
			$theme_dir = get_stylesheet_directory();
			$file_path = realpath( $theme_dir . '/' . $src );

			if ( ! $file_path || strpos( $file_path, realpath( $theme_dir ) ) !== 0 ) {
				return new \WP_Error( 'ability_invalid_input', 'Source path resolves outside the active theme directory.' );
			}

			if ( ! file_exists( $file_path ) ) {
				return new \WP_Error( 'not_found', "File not found: {$src}" );
			}

			// Validate extension matches type.
			$ext = strtolower( pathinfo( $src, PATHINFO_EXTENSION ) );
			if ( $type === 'style' && $ext !== 'css' ) {
				return new \WP_Error( 'ability_invalid_input', 'Style assets must have a .css extension.' );
			}
			if ( $type === 'script' && $ext !== 'js' ) {
				return new \WP_Error( 'ability_invalid_input', 'Script assets must have a .js extension.' );
			}

			// Build rule.
			$rule = array(
				'handle'    => $handle,
				'src'       => $src,
				'type'      => $type,
				'deps'      => array_map( 'sanitize_text_field', $input['deps'] ?? array() ),
				'version'   => sanitize_text_field( $input['version'] ?? 'filemtime' ),
				'condition' => array(
					'type'  => sanitize_text_field( $input['condition_type'] ?? 'always' ),
					'value' => sanitize_text_field( $input['condition_value'] ?? '' ),
				),
				'in_footer' => (bool) ( $input['in_footer'] ?? true ),
				'media'     => sanitize_text_field( $input['media'] ?? 'all' ),
				'added_by'  => 'ability',
				'added_at'  => gmdate( 'c' ),
			);

			// Read existing rules.
			$rules = get_option( 'abilities_theme_enqueue_rules', array() );
			if ( ! is_array( $rules ) ) {
				$rules = array();
			}

			// Upsert: find by handle and update, or append.
			$found = false;
			foreach ( $rules as $i => $existing ) {
				if ( ( $existing['handle'] ?? '' ) === $handle ) {
					$rules[ $i ] = $rule;
					$found = true;
					break;
				}
			}
			if ( ! $found ) {
				$rules[] = $rule;
			}

			update_option( 'abilities_theme_enqueue_rules', $rules, false );

			return array(
				'success'     => true,
				'rule'        => $rule,
				'total_rules' => count( $rules ),
			);
		},
	) );

	// ── theme/dequeue-asset (delete) ──
	$reg->delete( 'themes/dequeue-asset', array(
		'label'       => 'Dequeue Theme Asset',
		'description' => 'Remove an enqueue rule by handle. The file remains on disk — only the enqueue registration is removed.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'handle' ),
			'properties' => array(
				'handle' => array(
					'type'        => 'string',
					'description' => 'Handle of the enqueue rule to remove',
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'handle'         => array( 'type' => 'string' ),
			'remaining_rules' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$input  = (array) $input;
			$handle = sanitize_text_field( $input['handle'] );

			$rules = get_option( 'abilities_theme_enqueue_rules', array() );
			if ( ! is_array( $rules ) ) {
				$rules = array();
			}

			$original_count = count( $rules );
			$rules = array_values( array_filter( $rules, function( $rule ) use ( $handle ) {
				return ( $rule['handle'] ?? '' ) !== $handle;
			} ) );

			if ( count( $rules ) === $original_count ) {
				return array(
					'success'         => true,
					'handle'          => $handle,
					'message'         => 'No rule found with that handle (already removed or never existed).',
					'remaining_rules' => count( $rules ),
				);
			}

			update_option( 'abilities_theme_enqueue_rules', $rules, false );

			return array(
				'success'         => true,
				'handle'          => $handle,
				'remaining_rules' => count( $rules ),
			);
		},
	) );

	// ── theme/list-enqueued-assets (read) ──
	$reg->read( 'themes/list-enqueued-assets', array(
		'label'       => 'List Enqueued Theme Assets',
		'description' => 'List all asset enqueue rules managed by the abilities system. Shows each rule with a file_exists check.',
		'output_schema' => abilities_for_ai_schema_collection_output( 'rules', array(
			'handle'      => array( 'type' => 'string' ),
			'src'         => array( 'type' => 'string' ),
			'type'        => array( 'type' => 'string' ),
			'file_exists' => array( 'type' => 'boolean' ),
			'condition'   => array( 'type' => 'object' ),
		) ),
		'callback' => function() {
			$rules     = get_option( 'abilities_theme_enqueue_rules', array() );
			$theme_dir = get_stylesheet_directory();

			if ( ! is_array( $rules ) ) {
				$rules = array();
			}

			$result = array();
			foreach ( $rules as $rule ) {
				$file = $theme_dir . '/' . ltrim( $rule['src'] ?? '', '/' );
				$rule['file_exists'] = file_exists( $file );
				$result[] = $rule;
			}

			return array(
				'total' => count( $result ),
				'rules' => $result,
			);
		},
	) );
} );
