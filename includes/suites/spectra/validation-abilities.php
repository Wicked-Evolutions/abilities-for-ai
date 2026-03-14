<?php
/**
 * Spectra Suite — Validation Abilities
 *
 * Post-deployment validation and cache management.
 *
 * Abilities:
 *   - spectra/validate-page   (read)
 *   - spectra/flush-caches    (write)
 *   - spectra/validate-markup (read) — P2
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	if ( ! class_exists( 'UAGB_Loader' ) ) {
		return;
	}

	$reg = new Abilities_For_AI_Registrar( 'spectra', 'edit_posts' );

	// ===== VALIDATE PAGE =====

	$reg->read( 'spectra/validate-page', array(
		'label'        => 'Validate Page',
		'description'  => 'Post-deployment structural validation. Checks for duplicate block_ids, missing classMigrate/childMigrate, orphaned image attachments, and untargetable blocks.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'post_id' ),
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'The WordPress post/page ID to validate',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'valid'         => array( 'type' => 'boolean' ),
				'post_id'       => array( 'type' => 'integer' ),
				'error_count'   => array( 'type' => 'integer' ),
				'warning_count' => array( 'type' => 'integer' ),
				'issues'        => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
				'stats'         => array( 'type' => 'object' ),
			),
		),
		'callback' => function( $input ) {
			$post = get_post( $input['post_id'] );
			if ( ! $post ) {
				return new WP_Error( 'not_found', 'Post not found.' );
			}

			$parsed = parse_blocks( $post->post_content );
			$flat   = spectra_abilities_flatten_blocks( $parsed );

			$issues     = array();
			$block_ids  = array();
			$uagb_count = 0;
			$total_count = count( $flat );
			$image_ids  = array();

			foreach ( $flat as $block ) {
				$name  = $block['blockName'];
				$attrs = $block['attrs'] ?? array();

				$is_uagb = ( strpos( $name, 'uagb/' ) === 0 );
				if ( $is_uagb ) {
					$uagb_count++;
				}

				// Check 1: Duplicate block_ids.
				if ( ! empty( $attrs['block_id'] ) ) {
					$bid = $attrs['block_id'];
					if ( isset( $block_ids[ $bid ] ) ) {
						$issues[] = array(
							'severity'   => 'error',
							'check'      => 'duplicate_block_id',
							'block_id'   => $bid,
							'block_name' => $name,
							'message'    => 'Duplicate block_id "' . $bid . '" found on ' . $name . ' (also used by ' . $block_ids[ $bid ] . ')',
							'suggestion' => 'Each block_id must be unique. Rename one instance.',
						);
					} else {
						$block_ids[ $bid ] = $name;
					}
				}

				// Check 2: Missing classMigrate on UAGB blocks.
				if ( $is_uagb && ! isset( $attrs['classMigrate'] ) ) {
					$issues[] = array(
						'severity'   => 'warning',
						'check'      => 'missing_classMigrate',
						'block_id'   => $attrs['block_id'] ?? '(none)',
						'block_name' => $name,
						'message'    => $name . ' is missing classMigrate attribute',
						'suggestion' => 'Add "classMigrate":true to the block attributes. This ensures Spectra uses the modern CSS class format.',
					);
				}

				// Check 3: Missing childMigrate on top-level container blocks.
				// Fixed: original had double-negation `! empty( $block['_depth'] ) === false`.
				// Correct logic: check if depth is 0 (top-level).
				if ( 'uagb/container' === $name && 0 === ( $block['_depth'] ?? -1 ) ) {
					if ( ! isset( $attrs['childMigrate'] ) ) {
						$issues[] = array(
							'severity'   => 'warning',
							'check'      => 'missing_childMigrate',
							'block_id'   => $attrs['block_id'] ?? '(none)',
							'block_name' => $name,
							'message'    => 'Top-level container missing childMigrate attribute',
							'suggestion' => 'Add "childMigrate":true to ensure child blocks use modern class format.',
						);
					}
				}

				// Check 4: UAGB blocks without block_id (untargetable).
				if ( $is_uagb && empty( $attrs['block_id'] ) ) {
					$issues[] = array(
						'severity'   => 'warning',
						'check'      => 'missing_block_id',
						'block_name' => $name,
						'message'    => $name . ' has no block_id attribute (untargetable for updates)',
						'suggestion' => 'Add a descriptive block_id so this block can be targeted by spectra/update-section.',
					);
				}

				// Collect image attachment IDs for validation.
				if ( 'uagb/image' === $name || 'core/image' === $name ) {
					$img_id = $attrs['id'] ?? null;
					if ( $img_id ) {
						$image_ids[] = $img_id;
					}
				}
			}

			// Check 5: Orphaned image attachments.
			foreach ( array_unique( $image_ids ) as $img_id ) {
				if ( ! wp_attachment_is_image( $img_id ) ) {
					$issues[] = array(
						'severity'   => 'error',
						'check'      => 'orphaned_image',
						'message'    => 'Image attachment ID ' . $img_id . ' does not exist or is not an image',
						'suggestion' => 'Upload the image to the media library and update the block with the correct attachment ID.',
					);
				}
			}

			$error_count   = count( array_filter( $issues, function( $i ) { return 'error' === $i['severity']; } ) );
			$warning_count = count( array_filter( $issues, function( $i ) { return 'warning' === $i['severity']; } ) );

			return array(
				'valid'         => 0 === $error_count,
				'post_id'       => $post->ID,
				'error_count'   => $error_count,
				'warning_count' => $warning_count,
				'issues'        => $issues,
				'stats'         => array(
					'total_blocks'     => $total_count,
					'spectra_blocks'   => $uagb_count,
					'unique_block_ids' => count( $block_ids ),
					'image_refs'       => count( $image_ids ),
				),
			);
		},
	));

	// ===== FLUSH CACHES =====

	// flush-caches requires manage_options — separate registrar.
	$reg_admin = new Abilities_For_AI_Registrar( 'spectra', 'manage_options' );

	$reg_admin->write( 'spectra/flush-caches', array(
		'label'        => 'Flush Caches',
		'description'  => 'Flush WP object cache and trigger LiteSpeed cache purge. Optionally target a specific page.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'Optional post/page ID to purge specifically. If omitted, flushes all caches.',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success' => array( 'type' => 'boolean' ),
				'actions' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
		),
		'callback' => function( $input ) {
			$actions = array();
			$post_id = $input['post_id'] ?? null;

			wp_cache_flush();
			$actions[] = 'wp_cache_flush';

			if ( $post_id ) {
				$post = get_post( $post_id );
				if ( ! $post ) {
					return new WP_Error( 'not_found', 'Post not found: ' . $post_id );
				}

				clean_post_cache( $post_id );
				$actions[] = 'clean_post_cache(' . $post_id . ')';

				if ( has_action( 'litespeed_purge_post' ) ) {
					do_action( 'litespeed_purge_post', $post_id );
					$actions[] = 'litespeed_purge_post(' . $post_id . ')';
				}
			} else {
				if ( has_action( 'litespeed_purge_all' ) ) {
					do_action( 'litespeed_purge_all' );
					$actions[] = 'litespeed_purge_all';
				}
			}

			if ( function_exists( 'wp_cache_clear_cache' ) ) {
				wp_cache_clear_cache();
				$actions[] = 'wp_super_cache_clear';
			}

			if ( function_exists( 'w3tc_flush_all' ) ) {
				w3tc_flush_all();
				$actions[] = 'w3tc_flush_all';
			}

			return array(
				'success' => true,
				'actions' => $actions,
			);
		},
	));

	// ===== VALIDATE MARKUP =====

	$reg->read( 'spectra/validate-markup', array(
		'label'        => 'Validate Block Markup',
		'description'  => 'Validate block markup before inserting it into a post. Checks for valid block structure, required Spectra attributes, and common errors that cause "Resolve Block" in the editor.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'markup' ),
			'properties' => array(
				'markup' => array(
					'type'        => 'string',
					'description' => 'The Gutenberg block markup to validate',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'valid'         => array( 'type' => 'boolean' ),
				'block_count'   => array( 'type' => 'integer' ),
				'error_count'   => array( 'type' => 'integer' ),
				'warning_count' => array( 'type' => 'integer' ),
				'issues'        => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
				'blocks'        => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			),
		),
		'callback' => function( $input ) {
			$parsed = parse_blocks( $input['markup'] );

			$named = array_values( array_filter( $parsed, function( $b ) {
				return ! empty( $b['blockName'] );
			}));

			if ( empty( $named ) ) {
				return new WP_Error( 'no_blocks', 'No valid blocks found in the provided markup.' );
			}

			$issues   = array();
			$flat     = spectra_abilities_flatten_blocks( $parsed );
			$seen_ids = array();

			foreach ( $flat as $block ) {
				$name  = $block['blockName'];
				$attrs = $block['attrs'] ?? array();
				$is_uagb = ( strpos( $name, 'uagb/' ) === 0 );

				// Check 1: Spectra blocks must have block_id.
				if ( $is_uagb && empty( $attrs['block_id'] ) ) {
					$issues[] = array(
						'severity'   => 'error',
						'check'      => 'missing_block_id',
						'block_name' => $name,
						'message'    => $name . ' is missing required block_id attribute. This will cause targeting issues.',
						'suggestion' => 'Add a unique block_id string (e.g., "my-section-header").',
					);
				}

				// Check 2: Duplicate block_ids.
				if ( ! empty( $attrs['block_id'] ) ) {
					$bid = $attrs['block_id'];
					if ( isset( $seen_ids[ $bid ] ) ) {
						$issues[] = array(
							'severity'   => 'error',
							'check'      => 'duplicate_block_id',
							'block_id'   => $bid,
							'block_name' => $name,
							'message'    => 'Duplicate block_id "' . $bid . '" — also used by ' . $seen_ids[ $bid ],
						);
					} else {
						$seen_ids[ $bid ] = $name;
					}
				}

				// Check 3: Spectra blocks should have classMigrate.
				if ( $is_uagb && ! isset( $attrs['classMigrate'] ) ) {
					$issues[] = array(
						'severity'   => 'warning',
						'check'      => 'missing_classMigrate',
						'block_name' => $name,
						'block_id'   => $attrs['block_id'] ?? '(none)',
						'message'    => $name . ' is missing classMigrate attribute',
						'suggestion' => 'Add "classMigrate": true for modern CSS class format.',
					);
				}

				// Check 4: Block name must be registered.
				$registered = WP_Block_Type_Registry::get_instance()->get_registered( $name );
				if ( null === $registered ) {
					$issues[] = array(
						'severity'   => 'warning',
						'check'      => 'unregistered_block',
						'block_name' => $name,
						'message'    => 'Block type "' . $name . '" is not registered on this server. May cause "Resolve Block" in editor.',
						'suggestion' => 'Verify the block name is correct and the plugin providing it is active.',
					);
				}

				// Check 5: innerHTML consistency — comment delimiter should contain valid JSON attrs.
				if ( $is_uagb && ! empty( $attrs ) ) {
					// Check that the comment delimiter can round-trip.
					$re_encoded = wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES );
					if ( false === $re_encoded ) {
						$issues[] = array(
							'severity'   => 'error',
							'check'      => 'invalid_attrs_json',
							'block_name' => $name,
							'block_id'   => $attrs['block_id'] ?? '(none)',
							'message'    => 'Block attributes cannot be serialized to valid JSON.',
						);
					}
				}
			}

			$error_count   = count( array_filter( $issues, function( $i ) { return 'error' === $i['severity']; } ) );
			$warning_count = count( array_filter( $issues, function( $i ) { return 'warning' === $i['severity']; } ) );

			// Build a summary of what was parsed.
			$block_summary = array();
			foreach ( $named as $b ) {
				$block_summary[] = array(
					'name'     => $b['blockName'],
					'block_id' => $b['attrs']['block_id'] ?? null,
					'has_inner' => ! empty( $b['innerBlocks'] ),
				);
			}

			return array(
				'valid'         => 0 === $error_count,
				'block_count'   => count( $flat ),
				'error_count'   => $error_count,
				'warning_count' => $warning_count,
				'issues'        => $issues,
				'blocks'        => $block_summary,
			);
		},
	));

});
