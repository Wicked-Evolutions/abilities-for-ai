<?php
/**
 * Block Editor Abilities
 *
 * Parse, serialize, list, find, insert, replace, and remove Gutenberg blocks.
 *
 * @package WordPress_Native_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', 'wp_native_register_blocks_abilities' );

function wp_native_register_blocks_abilities() {

	$perms = wp_abilities_suite_get_permissions( 'blocks' );

	// ===== BLOCKS — READ =====
	if ( $perms['read'] ) {

	// ---- blocks/parse ----
	wp_register_ability( 'blocks/parse', array(
		'label'       => 'Parse Blocks',
		'description' => 'Parse post content into a structured block array. Provide either post_id (reads the post) or raw content string.',
		'category'    => 'blocks',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'Post ID to parse blocks from',
				),
				'content' => array(
					'type'        => 'string',
					'description' => 'Raw block markup string to parse (alternative to post_id)',
				),
			),
		),
		'execute_callback' => function( $params ) {
			if ( ! empty( $params['post_id'] ) ) {
				$check = wp_abilities_suite_require_editable_post( $params['post_id'] );
				if ( is_wp_error( $check ) ) return $check;
				$content = $check->post_content;
			} elseif ( isset( $params['content'] ) ) {
				$content = $params['content'];
			} else {
				return wp_native_error( 'missing_input', 'Provide post_id or content.' );
			}

			$blocks = parse_blocks( $content );
			$cleaned = array();
			foreach ( $blocks as $index => $b ) {
				if ( ! empty( $b['blockName'] ) ) {
					$b['original_index'] = $index;
					$cleaned[] = $b;
				}
			}

			return array(
				'block_count' => count( $cleaned ),
				'blocks'      => $cleaned,
			);
		},
		'permission_callback' => function() {
			return current_user_can( 'edit_posts' );
		},
		'meta' => array(
			'show_in_rest' => true,
			'mcp'          => array( 'public' => true, 'type' => 'tool' ),
			'annotations'  => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			),
		
		'tier' => 'free',),
	));

	// ---- blocks/serialize ----
	wp_register_ability( 'blocks/serialize', array(
		'label'       => 'Serialize Blocks',
		'description' => 'Convert a structured block array back to HTML comment markup.',
		'category'    => 'blocks',
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
		'execute_callback' => function( $params ) {
			if ( empty( $params['blocks'] ) || ! is_array( $params['blocks'] ) ) {
				return wp_native_error( 'invalid_blocks', 'Blocks array is required.' );
			}
			$html = serialize_blocks( $params['blocks'] );
			return array(
				'html'   => $html,
				'length' => strlen( $html ),
			);
		},
		'permission_callback' => function() {
			return current_user_can( 'edit_posts' );
		},
		'meta' => array(
			'show_in_rest' => true,
			'mcp'          => array( 'public' => true, 'type' => 'tool' ),
			'annotations'  => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			),
		
		'tier' => 'free',),
	));

	// ---- blocks/list-types ----
	wp_register_ability( 'blocks/list-types', array(
		'label'       => 'List Block Types',
		'description' => 'List all registered block types with their attributes, supports, and styles.',
		'category'    => 'blocks',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				array(
					'namespace' => array(
						'type'        => 'string',
						'description' => 'Filter by namespace (e.g. "core", "uagb")',
					),
					'search' => array(
						'type'        => 'string',
						'description' => 'Search block type names',
					),
				),
				wp_native_pagination_schema()
			),
		),
		'execute_callback' => function( $params ) {
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

			$pag   = wp_native_pagination( $params );
			$slice = array_slice( $types, $pag['offset'], $pag['per_page'] );

			return array(
				'types' => $slice,
				'total' => count( $types ),
				'page'  => $pag['page'],
				'pages' => ceil( count( $types ) / $pag['per_page'] ),
			);
		},
		'permission_callback' => function() {
			return current_user_can( 'edit_posts' );
		},
		'meta' => array(
			'show_in_rest' => true,
			'mcp'          => array( 'public' => true, 'type' => 'tool' ),
			'annotations'  => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			),
		
		'tier' => 'free',),
	));

	// ---- blocks/get-type ----
	wp_register_ability( 'blocks/get-type', array(
		'label'       => 'Get Block Type',
		'description' => 'Get detailed information about a single registered block type.',
		'category'    => 'blocks',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'name' => array(
					'type'        => 'string',
					'description' => 'Block type name (e.g. "core/paragraph", "uagb/container")',
				),
			),
			'required' => array( 'name' ),
		),
		'execute_callback' => function( $params ) {
			$name = sanitize_text_field( $params['name'] ?? '' );
			if ( ! $name ) {
				return wp_native_error( 'missing_name', 'Block type name is required.' );
			}
			$registry = WP_Block_Type_Registry::get_instance();
			$type     = $registry->get_registered( $name );
			if ( ! $type ) {
				return wp_native_error( 'not_found', "Block type '{$name}' is not registered." );
			}
			return array(
				'name'            => $type->name,
				'title'           => $type->title ?? '',
				'description'     => $type->description ?? '',
				'category'        => $type->category ?? '',
				'parent'          => $type->parent ?? null,
				'ancestor'        => $type->ancestor ?? null,
				'allowed_blocks'  => $type->allowed_blocks ?? null,
				'icon'            => is_string( $type->icon ) ? $type->icon : null,
				'supports'        => $type->supports ?? array(),
				'attributes'      => $type->attributes ?? array(),
				'styles'          => $type->styles ?? array(),
				'example'         => $type->example ?? null,
				'provides_context' => $type->provides_context ?? array(),
				'uses_context'    => $type->uses_context ?? array(),
				'api_version'     => $type->api_version ?? 1,
			);
		},
		'permission_callback' => function() {
			return current_user_can( 'edit_posts' );
		},
		'meta' => array(
			'show_in_rest' => true,
			'mcp'          => array( 'public' => true, 'type' => 'tool' ),
			'annotations'  => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			),
		
		'tier' => 'free',),
	));

	// ---- blocks/find-in-post ----
	wp_register_ability( 'blocks/find-in-post', array(
		'label'       => 'Find Block in Post',
		'description' => 'Find blocks by name or attribute value within a post. Returns matching blocks with their index positions.',
		'category'    => 'blocks',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'Post ID to search within',
				),
				'block_name' => array(
					'type'        => 'string',
					'description' => 'Block type name to find (e.g. "core/paragraph")',
				),
				'attribute_key' => array(
					'type'        => 'string',
					'description' => 'Attribute key to match',
				),
				'attribute_value' => array(
					'type'        => 'string',
					'description' => 'Attribute value to match',
				),
			),
			'required' => array( 'post_id' ),
		),
		'execute_callback' => function( $params ) {
			$check = wp_abilities_suite_require_editable_post( $params['post_id'] ?? 0 );
			if ( is_wp_error( $check ) ) return $check;

			$blocks  = parse_blocks( $check->post_content );
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
					$matches[] = array(
						'index' => $index,
						'block' => $block,
					);
				}
				$index++;
			}

			return array(
				'found'   => count( $matches ),
				'matches' => $matches,
			);
		},
		'permission_callback' => function() {
			return current_user_can( 'edit_posts' );
		},
		'meta' => array(
			'show_in_rest' => true,
			'mcp'          => array( 'public' => true, 'type' => 'tool' ),
			'annotations'  => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			),
		
		'tier' => 'free',),
	));

	} // end read

	// ===== BLOCKS — WRITE =====
	if ( ! empty( $perms['write'] ) ) {

	// ---- blocks/insert ----
	wp_register_ability( 'blocks/insert', array(
		'label'       => 'Insert Block',
		'description' => 'Insert one or more blocks at a position in a post. Position 0 = beginning, -1 = end.',
		'category'    => 'blocks',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'Post ID to insert blocks into',
				),
				'blocks' => array(
					'type'        => 'array',
					'description' => 'Block objects to insert',
					'items'       => array( 'type' => 'object' ),
				),
				'position' => array(
					'type'        => 'integer',
					'description' => 'Insert position (0 = beginning, -1 = end, N = after Nth block)',
					'default'     => -1,
				),
			),
			'required' => array( 'post_id', 'blocks' ),
		),
		'execute_callback' => wp_abilities_suite_pro_gate('blocks/insert', function( $params ) {
			$check = wp_abilities_suite_require_editable_post( $params['post_id'] ?? 0 );
			if ( is_wp_error( $check ) ) return $check;
			$post_id = $check->ID;

			$existing    = parse_blocks( $check->post_content );
			$new_blocks  = $params['blocks'];
			$position    = intval( $params['position'] ?? -1 );

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

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return array(
				'post_id'     => $post_id,
				'inserted'    => count( $new_blocks ),
				'total_blocks' => count( array_filter( $existing, function( $b ) { return ! empty( $b['blockName'] ); } ) ),
			);
		}),
		'permission_callback' => function() {
			return current_user_can( 'edit_posts' );
		},
		'meta' => array(
			'show_in_rest' => true,
			'mcp'          => array( 'public' => true, 'type' => 'tool' ),
			'annotations'  => array(
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => false,
			),
		
		'tier' => 'pro',),
	));

	// ---- blocks/replace ----
	wp_register_ability( 'blocks/replace', array(
		'label'       => 'Replace Block',
		'description' => 'Replace a block at a specific index position in a post with a new block.',
		'category'    => 'blocks',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'Post ID containing the block',
				),
				'index' => array(
					'type'        => 'integer',
					'description' => 'Block index position to replace (from parse or find output)',
				),
				'block' => array(
					'type'        => 'object',
					'description' => 'New block object to replace with',
				),
			),
			'required' => array( 'post_id', 'index', 'block' ),
		),
		'execute_callback' => wp_abilities_suite_pro_gate('blocks/replace', function( $params ) {
			$check = wp_abilities_suite_require_editable_post( $params['post_id'] ?? 0 );
			if ( is_wp_error( $check ) ) return $check;
			$post_id = $check->ID;

			$blocks = parse_blocks( $check->post_content );
			$index  = intval( $params['index'] ?? -1 );

			if ( $index < 0 || $index >= count( $blocks ) ) {
				return wp_native_error( 'invalid_index', "Block index {$index} is out of range (0-" . ( count( $blocks ) - 1 ) . ")." );
			}

			$old_name       = $blocks[ $index ]['blockName'] ?? '(empty)';
			$blocks[ $index ] = $params['block'];

			$result = wp_update_post( array(
				'ID'           => $post_id,
				'post_content' => serialize_blocks( $blocks ),
			), true );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return array(
				'post_id'      => $post_id,
				'replaced'     => $old_name,
				'new_block'    => $params['block']['blockName'] ?? 'unknown',
				'index'        => $index,
			);
		}),
		'permission_callback' => function() {
			return current_user_can( 'edit_posts' );
		},
		'meta' => array(
			'show_in_rest' => true,
			'mcp'          => array( 'public' => true, 'type' => 'tool' ),
			'annotations'  => array(
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => true,
			),
		
		'tier' => 'pro',),
	));

	} // end write

	// ===== BLOCKS — DELETE =====
	if ( ! empty( $perms['delete'] ) ) {

	// ---- blocks/remove ----
	wp_register_ability( 'blocks/remove', array(
		'label'       => 'Remove Block',
		'description' => 'Remove a block at a specific index position from a post.',
		'category'    => 'blocks',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'Post ID containing the block',
				),
				'index' => array(
					'type'        => 'integer',
					'description' => 'Block index position to remove',
				),
			),
			'required' => array( 'post_id', 'index' ),
		),
		'execute_callback' => wp_abilities_suite_pro_gate('blocks/remove', function( $params ) {
			$check = wp_abilities_suite_require_editable_post( $params['post_id'] ?? 0 );
			if ( is_wp_error( $check ) ) return $check;
			$post_id = $check->ID;

			$blocks = parse_blocks( $check->post_content );
			$index  = intval( $params['index'] ?? -1 );

			if ( $index < 0 || $index >= count( $blocks ) ) {
				return wp_native_error( 'invalid_index', "Block index {$index} is out of range (0-" . ( count( $blocks ) - 1 ) . ")." );
			}

			$removed = $blocks[ $index ]['blockName'] ?? '(empty)';
			array_splice( $blocks, $index, 1 );

			$result = wp_update_post( array(
				'ID'           => $post_id,
				'post_content' => serialize_blocks( $blocks ),
			), true );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return array(
				'post_id'       => $post_id,
				'removed'       => $removed,
				'remaining_blocks' => count( array_filter( $blocks, function( $b ) { return ! empty( $b['blockName'] ); } ) ),
			);
		}),
		'permission_callback' => function() {
			return current_user_can( 'edit_posts' );
		},
		'meta' => array(
			'show_in_rest' => true,
			'mcp'          => array( 'public' => true, 'type' => 'tool' ),
			'annotations'  => array(
				'readonly'    => false,
				'destructive' => true,
				'idempotent'  => false,
			),
		
		'tier' => 'pro',),
	));

	} // end delete
}
