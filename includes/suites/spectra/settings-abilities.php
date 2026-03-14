<?php
/**
 * Spectra Suite — Settings & Patterns Abilities
 *
 * Read/write Spectra admin configuration and list registered patterns.
 *
 * Abilities:
 *   - spectra/get-settings    (read)  — P1
 *   - spectra/update-settings (write) — P2
 *   - spectra/list-patterns   (read)  — P2
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	if ( ! class_exists( 'UAGB_Loader' ) ) {
		return;
	}

	$reg = new Abilities_For_AI_Registrar( 'spectra', 'edit_posts' );

	// ===== GET SETTINGS =====

	$reg->read( 'spectra/get-settings', array(
		'label'        => 'Get Spectra Settings',
		'description'  => 'Read Spectra admin configuration: active/inactive blocks, performance settings, global defaults, and version info.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'section' => array(
					'type'        => 'string',
					'description' => 'Filter to a specific section: blocks, performance, or all (default: all)',
					'default'     => 'all',
					'enum'        => array( 'all', 'blocks', 'performance' ),
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'version'     => array( 'type' => 'string' ),
				'pro_active'  => array( 'type' => 'boolean' ),
				'sections'    => array( 'type' => 'object', 'additionalProperties' => true ),
			),
		),
		'callback' => function( $input ) {
			$section = $input['section'] ?? 'all';

			$version    = defined( 'UAGB_VER' ) ? UAGB_VER : 'unknown';
			$pro_active = defined( 'SPECTRA_PRO_VER' );

			$result = array(
				'version'    => $version,
				'pro_active' => $pro_active,
				'sections'   => array(),
			);

			// Block activation status.
			if ( 'all' === $section || 'blocks' === $section ) {
				$admin_settings = get_option( '_uagb_admin_settings', array() );
				$block_status   = array();

				if ( is_array( $admin_settings ) ) {
					foreach ( $admin_settings as $key => $value ) {
						if ( strpos( $key, 'uagb_' ) === 0 || strpos( $key, 'uagb-' ) === 0 ) {
							$block_status[ $key ] = $value;
						}
					}
				}

				// Also check the blocks option directly.
				$blocks_option = get_option( 'uagb_blocks', array() );
				if ( ! empty( $blocks_option ) && is_array( $blocks_option ) ) {
					$block_status['uagb_blocks'] = $blocks_option;
				}

				$result['sections']['blocks'] = array(
					'admin_settings' => $admin_settings,
					'active_blocks'  => $block_status,
				);
			}

			// Performance settings.
			if ( 'all' === $section || 'performance' === $section ) {
				$result['sections']['performance'] = array(
					'file_generation'    => get_option( '_uagb_allow_file_generation', 'enabled' ),
					'load_font_locally'  => get_option( 'uagb_load_gfonts_locally', 'disabled' ),
					'preload_local_fonts' => get_option( 'uagb_preload_local_fonts', 'disabled' ),
					'collapse_panels'    => get_option( 'uagb_collapse_panels', 'enabled' ),
					'copy_paste'         => get_option( 'uagb_copy_paste', 'enabled' ),
					'content_width'      => get_option( 'uagb_content_width', '' ),
					'container_padding'  => get_option( 'uagb_container_padding', '' ),
					'container_elements' => get_option( 'uagb_container_elements_gap', '' ),
				);
			}

			return $result;
		},
	));

	// ===== UPDATE SETTINGS =====

	$reg_admin = new Abilities_For_AI_Registrar( 'spectra', 'manage_options' );

	$reg_admin->write( 'spectra/update-settings', array(
		'label'        => 'Update Spectra Settings',
		'description'  => 'Modify Spectra global settings. Provide a settings object with key-value pairs. Only allowlisted keys are accepted.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'settings' ),
			'properties' => array(
				'settings' => array(
					'type'                 => 'object',
					'description'          => 'Key-value pairs of settings to update',
					'additionalProperties' => true,
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success' => array( 'type' => 'boolean' ),
				'updated' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'skipped' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
		),
		'callback' => function( $input ) {
			$settings = $input['settings'];

			// Allowlist of safe-to-modify Spectra options.
			$allowlist = array(
				'_uagb_allow_file_generation',
				'uagb_load_gfonts_locally',
				'uagb_preload_local_fonts',
				'uagb_collapse_panels',
				'uagb_copy_paste',
				'uagb_content_width',
				'uagb_container_padding',
				'uagb_container_elements_gap',
			);

			$updated = array();
			$skipped = array();

			foreach ( $settings as $key => $value ) {
				if ( in_array( $key, $allowlist, true ) ) {
					update_option( $key, sanitize_text_field( $value ) );
					$updated[] = $key;
				} else {
					$skipped[] = $key;
				}
			}

			if ( empty( $updated ) ) {
				return new WP_Error( 'nothing_updated', 'No valid settings keys provided. Allowlisted keys: ' . implode( ', ', $allowlist ) );
			}

			return array(
				'success' => true,
				'updated' => $updated,
				'skipped' => $skipped,
			);
		},
	));

	// ===== LIST PATTERNS =====

	$reg->read( 'spectra/list-patterns', array(
		'label'        => 'List Spectra Patterns',
		'description'  => 'List block patterns registered by Spectra (non-synced). Returns pattern names, titles, categories, and descriptions.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'category' => array(
					'type'        => 'string',
					'description' => 'Filter by pattern category slug',
				),
				'search' => array(
					'type'        => 'string',
					'description' => 'Search in pattern title and description',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'count'    => array( 'type' => 'integer' ),
				'patterns' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			),
		),
		'callback' => function( $input ) {
			$registry = WP_Block_Patterns_Registry::get_instance();
			$all      = $registry->get_all_registered();

			$spectra_patterns = array();
			$category_filter  = $input['category'] ?? '';
			$search           = strtolower( $input['search'] ?? '' );

			foreach ( $all as $pattern ) {
				// Filter to Spectra patterns: name starts with 'uagb/' or 'spectra/'.
				$name = $pattern['name'] ?? '';
				if ( strpos( $name, 'uagb/' ) !== 0 && strpos( $name, 'spectra/' ) !== 0 && strpos( $name, 'uagb-' ) !== 0 ) {
					continue;
				}

				// Category filter.
				if ( ! empty( $category_filter ) ) {
					$cats = $pattern['categories'] ?? array();
					if ( ! in_array( $category_filter, $cats, true ) ) {
						continue;
					}
				}

				// Search filter.
				if ( ! empty( $search ) ) {
					$title = strtolower( $pattern['title'] ?? '' );
					$desc  = strtolower( $pattern['description'] ?? '' );
					if ( strpos( $title, $search ) === false && strpos( $desc, $search ) === false ) {
						continue;
					}
				}

				$spectra_patterns[] = array(
					'name'        => $name,
					'title'       => $pattern['title'] ?? '',
					'description' => $pattern['description'] ?? '',
					'categories'  => $pattern['categories'] ?? array(),
					'keywords'    => $pattern['keywords'] ?? array(),
					'viewportWidth' => $pattern['viewportWidth'] ?? null,
				);
			}

			return array(
				'count'    => count( $spectra_patterns ),
				'patterns' => $spectra_patterns,
			);
		},
	));

});
