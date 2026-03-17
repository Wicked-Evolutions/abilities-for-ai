<?php
/**
 * Block Editor Abilities
 *
 * Parse, serialize, list, find, insert, replace, and remove Gutenberg blocks.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {
	$reg = new Abilities_For_AI_Registrar( 'blocks', 'edit_posts' );

	// ===== BLOCKS — READ =====

	$reg->read( 'blocks/parse', array(
		'label'       => 'Parse Blocks',
		'description' => 'Parse post content into a structured block array. Provide either post_id (reads the post) or raw content string.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_id' => array( 'type' => 'integer', 'description' => 'Post ID to parse blocks from' ),
				'content' => array( 'type' => 'string', 'description' => 'Raw block markup string to parse (alternative to post_id)' ),
			),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'block_count' => array( 'type' => 'integer' ),
			'blocks'      => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $params ) {
			if ( ! empty( $params['post_id'] ) ) {
				$check = abilities_for_ai_require_editable_post( $params['post_id'] );
				if ( is_wp_error( $check ) ) return $check;
				$content = $check->post_content;
			} elseif ( isset( $params['content'] ) ) {
				$content = $params['content'];
			} else {
				return wp_abilities_error( 'ability_invalid_input', 'Provide post_id or content.' );
			}

			$blocks  = parse_blocks( $content );
			$cleaned = array();
			foreach ( $blocks as $index => $b ) {
				if ( ! empty( $b['blockName'] ) ) {
					$b['original_index'] = $index;
					$cleaned[] = $b;
				}
			}

			return array( 'block_count' => count( $cleaned ), 'blocks' => $cleaned );
		},
	));

	$reg->read( 'blocks/serialize', array(
		'label'       => 'Serialize Blocks',
		'description' => 'Convert a structured block array back to HTML comment markup.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'blocks' => array(
					'type'        => 'array',
					'description' => 'Array of block objects (same structure as parse output)',
					'items'       => array( 'type' => 'object' ),
				),
			),
			'required' => array( 'blocks' ),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'html'   => array( 'type' => 'string' ),
			'length' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $params ) {
			if ( empty( $params['blocks'] ) || ! is_array( $params['blocks'] ) ) {
				return wp_abilities_error( 'ability_invalid_input', 'Blocks array is required.' );
			}
			$html = serialize_blocks( $params['blocks'] );
			return array( 'html' => $html, 'length' => strlen( $html ) );
		},
	));

	$reg->read( 'blocks/list-types', array(
		'label'       => 'List Block Types',
		'description' => 'List all registered block types with their attributes, supports, and styles.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				array(
					'namespace' => array( 'type' => 'string', 'description' => 'Filter by namespace (e.g. "core", "uagb")' ),
					'search'    => abilities_for_ai_schema_search( 'Search block type names' ),
				),
				abilities_for_ai_schema_pagination()
			),
		),
		'output_schema' => abilities_for_ai_schema_list_output( 'types', array(
			'name'       => array( 'type' => 'string' ),
			'title'      => array( 'type' => 'string' ),
			'category'   => array( 'type' => 'string' ),
			'supports'   => array( 'type' => 'object' ),
			'attributes' => array( 'type' => 'object' ),
		) ),
		'callback' => function( $params ) {
			$registry = WP_Block_Type_Registry::get_instance();
			$all      = $registry->get_all_registered();
			$types    = array();

			foreach ( $all as $name => $type ) {
				if ( ! empty( $params['namespace'] ) ) {
					$ns = explode( '/', $name );
					if ( $ns[0] !== $params['namespace'] ) {
						continue;
					}
				}
				if ( ! empty( $params['search'] ) && stripos( $name, $params['search'] ) === false ) {
					continue;
				}
				$types[] = array(
					'name'       => $name,
					'title'      => $type->title ?? '',
					'category'   => $type->category ?? '',
					'parent'     => $type->parent ?? null,
					'supports'   => $type->supports ?? array(),
					'attributes' => $type->attributes ?? array(),
					'styles'     => $type->styles ?? array(),
				);
			}

			$pag   = wp_abilities_pagination( $params );
			$slice = array_slice( $types, $pag['offset'], $pag['per_page'] );

			return array(
				'total'    => count( $types ),
				'pages'    => max( 1, (int) ceil( count( $types ) / $pag['per_page'] ) ),
				'page'     => $pag['page'],
				'per_page' => $pag['per_page'],
				'types'    => $slice,
			);
		},
	));

	$reg->read( 'blocks/get-type', array(
		'label'       => 'Get Block Type',
		'description' => 'Get detailed information about a single registered block type.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'name' => array( 'type' => 'string', 'description' => 'Block type name (e.g. "core/paragraph", "uagb/container")' ),
			),
			'required' => array( 'name' ),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'name'        => array( 'type' => 'string' ),
			'title'       => array( 'type' => 'string' ),
			'description' => array( 'type' => 'string' ),
			'category'    => array( 'type' => 'string' ),
			'supports'    => array( 'type' => 'object' ),
			'attributes'  => array( 'type' => 'object' ),
			'api_version' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $params ) {
			$name = sanitize_text_field( $params['name'] ?? '' );
			if ( ! $name ) {
				return wp_abilities_error( 'ability_invalid_input', 'Block type name is required.' );
			}
			$registry = WP_Block_Type_Registry::get_instance();
			$type     = $registry->get_registered( $name );
			if ( ! $type ) {
				return wp_abilities_error( 'not_found', "Block type '{$name}' is not registered." );
			}
			return array(
				'name'             => $type->name,
				'title'            => $type->title ?? '',
				'description'      => $type->description ?? '',
				'category'         => $type->category ?? '',
				'parent'           => $type->parent ?? null,
				'ancestor'         => $type->ancestor ?? null,
				'allowed_blocks'   => $type->allowed_blocks ?? null,
				'icon'             => is_string( $type->icon ) ? $type->icon : null,
				'supports'         => $type->supports ?? array(),
				'attributes'       => $type->attributes ?? array(),
				'styles'           => $type->styles ?? array(),
				'example'          => $type->example ?? null,
				'provides_context' => $type->provides_context ?? array(),
				'uses_context'     => $type->uses_context ?? array(),
				'api_version'      => $type->api_version ?? 1,
			);
		},
	));

	$reg->read( 'blocks/find-in-post', array(
		'label'       => 'Find Block in Post',
		'description' => 'Find blocks by name or attribute value within a post. Returns matching blocks with their index positions. Set recursive=true to search nested blocks and return path arrays.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_id'         => array( 'type' => 'integer', 'description' => 'Post ID to search within' ),
				'block_name'      => array( 'type' => 'string', 'description' => 'Block type name to find (e.g. "core/paragraph")' ),
				'attribute_key'   => array( 'type' => 'string', 'description' => 'Attribute key to match' ),
				'attribute_value' => array( 'type' => 'string', 'description' => 'Attribute value to match' ),
				'class_name'      => array( 'type' => 'string', 'description' => 'Substring match on className attribute (recursive mode only)' ),
				'recursive'       => array( 'type' => 'boolean', 'description' => 'Search nested blocks and return path arrays instead of flat index (default: false)', 'default' => false ),
			),
			'required' => array( 'post_id' ),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'found'   => array( 'type' => 'integer' ),
			'matches' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $params ) {
			$check = abilities_for_ai_require_editable_post( $params['post_id'] ?? 0 );
			if ( is_wp_error( $check ) ) return $check;

			$blocks    = parse_blocks( $check->post_content );
			$recursive = ! empty( $params['recursive'] );

			if ( $recursive ) {
				$criteria = array();
				if ( ! empty( $params['block_name'] ) ) {
					$criteria['block_name'] = $params['block_name'];
				}
				if ( ! empty( $params['attribute_key'] ) ) {
					$criteria['attribute_key'] = $params['attribute_key'];
					if ( isset( $params['attribute_value'] ) ) {
						$criteria['attribute_value'] = $params['attribute_value'];
					}
				}
				if ( ! empty( $params['class_name'] ) ) {
					$criteria['class_name'] = $params['class_name'];
				}

				$results = abilities_for_ai_find_blocks_recursive( $blocks, $criteria );

				// Lightweight output — strip innerHTML/innerContent from results.
				$matches = array();
				foreach ( $results as $r ) {
					$matches[] = array(
						'path'      => $r['path'],
						'blockName' => $r['block']['blockName'],
						'attrs'     => $r['block']['attrs'] ?? array(),
					);
				}

				return array( 'found' => count( $matches ), 'matches' => $matches );
			}

			// Original flat search (backward compatible).
			$matches = array();
			$index   = 0;

			foreach ( $blocks as $block ) {
				if ( empty( $block['blockName'] ) ) {
					$index++;
					continue;
				}

				$match = true;

				if ( ! empty( $params['block_name'] ) && $block['blockName'] !== $params['block_name'] ) {
					$match = false;
				}

				if ( $match && ! empty( $params['attribute_key'] ) ) {
					$key = $params['attribute_key'];
					$val = $params['attribute_value'] ?? null;
					if ( ! isset( $block['attrs'][ $key ] ) ) {
						$match = false;
					} elseif ( $val !== null && (string) $block['attrs'][ $key ] !== (string) $val ) {
						$match = false;
					}
				}

				if ( $match ) {
					$matches[] = array( 'index' => $index, 'block' => $block );
				}
				$index++;
			}

			return array( 'found' => count( $matches ), 'matches' => $matches );
		},
	));

	// ===== BLOCKS — WRITE =====

	$reg->write( 'blocks/insert', array(
		'label'       => 'Insert Block',
		'description' => 'Insert one or more blocks at a position in a post. Position 0 = beginning, -1 = end.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_id'  => array( 'type' => 'integer', 'description' => 'Post ID to insert blocks into' ),
				'blocks'   => array( 'type' => 'array', 'description' => 'Block objects to insert', 'items' => array( 'type' => 'object' ) ),
				'position' => array( 'type' => 'integer', 'description' => 'Insert position (0 = beginning, -1 = end, N = after Nth block)', 'default' => -1 ),
			),
			'required' => array( 'post_id', 'blocks' ),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'post_id'      => array( 'type' => 'integer' ),
			'inserted'     => array( 'type' => 'integer' ),
			'total_blocks' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
		'callback' => function( $params ) {
			$check = abilities_for_ai_require_editable_post( $params['post_id'] ?? 0 );
			if ( is_wp_error( $check ) ) return $check;
			$post_id    = $check->ID;
			$existing   = parse_blocks( $check->post_content );
			$new_blocks = array_map( 'abilities_for_ai_normalize_block', $params['blocks'] );
			$position   = intval( $params['position'] ?? -1 );

			if ( $position === -1 || $position >= count( $existing ) ) {
				$existing = array_merge( $existing, $new_blocks );
			} elseif ( $position === 0 ) {
				$existing = array_merge( $new_blocks, $existing );
			} else {
				array_splice( $existing, $position, 0, $new_blocks );
			}

			$result = wp_update_post( array(
				'ID'           => $post_id,
				'post_content' => serialize_blocks( $existing ),
			), true );

			if ( is_wp_error( $result ) ) return $result;

			return array(
				'post_id'      => $post_id,
				'inserted'     => count( $new_blocks ),
				'total_blocks' => count( array_filter( $existing, function( $b ) { return ! empty( $b['blockName'] ); } ) ),
			);
		},
	));

	$reg->write( 'blocks/replace', array(
		'label'       => 'Replace Block',
		'description' => 'Replace a block at a specific index position in a post with a new block.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_id' => array( 'type' => 'integer', 'description' => 'Post ID containing the block' ),
				'index'   => array( 'type' => 'integer', 'description' => 'Block index position to replace (from parse or find output)' ),
				'block'   => array( 'type' => 'object', 'description' => 'New block object to replace with' ),
			),
			'required' => array( 'post_id', 'index', 'block' ),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'post_id'   => array( 'type' => 'integer' ),
			'replaced'  => array( 'type' => 'string' ),
			'new_block' => array( 'type' => 'string' ),
			'index'     => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $params ) {
			$check = abilities_for_ai_require_editable_post( $params['post_id'] ?? 0 );
			if ( is_wp_error( $check ) ) return $check;
			$post_id = $check->ID;
			$blocks  = parse_blocks( $check->post_content );
			$index   = intval( $params['index'] ?? -1 );

			if ( $index < 0 || $index >= count( $blocks ) ) {
				return wp_abilities_error( 'ability_invalid_input', "Block index {$index} is out of range (0-" . ( count( $blocks ) - 1 ) . ")." );
			}

			$old_name         = $blocks[ $index ]['blockName'] ?? '(empty)';
			$blocks[ $index ] = abilities_for_ai_normalize_block( $params['block'] );

			$result = wp_update_post( array(
				'ID'           => $post_id,
				'post_content' => serialize_blocks( $blocks ),
			), true );

			if ( is_wp_error( $result ) ) return $result;

			return array(
				'post_id'   => $post_id,
				'replaced'  => $old_name,
				'new_block' => $params['block']['blockName'] ?? 'unknown',
				'index'     => $index,
			);
		},
	));

	// ===== BLOCKS — DELETE =====

	// blocks/remove is free — round-trip: insert → test → remove the test.
	$reg->delete( 'blocks/remove', array(
		'tier'        => 'free',
		'label'       => 'Remove Block',
		'description' => 'Remove a block at a specific index position from a post.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_id' => array( 'type' => 'integer', 'description' => 'Post ID containing the block' ),
				'index'   => array( 'type' => 'integer', 'description' => 'Block index position to remove' ),
			),
			'required' => array( 'post_id', 'index' ),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'post_id'          => array( 'type' => 'integer' ),
			'removed'          => array( 'type' => 'string' ),
			'remaining_blocks' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ),
		'callback' => function( $params ) {
			$check = abilities_for_ai_require_editable_post( $params['post_id'] ?? 0 );
			if ( is_wp_error( $check ) ) return $check;
			$post_id = $check->ID;
			$blocks  = parse_blocks( $check->post_content );
			$index   = intval( $params['index'] ?? -1 );

			if ( $index < 0 || $index >= count( $blocks ) ) {
				return wp_abilities_error( 'ability_invalid_input', "Block index {$index} is out of range (0-" . ( count( $blocks ) - 1 ) . ")." );
			}

			$removed = $blocks[ $index ]['blockName'] ?? '(empty)';
			array_splice( $blocks, $index, 1 );

			$result = wp_update_post( array(
				'ID'           => $post_id,
				'post_content' => serialize_blocks( $blocks ),
			), true );

			if ( is_wp_error( $result ) ) return $result;

			return array(
				'post_id'          => $post_id,
				'removed'          => $removed,
				'remaining_blocks' => count( array_filter( $blocks, function( $b ) { return ! empty( $b['blockName'] ); } ) ),
			);
		},
	));
});
