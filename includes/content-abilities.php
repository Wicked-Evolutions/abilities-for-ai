<?php
/**
 * Content Abilities
 *
 * WordPress content (posts, pages, CPTs) management.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {
	$reg = new Abilities_For_AI_Registrar( 'content', 'edit_posts' );

	// ===== CONTENT — READ =====

	$reg->read( 'content/list', array(
		'label'       => 'List Content',
		'description' => 'List posts, pages, or custom post types with filtering and pagination',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_type' => array(
					'type'        => 'string',
					'description' => 'Post type to list (post, page, or custom post type)',
					'default'     => 'post',
				),
				'per_page' => array(
					'type'        => 'integer',
					'description' => 'Number of posts to return',
					'default'     => 10,
					'minimum'     => 1,
					'maximum'     => 100,
				),
				'paged' => array(
					'type'        => 'integer',
					'description' => 'Page number for pagination',
					'default'     => 1,
					'minimum'     => 1,
				),
				'post_status' => array(
					'type'        => 'string',
					'description' => 'Post status (publish, draft, pending, etc.)',
					'default'     => 'publish',
				),
				's' => array(
					'type'        => 'string',
					'description' => 'Search query',
				),
				'orderby' => array(
					'type'        => 'string',
					'description' => 'Order by field (date, title, modified, etc.)',
					'default'     => 'date',
				),
				'order' => array(
					'type'        => 'string',
					'description' => 'Order direction (ASC or DESC)',
					'default'     => 'DESC',
					'enum'        => array( 'ASC', 'DESC' ),
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_list_output( 'posts', array(
			'id'        => array( 'type' => 'integer' ),
			'title'     => array( 'type' => 'string' ),
			'status'    => array( 'type' => 'string' ),
			'post_type' => array( 'type' => 'string' ),
			'date'      => array( 'type' => 'string' ),
			'modified'  => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$post_type     = sanitize_key( $input['post_type'] ?? 'post' );
			$post_type_obj = get_post_type_object( $post_type );
			if ( ! $post_type_obj ) {
				return new WP_Error( 'ability_invalid_input', 'Invalid post type.' );
			}
			if ( ! current_user_can( $post_type_obj->cap->edit_posts ) ) {
				return new WP_Error( 'rest_forbidden', 'You do not have permission to list this post type.' );
			}

			$args = array(
				'post_type'      => $post_type,
				'posts_per_page' => (int) ( $input['per_page'] ?? 10 ),
				'paged'          => $input['paged'] ?? 1,
				'post_status'    => $input['post_status'] ?? 'publish',
				'orderby'        => $input['orderby'] ?? 'date',
				'order'          => $input['order'] ?? 'DESC',
			);

			if ( ! empty( $input['s'] ) ) {
				$args['s'] = $input['s'];
			}

			$query = new WP_Query( $args );

			$posts = array();
			foreach ( $query->posts as $post ) {
				if ( ! current_user_can( 'edit_post', $post->ID ) ) {
					continue;
				}
				$posts[] = array(
					'id'       => $post->ID,
					'title'    => $post->post_title,
					'content'  => $post->post_content,
					'excerpt'  => $post->post_excerpt,
					'status'   => $post->post_status,
					'type'     => $post->post_type,
					'date'     => $post->post_date,
					'modified' => $post->post_modified,
					'author'   => $post->post_author,
					'slug'     => $post->post_name,
					'link'     => get_permalink( $post->ID ),
				);
			}

			return array(
				'total'    => (int) $query->found_posts,
				'pages'    => max( 1, (int) $query->max_num_pages ),
				'page'     => (int) $args['paged'],
				'per_page' => $args['posts_per_page'],
				'posts'    => $posts,
			);
		},
	) );

	$reg->read( 'content/get', array(
		'label'       => 'Get Content',
		'description' => 'Get a specific post, page, or custom post type by ID',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Post ID' ),
			),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'id'        => array( 'type' => 'integer' ),
			'title'     => array( 'type' => 'string' ),
			'content'   => array( 'type' => 'string' ),
			'status'    => array( 'type' => 'string' ),
			'post_type' => array( 'type' => 'string' ),
			'date'      => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$check = abilities_for_ai_require_editable_post( $input['id'] );
			if ( is_wp_error( $check ) ) return $check;

			$post = $check;
			return array(
				'id'             => $post->ID,
				'title'          => $post->post_title,
				'content'        => $post->post_content,
				'excerpt'        => $post->post_excerpt,
				'status'         => $post->post_status,
				'type'           => $post->post_type,
				'date'           => $post->post_date,
				'modified'       => $post->post_modified,
				'author'         => $post->post_author,
				'slug'           => $post->post_name,
				'link'           => get_permalink( $post->ID ),
				'featured_image' => get_post_thumbnail_id( $post->ID ),
			);
		},
	) );

	$reg->read( 'content/get-snapshot', array(
		'label'       => 'Get Content Snapshot',
		'description' => 'Get complete post data in a single call: post fields, all meta, taxonomy terms, featured image URL, and author details. Use include/exclude arrays to control which sections are returned. Much more efficient than calling content/get + separate meta/taxonomy lookups.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Post ID',
				),
				'include' => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Sections to include. Options: meta, terms, thumbnail, author, content. If omitted, all sections are included.',
				),
				'exclude' => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Sections to exclude. Options: meta, terms, thumbnail, author, content. Ignored if include is set.',
				),
				'char_limit' => array(
					'type'        => 'integer',
					'description' => 'Truncate post_content to this many characters. Useful to get metadata without full HTML weight. 0 or omitted = no limit.',
					'minimum'     => 0,
				),
				'meta_keys' => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Specific meta keys to return. If omitted, all non-internal meta is returned. Internal keys (starting with _) are excluded by default unless listed here.',
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'id'     => array( 'type' => 'integer' ),
			'title'  => array( 'type' => 'string' ),
			'status' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$check = abilities_for_ai_require_editable_post( $input['id'] );
			if ( is_wp_error( $check ) ) return $check;

			$post = $check;

			$all_sections = array( 'meta', 'terms', 'thumbnail', 'author', 'content' );
			if ( ! empty( $input['include'] ) ) {
				$sections = array_intersect( $input['include'], $all_sections );
			} elseif ( ! empty( $input['exclude'] ) ) {
				$sections = array_diff( $all_sections, $input['exclude'] );
			} else {
				$sections = $all_sections;
			}
			$sections = array_flip( $sections );

			$result = array(
				'id'       => $post->ID,
				'title'    => $post->post_title,
				'status'   => $post->post_status,
				'type'     => $post->post_type,
				'date'     => $post->post_date,
				'modified' => $post->post_modified,
				'slug'     => $post->post_name,
				'link'     => get_permalink( $post->ID ),
				'excerpt'  => $post->post_excerpt,
			);

			if ( isset( $sections['content'] ) ) {
				$content = $post->post_content;
				if ( ! empty( $input['char_limit'] ) && mb_strlen( $content ) > $input['char_limit'] ) {
					$content = mb_substr( $content, 0, $input['char_limit'] ) . '… [truncated]';
				}
				$result['content'] = $content;
			}

			if ( isset( $sections['meta'] ) ) {
				$raw_meta  = get_post_meta( $post->ID );
				$meta_keys = ! empty( $input['meta_keys'] ) ? $input['meta_keys'] : null;
				$meta      = array();

				foreach ( $raw_meta as $key => $values ) {
					if ( $meta_keys !== null ) {
						if ( ! in_array( $key, $meta_keys, true ) ) {
							continue;
						}
					} else {
						if ( substr( $key, 0, 1 ) === '_' ) {
							continue;
						}
					}
					$meta[ $key ] = count( $values ) === 1 ? $values[0] : $values;
				}

				$result['meta'] = $meta;
			}

			if ( isset( $sections['terms'] ) ) {
				$taxonomies = get_object_taxonomies( $post->post_type, 'names' );
				$terms      = array();

				foreach ( $taxonomies as $taxonomy ) {
					$post_terms = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'all' ) );
					if ( ! is_wp_error( $post_terms ) && ! empty( $post_terms ) ) {
						$terms[ $taxonomy ] = array_map( function( $term ) {
							return array(
								'id'   => $term->term_id,
								'name' => $term->name,
								'slug' => $term->slug,
							);
						}, $post_terms );
					}
				}

				$result['terms'] = $terms;
			}

			if ( isset( $sections['thumbnail'] ) ) {
				$thumb_id = get_post_thumbnail_id( $post->ID );
				if ( $thumb_id ) {
					$image_data          = wp_get_attachment_image_src( $thumb_id, 'full' );
					$result['thumbnail'] = array(
						'id'     => $thumb_id,
						'url'    => $image_data ? $image_data[0] : wp_get_attachment_url( $thumb_id ),
						'width'  => $image_data ? $image_data[1] : null,
						'height' => $image_data ? $image_data[2] : null,
						'alt'    => get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ),
					);
				} else {
					$result['thumbnail'] = null;
				}
			}

			if ( isset( $sections['author'] ) ) {
				$author = get_userdata( (int) $post->post_author );
				if ( $author ) {
					$result['author'] = array(
						'id'           => $author->ID,
						'display_name' => $author->display_name,
						'url'          => $author->user_url,
					);
				} else {
					$result['author'] = null;
				}
			}

			return $result;
		},
	) );

	$reg->read( 'content/get-text', array(
		'label'       => 'Get Content as Plain Text',
		'description' => 'Get a post\'s readable text content with block markup and HTML stripped. Returns ~2-20KB instead of 50-200KB from content/get. Ideal for Story Read, ESSENCE synthesis, and content analysis where you need what the page says, not how it\'s built.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Post ID' ),
			),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'id'         => array( 'type' => 'integer' ),
			'title'      => array( 'type' => 'string' ),
			'text'       => array( 'type' => 'string' ),
			'word_count' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$check = abilities_for_ai_require_editable_post( $input['id'] );
			if ( is_wp_error( $check ) ) return $check;

			$post = $check;

			// Strip block comments (<!-- wp:... --> and <!-- /wp:... -->).
			$text = preg_replace( '/<!--\s*\/?wp:[^>]*-->/s', '', $post->post_content );
			// Render shortcodes and embeds.
			$text = do_shortcode( $text );
			// Strip remaining HTML tags.
			$text = wp_strip_all_tags( $text );
			// Normalize whitespace: collapse runs of whitespace, trim.
			$text = preg_replace( '/[ \t]+/', ' ', $text );
			$text = preg_replace( '/\n{3,}/', "\n\n", $text );
			$text = trim( $text );

			return array(
				'id'         => $post->ID,
				'title'      => $post->post_title,
				'excerpt'    => $post->post_excerpt,
				'text'       => $text,
				'word_count' => str_word_count( $text ),
				'type'       => $post->post_type,
				'status'     => $post->post_status,
				'date'       => $post->post_date,
				'link'       => get_permalink( $post->ID ),
			);
		},
	) );

	$reg->read( 'content/list-structure', array(
		'label'       => 'List Content Structure',
		'description' => 'List content metadata without the content field. Returns titles, IDs, slugs, parent, status, and dates — everything needed for site mapping without the 50-200KB Gutenberg payload per post. Supports pagination, filtering by type and status.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_type' => array(
					'type'        => 'string',
					'description' => 'Post type to list (post, page, or custom post type)',
					'default'     => 'page',
				),
				'per_page' => array(
					'type'        => 'integer',
					'description' => 'Number of items to return',
					'default'     => 100,
					'minimum'     => 1,
					'maximum'     => 100,
				),
				'paged' => array(
					'type'        => 'integer',
					'description' => 'Page number for pagination',
					'default'     => 1,
					'minimum'     => 1,
				),
				'post_status' => array(
					'type'        => 'string',
					'description' => 'Post status (publish, draft, any)',
					'default'     => 'publish',
				),
				'orderby' => array(
					'type'        => 'string',
					'description' => 'Order by field (title, date, menu_order, modified)',
					'default'     => 'title',
				),
				'order' => array(
					'type'        => 'string',
					'description' => 'Order direction (ASC or DESC)',
					'default'     => 'ASC',
					'enum'        => array( 'ASC', 'DESC' ),
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_list_output( 'items', array(
			'id'     => array( 'type' => 'integer' ),
			'title'  => array( 'type' => 'string' ),
			'slug'   => array( 'type' => 'string' ),
			'parent' => array( 'type' => 'integer' ),
			'status' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$post_type     = sanitize_key( $input['post_type'] ?? 'page' );
			$post_type_obj = get_post_type_object( $post_type );
			if ( ! $post_type_obj ) {
				return new WP_Error( 'ability_invalid_input', 'Invalid post type.' );
			}
			if ( ! current_user_can( $post_type_obj->cap->edit_posts ) ) {
				return new WP_Error( 'rest_forbidden', 'You do not have permission to list this post type.' );
			}

			$query = new WP_Query( array(
				'post_type'      => $post_type,
				'posts_per_page' => (int) ( $input['per_page'] ?? 100 ),
				'paged'          => $input['paged'] ?? 1,
				'post_status'    => $input['post_status'] ?? 'publish',
				'orderby'        => $input['orderby'] ?? 'title',
				'order'          => $input['order'] ?? 'ASC',
			) );

			$items = array();
			foreach ( $query->posts as $post ) {
				if ( ! current_user_can( 'edit_post', $post->ID ) ) {
					continue;
				}
				$items[] = array(
					'id'         => $post->ID,
					'title'      => $post->post_title,
					'slug'       => $post->post_name,
					'parent'     => $post->post_parent,
					'status'     => $post->post_status,
					'type'       => $post->post_type,
					'date'       => $post->post_date,
					'modified'   => $post->post_modified,
					'menu_order' => $post->menu_order,
					'link'       => get_permalink( $post->ID ),
				);
			}

			return array(
				'total'    => (int) $query->found_posts,
				'pages'    => max( 1, (int) $query->max_num_pages ),
				'page'     => (int) ( $input['paged'] ?? 1 ),
				'per_page' => (int) ( $input['per_page'] ?? 100 ),
				'items'    => $items,
			);
		},
	) );

	$reg->read( 'content/get-site-map', array(
		'label'       => 'Get Site Map',
		'description' => 'Get the full hierarchical page tree in a single call. Returns all pages as a nested tree structure with parent/child relationships resolved. Ideal for understanding site architecture without multiple content/list calls.',
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'total' => array( 'type' => 'integer' ),
			'tree'  => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function() {
			if ( ! current_user_can( 'edit_pages' ) ) {
				return new WP_Error( 'rest_forbidden', 'You do not have permission to list pages.' );
			}

			$query = new WP_Query( array(
				'post_type'      => 'page',
				'posts_per_page' => 500,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
			) );

			// Build flat list keyed by ID.
			$flat = array();
			foreach ( $query->posts as $post ) {
				if ( ! current_user_can( 'edit_post', $post->ID ) ) {
					continue;
				}
				$flat[ $post->ID ] = array(
					'id'         => $post->ID,
					'title'      => $post->post_title,
					'slug'       => $post->post_name,
					'parent'     => $post->post_parent,
					'status'     => $post->post_status,
					'menu_order' => $post->menu_order,
					'link'       => get_permalink( $post->ID ),
					'children'   => array(),
				);
			}

			// Build tree by assigning children to parents.
			$tree = array();
			foreach ( $flat as $id => &$node ) {
				if ( $node['parent'] && isset( $flat[ $node['parent'] ] ) ) {
					$flat[ $node['parent'] ]['children'][] = &$node;
				} else {
					$tree[] = &$node;
				}
			}
			unset( $node );

			return array(
				'total' => count( $flat ),
				'tree'  => $tree,
			);
		},
	) );

	$reg->read( 'content/discover-types', array(
		'label'       => 'Discover Content Types',
		'description' => 'Discover all available post types',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'public' => array(
					'type'        => 'boolean',
					'description' => 'Only return public post types',
					'default'     => true,
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_collection_output( 'post_types', array(
			'name'  => array( 'type' => 'string' ),
			'label' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$args = array();
			if ( isset( $input['public'] ) && $input['public'] ) {
				$args['public'] = true;
			}

			$post_types = get_post_types( $args, 'objects' );

			$items = array_values( array_map( function( $post_type ) {
				return array(
					'name'         => $post_type->name,
					'label'        => $post_type->label,
					'description'  => $post_type->description,
					'public'       => $post_type->public,
					'hierarchical' => $post_type->hierarchical,
					'rest_base'    => $post_type->rest_base,
					'supports'     => get_all_post_type_supports( $post_type->name ),
				);
			}, $post_types ) );

			return array( 'post_types' => $items, 'total' => count( $items ) );
		},
	) );

	$reg->read( 'content/find-by-url', array(
		'label'       => 'Find Content by URL',
		'description' => 'Find a post, page, or custom post type by its URL',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'url' ),
			'properties' => array(
				'url' => array( 'type' => 'string', 'description' => 'Full URL or path to the content' ),
			),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'id'        => array( 'type' => 'integer' ),
			'title'     => array( 'type' => 'string' ),
			'status'    => array( 'type' => 'string' ),
			'post_type' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$post_id = url_to_postid( $input['url'] );
			if ( ! $post_id ) {
				return new WP_Error( 'not_found', 'No content found for this URL' );
			}

			$check = abilities_for_ai_require_editable_post( $post_id );
			if ( is_wp_error( $check ) ) return $check;

			$post = $check;
			return array(
				'id'             => $post->ID,
				'title'          => $post->post_title,
				'content'        => $post->post_content,
				'excerpt'        => $post->post_excerpt,
				'status'         => $post->post_status,
				'type'           => $post->post_type,
				'date'           => $post->post_date,
				'modified'       => $post->post_modified,
				'author'         => $post->post_author,
				'slug'           => $post->post_name,
				'link'           => get_permalink( $post->ID ),
				'featured_image' => get_post_thumbnail_id( $post->ID ),
			);
		},
	) );

	$reg->read( 'content/get-by-slug', array(
		'label'       => 'Get Content by Slug',
		'description' => 'Get a post, page, or custom post type by its slug',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'slug' ),
			'properties' => array(
				'slug' => array(
					'type'        => 'string',
					'description' => 'Post slug (post_name)',
				),
				'post_type' => array(
					'type'        => 'string',
					'description' => 'Post type to search in',
					'default'     => 'post',
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'id'        => array( 'type' => 'integer' ),
			'title'     => array( 'type' => 'string' ),
			'status'    => array( 'type' => 'string' ),
			'post_type' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$query = new WP_Query( array(
				'name'           => $input['slug'],
				'post_type'      => $input['post_type'] ?? 'post',
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => 1,
			) );

			if ( ! $query->have_posts() ) {
				return new WP_Error( 'not_found', 'No content found with this slug.' );
			}

			$check = abilities_for_ai_require_editable_post( $query->posts[0]->ID );
			if ( is_wp_error( $check ) ) return $check;

			$post = $check;
			return abilities_for_ai_safe_value( array(
				'id'             => $post->ID,
				'title'          => $post->post_title,
				'content'        => $post->post_content,
				'excerpt'        => $post->post_excerpt,
				'status'         => $post->post_status,
				'type'           => $post->post_type,
				'date'           => $post->post_date,
				'modified'       => $post->post_modified,
				'author'         => $post->post_author,
				'slug'           => $post->post_name,
				'link'           => get_permalink( $post->ID ),
				'featured_image' => get_post_thumbnail_id( $post->ID ),
			) );
		},
	) );

	$reg->read( 'content/render-page', array(
		'ability_class' => 'WE_Multisite_Ability',
		'capability'    => 'edit_theme_options',
		'label'         => 'Render Page HTML',
		'description'   => 'Fetch the full rendered frontend HTML of a page via HTTP loopback — equivalent to what a browser or curl would receive. Returns the complete document including <html>, <head>, enqueued assets, <body class>, and template chrome. Accepts a URL (full or path) or a post_id. Useful for theme refactor baselines, visual regression checks, and verifying rendered output.',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array(
				'url' => array(
					'type'        => 'string',
					'description' => 'Full URL or relative path (e.g. "/about/" or "https://site.com/about/") to render. At least one of url or post_id is required.',
				),
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'Post ID to render. Resolves the URL via get_permalink(). At least one of url or post_id is required.',
				),
				'blog_id' => array(
					'type'        => 'integer',
					'description' => 'Multisite: subsite blog ID to render from. Switches context so home_url() and get_permalink() resolve to that subsite.',
				),
				'timeout' => array(
					'type'        => 'integer',
					'description' => 'HTTP request timeout in seconds.',
					'default'     => 20,
					'minimum'     => 1,
					'maximum'     => 60,
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'html'           => array( 'type' => 'string' ),
			'status_code'    => array( 'type' => 'integer' ),
			'content_length' => array( 'type' => 'integer' ),
			'url'            => array( 'type' => 'string' ),
			'headers'        => array( 'type' => 'object' ),
		) ),
		'callback' => function( $input ) {
			$url     = $input['url'] ?? null;
			$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
			$timeout = isset( $input['timeout'] ) ? (int) $input['timeout'] : 20;

			if ( ! $url && ! $post_id ) {
				return new WP_Error(
					'ability_invalid_input',
					'At least one of url or post_id is required.'
				);
			}

			// Resolve URL from post_id.
			if ( $post_id ) {
				$permalink = get_permalink( $post_id );
				if ( ! $permalink ) {
					return new WP_Error(
						'ability_invalid_input',
						sprintf( 'Could not resolve permalink for post ID %d. Post may not exist or is not public.', $post_id )
					);
				}
				$url = $permalink;
			}

			// Relative path → absolute URL.
			if ( strpos( $url, '/' ) === 0 ) {
				$url = home_url( $url );
			}

			$response = wp_remote_get( $url, array(
				'timeout'     => $timeout,
				'redirection' => 3,
				'sslverify'   => false,
			) );

			if ( is_wp_error( $response ) ) {
				return new WP_Error(
					'loopback_failed',
					sprintf(
						'HTTP loopback request failed: %s. This can happen on shared hosting (CageFS/CloudLinux) that blocks self-requests. URL attempted: %s',
						$response->get_error_message(),
						$url
					),
					array( 'status' => 502 )
				);
			}

			$body        = wp_remote_retrieve_body( $response );
			$status_code = (int) wp_remote_retrieve_response_code( $response );
			$headers     = wp_remote_retrieve_headers( $response );

			// Convert headers object to plain array.
			$headers_array = array();
			if ( $headers instanceof \WpOrg\Requests\Utility\CaseInsensitiveDictionary || $headers instanceof \Requests_Utility_CaseInsensitiveDictionary ) {
				foreach ( $headers as $key => $value ) {
					$headers_array[ $key ] = $value;
				}
			} elseif ( is_array( $headers ) ) {
				$headers_array = $headers;
			}

			return array(
				'html'           => $body,
				'status_code'    => $status_code,
				'content_length' => strlen( $body ),
				'url'            => $url,
				'headers'        => $headers_array,
			);
		},
	) );

	// ===== CONTENT — WRITE =====

	$reg->write( 'content/create', array(
		'tier'        => 'free',
		'label'       => 'Create Content',
		'description' => 'Create new content (posts, pages, custom post types)',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'title' ),
			'properties' => array(
				'title' => array(
					'type'        => 'string',
					'description' => 'Post title',
				),
				'content' => array(
					'type'        => 'string',
					'description' => 'Post content',
				),
				'post_type' => array(
					'type'        => 'string',
					'description' => 'Post type',
					'default'     => 'post',
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'Post status',
					'default'     => 'draft',
					'enum'        => array( 'publish', 'draft', 'pending', 'private' ),
				),
				'excerpt' => array(
					'type'        => 'string',
					'description' => 'Post excerpt',
				),
				'post_date' => array(
					'type'        => 'string',
					'description' => 'Publish date in MySQL datetime (e.g. "2026-03-05 14:30:00") or ISO 8601 format. Defaults to current time.',
				),
				'author' => array(
					'type'        => 'integer',
					'description' => 'User ID to set as post author. Defaults to current user.',
				),
				'post_name' => array(
					'type'        => 'string',
					'description' => 'Post slug. Auto-generated from title if not provided.',
				),
				'terms' => array(
					'type'        => 'string',
					'description' => 'JSON object of taxonomy terms to assign. Keys are taxonomy slugs, values are arrays of term IDs. Example: {"category": [3, 5], "post_tag": [12]}',
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'id'   => array( 'type' => 'integer' ),
			'link' => array( 'type' => 'string' ),
		) ),
		'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
		'callback' => function( $input ) {
			$post_type     = sanitize_key( $input['post_type'] ?? 'post' );
			$post_type_obj = get_post_type_object( $post_type );
			if ( ! $post_type_obj ) {
				return new WP_Error( 'ability_invalid_input', 'Invalid post type.' );
			}
			if ( ! current_user_can( $post_type_obj->cap->create_posts ) ) {
				return new WP_Error( 'rest_forbidden', 'You do not have permission to create this post type.' );
			}

			$status = $input['status'] ?? 'draft';
			if ( in_array( $status, array( 'publish', 'future' ), true ) ) {
				if ( ! current_user_can( $post_type_obj->cap->publish_posts ) ) {
					return new WP_Error( 'rest_forbidden', 'You do not have permission to publish this post type.' );
				}
			}

			$post_data = array(
				'post_title'   => $input['title'],
				'post_content' => $input['content'] ?? '',
				'post_type'    => $post_type,
				'post_status'  => $status,
				'post_excerpt' => $input['excerpt'] ?? '',
			);

			if ( isset( $input['post_name'] ) ) {
				$post_data['post_name'] = sanitize_title( $input['post_name'] );
			}

			if ( isset( $input['post_date'] ) ) {
				$post_data['post_date']     = sanitize_text_field( $input['post_date'] );
				$post_data['post_date_gmt'] = get_gmt_from_date( $post_data['post_date'] );
			}

			if ( isset( $input['author'] ) ) {
				$author_id = (int) $input['author'];
				if ( ! get_userdata( $author_id ) ) {
					return new WP_Error( 'ability_invalid_input', "User ID {$author_id} does not exist." );
				}
				$post_data['post_author'] = $author_id;
			}

			$post_id = wp_insert_post( $post_data );
			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}

			if ( ! empty( $input['terms'] ) ) {
				$terms = $input['terms'];
				if ( is_string( $terms ) ) {
					$terms = json_decode( $terms, true );
				}
				if ( ! is_array( $terms ) ) {
					$terms = array();
				}
				foreach ( $terms as $taxonomy => $term_ids ) {
					$taxonomy = sanitize_key( $taxonomy );
					if ( ! taxonomy_exists( $taxonomy ) ) {
						continue;
					}
					$term_ids = array_map( 'intval', (array) $term_ids );
					wp_set_object_terms( $post_id, $term_ids, $taxonomy );
				}
			}

			return array(
				'success' => true,
				'id'      => $post_id,
				'link'    => get_permalink( $post_id ),
			);
		},
	) );

	$reg->write( 'content/create-from-file', array(
		'tier'        => 'free',
		'label'       => 'Create Content from File',
		'description' => 'Create content by reading post_content from a server-side file. Use with filesystem/write-file to move large payloads (block markup, long-form content) without passing content through the LLM context.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'content_path', 'title' ),
			'properties' => array(
				'content_path' => array(
					'type'        => 'string',
					'description' => 'Relative path from ABSPATH to the file containing post content (e.g. "wp-content/uploads/staging/my-page.html")',
				),
				'title' => array(
					'type'        => 'string',
					'description' => 'Post title',
				),
				'post_type' => array(
					'type'        => 'string',
					'description' => 'Post type',
					'default'     => 'post',
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'Post status',
					'default'     => 'draft',
					'enum'        => array( 'publish', 'draft', 'pending', 'private' ),
				),
				'excerpt' => array(
					'type'        => 'string',
					'description' => 'Post excerpt',
				),
				'post_date' => array(
					'type'        => 'string',
					'description' => 'Publish date in MySQL datetime (e.g. "2026-03-05 14:30:00") or ISO 8601 format. Defaults to current time.',
				),
				'author' => array(
					'type'        => 'integer',
					'description' => 'User ID to set as post author. Defaults to current user.',
				),
				'post_name' => array(
					'type'        => 'string',
					'description' => 'Post slug. Auto-generated from title if not provided.',
				),
				'terms' => array(
					'type'        => 'string',
					'description' => 'JSON object of taxonomy terms to assign. Keys are taxonomy slugs, values are arrays of term IDs. Example: {"category": [3, 5], "post_tag": [12]}',
				),
				'delete_file' => array(
					'type'        => 'boolean',
					'description' => 'Delete the staging file after post creation',
					'default'     => true,
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'id'           => array( 'type' => 'integer' ),
			'link'         => array( 'type' => 'string' ),
			'bytes_read'   => array( 'type' => 'integer' ),
			'file_deleted' => array( 'type' => 'boolean' ),
		) ),
		'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
		'callback' => function( $input ) {
			// Validate and resolve file path.
			$abs_path = wp_abilities_filesystem_validate_path( $input['content_path'], true );
			if ( is_wp_error( $abs_path ) ) {
				return $abs_path;
			}

			if ( ! is_file( $abs_path ) || ! is_readable( $abs_path ) ) {
				return new WP_Error( 'ability_invalid_input', 'Content file does not exist or is not readable.' );
			}

			// Read file content.
			$content = file_get_contents( $abs_path );
			if ( $content === false ) {
				return new WP_Error( 'ability_server_error', 'Failed to read content file.' );
			}
			$bytes_read = strlen( $content );

			// Validate post type and permissions.
			$post_type     = sanitize_key( $input['post_type'] ?? 'post' );
			$post_type_obj = get_post_type_object( $post_type );
			if ( ! $post_type_obj ) {
				return new WP_Error( 'ability_invalid_input', 'Invalid post type.' );
			}
			if ( ! current_user_can( $post_type_obj->cap->create_posts ) ) {
				return new WP_Error( 'rest_forbidden', 'You do not have permission to create this post type.' );
			}

			$status = $input['status'] ?? 'draft';
			if ( in_array( $status, array( 'publish', 'future' ), true ) ) {
				if ( ! current_user_can( $post_type_obj->cap->publish_posts ) ) {
					return new WP_Error( 'rest_forbidden', 'You do not have permission to publish this post type.' );
				}
			}

			$post_data = array(
				'post_title'   => $input['title'],
				'post_content' => $content,
				'post_type'    => $post_type,
				'post_status'  => $status,
				'post_excerpt' => $input['excerpt'] ?? '',
			);

			if ( isset( $input['post_name'] ) ) {
				$post_data['post_name'] = sanitize_title( $input['post_name'] );
			}

			if ( isset( $input['post_date'] ) ) {
				$post_data['post_date']     = sanitize_text_field( $input['post_date'] );
				$post_data['post_date_gmt'] = get_gmt_from_date( $post_data['post_date'] );
			}

			if ( isset( $input['author'] ) ) {
				$author_id = (int) $input['author'];
				if ( ! get_userdata( $author_id ) ) {
					return new WP_Error( 'ability_invalid_input', "User ID {$author_id} does not exist." );
				}
				$post_data['post_author'] = $author_id;
			}

			$post_id = wp_insert_post( $post_data );
			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}

			// Handle terms assignment.
			if ( ! empty( $input['terms'] ) ) {
				$terms = $input['terms'];
				if ( is_string( $terms ) ) {
					$terms = json_decode( $terms, true );
				}
				if ( ! is_array( $terms ) ) {
					$terms = array();
				}
				foreach ( $terms as $taxonomy => $term_ids ) {
					$taxonomy = sanitize_key( $taxonomy );
					if ( ! taxonomy_exists( $taxonomy ) ) {
						continue;
					}
					$term_ids = array_map( 'intval', (array) $term_ids );
					wp_set_object_terms( $post_id, $term_ids, $taxonomy );
				}
			}

			// Clean up staging file.
			$delete_file = $input['delete_file'] ?? true;
			$file_deleted = false;
			if ( $delete_file ) {
				$file_deleted = @unlink( $abs_path );
			}

			return array(
				'success'      => true,
				'id'           => $post_id,
				'link'         => get_permalink( $post_id ),
				'bytes_read'   => $bytes_read,
				'file_deleted' => $file_deleted,
			);
		},
	) );

	$reg->write( 'content/update', array(
		'label'       => 'Update Content',
		'description' => 'Update existing content',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Post ID',
				),
				'title' => array(
					'type'        => 'string',
					'description' => 'Post title',
				),
				'content' => array(
					'type'        => 'string',
					'description' => 'Post content',
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'Post status',
				),
				'excerpt' => array(
					'type'        => 'string',
					'description' => 'Post excerpt',
				),
				'post_date' => array(
					'type'        => 'string',
					'description' => 'Publish date in MySQL datetime (e.g. "2026-03-05 14:30:00") or ISO 8601 format.',
				),
				'author' => array(
					'type'        => 'integer',
					'description' => 'User ID to set as post author.',
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$check = abilities_for_ai_require_editable_post( $input['id'] );
			if ( is_wp_error( $check ) ) return $check;

			$post          = $check;
			$post_type_obj = get_post_type_object( $post->post_type );

			if ( isset( $input['status'] ) && in_array( $input['status'], array( 'publish', 'future' ), true ) ) {
				if ( $post->post_status !== $input['status'] && ! current_user_can( $post_type_obj->cap->publish_posts ) ) {
					return new WP_Error( 'rest_forbidden', 'You do not have permission to publish this post type.' );
				}
			}

			$post_data = array( 'ID' => $post->ID );

			if ( isset( $input['title'] ) )   $post_data['post_title']   = $input['title'];
			if ( isset( $input['content'] ) )  $post_data['post_content']  = $input['content'];
			if ( isset( $input['status'] ) )   $post_data['post_status']   = $input['status'];
			if ( isset( $input['excerpt'] ) )  $post_data['post_excerpt']  = $input['excerpt'];
			if ( isset( $input['post_date'] ) ) {
				$post_data['post_date']     = sanitize_text_field( $input['post_date'] );
				$post_data['post_date_gmt'] = get_gmt_from_date( $post_data['post_date'] );
			}
			if ( isset( $input['author'] ) ) {
				$author_id = (int) $input['author'];
				if ( ! get_userdata( $author_id ) ) {
					return new WP_Error( 'ability_invalid_input', "User ID {$author_id} does not exist." );
				}
				$post_data['post_author'] = $author_id;
			}

			$result = wp_update_post( $post_data );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return array( 'success' => true, 'id' => $result );
		},
	) );

	$reg->write( 'content/append', array(
		'label'       => 'Append Content',
		'description' => 'Append block markup to the end of an existing post\'s content without reading or returning the full content. Useful for incrementally building pages.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id', 'content' ),
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Post ID to append content to',
				),
				'content' => array(
					'type'        => 'string',
					'description' => 'Block markup to append to the end of existing content',
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'id'             => array( 'type' => 'integer' ),
			'content_length' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$check = abilities_for_ai_require_editable_post( $input['id'] );
			if ( is_wp_error( $check ) ) return $check;

			$post            = $check;
			$existing        = $post->post_content;
			$appended        = $existing . $input['content'];

			$result = wp_update_post( array(
				'ID'           => $post->ID,
				'post_content' => $appended,
			) );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return array(
				'success'        => true,
				'id'             => $result,
				'content_length' => strlen( $appended ),
			);
		},
	) );

	$reg->write( 'content/change-type', array(
		'label'       => 'Change Content Type',
		'description' => 'Convert a post between post types (e.g. page to post, post to page). Returns the new permalink and warns about taxonomy/template side effects. Use this instead of content/update when you need to change post_type — content/update does not support type changes.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id', 'new_type' ),
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Post ID to convert',
				),
				'new_type' => array(
					'type'        => 'string',
					'description' => 'Target post type (e.g. post, page, or any registered custom post type)',
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'id'           => array( 'type' => 'integer' ),
			'new_post_type' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$check = abilities_for_ai_require_editable_post( $input['id'] );
			if ( is_wp_error( $check ) ) return $check;

			$post     = $check;
			$old_type = $post->post_type;
			$new_type = sanitize_key( $input['new_type'] );

			$new_type_obj = get_post_type_object( $new_type );
			if ( ! $new_type_obj ) {
				return new WP_Error( 'ability_invalid_input', "Post type '{$new_type}' does not exist." );
			}

			if ( ! current_user_can( $new_type_obj->cap->create_posts ) ) {
				return new WP_Error( 'rest_forbidden', "You do not have permission to create {$new_type} posts." );
			}

			if ( $old_type === $new_type ) {
				return new WP_Error( 'ability_invalid_input', "Post is already of type '{$new_type}'." );
			}

			$old_permalink = get_permalink( $post->ID );
			$warnings      = array();

			$old_taxonomies  = get_object_taxonomies( $old_type );
			$new_taxonomies  = get_object_taxonomies( $new_type );
			$lost_taxonomies = array_diff( $old_taxonomies, $new_taxonomies );

			if ( ! empty( $lost_taxonomies ) ) {
				$orphaned = array();
				foreach ( $lost_taxonomies as $tax ) {
					$terms = wp_get_object_terms( $post->ID, $tax, array( 'fields' => 'names' ) );
					if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
						$orphaned[ $tax ] = $terms;
					}
				}
				if ( ! empty( $orphaned ) ) {
					foreach ( $orphaned as $tax => $term_names ) {
						$warnings[] = "Taxonomy '{$tax}' is not supported by '{$new_type}'. Assigned terms (" . implode( ', ', $term_names ) . ") will become orphaned — they remain in the database but won't display.";
					}
				}
			}

			$gained_taxonomies = array_diff( $new_taxonomies, $old_taxonomies );
			if ( ! empty( $gained_taxonomies ) ) {
				$warnings[] = "New type '{$new_type}' supports additional taxonomies: " . implode( ', ', $gained_taxonomies ) . ". You may want to assign terms.";
			}

			$warnings[] = "Permalink structure will change. Old: {$old_permalink}. Update any internal links that reference the old URL.";

			$result = wp_update_post( array(
				'ID'        => $post->ID,
				'post_type' => $new_type,
			) );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return array(
				'success'       => true,
				'id'            => $post->ID,
				'old_type'      => $old_type,
				'new_type'      => $new_type,
				'old_permalink' => $old_permalink,
				'new_permalink' => get_permalink( $post->ID ),
				'warnings'      => $warnings,
			);
		},
	) );

	$reg->write( 'content/search-replace', array(
		'label'       => 'Search and Replace in Content',
		'description' => 'Find and replace text across multiple posts. Operates on post_content only. Returns a list of affected post IDs with match counts. Supports plain text matching (not regex). Use dry_run=true to preview changes without saving.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'search', 'replace' ),
			'properties' => array(
				'search' => array(
					'type'        => 'string',
					'description' => 'The text to search for (exact match, case-sensitive)',
				),
				'replace' => array(
					'type'        => 'string',
					'description' => 'The replacement text',
				),
				'post_type' => array(
					'type'        => 'string',
					'description' => 'Limit to a specific post type (default: all public types)',
				),
				'post_ids' => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'integer' ),
					'description' => 'Limit to specific post IDs. If provided, post_type is ignored.',
				),
				'dry_run' => array(
					'type'        => 'boolean',
					'description' => 'Preview changes without saving (default: false)',
					'default'     => false,
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'dry_run'       => array( 'type' => 'boolean' ),
			'search'        => array( 'type' => 'string' ),
			'replace'       => array( 'type' => 'string' ),
			'updated_count' => array( 'type' => 'integer' ),
			'details'       => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) {
			$search  = $input['search'];
			$replace = $input['replace'];
			$dry_run = ! empty( $input['dry_run'] );

			if ( empty( $search ) ) {
				return new WP_Error( 'ability_invalid_input', 'Search string cannot be empty.' );
			}

			if ( $search === $replace ) {
				return new WP_Error( 'ability_invalid_input', 'Search and replace strings are identical.' );
			}

			$query_args = array(
				'posts_per_page' => 500,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'fields'         => 'ids',
				's'              => $search,
			);

			if ( ! empty( $input['post_ids'] ) ) {
				$query_args['post__in'] = array_map( 'absint', $input['post_ids'] );
				$query_args['post_type'] = 'any';
				unset( $query_args['s'] );
			} elseif ( ! empty( $input['post_type'] ) ) {
				$query_args['post_type'] = sanitize_key( $input['post_type'] );
			} else {
				$query_args['post_type'] = 'any';
			}

			$query          = new WP_Query( $query_args );
			$details        = array();
			$total_replaced = 0;
			$posts_affected = 0;

			foreach ( $query->posts as $post_id ) {
				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					continue;
				}

				$post    = get_post( $post_id );
				$content = $post->post_content;
				$count   = substr_count( $content, $search );

				if ( $count === 0 ) {
					continue;
				}

				$posts_affected++;
				$total_replaced += $count;

				if ( ! $dry_run ) {
					wp_update_post( array(
						'ID'           => $post_id,
						'post_content' => str_replace( $search, $replace, $content ),
					) );
				}

				$details[] = array(
					'id'           => $post_id,
					'title'        => $post->post_title,
					'type'         => $post->post_type,
					'replacements' => $count,
				);
			}

			return array(
				'success'            => true,
				'dry_run'            => $dry_run,
				'search'             => $search,
				'replace'            => $replace,
				'posts_scanned'      => count( $query->posts ),
				'posts_affected'     => $posts_affected,
				'total_replacements' => $total_replaced,
				'details'            => $details,
			);
		},
	) );

	// ===== CONTENT — BATCH =====

	$reg->write( 'content/batch-update', array(
		'label'       => 'Batch Update Content',
		'description' => 'Update multiple posts in a single call. Each operation specifies a post ID and the fields to update. Supports title, content, status, excerpt, and author. Returns per-item results with success/error.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'operations' ),
			'properties' => array(
				'operations' => array(
					'type'        => 'array',
					'description' => 'Array of update operations',
					'items'       => array(
						'type'       => 'object',
						'required'   => array( 'id' ),
						'properties' => array(
							'id'      => array( 'type' => 'integer', 'description' => 'Post ID' ),
							'title'   => array( 'type' => 'string', 'description' => 'Post title' ),
							'content' => array( 'type' => 'string', 'description' => 'Post content' ),
							'status'  => array( 'type' => 'string', 'description' => 'Post status' ),
							'excerpt' => array( 'type' => 'string', 'description' => 'Post excerpt' ),
							'author'  => array( 'type' => 'integer', 'description' => 'Author user ID' ),
						),
					),
					'maxItems' => 50,
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'processed' => array( 'type' => 'integer' ),
			'failed'    => array( 'type' => 'integer' ),
			'results'   => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) {
			$results   = array();
			$processed = 0;
			$failed    = 0;

			foreach ( $input['operations'] as $op ) {
				$post_id = $op['id'];

				$check = abilities_for_ai_require_editable_post( $post_id );
				if ( is_wp_error( $check ) ) {
					++$failed;
					$results[] = array(
						'id'      => $post_id,
						'success' => false,
						'error'   => $check->get_error_message(),
					);
					continue;
				}

				$post          = $check;
				$post_type_obj = get_post_type_object( $post->post_type );

				if ( isset( $op['status'] ) && in_array( $op['status'], array( 'publish', 'future' ), true ) ) {
					if ( $post->post_status !== $op['status'] && ! current_user_can( $post_type_obj->cap->publish_posts ) ) {
						++$failed;
						$results[] = array(
							'id'      => $post_id,
							'success' => false,
							'error'   => 'You do not have permission to publish this post type.',
						);
						continue;
					}
				}

				$post_data = array( 'ID' => $post_id );
				if ( isset( $op['title'] ) )   $post_data['post_title']   = $op['title'];
				if ( isset( $op['content'] ) ) $post_data['post_content'] = $op['content'];
				if ( isset( $op['status'] ) )  $post_data['post_status']  = $op['status'];
				if ( isset( $op['excerpt'] ) ) $post_data['post_excerpt'] = $op['excerpt'];
				if ( isset( $op['author'] ) ) {
					$author_id = (int) $op['author'];
					if ( ! get_userdata( $author_id ) ) {
						++$failed;
						$results[] = array(
							'id'      => $post_id,
							'success' => false,
							'error'   => "User ID {$author_id} does not exist.",
						);
						continue;
					}
					$post_data['post_author'] = $author_id;
				}

				$result = wp_update_post( $post_data );
				if ( is_wp_error( $result ) ) {
					++$failed;
					$results[] = array(
						'id'      => $post_id,
						'success' => false,
						'error'   => $result->get_error_message(),
					);
				} else {
					++$processed;
					$results[] = array(
						'id'      => $post_id,
						'success' => true,
					);
				}
			}

			return array(
				'success'   => $failed === 0,
				'processed' => $processed,
				'failed'    => $failed,
				'results'   => $results,
			);
		},
	) );

	// ===== CONTENT — DELETE =====

	// content/delete is free — round-trip: create → test → delete the test.
	$reg->delete( 'content/delete', array(
		'tier'        => 'free',
		'label'       => 'Delete Content',
		'description' => 'Delete content (move to trash or permanently delete)',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Post ID',
				),
				'force' => array(
					'type'        => 'boolean',
					'description' => 'Whether to bypass trash and force deletion',
					'default'     => false,
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'deleted' => array( 'type' => 'boolean' ),
		) ),
		'capability'  => 'delete_posts',
		'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ),
		'callback' => function( $input ) {
			$check = abilities_for_ai_require_editable_post( $input['id'], 'delete_post' );
			if ( is_wp_error( $check ) ) return $check;

			$result = wp_delete_post( $input['id'], $input['force'] ?? false );
			if ( ! $result ) {
				return new WP_Error( 'ability_invalid_input', 'Failed to delete post' );
			}

			return array(
				'success' => true,
				'deleted' => (bool) $result,
			);
		},
	) );


	// ===== CONTENT — DUPLICATE =====

	$reg->write( 'content/duplicate', array(
		'label'       => 'Duplicate Content',
		'description' => 'Duplicate a post, page, or custom post type. Copies content, excerpt, meta, and taxonomy terms. Creates the duplicate as a draft.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Post ID to duplicate (preferred; alias for source_id)',
				),
				'source_id' => array(
					'type'        => 'integer',
					'description' => 'Post ID to duplicate (deprecated alias for id)',
				),
				'title' => array(
					'type'        => 'string',
					'description' => 'Title for the duplicate. Defaults to "Copy of {original title}".',
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'Status for the duplicate',
					'default'     => 'draft',
					'enum'        => array( 'publish', 'draft', 'pending', 'private' ),
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'id'          => array( 'type' => 'integer' ),
			'link'        => array( 'type' => 'string' ),
			'source_id'   => array( 'type' => 'integer' ),
			'title'       => array( 'type' => 'string' ),
			'meta_copied' => array( 'type' => 'integer' ),
			'terms_copied' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
		'callback' => function( $input ) {
			$input['source_id'] = $input['id'] ?? $input['source_id'] ?? null;
			if ( ! $input['source_id'] ) {
				return new WP_Error( 'ability_invalid_input', 'Provide id or source_id.' );
			}
			$source = get_post( $input['source_id'] );
			if ( ! $source ) {
				return new WP_Error( 'ability_invalid_input', 'Source post not found.' );
			}

			$post_type_obj = get_post_type_object( $source->post_type );
			if ( ! $post_type_obj || ! current_user_can( $post_type_obj->cap->create_posts ) ) {
				return new WP_Error( 'rest_forbidden', 'You do not have permission to create this post type.' );
			}

			$new_title = $input['title'] ?? 'Copy of ' . $source->post_title;
			$status    = $input['status'] ?? 'draft';

			$post_data = array(
				'post_title'     => $new_title,
				'post_content'   => $source->post_content,
				'post_excerpt'   => $source->post_excerpt,
				'post_type'      => $source->post_type,
				'post_status'    => $status,
				'post_parent'    => $source->post_parent,
				'menu_order'     => $source->menu_order,
				'post_password'  => $source->post_password,
				'comment_status' => $source->comment_status,
				'ping_status'    => $source->ping_status,
			);

			$new_id = wp_insert_post( $post_data );
			if ( is_wp_error( $new_id ) ) {
				return $new_id;
			}

			// Copy post meta.
			$meta_copied = 0;
			$meta = get_post_meta( $source->ID );
			if ( $meta ) {
				foreach ( $meta as $key => $values ) {
					if ( '_edit_lock' === $key || '_edit_last' === $key ) {
						continue;
					}
					foreach ( $values as $value ) {
						add_post_meta( $new_id, $key, maybe_unserialize( $value ) );
						$meta_copied++;
					}
				}
			}

			// Copy taxonomy terms.
			$terms_copied = 0;
			$taxonomies = get_object_taxonomies( $source->post_type );
			foreach ( $taxonomies as $taxonomy ) {
				$terms = wp_get_object_terms( $source->ID, $taxonomy, array( 'fields' => 'ids' ) );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					wp_set_object_terms( $new_id, $terms, $taxonomy );
					$terms_copied += count( $terms );
				}
			}

			return array(
				'success'      => true,
				'id'           => $new_id,
				'link'         => get_permalink( $new_id ),
				'source_id'    => $source->ID,
				'title'        => $new_title,
				'meta_copied'  => $meta_copied,
				'terms_copied' => $terms_copied,
			);
		},
	) );


	// ===== CONTENT — MULTISITE CLONE =====

	if ( is_multisite() ) {
		$reg->write( 'content/clone-to-site', array(
			'ability_class' => 'WE_Multisite_Ability',
			'label'       => 'Clone Content to Site',
			'description' => 'Clone a post from the current site to another site in the multisite network. Copies content, excerpt, meta, and taxonomy terms server-side — content never passes through the LLM context.',
			'input_schema' => array(
				'type'       => 'object',
				'required'   => array( 'source_post_id', 'target_blog_id' ),
				'properties' => array(
					'source_post_id' => array(
						'type'        => 'integer',
						'description' => 'Post ID on the current site to clone',
					),
					'target_blog_id' => array(
						'type'        => 'integer',
						'description' => 'Blog ID of the destination site',
					),
					'post_type' => array(
						'type'        => 'string',
						'description' => 'Post type on the target site. Defaults to the source post type.',
					),
					'status' => array(
						'type'        => 'string',
						'description' => 'Post status on the target site',
						'default'     => 'draft',
						'enum'        => array( 'publish', 'draft', 'pending', 'private' ),
					),
					'new_title' => array(
						'type'        => 'string',
						'description' => 'Title for the cloned post. Defaults to source title.',
					),
					'copy_meta' => array(
						'type'        => 'boolean',
						'description' => 'Copy post meta to the target (excludes _edit_lock, _edit_last, _thumbnail_id)',
						'default'     => true,
					),
					'copy_terms' => array(
						'type'        => 'boolean',
						'description' => 'Copy taxonomy terms to the target (only taxonomies that exist on both sites)',
						'default'     => true,
					),
				),
			),
			'output_schema' => abilities_for_ai_schema_success_output( array(
				'new_post_id'  => array( 'type' => 'integer' ),
				'target_url'   => array( 'type' => 'string' ),
				'meta_copied'  => array( 'type' => 'integer' ),
				'terms_copied' => array( 'type' => 'integer' ),
				'media_urls'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'warnings'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			) ),
			'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
			'callback' => function( $input ) {
				// Read source post on the current site.
				$source = get_post( (int) $input['source_post_id'] );
				if ( ! $source ) {
					return new WP_Error( 'ability_invalid_input', 'Source post not found.' );
				}

				// Gather source data before switching blogs.
				$source_content   = $source->post_content;
				$source_excerpt   = $source->post_excerpt;
				$source_post_type = $source->post_type;
				$source_title     = $source->post_title;
				$source_meta      = array();
				$source_terms     = array();

				$copy_meta  = $input['copy_meta'] ?? true;
				$copy_terms = $input['copy_terms'] ?? true;

				$skip_meta_keys = array( '_edit_lock', '_edit_last', '_thumbnail_id' );

				if ( $copy_meta ) {
					$meta = get_post_meta( $source->ID );
					if ( $meta ) {
						foreach ( $meta as $key => $values ) {
							if ( in_array( $key, $skip_meta_keys, true ) ) {
								continue;
							}
							$source_meta[ $key ] = array_map( 'maybe_unserialize', $values );
						}
					}
				}

				if ( $copy_terms ) {
					$taxonomies = get_object_taxonomies( $source_post_type );
					foreach ( $taxonomies as $taxonomy ) {
						$terms = wp_get_object_terms( $source->ID, $taxonomy, array( 'fields' => 'all' ) );
						if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
							$source_terms[ $taxonomy ] = $terms;
						}
					}
				}

				// Extract media URLs from content for informational output.
				$media_urls = array();
				if ( preg_match_all( '#https?://[^\s"\'<>]+\.(?:jpg|jpeg|png|gif|webp|svg|mp4|mp3|pdf)#i', $source_content, $matches ) ) {
					$media_urls = array_unique( $matches[0] );
					$media_urls = array_values( $media_urls );
				}

				// Verify target blog exists.
				$target_blog_id = (int) $input['target_blog_id'];
				$target_site = get_blog_details( $target_blog_id );
				if ( ! $target_site ) {
					return new WP_Error( 'ability_invalid_input', "Target blog ID {$target_blog_id} does not exist." );
				}

				// Switch to target blog.
				switch_to_blog( $target_blog_id );
				try {
					$post_type = sanitize_key( $input['post_type'] ?? $source_post_type );
					$post_type_obj = get_post_type_object( $post_type );
					if ( ! $post_type_obj ) {
						return new WP_Error( 'ability_invalid_input', "Post type '{$post_type}' does not exist on the target site." );
					}
					if ( ! current_user_can( $post_type_obj->cap->create_posts ) ) {
						return new WP_Error( 'rest_forbidden', 'You do not have permission to create this post type on the target site.' );
					}

					$new_title = $input['new_title'] ?? $source_title;
					$status    = $input['status'] ?? 'draft';

					$post_data = array(
						'post_title'     => $new_title,
						'post_content'   => $source_content,
						'post_excerpt'   => $source_excerpt,
						'post_type'      => $post_type,
						'post_status'    => $status,
						'comment_status' => $source->comment_status,
						'ping_status'    => $source->ping_status,
						'menu_order'     => $source->menu_order,
					);

					$new_id = wp_insert_post( $post_data );
					if ( is_wp_error( $new_id ) ) {
						return $new_id;
					}

					// Copy meta to target.
					$meta_copied = 0;
					if ( $copy_meta ) {
						foreach ( $source_meta as $key => $values ) {
							foreach ( $values as $value ) {
								add_post_meta( $new_id, $key, $value );
								$meta_copied++;
							}
						}
					}

					// Copy terms — only for taxonomies that exist on both sites.
					$terms_copied = 0;
					if ( $copy_terms ) {
						foreach ( $source_terms as $taxonomy => $terms ) {
							if ( ! taxonomy_exists( $taxonomy ) ) {
								continue;
							}
							// Terms may not exist on target site — match by slug.
							$target_term_ids = array();
							foreach ( $terms as $term ) {
								$existing = get_term_by( 'slug', $term->slug, $taxonomy );
								if ( $existing ) {
									$target_term_ids[] = (int) $existing->term_id;
								}
							}
							if ( ! empty( $target_term_ids ) ) {
								wp_set_object_terms( $new_id, $target_term_ids, $taxonomy );
								$terms_copied += count( $target_term_ids );
							}
						}
					}

					$warnings = array();
					if ( ! empty( $media_urls ) ) {
						$warnings[] = 'Media URLs preserved as-is from source site — images may not exist on the target site.';
					}

					return array(
						'success'      => true,
						'new_post_id'  => $new_id,
						'target_url'   => get_permalink( $new_id ),
						'meta_copied'  => $meta_copied,
						'terms_copied' => $terms_copied,
						'media_urls'   => $media_urls,
						'warnings'     => $warnings,
					);
				} finally {
					restore_current_blog();
				}
			},
		) );
	}

} );
