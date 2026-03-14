<?php
/**
 * Spectra Suite — Block Parser Abilities
 *
 * Parse, insert, update, and delete blocks within WordPress post content.
 *
 * Abilities:
 *   - spectra/get-page-blocks  (read)
 *   - spectra/insert-blocks    (write)
 *   - spectra/update-section   (write)
 *   - spectra/delete-block     (delete)
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	if ( ! class_exists( 'UAGB_Loader' ) ) {
		return;
	}

	$reg = new Abilities_For_AI_Registrar( 'spectra', 'edit_posts' );

	// ===== GET PAGE BLOCKS =====

	$reg->read( 'spectra/get-page-blocks', array(
		'label'        => 'Get Page Blocks',
		'description'  => 'Parse a page into structured block JSON. Returns block names, attributes (including block_id), and inner blocks recursively.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'post_id' ),
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'The WordPress post/page ID to parse',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_id'     => array( 'type' => 'integer' ),
				'title'       => array( 'type' => 'string' ),
				'block_count' => array( 'type' => 'integer' ),
				'blocks'      => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			),
		),
		'callback' => function( $input ) {
			$post = get_post( $input['post_id'] );
			if ( ! $post ) {
				return new WP_Error( 'not_found', 'Post not found.' );
			}

			$parsed = parse_blocks( $post->post_content );
			$blocks = spectra_abilities_simplify_blocks( $parsed );

			return array(
				'post_id'     => $post->ID,
				'title'       => $post->post_title,
				'block_count' => count( $blocks ),
				'blocks'      => $blocks,
			);
		},
	));

	// ===== INSERT BLOCKS =====

	$reg->write( 'spectra/insert-blocks', array(
		'label'        => 'Insert Blocks',
		'description'  => 'Insert Gutenberg block markup into a page at a specific position (beginning, end, or before/after a block_id).',
		'annotations'  => array( 'idempotent' => false ),
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'post_id', 'markup' ),
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'The WordPress post/page ID to modify',
				),
				'markup' => array(
					'type'        => 'string',
					'description' => 'The Gutenberg block markup to insert',
				),
				'position' => array(
					'type'        => 'string',
					'description' => 'Where to insert: beginning, end, before, or after a target block_id',
					'default'     => 'end',
					'enum'        => array( 'beginning', 'end', 'before', 'after' ),
				),
				'target_block_id' => array(
					'type'        => 'string',
					'description' => 'The Spectra block_id to insert before/after. Required when position is "before" or "after".',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success'         => array( 'type' => 'boolean' ),
				'post_id'         => array( 'type' => 'integer' ),
				'blocks_inserted' => array( 'type' => 'integer' ),
				'total_blocks'    => array( 'type' => 'integer' ),
			),
		),
		'callback' => function( $input ) {
			$post = get_post( $input['post_id'] );
			if ( ! $post ) {
				return new WP_Error( 'not_found', 'Post not found.' );
			}

			$position        = $input['position'] ?? 'end';
			$target_block_id = $input['target_block_id'] ?? '';

			if ( in_array( $position, array( 'before', 'after' ), true ) && empty( $target_block_id ) ) {
				return new WP_Error( 'missing_target', 'target_block_id is required when position is "before" or "after".' );
			}

			$existing_blocks = parse_blocks( $post->post_content );
			$new_blocks      = parse_blocks( $input['markup'] );

			$new_blocks = array_values( array_filter( $new_blocks, function( $block ) {
				return ! empty( $block['blockName'] );
			}));

			if ( empty( $new_blocks ) ) {
				return new WP_Error( 'empty_markup', 'No valid blocks found in the provided markup.' );
			}

			$existing_blocks = array_values( array_filter( $existing_blocks, function( $block ) {
				return ! empty( $block['blockName'] );
			}));

			switch ( $position ) {
				case 'beginning':
					$result_blocks = array_merge( $new_blocks, $existing_blocks );
					break;
				case 'end':
					$result_blocks = array_merge( $existing_blocks, $new_blocks );
					break;
				case 'before':
				case 'after':
					$index = spectra_abilities_find_block_index( $existing_blocks, $target_block_id );
					if ( false === $index ) {
						return new WP_Error( 'block_not_found', 'No block found with block_id: ' . $target_block_id );
					}
					$splice_pos    = ( 'before' === $position ) ? $index : $index + 1;
					$result_blocks = $existing_blocks;
					array_splice( $result_blocks, $splice_pos, 0, $new_blocks );
					break;
			}

			$new_content = serialize_blocks( $result_blocks );
			$update      = wp_update_post( array(
				'ID'           => $post->ID,
				'post_content' => $new_content,
			));

			if ( is_wp_error( $update ) ) {
				return $update;
			}

			return array(
				'success'         => true,
				'post_id'         => $post->ID,
				'blocks_inserted' => count( $new_blocks ),
				'total_blocks'    => count( $result_blocks ),
			);
		},
	));

	// ===== UPDATE SECTION =====

	$reg->write( 'spectra/update-section', array(
		'label'        => 'Update Section',
		'description'  => 'Replace a specific section (identified by Spectra block_id attribute) with new block markup.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'post_id', 'block_id', 'markup' ),
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'The WordPress post/page ID to modify',
				),
				'block_id' => array(
					'type'        => 'string',
					'description' => 'The Spectra block_id attribute of the block to replace (e.g., "pr-hero")',
				),
				'markup' => array(
					'type'        => 'string',
					'description' => 'The replacement Gutenberg block markup',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success'           => array( 'type' => 'boolean' ),
				'post_id'           => array( 'type' => 'integer' ),
				'replaced_block_id' => array( 'type' => 'string' ),
			),
		),
		'callback' => function( $input ) {
			$post = get_post( $input['post_id'] );
			if ( ! $post ) {
				return new WP_Error( 'not_found', 'Post not found.' );
			}

			$existing_blocks = parse_blocks( $post->post_content );
			$replacement     = parse_blocks( $input['markup'] );

			$replacement = array_values( array_filter( $replacement, function( $block ) {
				return ! empty( $block['blockName'] );
			}));

			if ( empty( $replacement ) ) {
				return new WP_Error( 'empty_markup', 'No valid blocks found in the replacement markup.' );
			}

			$replaced = spectra_abilities_replace_block( $existing_blocks, $input['block_id'], $replacement );

			if ( ! $replaced['found'] ) {
				return new WP_Error( 'block_not_found', 'No block found with block_id: ' . $input['block_id'] );
			}

			$new_content = serialize_blocks( $replaced['blocks'] );
			$update      = wp_update_post( array(
				'ID'           => $post->ID,
				'post_content' => $new_content,
			));

			if ( is_wp_error( $update ) ) {
				return $update;
			}

			return array(
				'success'           => true,
				'post_id'           => $post->ID,
				'replaced_block_id' => $input['block_id'],
			);
		},
	));

	// ===== DELETE BLOCK =====

	$reg->delete( 'spectra/delete-block', array(
		'label'        => 'Delete Block',
		'description'  => 'Delete a specific block from a post by its Spectra block_id. Removes the block and re-serializes the remaining content.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'post_id', 'block_id' ),
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'The WordPress post/page ID to modify',
				),
				'block_id' => array(
					'type'        => 'string',
					'description' => 'The Spectra block_id attribute of the block to delete',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success'          => array( 'type' => 'boolean' ),
				'post_id'          => array( 'type' => 'integer' ),
				'deleted_block_id' => array( 'type' => 'string' ),
				'remaining_blocks' => array( 'type' => 'integer' ),
			),
		),
		'callback' => function( $input ) {
			$post = get_post( $input['post_id'] );
			if ( ! $post ) {
				return new WP_Error( 'not_found', 'Post not found.' );
			}

			$blocks = parse_blocks( $post->post_content );

			// Replace the target block with nothing (empty array = deletion).
			$result = spectra_abilities_replace_block( $blocks, $input['block_id'], array() );

			if ( ! $result['found'] ) {
				return new WP_Error( 'block_not_found', 'No block found with block_id: ' . $input['block_id'] );
			}

			$new_content = serialize_blocks( $result['blocks'] );
			$update      = wp_update_post( array(
				'ID'           => $post->ID,
				'post_content' => $new_content,
			));

			if ( is_wp_error( $update ) ) {
				return $update;
			}

			// Count remaining named blocks.
			$remaining = count( array_filter( $result['blocks'], function( $b ) {
				return ! empty( $b['blockName'] );
			}));

			return array(
				'success'          => true,
				'post_id'          => $post->ID,
				'deleted_block_id' => $input['block_id'],
				'remaining_blocks' => $remaining,
			);
		},
	));

});
