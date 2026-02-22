<?php
/**
 * Content Abilities - Version 2 (Using Named Functions)
 * Testing if anonymous functions are causing hook registration issues
 */

defined( 'ABSPATH' ) || exit;

// Register all content abilities using named function
add_action( 'wp_abilities_api_init', 'wp_abilities_suite_register_content_abilities', 100 );

/**
 * Register all content management abilities
 */
function wp_abilities_suite_register_content_abilities() {

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
        'execute_callback' => 'wp_abilities_suite_content_list',
        'permission_callback' => 'wp_abilities_suite_can_edit_posts',
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
        'execute_callback' => 'wp_abilities_suite_content_get',
        'permission_callback' => 'wp_abilities_suite_can_edit_posts',
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

    error_log( 'WordPress Content Abilities V2: Registered 2 content management abilities using named functions' );
}

/**
 * Callback: List content
 */
function wp_abilities_suite_content_list( $input ) {
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
}

/**
 * Callback: Get content by ID
 */
function wp_abilities_suite_content_get( $input ) {
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
}

/**
 * Permission callback: Can edit posts
 */
function wp_abilities_suite_can_edit_posts() {
    return current_user_can( 'edit_posts' );
}
