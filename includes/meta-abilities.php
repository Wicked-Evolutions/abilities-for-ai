<?php
/**
 * Meta Fields Abilities
 *
 * Post, term, and user meta CRUD + registered meta key listing.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {
	$reg = new Abilities_For_AI_Registrar( 'meta', 'edit_posts' );

	// ===== META — READ =====

	$reg->read( 'meta/list-post-meta', array(
		'label'       => 'List Post Meta',
		'description' => 'List all meta fields for a post.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_id' => array( 'type' => 'integer', 'description' => 'Post ID' ),
			),
			'required' => array( 'post_id' ),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'post_id'    => array( 'type' => 'integer' ),
			'meta_count' => array( 'type' => 'integer' ),
			'meta'       => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $params ) {
			$check = abilities_for_ai_require_editable_post( $params['post_id'] ?? 0 );
			if ( is_wp_error( $check ) ) return $check;
			$post_id = $check->ID;
			$meta    = get_post_meta( $post_id );
			$result  = array();
			foreach ( $meta as $key => $values ) {
				$result[] = array( 'key' => $key, 'values' => abilities_for_ai_safe_value( $values ), 'count' => count( $values ) );
			}
			return array( 'post_id' => $post_id, 'meta_count' => count( $result ), 'meta' => $result );
		},
	));

	$reg->read( 'meta/get-post-meta', array(
		'label'       => 'Get Post Meta',
		'description' => 'Get a specific meta value for a post.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_id'  => array( 'type' => 'integer', 'description' => 'Post ID' ),
				'meta_key' => array( 'type' => 'string', 'description' => 'Meta key to retrieve' ),
				'single'   => array( 'type' => 'boolean', 'description' => 'Return single value (default: true)', 'default' => true ),
			),
			'required' => array( 'post_id', 'meta_key' ),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'post_id' => array( 'type' => 'integer' ),
			'key'     => array( 'type' => 'string' ),
			'value'   => array( 'type' => 'string', 'description' => 'Meta value (may be string, array, or serialized data)' ),
		) ),
		'callback' => function( $params ) {
			$check = abilities_for_ai_require_editable_post( $params['post_id'] ?? 0 );
			if ( is_wp_error( $check ) ) return $check;
			$post_id = $check->ID;
			$single  = $params['single'] ?? true;
			$value   = get_post_meta( $post_id, sanitize_text_field( $params['meta_key'] ), $single );
			return array( 'post_id' => $post_id, 'key' => $params['meta_key'], 'value' => abilities_for_ai_safe_value( $value ) );
		},
	));

	$reg->read( 'meta/list-term-meta', array(
		'label'       => 'List Term Meta',
		'description' => 'List all meta fields for a taxonomy term.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'term_id' => array( 'type' => 'integer', 'description' => 'Term ID' ),
			),
			'required' => array( 'term_id' ),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'term_id'    => array( 'type' => 'integer' ),
			'meta_count' => array( 'type' => 'integer' ),
			'meta'       => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $params ) {
			$term_id = intval( $params['term_id'] ?? 0 );
			$term    = get_term( $term_id );
			if ( ! $term || is_wp_error( $term ) ) {
				return wp_abilities_error( 'not_found', 'Term not found.' );
			}
			$meta   = get_term_meta( $term_id );
			$result = array();
			foreach ( $meta as $key => $values ) {
				$result[] = array( 'key' => $key, 'values' => abilities_for_ai_safe_value( $values ), 'count' => count( $values ) );
			}
			return array( 'term_id' => $term_id, 'meta_count' => count( $result ), 'meta' => $result );
		},
	));

	$reg->read( 'meta/get-term-meta', array(
		'label'       => 'Get Term Meta',
		'description' => 'Get a specific meta value for a taxonomy term.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'term_id'  => array( 'type' => 'integer', 'description' => 'Term ID' ),
				'meta_key' => array( 'type' => 'string', 'description' => 'Meta key' ),
				'single'   => array( 'type' => 'boolean', 'description' => 'Return single value (default: true)', 'default' => true ),
			),
			'required' => array( 'term_id', 'meta_key' ),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'term_id' => array( 'type' => 'integer' ),
			'key'     => array( 'type' => 'string' ),
			'value'   => array( 'type' => 'string', 'description' => 'Meta value (may be string, array, or serialized data)' ),
		) ),
		'callback' => function( $params ) {
			$term_id = intval( $params['term_id'] ?? 0 );
			$term    = get_term( $term_id );
			if ( ! $term || is_wp_error( $term ) ) {
				return wp_abilities_error( 'not_found', 'Term not found.' );
			}
			$single = $params['single'] ?? true;
			$value  = get_term_meta( $term_id, sanitize_text_field( $params['meta_key'] ), $single );
			return array( 'term_id' => $term_id, 'key' => $params['meta_key'], 'value' => abilities_for_ai_safe_value( $value ) );
		},
	));

	$reg->read( 'meta/list-user-meta', array(
		'label'       => 'List User Meta',
		'description' => 'List all meta fields for a user (sensitive security keys are redacted).',
		'capability'  => 'list_users',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'user_id' => array( 'type' => 'integer', 'description' => 'User ID' ),
			),
			'required' => array( 'user_id' ),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'user_id'    => array( 'type' => 'integer' ),
			'meta_count' => array( 'type' => 'integer' ),
			'meta'       => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $params ) {
			$user_id = intval( $params['user_id'] ?? 0 );
			$user    = get_userdata( $user_id );
			if ( ! $user ) {
				return wp_abilities_error( 'not_found', 'User not found.' );
			}
			if ( ! current_user_can( 'edit_user', $user_id ) ) {
				return wp_abilities_error( 'rest_forbidden', 'You do not have permission to view meta for this user.' );
			}
			$sensitive_keys = array(
				'session_tokens', 'wp_capabilities', 'wp_user_level',
				'user_pass', 'wp_dashboard_quick_press_last_post_id', 'auth_cookie',
			);
			$meta   = get_user_meta( $user_id );
			$result = array();
			foreach ( $meta as $key => $values ) {
				if ( in_array( $key, $sensitive_keys, true ) ) {
					$result[] = array( 'key' => $key, 'values' => array( '[REDACTED]' ), 'count' => 1 );
				} else {
					$result[] = array( 'key' => $key, 'values' => abilities_for_ai_safe_value( $values ), 'count' => count( $values ) );
				}
			}
			return array( 'user_id' => $user_id, 'meta_count' => count( $result ), 'meta' => $result );
		},
	));

	$reg->read( 'meta/get-user-meta', array(
		'label'       => 'Get User Meta',
		'description' => 'Get a specific meta value for a user (sensitive security keys are blocked).',
		'capability'  => 'list_users',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'user_id'  => array( 'type' => 'integer', 'description' => 'User ID' ),
				'meta_key' => array( 'type' => 'string', 'description' => 'Meta key' ),
				'single'   => array( 'type' => 'boolean', 'description' => 'Return single value (default: true)', 'default' => true ),
			),
			'required' => array( 'user_id', 'meta_key' ),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'user_id' => array( 'type' => 'integer' ),
			'key'     => array( 'type' => 'string' ),
			'value'   => array( 'type' => 'string', 'description' => 'Meta value (may be string, array, or serialized data)' ),
		) ),
		'callback' => function( $params ) {
			$user_id = intval( $params['user_id'] ?? 0 );
			if ( ! get_userdata( $user_id ) ) {
				return wp_abilities_error( 'not_found', 'User not found.' );
			}
			if ( ! current_user_can( 'edit_user', $user_id ) ) {
				return wp_abilities_error( 'rest_forbidden', 'You do not have permission to view meta for this user.' );
			}
			$key            = sanitize_text_field( $params['meta_key'] );
			$sensitive_keys = array( 'session_tokens', 'wp_capabilities', 'wp_user_level', 'user_pass', 'auth_cookie' );
			if ( in_array( $key, $sensitive_keys, true ) ) {
				return wp_abilities_error( 'rest_forbidden', 'This meta key is protected and cannot be read via this ability.' );
			}
			$single = $params['single'] ?? true;
			$value  = get_user_meta( $user_id, $key, $single );
			return array( 'user_id' => $user_id, 'key' => $key, 'value' => abilities_for_ai_safe_value( $value ) );
		},
	));

	$reg->read( 'meta/list-registered', array(
		'label'       => 'List Registered Meta Keys',
		'description' => 'List all meta keys registered via register_meta() for a given object type.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'object_type' => array(
					'type'        => 'string',
					'description' => 'Object type: post, term, user, or comment',
					'default'     => 'post',
				),
				'object_subtype' => array(
					'type'        => 'string',
					'description' => 'Object subtype (e.g. post type or taxonomy slug)',
					'default'     => '',
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'object_type'    => array( 'type' => 'string' ),
			'object_subtype' => array( 'type' => 'string' ),
			'total'          => array( 'type' => 'integer' ),
			'keys'           => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $params ) {
			$object_type    = sanitize_text_field( $params['object_type'] ?? 'post' );
			$object_subtype = sanitize_text_field( $params['object_subtype'] ?? '' );
			$keys           = get_registered_meta_keys( $object_type, $object_subtype );
			$result         = array();
			foreach ( $keys as $key => $args ) {
				$result[] = array(
					'key'          => $key,
					'type'         => $args['type'] ?? 'string',
					'description'  => $args['description'] ?? '',
					'single'       => $args['single'] ?? false,
					'show_in_rest' => ! empty( $args['show_in_rest'] ),
				);
			}
			return array(
				'object_type'    => $object_type,
				'object_subtype' => $object_subtype,
				'total'          => count( $result ),
				'keys'           => $result,
			);
		},
	));

	// ===== META — WRITE =====

	$reg->write( 'meta/update-post-meta', array(
		'label'       => 'Update Post Meta',
		'description' => 'Set or update a meta value for a post.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_id'    => array( 'type' => 'integer', 'description' => 'Post ID' ),
				'meta_key'   => array( 'type' => 'string', 'description' => 'Meta key' ),
				'meta_value' => array( 'type' => 'string', 'description' => 'Meta value to set' ),
			),
			'required' => array( 'post_id', 'meta_key', 'meta_value' ),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'post_id' => array( 'type' => 'integer' ),
			'key'     => array( 'type' => 'string' ),
			'updated' => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $params ) {
			$check = abilities_for_ai_require_editable_post( $params['post_id'] ?? 0 );
			if ( is_wp_error( $check ) ) return $check;
			$post_id = $check->ID;
			$key     = sanitize_text_field( $params['meta_key'] );
			$value   = sanitize_text_field( $params['meta_value'] );
			$result  = update_post_meta( $post_id, $key, $value );
			return array( 'post_id' => $post_id, 'key' => $key, 'updated' => (bool) $result );
		},
	));

	$reg->write( 'meta/update-term-meta', array(
		'label'       => 'Update Term Meta',
		'description' => 'Set or update a meta value for a taxonomy term.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'term_id'    => array( 'type' => 'integer', 'description' => 'Term ID' ),
				'meta_key'   => array( 'type' => 'string', 'description' => 'Meta key' ),
				'meta_value' => array( 'type' => 'string', 'description' => 'Meta value' ),
			),
			'required' => array( 'term_id', 'meta_key', 'meta_value' ),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'term_id' => array( 'type' => 'integer' ),
			'key'     => array( 'type' => 'string' ),
			'updated' => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $params ) {
			$term_id = intval( $params['term_id'] ?? 0 );
			$term    = get_term( $term_id );
			if ( ! $term || is_wp_error( $term ) ) {
				return wp_abilities_error( 'not_found', 'Term not found.' );
			}
			$key    = sanitize_text_field( $params['meta_key'] );
			$value  = sanitize_text_field( $params['meta_value'] );
			$result = update_term_meta( $term_id, $key, $value );
			return array( 'term_id' => $term_id, 'key' => $key, 'updated' => ! is_wp_error( $result ) );
		},
	));

	$reg->write( 'meta/update-user-meta', array(
		'label'       => 'Update User Meta',
		'description' => 'Set or update a meta value for a user. Security-sensitive keys are blocked.',
		'capability'  => 'edit_users',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'user_id'    => array( 'type' => 'integer', 'description' => 'User ID' ),
				'meta_key'   => array( 'type' => 'string', 'description' => 'Meta key' ),
				'meta_value' => array( 'type' => 'string', 'description' => 'Meta value' ),
			),
			'required' => array( 'user_id', 'meta_key', 'meta_value' ),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'user_id' => array( 'type' => 'integer' ),
			'key'     => array( 'type' => 'string' ),
			'updated' => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $params ) {
			$user_id = intval( $params['user_id'] ?? 0 );
			if ( ! get_userdata( $user_id ) ) {
				return wp_abilities_error( 'not_found', 'User not found.' );
			}
			if ( ! current_user_can( 'edit_user', $user_id ) ) {
				return wp_abilities_error( 'rest_forbidden', 'You do not have permission to edit meta for this user.' );
			}
			$key         = sanitize_text_field( $params['meta_key'] );
			$denied_keys = array( 'wp_capabilities', 'wp_user_level', 'session_tokens', 'user_pass', 'auth_cookie' );
			if ( in_array( $key, $denied_keys, true ) ) {
				return wp_abilities_error( 'rest_forbidden', 'This meta key is protected and cannot be modified via this ability.' );
			}
			$value  = sanitize_text_field( $params['meta_value'] );
			$result = update_user_meta( $user_id, $key, $value );
			return array( 'user_id' => $user_id, 'key' => $key, 'updated' => (bool) $result );
		},
	));

	// ===== META — DELETE =====

	$reg->delete( 'meta/delete-post-meta', array(
		'label'       => 'Delete Post Meta',
		'description' => 'Delete a meta key from a post.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_id'  => array( 'type' => 'integer', 'description' => 'Post ID' ),
				'meta_key' => array( 'type' => 'string', 'description' => 'Meta key to delete' ),
			),
			'required' => array( 'post_id', 'meta_key' ),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'post_id' => array( 'type' => 'integer' ),
			'key'     => array( 'type' => 'string' ),
			'deleted' => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $params ) {
			$check = abilities_for_ai_require_editable_post( $params['post_id'] ?? 0 );
			if ( is_wp_error( $check ) ) return $check;
			$post_id = $check->ID;
			$key     = sanitize_text_field( $params['meta_key'] );
			$result  = delete_post_meta( $post_id, $key );
			return array( 'post_id' => $post_id, 'key' => $key, 'deleted' => (bool) $result );
		},
	));

	$reg->delete( 'meta/delete-term-meta', array(
		'label'       => 'Delete Term Meta',
		'description' => 'Delete a meta key from a taxonomy term.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'term_id', 'meta_key' ),
			'properties' => array(
				'term_id'  => array( 'type' => 'integer', 'description' => 'Term ID' ),
				'meta_key' => array( 'type' => 'string', 'description' => 'Meta key to delete' ),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'term_id' => array( 'type' => 'integer' ),
			'key'     => array( 'type' => 'string' ),
			'deleted' => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $params ) {
			$term_id = intval( $params['term_id'] ?? 0 );
			$term    = get_term( $term_id );
			if ( ! $term || is_wp_error( $term ) ) {
				return wp_abilities_error( 'not_found', 'Term not found.' );
			}
			$key    = sanitize_text_field( $params['meta_key'] );
			$result = delete_term_meta( $term_id, $key );
			return array( 'term_id' => $term_id, 'key' => $key, 'deleted' => (bool) $result );
		},
	));

	$reg->delete( 'meta/delete-user-meta', array(
		'label'       => 'Delete User Meta',
		'description' => 'Delete a meta key from a user. Security-sensitive keys are blocked.',
		'capability'  => 'edit_users',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'user_id', 'meta_key' ),
			'properties' => array(
				'user_id'  => array( 'type' => 'integer', 'description' => 'User ID' ),
				'meta_key' => array( 'type' => 'string', 'description' => 'Meta key to delete' ),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'user_id' => array( 'type' => 'integer' ),
			'key'     => array( 'type' => 'string' ),
			'deleted' => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $params ) {
			$user_id = intval( $params['user_id'] ?? 0 );
			if ( ! get_userdata( $user_id ) ) {
				return wp_abilities_error( 'not_found', 'User not found.' );
			}
			if ( ! current_user_can( 'edit_user', $user_id ) ) {
				return wp_abilities_error( 'rest_forbidden', 'You do not have permission to delete meta for this user.' );
			}
			$key         = sanitize_text_field( $params['meta_key'] );
			$denied_keys = array( 'wp_capabilities', 'wp_user_level', 'session_tokens', 'user_pass', 'auth_cookie' );
			if ( in_array( $key, $denied_keys, true ) ) {
				return wp_abilities_error( 'rest_forbidden', 'This meta key is protected and cannot be deleted via this ability.' );
			}
			$result = delete_user_meta( $user_id, $key );
			return array( 'user_id' => $user_id, 'key' => $key, 'deleted' => (bool) $result );
		},
	));
});
