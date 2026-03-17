<?php
/**
 * Block Helper Functions
 *
 * Infrastructure for nested block addressing and innerContent normalization.
 * Used by blocks-abilities.php — not registered as abilities themselves.
 *
 * @package Abilities_For_AI
 * @since   1.5.0
 * @see     https://github.com/Wicked-Evolutions/abilities-for-ai/issues/54
 */

defined( 'ABSPATH' ) || exit;

/**
 * Normalize a block's innerContent to match its innerBlocks.
 *
 * WordPress's serialize_block() requires innerContent to contain null markers
 * for each innerBlock. Blocks from JSON API input have innerBlocks but no
 * innerContent, causing serialize_block() to silently drop inner blocks.
 *
 * Rules:
 * - If innerContent already has the correct number of nulls → leave it alone.
 * - If block has no innerBlocks → leave innerContent alone.
 * - Self-closing blocks (no innerBlocks, no innerHTML) → innerContent = [].
 * - Recurse into each innerBlock.
 *
 * @param array $block A parsed block array.
 * @return array The block with normalized innerContent.
 */
function abilities_for_ai_normalize_block( $block ) {
	// Ensure innerBlocks exists as an array.
	if ( ! isset( $block['innerBlocks'] ) || ! is_array( $block['innerBlocks'] ) ) {
		$block['innerBlocks'] = array();
	}

	// Recurse into innerBlocks first.
	$block['innerBlocks'] = array_map( 'abilities_for_ai_normalize_block', $block['innerBlocks'] );

	$inner_count = count( $block['innerBlocks'] );

	if ( $inner_count === 0 ) {
		// No inner blocks — leave innerContent as-is if it exists,
		// or set to empty array for self-closing blocks.
		if ( ! isset( $block['innerContent'] ) ) {
			$block['innerContent'] = array();
		}
		return $block;
	}

	// Count existing null markers in innerContent.
	$existing_nulls = 0;
	if ( ! empty( $block['innerContent'] ) && is_array( $block['innerContent'] ) ) {
		foreach ( $block['innerContent'] as $piece ) {
			if ( null === $piece ) {
				$existing_nulls++;
			}
		}
	}

	// If innerContent already has the right number of nulls, it's valid.
	if ( $existing_nulls === $inner_count ) {
		return $block;
	}

	// Reconstruct innerContent: null for each innerBlock with "\n" between.
	$inner_content = array();
	for ( $i = 0; $i < $inner_count; $i++ ) {
		$inner_content[] = null;
		if ( $i < $inner_count - 1 ) {
			$inner_content[] = "\n";
		}
	}
	$block['innerContent'] = $inner_content;

	return $block;
}

/**
 * Get a block at a specific path in a parsed block tree.
 *
 * Path indices refer to named blocks only (blocks with a non-empty blockName),
 * consistent with how blocks/parse returns original_index.
 *
 * @param array $blocks Parsed block array (from parse_blocks()).
 * @param array $path   Integer path array, e.g. [0, 2, 1].
 * @return array|WP_Error The block at the path, or WP_Error if path is invalid.
 */
function abilities_for_ai_get_block_at_path( $blocks, $path ) {
	if ( ! is_array( $path ) || empty( $path ) ) {
		return new WP_Error( 'invalid_path', 'Path must be a non-empty array of integers.' );
	}

	$current_blocks = $blocks;

	for ( $depth = 0; $depth < count( $path ); $depth++ ) {
		$target_index = (int) $path[ $depth ];

		// Filter to named blocks only.
		$named = array();
		foreach ( $current_blocks as $raw_index => $b ) {
			if ( ! empty( $b['blockName'] ) ) {
				$named[] = array( 'raw_index' => $raw_index, 'block' => $b );
			}
		}

		if ( $target_index < 0 || $target_index >= count( $named ) ) {
			$max = count( $named ) - 1;
			return new WP_Error(
				'invalid_path',
				sprintf(
					'Path index %d is out of range at depth %d (valid: 0–%d, %d named block%s).',
					$target_index,
					$depth,
					max( 0, $max ),
					count( $named ),
					count( $named ) === 1 ? '' : 's'
				)
			);
		}

		$block = $named[ $target_index ]['block'];

		// If this is the last segment, return the block.
		if ( $depth === count( $path ) - 1 ) {
			return $block;
		}

		// Descend into innerBlocks for next segment.
		if ( empty( $block['innerBlocks'] ) || ! is_array( $block['innerBlocks'] ) ) {
			return new WP_Error(
				'invalid_path',
				sprintf(
					'Block at depth %d (%s) has no innerBlocks — cannot descend to depth %d.',
					$depth,
					$block['blockName'],
					$depth + 1
				)
			);
		}

		$current_blocks = $block['innerBlocks'];
	}

	// Should not reach here, but safety net.
	return new WP_Error( 'invalid_path', 'Could not resolve path.' );
}

/**
 * Replace a block at a specific path and rebuild ancestor innerContent.
 *
 * @param array $blocks      Parsed block array (from parse_blocks()).
 * @param array $path        Integer path array, e.g. [0, 2, 1].
 * @param array $replacement The replacement block.
 * @return array|WP_Error The modified blocks array, or WP_Error if path is invalid.
 */
function abilities_for_ai_set_block_at_path( $blocks, $path, $replacement ) {
	if ( ! is_array( $path ) || empty( $path ) ) {
		return new WP_Error( 'invalid_path', 'Path must be a non-empty array of integers.' );
	}

	// Resolve the raw index at each depth level, then set from the bottom up.
	// We build a stack of (parent_blocks_ref, raw_index) to walk back up.
	return _abilities_for_ai_set_recursive( $blocks, $path, 0, $replacement );
}

/**
 * Internal recursive setter for set_block_at_path.
 *
 * @param array $blocks      Current level blocks.
 * @param array $path        Full path array.
 * @param int   $depth       Current depth in the path.
 * @param array $replacement Replacement block.
 * @return array|WP_Error Modified blocks array at this level.
 */
function _abilities_for_ai_set_recursive( $blocks, $path, $depth, $replacement ) {
	$target_index = (int) $path[ $depth ];

	// Filter to named blocks and find the raw index.
	$named_count = 0;
	$raw_index   = null;
	foreach ( $blocks as $i => $b ) {
		if ( ! empty( $b['blockName'] ) ) {
			if ( $named_count === $target_index ) {
				$raw_index = $i;
				break;
			}
			$named_count++;
		}
	}

	if ( $raw_index === null ) {
		$max = $named_count - 1;
		return new WP_Error(
			'invalid_path',
			sprintf(
				'Path index %d is out of range at depth %d (valid: 0–%d).',
				$target_index,
				$depth,
				max( 0, $max )
			)
		);
	}

	// Last segment — do the replacement.
	if ( $depth === count( $path ) - 1 ) {
		$blocks[ $raw_index ] = abilities_for_ai_normalize_block( $replacement );
		return $blocks;
	}

	// Not the last segment — descend into innerBlocks.
	$block = $blocks[ $raw_index ];
	if ( empty( $block['innerBlocks'] ) || ! is_array( $block['innerBlocks'] ) ) {
		return new WP_Error(
			'invalid_path',
			sprintf(
				'Block at depth %d (%s) has no innerBlocks — cannot descend.',
				$depth,
				$block['blockName']
			)
		);
	}

	$result = _abilities_for_ai_set_recursive( $block['innerBlocks'], $path, $depth + 1, $replacement );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$block['innerBlocks'] = $result;

	// Rebuild innerContent for this ancestor after child modification.
	$block = abilities_for_ai_normalize_block( $block );

	$blocks[ $raw_index ] = $block;
	return $blocks;
}

/**
 * Find blocks recursively by criteria, returning path arrays.
 *
 * @param array $blocks       Parsed block array.
 * @param array $criteria     Search criteria:
 *                            - block_name: exact match on blockName
 *                            - attribute_key + optional attribute_value: attribute match
 *                            - class_name: substring match on className attribute
 * @param array $current_path Current path prefix (for recursion).
 * @return array Array of [ 'path' => [...], 'block' => {...} ] matches.
 */
function abilities_for_ai_find_blocks_recursive( $blocks, $criteria, $current_path = array() ) {
	$results     = array();
	$named_index = 0;

	foreach ( $blocks as $b ) {
		if ( empty( $b['blockName'] ) ) {
			continue;
		}

		$path  = array_merge( $current_path, array( $named_index ) );
		$match = true;

		// Criteria: block_name.
		if ( ! empty( $criteria['block_name'] ) && $b['blockName'] !== $criteria['block_name'] ) {
			$match = false;
		}

		// Criteria: attribute_key (+ optional attribute_value).
		if ( $match && ! empty( $criteria['attribute_key'] ) ) {
			$key = $criteria['attribute_key'];
			$val = $criteria['attribute_value'] ?? null;
			if ( ! isset( $b['attrs'][ $key ] ) ) {
				$match = false;
			} elseif ( $val !== null && (string) $b['attrs'][ $key ] !== (string) $val ) {
				$match = false;
			}
		}

		// Criteria: class_name (substring match on className attribute).
		if ( $match && ! empty( $criteria['class_name'] ) ) {
			$class = $b['attrs']['className'] ?? '';
			if ( strpos( $class, $criteria['class_name'] ) === false ) {
				$match = false;
			}
		}

		if ( $match ) {
			$results[] = array( 'path' => $path, 'block' => $b );
		}

		// Recurse into innerBlocks.
		if ( ! empty( $b['innerBlocks'] ) && is_array( $b['innerBlocks'] ) ) {
			$child_results = abilities_for_ai_find_blocks_recursive( $b['innerBlocks'], $criteria, $path );
			$results       = array_merge( $results, $child_results );
		}

		$named_index++;
	}

	return $results;
}
