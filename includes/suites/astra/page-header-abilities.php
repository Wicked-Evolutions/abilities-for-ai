<?php
/**
 * Astra Page Header Abilities (Pro)
 *
 * CRUD for the `astra_adv_header` CPT — per-page custom headers with
 * background images, breadcrumbs, and display conditions.
 *
 * Abilities:
 *   - astra/list-page-headers   (read)   — P2
 *   - astra/get-page-header     (read)   — P2
 *   - astra/create-page-header  (write)  — P2
 *   - astra/update-page-header  (write)  — P2
 *   - astra/delete-page-header  (delete) — P2
 *
 * All abilities require Astra Pro with the advanced-headers module active.
 *
 * @package Abilities_For_AI
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	// Page Headers require Astra Pro with advanced-headers module.
	if ( ! defined( 'ASTRA_EXT_VER' ) ) {
		return;
	}

	// Check if the advanced-headers module is active.
	if ( ! post_type_exists( 'astra_adv_header' ) ) {
		return;
	}

	$reg = new Abilities_For_AI_Registrar( 'astra', 'edit_theme_options' );

	// ===== LIST PAGE HEADERS =====

	$reg->read( 'astra/list-page-headers', array(
		'label'       => 'List Page Headers',
		'description' => 'Lists all Astra Pro Page Headers (advanced headers) with titles, status, and display conditions.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'status' => array(
					'type'        => 'string',
					'description' => 'Filter by status: publish, draft, any (default: any)',
					'default'     => 'any',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'count'   => array( 'type' => 'integer' ),
				'headers' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			),
		),
		'callback' => function( $input ) {
			$status = $input['status'] ?? 'any';

			$args = array(
				'post_type'      => 'astra_adv_header',
				'post_status'    => $status,
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			);

			$query   = new \WP_Query( $args );
			$headers = array();

			foreach ( $query->posts as $post ) {
				$headers[] = array(
					'id'         => $post->ID,
					'title'      => $post->post_title,
					'status'     => $post->post_status,
					'modified'   => $post->post_modified,
					'layout'     => get_post_meta( $post->ID, 'ast-advanced-headers-layout', true ),
					'bg_type'    => get_post_meta( $post->ID, 'ast-advanced-headers-bg-type', true ),
					'breadcrumb' => get_post_meta( $post->ID, 'ast-advanced-headers-breadcrumb', true ),
				);
			}

			return array(
				'count'   => count( $headers ),
				'headers' => $headers,
			);
		},
	));

	// ===== GET PAGE HEADER =====

	$reg->read( 'astra/get-page-header', array(
		'label'       => 'Get Page Header',
		'description' => 'Returns a single Page Header by ID with full configuration: layout, background, breadcrumb, typography, colors, and display conditions.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'The Page Header post ID',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id'       => array( 'type' => 'integer' ),
				'title'    => array( 'type' => 'string' ),
				'status'   => array( 'type' => 'string' ),
				'config'   => array( 'type' => 'object' ),
				'display'  => array( 'type' => 'object' ),
			),
		),
		'callback' => function( $input ) {
			$post = get_post( $input['id'] );
			if ( ! $post || 'astra_adv_header' !== $post->post_type ) {
				return new \WP_Error( 'not_found', 'Page Header not found: ' . $input['id'] );
			}

			// Collect all meta for this page header.
			$meta_keys = array(
				'layout'             => 'ast-advanced-headers-layout',
				'bg_type'            => 'ast-advanced-headers-bg-type',
				'bg_color'           => 'ast-advanced-headers-bg-color',
				'bg_image'           => 'ast-advanced-headers-bg-image',
				'bg_image_position'  => 'ast-advanced-headers-bg-image-position',
				'bg_image_size'      => 'ast-advanced-headers-bg-image-size',
				'bg_image_repeat'    => 'ast-advanced-headers-bg-image-repeat',
				'breadcrumb'         => 'ast-advanced-headers-breadcrumb',
				'title_color'        => 'ast-advanced-headers-title-color',
				'subtitle_color'     => 'ast-advanced-headers-subtitle-color',
				'breadcrumb_color'   => 'ast-advanced-headers-breadcrumb-color',
				'breadcrumb_separator_color' => 'ast-advanced-headers-breadcrumb-separator-color',
				'custom_title'       => 'ast-advanced-headers-custom-title',
				'custom_subtitle'    => 'ast-advanced-headers-custom-subtitle',
				'merge_header'       => 'ast-advanced-headers-merge-header',
				'header_bg_color'    => 'ast-advanced-headers-header-bg-color',
				'site_title_color'   => 'ast-advanced-headers-site-title-color',
				'site_tagline_color' => 'ast-advanced-headers-site-tagline-color',
				'menu_color'         => 'ast-advanced-headers-menu-color',
				'menu_h_color'       => 'ast-advanced-headers-menu-h-color',
				'menu_a_color'       => 'ast-advanced-headers-menu-a-color',
				'submenu_color'      => 'ast-advanced-headers-submenu-color',
				'submenu_h_color'    => 'ast-advanced-headers-submenu-h-color',
				'submenu_a_color'    => 'ast-advanced-headers-submenu-a-color',
				'submenu_bg_color'   => 'ast-advanced-headers-submenu-bg-color',
			);

			$config = array();
			foreach ( $meta_keys as $key => $meta_key ) {
				$config[ $key ] = get_post_meta( $post->ID, $meta_key, true );
			}

			// Display conditions.
			$display = array(
				'display_on' => get_post_meta( $post->ID, 'ast-advanced-headers-display-on', true ),
				'exclude_on' => get_post_meta( $post->ID, 'ast-advanced-headers-exclude-on', true ),
				'user_roles' => get_post_meta( $post->ID, 'ast-advanced-headers-user-roles', true ),
			);

			return array(
				'id'      => $post->ID,
				'title'   => $post->post_title,
				'status'  => $post->post_status,
				'content' => $post->post_content,
				'config'  => $config,
				'display' => $display,
			);
		},
	));

	// ===== CREATE PAGE HEADER =====

	$reg->write( 'astra/create-page-header', array(
		'label'       => 'Create Page Header',
		'description' => 'Create a new Astra Pro Page Header with layout, background, breadcrumb, and display conditions.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'title' ),
			'properties' => array(
				'title'    => array( 'type' => 'string', 'description' => 'Page Header title' ),
				'content'  => array( 'type' => 'string', 'description' => 'Block content for custom header layout' ),
				'status'   => array( 'type' => 'string', 'description' => 'Post status (default: publish)', 'default' => 'publish' ),
				'layout'   => array( 'type' => 'string', 'description' => 'Layout type' ),
				'bg_type'  => array( 'type' => 'string', 'description' => 'Background type: color, image, none' ),
				'bg_color' => array( 'type' => 'string', 'description' => 'Background color (hex)' ),
				'bg_image' => array( 'type' => 'string', 'description' => 'Background image URL' ),
				'breadcrumb' => array( 'type' => 'string', 'description' => 'Breadcrumb display: enabled, disabled' ),
				'custom_title' => array( 'type' => 'string', 'description' => 'Custom title override text' ),
				'custom_subtitle' => array( 'type' => 'string', 'description' => 'Custom subtitle text' ),
				'merge_header' => array( 'type' => 'string', 'description' => 'Merge with site header: enabled, disabled' ),
				'display_on' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'description' => 'Display conditions' ),
				'exclude_on' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'description' => 'Exclusion conditions' ),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success' => array( 'type' => 'boolean' ),
				'id'      => array( 'type' => 'integer' ),
				'title'   => array( 'type' => 'string' ),
			),
		),
		'callback' => function( $input ) {
			$post_id = wp_insert_post( array(
				'post_title'   => sanitize_text_field( $input['title'] ),
				'post_content' => $input['content'] ?? '',
				'post_type'    => 'astra_adv_header',
				'post_status'  => $input['status'] ?? 'publish',
			));

			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}

			// Set meta fields.
			$meta_map = array(
				'layout'           => 'ast-advanced-headers-layout',
				'bg_type'          => 'ast-advanced-headers-bg-type',
				'bg_color'         => 'ast-advanced-headers-bg-color',
				'bg_image'         => 'ast-advanced-headers-bg-image',
				'breadcrumb'       => 'ast-advanced-headers-breadcrumb',
				'custom_title'     => 'ast-advanced-headers-custom-title',
				'custom_subtitle'  => 'ast-advanced-headers-custom-subtitle',
				'merge_header'     => 'ast-advanced-headers-merge-header',
			);

			foreach ( $meta_map as $key => $meta_key ) {
				if ( isset( $input[ $key ] ) ) {
					update_post_meta( $post_id, $meta_key, $input[ $key ] );
				}
			}

			// Display conditions.
			if ( isset( $input['display_on'] ) ) {
				update_post_meta( $post_id, 'ast-advanced-headers-display-on', $input['display_on'] );
			}
			if ( isset( $input['exclude_on'] ) ) {
				update_post_meta( $post_id, 'ast-advanced-headers-exclude-on', $input['exclude_on'] );
			}

			return array(
				'success' => true,
				'id'      => $post_id,
				'title'   => $input['title'],
			);
		},
	));

	// ===== UPDATE PAGE HEADER =====

	$reg->write( 'astra/update-page-header', array(
		'label'       => 'Update Page Header',
		'description' => 'Update an existing Page Header. Only provided fields are changed.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'       => array( 'type' => 'integer', 'description' => 'Page Header post ID' ),
				'title'    => array( 'type' => 'string', 'description' => 'Title' ),
				'content'  => array( 'type' => 'string', 'description' => 'Block content' ),
				'status'   => array( 'type' => 'string', 'description' => 'Post status' ),
				'layout'   => array( 'type' => 'string' ),
				'bg_type'  => array( 'type' => 'string' ),
				'bg_color' => array( 'type' => 'string' ),
				'bg_image' => array( 'type' => 'string' ),
				'breadcrumb'       => array( 'type' => 'string' ),
				'custom_title'     => array( 'type' => 'string' ),
				'custom_subtitle'  => array( 'type' => 'string' ),
				'merge_header'     => array( 'type' => 'string' ),
				'display_on'       => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
				'exclude_on'       => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success' => array( 'type' => 'boolean' ),
				'id'      => array( 'type' => 'integer' ),
				'updated' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
		),
		'callback' => function( $input ) {
			$post = get_post( $input['id'] );
			if ( ! $post || 'astra_adv_header' !== $post->post_type ) {
				return new \WP_Error( 'not_found', 'Page Header not found: ' . $input['id'] );
			}

			$updated = array();

			// Update post fields.
			$post_update = array( 'ID' => $post->ID );
			if ( isset( $input['title'] ) ) {
				$post_update['post_title'] = sanitize_text_field( $input['title'] );
				$updated[] = 'title';
			}
			if ( isset( $input['content'] ) ) {
				$post_update['post_content'] = $input['content'];
				$updated[] = 'content';
			}
			if ( isset( $input['status'] ) ) {
				$post_update['post_status'] = $input['status'];
				$updated[] = 'status';
			}

			if ( count( $post_update ) > 1 ) {
				$result = wp_update_post( $post_update );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}

			// Update meta fields.
			$meta_map = array(
				'layout'           => 'ast-advanced-headers-layout',
				'bg_type'          => 'ast-advanced-headers-bg-type',
				'bg_color'         => 'ast-advanced-headers-bg-color',
				'bg_image'         => 'ast-advanced-headers-bg-image',
				'breadcrumb'       => 'ast-advanced-headers-breadcrumb',
				'custom_title'     => 'ast-advanced-headers-custom-title',
				'custom_subtitle'  => 'ast-advanced-headers-custom-subtitle',
				'merge_header'     => 'ast-advanced-headers-merge-header',
			);

			foreach ( $meta_map as $key => $meta_key ) {
				if ( isset( $input[ $key ] ) ) {
					update_post_meta( $post->ID, $meta_key, $input[ $key ] );
					$updated[] = $key;
				}
			}

			if ( isset( $input['display_on'] ) ) {
				update_post_meta( $post->ID, 'ast-advanced-headers-display-on', $input['display_on'] );
				$updated[] = 'display_on';
			}
			if ( isset( $input['exclude_on'] ) ) {
				update_post_meta( $post->ID, 'ast-advanced-headers-exclude-on', $input['exclude_on'] );
				$updated[] = 'exclude_on';
			}

			if ( empty( $updated ) ) {
				return new \WP_Error( 'no_changes', 'No fields provided to update.' );
			}

			return array(
				'success' => true,
				'id'      => $post->ID,
				'updated' => $updated,
			);
		},
	));

	// ===== DELETE PAGE HEADER =====

	$reg->delete( 'astra/delete-page-header', array(
		'label'       => 'Delete Page Header',
		'description' => 'Delete an Astra Pro Page Header. By default moves to trash; use force=true to permanently delete.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'The Page Header post ID to delete',
				),
				'force' => array(
					'type'        => 'boolean',
					'description' => 'Permanently delete (true) or move to trash (false, default)',
					'default'     => false,
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success' => array( 'type' => 'boolean' ),
				'id'      => array( 'type' => 'integer' ),
				'title'   => array( 'type' => 'string' ),
				'action'  => array( 'type' => 'string' ),
			),
		),
		'callback' => function( $input ) {
			$post = get_post( $input['id'] );
			if ( ! $post || 'astra_adv_header' !== $post->post_type ) {
				return new \WP_Error( 'not_found', 'Page Header not found: ' . $input['id'] );
			}

			$title = $post->post_title;
			$force = $input['force'] ?? false;

			$result = wp_delete_post( $post->ID, $force );
			if ( ! $result ) {
				return new \WP_Error( 'delete_failed', 'Failed to delete Page Header.' );
			}

			return array(
				'success' => true,
				'id'      => $input['id'],
				'title'   => $title,
				'action'  => $force ? 'permanently_deleted' : 'trashed',
			);
		},
	));

}, 100 );
