<?php
/**
 * Shared Helper Functions
 *
 * Helpers used across multiple Astra ability files.
 *
 * @package Astra_Abilities
 */

defined( 'ABSPATH' ) || exit;

/**
 * Check if Astra Pro is active.
 *
 * @return bool
 */
function astra_abilities_is_pro_active() {
	return defined( 'ASTRA_EXT_VER' );
}

/**
 * Get Astra Pro modules with active/inactive status.
 *
 * @return array Array of modules with 'slug', 'label', 'active' keys.
 */
function astra_abilities_get_pro_modules() {
	if ( ! astra_abilities_is_pro_active() ) {
		return array();
	}

	// Known module labels.
	$module_labels = array(
		'advanced-hooks'          => 'Custom Layouts',
		'blog-pro'                => 'Blog Pro',
		'colors-and-background'   => 'Colors & Background',
		'advanced-headers'        => 'Page Headers',
		'site-layouts'            => 'Site Layouts',
		'spacing'                 => 'Spacing',
		'sticky-header'           => 'Sticky Header',
		'typography'              => 'Typography',
		'nav-menu'                => 'Nav Menu',
		'header-sections'         => 'Header Sections',
		'above-header-section'    => 'Above Header',
		'below-header-section'    => 'Below Header',
		'scroll-to-top'           => 'Scroll to Top',
		'woocommerce'             => 'WooCommerce',
		'edd'                     => 'Easy Digital Downloads',
		'learndash'               => 'LearnDash',
		'lifterlms'               => 'LifterLMS',
	);

	// Get active addons.
	$active_addons = array();
	if ( class_exists( 'Astra_Ext_Extension' ) && method_exists( 'Astra_Ext_Extension', 'get_enabled_addons' ) ) {
		$active_addons = Astra_Ext_Extension::get_enabled_addons();
	} else {
		$addon_settings = get_option( 'astra-addon-settings', array() );
		if ( is_array( $addon_settings ) ) {
			foreach ( $addon_settings as $slug => $status ) {
				if ( $status ) {
					$active_addons[ $slug ] = true;
				}
			}
		}
	}

	$modules = array();
	foreach ( $module_labels as $slug => $label ) {
		$modules[] = array(
			'slug'   => $slug,
			'label'  => $label,
			'active' => isset( $active_addons[ $slug ] ),
		);
	}

	return $modules;
}

/**
 * Resolve the effective page layout by checking page meta first, then global defaults.
 *
 * @param int $post_id The post/page ID.
 * @return array Layout settings with 'value' and 'source' for each key.
 */
function astra_abilities_resolve_page_layout( $post_id ) {
	$layout = array();

	// Meta key → Astra global option key mapping.
	$meta_map = array(
		'sidebar'            => array( 'meta' => 'site-sidebar-layout',            'global' => 'site-sidebar-layout' ),
		'content_layout'     => array( 'meta' => 'site-content-layout',            'global' => 'site-content-layout' ),
		'content_style'      => array( 'meta' => 'site-content-style',             'global' => 'site-content-style' ),
		'header_display'     => array( 'meta' => 'ast-main-header-display',        'global' => null ),
		'title_display'      => array( 'meta' => 'site-post-title',                'global' => null ),
		'featured_image'     => array( 'meta' => 'ast-featured-img',               'global' => null ),
		'transparent_header' => array( 'meta' => 'theme-transparent-header-meta',  'global' => 'transparent-header-enable' ),
	);

	foreach ( $meta_map as $key => $map ) {
		$meta_value = get_post_meta( $post_id, $map['meta'], true );

		if ( ! empty( $meta_value ) && 'default' !== $meta_value ) {
			$layout[ $key ] = array(
				'value'  => $meta_value,
				'source' => 'page',
			);
		} elseif ( $map['global'] ) {
			$global_value = astra_get_option( $map['global'] );
			$layout[ $key ] = array(
				'value'  => $global_value ?: 'default',
				'source' => 'global',
			);
		} else {
			$layout[ $key ] = array(
				'value'  => 'default',
				'source' => 'global',
			);
		}
	}

	return $layout;
}

/**
 * Recursively deep-merge two associative arrays.
 *
 * Values from $override replace values in $base. Nested arrays are merged recursively.
 * Sequential (non-associative) arrays from $override replace $base entirely.
 *
 * @param array $base     Base array.
 * @param array $override Override array.
 * @return array Merged array.
 */
function astra_abilities_deep_merge( $base, $override ) {
	foreach ( $override as $key => $value ) {
		if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) ) {
			// Check if sequential array (list) vs associative.
			if ( array_values( $value ) === $value ) {
				$base[ $key ] = $value; // Replace sequential arrays entirely.
			} else {
				$base[ $key ] = astra_abilities_deep_merge( $base[ $key ], $value );
			}
		} else {
			$base[ $key ] = $value;
		}
	}
	return $base;
}

/**
 * Return valid page meta keys and their allowed values.
 *
 * @return array
 */
function astra_abilities_page_meta_keys() {
	return array(
		'sidebar' => array(
			'meta_key' => 'site-sidebar-layout',
			'values'   => array( 'default', 'left-sidebar', 'right-sidebar', 'no-sidebar' ),
		),
		'content_layout' => array(
			'meta_key' => 'site-content-layout',
			'values'   => array( 'default', 'boxed-container', 'content-boxed-container', 'plain-container', 'page-builder', 'narrow-container' ),
		),
		'content_style' => array(
			'meta_key' => 'site-content-style',
			'values'   => array( 'default', 'boxed', 'unboxed' ),
		),
		'disable_title' => array(
			'meta_key' => 'site-post-title',
			'values'   => array( 'default', 'disabled' ),
		),
		'disable_header' => array(
			'meta_key' => 'ast-main-header-display',
			'values'   => array( 'default', 'disabled' ),
		),
		'transparent_header' => array(
			'meta_key' => 'theme-transparent-header-meta',
			'values'   => array( 'default', 'enabled', 'disabled' ),
		),
		'disable_featured_image' => array(
			'meta_key' => 'ast-featured-img',
			'values'   => array( 'default', 'disabled' ),
		),
	);
}
