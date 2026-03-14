<?php
/**
 * Spectra Suite — Reusable Block Abilities
 *
 * Discover and retrieve WordPress reusable blocks (synced patterns).
 *
 * Abilities:
 *   - spectra/list-reusable-blocks (read)
 *   - spectra/get-reusable-block   (read)
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

});
