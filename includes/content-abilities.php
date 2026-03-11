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
 * @package WordPress_Abilities_Suite
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {
	$reg = new WP_Abilities_Suite_Registrar( 'content', 'edit_posts' );

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
		'output_schema' => wp_abilities_suite_schema_list_output( 'posts', array(
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
		'output_schema' => wp_abilities_suite_schema_item_output( array(
			'id'        => array( 'type' => 'integer' ),
			'title'     => array( 'type' => 'string' ),
			'content'   => array( 'type' => 'string' ),
			'status'    => array( 'type' => 'string' ),
			'post_type' => array( 'type' => 'string' ),
			'date'      => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$check = wp_abilities_suite_require_editable_post( $input['id'] );
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
		'output_schema' => wp_abilities_suite_schema_item_output( array(
			'id'     => array( 'type' => 'integer' ),
			'title'  => array( 'type' => 'string' ),
			'status' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$check = wp_abilities_suite_require_editable_post( $input['id'] );
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
		'output_schema' => wp_abilities_suite_schema_collection_output( 'post_types', array(
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
		'output_schema' => wp_abilities_suite_schema_item_output( array(
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

			$check = wp_abilities_suite_require_editable_post( $post_id );
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
		'output_schema' => wp_abilities_suite_schema_item_output( array(
			'id'        => array( 'type' => 'integer' ),
			'title'     => array( 'type' => 'string' ),
			'status'    => array( 'type' => 'string' ),
			'post_type' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$query = new WP_Query( array(
				'name'           => $input['slug'],
				'post_type'      => $input['post_type'] ?? 'post',
				'posts_per_page' => 1,
			) );

			if ( ! $query->have_posts() ) {
				return new WP_Error( 'not_found', 'No content found with this slug' );
			}

			$check = wp_abilities_suite_require_editable_post( $query->posts[0]->ID );
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
			),
		),
		'output_schema' => wp_abilities_suite_schema_success_output( array(
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

			return array(
				'success' => true,
				'id'      => $post_id,
				'link'    => get_permalink( $post_id ),
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
		'output_schema' => wp_abilities_suite_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$check = wp_abilities_suite_require_editable_post( $input['id'] );
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
		'output_schema' => wp_abilities_suite_schema_success_output( array(
			'id'           => array( 'type' => 'integer' ),
			'new_post_type' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$check = wp_abilities_suite_require_editable_post( $input['id'] );
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
		'output_schema' => wp_abilities_suite_schema_success_output( array(
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
		'output_schema' => wp_abilities_suite_schema_success_output( array(
			'deleted' => array( 'type' => 'boolean' ),
		) ),
		'capability'  => 'delete_posts',
		'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ),
		'callback' => function( $input ) {
			$check = wp_abilities_suite_require_editable_post( $input['id'], 'delete_post' );
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

} );
