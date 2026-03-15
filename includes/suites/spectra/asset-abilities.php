<?php
/**
 * Spectra Suite — Asset Abilities
 *
 * Triggers Spectra CSS regeneration for a specific post.
 *
 * Abilities:
 *   - spectra/regenerate-assets     (write)
 *   - spectra/regenerate-all-assets (write) — P1
 *   - spectra/get-asset-info        (read)  — P2
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
			$input = (array) $input;
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

	// ===== REGENERATE ALL ASSETS =====

	$reg->write( 'spectra/regenerate-all-assets', array(
		'label'        => 'Regenerate All Spectra Assets',
		'description'  => 'Site-wide CSS regeneration for all published posts containing Spectra blocks. Use after theme changes or Spectra updates. Processes in batches to avoid timeouts.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'batch_size' => array(
					'type'        => 'integer',
					'description' => 'Posts per batch (default: 50, max: 200)',
					'default'     => 50,
				),
				'post_type' => array(
					'type'        => 'string',
					'description' => 'Limit to a specific post type (default: all public types)',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success'    => array( 'type' => 'boolean' ),
				'processed'  => array( 'type' => 'integer' ),
				'regenerated' => array( 'type' => 'integer' ),
				'skipped'    => array( 'type' => 'integer' ),
				'message'    => array( 'type' => 'string' ),
			),
		),
		'callback' => function( $input ) {
			$input = (array) $input;
			if ( ! class_exists( 'UAGB_Post_Assets' ) ) {
				return new WP_Error( 'spectra_missing', 'UAGB_Post_Assets class not found.' );
			}

			$batch_size = min( max( (int) ( $input['batch_size'] ?? 50 ), 1 ), 200 );

			$args = array(
				'post_status'    => 'publish',
				'posts_per_page' => $batch_size,
				'fields'         => 'ids',
				'no_found_rows'  => false,
				's'              => 'wp:uagb/',
			);

			if ( ! empty( $input['post_type'] ) ) {
				$args['post_type'] = sanitize_text_field( $input['post_type'] );
			} else {
				$args['post_type'] = get_post_types( array( 'public' => true ) );
			}

			$query       = new WP_Query( $args );
			$regenerated = 0;
			$skipped     = 0;

			foreach ( $query->posts as $post_id ) {
				$post = get_post( $post_id );
				if ( ! $post || strpos( $post->post_content, 'wp:uagb/' ) === false ) {
					$skipped++;
					continue;
				}

				new UAGB_Post_Assets( $post_id );
				clean_post_cache( $post_id );
				$regenerated++;
			}

			return array(
				'success'     => true,
				'processed'   => count( $query->posts ),
				'regenerated' => $regenerated,
				'skipped'     => $skipped,
				'message'     => 'Regenerated CSS for ' . $regenerated . ' posts. Total matching posts in DB: ' . $query->found_posts . '. Clear CDN cache after this operation.',
			);
		},
	));

	// ===== GET ASSET INFO =====

	$reg->read( 'spectra/get-asset-info', array(
		'label'        => 'Get Spectra Asset Info',
		'description'  => 'Returns Spectra CSS file paths, sizes, and last-modified timestamps for a post. Useful for diagnosing stale styles.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'post_id' ),
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'The WordPress post/page ID to check assets for',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_id'       => array( 'type' => 'integer' ),
				'css_exists'    => array( 'type' => 'boolean' ),
				'css_path'      => array( 'type' => 'string' ),
				'css_url'       => array( 'type' => 'string' ),
				'css_size'      => array( 'type' => 'integer' ),
				'css_modified'  => array( 'type' => 'string' ),
				'post_modified' => array( 'type' => 'string' ),
				'is_stale'      => array( 'type' => 'boolean' ),
			),
		),
		'callback' => function( $input ) {
			$input = (array) $input;
			$post = get_post( $input['post_id'] );
			if ( ! $post ) {
				return new WP_Error( 'not_found', 'Post not found.' );
			}

			$upload_dir = wp_upload_dir();
			$bucket     = absint( round( $post->ID, -3 ) );
			$css_file   = 'uag-plugin/assets/' . $bucket . '/uag-css-' . $post->ID . '.css';
			$css_path   = $upload_dir['basedir'] . '/' . $css_file;
			$css_url    = $upload_dir['baseurl'] . '/' . $css_file;

			$css_exists = file_exists( $css_path );

			$result = array(
				'post_id'       => $post->ID,
				'css_exists'    => $css_exists,
				'css_path'      => str_replace( ABSPATH, '', $css_path ),
				'css_url'       => $css_url,
				'post_modified' => $post->post_modified,
			);

			if ( $css_exists ) {
				$css_mtime            = filemtime( $css_path );
				$post_mtime           = strtotime( $post->post_modified );
				$result['css_size']     = filesize( $css_path );
				$result['css_modified'] = gmdate( 'Y-m-d H:i:s', $css_mtime );
				$result['is_stale']     = ( $post_mtime > $css_mtime );
			} else {
				$result['css_size']     = 0;
				$result['css_modified'] = '';
				$result['is_stale']     = true;
			}

			return $result;
		},
	));

});
