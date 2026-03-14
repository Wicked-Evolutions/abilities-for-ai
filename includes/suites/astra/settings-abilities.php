<?php
/**
 * Astra Settings Abilities
 *
 * Design tokens, individual option access, and option updates.
 *
 * Abilities:
 *   - astra/get-design-tokens   (read)
 *   - astra/get-option          (read)
 *   - astra/update-option       (write)
 *   - astra/update-design-tokens (write)
 *
 * @package Abilities_For_AI
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Abilities_For_AI_Registrar( 'astra', 'edit_posts' );

	// ===== GET DESIGN TOKENS =====

	$reg->read( 'astra/get-design-tokens', array(
		'label'       => 'Get Design Tokens',
		'description' => 'Returns the complete Astra design system: global color palette, typography, button styles, layout settings, and version info. Filterable by section.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'sections' => array(
					'type'        => 'string',
					'description' => 'Comma-separated sections to return: colors, typography, buttons, layout, or "all" (default: all)',
					'default'     => 'all',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'theme'      => array( 'type' => 'string' ),
				'versions'   => array( 'type' => 'object' ),
				'colors'     => array( 'type' => 'object' ),
				'typography' => array( 'type' => 'object' ),
				'buttons'    => array( 'type' => 'object' ),
				'layout'     => array( 'type' => 'object' ),
			),
		),
		'callback' => function( $input ) {
			$sections_str = $input['sections'] ?? 'all';
			$sections     = array_map( 'trim', explode( ',', $sections_str ) );
			$include_all  = in_array( 'all', $sections, true );

			$result = array(
				'theme'    => wp_get_theme()->get( 'Name' ),
				'versions' => array(
					'astra'       => defined( 'ASTRA_THEME_VERSION' ) ? ASTRA_THEME_VERSION : 'unknown',
					'astra_pro'   => defined( 'ASTRA_EXT_VER' ) ? ASTRA_EXT_VER : null,
					'child_theme' => wp_get_theme()->get( 'Version' ),
				),
			);

			if ( $include_all || in_array( 'colors', $sections, true ) ) {
				$palette = astra_get_option( 'global-color-palette' );
				$palette_colors = array();
				if ( ! empty( $palette['palette'] ) && is_array( $palette['palette'] ) ) {
					foreach ( $palette['palette'] as $index => $color ) {
						$palette_colors[] = array(
							'index'    => $index,
							'css_var'  => '--ast-global-color-' . $index,
							'value'    => $color,
						);
					}
				}
				$result['colors'] = array(
					'global_palette'     => $palette_colors,
					'theme_color'        => astra_get_option( 'theme-color' ),
					'link_color'         => astra_get_option( 'link-color' ),
					'link_hover_color'   => astra_get_option( 'link-h-color' ),
					'text_color'         => astra_get_option( 'text-color' ),
					'heading_base_color' => astra_get_option( 'heading-base-color' ),
					'background'         => astra_get_option( 'site-layout-outside-bg-obj-responsive' ),
				);
			}

			if ( $include_all || in_array( 'typography', $sections, true ) ) {
				$result['typography'] = array(
					'body' => array(
						'font_family'    => astra_get_option( 'body-font-family' ),
						'font_weight'    => astra_get_option( 'body-font-weight' ),
						'font_size'      => astra_get_option( 'font-size-body' ),
						'line_height'    => astra_get_option( 'body-line-height' ),
						'text_transform' => astra_get_option( 'body-text-transform' ),
					),
					'headings' => array(
						'font_family'    => astra_get_option( 'headings-font-family' ),
						'font_weight'    => astra_get_option( 'headings-font-weight' ),
						'line_height'    => astra_get_option( 'headings-line-height' ),
						'text_transform' => astra_get_option( 'headings-text-transform' ),
					),
					'h1' => array( 'font_size' => astra_get_option( 'font-size-h1' ) ),
					'h2' => array( 'font_size' => astra_get_option( 'font-size-h2' ) ),
					'h3' => array( 'font_size' => astra_get_option( 'font-size-h3' ) ),
					'h4' => array( 'font_size' => astra_get_option( 'font-size-h4' ) ),
					'h5' => array( 'font_size' => astra_get_option( 'font-size-h5' ) ),
					'h6' => array( 'font_size' => astra_get_option( 'font-size-h6' ) ),
				);
			}

			if ( $include_all || in_array( 'buttons', $sections, true ) ) {
				$result['buttons'] = array(
					'color'          => astra_get_option( 'button-color' ),
					'hover_color'    => astra_get_option( 'button-h-color' ),
					'bg_color'       => astra_get_option( 'button-bg-color' ),
					'bg_hover_color' => astra_get_option( 'button-bg-h-color' ),
					'border_radius'  => astra_get_option( 'button-radius' ),
					'padding'        => astra_get_option( 'theme-button-padding' ),
					'font_family'    => astra_get_option( 'font-family-button' ),
					'font_weight'    => astra_get_option( 'font-weight-button' ),
					'font_size'      => astra_get_option( 'font-size-button' ),
					'text_transform' => astra_get_option( 'text-transform-button' ),
				);
			}

			if ( $include_all || in_array( 'layout', $sections, true ) ) {
				$result['layout'] = array(
					'site_layout'      => astra_get_option( 'site-layout' ),
					'container_layout' => astra_get_option( 'site-content-layout' ),
					'container_width'  => astra_get_option( 'site-content-width' ),
					'narrow_width'     => astra_get_option( 'narrow-container-max-width' ),
					'default_sidebar'  => astra_get_option( 'site-sidebar-layout' ),
					'content_style'    => astra_get_option( 'site-content-style' ),
				);
			}

			return $result;
		},
	));

	// ===== GET OPTION =====

	$reg->read( 'astra/get-option', array(
		'label'       => 'Get Astra Option',
		'description' => 'Read any individual Astra theme option by key. Use get-design-tokens for the full design system; this is for accessing specific settings not covered there.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'key' ),
			'properties' => array(
				'key' => array(
					'type'        => 'string',
					'description' => 'The Astra option key (e.g., "theme-color", "site-content-width")',
				),
				'default' => array(
					'type'        => 'string',
					'description' => 'Default value if option is not set (JSON-encoded for non-string values)',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'key'   => array( 'type' => 'string' ),
				'value' => array( 'type' => 'string' ),
				'type'  => array( 'type' => 'string' ),
			),
		),
		'callback' => function( $input ) {
			$key     = $input['key'];
			$default = $input['default'] ?? null;
			$value   = astra_get_option( $key, $default );
			$type    = gettype( $value );
			$encoded = is_string( $value ) ? $value : wp_json_encode( $value );

			return array(
				'key'   => $key,
				'value' => $encoded,
				'type'  => $type,
			);
		},
	));

	// ===== UPDATE OPTION =====

	$reg->write( 'astra/update-option', array(
		'label'       => 'Update Astra Option',
		'description' => 'Update a specific Astra theme option. Guarded by an allowlist of safe-to-modify keys.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'key', 'value' ),
			'properties' => array(
				'key' => array(
					'type'        => 'string',
					'description' => 'The Astra option key to update',
				),
				'value' => array(
					'type'        => 'string',
					'description' => 'The new value to set (use JSON string for complex values)',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success'   => array( 'type' => 'boolean' ),
				'key'       => array( 'type' => 'string' ),
				'old_value' => array( 'type' => 'string' ),
				'new_value' => array( 'type' => 'string' ),
			),
		),
		'callback' => function( $input ) {
			$key   = $input['key'];
			$value = $input['value'];

			$allowed_keys = array(
				// Layout.
				'site-content-width', 'narrow-container-max-width', 'site-content-layout',
				'site-sidebar-layout', 'site-content-style', 'site-layout',
				// Buttons.
				'button-color', 'button-h-color', 'button-bg-color', 'button-bg-h-color',
				'button-radius', 'theme-button-padding', 'font-family-button',
				'font-weight-button', 'font-size-button', 'text-transform-button',
				// Blog.
				'blog-post-structure', 'blog-post-content', 'blog-meta',
				// Footer.
				'footer-copyright-editor', 'footer-copyright-alignment',
				// Colors.
				'global-color-palette', 'theme-color', 'link-color', 'link-h-color',
				'text-color', 'heading-base-color',
				// Background.
				'site-layout-outside-bg-obj-responsive',
				// Typography — Body.
				'body-font-family', 'body-font-weight', 'body-line-height',
				'body-text-transform', 'font-size-body',
				// Typography — Headings.
				'headings-font-family', 'headings-font-weight', 'headings-line-height',
				'headings-text-transform', 'font-size-h1', 'font-size-h2', 'font-size-h3',
				'font-size-h4', 'font-size-h5', 'font-size-h6',
				// Transparent header.
				'transparent-header-enable',
			);

			if ( ! in_array( $key, $allowed_keys, true ) ) {
				return new \WP_Error(
					'key_not_allowed',
					'Key "' . $key . '" is not in the allowlist. Allowed keys: ' . implode( ', ', $allowed_keys )
				);
			}

			$old_value = astra_get_option( $key );
			astra_update_option( $key, $value );
			$new_value = astra_get_option( $key );

			return array(
				'success'   => true,
				'key'       => $key,
				'old_value' => is_string( $old_value ) ? $old_value : wp_json_encode( $old_value ),
				'new_value' => is_string( $new_value ) ? $new_value : wp_json_encode( $new_value ),
			);
		},
	));

	// ===== UPDATE DESIGN TOKENS (batch) =====

	$reg->write( 'astra/update-design-tokens', array(
		'label'       => 'Update Design Tokens',
		'description' => 'Batch update Astra design tokens. Accepts the same structure as get-design-tokens output. Only provided sections/keys are updated; omitted sections are left unchanged.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'colors' => array(
					'type'        => 'object',
					'description' => 'Color updates. Keys: global_palette (array of {index,color}), theme_color, link_color, link_hover_color, text_color, heading_base_color',
				),
				'typography' => array(
					'type'        => 'object',
					'description' => 'Typography updates. Keys: body {font_family, font_weight, font_size, line_height, text_transform}, headings {font_family, font_weight, line_height, text_transform}, h1-h6 {font_size}.',
				),
				'buttons' => array(
					'type'        => 'object',
					'description' => 'Button updates. Keys: color, hover_color, bg_color, bg_hover_color, border_radius, padding, font_family, font_weight, font_size, text_transform',
				),
				'layout' => array(
					'type'        => 'object',
					'description' => 'Layout updates. Keys: site_layout, container_layout, container_width, narrow_width, default_sidebar, content_style',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success'       => array( 'type' => 'boolean' ),
				'updated_keys'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'design_tokens' => array( 'type' => 'object' ),
			),
		),
		'callback' => function( $input ) {
			$updated = array();

			// === COLORS ===
			if ( ! empty( $input['colors'] ) ) {
				$colors = $input['colors'];
				if ( ! empty( $colors['global_palette'] ) && is_array( $colors['global_palette'] ) ) {
					$palette = astra_get_option( 'global-color-palette' );
					foreach ( $colors['global_palette'] as $entry ) {
						if ( isset( $entry['index'], $entry['color'] ) ) {
							$palette['palette'][ (int) $entry['index'] ] = $entry['color'];
						}
					}
					astra_update_option( 'global-color-palette', $palette );
					$updated[] = 'global-color-palette';
				}
				$color_map = array(
					'theme_color'        => 'theme-color',
					'link_color'         => 'link-color',
					'link_hover_color'   => 'link-h-color',
					'text_color'         => 'text-color',
					'heading_base_color' => 'heading-base-color',
				);
				foreach ( $color_map as $key => $option ) {
					if ( isset( $colors[ $key ] ) ) {
						astra_update_option( $option, $colors[ $key ] );
						$updated[] = $option;
					}
				}
			}

			// === TYPOGRAPHY ===
			if ( ! empty( $input['typography'] ) ) {
				$typo = $input['typography'];
				$body_map = array(
					'font_family' => 'body-font-family', 'font_weight' => 'body-font-weight',
					'font_size' => 'font-size-body', 'line_height' => 'body-line-height',
					'text_transform' => 'body-text-transform',
				);
				if ( ! empty( $typo['body'] ) ) {
					foreach ( $body_map as $key => $option ) {
						if ( isset( $typo['body'][ $key ] ) ) {
							astra_update_option( $option, $typo['body'][ $key ] );
							$updated[] = $option;
						}
					}
				}
				$heading_map = array(
					'font_family' => 'headings-font-family', 'font_weight' => 'headings-font-weight',
					'line_height' => 'headings-line-height', 'text_transform' => 'headings-text-transform',
				);
				if ( ! empty( $typo['headings'] ) ) {
					foreach ( $heading_map as $key => $option ) {
						if ( isset( $typo['headings'][ $key ] ) ) {
							astra_update_option( $option, $typo['headings'][ $key ] );
							$updated[] = $option;
						}
					}
				}
				for ( $i = 1; $i <= 6; $i++ ) {
					if ( ! empty( $typo["h{$i}"]['font_size'] ) ) {
						astra_update_option( "font-size-h{$i}", $typo["h{$i}"]['font_size'] );
						$updated[] = "font-size-h{$i}";
					}
				}
			}

			// === BUTTONS ===
			if ( ! empty( $input['buttons'] ) ) {
				$btns = $input['buttons'];
				$btn_map = array(
					'color' => 'button-color', 'hover_color' => 'button-h-color',
					'bg_color' => 'button-bg-color', 'bg_hover_color' => 'button-bg-h-color',
					'border_radius' => 'button-radius', 'padding' => 'theme-button-padding',
					'font_family' => 'font-family-button', 'font_weight' => 'font-weight-button',
					'font_size' => 'font-size-button', 'text_transform' => 'text-transform-button',
				);
				foreach ( $btn_map as $key => $option ) {
					if ( isset( $btns[ $key ] ) ) {
						astra_update_option( $option, $btns[ $key ] );
						$updated[] = $option;
					}
				}
			}

			// === LAYOUT ===
			if ( ! empty( $input['layout'] ) ) {
				$layout = $input['layout'];
				$layout_map = array(
					'site_layout' => 'site-layout', 'container_layout' => 'site-content-layout',
					'container_width' => 'site-content-width', 'narrow_width' => 'narrow-container-max-width',
					'default_sidebar' => 'site-sidebar-layout', 'content_style' => 'site-content-style',
				);
				foreach ( $layout_map as $key => $option ) {
					if ( isset( $layout[ $key ] ) ) {
						astra_update_option( $option, $layout[ $key ] );
						$updated[] = $option;
					}
				}
			}

			// Return full design tokens after update.
			$get_tokens = wp_get_ability( 'astra/get-design-tokens' );
			$tokens     = $get_tokens ? $get_tokens->execute( array( 'sections' => 'all' ) ) : array();

			return array(
				'success'       => true,
				'updated_keys'  => $updated,
				'design_tokens' => $tokens,
			);
		},
	));

}, 100 );
