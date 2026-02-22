<?php

defined( 'ABSPATH' ) || exit;

// Register all WordPress user management abilities
add_action( 'wp_abilities_api_init', function() {

    // ===== USER ABILITIES =====

    // List users
    wp_register_ability( 'users/list', array(
        'label' => 'List Users',
        'description' => 'List WordPress users with filtering and pagination options',
        'category' => 'users',
        'input_schema' => array(
            'type' => 'object',
            'properties' => array(
                'per_page' => array(
                    'type' => 'integer',
                    'description' => 'Users per page',
                    'default' => 20,
                    'minimum' => 1,
                    'maximum' => 100
                ),
                'page' => array(
                    'type' => 'integer',
                    'description' => 'Page number',
                    'default' => 1,
                    'minimum' => 1
                ),
                'role' => array(
                    'type' => 'string',
                    'description' => 'Filter by role (e.g., "administrator", "editor", "subscriber")'
                ),
                'search' => array(
                    'type' => 'string',
                    'description' => 'Search by username, email, or display name'
                ),
                'orderby' => array(
                    'type' => 'string',
                    'enum' => array('ID', 'login', 'nicename', 'email', 'registered', 'display_name'),
                    'default' => 'registered',
                    'description' => 'Field to order by'
                ),
                'order' => array(
                    'type' => 'string',
                    'enum' => array('ASC', 'DESC'),
                    'default' => 'DESC',
                    'description' => 'Sort order'
                )
            )
        ),
        'output_schema' => array(
            'type' => 'object',
            'properties' => array(
                'users' => array('type' => 'array'),
                'total' => array('type' => 'integer')
            )
        ),
        'execute_callback' => function( $input ) {
            $per_page = min( $input['per_page'] ?? 20, 100 );
            $page = $input['page'] ?? 1;

            $args = array(
                'number' => $per_page,
                'offset' => ( $page - 1 ) * $per_page,
                'orderby' => $input['orderby'] ?? 'registered',
                'order' => $input['order'] ?? 'DESC'
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
                    'id' => $user->ID,
                    'username' => $user->user_login,
                    'email' => $user->user_email,
                    'display_name' => $user->display_name,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'nickname' => $user->nickname,
                    'roles' => $user->roles,
                    'registered' => $user->user_registered,
                    'url' => $user->user_url
                );
            }

            return array(
                'users' => $users,
                'total' => $user_query->get_total()
            );
        },
        'permission_callback' => function() {
            return current_user_can( 'list_users' );
        },
        'meta' => array(
            'annotations' => array(
                'readonly' => true,
                'destructive' => false,
                'idempotent' => true
            ),
            'show_in_rest' => true,
            'mcp' => array( 'public' => true, 'type' => 'tool' ),
                    )
    ));

    // Get user
    wp_register_ability( 'users/get', array(
        'label' => 'Get User',
        'description' => 'Get detailed information about a specific user by ID, email, or username',
        'category' => 'users',
        'input_schema' => array(
            'type' => 'object',
            'properties' => array(
                'id' => array(
                    'type' => 'integer',
                    'description' => 'User ID'
                ),
                'email' => array(
                    'type' => 'string',
                    'description' => 'User email (alternative to ID)'
                ),
                'username' => array(
                    'type' => 'string',
                    'description' => 'Username/login (alternative to ID)'
                )
            )
        ),
        'output_schema' => array(
            'type' => 'object'
        ),
        'execute_callback' => function( $input ) {
            $user = null;

            if ( ! empty( $input['id'] ) ) {
                $user = get_user_by( 'ID', $input['id'] );
            } elseif ( ! empty( $input['email'] ) ) {
                $user = get_user_by( 'email', $input['email'] );
            } elseif ( ! empty( $input['username'] ) ) {
                $user = get_user_by( 'login', $input['username'] );
            } else {
                return new WP_Error( 'missing_identifier', 'Provide id, email, or username' );
            }

            if ( ! $user ) {
                return new WP_Error( 'not_found', 'User not found' );
            }

            return array(
                'id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'display_name' => $user->display_name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'nickname' => $user->nickname,
                'description' => $user->description,
                'roles' => $user->roles,
                'capabilities' => array_keys( array_filter( $user->allcaps ) ),
                'registered' => $user->user_registered,
                'url' => $user->user_url,
                'posts_count' => count_user_posts( $user->ID )
            );
        },
        'permission_callback' => function() {
            return current_user_can( 'list_users' );
        },
        'meta' => array(
            'annotations' => array(
                'readonly' => true,
                'destructive' => false,
                'idempotent' => true
            ),
            'show_in_rest' => true,
            'mcp' => array( 'public' => true, 'type' => 'tool' ),
                    )
    ));

    // Create user
    wp_register_ability( 'users/create', array(
        'label' => 'Create User',
        'description' => 'Create a new WordPress user with specified details and role',
        'category' => 'users',
        'input_schema' => array(
            'type' => 'object',
            'required' => array('username', 'email'),
            'properties' => array(
                'username' => array(
                    'type' => 'string',
                    'description' => 'Login username (must be unique)'
                ),
                'email' => array(
                    'type' => 'string',
                    'description' => 'Email address (must be unique)'
                ),
                'password' => array(
                    'type' => 'string',
                    'description' => 'Password (auto-generated if not provided)'
                ),
                'first_name' => array(
                    'type' => 'string',
                    'description' => 'First name'
                ),
                'last_name' => array(
                    'type' => 'string',
                    'description' => 'Last name'
                ),
                'display_name' => array(
                    'type' => 'string',
                    'description' => 'Display name'
                ),
                'role' => array(
                    'type' => 'string',
                    'default' => 'subscriber',
                    'description' => 'User role (subscriber, contributor, author, editor, administrator)'
                ),
                'send_notification' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'description' => 'Send new user email notification'
                )
            )
        ),
        'output_schema' => array(
            'type' => 'object',
            'properties' => array(
                'id' => array('type' => 'integer'),
                'username' => array('type' => 'string'),
                'email' => array('type' => 'string'),
                'role' => array('type' => 'string')
            )
        ),
        'execute_callback' => function( $input ) {
            $userdata = array(
                'user_login' => sanitize_user( $input['username'] ),
                'user_email' => sanitize_email( $input['email'] ),
                'user_pass' => $input['password'] ?? wp_generate_password(),
                'role' => $input['role'] ?? 'subscriber'
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

            // Send notification if requested
            if ( $input['send_notification'] ?? true ) {
                wp_new_user_notification( $user_id, null, 'both' );
            }

            $user = get_user_by( 'ID', $user_id );

            return array(
                'id' => $user_id,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'role' => $user->roles[0] ?? ''
            );
        },
        'permission_callback' => function() {
            return current_user_can( 'create_users' );
        },
        'meta' => array(
            'annotations' => array(
                'readonly' => false,
                'destructive' => false,
                'idempotent' => false
            ),
            'show_in_rest' => true,
            'mcp' => array( 'public' => true, 'type' => 'tool' ),
                    )
    ));

    // Update user
    wp_register_ability( 'users/update', array(
        'label' => 'Update User',
        'description' => 'Update an existing user\'s information and metadata',
        'category' => 'users',
        'input_schema' => array(
            'type' => 'object',
            'required' => array('id'),
            'properties' => array(
                'id' => array(
                    'type' => 'integer',
                    'description' => 'User ID to update'
                ),
                'email' => array(
                    'type' => 'string',
                    'description' => 'New email address'
                ),
                'first_name' => array(
                    'type' => 'string',
                    'description' => 'First name'
                ),
                'last_name' => array(
                    'type' => 'string',
                    'description' => 'Last name'
                ),
                'display_name' => array(
                    'type' => 'string',
                    'description' => 'Display name'
                ),
                'nickname' => array(
                    'type' => 'string',
                    'description' => 'Nickname'
                ),
                'description' => array(
                    'type' => 'string',
                    'description' => 'Biographical info'
                ),
                'url' => array(
                    'type' => 'string',
                    'description' => 'Website URL'
                ),
                'role' => array(
                    'type' => 'string',
                    'description' => 'New role'
                )
            )
        ),
        'output_schema' => array(
            'type' => 'object',
            'properties' => array(
                'success' => array('type' => 'boolean'),
                'id' => array('type' => 'integer')
            )
        ),
        'execute_callback' => function( $input ) {
            $user_id = (int) $input['id'];

            if ( ! get_user_by( 'ID', $user_id ) ) {
                return new WP_Error( 'not_found', 'User not found' );
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

            return array(
                'success' => true,
                'id' => $user_id
            );
        },
        'permission_callback' => function() {
            return current_user_can( 'edit_users' );
        },
        'meta' => array(
            'annotations' => array(
                'readonly' => false,
                'destructive' => false,
                'idempotent' => true
            ),
            'show_in_rest' => true,
            'mcp' => array( 'public' => true, 'type' => 'tool' ),
                    )
    ));

    // Delete user
    wp_register_ability( 'users/delete', array(
        'label' => 'Delete User',
        'description' => 'Delete a WordPress user and optionally reassign their content',
        'category' => 'users',
        'input_schema' => array(
            'type' => 'object',
            'required' => array('id'),
            'properties' => array(
                'id' => array(
                    'type' => 'integer',
                    'description' => 'User ID to delete'
                ),
                'reassign' => array(
                    'type' => 'integer',
                    'description' => 'User ID to reassign posts to (null to delete posts)'
                )
            )
        ),
        'output_schema' => array(
            'type' => 'object',
            'properties' => array(
                'success' => array('type' => 'boolean'),
                'message' => array('type' => 'string')
            )
        ),
        'execute_callback' => function( $input ) {
            if ( ! function_exists( 'wp_delete_user' ) ) {
                require_once ABSPATH . 'wp-admin/includes/user.php';
            }

            $user_id = (int) $input['id'];

            if ( ! get_user_by( 'ID', $user_id ) ) {
                return new WP_Error( 'not_found', 'User not found' );
            }

            // Prevent deleting yourself
            if ( $user_id === get_current_user_id() ) {
                return new WP_Error( 'cannot_delete_self', 'Cannot delete your own account' );
            }

            $reassign = isset( $input['reassign'] ) ? (int) $input['reassign'] : null;

            $result = wp_delete_user( $user_id, $reassign );

            if ( ! $result ) {
                return new WP_Error( 'delete_failed', 'Failed to delete user' );
            }

            return array(
                'success' => true,
                'message' => 'User deleted successfully'
            );
        },
        'permission_callback' => function() {
            return current_user_can( 'delete_users' );
        },
        'meta' => array(
            'annotations' => array(
                'readonly' => false,
                'destructive' => true,
                'idempotent' => true
            ),
            'show_in_rest' => true,
            'mcp' => array( 'public' => true, 'type' => 'tool' ),
                    )
    ));

    error_log( 'WordPress User Abilities: Registered 5 user management abilities' );

}, 100 );
