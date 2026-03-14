<?php
/**
 * Spectra Suite — Asset Abilities
 *
 * Triggers Spectra CSS regeneration for a specific post.
 *
 * Abilities:
 *   - spectra/regenerate-assets (write)
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	if ( ! class_exists( 'UAGB_Loader' ) ) {
		return;
	}

	$reg = new Abilities_For_AI_Registrar( 'spectra', 'edit_posts' );

	$reg->write( 'spectra/regenerate-assets', array(
		'label'        => 'Regenerate Spectra Assets',
		'description'  => 'Trigger Spectra CSS regeneration for a specific page. Run this after inserting or updating blocks to ensure styles are rebuilt.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'post_id' ),
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'The WordPress post/page ID to regenerate assets for',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success' => array( 'type' => 'boolean' ),
				'post_id' => array( 'type' => 'integer' ),
				'message' => array( 'type' => 'string' ),
			),
		),
		'callback' => function( $input ) {
			$post = get_post( $input['post_id'] );
			if ( ! $post ) {
				return new WP_Error( 'not_found', 'Post not found.' );
			}

			if ( ! class_exists( 'UAGB_Post_Assets' ) ) {
				return new WP_Error( 'spectra_missing', 'Spectra plugin is not active. UAGB_Post_Assets class not found.' );
			}

			new UAGB_Post_Assets( $post->ID );

			clean_post_cache( $post->ID );
			wp_cache_delete( $post->ID, 'posts' );
			wp_cache_delete( $post->ID, 'post_meta' );

			return array(
				'success' => true,
				'post_id' => $post->ID,
				'message' => 'Spectra CSS regenerated for post ' . $post->ID . '. Clear CDN cache if using one.',
			);
		},
	));

});
