<?php
/**
 * Spectra Suite — Reusable Block Abilities
 *
 * Discover and retrieve WordPress reusable blocks (synced patterns).
 *
 * Abilities:
 *   - spectra/list-reusable-blocks   (read)
 *   - spectra/get-reusable-block     (read)
 *   - spectra/create-reusable-block  (write)  — P1
 *   - spectra/update-reusable-block  (write)  — P1
 *   - spectra/delete-reusable-block  (delete) — P1
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	if ( ! class_exists( 'UAGB_Loader' ) ) {
		return;
	}

	$reg = new Abilities_For_AI_Registrar( 'spectra', 'edit_posts' );

	// ===== LIST REUSABLE BLOCKS =====

	$reg->read( 'spectra/list-reusable-blocks', array(
		'label'        => 'List Reusable Blocks',
		'description'  => 'List all reusable blocks (synced patterns) with titles, root block_ids, block counts, and usage counts.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'search' => array(
					'type'        => 'string',
					'description' => 'Optional search filter for block titles',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'count'  => array( 'type' => 'integer' ),
				'blocks' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			),
		),
		'callback' => function( $input ) {
			$input = (array) $input;
			global $wpdb;

			$args = array(
				'post_type'      => 'wp_block',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			);

			if ( ! empty( $input['search'] ) ) {
				$args['s'] = $input['search'];
			}

			$query  = new WP_Query( $args );
			$blocks = array();

			foreach ( $query->posts as $block_post ) {
				$parsed      = parse_blocks( $block_post->post_content );
				$block_count = spectra_abilities_count_blocks( $parsed );

				$root_ids = array();
				foreach ( $parsed as $b ) {
					if ( ! empty( $b['blockName'] ) && ! empty( $b['attrs']['block_id'] ) ) {
						$root_ids[] = $b['attrs']['block_id'];
					}
				}

				$ref_pattern = '%<!-- wp:block {"ref":' . $block_post->ID . '} /-->%';
				$usage_count = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE %s AND post_status IN ('publish','draft') AND post_type NOT IN ('revision')",
					$ref_pattern
				) );

				$blocks[] = array(
					'id'          => $block_post->ID,
					'title'       => $block_post->post_title,
					'modified'    => $block_post->post_modified,
					'block_count' => $block_count,
					'root_ids'    => $root_ids,
					'usage_count' => $usage_count,
				);
			}

			return array(
				'count'  => count( $blocks ),
				'blocks' => $blocks,
			);
		},
	));

	// ===== GET REUSABLE BLOCK =====

	$reg->read( 'spectra/get-reusable-block', array(
		'label'        => 'Get Reusable Block',
		'description'  => 'Retrieve a reusable block by ID. "ref" mode returns synced reference markup. "expanded" mode returns full block content and structure.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'The reusable block post ID',
				),
				'mode' => array(
					'type'        => 'string',
					'description' => 'Retrieval mode: "ref" for synced reference, "expanded" for full content',
					'default'     => 'ref',
					'enum'        => array( 'ref', 'expanded' ),
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id'     => array( 'type' => 'integer' ),
				'title'  => array( 'type' => 'string' ),
				'mode'   => array( 'type' => 'string' ),
				'markup' => array( 'type' => 'string' ),
				'blocks' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			),
		),
		'callback' => function( $input ) {
			$input = (array) $input;
			$block_post = get_post( $input['id'] );
			if ( ! $block_post || 'wp_block' !== $block_post->post_type ) {
				return new WP_Error( 'not_found', 'Reusable block not found: ' . $input['id'] );
			}

			$mode = $input['mode'] ?? 'ref';

			if ( 'ref' === $mode ) {
				return array(
					'id'     => $block_post->ID,
					'title'  => $block_post->post_title,
					'mode'   => 'ref',
					'markup' => '<!-- wp:block {"ref":' . $block_post->ID . '} /-->',
				);
			}

			$parsed     = parse_blocks( $block_post->post_content );
			$simplified = spectra_abilities_simplify_blocks( $parsed );

			return array(
				'id'     => $block_post->ID,
				'title'  => $block_post->post_title,
				'mode'   => 'expanded',
				'markup' => $block_post->post_content,
				'blocks' => $simplified,
			);
		},
	));

	// ===== CREATE REUSABLE BLOCK =====

	$reg->write( 'spectra/create-reusable-block', array(
		'label'        => 'Create Reusable Block',
		'description'  => 'Save block markup as a new reusable block (synced pattern). The content becomes available in the block inserter.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'title', 'content' ),
			'properties' => array(
				'title' => array(
					'type'        => 'string',
					'description' => 'Name for the reusable block',
				),
				'content' => array(
					'type'        => 'string',
					'description' => 'Gutenberg block markup to save as the reusable block content',
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'Post status (default: publish)',
					'default'     => 'publish',
					'enum'        => array( 'publish', 'draft' ),
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success'     => array( 'type' => 'boolean' ),
				'id'          => array( 'type' => 'integer' ),
				'title'       => array( 'type' => 'string' ),
				'block_count' => array( 'type' => 'integer' ),
				'ref_markup'  => array( 'type' => 'string' ),
			),
		),
		'callback' => function( $input ) {
			$input = (array) $input;
			$parsed = parse_blocks( $input['content'] );
			$named  = array_filter( $parsed, function( $b ) {
				return ! empty( $b['blockName'] );
			});

			if ( empty( $named ) ) {
				return new WP_Error( 'empty_content', 'No valid blocks found in the provided content.' );
			}

			$post_id = wp_insert_post( array(
				'post_title'   => sanitize_text_field( $input['title'] ),
				'post_content' => $input['content'],
				'post_type'    => 'wp_block',
				'post_status'  => $input['status'] ?? 'publish',
			));

			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}

			return array(
				'success'     => true,
				'id'          => $post_id,
				'title'       => $input['title'],
				'block_count' => count( $named ),
				'ref_markup'  => '<!-- wp:block {"ref":' . $post_id . '} /-->',
			);
		},
	));

	// ===== UPDATE REUSABLE BLOCK =====

	$reg->write( 'spectra/update-reusable-block', array(
		'label'        => 'Update Reusable Block',
		'description'  => 'Modify an existing reusable block\'s title and/or content. Only provided fields are changed.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'The reusable block post ID',
				),
				'title' => array(
					'type'        => 'string',
					'description' => 'New title (omit to keep current)',
				),
				'content' => array(
					'type'        => 'string',
					'description' => 'New block markup content (omit to keep current)',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success' => array( 'type' => 'boolean' ),
				'id'      => array( 'type' => 'integer' ),
				'title'   => array( 'type' => 'string' ),
				'updated' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
		),
		'callback' => function( $input ) {
			$input = (array) $input;
			$block_post = get_post( $input['id'] );
			if ( ! $block_post || 'wp_block' !== $block_post->post_type ) {
				return new WP_Error( 'not_found', 'Reusable block not found: ' . $input['id'] );
			}

			$update_args = array( 'ID' => $block_post->ID );
			$updated     = array();

			if ( isset( $input['title'] ) ) {
				$update_args['post_title'] = sanitize_text_field( $input['title'] );
				$updated[] = 'title';
			}

			if ( isset( $input['content'] ) ) {
				$parsed = parse_blocks( $input['content'] );
				$named  = array_filter( $parsed, function( $b ) {
					return ! empty( $b['blockName'] );
				});

				if ( empty( $named ) ) {
					return new WP_Error( 'empty_content', 'No valid blocks found in the provided content.' );
				}

				$update_args['post_content'] = $input['content'];
				$updated[] = 'content';
			}

			if ( empty( $updated ) ) {
				return new WP_Error( 'nothing_to_update', 'Provide at least one of: title, content.' );
			}

			$result = wp_update_post( $update_args );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$refreshed = get_post( $block_post->ID );

			return array(
				'success' => true,
				'id'      => $block_post->ID,
				'title'   => $refreshed->post_title,
				'updated' => $updated,
			);
		},
	));

	// ===== DELETE REUSABLE BLOCK =====

	$reg->delete( 'spectra/delete-reusable-block', array(
		'label'        => 'Delete Reusable Block',
		'description'  => 'Delete a reusable block (synced pattern). Reports how many posts reference it. Use force=true to delete even if referenced.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'The reusable block post ID to delete',
				),
				'force' => array(
					'type'        => 'boolean',
					'description' => 'Delete even if other posts reference this block (default: false)',
					'default'     => false,
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success'     => array( 'type' => 'boolean' ),
				'id'          => array( 'type' => 'integer' ),
				'title'       => array( 'type' => 'string' ),
				'usage_count' => array( 'type' => 'integer' ),
			),
		),
		'callback' => function( $input ) {
			$input = (array) $input;
			global $wpdb;

			$block_post = get_post( $input['id'] );
			if ( ! $block_post || 'wp_block' !== $block_post->post_type ) {
				return new WP_Error( 'not_found', 'Reusable block not found: ' . $input['id'] );
			}

			// Check usage count.
			$ref_pattern = '%<!-- wp:block {"ref":' . $block_post->ID . '} /-->%';
			$usage_count = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE %s AND post_status IN ('publish','draft') AND post_type NOT IN ('revision')",
				$ref_pattern
			) );

			$force = $input['force'] ?? false;

			if ( $usage_count > 0 && ! $force ) {
				return new WP_Error(
					'in_use',
					'Reusable block "' . $block_post->post_title . '" is referenced by ' . $usage_count . ' post(s). Use force=true to delete anyway.',
					array( 'usage_count' => $usage_count )
				);
			}

			$title  = $block_post->post_title;
			$result = wp_delete_post( $block_post->ID, true );

			if ( ! $result ) {
				return new WP_Error( 'delete_failed', 'Failed to delete reusable block.' );
			}

			return array(
				'success'     => true,
				'id'          => $input['id'],
				'title'       => $title,
				'usage_count' => $usage_count,
			);
		},
	));

});
