<?php
/**
 * Spectra Suite — Validation Abilities
 *
 * Post-deployment validation and cache management.
 *
 * Abilities:
 *   - spectra/validate-page (read)
 *   - spectra/flush-caches  (write)
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

});
