<?php
/**
 * Shared Helper Functions
 *
 * Pagination, sanitization, and shared utilities used across all modules.
 *
 * @package WordPress_Native_Abilities
 */

defined( 'ABSPATH' ) || exit;

/**
 * Paginate results with standard parameters.
 *
 * @param array $input          Input with optional 'page' and 'per_page'.
 * @param int   $default_per_page Default items per page.
 * @return array [ 'page' => int, 'per_page' => int, 'offset' => int ]
 */
function wp_native_pagination( $input, $default_per_page = 20 ) {
	$page     = max( 1, intval( $input['page'] ?? 1 ) );
	$per_page = min( 100, max( 1, intval( $input['per_page'] ?? $default_per_page ) ) );
	$offset   = ( $page - 1 ) * $per_page;

	return array(
		'page'     => $page,
		'per_page' => $per_page,
		'offset'   => $offset,
	);
}

/**
 * Standard pagination input schema properties.
 *
 * @return array Schema properties for page and per_page.
 */
function wp_native_pagination_schema() {
	return array(
		'page' => array(
			'type'        => 'integer',
			'description' => 'Page number (default: 1)',
			'default'     => 1,
		),
		'per_page' => array(
			'type'        => 'integer',
			'description' => 'Items per page, max 100 (default: 20)',
			'default'     => 20,
		),
	);
}

/**
 * Format a standard error response.
 *
 * @param string $code    Error code.
 * @param string $message Error message.
 * @return WP_Error
 */
function wp_native_error( $code, $message ) {
	return new WP_Error( $code, $message );
}

// ============================================================
// Menu helpers (absorbed from menu-abilities v1.0.0)
// ============================================================

/**
 * Build a hierarchical tree from a flat list of menu items.
 *
 * @param array $items Flat array of WP_Post menu item objects.
 * @return array Nested tree with 'children' arrays.
 */
function menu_abilities_build_tree( $items ) {
	$by_id = array();
	$tree  = array();

	foreach ( $items as $item ) {
		$by_id[ $item->ID ] = array(
			'id'          => (int) $item->ID,
			'title'       => $item->title,
			'url'         => $item->url,
			'type'        => $item->type,
			'object'      => $item->object,
			'object_id'   => (int) $item->object_id,
			'parent'      => (int) $item->menu_item_parent,
			'position'    => (int) $item->menu_order,
			'classes'     => array_filter( (array) $item->classes ),
			'target'      => $item->target,
			'attr_title'  => $item->attr_title,
			'description' => $item->description,
			'children'    => array(),
		);
	}

	foreach ( $by_id as $id => &$entry ) {
		$parent_id = $entry['parent'];
		if ( $parent_id && isset( $by_id[ $parent_id ] ) ) {
			$by_id[ $parent_id ]['children'][] = &$entry;
		} else {
			$tree[] = &$entry;
		}
	}
	unset( $entry );

	return $tree;
}

/**
 * Format a single menu item for API output.
 *
 * @param WP_Post $item Menu item post object.
 * @return array Formatted item data.
 */
function menu_abilities_format_item( $item ) {
	return array(
		'id'          => (int) $item->ID,
		'title'       => $item->title,
		'url'         => $item->url,
		'type'        => $item->type,
		'object'      => $item->object,
		'object_id'   => (int) $item->object_id,
		'parent'      => (int) $item->menu_item_parent,
		'position'    => (int) $item->menu_order,
		'classes'     => array_filter( (array) $item->classes ),
		'target'      => $item->target,
		'attr_title'  => $item->attr_title,
		'description' => $item->description,
	);
}

/**
 * Get all menu location assignments.
 *
 * @return array Associative array of location slug => menu_id.
 */
function menu_abilities_get_location_assignments() {
	return get_nav_menu_locations();
}
