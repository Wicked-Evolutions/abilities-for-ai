<?php
/**
 * Block Patterns Abilities
 *
 * List, get, register, and unregister block patterns.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {
	$reg = new Abilities_For_AI_Registrar( 'patterns', 'edit_posts' );

	$reg->read( 'patterns/list', array(
		'label'       => 'List Block Patterns',
		'compiled'    => false,
		'replaces'    => 'site-editor.php?p=%2Fpattern',
		'description' => 'List all registered block patterns with optional category filter.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				array(
					'category' => array( 'type' => 'string', 'description' => 'Filter by pattern category slug' ),
					'search'   => abilities_for_ai_schema_search( 'Search pattern names or titles' ),
				),
				abilities_for_ai_schema_pagination()
			),
		),
		'output_schema' => abilities_for_ai_schema_list_output( 'patterns', array(
			'name'        => array( 'type' => 'string' ),
			'title'       => array( 'type' => 'string' ),
			'description' => array( 'type' => 'string' ),
			'categories'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'keywords'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'blockTypes'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
		) ),
		'callback' => function( $params ) {
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

			$pag   = wp_abilities_pagination( $params );
			$slice = array_slice( $patterns, $pag['offset'], $pag['per_page'] );

			return array(
				'total'    => count( $patterns ),
				'pages'    => max( 1, (int) ceil( count( $patterns ) / $pag['per_page'] ) ),
				'page'     => $pag['page'],
				'per_page' => $pag['per_page'],
				'patterns' => $slice,
			);
		},
	));

	$reg->read( 'patterns/get', array(
		'label'       => 'Get Block Pattern',
		'compiled'    => false,
		'replaces'    => 'site-editor.php?p=%2Fpattern',
		'description' => 'Get a single block pattern by name, including its content markup.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'name' => array( 'type' => 'string', 'description' => 'Pattern name (e.g. "core/query-standard-posts")' ),
			),
			'required' => array( 'name' ),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'name'        => array( 'type' => 'string' ),
			'title'       => array( 'type' => 'string' ),
			'description' => array( 'type' => 'string' ),
			'content'     => array( 'type' => 'string' ),
			'categories'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'keywords'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'blockTypes'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'inserter'    => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $params ) {
			$name     = sanitize_text_field( $params['name'] ?? '' );
			$registry = WP_Block_Patterns_Registry::get_instance();

			if ( ! $registry->is_registered( $name ) ) {
				return wp_abilities_error( 'not_found', "Pattern '{$name}' not found." );
			}

			$all     = $registry->get_all_registered();
			$pattern = null;
			foreach ( $all as $p ) {
				if ( ( $p['name'] ?? '' ) === $name ) {
					$pattern = $p;
					break;
				}
			}

			if ( ! $pattern ) {
				return wp_abilities_error( 'not_found', "Pattern '{$name}' not found." );
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
	));

	$reg->read( 'patterns/list-categories', array(
		'label'       => 'List Pattern Categories',
		'compiled'    => false,
		'replaces'    => 'site-editor.php?p=%2Fpattern',
		'description' => 'List all registered block pattern categories.',
		'output_schema' => abilities_for_ai_schema_collection_output( 'categories', array(
			'name'        => array( 'type' => 'string' ),
			'label'       => array( 'type' => 'string' ),
			'compiled'    => false,
			'replaces'    => 'site-editor.php?p=%2Fpattern',
			'description' => array( 'type' => 'string' ),
		) ),
		'callback' => function() {
			$registry   = WP_Block_Pattern_Categories_Registry::get_instance();
			$categories = $registry->get_all_registered();
			$result     = array();
			foreach ( $categories as $cat ) {
				$result[] = array(
					'name'        => $cat['name'] ?? '',
					'label'       => $cat['label'] ?? '',
					'compiled'    => false,
					'replaces'    => 'site-editor.php?p=%2Fpattern',
					'description' => $cat['description'] ?? '',
				);
			}
			return array( 'total' => count( $result ), 'categories' => $result );
		},
	));

	$reg->write( 'patterns/register', array(
		'label'       => 'Register Block Pattern',
		'compiled'    => false,
		'replaces'    => 'site-editor.php?p=%2Fpattern',
		'description' => 'Register a new block pattern. The pattern will be available in the block inserter.',
		'capability'  => 'manage_options',
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
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'name'       => array( 'type' => 'string' ),
			'registered' => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $params ) {
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
				return wp_abilities_error( 'ability_invalid_input', "Failed to register pattern '{$name}'. It may already exist." );
			}

			return array( 'name' => $name, 'registered' => true );
		},
	));

	$reg->delete( 'patterns/unregister', array(
		'label'       => 'Unregister Block Pattern',
		'compiled'    => false,
		'replaces'    => 'site-editor.php?p=%2Fpattern',
		'description' => 'Unregister an existing block pattern by name.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'name' => array( 'type' => 'string', 'description' => 'Pattern name to unregister' ),
			),
			'required' => array( 'name' ),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'name'         => array( 'type' => 'string' ),
			'unregistered' => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $params ) {
			$name     = sanitize_text_field( $params['name'] ?? '' );
			$registry = WP_Block_Patterns_Registry::get_instance();

			if ( ! $registry->is_registered( $name ) ) {
				return wp_abilities_error( 'not_found', "Pattern '{$name}' is not registered." );
			}

			$result = unregister_block_pattern( $name );
			return array( 'name' => $name, 'unregistered' => (bool) $result );
		},
	));
});
