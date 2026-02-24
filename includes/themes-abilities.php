<?php
/**
 * Themes Abilities
 *
 * Read-only theme listing, mods, and theme.json access for V1.0.
 *
 * @package WordPress_Native_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', 'wp_native_register_themes_abilities' );

function wp_native_register_themes_abilities() {

	$perms = wp_abilities_suite_get_permissions( 'themes' );

	// ===== THEMES — READ =====
	if ( $perms['read'] ) {

	// ---- themes/list ----
	wp_register_ability( 'themes/list', array(
		'label'       => 'List Themes',
		'description' => 'List all installed themes with version, status, and capabilities.',
		'category'    => 'themes',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => (object) array(),
		),
		'execute_callback' => function() {
			$themes = wp_get_themes();
			$active = get_stylesheet();
			$result = array();

			foreach ( $themes as $slug => $theme ) {
				$result[] = array(
					'slug'         => $slug,
					'name'         => $theme->get( 'Name' ),
					'version'      => $theme->get( 'Version' ),
					'author'       => $theme->get( 'Author' ),
					'active'       => ( $slug === $active ),
					'parent'       => $theme->parent() ? $theme->parent()->get_stylesheet() : null,
					'block_theme'  => $theme->is_block_theme(),
					'template'     => $theme->get_template(),
				);
			}

			return array( 'themes' => $result, 'count' => count( $result ), 'active' => $active );
		},
		'permission_callback' => function() { return current_user_can( 'switch_themes' ); },
		'meta' => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
	));

	// ---- themes/get-active ----
	wp_register_ability( 'themes/get-active', array(
		'label'       => 'Get Active Theme',
		'description' => 'Get detailed information about the currently active theme.',
		'category'    => 'themes',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => (object) array(),
		),
		'execute_callback' => function() {
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
		'permission_callback' => function() { return current_user_can( 'switch_themes' ); },
		'meta' => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
	));

	// ---- themes/list-mods ----
	wp_register_ability( 'themes/list-mods', array(
		'label'       => 'List Theme Mods',
		'description' => 'List all theme modifications for the active theme.',
		'category'    => 'themes',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => (object) array(),
		),
		'execute_callback' => function() {
			$mods = get_theme_mods();
			if ( ! is_array( $mods ) ) {
				$mods = array();
			}
			$result = array();
			foreach ( $mods as $key => $value ) {
				$result[] = array(
					'key'   => $key,
					'value' => $value,
					'type'  => gettype( $value ),
				);
			}
			return array( 'theme' => get_stylesheet(), 'mod_count' => count( $result ), 'mods' => $result );
		},
		'permission_callback' => function() { return current_user_can( 'edit_theme_options' ); },
		'meta' => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
	));

	// ---- themes/get-mod ----
	wp_register_ability( 'themes/get-mod', array(
		'label'       => 'Get Theme Mod',
		'description' => 'Get a specific theme modification value.',
		'category'    => 'themes',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'name' => array( 'type' => 'string', 'description' => 'Theme mod name' ),
			),
			'required' => array( 'name' ),
		),
		'execute_callback' => function( $params ) {
			$name  = sanitize_text_field( $params['name'] ?? '' );
			$value = get_theme_mod( $name, '__NOT_SET__' );
			if ( $value === '__NOT_SET__' ) {
				return wp_native_error( 'not_found', "Theme mod '{$name}' not found." );
			}
			return array( 'name' => $name, 'value' => $value );
		},
		'permission_callback' => function() { return current_user_can( 'edit_theme_options' ); },
		'meta' => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
	));

	// ---- themes/get-theme-json ----
	wp_register_ability( 'themes/get-theme-json', array(
		'label'       => 'Get Theme JSON',
		'description' => 'Get the merged theme.json data for the active block theme. Returns settings and styles.',
		'category'    => 'themes',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'section' => array(
					'type'        => 'string',
					'description' => 'Specific section to return: settings, styles, or customTemplates. Omit for full data.',
				),
			),
		),
		'execute_callback' => function( $params ) {
			if ( ! class_exists( 'WP_Theme_JSON_Resolver' ) ) {
				return wp_native_error( 'not_available', 'theme.json is not available (requires block theme or WP 5.8+).' );
			}

			$theme = wp_get_theme();
			if ( ! $theme->is_block_theme() && ! file_exists( $theme->get_theme_root() . '/' . $theme->get_stylesheet() . '/theme.json' ) ) {
				// Classic themes may still have theme.json.
				if ( ! file_exists( get_stylesheet_directory() . '/theme.json' ) ) {
					return wp_native_error( 'no_theme_json', 'Active theme does not have a theme.json file.' );
				}
			}

			$merged = WP_Theme_JSON_Resolver::get_merged_data();
			$data   = $merged->get_raw_data();

			if ( ! empty( $params['section'] ) ) {
				$section = sanitize_text_field( $params['section'] );
				if ( ! isset( $data[ $section ] ) ) {
					return wp_native_error( 'not_found', "Section '{$section}' not found. Available: " . implode( ', ', array_keys( $data ) ) );
				}
				return array( 'section' => $section, 'data' => $data[ $section ] );
			}

			return array( 'theme' => $theme->get( 'Name' ), 'data' => $data );
		},
		'permission_callback' => function() { return current_user_can( 'edit_theme_options' ); },
		'meta' => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
	));

	} // end read
}
