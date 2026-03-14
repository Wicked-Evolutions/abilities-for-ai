<?php
/**
 * Astra Pro Abilities
 *
 * Astra Pro addon status, per-module settings read, and settings write.
 *
 * Abilities:
 *   - astra/get-pro-status    (read)
 *   - astra/get-pro-settings  (read)
 *   - astra/update-pro-settings (write) — P0 gap fill
 *
 * @package Abilities_For_AI
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Abilities_For_AI_Registrar( 'astra', 'edit_posts' );

	// ===== GET PRO STATUS =====

	$reg->read( 'astra/get-pro-status', array(
		'label'       => 'Get Pro Status',
		'description' => 'Returns Astra Pro addon status, version info, and all module active/inactive states. Works even if Pro is not installed.',
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'pro_active'    => array( 'type' => 'boolean' ),
				'pro_version'   => array( 'type' => 'string' ),
				'theme_version' => array( 'type' => 'string' ),
				'modules'       => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			),
		),
		'callback' => function( $input ) {
			$pro_active = astra_abilities_is_pro_active();
			$modules    = astra_abilities_get_pro_modules();

			$active_count   = count( array_filter( $modules, function( $m ) { return $m['active']; } ) );
			$inactive_count = count( $modules ) - $active_count;

			return array(
				'pro_active'     => $pro_active,
				'pro_version'    => $pro_active ? ASTRA_EXT_VER : null,
				'theme_version'  => defined( 'ASTRA_THEME_VERSION' ) ? ASTRA_THEME_VERSION : 'unknown',
				'active_count'   => $active_count,
				'inactive_count' => $inactive_count,
				'modules'        => $modules,
			);
		},
	));

	// ===== GET PRO SETTINGS =====

	$reg->read( 'astra/get-pro-settings', array(
		'label'       => 'Get Pro Settings',
		'description' => 'Returns settings for a specific Astra Pro module. Each module has its own configuration stored in Astra options.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'module' ),
			'properties' => array(
				'module' => array(
					'type'        => 'string',
					'description' => 'Pro module slug: colors-and-background, typography, spacing, blog-pro, sticky-header, site-layouts, advanced-hooks, nav-menu, advanced-headers',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'module'   => array( 'type' => 'string' ),
				'active'   => array( 'type' => 'boolean' ),
				'settings' => array( 'type' => 'object' ),
			),
		),
		'callback' => function( $input ) {
			if ( ! astra_abilities_is_pro_active() ) {
				return new \WP_Error( 'pro_required', 'Astra Pro is not active.' );
			}

			$module  = $input['module'];
			$modules = astra_abilities_get_pro_modules();

			$module_info = null;
			foreach ( $modules as $m ) {
				if ( $m['slug'] === $module ) {
					$module_info = $m;
					break;
				}
			}

			if ( ! $module_info ) {
				return new \WP_Error( 'unknown_module', 'Unknown Pro module: ' . $module . '. Use astra/get-pro-status to see available modules.' );
			}

			if ( ! $module_info['active'] ) {
				return array(
					'module'   => $module,
					'active'   => false,
					'settings' => array(),
					'message'  => 'Module "' . $module . '" is installed but not active.',
				);
			}

			$settings = array();

			switch ( $module ) {
				case 'colors-and-background':
					$settings = array(
						'h1_color'   => astra_get_option( 'h1-color' ),
						'h2_color'   => astra_get_option( 'h2-color' ),
						'h3_color'   => astra_get_option( 'h3-color' ),
						'h4_color'   => astra_get_option( 'h4-color' ),
						'h5_color'   => astra_get_option( 'h5-color' ),
						'h6_color'   => astra_get_option( 'h6-color' ),
						'content_bg' => astra_get_option( 'content-bg-obj-responsive' ),
						'header_bg'  => astra_get_option( 'hdr-bg-obj-responsive' ),
						'footer_bg'  => astra_get_option( 'footer-bg-obj' ),
						'sidebar_bg' => astra_get_option( 'sidebar-bg-obj' ),
					);
					break;

				case 'typography':
					$settings = array();
					for ( $i = 1; $i <= 6; $i++ ) {
						$settings["h{$i}"] = array(
							'font_family'    => astra_get_option( "font-family-h{$i}" ),
							'font_weight'    => astra_get_option( "font-weight-h{$i}" ),
							'text_transform' => astra_get_option( "text-transform-h{$i}" ),
							'line_height'    => astra_get_option( "line-height-h{$i}" ),
						);
					}
					$settings['entry_title'] = array(
						'font_family' => astra_get_option( 'font-family-entry-title' ),
						'font_size'   => astra_get_option( 'font-size-entry-title' ),
					);
					$settings['archive_title'] = array(
						'font_family' => astra_get_option( 'font-family-archive-summary-title' ),
						'font_size'   => astra_get_option( 'font-size-archive-summary-title' ),
					);
					break;

				case 'spacing':
					$settings = array(
						'site_content_padding' => astra_get_option( 'site-content-padding' ),
						'header_spacing'       => astra_get_option( 'header-spacing' ),
						'footer_spacing'       => astra_get_option( 'footer-sml-spacing' ),
						'sidebar_outside'      => astra_get_option( 'sidebar-outside-spacing' ),
						'sidebar_inside'       => astra_get_option( 'sidebar-inside-spacing' ),
						'blog_post_outside'    => astra_get_option( 'blog-post-outside-spacing' ),
						'blog_post_inside'     => astra_get_option( 'blog-post-inside-spacing' ),
					);
					break;

				case 'blog-pro':
					$settings = array(
						'blog_layout'         => astra_get_option( 'blog-layout' ),
						'blog_grid'           => astra_get_option( 'blog-grid' ),
						'blog_grid_layout'    => astra_get_option( 'blog-grid-layout' ),
						'blog_post_structure' => astra_get_option( 'blog-post-structure' ),
						'blog_post_content'   => astra_get_option( 'blog-post-content' ),
						'blog_meta'           => astra_get_option( 'blog-meta' ),
						'blog_excerpt_count'  => astra_get_option( 'blog-excerpt-count' ),
						'blog_pagination'     => astra_get_option( 'blog-pagination' ),
					);
					break;

				case 'sticky-header':
					$settings = array(
						'on_devices'     => astra_get_option( 'sticky-header-on-devices' ),
						'style'          => astra_get_option( 'sticky-header-style' ),
						'hide_on_scroll' => astra_get_option( 'sticky-hide-on-scroll' ),
						'above_stick'    => astra_get_option( 'header-above-stick' ),
						'main_stick'     => astra_get_option( 'header-main-stick' ),
						'below_stick'    => astra_get_option( 'header-below-stick' ),
						'bg_color'       => astra_get_option( 'sticky-header-bg-color-responsive' ),
						'color'          => astra_get_option( 'sticky-header-color-responsive' ),
					);
					break;

				case 'site-layouts':
					$settings = array(
						'site_layout'           => astra_get_option( 'site-layout' ),
						'site_layout_box_width' => astra_get_option( 'site-layout-box-width' ),
						'site_layout_padded_pad' => astra_get_option( 'site-layout-padded-pad' ),
					);
					break;

				case 'advanced-hooks':
					$settings = array(
						'description' => 'Custom Layouts settings are per-layout. Use astra/list-custom-layouts and astra/get-custom-layout.',
					);
					break;

				case 'nav-menu':
					$settings = array(
						'primary_menu_last_item' => astra_get_option( 'header-main-rt-section' ),
						'mobile_header_toggle'   => astra_get_option( 'mobile-header-toggle-btn-style' ),
					);
					break;

				case 'advanced-headers':
					$settings = array(
						'description' => 'Page Headers are per-post settings. Configured via the Page Headers CPT.',
					);
					break;

				default:
					$settings = array(
						'message' => 'No detailed settings mapping for module: ' . $module . '. Use astra/get-option with specific keys.',
					);
			}

			return array(
				'module'   => $module,
				'active'   => true,
				'settings' => $settings,
			);
		},
	));

	// ===== UPDATE PRO SETTINGS (P0 gap fill) =====

	$reg->write( 'astra/update-pro-settings', array(
		'label'       => 'Update Pro Settings',
		'description' => 'Batch update Astra Pro module settings. Provide the module slug and a settings object matching the structure from get-pro-settings. Only provided keys are updated.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'module', 'settings' ),
			'properties' => array(
				'module' => array(
					'type'        => 'string',
					'description' => 'Pro module slug: colors-and-background, typography, spacing, blog-pro, sticky-header, site-layouts, nav-menu',
				),
				'settings' => array(
					'type'        => 'object',
					'description' => 'Settings to update. Keys match the output of get-pro-settings for the given module.',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success'      => array( 'type' => 'boolean' ),
				'module'       => array( 'type' => 'string' ),
				'updated_keys' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
		),
		'callback' => function( $input ) {
			if ( ! defined( 'ASTRA_EXT_VER' ) ) {
				return new \WP_Error( 'astra_pro_required', 'This ability requires Astra Pro.' );
			}

			$module   = $input['module'];
			$settings = $input['settings'];
			$updated  = array();

			// Module → settings key mapping.
			$module_maps = array(
				'colors-and-background' => array(
					'h1_color'   => 'h1-color',
					'h2_color'   => 'h2-color',
					'h3_color'   => 'h3-color',
					'h4_color'   => 'h4-color',
					'h5_color'   => 'h5-color',
					'h6_color'   => 'h6-color',
					'content_bg' => 'content-bg-obj-responsive',
					'header_bg'  => 'hdr-bg-obj-responsive',
					'footer_bg'  => 'footer-bg-obj',
					'sidebar_bg' => 'sidebar-bg-obj',
				),
				'spacing' => array(
					'site_content_padding' => 'site-content-padding',
					'header_spacing'       => 'header-spacing',
					'footer_spacing'       => 'footer-sml-spacing',
					'sidebar_outside'      => 'sidebar-outside-spacing',
					'sidebar_inside'       => 'sidebar-inside-spacing',
					'blog_post_outside'    => 'blog-post-outside-spacing',
					'blog_post_inside'     => 'blog-post-inside-spacing',
				),
				'blog-pro' => array(
					'blog_layout'         => 'blog-layout',
					'blog_grid'           => 'blog-grid',
					'blog_grid_layout'    => 'blog-grid-layout',
					'blog_post_structure' => 'blog-post-structure',
					'blog_post_content'   => 'blog-post-content',
					'blog_meta'           => 'blog-meta',
					'blog_excerpt_count'  => 'blog-excerpt-count',
					'blog_pagination'     => 'blog-pagination',
				),
				'sticky-header' => array(
					'on_devices'     => 'sticky-header-on-devices',
					'style'          => 'sticky-header-style',
					'hide_on_scroll' => 'sticky-hide-on-scroll',
					'above_stick'    => 'header-above-stick',
					'main_stick'     => 'header-main-stick',
					'below_stick'    => 'header-below-stick',
					'bg_color'       => 'sticky-header-bg-color-responsive',
					'color'          => 'sticky-header-color-responsive',
				),
				'site-layouts' => array(
					'site_layout'            => 'site-layout',
					'site_layout_box_width'  => 'site-layout-box-width',
					'site_layout_padded_pad' => 'site-layout-padded-pad',
				),
				'nav-menu' => array(
					'primary_menu_last_item' => 'header-main-rt-section',
					'mobile_header_toggle'   => 'mobile-header-toggle-btn-style',
				),
			);

			// Typography is special — nested per heading level.
			if ( 'typography' === $module ) {
				$heading_props = array( 'font_family', 'font_weight', 'text_transform', 'line_height' );
				$prop_to_prefix = array(
					'font_family'    => 'font-family',
					'font_weight'    => 'font-weight',
					'text_transform' => 'text-transform',
					'line_height'    => 'line-height',
				);
				for ( $i = 1; $i <= 6; $i++ ) {
					$key = "h{$i}";
					if ( ! empty( $settings[ $key ] ) && is_array( $settings[ $key ] ) ) {
						foreach ( $heading_props as $prop ) {
							if ( isset( $settings[ $key ][ $prop ] ) ) {
								$option = $prop_to_prefix[ $prop ] . "-h{$i}";
								astra_update_option( $option, $settings[ $key ][ $prop ] );
								$updated[] = $option;
							}
						}
					}
				}
				if ( ! empty( $settings['entry_title'] ) && is_array( $settings['entry_title'] ) ) {
					if ( isset( $settings['entry_title']['font_family'] ) ) {
						astra_update_option( 'font-family-entry-title', $settings['entry_title']['font_family'] );
						$updated[] = 'font-family-entry-title';
					}
					if ( isset( $settings['entry_title']['font_size'] ) ) {
						astra_update_option( 'font-size-entry-title', $settings['entry_title']['font_size'] );
						$updated[] = 'font-size-entry-title';
					}
				}
				if ( ! empty( $settings['archive_title'] ) && is_array( $settings['archive_title'] ) ) {
					if ( isset( $settings['archive_title']['font_family'] ) ) {
						astra_update_option( 'font-family-archive-summary-title', $settings['archive_title']['font_family'] );
						$updated[] = 'font-family-archive-summary-title';
					}
					if ( isset( $settings['archive_title']['font_size'] ) ) {
						astra_update_option( 'font-size-archive-summary-title', $settings['archive_title']['font_size'] );
						$updated[] = 'font-size-archive-summary-title';
					}
				}
			} elseif ( isset( $module_maps[ $module ] ) ) {
				$map = $module_maps[ $module ];
				foreach ( $map as $setting_key => $option_key ) {
					if ( isset( $settings[ $setting_key ] ) ) {
						astra_update_option( $option_key, $settings[ $setting_key ] );
						$updated[] = $option_key;
					}
				}
			} else {
				$non_writable = array( 'advanced-hooks', 'advanced-headers' );
				if ( in_array( $module, $non_writable, true ) ) {
					return new \WP_Error(
						'not_writable',
						'Module "' . $module . '" settings are per-post, not global options. Use the Custom Layout CRUD abilities instead.'
					);
				}
				return new \WP_Error( 'unknown_module', 'Unknown or unsupported module: ' . $module );
			}

			if ( empty( $updated ) ) {
				return new \WP_Error( 'no_changes', 'No matching settings keys were found to update.' );
			}

			return array(
				'success'      => true,
				'module'       => $module,
				'updated_keys' => $updated,
			);
		},
	));

}, 100 );
