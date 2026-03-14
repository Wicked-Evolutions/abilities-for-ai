<?php
/**
 * Astra Layout Abilities
 *
 * Page layout resolution, updates, and preset discovery.
 *
 * Abilities:
 *   - astra/get-page-layout   (read)
 *   - astra/update-page-layout (write)
 *   - astra/get-layout-presets (read)
 *
 * @package Abilities_For_AI
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Abilities_For_AI_Registrar( 'astra', 'edit_posts' );

	// ===== GET PAGE LAYOUT =====

	$reg->read( 'astra/get-page-layout', array(
		'label'       => 'Get Page Layout',
		'description' => 'Returns the effective layout for a page, resolving the cascade: page meta overrides global defaults. Each setting shows its value and source.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'post_id' ),
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'The WordPress post/page ID',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_id'       => array( 'type' => 'integer' ),
				'post_type'     => array( 'type' => 'string' ),
				'page_template' => array( 'type' => 'string' ),
				'layout'        => array( 'type' => 'object' ),
			),
		),
		'callback' => function( $input ) {
			$post = get_post( $input['post_id'] );
			if ( ! $post ) {
				return new \WP_Error( 'not_found', 'Post not found.' );
			}
			return array(
				'post_id'       => $post->ID,
				'post_type'     => $post->post_type,
				'page_template' => get_page_template_slug( $post->ID ) ?: 'default',
				'layout'        => astra_abilities_resolve_page_layout( $post->ID ),
			);
		},
	));

	// ===== UPDATE PAGE LAYOUT =====

	$reg->write( 'astra/update-page-layout', array(
		'label'       => 'Update Page Layout',
		'description' => 'Set page-level Astra layout options via post meta. Only updates the fields you provide (partial update). Use get-layout-presets to see valid values.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'post_id' ),
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'The WordPress post/page ID',
				),
				'sidebar' => array(
					'type'        => 'string',
					'description' => 'Sidebar layout: default, left-sidebar, right-sidebar, no-sidebar',
				),
				'content_layout' => array(
					'type'        => 'string',
					'description' => 'Content layout: default, boxed-container, content-boxed-container, plain-container, page-builder, narrow-container',
				),
				'content_style' => array(
					'type'        => 'string',
					'description' => 'Content style: default, boxed, unboxed',
				),
				'disable_title' => array(
					'type'        => 'boolean',
					'description' => 'Set to true to disable the page title',
				),
				'disable_header' => array(
					'type'        => 'boolean',
					'description' => 'Set to true to disable the main header',
				),
				'transparent_header' => array(
					'type'        => 'string',
					'description' => 'Transparent header: default, enabled, disabled',
				),
				'disable_featured_image' => array(
					'type'        => 'boolean',
					'description' => 'Set to true to disable the featured image',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success' => array( 'type' => 'boolean' ),
				'post_id' => array( 'type' => 'integer' ),
				'updated' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
				'layout'  => array( 'type' => 'object' ),
			),
		),
		'callback' => function( $input ) {
			$post = get_post( $input['post_id'] );
			if ( ! $post ) {
				return new \WP_Error( 'not_found', 'Post not found.' );
			}

			$meta_keys = astra_abilities_page_meta_keys();
			$updated   = array();

			$field_map = array(
				'sidebar'                => 'sidebar',
				'content_layout'         => 'content_layout',
				'content_style'          => 'content_style',
				'disable_title'          => 'disable_title',
				'disable_header'         => 'disable_header',
				'transparent_header'     => 'transparent_header',
				'disable_featured_image' => 'disable_featured_image',
			);

			foreach ( $field_map as $input_key => $meta_ref ) {
				if ( ! isset( $input[ $input_key ] ) ) {
					continue;
				}
				$meta_info = $meta_keys[ $meta_ref ];
				$value     = $input[ $input_key ];
				if ( is_bool( $value ) ) {
					$value = $value ? 'disabled' : 'default';
				}
				if ( ! in_array( $value, $meta_info['values'], true ) ) {
					return new \WP_Error(
						'invalid_value',
						'Invalid value "' . $value . '" for ' . $input_key . '. Allowed: ' . implode( ', ', $meta_info['values'] )
					);
				}
				update_post_meta( $post->ID, $meta_info['meta_key'], $value );
				$updated[] = array(
					'field'    => $input_key,
					'meta_key' => $meta_info['meta_key'],
					'value'    => $value,
				);
			}

			if ( empty( $updated ) ) {
				return new \WP_Error( 'no_fields', 'No layout fields were provided to update.' );
			}

			return array(
				'success' => true,
				'post_id' => $post->ID,
				'updated' => $updated,
				'layout'  => astra_abilities_resolve_page_layout( $post->ID ),
			);
		},
	));

	// ===== GET LAYOUT PRESETS =====

	$reg->read( 'astra/get-layout-presets', array(
		'label'       => 'Get Layout Presets',
		'description' => 'Returns all valid Astra layout options with descriptions and current global defaults. Use this before calling update-page-layout.',
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'sidebar_options'        => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
				'content_layout_options' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
				'content_style_options'  => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
				'global_defaults'        => array( 'type' => 'object' ),
			),
		),
		'callback' => function( $input ) {
			return array(
				'sidebar_options' => array(
					array( 'value' => 'default',       'description' => 'Use global default sidebar setting' ),
					array( 'value' => 'left-sidebar',  'description' => 'Sidebar on the left side' ),
					array( 'value' => 'right-sidebar', 'description' => 'Sidebar on the right side' ),
					array( 'value' => 'no-sidebar',    'description' => 'No sidebar, full content width' ),
				),
				'content_layout_options' => array(
					array( 'value' => 'default',                 'description' => 'Use global default content layout' ),
					array( 'value' => 'boxed-container',         'description' => 'Boxed layout with contained width' ),
					array( 'value' => 'content-boxed-container', 'description' => 'Content area boxed, background full-width' ),
					array( 'value' => 'plain-container',         'description' => 'Full-width within container' ),
					array( 'value' => 'page-builder',            'description' => 'Stretched full-width, no container padding' ),
					array( 'value' => 'narrow-container',        'description' => 'Narrow centered content' ),
				),
				'content_style_options' => array(
					array( 'value' => 'default', 'description' => 'Use global default content style' ),
					array( 'value' => 'boxed',   'description' => 'Content in a boxed card-like container' ),
					array( 'value' => 'unboxed', 'description' => 'Content without box styling' ),
				),
				'global_defaults' => array(
					'sidebar'         => astra_get_option( 'site-sidebar-layout' ),
					'content_layout'  => astra_get_option( 'site-content-layout' ),
					'content_style'   => astra_get_option( 'site-content-style' ),
					'container_width' => astra_get_option( 'site-content-width' ),
					'narrow_width'    => astra_get_option( 'narrow-container-max-width' ),
				),
			);
		},
	));

}, 100 );
