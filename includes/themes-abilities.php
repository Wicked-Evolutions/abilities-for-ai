<?php
/**
 * Themes Abilities
 *
 * Read-only theme listing, mods, and theme.json access for V1.0.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {
	$reg = new Abilities_For_AI_Registrar( 'themes', 'switch_themes' );

	$reg->read( 'themes/list', array(
		'label'       => 'List Themes',
		'compiled'    => false,
		'replaces'    => 'themes.php',
		'description' => 'List all installed themes with version, status, and capabilities.',
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'themes' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'total'  => array( 'type' => 'integer' ),
			'active' => array( 'type' => 'string' ),
		) ),
		'callback' => function() {
			$themes = wp_get_themes();
			$active = get_stylesheet();
			$result = array();

			foreach ( $themes as $slug => $theme ) {
				$result[] = array(
					'slug'        => $slug,
					'name'        => $theme->get( 'Name' ),
					'version'     => $theme->get( 'Version' ),
					'author'      => $theme->get( 'Author' ),
					'active'      => ( $slug === $active ),
					'parent'      => $theme->parent() ? $theme->parent()->get_stylesheet() : null,
					'block_theme' => $theme->is_block_theme(),
					'template'    => $theme->get_template(),
				);
			}

			return array( 'total' => count( $result ), 'themes' => $result, 'active' => $active );
		},
	));

	$reg->read( 'themes/get-active', array(
		'label'       => 'Get Active Theme',
		'compiled'    => false,
		'replaces'    => 'themes.php',
		'description' => 'Get detailed information about the currently active theme.',
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'name'        => array( 'type' => 'string' ),
			'slug'        => array( 'type' => 'string' ),
			'version'     => array( 'type' => 'string' ),
			'author'      => array( 'type' => 'string' ),
			'block_theme' => array( 'type' => 'boolean' ),
		) ),
		'callback' => function() {
			$theme = wp_get_theme();
			return array(
				'name'         => $theme->get( 'Name' ),
				'slug'         => $theme->get_stylesheet(),
				'version'      => $theme->get( 'Version' ),
				'author'       => $theme->get( 'Author' ),
				'author_uri'   => $theme->get( 'AuthorURI' ),
				'description'  => $theme->get( 'Description' ),
				'template'     => $theme->get_template(),
				'parent'       => $theme->parent() ? $theme->parent()->get( 'Name' ) : null,
				'block_theme'  => $theme->is_block_theme(),
				'theme_root'   => $theme->get_theme_root(),
				'text_domain'  => $theme->get( 'TextDomain' ),
				'tags'         => $theme->get( 'Tags' ),
				'requires_wp'  => $theme->get( 'RequiresWP' ),
				'requires_php' => $theme->get( 'RequiresPHP' ),
			);
		},
	));

	// list-mods and get-mod require edit_theme_options — override the module-level capability.
	$reg->read( 'themes/list-mods', array(
		'label'       => 'List Theme Mods',
		'compiled'    => false,
		'replaces'    => 'themes.php',
		'description' => 'List all theme modifications for the active theme.',
		'capability'  => 'edit_theme_options',
		'output_schema' => abilities_for_ai_schema_collection_output( 'mods', array(
			'key'   => array( 'type' => 'string' ),
			'value' => array( 'type' => 'string', 'description' => 'Mod value (may be string, array, or serialized data)' ),
			'type'  => array( 'type' => 'string' ),
		) ),
		'callback' => function() {
			$mods = get_theme_mods();
			if ( ! is_array( $mods ) ) {
				$mods = array();
			}
			$result = array();
			foreach ( $mods as $key => $value ) {
				$result[] = array(
					'key'   => (string) $key,
					'value' => is_scalar( $value ) ? (string) $value : wp_json_encode( $value ),
					'type'  => gettype( $value ),
				);
			}
			return array( 'theme' => get_stylesheet(), 'total' => count( $result ), 'mods' => $result );
		},
	));

	$reg->read( 'themes/get-mod', array(
		'label'       => 'Get Theme Mod',
		'compiled'    => false,
		'replaces'    => 'themes.php',
		'description' => 'Get a specific theme modification value.',
		'capability'  => 'edit_theme_options',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'name' => array( 'type' => 'string', 'description' => 'Theme mod name' ),
			),
			'required' => array( 'name' ),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'name'  => array( 'type' => 'string' ),
			'value' => array( 'type' => 'string', 'description' => 'Mod value (may be string, array, or serialized data)' ),
		) ),
		'callback' => function( $params ) {
			$name  = sanitize_text_field( $params['name'] ?? '' );
			$value = get_theme_mod( $name, '__NOT_SET__' );
			if ( $value === '__NOT_SET__' ) {
				return wp_abilities_error( 'not_found', "Theme mod '{$name}' not found." );
			}
			return array( 'name' => $name, 'value' => is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) );
		},
	));

	$reg->read( 'themes/get-theme-json', array(
		'label'       => 'Get Theme JSON',
		'compiled'    => false,
		'replaces'    => 'themes.php',
		'description' => 'Get the merged theme.json data for the active block theme. Returns settings and styles.',
		'capability'  => 'edit_theme_options',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'section' => array(
					'type'        => 'string',
					'description' => 'Specific section to return: settings, styles, or customTemplates. Omit for full data.',
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'theme'   => array( 'type' => 'string' ),
			'section' => array( 'type' => 'string' ),
			'data'    => array( 'type' => 'object' ),
		) ),
		'callback' => function( $params ) {
			if ( ! class_exists( 'WP_Theme_JSON_Resolver' ) ) {
				return wp_abilities_error( 'ability_invalid_input', 'theme.json is not available (requires block theme or WP 5.8+).' );
			}

			$theme = wp_get_theme();
			if ( ! $theme->is_block_theme() && ! file_exists( $theme->get_theme_root() . '/' . $theme->get_stylesheet() . '/theme.json' ) ) {
				if ( ! file_exists( get_stylesheet_directory() . '/theme.json' ) ) {
					return wp_abilities_error( 'not_found', 'Active theme does not have a theme.json file.' );
				}
			}

			$merged = WP_Theme_JSON_Resolver::get_merged_data();
			$data   = $merged->get_raw_data();

			if ( ! empty( $params['section'] ) ) {
				$section = sanitize_text_field( $params['section'] );
				if ( ! isset( $data[ $section ] ) ) {
					return wp_abilities_error( 'not_found', "Section '{$section}' not found. Available: " . implode( ', ', array_keys( $data ) ) );
				}
				return array( 'section' => $section, 'data' => $data[ $section ] );
			}

			return array( 'theme' => $theme->get( 'Name' ), 'data' => $data );
		},
	));

	$reg->read( 'themes/design-snapshot', array(
		'label'       => 'Design Snapshot',
		'compiled'    => false,
		'replaces'    => 'themes.php',
		'description' => 'Single-call design overview: active theme info, theme.json settings (colors, typography, spacing, layout), custom CSS, template list, and global styles. Everything an AI needs to understand the site\'s visual identity without multiple tool calls.',
		'capability'  => 'edit_theme_options',
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'theme'      => array( 'type' => 'object' ),
			'colors'     => array( 'type' => 'object' ),
			'typography' => array( 'type' => 'object' ),
			'spacing'    => array( 'type' => 'object' ),
			'layout'     => array( 'type' => 'object' ),
			'custom_css' => array( 'type' => 'string' ),
			'templates'  => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function() {
			$theme_obj = wp_get_theme();

			// Active theme summary.
			$theme = array(
				'name'        => $theme_obj->get( 'Name' ),
				'slug'        => $theme_obj->get_stylesheet(),
				'version'     => $theme_obj->get( 'Version' ),
				'parent'      => $theme_obj->parent() ? $theme_obj->parent()->get( 'Name' ) : null,
				'block_theme' => $theme_obj->is_block_theme(),
			);

			// Theme.json data (merged).
			$colors     = array();
			$typography = array();
			$spacing    = array();
			$layout     = array();

			if ( class_exists( 'WP_Theme_JSON_Resolver' ) ) {
				$merged = WP_Theme_JSON_Resolver::get_merged_data();
				$data   = $merged->get_raw_data();

				$settings = $data['settings'] ?? array();
				$styles   = $data['styles'] ?? array();

				// Colors: palette + any style-level color settings.
				$colors = array(
					'palette' => $settings['color']['palette'] ?? array(),
					'gradients' => $settings['color']['gradients'] ?? array(),
					'background' => $styles['color']['background'] ?? null,
					'text'       => $styles['color']['text'] ?? null,
				);

				// Typography: font families, sizes, and style defaults.
				$typography = array(
					'fontFamilies' => $settings['typography']['fontFamilies'] ?? array(),
					'fontSizes'    => $settings['typography']['fontSizes'] ?? array(),
					'lineHeight'   => $styles['typography']['lineHeight'] ?? null,
					'fontFamily'   => $styles['typography']['fontFamily'] ?? null,
					'fontSize'     => $styles['typography']['fontSize'] ?? null,
				);

				// Spacing.
				$spacing = array(
					'spacingSizes' => $settings['spacing']['spacingSizes'] ?? array(),
					'units'        => $settings['spacing']['units'] ?? array(),
					'padding'      => $styles['spacing']['padding'] ?? null,
					'margin'       => $styles['spacing']['margin'] ?? null,
					'blockGap'     => $styles['spacing']['blockGap'] ?? null,
				);

				// Layout.
				$layout = array(
					'contentSize' => $settings['layout']['contentSize'] ?? null,
					'wideSize'    => $settings['layout']['wideSize'] ?? null,
				);
			}

			// Custom CSS (Customizer additional CSS).
			$custom_css = wp_get_custom_css();

			// Templates (block themes).
			$templates = array();
			if ( $theme_obj->is_block_theme() ) {
				$block_templates = get_block_templates( array(), 'wp_template' );
				foreach ( $block_templates as $tmpl ) {
					$templates[] = array(
						'slug'   => $tmpl->slug,
						'title'  => $tmpl->title,
						'source' => $tmpl->source,
						'type'   => $tmpl->type,
					);
				}
			}

			return array(
				'theme'      => $theme,
				'colors'     => $colors,
				'typography' => $typography,
				'spacing'    => $spacing,
				'layout'     => $layout,
				'custom_css' => $custom_css,
				'templates'  => $templates,
			);
		},
	));

	// ===== THEMES — WRITE =====

	$reg->write( 'themes/activate', array(
		'label'       => 'Activate Theme',
		'compiled'    => false,
		'replaces'    => 'themes.php',
		'description' => 'Switch to a different installed theme. The theme must already be installed.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'stylesheet' ),
			'properties' => array(
				'stylesheet' => array(
					'type'        => 'string',
					'description' => 'Theme stylesheet slug (directory name)',
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'message'    => array( 'type' => 'string' ),
			'stylesheet' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$stylesheet = sanitize_text_field( $input['stylesheet'] );
			$theme      = wp_get_theme( $stylesheet );

			if ( ! $theme->exists() ) {
				return new WP_Error( 'not_found', "Theme '{$stylesheet}' is not installed" );
			}

			if ( get_stylesheet() === $stylesheet ) {
				return array( 'success' => true, 'message' => 'Theme is already active', 'stylesheet' => $stylesheet );
			}

			switch_theme( $stylesheet );

			return array( 'success' => true, 'message' => 'Theme activated successfully', 'stylesheet' => $stylesheet );
		},
	) );

	$reg->write( 'themes/install', array(
		'capability'  => 'install_themes',
		'label'       => 'Install Theme',
		'compiled'    => false,
		'replaces'    => 'themes.php',
		'description' => 'Install a theme from the WordPress.org repository by slug.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'slug' ),
			'properties' => array(
				'slug' => array(
					'type'        => 'string',
					'description' => 'Theme slug from WordPress.org repository',
				),
				'activate' => array(
					'type'        => 'boolean',
					'default'     => false,
					'description' => 'Activate theme after installation',
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'message'    => array( 'type' => 'string' ),
			'stylesheet' => array( 'type' => 'string' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			if ( ! function_exists( 'themes_api' ) ) {
				require_once ABSPATH . 'wp-admin/includes/theme.php';
			}
			if ( ! class_exists( 'Theme_Upgrader' ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			}

			$slug = sanitize_text_field( $input['slug'] );

			try {
				$api = themes_api( 'theme_information', array(
					'slug'   => $slug,
					'fields' => array( 'sections' => false, 'description' => false ),
				) );
			} catch ( \Throwable $e ) {
				return new WP_Error( 'themes_api_error', 'Theme API request failed: ' . $e->getMessage() . '. This may be caused by hosting environment network restrictions.' );
			}

			if ( is_wp_error( $api ) ) {
				return new WP_Error(
					$api->get_error_code(),
					$api->get_error_message() . '. If the theme slug is correct, this may be caused by hosting environment restrictions blocking WordPress.org API requests.'
				);
			}

			if ( empty( $api->download_link ) ) {
				return new WP_Error( 'themes_api_failed', "Theme '{$slug}' was found but no download link is available. This may be caused by hosting environment restrictions." );
			}

			$upgrader = new Theme_Upgrader( new WP_Ajax_Upgrader_Skin() );

			try {
				$result = $upgrader->install( $api->download_link );
			} catch ( \Throwable $e ) {
				return new WP_Error( 'theme_install_error', 'Theme installation failed: ' . $e->getMessage() . '. This may be caused by hosting environment filesystem restrictions.' );
			}

			if ( is_wp_error( $result ) ) {
				return $result;
			}
			if ( ! $result ) {
				return new WP_Error( 'ability_invalid_input', 'Theme installation failed. This may be caused by hosting environment filesystem restrictions (e.g., CageFS/CloudLinux).' );
			}

			$response = array(
				'success'    => true,
				'message'    => 'Theme installed successfully',
				'stylesheet' => $slug,
			);

			if ( $input['activate'] ?? false ) {
				switch_theme( $slug );
				$response['message'] = 'Theme installed and activated successfully';
			}

			return $response;
		},
	) );

	$reg->write( 'themes/set-mod', array(
		'capability'  => 'edit_theme_options',
		'label'       => 'Set Theme Mod',
		'compiled'    => false,
		'replaces'    => 'themes.php',
		'description' => 'Set a theme modification value for the active theme.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'name', 'value' ),
			'properties' => array(
				'name' => array(
					'type'        => 'string',
					'description' => 'Theme mod name',
				),
				'value' => array(
					'type'        => 'string',
					'description' => 'Value to set (use JSON string for arrays/objects)',
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'name'  => array( 'type' => 'string' ),
			'value' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$name  = sanitize_text_field( $input['name'] );
			$value = $input['value'];

			// Attempt to decode JSON for complex values.
			$decoded = json_decode( $value, true );
			if ( json_last_error() === JSON_ERROR_NONE && ( is_array( $decoded ) || is_object( $decoded ) ) ) {
				$value = $decoded;
			}

			set_theme_mod( $name, $value );

			return array(
				'success' => true,
				'name'    => $name,
				'value'   => is_scalar( $value ) ? (string) $value : wp_json_encode( $value ),
			);
		},
	) );

	$reg->write( 'themes/set-mods-batch', array(
		'capability'  => 'edit_theme_options',
		'label'       => 'Set Theme Mods (Batch)',
		'compiled'    => false,
		'replaces'    => 'themes.php',
		'description' => 'Set multiple theme modification values in a single call. Accepts a key-value map of mod names to values. Returns the full theme_mods option after applying all changes.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'mods' ),
			'properties' => array(
				'mods' => array(
					'type'        => 'object',
					'description' => 'Key-value map of theme mod names to values. Use JSON strings for complex values (arrays/objects).',
				),
			),
		),
		'callback' => function( $input ) {
			$mods    = $input['mods'];
			$updated = array();

			foreach ( $mods as $name => $value ) {
				$name = sanitize_text_field( $name );

				// Attempt to decode JSON for complex values (same logic as themes/set-mod).
				if ( is_string( $value ) ) {
					$decoded = json_decode( $value, true );
					if ( json_last_error() === JSON_ERROR_NONE && ( is_array( $decoded ) || is_object( $decoded ) ) ) {
						$value = $decoded;
					}
				}

				set_theme_mod( $name, $value );
				$updated[] = $name;
			}

			return array(
				'success'    => true,
				'updated'    => $updated,
				'theme_mods' => get_theme_mods(),
			);
		},
	) );

	// ===== THEMES — DELETE =====

	$reg->delete( 'themes/delete', array(
		'capability'  => 'delete_themes',
		'label'       => 'Delete Theme',
		'compiled'    => false,
		'replaces'    => 'themes.php',
		'description' => 'Delete an installed theme. The theme must not be the active theme or its parent.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'stylesheet' ),
			'properties' => array(
				'stylesheet' => array(
					'type'        => 'string',
					'description' => 'Theme stylesheet slug (directory name) to delete',
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'message' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! function_exists( 'delete_theme' ) ) {
				require_once ABSPATH . 'wp-admin/includes/theme.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			$stylesheet = sanitize_text_field( $input['stylesheet'] );
			$theme      = wp_get_theme( $stylesheet );

			if ( ! $theme->exists() ) {
				return new WP_Error( 'not_found', "Theme '{$stylesheet}' is not installed" );
			}

			if ( get_stylesheet() === $stylesheet || get_template() === $stylesheet ) {
				return new WP_Error( 'ability_invalid_input', 'Cannot delete the active theme or its parent' );
			}

			$result = delete_theme( $stylesheet );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return array( 'success' => true, 'message' => 'Theme deleted successfully' );
		},
	) );

	$reg->delete( 'themes/delete-mod', array(
		'capability'  => 'edit_theme_options',
		'label'       => 'Delete Theme Mod',
		'compiled'    => false,
		'replaces'    => 'themes.php',
		'description' => 'Remove a theme modification from the active theme.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'name' ),
			'properties' => array(
				'name' => array(
					'type'        => 'string',
					'description' => 'Theme mod name to remove',
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'name'    => array( 'type' => 'string' ),
			'deleted' => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $input ) {
			$name = sanitize_text_field( $input['name'] );

			$exists = get_theme_mod( $name, '__NOT_SET__' );
			if ( $exists === '__NOT_SET__' ) {
				return wp_abilities_error( 'not_found', "Theme mod '{$name}' does not exist." );
			}

			remove_theme_mod( $name );

			return array( 'success' => true, 'name' => $name, 'deleted' => true );
		},
	) );
});
