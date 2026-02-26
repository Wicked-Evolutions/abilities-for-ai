<?php
/**
 * Meta Fields Abilities
 *
 * Post, term, and user meta CRUD + registered meta key listing.
 *
 * @package WordPress_Native_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', 'wp_native_register_meta_abilities' );

function wp_native_register_meta_abilities() {

	$perms = wp_abilities_suite_get_permissions( 'meta' );

	// ===== META — READ =====
	if ( $perms['read'] ) {

	// ---- meta/list-post-meta ----
	wp_register_ability( 'meta/list-post-meta', array(
		'label'       => 'List Post Meta',
		'description' => 'List all meta fields for a post.',
		'category'    => 'meta',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_id' => array( 'type' => 'integer', 'description' => 'Post ID' ),
			),
			'required' => array( 'post_id' ),
		),
		'execute_callback' => function( $params ) {
			$check = wp_abilities_suite_require_editable_post( $params['post_id'] ?? 0 );
			if ( is_wp_error( $check ) ) return $check;
			$post_id = $check->ID;
			$meta = get_post_meta( $post_id );
			$result = array();
			foreach ( $meta as $key => $values ) {
				$result[] = array(
					'key'    => $key,
					'values' => $values,
					'count'  => count( $values ),
				);
			}
			return array( 'post_id' => $post_id, 'meta_count' => count( $result ), 'meta' => $result );
		},
		'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
	));

	// ---- meta/get-post-meta ----
	wp_register_ability( 'meta/get-post-meta', array(
		'label'       => 'Get Post Meta',
		'description' => 'Get a specific meta value for a post.',
		'category'    => 'meta',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_id'  => array( 'type' => 'integer', 'description' => 'Post ID' ),
				'meta_key' => array( 'type' => 'string', 'description' => 'Meta key to retrieve' ),
				'single'   => array( 'type' => 'boolean', 'description' => 'Return single value (default: true)', 'default' => true ),
			),
			'required' => array( 'post_id', 'meta_key' ),
		),
		'execute_callback' => function( $params ) {
			$check = wp_abilities_suite_require_editable_post( $params['post_id'] ?? 0 );
			if ( is_wp_error( $check ) ) return $check;
			$post_id = $check->ID;
			$single = $params['single'] ?? true;
			$value  = get_post_meta( $post_id, sanitize_text_field( $params['meta_key'] ), $single );
			return array( 'post_id' => $post_id, 'key' => $params['meta_key'], 'value' => $value );
		},
		'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
	));

	// ---- meta/list-term-meta ----
	wp_register_ability( 'meta/list-term-meta', array(
		'label'       => 'List Term Meta',
		'description' => 'List all meta fields for a taxonomy term.',
		'category'    => 'meta',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'term_id' => array( 'type' => 'integer', 'description' => 'Term ID' ),
			),
			'required' => array( 'term_id' ),
		),
		'execute_callback' => function( $params ) {
			$term_id = intval( $params['term_id'] ?? 0 );
			$term    = get_term( $term_id );
			if ( ! $term || is_wp_error( $term ) ) {
				return wp_native_error( 'not_found', 'Term not found.' );
			}
			$meta = get_term_meta( $term_id );
			$result = array();
			foreach ( $meta as $key => $values ) {
				$result[] = array( 'key' => $key, 'values' => $values, 'count' => count( $values ) );
			}
			return array( 'term_id' => $term_id, 'meta_count' => count( $result ), 'meta' => $result );
		},
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
	));

	// ---- meta/get-term-meta ----
	wp_register_ability( 'meta/get-term-meta', array(
		'label'       => 'Get Term Meta',
		'description' => 'Get a specific meta value for a taxonomy term.',
		'category'    => 'meta',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'term_id'  => array( 'type' => 'integer', 'description' => 'Term ID' ),
				'meta_key' => array( 'type' => 'string', 'description' => 'Meta key' ),
				'single'   => array( 'type' => 'boolean', 'description' => 'Return single value (default: true)', 'default' => true ),
			),
			'required' => array( 'term_id', 'meta_key' ),
		),
		'execute_callback' => function( $params ) {
			$term_id = intval( $params['term_id'] ?? 0 );
			$term    = get_term( $term_id );
			if ( ! $term || is_wp_error( $term ) ) {
				return wp_native_error( 'not_found', 'Term not found.' );
			}
			$single = $params['single'] ?? true;
			$value  = get_term_meta( $term_id, sanitize_text_field( $params['meta_key'] ), $single );
			return array( 'term_id' => $term_id, 'key' => $params['meta_key'], 'value' => $value );
		},
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
	));

	// ---- meta/list-user-meta ----
	wp_register_ability( 'meta/list-user-meta', array(
		'label'       => 'List User Meta',
		'description' => 'List all meta fields for a user.',
		'category'    => 'meta',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'user_id' => array( 'type' => 'integer', 'description' => 'User ID' ),
			),
			'required' => array( 'user_id' ),
		),
		'execute_callback' => function( $params ) {
			$user_id = intval( $params['user_id'] ?? 0 );
			$user    = get_userdata( $user_id );
			if ( ! $user ) {
				return wp_native_error( 'not_found', 'User not found.' );
			}
			$meta = get_user_meta( $user_id );
			$result = array();
			foreach ( $meta as $key => $values ) {
				$result[] = array( 'key' => $key, 'values' => $values, 'count' => count( $values ) );
			}
			return array( 'user_id' => $user_id, 'meta_count' => count( $result ), 'meta' => $result );
		},
		'permission_callback' => function() { return current_user_can( 'list_users' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
	));

	// ---- meta/get-user-meta ----
	wp_register_ability( 'meta/get-user-meta', array(
		'label'       => 'Get User Meta',
		'description' => 'Get a specific meta value for a user.',
		'category'    => 'meta',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'user_id'  => array( 'type' => 'integer', 'description' => 'User ID' ),
				'meta_key' => array( 'type' => 'string', 'description' => 'Meta key' ),
				'single'   => array( 'type' => 'boolean', 'description' => 'Return single value (default: true)', 'default' => true ),
			),
			'required' => array( 'user_id', 'meta_key' ),
		),
		'execute_callback' => function( $params ) {
			$user_id = intval( $params['user_id'] ?? 0 );
			if ( ! get_userdata( $user_id ) ) {
				return wp_native_error( 'not_found', 'User not found.' );
			}
			$single = $params['single'] ?? true;
			$value  = get_user_meta( $user_id, sanitize_text_field( $params['meta_key'] ), $single );
			return array( 'user_id' => $user_id, 'key' => $params['meta_key'], 'value' => $value );
		},
		'permission_callback' => function() { return current_user_can( 'list_users' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
	));

	// ---- meta/list-registered ----
	wp_register_ability( 'meta/list-registered', array(
		'label'       => 'List Registered Meta Keys',
		'description' => 'List all meta keys registered via register_meta() for a given object type.',
		'category'    => 'meta',
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
		'execute_callback' => function( $params ) {
			$object_type    = sanitize_text_field( $params['object_type'] ?? 'post' );
			$object_subtype = sanitize_text_field( $params['object_subtype'] ?? '' );
			$keys = get_registered_meta_keys( $object_type, $object_subtype );
			$result = array();
			foreach ( $keys as $key => $args ) {
				$result[] = array(
					'key'           => $key,
					'type'          => $args['type'] ?? 'string',
					'description'   => $args['description'] ?? '',
					'single'        => $args['single'] ?? false,
					'show_in_rest'  => ! empty( $args['show_in_rest'] ),
				);
			}
			return array(
				'object_type'    => $object_type,
				'object_subtype' => $object_subtype,
				'count'          => count( $result ),
				'keys'           => $result,
			);
		},
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
	));

	} // end read

	// ===== META — WRITE =====
	if ( ! empty( $perms['write'] ) ) {

	// ---- meta/update-post-meta ----
	wp_register_ability( 'meta/update-post-meta', array(
		'label'       => 'Update Post Meta',
		'description' => 'Set or update a meta value for a post.',
		'category'    => 'meta',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_id'    => array( 'type' => 'integer', 'description' => 'Post ID' ),
				'meta_key'   => array( 'type' => 'string', 'description' => 'Meta key' ),
				'meta_value' => array( 'type' => 'string', 'description' => 'Meta value to set' ),
			),
			'required' => array( 'post_id', 'meta_key', 'meta_value' ),
		),
		'execute_callback' => function( $params ) {
			$check = wp_abilities_suite_require_editable_post( $params['post_id'] ?? 0 );
			if ( is_wp_error( $check ) ) return $check;
			$post_id = $check->ID;
			$key    = sanitize_text_field( $params['meta_key'] );
			$value  = sanitize_text_field( $params['meta_value'] );
			$result = update_post_meta( $post_id, $key, $value );
			return array( 'post_id' => $post_id, 'key' => $key, 'updated' => (bool) $result );
		},
		'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ),
	));

	// ---- meta/update-term-meta ----
	wp_register_ability( 'meta/update-term-meta', array(
		'label'       => 'Update Term Meta',
		'description' => 'Set or update a meta value for a taxonomy term.',
		'category'    => 'meta',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'term_id'    => array( 'type' => 'integer', 'description' => 'Term ID' ),
				'meta_key'   => array( 'type' => 'string', 'description' => 'Meta key' ),
				'meta_value' => array( 'type' => 'string', 'description' => 'Meta value' ),
			),
			'required' => array( 'term_id', 'meta_key', 'meta_value' ),
		),
		'execute_callback' => function( $params ) {
			$term_id = intval( $params['term_id'] ?? 0 );
			$term    = get_term( $term_id );
			if ( ! $term || is_wp_error( $term ) ) {
				return wp_native_error( 'not_found', 'Term not found.' );
			}
			$key    = sanitize_text_field( $params['meta_key'] );
			$value  = sanitize_text_field( $params['meta_value'] );
			$result = update_term_meta( $term_id, $key, $value );
			return array( 'term_id' => $term_id, 'key' => $key, 'updated' => ! is_wp_error( $result ) );
		},
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ),
	));

	// ---- meta/update-user-meta ----
	wp_register_ability( 'meta/update-user-meta', array(
		'label'       => 'Update User Meta',
		'description' => 'Set or update a meta value for a user.',
		'category'    => 'meta',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'user_id'    => array( 'type' => 'integer', 'description' => 'User ID' ),
				'meta_key'   => array( 'type' => 'string', 'description' => 'Meta key' ),
				'meta_value' => array( 'type' => 'string', 'description' => 'Meta value' ),
			),
			'required' => array( 'user_id', 'meta_key', 'meta_value' ),
		),
		'execute_callback' => function( $params ) {
			$user_id = intval( $params['user_id'] ?? 0 );
			if ( ! get_userdata( $user_id ) ) {
				return wp_native_error( 'not_found', 'User not found.' );
			}
			$key    = sanitize_text_field( $params['meta_key'] );
			$value  = sanitize_text_field( $params['meta_value'] );
			$result = update_user_meta( $user_id, $key, $value );
			return array( 'user_id' => $user_id, 'key' => $key, 'updated' => (bool) $result );
		},
		'permission_callback' => function() { return current_user_can( 'edit_users' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ),
	));

	} // end write

	// ===== META — DELETE =====
	if ( ! empty( $perms['delete'] ) ) {

	// ---- meta/delete-post-meta ----
	wp_register_ability( 'meta/delete-post-meta', array(
		'label'       => 'Delete Post Meta',
		'description' => 'Delete a meta key from a post.',
		'category'    => 'meta',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_id'  => array( 'type' => 'integer', 'description' => 'Post ID' ),
				'meta_key' => array( 'type' => 'string', 'description' => 'Meta key to delete' ),
			),
			'required' => array( 'post_id', 'meta_key' ),
		),
		'execute_callback' => function( $params ) {
			$check = wp_abilities_suite_require_editable_post( $params['post_id'] ?? 0 );
			if ( is_wp_error( $check ) ) return $check;
			$post_id = $check->ID;
			$key    = sanitize_text_field( $params['meta_key'] );
			$result = delete_post_meta( $post_id, $key );
			return array( 'post_id' => $post_id, 'key' => $key, 'deleted' => (bool) $result );
		},
		'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => true ) ),
	));

	} // end delete
}
