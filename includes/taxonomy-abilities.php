<?php
/**
 * Taxonomy Abilities
 *
 * WordPress taxonomy and term management.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WordPress_Abilities_Suite
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {
	$reg = new WP_Abilities_Suite_Registrar( 'taxonomies', 'edit_posts' );

	// ===== TAXONOMIES — READ =====

	$reg->read( 'taxonomies/discover', array(
		'label'       => 'Discover Taxonomies',
		'description' => 'List all available taxonomies with their configuration',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'public' => array(
					'type'        => 'boolean',
					'description' => 'Only return public taxonomies',
					'default'     => true,
				),
			),
		),
		'output_schema' => wp_abilities_suite_schema_collection_output( 'taxonomies', array(
			'name'         => array( 'type' => 'string' ),
			'label'        => array( 'type' => 'string' ),
			'hierarchical' => array( 'type' => 'boolean' ),
			'public'       => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $input ) {
			$args = array();
			if ( isset( $input['public'] ) && $input['public'] ) {
				$args['public'] = true;
			}
			$taxonomies = get_taxonomies( $args, 'objects' );
			return array_map( function( $taxonomy ) {
				return array(
					'name'         => $taxonomy->name,
					'label'        => $taxonomy->label,
					'description'  => $taxonomy->description,
					'hierarchical' => $taxonomy->hierarchical,
					'public'       => $taxonomy->public,
					'show_ui'      => $taxonomy->show_ui,
					'rest_base'    => $taxonomy->rest_base,
					'object_type'  => $taxonomy->object_type,
				);
			}, $taxonomies );
		},
	) );

	$reg->read( 'taxonomies/list-terms', array(
		'label'       => 'List Terms',
		'description' => 'List terms in a specific taxonomy with filtering and pagination',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'taxonomy' ),
			'properties' => array(
				'taxonomy' => array(
					'type'        => 'string',
					'description' => 'Taxonomy name (e.g., category, post_tag)',
				),
				'number' => array(
					'type'        => 'integer',
					'description' => 'Maximum number of terms to return',
					'default'     => 10,
					'minimum'     => 1,
					'maximum'     => 100,
				),
				'offset' => array(
					'type'        => 'integer',
					'description' => 'Number of terms to skip',
					'default'     => 0,
					'minimum'     => 0,
				),
				'search' => array(
					'type'        => 'string',
					'description' => 'Search term names',
				),
				'hide_empty' => array(
					'type'        => 'boolean',
					'description' => 'Hide terms with no posts',
					'default'     => false,
				),
				'parent' => array(
					'type'        => 'integer',
					'description' => 'Get direct children of this term ID (for hierarchical taxonomies)',
				),
			),
		),
		'output_schema' => wp_abilities_suite_schema_list_output( 'terms', array(
			'term_id'  => array( 'type' => 'integer' ),
			'name'     => array( 'type' => 'string' ),
			'slug'     => array( 'type' => 'string' ),
			'count'    => array( 'type' => 'integer' ),
			'taxonomy' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$args = array(
				'taxonomy'   => $input['taxonomy'],
				'number'     => $input['number'] ?? 10,
				'offset'     => $input['offset'] ?? 0,
				'hide_empty' => $input['hide_empty'] ?? false,
			);
			if ( ! empty( $input['search'] ) ) {
				$args['search'] = $input['search'];
			}
			if ( isset( $input['parent'] ) ) {
				$args['parent'] = $input['parent'];
			}
			$terms = get_terms( $args );
			if ( is_wp_error( $terms ) ) {
				return $terms;
			}
			$count_args           = $args;
			$count_args['number'] = 0;
			$count_args['offset'] = 0;
			$count_args['fields'] = 'count';
			$total                = get_terms( $count_args );

			$formatted_terms = array_map( function( $term ) {
				return array(
					'term_id'     => $term->term_id,
					'name'        => $term->name,
					'slug'        => $term->slug,
					'description' => $term->description,
					'count'       => $term->count,
					'parent'      => $term->parent,
					'taxonomy'    => $term->taxonomy,
					'link'        => get_term_link( $term ),
				);
			}, $terms );

			return array( 'terms' => $formatted_terms, 'total' => $total );
		},
	) );

	$reg->read( 'taxonomies/get-term', array(
		'label'       => 'Get Term',
		'description' => 'Get a specific term by ID',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'term_id', 'taxonomy' ),
			'properties' => array(
				'term_id'  => array( 'type' => 'integer', 'description' => 'Term ID' ),
				'taxonomy' => array( 'type' => 'string', 'description' => 'Taxonomy name' ),
			),
		),
		'output_schema' => wp_abilities_suite_schema_item_output( array(
			'term_id'    => array( 'type' => 'integer' ),
			'name'       => array( 'type' => 'string' ),
			'slug'       => array( 'type' => 'string' ),
			'post_count' => array( 'type' => 'integer', 'description' => 'Number of posts with this term' ),
			'taxonomy'   => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$term = get_term( $input['term_id'], $input['taxonomy'] );
			if ( is_wp_error( $term ) || ! $term ) {
				return new WP_Error( 'not_found', 'Term not found' );
			}
			return array(
				'term_id'     => $term->term_id,
				'name'        => $term->name,
				'slug'        => $term->slug,
				'description' => $term->description,
				'post_count'  => $term->count,
				'parent'      => $term->parent,
				'taxonomy'    => $term->taxonomy,
				'link'        => get_term_link( $term ),
				'meta'        => get_term_meta( $term->term_id ),
			);
		},
	) );

	$reg->read( 'taxonomies/get-content-terms', array(
		'label'       => 'Get Content Terms',
		'description' => 'Get all terms assigned to a specific post',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'post_id' ),
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'Post ID',
				),
				'taxonomy' => array(
					'type'        => 'string',
					'description' => 'Specific taxonomy to get terms from (optional, returns all if not specified)',
				),
			),
		),
		'output_schema' => wp_abilities_suite_schema_item_output( array() ),
		'callback' => function( $input ) {
			$check = wp_abilities_suite_require_editable_post( $input['post_id'] );
			if ( is_wp_error( $check ) ) {
				return $check;
			}
			$post_id = $input['post_id'];

			if ( isset( $input['taxonomy'] ) ) {
				$terms = get_the_terms( $post_id, $input['taxonomy'] );
				if ( is_wp_error( $terms ) ) {
					return $terms;
				}
				if ( ! $terms ) {
					return array( $input['taxonomy'] => array() );
				}
				return array(
					$input['taxonomy'] => array_map( function( $term ) {
						return array(
							'term_id'  => $term->term_id,
							'name'     => $term->name,
							'slug'     => $term->slug,
							'taxonomy' => $term->taxonomy,
						);
					}, $terms ),
				);
			}

			$post = get_post( $post_id );
			if ( ! $post ) {
				return new WP_Error( 'not_found', 'Post not found' );
			}

			$taxonomies = get_object_taxonomies( $post->post_type );
			$result     = array();
			foreach ( $taxonomies as $taxonomy ) {
				$terms = get_the_terms( $post_id, $taxonomy );
				if ( $terms && ! is_wp_error( $terms ) ) {
					$result[ $taxonomy ] = array_map( function( $term ) {
						return array(
							'term_id'  => $term->term_id,
							'name'     => $term->name,
							'slug'     => $term->slug,
							'taxonomy' => $term->taxonomy,
						);
					}, $terms );
				} else {
					$result[ $taxonomy ] = array();
				}
			}
			return (object) $result;
		},
	) );

	// ===== TAXONOMIES — WRITE =====

	// taxonomies/create-term is free despite being a write operation.
	$reg->write( 'taxonomies/create-term', array(
		'tier'        => 'free',
		'capability'  => 'manage_categories',
		'label'       => 'Create Term',
		'description' => 'Create a new term in a taxonomy',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'name', 'taxonomy' ),
			'properties' => array(
				'name' => array(
					'type'        => 'string',
					'description' => 'Term name',
				),
				'taxonomy' => array(
					'type'        => 'string',
					'description' => 'Taxonomy name',
				),
				'slug' => array(
					'type'        => 'string',
					'description' => 'Term slug (optional, will be generated from name if not provided)',
				),
				'description' => array(
					'type'        => 'string',
					'description' => 'Term description',
				),
				'parent' => array(
					'type'        => 'integer',
					'description' => 'Parent term ID (for hierarchical taxonomies)',
				),
			),
		),
		'output_schema' => wp_abilities_suite_schema_success_output( array(
			'term_id'          => array( 'type' => 'integer' ),
			'term_taxonomy_id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
		'callback' => function( $input ) {
			$taxonomy = $input['taxonomy'];
			$tax_obj  = get_taxonomy( $taxonomy );
			if ( ! $tax_obj ) {
				return new WP_Error( 'ability_invalid_input', 'Invalid taxonomy.' );
			}
			if ( ! current_user_can( $tax_obj->cap->manage_terms ) ) {
				return new WP_Error( 'rest_forbidden', "You do not have permission to manage terms in \"{$taxonomy}\"." );
			}
			$args = array();
			if ( ! empty( $input['slug'] ) ) {
				$args['slug'] = $input['slug'];
			}
			if ( ! empty( $input['description'] ) ) {
				$args['description'] = $input['description'];
			}
			if ( isset( $input['parent'] ) ) {
				$args['parent'] = $input['parent'];
			}
			$result = wp_insert_term( $input['name'], $taxonomy, $args );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array(
				'term_id'          => $result['term_id'],
				'term_taxonomy_id' => $result['term_taxonomy_id'],
			);
		},
	) );

	$reg->write( 'taxonomies/update-term', array(
		'capability'  => 'manage_categories',
		'label'       => 'Update Term',
		'description' => 'Update an existing term',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'term_id', 'taxonomy' ),
			'properties' => array(
				'term_id' => array(
					'type'        => 'integer',
					'description' => 'Term ID',
				),
				'taxonomy' => array(
					'type'        => 'string',
					'description' => 'Taxonomy name',
				),
				'name' => array(
					'type'        => 'string',
					'description' => 'Term name',
				),
				'slug' => array(
					'type'        => 'string',
					'description' => 'Term slug',
				),
				'description' => array(
					'type'        => 'string',
					'description' => 'Term description',
				),
				'parent' => array(
					'type'        => 'integer',
					'description' => 'Parent term ID',
				),
			),
		),
		'output_schema' => wp_abilities_suite_schema_success_output( array(
			'term_id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$taxonomy = $input['taxonomy'];
			$tax_obj  = get_taxonomy( $taxonomy );
			if ( ! $tax_obj ) {
				return new WP_Error( 'ability_invalid_input', 'Invalid taxonomy.' );
			}
			if ( ! current_user_can( $tax_obj->cap->edit_terms ) ) {
				return new WP_Error( 'rest_forbidden', "You do not have permission to edit terms in \"{$taxonomy}\"." );
			}
			$args = array();
			if ( isset( $input['name'] ) ) {
				$args['name'] = $input['name'];
			}
			if ( isset( $input['slug'] ) ) {
				$args['slug'] = $input['slug'];
			}
			if ( isset( $input['description'] ) ) {
				$args['description'] = $input['description'];
			}
			if ( isset( $input['parent'] ) ) {
				$args['parent'] = $input['parent'];
			}
			$result = wp_update_term( $input['term_id'], $taxonomy, $args );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array( 'term_id' => $result['term_id'], 'success' => true );
		},
	) );

	$reg->write( 'taxonomies/assign-to-content', array(
		'capability'  => 'edit_posts',
		'label'       => 'Assign Terms to Content',
		'description' => 'Assign one or more terms to a post or custom post type',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'post_id', 'taxonomy', 'terms' ),
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'Post ID',
				),
				'taxonomy' => array(
					'type'        => 'string',
					'description' => 'Taxonomy name',
				),
				'terms' => array(
					'type'        => 'array',
					'description' => 'Array of term IDs or term names to assign',
					'items'       => array(
						'oneOf' => array(
							array( 'type' => 'integer' ),
							array( 'type' => 'string' ),
						),
					),
				),
				'append' => array(
					'type'        => 'boolean',
					'description' => 'If true, append terms. If false, replace existing terms',
					'default'     => false,
				),
			),
		),
		'output_schema' => wp_abilities_suite_schema_success_output( array(
			'term_taxonomy_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
		) ),
		'callback' => function( $input ) {
			$check = wp_abilities_suite_require_editable_post( $input['post_id'] );
			if ( is_wp_error( $check ) ) {
				return $check;
			}
			$taxonomy = $input['taxonomy'];
			$tax_obj  = get_taxonomy( $taxonomy );
			if ( ! $tax_obj ) {
				return new WP_Error( 'ability_invalid_input', 'Invalid taxonomy.' );
			}
			if ( ! current_user_can( $tax_obj->cap->assign_terms ) ) {
				return new WP_Error( 'rest_forbidden', "You do not have permission to assign terms in \"{$taxonomy}\"." );
			}
			$result = wp_set_object_terms( $input['post_id'], $input['terms'], $taxonomy, $input['append'] ?? false );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array( 'success' => true, 'term_taxonomy_ids' => $result );
		},
	) );

	// ===== TAXONOMIES — DELETE =====

	// taxonomies/delete-term is free — round-trip: create → test → delete the test.
	$reg->delete( 'taxonomies/delete-term', array(
		'tier'        => 'free',
		'capability'  => 'manage_categories',
		'label'       => 'Delete Term',
		'description' => 'Delete a term from a taxonomy',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'term_id', 'taxonomy' ),
			'properties' => array(
				'term_id'  => array( 'type' => 'integer', 'description' => 'Term ID' ),
				'taxonomy' => array( 'type' => 'string', 'description' => 'Taxonomy name' ),
			),
		),
		'output_schema' => wp_abilities_suite_schema_success_output( array() ),
		'annotations'   => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ),
		'callback' => function( $input ) {
			$taxonomy = $input['taxonomy'];
			$tax_obj  = get_taxonomy( $taxonomy );
			if ( ! $tax_obj ) {
				return new WP_Error( 'ability_invalid_input', 'Invalid taxonomy.' );
			}
			if ( ! current_user_can( $tax_obj->cap->delete_terms ) ) {
				return new WP_Error( 'rest_forbidden', "You do not have permission to delete terms in \"{$taxonomy}\"." );
			}
			$result = wp_delete_term( $input['term_id'], $taxonomy );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array( 'success' => (bool) $result );
		},
	) );
} );
