<?php
/**
 * Themes Abilities
 *
 * Read-only theme listing, mods, and theme.json access for V1.0.
 *
 * @package WordPress_Abilities_Suite
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {
	$reg = new WP_Abilities_Suite_Registrar( 'themes', 'switch_themes' );

	$reg->read( 'themes/list', array(
		'label'       => 'List Themes',
		'description' => 'List all installed themes with version, status, and capabilities.',
		'output_schema' => wp_abilities_suite_schema_item_output( array(
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
		'description' => 'Get detailed information about the currently active theme.',
		'output_schema' => wp_abilities_suite_schema_item_output( array(
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
		'description' => 'List all theme modifications for the active theme.',
		'capability'  => 'edit_theme_options',
		'output_schema' => wp_abilities_suite_schema_collection_output( 'mods', array(
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
		'description' => 'Get a specific theme modification value.',
		'capability'  => 'edit_theme_options',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'name' => array( 'type' => 'string', 'description' => 'Theme mod name' ),
			),
			'required' => array( 'name' ),
		),
		'output_schema' => wp_abilities_suite_schema_item_output( array(
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
		'output_schema' => wp_abilities_suite_schema_item_output( array(
			'theme'   => array( 'type' => 'string' ),
			'section' => array( 'type' => 'string' ),
			'data'    => array( 'type' => 'object' ),
		) ),
		'callback' => function( $params ) {
			if ( ! class_exists( 'WP_Theme_JSON_Resolver' ) ) {
				return wp_abilities_error( 'not_available', 'theme.json is not available (requires block theme or WP 5.8+).' );
			}

			$theme = wp_get_theme();
			if ( ! $theme->is_block_theme() && ! file_exists( $theme->get_theme_root() . '/' . $theme->get_stylesheet() . '/theme.json' ) ) {
				if ( ! file_exists( get_stylesheet_directory() . '/theme.json' ) ) {
					return wp_abilities_error( 'no_theme_json', 'Active theme does not have a theme.json file.' );
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
});
