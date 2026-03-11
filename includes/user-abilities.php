<?php
/**
 * User Abilities
 *
 * WordPress user management.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WordPress_Abilities_Suite
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {
	$reg = new WP_Abilities_Suite_Registrar( 'users', 'list_users' );

	// ===== USERS — READ =====

	$reg->read( 'users/list', array(
		'label'       => 'List Users',
		'description' => 'List WordPress users with filtering and pagination options',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'per_page' => array(
					'type'        => 'integer',
					'description' => 'Users per page',
					'default'     => 20,
					'minimum'     => 1,
					'maximum'     => 100,
				),
				'page' => array(
					'type'        => 'integer',
					'description' => 'Page number',
					'default'     => 1,
					'minimum'     => 1,
				),
				'role' => array(
					'type'        => 'string',
					'description' => 'Filter by role (e.g., "administrator", "editor", "subscriber")',
				),
				'search' => array(
					'type'        => 'string',
					'description' => 'Search by username, email, or display name',
				),
				'orderby' => array(
					'type'        => 'string',
					'enum'        => array( 'ID', 'login', 'nicename', 'email', 'registered', 'display_name' ),
					'default'     => 'registered',
					'description' => 'Field to order by',
				),
				'order' => array(
					'type'        => 'string',
					'enum'        => array( 'ASC', 'DESC' ),
					'default'     => 'DESC',
					'description' => 'Sort order',
				),
			),
		),
		'output_schema' => wp_abilities_suite_schema_list_output( 'users', array(
			'id'           => array( 'type' => 'integer' ),
			'username'     => array( 'type' => 'string' ),
			'email'        => array( 'type' => 'string' ),
			'display_name' => array( 'type' => 'string' ),
			'roles'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'registered'   => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$per_page = min( $input['per_page'] ?? 20, 100 );
			$page     = $input['page'] ?? 1;

			$args = array(
				'number'  => $per_page,
				'offset'  => ( $page - 1 ) * $per_page,
				'orderby' => $input['orderby'] ?? 'registered',
				'order'   => $input['order'] ?? 'DESC',
			);

			if ( ! empty( $input['role'] ) ) {
				$args['role'] = $input['role'];
			}

			if ( ! empty( $input['search'] ) ) {
				$args['search'] = '*' . $input['search'] . '*';
			}

			$user_query = new WP_User_Query( $args );

			$users = array();
			foreach ( $user_query->get_results() as $user ) {
				$users[] = array(
					'id'           => $user->ID,
					'username'     => $user->user_login,
					'email'        => $user->user_email,
					'display_name' => $user->display_name,
					'first_name'   => $user->first_name,
					'last_name'    => $user->last_name,
					'nickname'     => $user->nickname,
					'roles'        => $user->roles,
					'registered'   => $user->user_registered,
					'url'          => $user->user_url,
				);
			}

			return array(
				'users' => $users,
				'total' => $user_query->get_total(),
			);
		},
	) );

	$reg->read( 'users/get', array(
		'label'       => 'Get User',
		'description' => 'Get detailed information about a specific user by ID, email, or username',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'User ID',
				),
				'email' => array(
					'type'        => 'string',
					'description' => 'User email (alternative to ID)',
				),
				'username' => array(
					'type'        => 'string',
					'description' => 'Username/login (alternative to ID)',
				),
			),
		),
		'output_schema' => wp_abilities_suite_schema_item_output( array(
			'id'           => array( 'type' => 'integer' ),
			'username'     => array( 'type' => 'string' ),
			'email'        => array( 'type' => 'string' ),
			'display_name' => array( 'type' => 'string' ),
			'roles'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'registered'   => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$user = null;
			if ( ! empty( $input['id'] ) ) {
				$user = get_user_by( 'ID', $input['id'] );
			} elseif ( ! empty( $input['email'] ) ) {
				$user = get_user_by( 'email', $input['email'] );
			} elseif ( ! empty( $input['username'] ) ) {
				$user = get_user_by( 'login', $input['username'] );
			} else {
				return new WP_Error( 'ability_invalid_input', 'Provide id, email, or username' );
			}
			if ( ! $user ) {
				return new WP_Error( 'not_found', 'User not found' );
			}
			return array(
				'id'           => $user->ID,
				'username'     => $user->user_login,
				'email'        => $user->user_email,
				'display_name' => $user->display_name,
				'first_name'   => $user->first_name,
				'last_name'    => $user->last_name,
				'nickname'     => $user->nickname,
				'description'  => $user->description,
				'roles'        => $user->roles,
				'capabilities' => array_keys( array_filter( $user->allcaps ) ),
				'registered'   => $user->user_registered,
				'url'          => $user->user_url,
				'posts_count'  => count_user_posts( $user->ID ),
			);
		},
	) );

	// ===== USERS — WRITE =====

	// users/create is free — round-trip: create → test → (admin deletes).
	$reg->write( 'users/create', array(
		'tier'        => 'free',
		'capability'  => 'create_users',
		'label'       => 'Create User',
		'description' => 'Create a new WordPress user with specified details and role',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'username', 'email' ),
			'properties' => array(
				'username' => array(
					'type'        => 'string',
					'description' => 'Login username (must be unique)',
				),
				'email' => array(
					'type'        => 'string',
					'description' => 'Email address (must be unique)',
				),
				'password' => array(
					'type'        => 'string',
					'description' => 'Password (auto-generated if not provided)',
				),
				'first_name' => array(
					'type'        => 'string',
					'description' => 'First name',
				),
				'last_name' => array(
					'type'        => 'string',
					'description' => 'Last name',
				),
				'display_name' => array(
					'type'        => 'string',
					'description' => 'Display name',
				),
				'role' => array(
					'type'        => 'string',
					'default'     => 'subscriber',
					'description' => 'User role (subscriber, contributor, author, editor, administrator)',
				),
				'send_notification' => array(
					'type'        => 'boolean',
					'default'     => true,
					'description' => 'Send new user email notification',
				),
			),
		),
		'output_schema' => wp_abilities_suite_schema_success_output( array(
			'id'       => array( 'type' => 'integer' ),
			'username' => array( 'type' => 'string' ),
			'email'    => array( 'type' => 'string' ),
			'role'     => array( 'type' => 'string' ),
		) ),
		'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
		'callback' => function( $input ) {
			$role = $input['role'] ?? 'subscriber';
			if ( ! function_exists( 'get_editable_roles' ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
			}
			$editable_roles = get_editable_roles();
			if ( ! isset( $editable_roles[ $role ] ) ) {
				return new WP_Error( 'rest_forbidden', "You cannot assign the role \"{$role}\"." );
			}
			$userdata = array(
				'user_login' => sanitize_user( $input['username'] ),
				'user_email' => sanitize_email( $input['email'] ),
				'user_pass'  => $input['password'] ?? wp_generate_password(),
				'role'       => $role,
			);
			if ( ! empty( $input['first_name'] ) ) {
				$userdata['first_name'] = sanitize_text_field( $input['first_name'] );
			}
			if ( ! empty( $input['last_name'] ) ) {
				$userdata['last_name'] = sanitize_text_field( $input['last_name'] );
			}
			if ( ! empty( $input['display_name'] ) ) {
				$userdata['display_name'] = sanitize_text_field( $input['display_name'] );
			}
			$user_id = wp_insert_user( $userdata );
			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}
			if ( $input['send_notification'] ?? true ) {
				wp_new_user_notification( $user_id, null, 'both' );
			}
			$user = get_user_by( 'ID', $user_id );
			return array(
				'id'       => $user_id,
				'username' => $user->user_login,
				'email'    => $user->user_email,
				'role'     => $user->roles[0] ?? '',
			);
		},
	) );

	$reg->write( 'users/update', array(
		'capability'  => 'edit_users',
		'label'       => 'Update User',
		'description' => "Update an existing user's information and metadata",
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'User ID to update',
				),
				'email' => array(
					'type'        => 'string',
					'description' => 'New email address',
				),
				'first_name' => array(
					'type'        => 'string',
					'description' => 'First name',
				),
				'last_name' => array(
					'type'        => 'string',
					'description' => 'Last name',
				),
				'display_name' => array(
					'type'        => 'string',
					'description' => 'Display name',
				),
				'nickname' => array(
					'type'        => 'string',
					'description' => 'Nickname',
				),
				'description' => array(
					'type'        => 'string',
					'description' => 'Biographical info',
				),
				'url' => array(
					'type'        => 'string',
					'description' => 'Website URL',
				),
				'role' => array(
					'type'        => 'string',
					'description' => 'New role',
				),
			),
		),
		'output_schema' => wp_abilities_suite_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$user_id     = (int) $input['id'];
			$target_user = get_user_by( 'ID', $user_id );
			if ( ! $target_user ) {
				return new WP_Error( 'not_found', 'User not found' );
			}
			if ( ! current_user_can( 'edit_user', $user_id ) ) {
				return new WP_Error( 'rest_forbidden', 'You do not have permission to edit this user.' );
			}
			if ( isset( $input['role'] ) ) {
				if ( ! current_user_can( 'promote_user', $user_id ) ) {
					return new WP_Error( 'rest_forbidden', 'You do not have permission to change roles for this user.' );
				}
				if ( ! function_exists( 'get_editable_roles' ) ) {
					require_once ABSPATH . 'wp-admin/includes/user.php';
				}
				$editable_roles = get_editable_roles();
				$new_role       = sanitize_text_field( $input['role'] );
				if ( ! isset( $editable_roles[ $new_role ] ) ) {
					return new WP_Error( 'rest_forbidden', "You cannot assign the role \"{$new_role}\"." );
				}
			}
			$userdata = array( 'ID' => $user_id );
			if ( isset( $input['email'] ) ) {
				$userdata['user_email'] = sanitize_email( $input['email'] );
			}
			if ( isset( $input['first_name'] ) ) {
				$userdata['first_name'] = sanitize_text_field( $input['first_name'] );
			}
			if ( isset( $input['last_name'] ) ) {
				$userdata['last_name'] = sanitize_text_field( $input['last_name'] );
			}
			if ( isset( $input['display_name'] ) ) {
				$userdata['display_name'] = sanitize_text_field( $input['display_name'] );
			}
			if ( isset( $input['nickname'] ) ) {
				$userdata['nickname'] = sanitize_text_field( $input['nickname'] );
			}
			if ( isset( $input['description'] ) ) {
				$userdata['description'] = sanitize_textarea_field( $input['description'] );
			}
			if ( isset( $input['url'] ) ) {
				$userdata['user_url'] = esc_url_raw( $input['url'] );
			}
			if ( isset( $input['role'] ) ) {
				$userdata['role'] = sanitize_text_field( $input['role'] );
			}
			$result = wp_update_user( $userdata );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array( 'success' => true, 'id' => $user_id );
		},
	) );

	// ===== USERS — DELETE =====

	$reg->delete( 'users/delete', array(
		'capability'  => 'delete_users',
		'label'       => 'Delete User',
		'description' => 'Delete a WordPress user and optionally reassign their content',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'User ID to delete',
				),
				'reassign' => array(
					'type'        => 'integer',
					'description' => 'User ID to reassign posts to (null to delete posts)',
				),
			),
		),
		'output_schema' => wp_abilities_suite_schema_success_output( array(
			'message' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! function_exists( 'wp_delete_user' ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
			}
			$user_id = (int) $input['id'];
			if ( ! get_user_by( 'ID', $user_id ) ) {
				return new WP_Error( 'not_found', 'User not found' );
			}
			if ( $user_id === get_current_user_id() ) {
				return new WP_Error( 'rest_forbidden', 'Cannot delete your own account' );
			}
			$reassign = isset( $input['reassign'] ) ? (int) $input['reassign'] : null;
			$result   = wp_delete_user( $user_id, $reassign );
			if ( ! $result ) {
				return new WP_Error( 'ability_invalid_input', 'Failed to delete user' );
			}
			return array( 'success' => true, 'message' => 'User deleted successfully' );
		},
	) );
} );
