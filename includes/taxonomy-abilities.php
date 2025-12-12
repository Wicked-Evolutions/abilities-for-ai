<?php

add_action( 'wp_abilities_api_init', function() {

    // ===== TAXONOMY ABILITIES =====

    // Discover taxonomies
    wp_register_ability( 'taxonomies/discover', array(
        'label' => 'Discover Taxonomies',
        'description' => 'List all available taxonomies with their configuration',
        'category' => 'taxonomies',
        'input_schema' => array(
            'type' => 'object',
            'properties' => array(
                'public' => array(
                    'type' => 'boolean',
                    'description' => 'Only return public taxonomies',
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

            $taxonomies = get_taxonomies( $args, 'objects' );

            return array_map( function( $taxonomy ) {
                return array(
                    'name' => $taxonomy->name,
                    'label' => $taxonomy->label,
                    'description' => $taxonomy->description,
                    'hierarchical' => $taxonomy->hierarchical,
                    'public' => $taxonomy->public,
                    'show_ui' => $taxonomy->show_ui,
                    'rest_base' => $taxonomy->rest_base,
                    'object_type' => $taxonomy->object_type
                );
            }, $taxonomies );
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

    // List terms
    wp_register_ability( 'taxonomies/list-terms', array(
        'label' => 'List Terms',
        'description' => 'List terms in a specific taxonomy with filtering and pagination',
        'category' => 'taxonomies',
        'input_schema' => array(
            'type' => 'object',
            'required' => array('taxonomy'),
            'properties' => array(
                'taxonomy' => array(
                    'type' => 'string',
                    'description' => 'Taxonomy name (e.g., category, post_tag)'
                ),
                'number' => array(
                    'type' => 'integer',
                    'description' => 'Maximum number of terms to return',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 100
                ),
                'offset' => array(
                    'type' => 'integer',
                    'description' => 'Number of terms to skip',
                    'default' => 0,
                    'minimum' => 0
                ),
                'search' => array(
                    'type' => 'string',
                    'description' => 'Search term names'
                ),
                'hide_empty' => array(
                    'type' => 'boolean',
                    'description' => 'Hide terms with no posts',
                    'default' => false
                ),
                'parent' => array(
                    'type' => 'integer',
                    'description' => 'Get direct children of this term ID (for hierarchical taxonomies)'
                )
            )
        ),
        'output_schema' => array(
            'type' => 'object',
            'properties' => array(
                'terms' => array('type' => 'array'),
                'total' => array('type' => 'integer')
            )
        ),
        'execute_callback' => function( $input ) {
            $args = array(
                'taxonomy' => $input['taxonomy'],
                'number' => $input['number'] ?? 10,
                'offset' => $input['offset'] ?? 0,
                'hide_empty' => $input['hide_empty'] ?? false
            );

            if ( !empty( $input['search'] ) ) {
                $args['search'] = $input['search'];
            }

            if ( isset( $input['parent'] ) ) {
                $args['parent'] = $input['parent'];
            }

            $terms = get_terms( $args );

            if ( is_wp_error( $terms ) ) {
                return $terms;
            }

            $count_args = $args;
            $count_args['number'] = 0;
            $count_args['offset'] = 0;
            $count_args['fields'] = 'count';
            $total = get_terms( $count_args );

            $formatted_terms = array_map( function( $term ) {
                return array(
                    'term_id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'description' => $term->description,
                    'count' => $term->count,
                    'parent' => $term->parent,
                    'taxonomy' => $term->taxonomy,
                    'link' => get_term_link( $term )
                );
            }, $terms );

            return array(
                'terms' => $formatted_terms,
                'total' => $total
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

    // Get term
    wp_register_ability( 'taxonomies/get-term', array(
        'label' => 'Get Term',
        'description' => 'Get a specific term by ID',
        'category' => 'taxonomies',
        'input_schema' => array(
            'type' => 'object',
            'required' => array('term_id', 'taxonomy'),
            'properties' => array(
                'term_id' => array(
                    'type' => 'integer',
                    'description' => 'Term ID'
                ),
                'taxonomy' => array(
                    'type' => 'string',
                    'description' => 'Taxonomy name'
                )
            )
        ),
        'output_schema' => array(
            'type' => 'object'
        ),
        'execute_callback' => function( $input ) {
            $term = get_term( $input['term_id'], $input['taxonomy'] );

            if ( is_wp_error( $term ) || !$term ) {
                return new WP_Error( 'not_found', 'Term not found' );
            }

            return array(
                'term_id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'description' => $term->description,
                'count' => $term->count,
                'parent' => $term->parent,
                'taxonomy' => $term->taxonomy,
                'link' => get_term_link( $term ),
                'meta' => get_term_meta( $term->term_id )
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

    // Create term
    wp_register_ability( 'taxonomies/create-term', array(
        'label' => 'Create Term',
        'description' => 'Create a new term in a taxonomy',
        'category' => 'taxonomies',
        'input_schema' => array(
            'type' => 'object',
            'required' => array('name', 'taxonomy'),
            'properties' => array(
                'name' => array(
                    'type' => 'string',
                    'description' => 'Term name'
                ),
                'taxonomy' => array(
                    'type' => 'string',
                    'description' => 'Taxonomy name'
                ),
                'slug' => array(
                    'type' => 'string',
                    'description' => 'Term slug (optional, will be generated from name if not provided)'
                ),
                'description' => array(
                    'type' => 'string',
                    'description' => 'Term description'
                ),
                'parent' => array(
                    'type' => 'integer',
                    'description' => 'Parent term ID (for hierarchical taxonomies)'
                )
            )
        ),
        'output_schema' => array(
            'type' => 'object',
            'properties' => array(
                'term_id' => array('type' => 'integer'),
                'term_taxonomy_id' => array('type' => 'integer')
            )
        ),
        'execute_callback' => function( $input ) {
            $args = array();

            if ( !empty( $input['slug'] ) ) {
                $args['slug'] = $input['slug'];
            }

            if ( !empty( $input['description'] ) ) {
                $args['description'] = $input['description'];
            }

            if ( isset( $input['parent'] ) ) {
                $args['parent'] = $input['parent'];
            }

            $result = wp_insert_term( $input['name'], $input['taxonomy'], $args );

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            return array(
                'term_id' => $result['term_id'],
                'term_taxonomy_id' => $result['term_taxonomy_id']
            );
        },
        'permission_callback' => function() {
            return current_user_can( 'manage_categories' );
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

    // Update term
    wp_register_ability( 'taxonomies/update-term', array(
        'label' => 'Update Term',
        'description' => 'Update an existing term',
        'category' => 'taxonomies',
        'input_schema' => array(
            'type' => 'object',
            'required' => array('term_id', 'taxonomy'),
            'properties' => array(
                'term_id' => array(
                    'type' => 'integer',
                    'description' => 'Term ID'
                ),
                'taxonomy' => array(
                    'type' => 'string',
                    'description' => 'Taxonomy name'
                ),
                'name' => array(
                    'type' => 'string',
                    'description' => 'Term name'
                ),
                'slug' => array(
                    'type' => 'string',
                    'description' => 'Term slug'
                ),
                'description' => array(
                    'type' => 'string',
                    'description' => 'Term description'
                ),
                'parent' => array(
                    'type' => 'integer',
                    'description' => 'Parent term ID'
                )
            )
        ),
        'output_schema' => array(
            'type' => 'object',
            'properties' => array(
                'term_id' => array('type' => 'integer'),
                'success' => array('type' => 'boolean')
            )
        ),
        'execute_callback' => function( $input ) {
            $args = array();

            if ( isset( $input['name'] ) ) $args['name'] = $input['name'];
            if ( isset( $input['slug'] ) ) $args['slug'] = $input['slug'];
            if ( isset( $input['description'] ) ) $args['description'] = $input['description'];
            if ( isset( $input['parent'] ) ) $args['parent'] = $input['parent'];

            $result = wp_update_term( $input['term_id'], $input['taxonomy'], $args );

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            return array(
                'term_id' => $result['term_id'],
                'success' => true
            );
        },
        'permission_callback' => function() {
            return current_user_can( 'manage_categories' );
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

    // Delete term
    wp_register_ability( 'taxonomies/delete-term', array(
        'label' => 'Delete Term',
        'description' => 'Delete a term from a taxonomy',
        'category' => 'taxonomies',
        'input_schema' => array(
            'type' => 'object',
            'required' => array('term_id', 'taxonomy'),
            'properties' => array(
                'term_id' => array(
                    'type' => 'integer',
                    'description' => 'Term ID'
                ),
                'taxonomy' => array(
                    'type' => 'string',
                    'description' => 'Taxonomy name'
                )
            )
        ),
        'output_schema' => array(
            'type' => 'object',
            'properties' => array(
                'success' => array('type' => 'boolean')
            )
        ),
        'execute_callback' => function( $input ) {
            $result = wp_delete_term( $input['term_id'], $input['taxonomy'] );

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            return array(
                'success' => (bool) $result
            );
        },
        'permission_callback' => function() {
            return current_user_can( 'manage_categories' );
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

    // Assign terms to content
    wp_register_ability( 'taxonomies/assign-to-content', array(
        'label' => 'Assign Terms to Content',
        'description' => 'Assign one or more terms to a post or custom post type',
        'category' => 'taxonomies',
        'input_schema' => array(
            'type' => 'object',
            'required' => array('post_id', 'taxonomy', 'terms'),
            'properties' => array(
                'post_id' => array(
                    'type' => 'integer',
                    'description' => 'Post ID'
                ),
                'taxonomy' => array(
                    'type' => 'string',
                    'description' => 'Taxonomy name'
                ),
                'terms' => array(
                    'type' => 'array',
                    'description' => 'Array of term IDs or term names to assign',
                    'items' => array(
                        'oneOf' => array(
                            array('type' => 'integer'),
                            array('type' => 'string')
                        )
                    )
                ),
                'append' => array(
                    'type' => 'boolean',
                    'description' => 'If true, append terms. If false, replace existing terms',
                    'default' => false
                )
            )
        ),
        'output_schema' => array(
            'type' => 'object',
            'properties' => array(
                'success' => array('type' => 'boolean'),
                'term_taxonomy_ids' => array('type' => 'array')
            )
        ),
        'execute_callback' => function( $input ) {
            $result = wp_set_object_terms(
                $input['post_id'],
                $input['terms'],
                $input['taxonomy'],
                $input['append'] ?? false
            );

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            return array(
                'success' => true,
                'term_taxonomy_ids' => $result
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

    // Get content terms
    wp_register_ability( 'taxonomies/get-content-terms', array(
        'label' => 'Get Content Terms',
        'description' => 'Get all terms assigned to a specific post',
        'category' => 'taxonomies',
        'input_schema' => array(
            'type' => 'object',
            'required' => array('post_id'),
            'properties' => array(
                'post_id' => array(
                    'type' => 'integer',
                    'description' => 'Post ID'
                ),
                'taxonomy' => array(
                    'type' => 'string',
                    'description' => 'Specific taxonomy to get terms from (optional, returns all if not specified)'
                )
            )
        ),
        'output_schema' => array(
            'type' => 'object'
        ),
        'execute_callback' => function( $input ) {
            $post_id = $input['post_id'];

            if ( isset( $input['taxonomy'] ) ) {
                $terms = get_the_terms( $post_id, $input['taxonomy'] );

                if ( is_wp_error( $terms ) ) {
                    return $terms;
                }

                if ( !$terms ) {
                    return array( $input['taxonomy'] => array() );
                }

                return array(
                    $input['taxonomy'] => array_map( function( $term ) {
                        return array(
                            'term_id' => $term->term_id,
                            'name' => $term->name,
                            'slug' => $term->slug,
                            'taxonomy' => $term->taxonomy
                        );
                    }, $terms )
                );
            }

            // Get all taxonomies for this post type
            $post = get_post( $post_id );
            if ( !$post ) {
                return new WP_Error( 'not_found', 'Post not found' );
            }

            $taxonomies = get_object_taxonomies( $post->post_type );
            $result = array();

            foreach ( $taxonomies as $taxonomy ) {
                $terms = get_the_terms( $post_id, $taxonomy );

                if ( $terms && !is_wp_error( $terms ) ) {
                    $result[$taxonomy] = array_map( function( $term ) {
                        return array(
                            'term_id' => $term->term_id,
                            'name' => $term->name,
                            'slug' => $term->slug,
                            'taxonomy' => $term->taxonomy
                        );
                    }, $terms );
                } else {
                    $result[$taxonomy] = array();
                }
            }

            return $result;
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

    error_log( 'WordPress Abilities Suite: Registered 8 taxonomy management abilities' );

}, 100 );
