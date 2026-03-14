<?php
/**
 * Astra Blog, Archive & CPT Layout Abilities
 *
 * Structured access to blog/archive settings, single post config,
 * and per-CPT layout defaults.
 *
 * Abilities:
 *   - astra/get-blog-config          (read)  — P1
 *   - astra/update-blog-config       (write) — P1
 *   - astra/get-single-post-config   (read)  — P1
 *   - astra/get-cpt-layout-defaults  (read)  — P2
 *   - astra/update-cpt-layout-defaults (write) — P2
 *
 * @package Abilities_For_AI
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Abilities_For_AI_Registrar( 'astra', 'edit_posts' );

	// ===== GET BLOG CONFIG =====

	$reg->read( 'astra/get-blog-config', array(
		'label'       => 'Get Blog Config',
		'description' => 'Returns structured blog/archive settings: post structure, content type, meta display, pagination, excerpt length, grid layout, and featured image position.',
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'blog_layout'       => array( 'type' => 'object' ),
				'post_structure'    => array( 'type' => 'object' ),
				'meta'              => array( 'type' => 'object' ),
				'pagination'        => array( 'type' => 'object' ),
				'archive_settings'  => array( 'type' => 'object' ),
			),
		),
		'callback' => function( $input ) {
			$result = array();

			$result['blog_layout'] = array(
				'layout'           => astra_get_option( 'blog-layout' ),
				'grid'             => astra_get_option( 'blog-grid' ),
				'grid_layout'      => astra_get_option( 'blog-grid-layout' ),
				'content_width'    => astra_get_option( 'blog-width' ),
				'sidebar'          => astra_get_option( 'archive-post-sidebar-layout' ),
				'content_layout'   => astra_get_option( 'archive-post-content-layout' ),
				'content_style'    => astra_get_option( 'archive-post-content-style' ),
			);

			$result['post_structure'] = array(
				'structure'        => astra_get_option( 'blog-post-structure' ),
				'content_type'     => astra_get_option( 'blog-post-content' ),
				'excerpt_count'    => astra_get_option( 'blog-excerpt-count' ),
				'read_more_text'   => astra_get_option( 'blog-read-more-text' ),
				'read_more_as_btn' => astra_get_option( 'blog-read-more-as-button' ),
			);

			$result['meta'] = array(
				'meta_keys'      => astra_get_option( 'blog-meta' ),
				'date_format'    => astra_get_option( 'blog-meta-date-format' ),
				'author_avatar'  => astra_get_option( 'blog-meta-author-avatar' ),
				'category_style' => astra_get_option( 'blog-category-style' ),
				'tag_style'      => astra_get_option( 'blog-tag-style' ),
			);

			$result['pagination'] = array(
				'type'         => astra_get_option( 'blog-pagination' ),
				'infinite_msg' => astra_get_option( 'blog-infinite-scroll-last-text' ),
			);

			$result['archive_settings'] = array(
				'archive_title'       => astra_get_option( 'archive-title-prefix' ),
				'post_featured_image' => astra_get_option( 'blog-post-featured-image-position' ),
			);

			return $result;
		},
	));

	// ===== UPDATE BLOG CONFIG =====

	$reg->write( 'astra/update-blog-config', array(
		'label'       => 'Update Blog Config',
		'description' => 'Update blog/archive settings. Only provided keys are changed. Use get-blog-config to see current values and valid structures.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'layout'             => array( 'type' => 'string', 'description' => 'Blog layout: blog-layout-1 or blog-layout-2 (masonry)' ),
				'grid'               => array( 'type' => 'integer', 'description' => 'Grid columns: 1, 2, 3, or 4' ),
				'grid_layout'        => array( 'type' => 'string', 'description' => 'Grid layout style' ),
				'content_type'       => array( 'type' => 'string', 'description' => 'Content display: full-content or excerpt' ),
				'excerpt_count'      => array( 'type' => 'integer', 'description' => 'Excerpt word count' ),
				'read_more_text'     => array( 'type' => 'string', 'description' => 'Read more button text' ),
				'read_more_as_btn'   => array( 'type' => 'boolean', 'description' => 'Show read more as button (true) or link (false)' ),
				'pagination'         => array( 'type' => 'string', 'description' => 'Pagination type: number, infinite, or disabled' ),
				'post_structure'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Post structure elements array' ),
				'meta_keys'          => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Meta keys to display' ),
				'featured_image_pos' => array( 'type' => 'string', 'description' => 'Featured image position in blog list' ),
				'sidebar'            => array( 'type' => 'string', 'description' => 'Archive sidebar: left-sidebar, right-sidebar, no-sidebar, default' ),
				'content_layout'     => array( 'type' => 'string', 'description' => 'Archive content layout' ),
				'content_style'      => array( 'type' => 'string', 'description' => 'Archive content style: boxed, unboxed' ),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success'      => array( 'type' => 'boolean' ),
				'updated_keys' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
		),
		'callback' => function( $input ) {
			$map = array(
				'layout'             => 'blog-layout',
				'grid'               => 'blog-grid',
				'grid_layout'        => 'blog-grid-layout',
				'content_type'       => 'blog-post-content',
				'excerpt_count'      => 'blog-excerpt-count',
				'read_more_text'     => 'blog-read-more-text',
				'read_more_as_btn'   => 'blog-read-more-as-button',
				'pagination'         => 'blog-pagination',
				'post_structure'     => 'blog-post-structure',
				'meta_keys'          => 'blog-meta',
				'featured_image_pos' => 'blog-post-featured-image-position',
				'sidebar'            => 'archive-post-sidebar-layout',
				'content_layout'     => 'archive-post-content-layout',
				'content_style'      => 'archive-post-content-style',
			);

			$updated = array();
			foreach ( $map as $key => $option ) {
				if ( isset( $input[ $key ] ) ) {
					astra_update_option( $option, $input[ $key ] );
					$updated[] = $option;
				}
			}

			if ( empty( $updated ) ) {
				return new \WP_Error( 'no_changes', 'No valid settings keys provided.' );
			}

			return array(
				'success'      => true,
				'updated_keys' => $updated,
			);
		},
	));

	// ===== GET SINGLE POST CONFIG =====

	$reg->read( 'astra/get-single-post-config', array(
		'label'       => 'Get Single Post Config',
		'description' => 'Returns single post display settings: title position, meta, featured image, structure, navigation, and related posts.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_type' => array(
					'type'        => 'string',
					'description' => 'Post type to get config for (default: post)',
					'default'     => 'post',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_type' => array( 'type' => 'string' ),
				'structure' => array( 'type' => 'object' ),
				'layout'    => array( 'type' => 'object' ),
				'meta'      => array( 'type' => 'object' ),
			),
		),
		'callback' => function( $input ) {
			$post_type = $input['post_type'] ?? 'post';

			$result = array(
				'post_type' => $post_type,
			);

			$result['structure'] = array(
				'post_structure'       => astra_get_option( 'blog-single-post-structure' ),
				'title_position'       => astra_get_option( 'blog-single-post-title-position' ),
				'featured_image'       => astra_get_option( 'blog-single-featured-image-position' ),
				'featured_image_width' => astra_get_option( 'blog-single-featured-image-width' ),
			);

			$result['layout'] = array(
				'sidebar'        => astra_get_option( 'single-' . $post_type . '-sidebar-layout' ),
				'content_layout' => astra_get_option( 'single-' . $post_type . '-content-layout' ),
				'content_style'  => astra_get_option( 'single-' . $post_type . '-content-style' ),
			);

			$result['meta'] = array(
				'meta_keys'     => astra_get_option( 'blog-single-meta' ),
				'date_format'   => astra_get_option( 'blog-single-meta-date-format' ),
				'author_avatar' => astra_get_option( 'blog-single-meta-author-avatar' ),
			);

			// Post navigation.
			$result['navigation'] = array(
				'enabled' => astra_get_option( 'ast-single-post-navigation' ),
			);

			// Related posts (Astra Pro).
			if ( astra_abilities_is_pro_active() ) {
				$result['related_posts'] = array(
					'enabled'       => astra_get_option( 'enable-related-posts' ),
					'title'         => astra_get_option( 'related-posts-title' ),
					'total_count'   => astra_get_option( 'related-posts-total-count' ),
					'grid_columns'  => astra_get_option( 'related-posts-grid' ),
					'structure'     => astra_get_option( 'related-posts-structure' ),
					'order_by'      => astra_get_option( 'related-posts-order-by' ),
					'order'         => astra_get_option( 'related-posts-order' ),
				);
			}

			return $result;
		},
	));

	// ===== GET CPT LAYOUT DEFAULTS =====

	$reg->read( 'astra/get-cpt-layout-defaults', array(
		'label'       => 'Get CPT Layout Defaults',
		'description' => 'Returns per-post-type layout defaults: sidebar, content layout, content style for both archive and single views. Lists all registered public post types.',
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_types' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			),
		),
		'callback' => function( $input ) {
			$post_types = get_post_types( array( 'public' => true ), 'objects' );
			$results    = array();

			foreach ( $post_types as $pt ) {
				$slug = $pt->name;
				$results[] = array(
					'post_type' => $slug,
					'label'     => $pt->label,
					'archive'   => array(
						'sidebar'        => astra_get_option( 'archive-' . $slug . '-sidebar-layout' ),
						'content_layout' => astra_get_option( 'archive-' . $slug . '-content-layout' ),
						'content_style'  => astra_get_option( 'archive-' . $slug . '-content-style' ),
					),
					'single' => array(
						'sidebar'        => astra_get_option( 'single-' . $slug . '-sidebar-layout' ),
						'content_layout' => astra_get_option( 'single-' . $slug . '-content-layout' ),
						'content_style'  => astra_get_option( 'single-' . $slug . '-content-style' ),
					),
				);
			}

			return array(
				'post_types' => $results,
			);
		},
	));

	// ===== UPDATE CPT LAYOUT DEFAULTS =====

	$reg->write( 'astra/update-cpt-layout-defaults', array(
		'label'       => 'Update CPT Layout Defaults',
		'description' => 'Update per-post-type layout defaults for archive and/or single views. Provide the post_type slug and the settings to change.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'post_type' ),
			'properties' => array(
				'post_type' => array(
					'type'        => 'string',
					'description' => 'Post type slug (e.g., post, page, product)',
				),
				'archive_sidebar' => array(
					'type'        => 'string',
					'description' => 'Archive sidebar: default, left-sidebar, right-sidebar, no-sidebar',
				),
				'archive_content_layout' => array(
					'type'        => 'string',
					'description' => 'Archive content layout',
				),
				'archive_content_style' => array(
					'type'        => 'string',
					'description' => 'Archive content style: boxed, unboxed',
				),
				'single_sidebar' => array(
					'type'        => 'string',
					'description' => 'Single sidebar: default, left-sidebar, right-sidebar, no-sidebar',
				),
				'single_content_layout' => array(
					'type'        => 'string',
					'description' => 'Single content layout',
				),
				'single_content_style' => array(
					'type'        => 'string',
					'description' => 'Single content style: boxed, unboxed',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success'      => array( 'type' => 'boolean' ),
				'post_type'    => array( 'type' => 'string' ),
				'updated_keys' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
		),
		'callback' => function( $input ) {
			$pt = sanitize_key( $input['post_type'] );

			if ( ! post_type_exists( $pt ) ) {
				return new \WP_Error( 'invalid_post_type', 'Post type "' . $pt . '" does not exist.' );
			}

			$map = array(
				'archive_sidebar'        => 'archive-' . $pt . '-sidebar-layout',
				'archive_content_layout' => 'archive-' . $pt . '-content-layout',
				'archive_content_style'  => 'archive-' . $pt . '-content-style',
				'single_sidebar'         => 'single-' . $pt . '-sidebar-layout',
				'single_content_layout'  => 'single-' . $pt . '-content-layout',
				'single_content_style'   => 'single-' . $pt . '-content-style',
			);

			$updated = array();
			foreach ( $map as $key => $option ) {
				if ( isset( $input[ $key ] ) ) {
					astra_update_option( $option, $input[ $key ] );
					$updated[] = $option;
				}
			}

			if ( empty( $updated ) ) {
				return new \WP_Error( 'no_changes', 'No layout settings provided to update.' );
			}

			return array(
				'success'      => true,
				'post_type'    => $pt,
				'updated_keys' => $updated,
			);
		},
	));

}, 100 );
