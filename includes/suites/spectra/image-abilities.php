<?php
/**
 * Spectra Suite — Image Abilities
 *
 * Generates Spectra uagb/image block markup from WordPress media library
 * attachments, with full srcset, dimensions, and alt text resolution.
 *
 * Abilities:
 *   - spectra/build-image (read — generates markup without modifying posts)
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	if ( ! class_exists( 'UAGB_Loader' ) ) {
		return;
	}

	$reg = new Abilities_For_AI_Registrar( 'spectra', 'upload_files' );

	$reg->read( 'spectra/build-image', array(
		'label'        => 'Build Image Block',
		'description'  => 'Generate a complete Spectra uagb/image block from a media library attachment ID, with srcset, dimensions, and alt text resolved server-side.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'attachment_id' ),
			'properties' => array(
				'attachment_id' => array(
					'type'        => 'integer',
					'description' => 'WordPress media library attachment ID',
				),
				'block_id' => array(
					'type'        => 'string',
					'description' => 'Spectra block_id for the image block (e.g., "pr-hero-img"). Auto-generated if omitted.',
				),
				'size_slug' => array(
					'type'        => 'string',
					'description' => 'WordPress image size (thumbnail, medium, large, full)',
					'default'     => 'full',
				),
				'layout' => array(
					'type'        => 'string',
					'description' => 'Image layout: default or overlay',
					'default'     => 'default',
					'enum'        => array( 'default', 'overlay' ),
				),
				'align' => array(
					'type'        => 'string',
					'description' => 'Block alignment (left, center, right, wide, full)',
				),
				'custom_alt' => array(
					'type'        => 'string',
					'description' => 'Override alt text (uses media library alt if omitted)',
				),
				'custom_classes' => array(
					'type'        => 'string',
					'description' => 'Additional CSS classes to add to the block',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'markup'        => array( 'type' => 'string' ),
				'attachment_id' => array( 'type' => 'integer' ),
				'url'           => array( 'type' => 'string' ),
				'width'         => array( 'type' => 'integer' ),
				'height'        => array( 'type' => 'integer' ),
				'alt'           => array( 'type' => 'string' ),
			),
		),
		'callback' => function( $input ) {
			$input = (array) $input;
			$attachment_id = $input['attachment_id'];
			$size_slug     = $input['size_slug'] ?? 'full';

			$attachment = get_post( $attachment_id );
			if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
				return new WP_Error( 'not_found', 'Attachment not found: ' . $attachment_id );
			}

			$image_src = wp_get_attachment_image_src( $attachment_id, $size_slug );
			if ( ! $image_src ) {
				return new WP_Error( 'not_image', 'Attachment is not an image or size not available.' );
			}

			$url    = $image_src[0];
			$width  = $image_src[1];
			$height = $image_src[2];

			$full_src       = wp_get_attachment_image_src( $attachment_id, 'full' );
			$natural_width  = $full_src ? $full_src[1] : $width;
			$natural_height = $full_src ? $full_src[2] : $height;

			$srcset = wp_get_attachment_image_srcset( $attachment_id, $size_slug ) ?: '';
			$sizes  = wp_get_attachment_image_sizes( $attachment_id, $size_slug ) ?: '';

			$alt = $input['custom_alt'] ?? '';
			if ( empty( $alt ) ) {
				$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: '';
			}

			$block_id = $input['block_id'] ?? ( 'img-' . substr( md5( uniqid() ), 0, 8 ) );
			$layout   = $input['layout'] ?? 'default';
			$align    = $input['align'] ?? '';

			$attrs = array(
				'block_id'      => $block_id,
				'url'           => $url,
				'id'            => $attachment_id,
				'width'         => $width,
				'height'        => $height,
				'naturalWidth'  => $natural_width,
				'naturalHeight' => $natural_height,
				'sizeSlug'      => $size_slug,
			);

			if ( 'default' !== $layout ) {
				$attrs['layout'] = $layout;
			}
			if ( ! empty( $align ) ) {
				$attrs['align'] = $align;
			}

			$attrs_json = wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES );

			$class_parts   = array( 'wp-block-uagb-image' );
			$class_parts[] = 'uagb-block-' . $block_id;
			$class_parts[] = 'wp-block-uagb-image--layout-' . $layout;
			$class_parts[] = 'wp-block-uagb-image--effect-static';
			$align_class   = ! empty( $align ) ? $align : 'none';
			$class_parts[] = 'wp-block-uagb-image--align-' . $align_class;

			if ( ! empty( $input['custom_classes'] ) ) {
				$class_parts[] = $input['custom_classes'];
			}

			$classes = implode( ' ', $class_parts );

			$srcset_attr = ! empty( $srcset ) ? ' srcset="' . esc_attr( $srcset ) . '"' : '';
			$sizes_attr  = ! empty( $sizes ) ? ' sizes="' . esc_attr( $sizes ) . '"' : '';

			$markup  = '<!-- wp:uagb/image ' . $attrs_json . ' -->' . "\n";
			$markup .= '<div class="' . esc_attr( $classes ) . '">';
			$markup .= '<figure class="wp-block-uagb-image__figure">';
			$markup .= '<img' . $srcset_attr . $sizes_attr;
			$markup .= ' src="' . esc_url( $url ) . '"';
			$markup .= ' alt="' . esc_attr( $alt ) . '"';
			$markup .= ' width="' . esc_attr( $width ) . '"';
			$markup .= ' height="' . esc_attr( $height ) . '"';
			$markup .= ' loading="lazy"';
			$markup .= ' role="img"/>';
			$markup .= '</figure>';
			$markup .= '</div>' . "\n";
			$markup .= '<!-- /wp:uagb/image -->';

			return array(
				'markup'        => $markup,
				'attachment_id' => $attachment_id,
				'url'           => $url,
				'width'         => $width,
				'height'        => $height,
				'alt'           => $alt,
			);
		},
	));

});
