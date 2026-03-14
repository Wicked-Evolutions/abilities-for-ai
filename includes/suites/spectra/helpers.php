<?php
/**
 * Spectra Suite — Shared Helper Functions
 *
 * Recursive helpers used across multiple ability files.
 * All functions are guarded with function_exists() to prevent fatal errors
 * if the standalone Abilities for Spectra plugin is still active during transition.
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'spectra_abilities_outline_blocks' ) ) {
	/**
	 * Build a lightweight outline of blocks (names, block_ids, classNames, depth).
	 *
	 * @param array $blocks    Parsed blocks from parse_blocks().
	 * @param int   $depth     Current nesting depth.
	 * @param int   $max_depth Maximum depth (-1 = unlimited, 0 = top-level only).
	 * @return array Outline entries.
	 */
	function spectra_abilities_outline_blocks( $blocks, $depth = 0, $max_depth = -1 ) {
		$result = array();

		foreach ( $blocks as $block ) {
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			$entry = array(
				'name'  => $block['blockName'],
				'depth' => $depth,
			);

			$attrs = $block['attrs'] ?? array();

			if ( ! empty( $attrs['block_id'] ) ) {
				$entry['block_id'] = $attrs['block_id'];
			}
			if ( ! empty( $attrs['className'] ) ) {
				$entry['className'] = $attrs['className'];
			}

			$result[] = $entry;

			if ( ! empty( $block['innerBlocks'] ) && ( -1 === $max_depth || $depth < $max_depth ) ) {
				$children = spectra_abilities_outline_blocks( $block['innerBlocks'], $depth + 1, $max_depth );
				$result   = array_merge( $result, $children );
			}
		}

		return $result;
	}
}

if ( ! function_exists( 'spectra_abilities_flatten_blocks' ) ) {
	/**
	 * Flatten a block tree into a single-level array with depth tracking.
	 *
	 * @param array $blocks Parsed blocks.
	 * @param int   $depth  Current depth.
	 * @return array Flat array of blocks with '_depth' added to each.
	 */
	function spectra_abilities_flatten_blocks( $blocks, $depth = 0 ) {
		$result = array();

		foreach ( $blocks as $block ) {
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			$block['_depth'] = $depth;
			$inner           = $block['innerBlocks'] ?? array();
			unset( $block['innerBlocks'], $block['innerContent'] );

			$result[] = $block;

			if ( ! empty( $inner ) ) {
				$result = array_merge( $result, spectra_abilities_flatten_blocks( $inner, $depth + 1 ) );
			}
		}

		return $result;
	}
}

if ( ! function_exists( 'spectra_abilities_find_block_recursive' ) ) {
	/**
	 * Find a block by block_id recursively through the entire tree.
	 *
	 * @param array  $blocks   Parsed blocks.
	 * @param string $block_id The block_id to search for.
	 * @return array|false The block array, or false if not found.
	 */
	function spectra_abilities_find_block_recursive( $blocks, $block_id ) {
		foreach ( $blocks as $block ) {
			if ( isset( $block['attrs']['block_id'] ) && $block['attrs']['block_id'] === $block_id ) {
				return $block;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = spectra_abilities_find_block_recursive( $block['innerBlocks'], $block_id );
				if ( false !== $found ) {
					return $found;
				}
			}
		}
		return false;
	}
}

if ( ! function_exists( 'spectra_abilities_count_blocks' ) ) {
	/**
	 * Count all blocks recursively (including inner blocks).
	 *
	 * @param array $blocks Parsed blocks.
	 * @return int Total block count.
	 */
	function spectra_abilities_count_blocks( $blocks ) {
		$count = 0;
		foreach ( $blocks as $block ) {
			if ( empty( $block['blockName'] ) ) {
				continue;
			}
			$count++;
			if ( ! empty( $block['innerBlocks'] ) ) {
				$count += spectra_abilities_count_blocks( $block['innerBlocks'] );
			}
		}
		return $count;
	}
}

if ( ! function_exists( 'spectra_abilities_simplify_blocks' ) ) {
	/**
	 * Simplify parsed blocks into a cleaner structure for AI consumption.
	 *
	 * @param array $blocks Parsed blocks from parse_blocks().
	 * @return array Simplified block array.
	 */
	function spectra_abilities_simplify_blocks( $blocks ) {
		$result = array();

		foreach ( $blocks as $block ) {
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			$simplified = array(
				'name'  => $block['blockName'],
				'attrs' => $block['attrs'] ?? array(),
			);

			if ( ! empty( $block['innerBlocks'] ) ) {
				$simplified['innerBlocks'] = spectra_abilities_simplify_blocks( $block['innerBlocks'] );
			}

			$result[] = $simplified;
		}

		return $result;
	}
}

if ( ! function_exists( 'spectra_abilities_find_block_index' ) ) {
	/**
	 * Find the index of a top-level block by its Spectra block_id attribute.
	 *
	 * @param array  $blocks   Top-level parsed blocks.
	 * @param string $block_id The block_id to search for.
	 * @return int|false The index, or false if not found.
	 */
	function spectra_abilities_find_block_index( $blocks, $block_id ) {
		foreach ( $blocks as $index => $block ) {
			if ( isset( $block['attrs']['block_id'] ) && $block['attrs']['block_id'] === $block_id ) {
				return $index;
			}
		}
		return false;
	}
}

if ( ! function_exists( 'spectra_abilities_replace_block' ) ) {
	/**
	 * Replace a block (by block_id) in the block tree with replacement blocks.
	 *
	 * @param array  $blocks      The block array to search.
	 * @param string $block_id    The block_id to find and replace.
	 * @param array  $replacement The replacement blocks.
	 * @return array { 'blocks' => array, 'found' => bool }
	 */
	function spectra_abilities_replace_block( $blocks, $block_id, $replacement ) {
		foreach ( $blocks as $index => $block ) {
			if ( isset( $block['attrs']['block_id'] ) && $block['attrs']['block_id'] === $block_id ) {
				array_splice( $blocks, $index, 1, $replacement );
				return array( 'blocks' => $blocks, 'found' => true );
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$inner_result = spectra_abilities_replace_block( $block['innerBlocks'], $block_id, $replacement );
				if ( $inner_result['found'] ) {
					$blocks[ $index ]['innerBlocks'] = $inner_result['blocks'];
					$blocks[ $index ]['innerContent'] = spectra_abilities_rebuild_inner_content( $blocks[ $index ] );
					return array( 'blocks' => $blocks, 'found' => true );
				}
			}
		}

		return array( 'blocks' => $blocks, 'found' => false );
	}
}

if ( ! function_exists( 'spectra_abilities_rebuild_inner_content' ) ) {
	/**
	 * Rebuild innerContent array after inner blocks have been modified.
	 *
	 * @param array $block The block with updated innerBlocks.
	 * @return array Rebuilt innerContent.
	 */
	function spectra_abilities_rebuild_inner_content( $block ) {
		$inner_content = array();
		foreach ( $block['innerBlocks'] as $i => $inner_block ) {
			if ( $i > 0 ) {
				$inner_content[] = "\n";
			}
			$inner_content[] = null;
		}
		return $inner_content;
	}
}

if ( ! function_exists( 'spectra_abilities_update_block_attrs' ) ) {
	/**
	 * Find a block by block_id and merge new attributes into it.
	 *
	 * @param array  $blocks    Parsed blocks.
	 * @param string $block_id  The block_id to find.
	 * @param array  $new_attrs Attributes to merge (existing keys overwritten, new keys added).
	 * @return array { 'blocks' => array, 'found' => bool, 'old_attrs' => array|null }
	 */
	function spectra_abilities_update_block_attrs( $blocks, $block_id, $new_attrs ) {
		foreach ( $blocks as $index => $block ) {
			if ( isset( $block['attrs']['block_id'] ) && $block['attrs']['block_id'] === $block_id ) {
				$old_attrs = $block['attrs'];
				$blocks[ $index ]['attrs'] = array_merge( $old_attrs, $new_attrs );

				// Re-serialize innerHTML to reflect attribute changes in the comment delimiter.
				$blocks[ $index ]['innerHTML'] = null;
				$blocks[ $index ]['innerContent'] = spectra_abilities_rebuild_block_content( $blocks[ $index ] );

				return array( 'blocks' => $blocks, 'found' => true, 'old_attrs' => $old_attrs );
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$inner_result = spectra_abilities_update_block_attrs( $block['innerBlocks'], $block_id, $new_attrs );
				if ( $inner_result['found'] ) {
					$blocks[ $index ]['innerBlocks'] = $inner_result['blocks'];
					$blocks[ $index ]['innerContent'] = spectra_abilities_rebuild_inner_content( $blocks[ $index ] );
					return array( 'blocks' => $blocks, 'found' => true, 'old_attrs' => $inner_result['old_attrs'] );
				}
			}
		}

		return array( 'blocks' => $blocks, 'found' => false, 'old_attrs' => null );
	}
}

if ( ! function_exists( 'spectra_abilities_rebuild_block_content' ) ) {
	/**
	 * Rebuild a single block's innerContent after attribute changes.
	 * For blocks with inner blocks, delegates to rebuild_inner_content.
	 * For leaf blocks, preserves existing innerContent (attributes live in the comment delimiter,
	 * which serialize_blocks() regenerates from the attrs array).
	 *
	 * @param array $block The block with updated attrs.
	 * @return array The innerContent array.
	 */
	function spectra_abilities_rebuild_block_content( $block ) {
		if ( ! empty( $block['innerBlocks'] ) ) {
			return spectra_abilities_rebuild_inner_content( $block );
		}
		return $block['innerContent'] ?? array();
	}
}

if ( ! function_exists( 'spectra_abilities_extract_block' ) ) {
	/**
	 * Extract (remove) a block by block_id from the tree, returning both
	 * the extracted block and the modified tree.
	 *
	 * @param array  $blocks   Parsed blocks.
	 * @param string $block_id The block_id to extract.
	 * @return array { 'blocks' => array, 'extracted' => array|null }
	 */
	function spectra_abilities_extract_block( $blocks, $block_id ) {
		foreach ( $blocks as $index => $block ) {
			if ( isset( $block['attrs']['block_id'] ) && $block['attrs']['block_id'] === $block_id ) {
				$extracted = $block;
				array_splice( $blocks, $index, 1 );
				return array( 'blocks' => $blocks, 'extracted' => $extracted );
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$inner_result = spectra_abilities_extract_block( $block['innerBlocks'], $block_id );
				if ( null !== $inner_result['extracted'] ) {
					$blocks[ $index ]['innerBlocks'] = $inner_result['blocks'];
					$blocks[ $index ]['innerContent'] = spectra_abilities_rebuild_inner_content( $blocks[ $index ] );
					return array( 'blocks' => $blocks, 'extracted' => $inner_result['extracted'] );
				}
			}
		}

		return array( 'blocks' => $blocks, 'extracted' => null );
	}
}

if ( ! function_exists( 'spectra_abilities_clone_block_ids' ) ) {
	/**
	 * Deep-clone a block, generating new unique block_ids for it and all inner blocks.
	 *
	 * @param array $block The block to clone.
	 * @return array The cloned block with fresh block_ids.
	 */
	function spectra_abilities_clone_block_ids( $block ) {
		if ( isset( $block['attrs']['block_id'] ) ) {
			$block['attrs']['block_id'] = substr( md5( uniqid( '', true ) ), 0, 8 );
		}

		if ( ! empty( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $i => $inner ) {
				$block['innerBlocks'][ $i ] = spectra_abilities_clone_block_ids( $inner );
			}
		}

		return $block;
	}
}
