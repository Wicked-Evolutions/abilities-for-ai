<?php
/**
 * Astra Header & Footer Abilities
 *
 * Header builder and footer builder configuration — read and write.
 *
 * Abilities:
 *   - astra/get-header-config    (read)
 *   - astra/get-footer-config    (read)
 *   - astra/update-header-builder (write) — P0 gap fill
 *   - astra/update-footer-builder (write) — P0 gap fill
 *
 * @package Abilities_For_AI
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Abilities_For_AI_Registrar( 'astra', 'edit_posts' );

	// ===== GET HEADER CONFIG =====

	$reg->read( 'astra/get-header-config', array(
		'label'       => 'Get Header Config',
		'description' => 'Returns the Astra Header Builder configuration: desktop/mobile items, logo, site identity, transparent header settings, nav menus, and sticky header (if Pro active).',
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'desktop_items'      => array( 'type' => 'object' ),
				'mobile_items'       => array( 'type' => 'object' ),
				'logo'               => array( 'type' => 'object' ),
				'site_identity'      => array( 'type' => 'object' ),
				'transparent_header' => array( 'type' => 'object' ),
				'nav_menus'          => array( 'type' => 'object' ),
				'sticky_header'      => array( 'type' => 'object' ),
			),
		),
		'callback' => function( $input ) {
			$result = array();

			$result['desktop_items'] = astra_get_option( 'header-desktop-items' );
			$result['mobile_items']  = astra_get_option( 'header-mobile-items' );

			$custom_logo_id = get_theme_mod( 'custom_logo' );
			$logo_url       = $custom_logo_id ? wp_get_attachment_image_url( $custom_logo_id, 'full' ) : null;
			$result['logo'] = array(
				'id'         => $custom_logo_id ?: null,
				'url'        => $logo_url,
				'width'      => astra_get_option( 'ast-header-responsive-logo-width' ),
				'retina_url' => astra_get_option( 'ast-header-retina-logo' ) ?: null,
			);

			$result['site_identity'] = array(
				'site_title'   => get_bloginfo( 'name' ),
				'tagline'      => get_bloginfo( 'description' ),
				'show_title'   => astra_get_option( 'display-site-title-responsive' ),
				'show_tagline' => astra_get_option( 'display-site-tagline-responsive' ),
			);

			$result['transparent_header'] = array(
				'enabled'       => astra_get_option( 'transparent-header-enable' ),
				'on_archive'    => astra_get_option( 'transparent-header-disable-archive' ),
				'on_search'     => astra_get_option( 'transparent-header-disable-search' ),
				'on_blog_index' => astra_get_option( 'transparent-header-disable-index' ),
				'on_page'       => astra_get_option( 'transparent-header-disable-page' ),
				'on_post'       => astra_get_option( 'transparent-header-disable-posts' ),
				'on_404'        => astra_get_option( 'transparent-header-disable-404' ),
				'logo'          => astra_get_option( 'transparent-header-logo' ),
				'logo_width'    => astra_get_option( 'transparent-header-logo-width' ),
			);

			$nav_menus      = array();
			$menu_locations = get_nav_menu_locations();
			foreach ( $menu_locations as $location => $menu_id ) {
				$menu_obj = wp_get_nav_menu_object( $menu_id );
				$nav_menus[ $location ] = $menu_obj ? $menu_obj->name : null;
			}
			$result['nav_menus'] = $nav_menus;

			if ( astra_abilities_is_pro_active() ) {
				$result['sticky_header'] = array(
					'enabled'        => astra_get_option( 'sticky-header-on-devices' ),
					'style'          => astra_get_option( 'sticky-header-style' ),
					'hide_on_scroll' => astra_get_option( 'sticky-hide-on-scroll' ),
					'above_header'   => astra_get_option( 'header-above-stick' ),
					'primary_header' => astra_get_option( 'header-main-stick' ),
					'below_header'   => astra_get_option( 'header-below-stick' ),
				);
			} else {
				$result['sticky_header'] = array( 'available' => false, 'reason' => 'Astra Pro not active' );
			}

			return $result;
		},
	));

	// ===== GET FOOTER CONFIG =====

	$reg->read( 'astra/get-footer-config', array(
		'label'       => 'Get Footer Config',
		'description' => 'Returns the Astra Footer Builder configuration: desktop items (above/primary/below rows), copyright text, widget areas, and footer width.',
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'desktop_items' => array( 'type' => 'object' ),
				'copyright'     => array( 'type' => 'object' ),
				'widgets'       => array( 'type' => 'object' ),
				'layout'        => array( 'type' => 'object' ),
			),
		),
		'callback' => function( $input ) {
			$result = array();

			$result['desktop_items'] = astra_get_option( 'footer-desktop-items' );

			$result['copyright'] = array(
				'text'      => astra_get_option( 'footer-copyright-editor' ),
				'alignment' => astra_get_option( 'footer-copyright-alignment' ),
			);

			$widget_areas = array();
			for ( $i = 1; $i <= 4; $i++ ) {
				$sidebar_id = 'footer-widget-area-' . $i;
				$widgets    = wp_get_sidebars_widgets();
				$widget_areas[ 'area_' . $i ] = array(
					'id'           => $sidebar_id,
					'widget_count' => isset( $widgets[ $sidebar_id ] ) ? count( $widgets[ $sidebar_id ] ) : 0,
				);
			}
			$result['widgets'] = $widget_areas;

			$result['layout'] = array(
				'footer_layout' => astra_get_option( 'hb-footer-layout-width' ),
				'footer_width'  => astra_get_option( 'hb-footer-main-layout-width' ),
				'above_layout'  => astra_get_option( 'hb-footer-above-layout-width' ),
				'below_layout'  => astra_get_option( 'hb-footer-below-layout-width' ),
			);

			return $result;
		},
	));

	// ===== UPDATE HEADER BUILDER (P0 gap fill) =====

	$reg->write( 'astra/update-header-builder', array(
		'label'       => 'Update Header Builder',
		'description' => 'Update Astra Header Builder configuration. Can update desktop items, mobile items, transparent header settings, and logo width. Requires Astra Pro for sticky header settings.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'desktop_items' => array(
					'type'        => 'object',
					'description' => 'Header Builder desktop item grid. Same structure as returned by get-header-config desktop_items.',
				),
				'mobile_items' => array(
					'type'        => 'object',
					'description' => 'Header Builder mobile item grid. Same structure as returned by get-header-config mobile_items.',
				),
				'transparent_header' => array(
					'type'        => 'object',
					'description' => 'Transparent header settings. Keys: enabled (bool), on_archive, on_search, on_blog_index, on_page, on_post, on_404 (all bool), logo (URL string), logo_width (responsive object).',
				),
				'logo_width' => array(
					'type'        => 'object',
					'description' => 'Responsive logo width object: {desktop: int, tablet: int, mobile: int}.',
				),
				'sticky_header' => array(
					'type'        => 'object',
					'description' => 'Sticky header settings (requires Astra Pro). Keys: on_devices, style, hide_on_scroll, above_header, primary_header, below_header.',
				),
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
			$updated = array();

			if ( isset( $input['desktop_items'] ) ) {
				astra_update_option( 'header-desktop-items', $input['desktop_items'] );
				$updated[] = 'header-desktop-items';
			}

			if ( isset( $input['mobile_items'] ) ) {
				astra_update_option( 'header-mobile-items', $input['mobile_items'] );
				$updated[] = 'header-mobile-items';
			}

			if ( isset( $input['logo_width'] ) ) {
				astra_update_option( 'ast-header-responsive-logo-width', $input['logo_width'] );
				$updated[] = 'ast-header-responsive-logo-width';
			}

			if ( ! empty( $input['transparent_header'] ) ) {
				$th = $input['transparent_header'];
				$th_map = array(
					'enabled'       => 'transparent-header-enable',
					'on_archive'    => 'transparent-header-disable-archive',
					'on_search'     => 'transparent-header-disable-search',
					'on_blog_index' => 'transparent-header-disable-index',
					'on_page'       => 'transparent-header-disable-page',
					'on_post'       => 'transparent-header-disable-posts',
					'on_404'        => 'transparent-header-disable-404',
					'logo'          => 'transparent-header-logo',
					'logo_width'    => 'transparent-header-logo-width',
				);
				foreach ( $th_map as $key => $option ) {
					if ( isset( $th[ $key ] ) ) {
						astra_update_option( $option, $th[ $key ] );
						$updated[] = $option;
					}
				}
			}

			if ( ! empty( $input['sticky_header'] ) ) {
				if ( ! defined( 'ASTRA_EXT_VER' ) ) {
					return new \WP_Error( 'astra_pro_required', 'Sticky header settings require Astra Pro.' );
				}
				$sh = $input['sticky_header'];
				$sh_map = array(
					'on_devices'     => 'sticky-header-on-devices',
					'style'          => 'sticky-header-style',
					'hide_on_scroll' => 'sticky-hide-on-scroll',
					'above_header'   => 'header-above-stick',
					'primary_header' => 'header-main-stick',
					'below_header'   => 'header-below-stick',
				);
				foreach ( $sh_map as $key => $option ) {
					if ( isset( $sh[ $key ] ) ) {
						astra_update_option( $option, $sh[ $key ] );
						$updated[] = $option;
					}
				}
			}

			if ( empty( $updated ) ) {
				return new \WP_Error( 'no_fields', 'No header fields were provided to update.' );
			}

			return array(
				'success'      => true,
				'updated_keys' => $updated,
			);
		},
	));

	// ===== UPDATE FOOTER BUILDER (P0 gap fill) =====

	$reg->write( 'astra/update-footer-builder', array(
		'label'       => 'Update Footer Builder',
		'description' => 'Update Astra Footer Builder configuration. Can update desktop items, copyright text/alignment, and footer layout widths.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'desktop_items' => array(
					'type'        => 'object',
					'description' => 'Footer Builder desktop item grid. Same structure as returned by get-footer-config desktop_items.',
				),
				'copyright_text' => array(
					'type'        => 'string',
					'description' => 'Copyright text (supports HTML and Astra shortcodes like [copyright] [current_year] [site_title]).',
				),
				'copyright_alignment' => array(
					'type'        => 'string',
					'description' => 'Copyright alignment: left, center, right.',
				),
				'footer_layout' => array(
					'type'        => 'string',
					'description' => 'Footer primary row layout width: full, content.',
				),
				'footer_width' => array(
					'type'        => 'string',
					'description' => 'Footer main layout width: full, content.',
				),
				'above_layout' => array(
					'type'        => 'string',
					'description' => 'Footer above row layout width: full, content.',
				),
				'below_layout' => array(
					'type'        => 'string',
					'description' => 'Footer below row layout width: full, content.',
				),
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
			$updated = array();

			if ( isset( $input['desktop_items'] ) ) {
				astra_update_option( 'footer-desktop-items', $input['desktop_items'] );
				$updated[] = 'footer-desktop-items';
			}

			if ( isset( $input['copyright_text'] ) ) {
				astra_update_option( 'footer-copyright-editor', $input['copyright_text'] );
				$updated[] = 'footer-copyright-editor';
			}

			if ( isset( $input['copyright_alignment'] ) ) {
				astra_update_option( 'footer-copyright-alignment', $input['copyright_alignment'] );
				$updated[] = 'footer-copyright-alignment';
			}

			$layout_map = array(
				'footer_layout' => 'hb-footer-layout-width',
				'footer_width'  => 'hb-footer-main-layout-width',
				'above_layout'  => 'hb-footer-above-layout-width',
				'below_layout'  => 'hb-footer-below-layout-width',
			);
			foreach ( $layout_map as $key => $option ) {
				if ( isset( $input[ $key ] ) ) {
					astra_update_option( $option, $input[ $key ] );
					$updated[] = $option;
				}
			}

			if ( empty( $updated ) ) {
				return new \WP_Error( 'no_fields', 'No footer fields were provided to update.' );
			}

			return array(
				'success'      => true,
				'updated_keys' => $updated,
			);
		},
	));

}, 100 );
