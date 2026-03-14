<?php
/**
 * Astra Breadcrumb, Scroll to Top & Performance Abilities
 *
 * Theme-level configuration for breadcrumbs, scroll-to-top button,
 * and performance/font-loading settings.
 *
 * Abilities:
 *   - astra/get-breadcrumb-config    (read)  — P1
 *   - astra/update-breadcrumb-config (write) — P1
 *   - astra/get-scroll-to-top        (read)  — P2 (Pro)
 *   - astra/update-scroll-to-top     (write) — P2 (Pro)
 *   - astra/get-performance-settings (read)  — P2
 *
 * @package Abilities_For_AI
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Abilities_For_AI_Registrar( 'astra', 'edit_posts' );

	// ===== GET BREADCRUMB CONFIG =====

	$reg->read( 'astra/get-breadcrumb-config', array(
		'label'       => 'Get Breadcrumb Config',
		'description' => 'Returns breadcrumb display settings: position, source, separator, and per-page-type visibility.',
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'position'   => array( 'type' => 'string' ),
				'source'     => array( 'type' => 'string' ),
				'separator'  => array( 'type' => 'string' ),
				'visibility' => array( 'type' => 'object' ),
			),
		),
		'callback' => function( $input ) {
			return array(
				'position'  => astra_get_option( 'breadcrumb-position' ),
				'source'    => astra_get_option( 'select-breadcrumb-source' ),
				'separator' => astra_get_option( 'breadcrumb-separator' ),
				'visibility' => array(
					'on_home'       => astra_get_option( 'breadcrumb-on-home-page' ),
					'on_blog'       => astra_get_option( 'breadcrumb-on-blog-page' ),
					'on_archive'    => astra_get_option( 'breadcrumb-on-archive-page' ),
					'on_single'     => astra_get_option( 'breadcrumb-on-single-page' ),
					'on_page'       => astra_get_option( 'breadcrumb-on-page' ),
					'on_search'     => astra_get_option( 'breadcrumb-on-search-page' ),
					'on_404'        => astra_get_option( 'breadcrumb-on-404-page' ),
				),
				'typography' => array(
					'font_size'   => astra_get_option( 'breadcrumb-font-size' ),
					'font_family' => astra_get_option( 'breadcrumb-font-family' ),
					'font_weight' => astra_get_option( 'breadcrumb-font-weight' ),
					'line_height' => astra_get_option( 'breadcrumb-line-height' ),
				),
				'colors' => array(
					'text_color'       => astra_get_option( 'breadcrumb-active-color-responsive' ),
					'link_color'       => astra_get_option( 'breadcrumb-text-color-responsive' ),
					'link_hover_color' => astra_get_option( 'breadcrumb-hover-color-responsive' ),
					'separator_color'  => astra_get_option( 'breadcrumb-separator-color' ),
					'background'       => astra_get_option( 'breadcrumb-bg-color' ),
				),
			);
		},
	));

	// ===== UPDATE BREADCRUMB CONFIG =====

	$reg_admin = new Abilities_For_AI_Registrar( 'astra', 'manage_options' );

	$reg_admin->write( 'astra/update-breadcrumb-config', array(
		'label'       => 'Update Breadcrumb Config',
		'description' => 'Update breadcrumb display settings. Only provided keys are changed.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'position'       => array( 'type' => 'string', 'description' => 'Position: none, astra_header_after, astra_entry_top, or inside title area' ),
				'source'         => array( 'type' => 'string', 'description' => 'Source: default, yoast-seo-breadcrumbs, breadcrumb-navxt, rank-math' ),
				'separator'      => array( 'type' => 'string', 'description' => 'Separator character (e.g., ">", "/", "»")' ),
				'on_home'        => array( 'type' => 'boolean', 'description' => 'Show on homepage' ),
				'on_blog'        => array( 'type' => 'boolean', 'description' => 'Show on blog page' ),
				'on_archive'     => array( 'type' => 'boolean', 'description' => 'Show on archive pages' ),
				'on_single'      => array( 'type' => 'boolean', 'description' => 'Show on single posts' ),
				'on_page'        => array( 'type' => 'boolean', 'description' => 'Show on pages' ),
				'on_search'      => array( 'type' => 'boolean', 'description' => 'Show on search results' ),
				'on_404'         => array( 'type' => 'boolean', 'description' => 'Show on 404 page' ),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success'      => array( 'type' => 'boolean' ),
				'updated_keys' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
		),
		'callback' => function( $input ) {
			$map = array(
				'position'   => 'breadcrumb-position',
				'source'     => 'select-breadcrumb-source',
				'separator'  => 'breadcrumb-separator',
				'on_home'    => 'breadcrumb-on-home-page',
				'on_blog'    => 'breadcrumb-on-blog-page',
				'on_archive' => 'breadcrumb-on-archive-page',
				'on_single'  => 'breadcrumb-on-single-page',
				'on_page'    => 'breadcrumb-on-page',
				'on_search'  => 'breadcrumb-on-search-page',
				'on_404'     => 'breadcrumb-on-404-page',
			);

			$updated = array();
			foreach ( $map as $key => $option ) {
				if ( isset( $input[ $key ] ) ) {
					astra_update_option( $option, $input[ $key ] );
					$updated[] = $option;
				}
			}

			if ( empty( $updated ) ) {
				return new \WP_Error( 'no_changes', 'No breadcrumb settings provided to update.' );
			}

			return array(
				'success'      => true,
				'updated_keys' => $updated,
			);
		},
	));

	// ===== GET SCROLL TO TOP =====

	$reg->read( 'astra/get-scroll-to-top', array(
		'label'       => 'Get Scroll to Top',
		'description' => 'Returns scroll-to-top button settings: enable, position, icon, size, colors. Requires Astra Pro scroll-to-top module.',
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'available' => array( 'type' => 'boolean' ),
				'settings'  => array( 'type' => 'object' ),
			),
		),
		'callback' => function( $input ) {
			if ( ! astra_abilities_is_pro_active() ) {
				return array(
					'available' => false,
					'message'   => 'Scroll to Top requires Astra Pro.',
					'settings'  => array(),
				);
			}

			return array(
				'available' => true,
				'settings'  => array(
					'enabled'        => astra_get_option( 'scroll-to-top-enable' ),
					'on_devices'     => astra_get_option( 'scroll-to-top-on-devices' ),
					'position'       => astra_get_option( 'scroll-to-top-position' ),
					'icon_size'      => astra_get_option( 'scroll-to-top-icon-size' ),
					'icon_radius'    => astra_get_option( 'scroll-to-top-icon-radius' ),
					'icon_color'     => astra_get_option( 'scroll-to-top-icon-color' ),
					'icon_h_color'   => astra_get_option( 'scroll-to-top-icon-h-color' ),
					'icon_bg_color'  => astra_get_option( 'scroll-to-top-icon-bg-color' ),
					'icon_h_bg_color' => astra_get_option( 'scroll-to-top-icon-h-bg-color' ),
					'border_size'    => astra_get_option( 'scroll-to-top-border-size' ),
					'border_color'   => astra_get_option( 'scroll-to-top-border-color' ),
					'border_h_color' => astra_get_option( 'scroll-to-top-border-h-color' ),
				),
			);
		},
	));

	// ===== UPDATE SCROLL TO TOP =====

	$reg_admin->write( 'astra/update-scroll-to-top', array(
		'label'       => 'Update Scroll to Top',
		'description' => 'Update scroll-to-top button settings. Requires Astra Pro. Only provided keys are changed.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'enabled'          => array( 'type' => 'boolean', 'description' => 'Enable/disable scroll-to-top button' ),
				'on_devices'       => array( 'type' => 'string', 'description' => 'Show on: both, desktop, mobile' ),
				'position'         => array( 'type' => 'string', 'description' => 'Position: left or right' ),
				'icon_size'        => array( 'type' => 'integer', 'description' => 'Icon size in pixels' ),
				'icon_radius'      => array( 'type' => 'integer', 'description' => 'Border radius in pixels' ),
				'icon_color'       => array( 'type' => 'string', 'description' => 'Icon color (hex)' ),
				'icon_h_color'     => array( 'type' => 'string', 'description' => 'Icon hover color (hex)' ),
				'icon_bg_color'    => array( 'type' => 'string', 'description' => 'Background color (hex)' ),
				'icon_h_bg_color'  => array( 'type' => 'string', 'description' => 'Background hover color (hex)' ),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success'      => array( 'type' => 'boolean' ),
				'updated_keys' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
		),
		'callback' => function( $input ) {
			if ( ! defined( 'ASTRA_EXT_VER' ) ) {
				return new \WP_Error( 'astra_pro_required', 'Scroll to Top requires Astra Pro.' );
			}

			$map = array(
				'enabled'        => 'scroll-to-top-enable',
				'on_devices'     => 'scroll-to-top-on-devices',
				'position'       => 'scroll-to-top-position',
				'icon_size'      => 'scroll-to-top-icon-size',
				'icon_radius'    => 'scroll-to-top-icon-radius',
				'icon_color'     => 'scroll-to-top-icon-color',
				'icon_h_color'   => 'scroll-to-top-icon-h-color',
				'icon_bg_color'  => 'scroll-to-top-icon-bg-color',
				'icon_h_bg_color' => 'scroll-to-top-icon-h-bg-color',
			);

			$updated = array();
			foreach ( $map as $key => $option ) {
				if ( isset( $input[ $key ] ) ) {
					astra_update_option( $option, $input[ $key ] );
					$updated[] = $option;
				}
			}

			if ( empty( $updated ) ) {
				return new \WP_Error( 'no_changes', 'No scroll-to-top settings provided to update.' );
			}

			return array(
				'success'      => true,
				'updated_keys' => $updated,
			);
		},
	));

	// ===== GET PERFORMANCE SETTINGS =====

	$reg->read( 'astra/get-performance-settings', array(
		'label'       => 'Get Performance Settings',
		'description' => 'Returns Astra performance settings: Google Fonts loading strategy, file generation, and preloading.',
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'fonts'       => array( 'type' => 'object' ),
				'assets'      => array( 'type' => 'object' ),
			),
		),
		'callback' => function( $input ) {
			return array(
				'fonts' => array(
					'load_google_fonts_locally' => astra_get_option( 'load-google-fonts-locally' ),
					'preload_local_fonts'       => astra_get_option( 'preload-local-fonts' ),
					'self_hosted_gfonts'        => astra_get_option( 'self-host-gfonts' ),
				),
				'assets' => array(
					'file_generation'   => astra_get_option( 'file-generation' ),
					'load_unminified'   => astra_get_option( 'ast-auto-load-unminified' ),
					'enqueue_theme_js'  => astra_get_option( 'enqueue-theme-js' ),
				),
				'version' => array(
					'theme_version' => defined( 'ASTRA_THEME_VERSION' ) ? ASTRA_THEME_VERSION : 'unknown',
					'pro_version'   => defined( 'ASTRA_EXT_VER' ) ? ASTRA_EXT_VER : null,
				),
			);
		},
	));

}, 100 );
