<?php

defined( 'ABSPATH' ) || exit;

// Register all WordPress content management abilities
add_action( 'wp_abilities_api_init', function() {

    // ===== CONTENT ABILITIES =====

    // List content
    wp_register_ability( 'content/list', array(
        'label' => 'List Content',
        'description' => 'List posts, pages, or custom post types with filtering and pagination',
        'category' => 'content',
        'input_schema' => array(
            'type' => 'object',
            'properties' => array(
                'post_type' => array(
                    'type' => 'string',
                    'description' => 'Post type to list (post, page, or custom post type)',
                    'default' => 'post'
                ),
                'posts_per_page' => array(
                    'type' => 'integer',
                    'description' => 'Number of posts to return',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 100
                ),
                'paged' => array(
                    'type' => 'integer',
                    'description' => 'Page number for pagination',
                    'default' => 1,
                    'minimum' => 1
                ),
                'post_status' => array(
                    'type' => 'string',
                    'description' => 'Post status (publish, draft, pending, etc.)',
                    'default' => 'publish'
                ),
                's' => array(
                    'type' => 'string',
                    'description' => 'Search query'
                ),
                'orderby' => array(
                    'type' => 'string',
                    'description' => 'Order by field (date, title, modified, etc.)',
                    'default' => 'date'
                ),
                'order' => array(
                    'type' => 'string',
                    'description' => 'Order direction (ASC or DESC)',
                    'default' => 'DESC',
                    'enum' => array('ASC', 'DESC')
                )
            )
        ),
        'output_schema' => array(
            'type' => 'object',
            'properties' => array(
                'posts' => array('type' => 'array'),
                'total' => array('type' => 'integer'),
                'pages' => array('type' => 'integer')
            )
        ),
        'execute_callback' => function( $input ) {
            $args = array(
                'post_type' => $input['post_type'] ?? 'post',
                'posts_per_page' => $input['posts_per_page'] ?? 10,
                'paged' => $input['paged'] ?? 1,
                'post_status' => $input['post_status'] ?? 'publish',
                'orderby' => $input['orderby'] ?? 'date',
                'order' => $input['order'] ?? 'DESC'
            );

            if ( !empty( $input['s'] ) ) {
                $args['s'] = $input['s'];
            }

            $query = new WP_Query( $args );

            $posts = array_map( function( $post ) {
                return array(
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'content' => $post->post_content,
                    'excerpt' => $post->post_excerpt,
                    'status' => $post->post_status,
                    'type' => $post->post_type,
                    'date' => $post->post_date,
                    'modified' => $post->post_modified,
                    'author' => $post->post_author,
                    'slug' => $post->post_name,
                    'link' => get_permalink( $post->ID )
                );
            }, $query->posts );

            return array(
                'posts' => $posts,
                'total' => $query->found_posts,
                'pages' => $query->max_num_pages
            );
        },
        'permission_callback' => function() {
            return current_user_can( 'edit_posts' );
        },
        'meta' => array(
            'annotations' => array(
                'readonly' => true,
                'destructive' => false,
                'idempotent' => true
            ),
            'show_in_rest' => true
                    )
    ));

    // Get content
    wp_register_ability( 'content/get', array(
        'label' => 'Get Content',
        'description' => 'Get a specific post, page, or custom post type by ID',
        'category' => 'content',
        'input_schema' => array(
            'type' => 'object',
            'required' => array('id'),
            'properties' => array(
                'id' => array(
                    'type' => 'integer',
                    'description' => 'Post ID'
                )
            )
        ),
        'output_schema' => array(
            'type' => 'object'
        ),
        'execute_callback' => function( $input ) {
            $post = get_post( $input['id'] );

            if ( !$post ) {
                return new WP_Error( 'not_found', 'Post not found' );
            }

            return array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'content' => $post->post_content,
                'excerpt' => $post->post_excerpt,
                'status' => $post->post_status,
                'type' => $post->post_type,
                'date' => $post->post_date,
                'modified' => $post->post_modified,
                'author' => $post->post_author,
                'slug' => $post->post_name,
                'link' => get_permalink( $post->ID ),
                'featured_image' => get_post_thumbnail_id( $post->ID )
            );
        },
        'permission_callback' => function() {
            return current_user_can( 'edit_posts' );
        },
        'meta' => array(
            'annotations' => array(
                'readonly' => true,
                'destructive' => false,
                'idempotent' => true
            ),
            'show_in_rest' => true
                    )
    ));

    // Create content
    wp_register_ability( 'content/create', array(
        'label' => 'Create Content',
        'description' => 'Create new content (posts, pages, custom post types)',
        'category' => 'content',
        'input_schema' => array(
            'type' => 'object',
            'required' => array('title'),
            'properties' => array(
                'title' => array(
                    'type' => 'string',
                    'description' => 'Post title'
                ),
                'content' => array(
                    'type' => 'string',
                    'description' => 'Post content'
                ),
                'post_type' => array(
                    'type' => 'string',
                    'description' => 'Post type',
                    'default' => 'post'
                ),
                'status' => array(
                    'type' => 'string',
                    'description' => 'Post status',
                    'default' => 'draft',
                    'enum' => array('publish', 'draft', 'pending', 'private')
                ),
                'excerpt' => array(
                    'type' => 'string',
                    'description' => 'Post excerpt'
                )
            )
        ),
        'output_schema' => array(
            'type' => 'object',
            'properties' => array(
                'id' => array('type' => 'integer'),
                'link' => array('type' => 'string')
            )
        ),
        'execute_callback' => function( $input ) {
            $post_data = array(
                'post_title' => $input['title'],
                'post_content' => $input['content'] ?? '',
                'post_type' => $input['post_type'] ?? 'post',
                'post_status' => $input['status'] ?? 'draft',
                'post_excerpt' => $input['excerpt'] ?? ''
            );

            $post_id = wp_insert_post( $post_data );

            if ( is_wp_error( $post_id ) ) {
                return $post_id;
            }

            return array(
                'id' => $post_id,
                'link' => get_permalink( $post_id )
            );
        },
        'permission_callback' => function() {
            return current_user_can( 'edit_posts' );
        },
        'meta' => array(
            'annotations' => array(
                'readonly' => false,
                'destructive' => false,
                'idempotent' => false
            ),
            'show_in_rest' => true
                    )
    ));

    // Update content
    wp_register_ability( 'content/update', array(
        'label' => 'Update Content',
        'description' => 'Update existing content',
        'category' => 'content',
        'input_schema' => array(
            'type' => 'object',
            'required' => array('id'),
            'properties' => array(
                'id' => array(
                    'type' => 'integer',
                    'description' => 'Post ID'
                ),
                'title' => array(
                    'type' => 'string',
                    'description' => 'Post title'
                ),
                'content' => array(
                    'type' => 'string',
                    'description' => 'Post content'
                ),
                'status' => array(
                    'type' => 'string',
                    'description' => 'Post status'
                ),
                'excerpt' => array(
                    'type' => 'string',
                    'description' => 'Post excerpt'
                )
            )
        ),
        'output_schema' => array(
            'type' => 'object',
            'properties' => array(
                'id' => array('type' => 'integer'),
                'success' => array('type' => 'boolean')
            )
        ),
        'execute_callback' => function( $input ) {
            $post_data = array(
                'ID' => $input['id']
            );

            if ( isset( $input['title'] ) ) $post_data['post_title'] = $input['title'];
            if ( isset( $input['content'] ) ) $post_data['post_content'] = $input['content'];
            if ( isset( $input['status'] ) ) $post_data['post_status'] = $input['status'];
            if ( isset( $input['excerpt'] ) ) $post_data['post_excerpt'] = $input['excerpt'];

            $result = wp_update_post( $post_data );

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            return array(
                'id' => $result,
                'success' => true
            );
        },
        'permission_callback' => function() {
            return current_user_can( 'edit_posts' );
        },
        'meta' => array(
            'annotations' => array(
                'readonly' => false,
                'destructive' => false,
                'idempotent' => true
            ),
            'show_in_rest' => true
                    )
    ));

    // Delete content
    wp_register_ability( 'content/delete', array(
        'label' => 'Delete Content',
        'description' => 'Delete content (move to trash or permanently delete)',
        'category' => 'content',
        'input_schema' => array(
            'type' => 'object',
            'required' => array('id'),
            'properties' => array(
                'id' => array(
                    'type' => 'integer',
                    'description' => 'Post ID'
                ),
                'force' => array(
                    'type' => 'boolean',
                    'description' => 'Whether to bypass trash and force deletion',
                    'default' => false
                )
            )
        ),
        'output_schema' => array(
            'type' => 'object',
            'properties' => array(
                'success' => array('type' => 'boolean'),
                'deleted' => array('type' => 'boolean')
            )
        ),
        'execute_callback' => function( $input ) {
            $result = wp_delete_post( $input['id'], $input['force'] ?? false );

            if ( !$result ) {
                return new WP_Error( 'delete_failed', 'Failed to delete post' );
            }

            return array(
                'success' => true,
                'deleted' => (bool) $result
            );
        },
        'permission_callback' => function() {
            return current_user_can( 'delete_posts' );
        },
        'meta' => array(
            'annotations' => array(
                'readonly' => false,
                'destructive' => true,
                'idempotent' => false
            ),
            'show_in_rest' => true
                    )
    ));

    // Discover content types
    wp_register_ability( 'content/discover-types', array(
        'label' => 'Discover Content Types',
        'description' => 'Discover all available post types',
        'category' => 'content',
        'input_schema' => array(
            'type' => 'object',
            'properties' => array(
                'public' => array(
                    'type' => 'boolean',
                    'description' => 'Only return public post types',
                    'default' => true
                )
            )
        ),
        'output_schema' => array(
            'type' => 'array'
        ),
        'execute_callback' => function( $input ) {
            $args = array();
            if ( isset( $input['public'] ) && $input['public'] ) {
                $args['public'] = true;
            }

            $post_types = get_post_types( $args, 'objects' );

            return array_map( function( $post_type ) {
                return array(
                    'name' => $post_type->name,
                    'label' => $post_type->label,
                    'description' => $post_type->description,
                    'public' => $post_type->public,
                    'hierarchical' => $post_type->hierarchical,
                    'rest_base' => $post_type->rest_base,
                    'supports' => get_all_post_type_supports( $post_type->name )
                );
            }, $post_types );
        },
        'permission_callback' => function() {
            return current_user_can( 'edit_posts' );
        },
        'meta' => array(
            'annotations' => array(
                'readonly' => true,
                'destructive' => false,
                'idempotent' => true
            ),
            'show_in_rest' => true
                    )
    ));

    // Find content by URL
    wp_register_ability( 'content/find-by-url', array(
        'label' => 'Find Content by URL',
        'description' => 'Find a post, page, or custom post type by its URL',
        'category' => 'content',
        'input_schema' => array(
            'type' => 'object',
            'required' => array('url'),
            'properties' => array(
                'url' => array(
                    'type' => 'string',
                    'description' => 'Full URL or path to the content'
                )
            )
        ),
        'output_schema' => array(
            'type' => 'object'
        ),
        'execute_callback' => function( $input ) {
            $url = $input['url'];

            // Get post ID from URL
            $post_id = url_to_postid( $url );

            if ( !$post_id ) {
                return new WP_Error( 'not_found', 'No content found for this URL' );
            }

            $post = get_post( $post_id );

            if ( !$post ) {
                return new WP_Error( 'not_found', 'Post not found' );
            }

            return array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'content' => $post->post_content,
                'excerpt' => $post->post_excerpt,
                'status' => $post->post_status,
                'type' => $post->post_type,
                'date' => $post->post_date,
                'modified' => $post->post_modified,
                'author' => $post->post_author,
                'slug' => $post->post_name,
                'link' => get_permalink( $post->ID ),
                'featured_image' => get_post_thumbnail_id( $post->ID )
            );
        },
        'permission_callback' => function() {
            return current_user_can( 'edit_posts' );
        },
        'meta' => array(
            'annotations' => array(
                'readonly' => true,
                'destructive' => false,
                'idempotent' => true
            ),
            'show_in_rest' => true
                    )
    ));

    // Get content by slug
    wp_register_ability( 'content/get-by-slug', array(
        'label' => 'Get Content by Slug',
        'description' => 'Get a post, page, or custom post type by its slug',
        'category' => 'content',
        'input_schema' => array(
            'type' => 'object',
            'required' => array('slug'),
            'properties' => array(
                'slug' => array(
                    'type' => 'string',
                    'description' => 'Post slug (post_name)'
                ),
                'post_type' => array(
                    'type' => 'string',
                    'description' => 'Post type to search in',
                    'default' => 'post'
                )
            )
        ),
        'output_schema' => array(
            'type' => 'object'
        ),
        'execute_callback' => function( $input ) {
            $args = array(
                'name' => $input['slug'],
                'post_type' => $input['post_type'] ?? 'post',
                'posts_per_page' => 1
            );

            $query = new WP_Query( $args );

            if ( !$query->have_posts() ) {
                return new WP_Error( 'not_found', 'No content found with this slug' );
            }

            $post = $query->posts[0];

            return array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'content' => $post->post_content,
                'excerpt' => $post->post_excerpt,
                'status' => $post->post_status,
                'type' => $post->post_type,
                'date' => $post->post_date,
                'modified' => $post->post_modified,
                'author' => $post->post_author,
                'slug' => $post->post_name,
                'link' => get_permalink( $post->ID ),
                'featured_image' => get_post_thumbnail_id( $post->ID )
            );
        },
        'permission_callback' => function() {
            return current_user_can( 'edit_posts' );
        },
        'meta' => array(
            'annotations' => array(
                'readonly' => true,
                'destructive' => false,
                'idempotent' => true
            ),
            'show_in_rest' => true
                    )
    ));

    error_log( 'WordPress Content Abilities: Registered 8 content management abilities' );

}, 100 );
