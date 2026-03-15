<?php
/**
 * Menu Abilities
 *
 * WordPress navigation menu management — menus, items, and location assignments.
 *
 * 12 abilities in the 'menus' category:
 *   - menus/list-menus          (readonly)
 *   - menus/get-menu            (readonly)
 *   - menus/create-menu         (write)
 *   - menus/delete-menu         (destructive)
 *   - menus/list-menu-items     (readonly)
 *   - menus/add-menu-item       (write)
 *   - menus/update-menu-item    (write)
 *   - menus/delete-menu-item    (destructive)
 *   - menus/reorder-menu-items  (write)
 *   - menus/list-locations      (readonly)
 *   - menus/assign-location     (write)
 *   - menus/unassign-location   (write)
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

// ===== HELPER FUNCTIONS =====

/**
 * Build a hierarchical tree from a flat list of menu items.
 */
function abilities_for_ai_menu_build_tree( $items ) {
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
 */
function abilities_for_ai_menu_format_item( $item ) {
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

// ===== ABILITIES =====

add_action( 'wp_abilities_api_init', function() {
	$reg = new Abilities_For_AI_Registrar( 'menus', 'edit_theme_options' );

	// ===== MENUS — READ =====

	$reg->read( 'menus/list-menus', array(
		'label'       => 'List Menus',
		'description' => 'List all navigation menus with item counts and assigned theme locations.',
		'output_schema' => abilities_for_ai_schema_collection_output( 'menus', array(
			'id'        => array( 'type' => 'integer' ),
			'name'      => array( 'type' => 'string' ),
			'slug'      => array( 'type' => 'string' ),
			'count'     => array( 'type' => 'integer' ),
			'locations' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
		) ),
		'callback' => function( $input ) {
			$menus     = wp_get_nav_menus();
			$locations = get_nav_menu_locations();

			$menu_locations = array();
			foreach ( $locations as $loc_slug => $menu_id ) {
				if ( $menu_id ) {
					$menu_locations[ $menu_id ][] = $loc_slug;
				}
			}

			$result = array();
			foreach ( $menus as $menu ) {
				$result[] = array(
					'id'        => (int) $menu->term_id,
					'name'      => $menu->name,
					'slug'      => $menu->slug,
					'count'     => (int) $menu->count,
					'locations' => $menu_locations[ $menu->term_id ] ?? array(),
				);
			}

			return array( 'menus' => $result );
		},
	) );

	$reg->read( 'menus/get-menu', array(
		'label'       => 'Get Menu',
		'description' => 'Get a single menu with its full hierarchical item tree.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'menu_id' ),
			'properties' => array(
				'menu_id' => array( 'type' => 'integer', 'description' => 'The menu term ID.' ),
			),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'id'    => array( 'type' => 'integer' ),
			'name'  => array( 'type' => 'string' ),
			'slug'  => array( 'type' => 'string' ),
			'items' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) {
			$menu_id = (int) $input['menu_id'];
			$menu    = wp_get_nav_menu_object( $menu_id );
			if ( ! $menu ) {
				return new WP_Error( 'not_found', "Menu {$menu_id} not found." );
			}
			$items = wp_get_nav_menu_items( $menu_id );
			$tree  = $items ? abilities_for_ai_menu_build_tree( $items ) : array();
			return array( 'id' => (int) $menu->term_id, 'name' => $menu->name, 'slug' => $menu->slug, 'items' => abilities_for_ai_safe_value( $tree ) );
		},
	) );

	$reg->read( 'menus/list-menu-items', array(
		'label'       => 'List Menu Items',
		'description' => 'List all items in a menu as a flat list with parent IDs and positions.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'menu_id' ),
			'properties' => array(
				'menu_id' => array( 'type' => 'integer', 'description' => 'The menu term ID.' ),
			),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'items' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) {
			$menu_id = (int) $input['menu_id'];
			$menu    = wp_get_nav_menu_object( $menu_id );
			if ( ! $menu ) {
				return new WP_Error( 'not_found', "Menu {$menu_id} not found." );
			}
			$items  = wp_get_nav_menu_items( $menu_id );
			$result = array();
			if ( $items ) {
				foreach ( $items as $item ) {
					$result[] = abilities_for_ai_menu_format_item( $item );
				}
			}
			return array( 'items' => $result );
		},
	) );

	$reg->read( 'menus/list-locations', array(
		'label'       => 'List Menu Locations',
		'description' => 'List all registered theme menu locations with their current menu assignments.',
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'locations' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) {
			$registered = get_registered_nav_menus();
			$assigned   = get_nav_menu_locations();
			$result     = array();
			foreach ( $registered as $slug => $name ) {
				$menu_id   = $assigned[ $slug ] ?? 0;
				$menu_name = '';
				if ( $menu_id ) {
					$menu_obj  = wp_get_nav_menu_object( $menu_id );
					$menu_name = $menu_obj ? $menu_obj->name : '';
				}
				$result[] = array( 'slug' => $slug, 'name' => $name, 'menu_id' => (int) $menu_id, 'menu_name' => $menu_name );
			}
			return array( 'locations' => $result );
		},
	) );

	// ===== MENUS — WRITE =====

	$reg->write( 'menus/create-menu', array(
		'label'       => 'Create Menu',
		'description' => 'Create a new empty navigation menu. Optionally assign it to a theme location.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'name' ),
			'properties' => array(
				'name'     => array( 'type' => 'string', 'description' => 'The menu name.' ),
				'location' => array( 'type' => 'string', 'description' => 'Optional theme location slug to assign.' ),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'menu' => array( 'type' => 'object' ),
		) ),
		'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
		'callback' => function( $input ) {
			$name    = sanitize_text_field( $input['name'] );
			$menu_id = wp_create_nav_menu( $name );
			if ( is_wp_error( $menu_id ) ) {
				return $menu_id;
			}
			if ( ! empty( $input['location'] ) ) {
				$locations = get_nav_menu_locations();
				$locations[ sanitize_key( $input['location'] ) ] = $menu_id;
				set_theme_mod( 'nav_menu_locations', $locations );
			}
			$menu = wp_get_nav_menu_object( $menu_id );
			return array( 'success' => true, 'menu' => array( 'id' => (int) $menu->term_id, 'name' => $menu->name, 'slug' => $menu->slug ) );
		},
	) );

	$reg->write( 'menus/add-menu-item', array(
		'label'       => 'Add Menu Item',
		'description' => 'Add an item to a navigation menu. For "custom", provide title and url. For "page"/"post"/"category"/"tag", provide object_id.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'menu_id', 'title' ),
			'properties' => array(
				'menu_id'   => array( 'type' => 'integer', 'description' => 'The menu term ID.' ),
				'title'     => array( 'type' => 'string', 'description' => 'Display title.' ),
				'url'       => array( 'type' => 'string', 'description' => 'URL for custom links.' ),
				'type'      => array( 'type' => 'string', 'description' => 'Item type: custom, page, post, category, tag.', 'default' => 'custom', 'enum' => array( 'custom', 'page', 'post', 'category', 'tag' ) ),
				'object_id' => array( 'type' => 'integer', 'description' => 'WordPress object ID for page/post/category/tag.' ),
				'parent'    => array( 'type' => 'integer', 'description' => 'Parent menu item ID. 0 = top level.', 'default' => 0 ),
				'position'  => array( 'type' => 'integer', 'description' => 'Menu order position.' ),
				'classes'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'CSS classes.' ),
				'target'    => array( 'type' => 'string', 'description' => 'Link target: "" or "_blank".', 'default' => '' ),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'item' => array( 'type' => 'object' ),
		) ),
		'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
		'callback' => function( $input ) {
			$menu_id = (int) $input['menu_id'];
			$menu    = wp_get_nav_menu_object( $menu_id );
			if ( ! $menu ) {
				return new WP_Error( 'not_found', "Menu {$menu_id} not found." );
			}

			$type      = $input['type'] ?? 'custom';
			$object_id = (int) ( $input['object_id'] ?? 0 );
			$url       = $input['url'] ?? '';
			$title     = sanitize_text_field( $input['title'] );
			$object    = '';

			switch ( $type ) {
				case 'page':
					$object = 'page'; $type = 'post_type';
					if ( $object_id && ! $url ) { $url = get_permalink( $object_id ); }
					break;
				case 'post':
					$object = 'post'; $type = 'post_type';
					if ( $object_id && ! $url ) { $url = get_permalink( $object_id ); }
					break;
				case 'category':
					$object = 'category'; $type = 'taxonomy';
					if ( $object_id && ! $url ) { $url = get_term_link( $object_id, 'category' ); if ( is_wp_error( $url ) ) { $url = ''; } }
					break;
				case 'tag':
					$object = 'post_tag'; $type = 'taxonomy';
					if ( $object_id && ! $url ) { $url = get_term_link( $object_id, 'post_tag' ); if ( is_wp_error( $url ) ) { $url = ''; } }
					break;
				default:
					$type = 'custom'; $object = 'custom';
					break;
			}

			$position = 0;
			if ( isset( $input['position'] ) ) {
				$position = (int) $input['position'];
			} else {
				$existing = wp_get_nav_menu_items( $menu_id );
				$position = $existing ? count( $existing ) + 1 : 1;
			}

			$item_data = array(
				'menu-item-title'     => $title,
				'menu-item-url'       => esc_url_raw( $url ),
				'menu-item-type'      => $type,
				'menu-item-object'    => $object,
				'menu-item-object-id' => $object_id,
				'menu-item-parent-id' => (int) ( $input['parent'] ?? 0 ),
				'menu-item-position'  => $position,
				'menu-item-target'    => sanitize_text_field( $input['target'] ?? '' ),
				'menu-item-classes'   => ! empty( $input['classes'] ) ? implode( ' ', array_map( 'sanitize_html_class', $input['classes'] ) ) : '',
				'menu-item-status'    => 'publish',
			);

			$item_id = wp_update_nav_menu_item( $menu_id, 0, $item_data );
			if ( is_wp_error( $item_id ) ) {
				return $item_id;
			}

			return array( 'success' => true, 'item' => array( 'id' => $item_id, 'title' => $title, 'url' => $url, 'position' => $position ) );
		},
	) );

	$reg->write( 'menus/update-menu-item', array(
		'label'       => 'Update Menu Item',
		'description' => 'Update properties of an existing menu item. Only provided fields are changed.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'item_id' ),
			'properties' => array(
				'item_id'    => array( 'type' => 'integer', 'description' => 'The menu item post ID.' ),
				'title'      => array( 'type' => 'string', 'description' => 'New display title.' ),
				'url'        => array( 'type' => 'string', 'description' => 'New URL.' ),
				'position'   => array( 'type' => 'integer', 'description' => 'New menu order position.' ),
				'parent'     => array( 'type' => 'integer', 'description' => 'New parent item ID. 0 = top level.' ),
				'classes'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'New CSS classes (replaces existing).' ),
				'target'     => array( 'type' => 'string', 'description' => 'Link target: "" or "_blank".' ),
				'attr_title' => array( 'type' => 'string', 'description' => 'Title attribute (tooltip).' ),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'item' => array( 'type' => 'object' ),
		) ),
		'callback' => function( $input ) {
			$item_id = (int) $input['item_id'];
			$post    = get_post( $item_id );
			if ( ! $post || 'nav_menu_item' !== $post->post_type ) {
				return new WP_Error( 'not_found', "Menu item {$item_id} not found." );
			}

			$current = wp_setup_nav_menu_item( $post );
			$menus   = wp_get_object_terms( $item_id, 'nav_menu' );
			if ( empty( $menus ) || is_wp_error( $menus ) ) {
				return new WP_Error( 'not_found', "Menu item {$item_id} is not assigned to any menu." );
			}
			$menu_id = $menus[0]->term_id;

			$item_data = array(
				'menu-item-title'      => isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : $current->title,
				'menu-item-url'        => isset( $input['url'] ) ? esc_url_raw( $input['url'] ) : $current->url,
				'menu-item-type'       => $current->type,
				'menu-item-object'     => $current->object,
				'menu-item-object-id'  => $current->object_id,
				'menu-item-parent-id'  => isset( $input['parent'] ) ? (int) $input['parent'] : (int) $current->menu_item_parent,
				'menu-item-position'   => isset( $input['position'] ) ? (int) $input['position'] : (int) $current->menu_order,
				'menu-item-target'     => isset( $input['target'] ) ? sanitize_text_field( $input['target'] ) : $current->target,
				'menu-item-attr-title' => isset( $input['attr_title'] ) ? sanitize_text_field( $input['attr_title'] ) : $current->attr_title,
				'menu-item-classes'    => isset( $input['classes'] ) ? implode( ' ', array_map( 'sanitize_html_class', $input['classes'] ) ) : implode( ' ', array_filter( (array) $current->classes ) ),
				'menu-item-status'     => 'publish',
			);

			$result = wp_update_nav_menu_item( $menu_id, $item_id, $item_data );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$updated = wp_setup_nav_menu_item( get_post( $item_id ) );
			return array( 'success' => true, 'item' => array( 'id' => (int) $updated->ID, 'title' => $updated->title, 'url' => $updated->url, 'position' => (int) $updated->menu_order, 'parent' => (int) $updated->menu_item_parent ) );
		},
	) );

	$reg->write( 'menus/reorder-menu-items', array(
		'label'       => 'Reorder Menu Items',
		'description' => 'Reorder items within a menu by providing an array of item IDs in the desired order.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'menu_id', 'item_order' ),
			'properties' => array(
				'menu_id'    => array( 'type' => 'integer', 'description' => 'The menu term ID.' ),
				'item_order' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'description' => 'Array of menu item IDs in desired order.' ),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'items' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) {
			$menu_id    = (int) $input['menu_id'];
			$item_order = $input['item_order'];
			$menu       = wp_get_nav_menu_object( $menu_id );
			if ( ! $menu ) {
				return new WP_Error( 'not_found', "Menu {$menu_id} not found." );
			}
			if ( ! is_array( $item_order ) || empty( $item_order ) ) {
				return new WP_Error( 'ability_invalid_input', 'item_order must be a non-empty array of item IDs.' );
			}

			$existing_items = wp_get_nav_menu_items( $menu_id );
			$valid_ids      = array();
			if ( $existing_items ) {
				foreach ( $existing_items as $existing_item ) {
					$valid_ids[ (int) $existing_item->ID ] = true;
				}
			}

			$result = array();
			foreach ( $item_order as $position => $item_id ) {
				$item_id = (int) $item_id;
				if ( ! isset( $valid_ids[ $item_id ] ) ) {
					continue;
				}
				$order = $position + 1;
				wp_update_post( array( 'ID' => $item_id, 'menu_order' => $order ) );
				$result[] = array( 'id' => $item_id, 'position' => $order );
			}
			return array( 'success' => true, 'items' => $result );
		},
	) );

	$reg->write( 'menus/assign-location', array(
		'label'       => 'Assign Menu to Location',
		'description' => 'Assign a menu to a theme location. Replaces any previous assignment.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'location', 'menu_id' ),
			'properties' => array(
				'location' => array( 'type' => 'string', 'description' => 'Theme location slug.' ),
				'menu_id'  => array( 'type' => 'integer', 'description' => 'The menu term ID to assign.' ),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'location'  => array( 'type' => 'string' ),
			'menu_id'   => array( 'type' => 'integer' ),
			'menu_name' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$location   = sanitize_key( $input['location'] );
			$menu_id    = (int) $input['menu_id'];
			$registered = get_registered_nav_menus();
			if ( ! isset( $registered[ $location ] ) ) {
				return new WP_Error( 'ability_invalid_input', "Location \"{$location}\" is not registered. Available: " . implode( ', ', array_keys( $registered ) ) );
			}
			$menu = wp_get_nav_menu_object( $menu_id );
			if ( ! $menu ) {
				return new WP_Error( 'not_found', "Menu {$menu_id} not found." );
			}
			$locations              = get_nav_menu_locations();
			$locations[ $location ] = $menu_id;
			set_theme_mod( 'nav_menu_locations', $locations );
			return array( 'success' => true, 'location' => $location, 'menu_id' => $menu_id, 'menu_name' => $menu->name );
		},
	) );

	$reg->write( 'menus/unassign-location', array(
		'label'       => 'Unassign Menu from Location',
		'description' => 'Remove the menu assignment from a theme location, leaving it empty.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'location' ),
			'properties' => array(
				'location' => array( 'type' => 'string', 'description' => 'Theme location slug to clear.' ),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'location' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$location   = sanitize_key( $input['location'] );
			$registered = get_registered_nav_menus();
			if ( ! isset( $registered[ $location ] ) ) {
				return new WP_Error( 'ability_invalid_input', "Location \"{$location}\" is not registered. Available: " . implode( ', ', array_keys( $registered ) ) );
			}
			$locations              = get_nav_menu_locations();
			$locations[ $location ] = 0;
			set_theme_mod( 'nav_menu_locations', $locations );
			return array( 'success' => true, 'location' => $location );
		},
	) );

	// ===== MENUS — DELETE =====

	// menus/delete-menu is free — round-trip: create → test → delete the test.
	$reg->delete( 'menus/delete-menu', array(
		'tier'        => 'free',
		'label'       => 'Delete Menu',
		'description' => 'Delete a navigation menu and all its items. Destructive and cannot be undone.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'menu_id' ),
			'properties' => array(
				'menu_id' => array( 'type' => 'integer', 'description' => 'The menu term ID to delete.' ),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'deleted_items' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ),
		'callback' => function( $input ) {
			$menu_id    = (int) $input['menu_id'];
			$menu       = wp_get_nav_menu_object( $menu_id );
			if ( ! $menu ) {
				return new WP_Error( 'not_found', "Menu {$menu_id} not found." );
			}
			$items      = wp_get_nav_menu_items( $menu_id );
			$item_count = $items ? count( $items ) : 0;
			$result     = wp_delete_nav_menu( $menu_id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array( 'success' => true, 'deleted_items' => $item_count );
		},
	) );

	// menus/delete-menu-item is free — round-trip: add → test → delete the test.
	$reg->delete( 'menus/delete-menu-item', array(
		'tier'        => 'free',
		'label'       => 'Delete Menu Item',
		'description' => 'Remove a single item from a menu.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'item_id' ),
			'properties' => array(
				'item_id' => array( 'type' => 'integer', 'description' => 'The menu item post ID to delete.' ),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array() ),
		'annotations'   => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ),
		'callback' => function( $input ) {
			$item_id = (int) $input['item_id'];
			$post    = get_post( $item_id );
			if ( ! $post || 'nav_menu_item' !== $post->post_type ) {
				return new WP_Error( 'not_found', "Menu item {$item_id} not found." );
			}
			$result = wp_delete_post( $item_id, true );
			if ( ! $result ) {
				return new WP_Error( 'ability_invalid_input', "Failed to delete menu item {$item_id}." );
			}
			return array( 'success' => true );
		},
	) );
} );
