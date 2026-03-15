<?php
/**
 * Spectra Suite — Inspection Abilities
 *
 * Lightweight inspection tools for page structure, block CSS, and theme classes.
 *
 * Abilities:
 *   - spectra/get-page-outline  (read)
 *   - spectra/get-block-css     (read)
 *   - spectra/get-theme-classes (read)
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	if ( ! class_exists( 'UAGB_Loader' ) ) {
		return;
	}

	$reg = new Abilities_For_AI_Registrar( 'spectra', 'edit_posts' );

	// ===== GET PAGE OUTLINE =====

	$reg->read( 'spectra/get-page-outline', array(
		'label'        => 'Get Page Outline',
		'description'  => 'Lightweight page structure: block names, block_ids, classNames, and nesting depth. Returns ~3-5K instead of 86K from get-page-blocks.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'post_id' ),
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'The WordPress post/page ID to outline',
				),
				'max_depth' => array(
					'type'        => 'integer',
					'description' => 'Maximum nesting depth (-1 = unlimited, 0 = top-level only). Default: -1',
					'default'     => -1,
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_id'     => array( 'type' => 'integer' ),
				'title'       => array( 'type' => 'string' ),
				'block_count' => array( 'type' => 'integer' ),
				'outline'     => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			),
		),
		'callback' => function( $input ) {
			$post = get_post( $input['post_id'] );
			if ( ! $post ) {
				return new WP_Error( 'not_found', 'Post not found.' );
			}

			$parsed    = parse_blocks( $post->post_content );
			$max_depth = $input['max_depth'] ?? -1;
			$outline   = spectra_abilities_outline_blocks( $parsed, 0, $max_depth );
			$total     = spectra_abilities_count_blocks( $parsed );

			return array(
				'post_id'     => $post->ID,
				'title'       => $post->post_title,
				'block_count' => $total,
				'outline'     => $outline,
			);
		},
	));

	// ===== GET BLOCK CSS =====

	$reg->read( 'spectra/get-block-css', array(
		'label'        => 'Get Block CSS',
		'description'  => 'Returns the Spectra-generated CSS for a specific block, split by desktop/tablet/mobile breakpoints.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'post_id', 'block_id' ),
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'The WordPress post/page ID',
				),
				'block_id' => array(
					'type'        => 'string',
					'description' => 'The Spectra block_id to get CSS for (e.g., "pr-hero-root")',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'block_id' => array( 'type' => 'string' ),
				'desktop'  => array( 'type' => 'string' ),
				'tablet'   => array( 'type' => 'string' ),
				'mobile'   => array( 'type' => 'string' ),
				'css_file' => array( 'type' => 'string' ),
			),
		),
		'callback' => function( $input ) {
			$post = get_post( $input['post_id'] );
			if ( ! $post ) {
				return new WP_Error( 'not_found', 'Post not found.' );
			}

			$block_id = $input['block_id'];

			$upload_dir = wp_upload_dir();
			$bucket     = absint( round( $post->ID, -3 ) );
			$css_path   = $upload_dir['basedir'] . '/uag-plugin/assets/' . $bucket . '/uag-css-' . $post->ID . '.css';

			if ( ! file_exists( $css_path ) ) {
				return new WP_Error( 'css_not_found', 'Spectra CSS file not found for post ' . $post->ID . '. Run spectra/regenerate-assets first.' );
			}

			$css_content = file_get_contents( $css_path );
			$selector    = '.uagb-block-' . $block_id;

			$desktop_rules = array();
			$tablet_rules  = array();
			$mobile_rules  = array();
			$sections      = array();
			$remaining     = $css_content;
			$pos           = 0;

			while ( preg_match( '/@media\s*\(([^)]+)\)\s*\{/s', $remaining, $match, PREG_OFFSET_CAPTURE, $pos ) ) {
				$before = substr( $remaining, $pos, $match[0][1] - $pos );
				if ( ! empty( trim( $before ) ) ) {
					$sections[] = array( 'context' => 'desktop', 'css' => $before );
				}

				$media_start = $match[0][1] + strlen( $match[0][0] );
				$brace_depth = 1;
				$media_end   = $media_start;

				while ( $brace_depth > 0 && $media_end < strlen( $remaining ) ) {
					$char = $remaining[ $media_end ];
					if ( '{' === $char ) {
						$brace_depth++;
					} elseif ( '}' === $char ) {
						$brace_depth--;
					}
					$media_end++;
				}

				$media_css   = substr( $remaining, $media_start, $media_end - $media_start - 1 );
				$media_query = $match[1][0];

				if ( strpos( $media_query, '1024' ) !== false || strpos( $media_query, 'max-width: 976' ) !== false ) {
					$context = 'tablet';
				} elseif ( strpos( $media_query, '767' ) !== false || strpos( $media_query, 'max-width: 768' ) !== false ) {
					$context = 'mobile';
				} else {
					$context = 'tablet';
				}

				$sections[] = array( 'context' => $context, 'css' => $media_css );
				$pos = $media_end;
			}

			$after = substr( $remaining, $pos );
			if ( ! empty( trim( $after ) ) ) {
				$sections[] = array( 'context' => 'desktop', 'css' => $after );
			}

			foreach ( $sections as $section ) {
				$lines          = explode( "\n", $section['css'] );
				$matching_rules = array();
				$in_rule        = false;
				$current_rule   = '';
				$brace_depth    = 0;

				foreach ( $lines as $line ) {
					if ( ! $in_rule && strpos( $line, $selector ) !== false ) {
						$in_rule      = true;
						$current_rule = '';
					}

					if ( $in_rule ) {
						$current_rule .= $line . "\n";
						$brace_depth  += substr_count( $line, '{' ) - substr_count( $line, '}' );

						if ( $brace_depth <= 0 ) {
							$matching_rules[] = trim( $current_rule );
							$in_rule          = false;
							$current_rule     = '';
							$brace_depth      = 0;
						}
					}
				}

				if ( ! empty( $matching_rules ) ) {
					$joined = implode( "\n\n", $matching_rules );
					switch ( $section['context'] ) {
						case 'desktop':
							$desktop_rules[] = $joined;
							break;
						case 'tablet':
							$tablet_rules[] = $joined;
							break;
						case 'mobile':
							$mobile_rules[] = $joined;
							break;
					}
				}
			}

			$desktop_css = implode( "\n\n", $desktop_rules );
			$tablet_css  = implode( "\n\n", $tablet_rules );
			$mobile_css  = implode( "\n\n", $mobile_rules );

			if ( empty( $desktop_css ) && empty( $tablet_css ) && empty( $mobile_css ) ) {
				return new WP_Error( 'no_css_found', 'No CSS rules found for block_id: ' . $block_id . '. Verify the block_id exists on this page.' );
			}

			return array(
				'block_id' => $block_id,
				'desktop'  => $desktop_css,
				'tablet'   => $tablet_css,
				'mobile'   => $mobile_css,
				'css_file' => str_replace( ABSPATH, '', $css_path ),
			);
		},
	));

	// ===== GET THEME CLASSES =====

	$reg->read( 'spectra/get-theme-classes', array(
		'label'        => 'Get Theme Classes',
		'description'  => 'Extract custom CSS class names from the active child theme style.css matching a prefix (e.g., "hw-" for Helena Willow classes).',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'prefix' => array(
					'type'        => 'string',
					'description' => 'CSS class prefix to search for (default: "hw-")',
					'default'     => 'hw-',
				),
				'include_rules' => array(
					'type'        => 'boolean',
					'description' => 'Include full CSS rule bodies (default: false, returns class names only)',
					'default'     => false,
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'theme'         => array( 'type' => 'string' ),
				'theme_version' => array( 'type' => 'string' ),
				'prefix'        => array( 'type' => 'string' ),
				'class_count'   => array( 'type' => 'integer' ),
				'classes'       => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			),
		),
		'callback' => function( $input ) {
			$prefix        = $input['prefix'] ?? 'hw-';
			$include_rules = $input['include_rules'] ?? false;

			$stylesheet_dir = get_stylesheet_directory();
			$style_path     = $stylesheet_dir . '/style.css';

			if ( ! file_exists( $style_path ) ) {
				return new WP_Error( 'not_found', 'Child theme style.css not found.' );
			}

			$css_content = file_get_contents( $style_path );

			$theme         = wp_get_theme();
			$theme_name    = $theme->get( 'Name' );
			$theme_version = $theme->get( 'Version' );

			$escaped_prefix = preg_quote( $prefix, '/' );

			if ( $include_rules ) {
				$classes = array();
				preg_match_all(
					'/([^\{\}]*\.' . $escaped_prefix . '[^\{\}]*)\{([^\}]*)\}/s',
					$css_content,
					$matches,
					PREG_SET_ORDER
				);

				foreach ( $matches as $match ) {
					$selector = trim( $match[1] );
					$body     = trim( $match[2] );

					preg_match_all( '/\.(' . $escaped_prefix . '[\w-]+)/', $selector, $class_matches );

					foreach ( $class_matches[1] as $class_name ) {
						if ( ! isset( $classes[ $class_name ] ) ) {
							$classes[ $class_name ] = array(
								'name'  => $class_name,
								'rules' => array(),
							);
						}
						$classes[ $class_name ]['rules'][] = array(
							'selector' => $selector,
							'body'     => $body,
						);
					}
				}

				$result_classes = array_values( $classes );
			} else {
				preg_match_all( '/\.(' . $escaped_prefix . '[\w-]+)/', $css_content, $matches );
				$class_names    = array_unique( $matches[1] );
				sort( $class_names );
				$result_classes = array_map( function( $name ) {
					return array( 'name' => $name );
				}, array_values( $class_names ) );
			}

			return array(
				'theme'         => $theme_name,
				'theme_version' => $theme_version,
				'prefix'        => $prefix,
				'class_count'   => count( $result_classes ),
				'classes'       => $result_classes,
			);
		},
	));

});
