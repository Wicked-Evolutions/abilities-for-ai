<?php
/**
 * Spectra Suite — Block Operations Abilities
 *
 * Advanced block manipulation: attribute updates, reordering, and duplication.
 *
 * Abilities:
 *   - spectra/update-block-attrs (write) — P1
 *   - spectra/move-block         (write) — P1
 *   - spectra/duplicate-block    (write) — P2
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	if ( ! class_exists( 'UAGB_Loader' ) ) {
		return;
	}

	$reg = new Abilities_For_AI_Registrar( 'spectra', 'edit_posts' );

	// ===== UPDATE BLOCK ATTRIBUTES =====

	$reg->write( 'spectra/update-block-attrs', array(
		'label'        => 'Update Block Attributes',
		'description'  => 'Modify specific attributes of a block (e.g., change heading text, update colors) without replacing entire block markup. Only provided attributes are changed; others are preserved.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'post_id', 'block_id', 'attrs' ),
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'The WordPress post/page ID to modify',
				),
				'block_id' => array(
					'type'        => 'string',
					'description' => 'The Spectra block_id attribute of the block to update',
				),
				'attrs' => array(
					'type'                 => 'object',
					'description'          => 'Attributes to merge into the block. Existing keys are overwritten, new keys are added.',
					'additionalProperties' => true,
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success'       => array( 'type' => 'boolean' ),
				'post_id'       => array( 'type' => 'integer' ),
				'block_id'      => array( 'type' => 'string' ),
				'block_name'    => array( 'type' => 'string' ),
				'attrs_changed' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
		),
		'callback' => function( $input ) {
			$post = get_post( $input['post_id'] );
			if ( ! $post ) {
				return new WP_Error( 'not_found', 'Post not found.' );
			}

			$new_attrs = $input['attrs'];
			if ( empty( $new_attrs ) ) {
				return new WP_Error( 'empty_attrs', 'No attributes provided to update.' );
			}

			// Prevent overwriting block_id itself.
			unset( $new_attrs['block_id'] );

			$blocks = parse_blocks( $post->post_content );
			$result = spectra_abilities_update_block_attrs( $blocks, $input['block_id'], $new_attrs );

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

			// Find the updated block to report its name.
			$updated_block = spectra_abilities_find_block_recursive( $result['blocks'], $input['block_id'] );
			$block_name    = $updated_block ? $updated_block['blockName'] : 'unknown';

			return array(
				'success'       => true,
				'post_id'       => $post->ID,
				'block_id'      => $input['block_id'],
				'block_name'    => $block_name,
				'attrs_changed' => array_keys( $input['attrs'] ),
			);
		},
	));

	// ===== MOVE BLOCK =====

	$reg->write( 'spectra/move-block', array(
		'label'        => 'Move Block',
		'description'  => 'Move a block to a new position within the same post. The block is extracted from its current location and inserted at the target position (beginning, end, before, or after another block_id).',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'post_id', 'block_id', 'position' ),
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'The WordPress post/page ID to modify',
				),
				'block_id' => array(
					'type'        => 'string',
					'description' => 'The Spectra block_id of the block to move',
				),
				'position' => array(
					'type'        => 'string',
					'description' => 'Target position: beginning, end, before, or after a target block_id',
					'enum'        => array( 'beginning', 'end', 'before', 'after' ),
				),
				'target_block_id' => array(
					'type'        => 'string',
					'description' => 'The block_id to insert before/after. Required when position is "before" or "after".',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success'    => array( 'type' => 'boolean' ),
				'post_id'    => array( 'type' => 'integer' ),
				'block_id'   => array( 'type' => 'string' ),
				'block_name' => array( 'type' => 'string' ),
				'position'   => array( 'type' => 'string' ),
			),
		),
		'callback' => function( $input ) {
			$post = get_post( $input['post_id'] );
			if ( ! $post ) {
				return new WP_Error( 'not_found', 'Post not found.' );
			}

			$position        = $input['position'];
			$target_block_id = $input['target_block_id'] ?? '';

			if ( in_array( $position, array( 'before', 'after' ), true ) && empty( $target_block_id ) ) {
				return new WP_Error( 'missing_target', 'target_block_id is required when position is "before" or "after".' );
			}

			if ( $target_block_id === $input['block_id'] ) {
				return new WP_Error( 'self_reference', 'Cannot move a block before/after itself.' );
			}

			$blocks = parse_blocks( $post->post_content );

			// Step 1: Extract the block from its current position.
			$extract_result = spectra_abilities_extract_block( $blocks, $input['block_id'] );
			if ( null === $extract_result['extracted'] ) {
				return new WP_Error( 'block_not_found', 'No block found with block_id: ' . $input['block_id'] );
			}

			$moving_block   = $extract_result['extracted'];
			$remaining      = $extract_result['blocks'];

			// Filter out empty blocks from top level.
			$remaining = array_values( array_filter( $remaining, function( $b ) {
				return ! empty( $b['blockName'] );
			}));

			// Step 2: Insert at the target position.
			switch ( $position ) {
				case 'beginning':
					array_unshift( $remaining, $moving_block );
					break;
				case 'end':
					$remaining[] = $moving_block;
					break;
				case 'before':
				case 'after':
					$target_index = spectra_abilities_find_block_index( $remaining, $target_block_id );
					if ( false === $target_index ) {
						return new WP_Error( 'target_not_found', 'Target block_id not found: ' . $target_block_id );
					}
					$splice_pos = ( 'before' === $position ) ? $target_index : $target_index + 1;
					array_splice( $remaining, $splice_pos, 0, array( $moving_block ) );
					break;
			}

			$new_content = serialize_blocks( $remaining );
			$update      = wp_update_post( array(
				'ID'           => $post->ID,
				'post_content' => $new_content,
			));

			if ( is_wp_error( $update ) ) {
				return $update;
			}

			return array(
				'success'    => true,
				'post_id'    => $post->ID,
				'block_id'   => $input['block_id'],
				'block_name' => $moving_block['blockName'] ?? 'unknown',
				'position'   => $position,
			);
		},
	));

	// ===== DUPLICATE BLOCK =====

	$reg->write( 'spectra/duplicate-block', array(
		'label'        => 'Duplicate Block',
		'description'  => 'Clone a block (with new unique block_ids for it and all inner blocks) and insert the copy immediately after the original.',
		'annotations'  => array( 'idempotent' => false ),
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
					'description' => 'The Spectra block_id of the block to duplicate',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success'       => array( 'type' => 'boolean' ),
				'post_id'       => array( 'type' => 'integer' ),
				'original_id'   => array( 'type' => 'string' ),
				'new_block_id'  => array( 'type' => 'string' ),
				'block_name'    => array( 'type' => 'string' ),
				'total_blocks'  => array( 'type' => 'integer' ),
			),
		),
		'callback' => function( $input ) {
			$post = get_post( $input['post_id'] );
			if ( ! $post ) {
				return new WP_Error( 'not_found', 'Post not found.' );
			}

			$blocks  = parse_blocks( $post->post_content );
			$source  = spectra_abilities_find_block_recursive( $blocks, $input['block_id'] );

			if ( ! $source ) {
				return new WP_Error( 'block_not_found', 'No block found with block_id: ' . $input['block_id'] );
			}

			// Clone the block with fresh block_ids.
			$clone        = spectra_abilities_clone_block_ids( $source );
			$new_block_id = $clone['attrs']['block_id'] ?? 'unknown';

			// Insert clone immediately after the original at the top level.
			$top_index = spectra_abilities_find_block_index( $blocks, $input['block_id'] );

			if ( false !== $top_index ) {
				// Block is at top level — insert after it.
				array_splice( $blocks, $top_index + 1, 0, array( $clone ) );
			} else {
				// Block is nested — we need to find its parent and insert there.
				// Use the replace helper: replace original with [original, clone].
				$replaced = spectra_abilities_replace_block( $blocks, $input['block_id'], array( $source, $clone ) );
				if ( ! $replaced['found'] ) {
					return new WP_Error( 'insert_failed', 'Could not insert duplicate — block structure issue.' );
				}
				$blocks = $replaced['blocks'];
			}

			$new_content = serialize_blocks( $blocks );
			$update      = wp_update_post( array(
				'ID'           => $post->ID,
				'post_content' => $new_content,
			));

			if ( is_wp_error( $update ) ) {
				return $update;
			}

			$total = count( array_filter( $blocks, function( $b ) {
				return ! empty( $b['blockName'] );
			}));

			return array(
				'success'      => true,
				'post_id'      => $post->ID,
				'original_id'  => $input['block_id'],
				'new_block_id' => $new_block_id,
				'block_name'   => $source['blockName'] ?? 'unknown',
				'total_blocks' => $total,
			);
		},
	));

});
