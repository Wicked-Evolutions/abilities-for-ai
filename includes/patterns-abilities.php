<?php
/**
 * Block Patterns Abilities
 *
 * List, get, register, and unregister block patterns.
 *
 * @package WordPress_Native_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', 'wp_native_register_patterns_abilities' );

function wp_native_register_patterns_abilities() {

	$perms = wp_abilities_suite_get_permissions( 'patterns' );

	// ===== PATTERNS — READ =====
	if ( $perms['read'] ) {

	// ---- patterns/list ----
	wp_register_ability( 'patterns/list', array(
		'label'       => 'List Block Patterns',
		'description' => 'List all registered block patterns with optional category filter.',
		'category'    => 'patterns',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				array(
					'category' => array( 'type' => 'string', 'description' => 'Filter by pattern category slug' ),
					'search'   => array( 'type' => 'string', 'description' => 'Search pattern names or titles' ),
				),
				wp_native_pagination_schema()
			),
		),
		'execute_callback' => function( $params ) {
			$registry = WP_Block_Patterns_Registry::get_instance();
			$all      = $registry->get_all_registered();
			$patterns = array();

			foreach ( $all as $pattern ) {
				if ( ! empty( $params['category'] ) ) {
					$cats = $pattern['categories'] ?? array();
					if ( ! in_array( $params['category'], $cats, true ) ) {
						continue;
					}
				}
				if ( ! empty( $params['search'] ) ) {
					$haystack = ( $pattern['name'] ?? '' ) . ' ' . ( $pattern['title'] ?? '' );
					if ( stripos( $haystack, $params['search'] ) === false ) {
						continue;
					}
				}
				$patterns[] = array(
					'name'        => $pattern['name'] ?? '',
					'title'       => $pattern['title'] ?? '',
					'description' => $pattern['description'] ?? '',
					'categories'  => $pattern['categories'] ?? array(),
					'keywords'    => $pattern['keywords'] ?? array(),
					'blockTypes'  => $pattern['blockTypes'] ?? array(),
				);
			}

			$pag   = wp_native_pagination( $params );
			$slice = array_slice( $patterns, $pag['offset'], $pag['per_page'] );

			return array(
				'patterns' => $slice,
				'total'    => count( $patterns ),
				'page'     => $pag['page'],
				'pages'    => ceil( count( $patterns ) / $pag['per_page'] ),
			);
		},
		'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
	));

	// ---- patterns/get ----
	wp_register_ability( 'patterns/get', array(
		'label'       => 'Get Block Pattern',
		'description' => 'Get a single block pattern by name, including its content markup.',
		'category'    => 'patterns',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'name' => array( 'type' => 'string', 'description' => 'Pattern name (e.g. "core/query-standard-posts")' ),
			),
			'required' => array( 'name' ),
		),
		'execute_callback' => function( $params ) {
			$name     = sanitize_text_field( $params['name'] ?? '' );
			$registry = WP_Block_Patterns_Registry::get_instance();

			if ( ! $registry->is_registered( $name ) ) {
				return wp_native_error( 'not_found', "Pattern '{$name}' not found." );
			}

			$all = $registry->get_all_registered();
			$pattern = null;
			foreach ( $all as $p ) {
				if ( ( $p['name'] ?? '' ) === $name ) {
					$pattern = $p;
					break;
				}
			}

			if ( ! $pattern ) {
				return wp_native_error( 'not_found', "Pattern '{$name}' not found." );
			}

			return array(
				'name'        => $pattern['name'] ?? '',
				'title'       => $pattern['title'] ?? '',
				'description' => $pattern['description'] ?? '',
				'content'     => $pattern['content'] ?? '',
				'categories'  => $pattern['categories'] ?? array(),
				'keywords'    => $pattern['keywords'] ?? array(),
				'blockTypes'  => $pattern['blockTypes'] ?? array(),
				'inserter'    => $pattern['inserter'] ?? true,
			);
		},
		'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
	));

	// ---- patterns/list-categories ----
	wp_register_ability( 'patterns/list-categories', array(
		'label'       => 'List Pattern Categories',
		'description' => 'List all registered block pattern categories.',
		'category'    => 'patterns',
		'input_schema' => array(
			'type'       => 'object',
		),
		'execute_callback' => function() {
			$registry   = WP_Block_Pattern_Categories_Registry::get_instance();
			$categories = $registry->get_all_registered();
			$result     = array();
			foreach ( $categories as $cat ) {
				$result[] = array(
					'name'        => $cat['name'] ?? '',
					'label'       => $cat['label'] ?? '',
					'description' => $cat['description'] ?? '',
				);
			}
			return array( 'count' => count( $result ), 'categories' => $result );
		},
		'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
	));

	} // end read

	// ===== PATTERNS — WRITE =====
	if ( ! empty( $perms['write'] ) ) {

	// ---- patterns/register ----
	wp_register_ability( 'patterns/register', array(
		'label'       => 'Register Block Pattern',
		'description' => 'Register a new block pattern. The pattern will be available in the block inserter.',
		'category'    => 'patterns',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'name'        => array( 'type' => 'string', 'description' => 'Unique pattern name (e.g. "my-plugin/hero-section")' ),
				'title'       => array( 'type' => 'string', 'description' => 'Human-readable title' ),
				'content'     => array( 'type' => 'string', 'description' => 'Block markup content' ),
				'description' => array( 'type' => 'string', 'description' => 'Pattern description' ),
				'categories'  => array( 'type' => 'array', 'description' => 'Category slugs', 'items' => array( 'type' => 'string' ) ),
				'keywords'    => array( 'type' => 'array', 'description' => 'Search keywords', 'items' => array( 'type' => 'string' ) ),
			),
			'required' => array( 'name', 'title', 'content' ),
		),
		'execute_callback' => function( $params ) {
			$name = sanitize_text_field( $params['name'] ?? '' );
			$args = array(
				'title'   => sanitize_text_field( $params['title'] ),
				'content' => $params['content'],
			);
			if ( ! empty( $params['description'] ) ) {
				$args['description'] = sanitize_text_field( $params['description'] );
			}
			if ( ! empty( $params['categories'] ) ) {
				$args['categories'] = array_map( 'sanitize_text_field', $params['categories'] );
			}
			if ( ! empty( $params['keywords'] ) ) {
				$args['keywords'] = array_map( 'sanitize_text_field', $params['keywords'] );
			}

			$result = register_block_pattern( $name, $args );
			if ( $result === false ) {
				return wp_native_error( 'registration_failed', "Failed to register pattern '{$name}'. It may already exist." );
			}

			return array( 'name' => $name, 'registered' => true );
		},
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ),
	));

	} // end write

	// ===== PATTERNS — DELETE =====
	if ( ! empty( $perms['delete'] ) ) {

	// ---- patterns/unregister ----
	wp_register_ability( 'patterns/unregister', array(
		'label'       => 'Unregister Block Pattern',
		'description' => 'Unregister an existing block pattern by name.',
		'category'    => 'patterns',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'name' => array( 'type' => 'string', 'description' => 'Pattern name to unregister' ),
			),
			'required' => array( 'name' ),
		),
		'execute_callback' => function( $params ) {
			$name = sanitize_text_field( $params['name'] ?? '' );
			$registry = WP_Block_Patterns_Registry::get_instance();

			if ( ! $registry->is_registered( $name ) ) {
				return wp_native_error( 'not_found', "Pattern '{$name}' is not registered." );
			}

			$result = unregister_block_pattern( $name );
			return array( 'name' => $name, 'unregistered' => (bool) $result );
		},
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => true ) ),
	));

	} // end delete
}
