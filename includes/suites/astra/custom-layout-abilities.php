<?php
/**
 * Astra Custom Layout Abilities
 *
 * Full CRUD for Astra Custom Layouts (advanced hooks) — all 6 layout types.
 *
 * Abilities:
 *   - astra/list-custom-layouts  (read)
 *   - astra/get-custom-layout    (read)
 *   - astra/create-custom-layout (write)
 *   - astra/update-custom-layout (write)
 *   - astra/delete-custom-layout (delete)
 *
 * @package Abilities_For_AI
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Abilities_For_AI_Registrar( 'astra', 'edit_posts' );

	// ===== LIST CUSTOM LAYOUTS =====

	$reg->read( 'astra/list-custom-layouts', array(
		'label'       => 'List Custom Layouts',
		'description' => 'Lists all Astra Custom Layout posts (advanced hooks) with titles, hook locations, layout types, display conditions, and status.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'layout_type' => array(
					'type'        => 'string',
					'description' => 'Optional: filter by layout type (hooks, header, footer, content, template, 404)',
				),
				'hook' => array(
					'type'        => 'string',
					'description' => 'Optional: filter by hook location (e.g., "astra_header_after")',
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'Optional: filter by post status (default: any). Values: publish, draft, any',
					'default'     => 'any',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'count'   => array( 'type' => 'integer' ),
				'layouts' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			),
		),
		'callback' => function( $input ) {
			if ( ! astra_abilities_is_pro_active() ) {
				return array(
					'count'   => 0,
					'layouts' => array(),
					'message' => 'Astra Pro is not active. Custom Layouts require Astra Pro.',
				);
			}

			$args = array(
				'post_type'      => 'astra-advanced-hook',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			);

			$status = $input['status'] ?? 'any';
			$args['post_status'] = 'any' === $status ? array( 'publish', 'draft' ) : $status;

			$query   = new \WP_Query( $args );
			$layouts = array();

			foreach ( $query->posts as $layout_post ) {
				$hook_action   = get_post_meta( $layout_post->ID, 'ast-advanced-hook-action', true );
				$layout_type   = get_post_meta( $layout_post->ID, 'ast-advanced-hook-layout', true );
				$priority      = get_post_meta( $layout_post->ID, 'ast-advanced-hook-priority', true );
				$display_rules = get_post_meta( $layout_post->ID, 'ast-advanced-hook-location', true );
				$template_type = get_post_meta( $layout_post->ID, 'ast-advanced-hook-template-type', true );
				$editor_type   = get_post_meta( $layout_post->ID, 'editor_type', true );

				if ( ! empty( $input['hook'] ) && $hook_action !== $input['hook'] ) {
					continue;
				}
				if ( ! empty( $input['layout_type'] ) && $layout_type !== $input['layout_type'] ) {
					continue;
				}

				$item = array(
					'id'            => $layout_post->ID,
					'title'         => $layout_post->post_title,
					'status'        => $layout_post->post_status,
					'layout_type'   => $layout_type ?: 'hooks',
					'hook'          => $hook_action,
					'priority'      => $priority ? (int) $priority : 10,
					'display_rules' => $display_rules ?: array(),
					'editor_type'   => $editor_type ?: '',
					'modified'      => $layout_post->post_modified,
				);
				if ( 'template' === $layout_type ) {
					$item['template_type'] = $template_type ?: '';
				}
				$layouts[] = $item;
			}

			return array(
				'count'   => count( $layouts ),
				'layouts' => $layouts,
			);
		},
	));

	// ===== GET CUSTOM LAYOUT =====

	$reg->read( 'astra/get-custom-layout', array(
		'label'       => 'Get Custom Layout',
		'description' => 'Returns a Custom Layout by ID with full content, all meta fields, display/exclusion rules, and user role restrictions.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'The Custom Layout post ID',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id'              => array( 'type' => 'integer' ),
				'title'           => array( 'type' => 'string' ),
				'status'          => array( 'type' => 'string' ),
				'layout_type'     => array( 'type' => 'string' ),
				'content'         => array( 'type' => 'string' ),
				'editor_type'     => array( 'type' => 'string' ),
				'php_code'        => array( 'type' => 'string' ),
				'display_rules'   => array( 'type' => 'object' ),
				'exclusion_rules' => array( 'type' => 'object' ),
				'user_roles'      => array( 'type' => 'object' ),
				'devices'         => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
		),
		'callback' => function( $input ) {
			if ( ! astra_abilities_is_pro_active() ) {
				return new \WP_Error( 'pro_required', 'Astra Pro is not active. Custom Layouts require Astra Pro.' );
			}

			$layout_post = get_post( $input['id'] );
			if ( ! $layout_post || 'astra-advanced-hook' !== $layout_post->post_type ) {
				return new \WP_Error( 'not_found', 'Custom Layout not found: ' . $input['id'] );
			}

			$result = array(
				'id'              => $layout_post->ID,
				'title'           => $layout_post->post_title,
				'status'          => $layout_post->post_status,
				'layout_type'     => get_post_meta( $layout_post->ID, 'ast-advanced-hook-layout', true ) ?: 'hooks',
				'content'         => $layout_post->post_content,
				'editor_type'     => get_post_meta( $layout_post->ID, 'editor_type', true ) ?: '',
				'php_code'        => get_post_meta( $layout_post->ID, 'ast-advanced-hook-php-code', true ) ?: '',
				'hook'            => get_post_meta( $layout_post->ID, 'ast-advanced-hook-action', true ) ?: '',
				'priority'        => (int) ( get_post_meta( $layout_post->ID, 'ast-advanced-hook-priority', true ) ?: 10 ),
				'display_rules'   => get_post_meta( $layout_post->ID, 'ast-advanced-hook-location', true ) ?: array(),
				'exclusion_rules' => get_post_meta( $layout_post->ID, 'ast-advanced-hook-exclusion', true ) ?: array(),
				'user_roles'      => get_post_meta( $layout_post->ID, 'ast-advanced-hook-users', true ) ?: array(),
				'devices'         => get_post_meta( $layout_post->ID, 'ast-advanced-display-device', true ) ?: array( 'desktop', 'tablet', 'mobile' ),
				'time_duration'   => get_post_meta( $layout_post->ID, 'ast-advanced-time-duration', true ) ?: array(),
				'modified'        => $layout_post->post_modified,
			);

			if ( 'template' === $result['layout_type'] ) {
				$result['template_type'] = get_post_meta( $layout_post->ID, 'ast-advanced-hook-template-type', true ) ?: '';
			}

			return $result;
		},
	));

	// ===== CREATE CUSTOM LAYOUT =====

	$reg->write( 'astra/create-custom-layout', array(
		'label'       => 'Create Custom Layout',
		'description' => 'Create a new Astra Custom Layout with full meta field support. Supports all 6 layout types: hooks, header, footer, content, template, 404.',
		'capability'  => 'edit_theme_options',
		'annotations' => array( 'idempotent' => false ),
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'title', 'layout_type' ),
			'properties' => array(
				'title'           => array( 'type' => 'string', 'description' => 'Title for the Custom Layout' ),
				'layout_type'     => array( 'type' => 'string', 'description' => 'Layout type: hooks, header, footer, content, template, 404' ),
				'content'         => array( 'type' => 'string', 'description' => 'Block markup content', 'default' => '' ),
				'editor_type'     => array( 'type' => 'string', 'description' => 'Editor type: "code_editor" for PHP, empty for block editor', 'default' => '' ),
				'php_code'        => array( 'type' => 'string', 'description' => 'PHP code (requires editor_type "code_editor")' ),
				'template_type'   => array( 'type' => 'string', 'description' => 'Template subtype: "single" or "archive" (for layout_type "template")', 'default' => 'single' ),
				'hook'            => array( 'type' => 'string', 'description' => 'Astra hook action (for layout_type "hooks")' ),
				'priority'        => array( 'type' => 'integer', 'description' => 'Hook priority (default: 10)', 'default' => 10 ),
				'display_rules'   => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'description' => 'Display condition rules' ),
				'exclusion_rules' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'description' => 'Exclusion rules' ),
				'user_roles'      => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'User role restrictions' ),
				'devices'         => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Device targeting' ),
				'status'          => array( 'type' => 'string', 'description' => 'Post status: publish or draft', 'default' => 'publish' ),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success'     => array( 'type' => 'boolean' ),
				'id'          => array( 'type' => 'integer' ),
				'title'       => array( 'type' => 'string' ),
				'layout_type' => array( 'type' => 'string' ),
				'status'      => array( 'type' => 'string' ),
				'meta_count'  => array( 'type' => 'integer' ),
			),
		),
		'callback' => function( $input ) {
			if ( ! astra_abilities_is_pro_active() ) {
				return new \WP_Error( 'pro_required', 'Astra Pro is not active. Custom Layouts require Astra Pro.' );
			}

			$layout_type = $input['layout_type'];
			$valid_types = array( 'hooks', 'header', 'footer', 'content', 'template', '404' );
			if ( ! in_array( $layout_type, $valid_types, true ) ) {
				return new \WP_Error( 'invalid_type', 'Invalid layout_type. Must be one of: ' . implode( ', ', $valid_types ) );
			}

			$status = $input['status'] ?? 'publish';
			if ( ! in_array( $status, array( 'publish', 'draft' ), true ) ) {
				$status = 'publish';
			}

			$post_id = wp_insert_post( array(
				'post_type'    => 'astra-advanced-hook',
				'post_title'   => sanitize_text_field( $input['title'] ),
				'post_content' => $input['content'] ?? '',
				'post_status'  => $status,
			), true );

			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}

			$meta       = astra_abilities_map_input_to_meta( $input, $layout_type, false );
			$meta_count = 0;
			foreach ( $meta as $key => $value ) {
				update_post_meta( $post_id, $key, $value );
				$meta_count++;
			}

			return array(
				'success'     => true,
				'id'          => $post_id,
				'title'       => $input['title'],
				'layout_type' => $layout_type,
				'status'      => $status,
				'meta_count'  => $meta_count,
			);
		},
	));

	// ===== UPDATE CUSTOM LAYOUT =====

	$reg->write( 'astra/update-custom-layout', array(
		'label'       => 'Update Custom Layout',
		'description' => 'Update an existing Astra Custom Layout. Only provided fields are modified.',
		'capability'  => 'edit_theme_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'              => array( 'type' => 'integer', 'description' => 'The Custom Layout post ID' ),
				'title'           => array( 'type' => 'string', 'description' => 'New title' ),
				'content'         => array( 'type' => 'string', 'description' => 'New block markup content' ),
				'layout_type'     => array( 'type' => 'string', 'description' => 'Layout type' ),
				'editor_type'     => array( 'type' => 'string', 'description' => 'Editor type' ),
				'php_code'        => array( 'type' => 'string', 'description' => 'PHP code' ),
				'template_type'   => array( 'type' => 'string', 'description' => 'Template subtype' ),
				'hook'            => array( 'type' => 'string', 'description' => 'Astra hook action' ),
				'priority'        => array( 'type' => 'integer', 'description' => 'Hook priority' ),
				'display_rules'   => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
				'exclusion_rules' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
				'user_roles'      => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'devices'         => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'status'          => array( 'type' => 'string', 'description' => 'Post status: publish or draft' ),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success'        => array( 'type' => 'boolean' ),
				'id'             => array( 'type' => 'integer' ),
				'fields_updated' => array( 'type' => 'integer' ),
			),
		),
		'callback' => function( $input ) {
			if ( ! astra_abilities_is_pro_active() ) {
				return new \WP_Error( 'pro_required', 'Astra Pro is not active. Custom Layouts require Astra Pro.' );
			}

			$post_id     = $input['id'];
			$layout_post = get_post( $post_id );
			if ( ! $layout_post || 'astra-advanced-hook' !== $layout_post->post_type ) {
				return new \WP_Error( 'not_found', 'Custom Layout not found: ' . $post_id );
			}

			$fields_updated = 0;
			$post_update    = array( 'ID' => $post_id );
			$needs_update   = false;

			if ( isset( $input['title'] ) ) {
				$post_update['post_title'] = sanitize_text_field( $input['title'] );
				$needs_update = true;
			}
			if ( isset( $input['content'] ) ) {
				$post_update['post_content'] = $input['content'];
				$needs_update = true;
			}
			if ( isset( $input['status'] ) && in_array( $input['status'], array( 'publish', 'draft' ), true ) ) {
				$post_update['post_status'] = $input['status'];
				$needs_update = true;
			}

			if ( $needs_update ) {
				$result = wp_update_post( $post_update, true );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				$fields_updated++;
			}

			$layout_type = $input['layout_type'] ?? get_post_meta( $post_id, 'ast-advanced-hook-layout', true );
			if ( empty( $layout_type ) ) {
				$layout_type = 'hooks';
			}

			$meta = astra_abilities_map_input_to_meta( $input, $layout_type, true );
			foreach ( $meta as $key => $value ) {
				update_post_meta( $post_id, $key, $value );
				$fields_updated++;
			}

			return array(
				'success'        => true,
				'id'             => $post_id,
				'fields_updated' => $fields_updated,
			);
		},
	));

	// ===== DELETE CUSTOM LAYOUT =====

	$reg->delete( 'astra/delete-custom-layout', array(
		'label'       => 'Delete Custom Layout',
		'description' => 'Delete an Astra Custom Layout. By default moves to trash; use force=true to permanently delete.',
		'capability'  => 'edit_theme_options',
		'annotations' => array( 'idempotent' => false ),
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'    => array( 'type' => 'integer', 'description' => 'The Custom Layout post ID' ),
				'force' => array( 'type' => 'boolean', 'description' => 'True to permanently delete (skip trash)', 'default' => false ),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success' => array( 'type' => 'boolean' ),
				'id'      => array( 'type' => 'integer' ),
				'action'  => array( 'type' => 'string' ),
			),
		),
		'callback' => function( $input ) {
			if ( ! astra_abilities_is_pro_active() ) {
				return new \WP_Error( 'pro_required', 'Astra Pro is not active. Custom Layouts require Astra Pro.' );
			}

			$post_id     = $input['id'];
			$layout_post = get_post( $post_id );
			if ( ! $layout_post || 'astra-advanced-hook' !== $layout_post->post_type ) {
				return new \WP_Error( 'not_found', 'Custom Layout not found: ' . $post_id );
			}

			$force  = $input['force'] ?? false;
			$result = $force ? wp_delete_post( $post_id, true ) : wp_trash_post( $post_id );

			if ( ! $result ) {
				return new \WP_Error( 'delete_failed', 'Failed to delete Custom Layout ' . $post_id );
			}

			return array(
				'success' => true,
				'id'      => $post_id,
				'action'  => $force ? 'permanently_deleted' : 'trashed',
			);
		},
	));

}, 100 );
