<?php
/**
 * Menu Abilities
 *
 * WordPress navigation menu management — menus, items, and location assignments.
 * Merged from standalone menu-abilities plugin.
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
 * @package WordPress_Abilities_Suite
 */

defined( 'ABSPATH' ) || exit;

// ===== HELPER FUNCTIONS =====

/**
 * Build a hierarchical tree from a flat list of menu items.
 */
function wp_abilities_suite_menu_build_tree( $items ) {
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
function wp_abilities_suite_menu_format_item( $item ) {
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

	$perms = wp_abilities_suite_get_permissions( 'menus' );

	// ===== MENUS — READ =====
	if ( $perms['read'] ) {

	// ===== LIST MENUS =====

	wp_register_ability( 'menus/list-menus', array(
		'label'       => 'List Menus',
		'description' => 'List all navigation menus with item counts and assigned theme locations.',
		'category'    => 'menus',
		'input_schema' => array(
			'type' => 'object',
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'menus' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			),
		),
		'execute_callback' => function( $input ) {
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
		'permission_callback' => function() {
			return current_user_can( 'edit_theme_options' );
		},
		'meta' => array(
			'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
			'show_in_rest' => true,
			'mcp' => array( 'public' => true, 'type' => 'tool' ),
		),
	));

	// ===== GET MENU =====

	wp_register_ability( 'menus/get-menu', array(
		'label'       => 'Get Menu',
		'description' => 'Get a single menu with its full hierarchical item tree.',
		'category'    => 'menus',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'menu_id' ),
			'properties' => array(
				'menu_id' => array( 'type' => 'integer', 'description' => 'The menu term ID.' ),
			),
		),
		'execute_callback' => function( $input ) {
			$menu_id = (int) $input['menu_id'];
			$menu    = wp_get_nav_menu_object( $menu_id );
			if ( ! $menu ) {
				return new WP_Error( 'not_found', "Menu {$menu_id} not found." );
			}
			$items = wp_get_nav_menu_items( $menu_id );
			$tree  = $items ? wp_abilities_suite_menu_build_tree( $items ) : array();
			return array( 'id' => (int) $menu->term_id, 'name' => $menu->name, 'slug' => $menu->slug, 'items' => $tree );
		},
		'permission_callback' => function() {
			return current_user_can( 'edit_theme_options' );
		},
		'meta' => array(
			'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
			'show_in_rest' => true,
			'mcp' => array( 'public' => true, 'type' => 'tool' ),
		),
	));

	} // end read

	// ===== MENUS — WRITE =====
	if ( ! empty( $perms['write'] ) ) {

	// ===== CREATE MENU =====

	wp_register_ability( 'menus/create-menu', array(
		'label'       => 'Create Menu',
		'description' => 'Create a new empty navigation menu. Optionally assign it to a theme location.',
		'category'    => 'menus',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'name' ),
			'properties' => array(
				'name'     => array( 'type' => 'string', 'description' => 'The menu name.' ),
				'location' => array( 'type' => 'string', 'description' => 'Optional theme location slug to assign.' ),
			),
		),
		'execute_callback' => function( $input ) {
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
		'permission_callback' => function() {
			return current_user_can( 'edit_theme_options' );
		},
		'meta' => array(
			'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
			'show_in_rest' => true,
			'mcp' => array( 'public' => true, 'type' => 'tool' ),
		),
	));

	} // end write

	// ===== MENUS — DELETE =====
	if ( ! empty( $perms['delete'] ) ) {

	// ===== DELETE MENU =====

	wp_register_ability( 'menus/delete-menu', array(
		'label'       => 'Delete Menu',
		'description' => 'Delete a navigation menu and all its items. Destructive and cannot be undone.',
		'category'    => 'menus',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'menu_id' ),
			'properties' => array(
				'menu_id' => array( 'type' => 'integer', 'description' => 'The menu term ID to delete.' ),
			),
		),
		'execute_callback' => function( $input ) {
			$menu_id = (int) $input['menu_id'];
			$menu    = wp_get_nav_menu_object( $menu_id );
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
		'permission_callback' => function() {
			return current_user_can( 'edit_theme_options' );
		},
		'meta' => array(
			'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ),
			'show_in_rest' => true,
			'mcp' => array( 'public' => true, 'type' => 'tool' ),
		),
	));

	} // end delete

	// ===== MENUS — READ (continued) =====
	if ( $perms['read'] ) {

	// ===== LIST MENU ITEMS =====

	wp_register_ability( 'menus/list-menu-items', array(
		'label'       => 'List Menu Items',
		'description' => 'List all items in a menu as a flat list with parent IDs and positions.',
		'category'    => 'menus',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'menu_id' ),
			'properties' => array(
				'menu_id' => array( 'type' => 'integer', 'description' => 'The menu term ID.' ),
			),
		),
		'execute_callback' => function( $input ) {
			$menu_id = (int) $input['menu_id'];
			$menu    = wp_get_nav_menu_object( $menu_id );
			if ( ! $menu ) {
				return new WP_Error( 'not_found', "Menu {$menu_id} not found." );
			}
			$items  = wp_get_nav_menu_items( $menu_id );
			$result = array();
			if ( $items ) {
				foreach ( $items as $item ) {
					$result[] = wp_abilities_suite_menu_format_item( $item );
				}
			}
			return array( 'items' => $result );
		},
		'permission_callback' => function() {
			return current_user_can( 'edit_theme_options' );
		},
		'meta' => array(
			'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
			'show_in_rest' => true,
			'mcp' => array( 'public' => true, 'type' => 'tool' ),
		),
	));

	} // end read

	// ===== MENUS — WRITE (continued) =====
	if ( ! empty( $perms['write'] ) ) {

	// ===== ADD MENU ITEM =====

	wp_register_ability( 'menus/add-menu-item', array(
		'label'       => 'Add Menu Item',
		'description' => 'Add an item to a navigation menu. For "custom", provide title and url. For "page"/"post"/"category"/"tag", provide object_id.',
		'category'    => 'menus',
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
		'execute_callback' => function( $input ) {
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
		'permission_callback' => function() {
			return current_user_can( 'edit_theme_options' );
		},
		'meta' => array(
			'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
			'show_in_rest' => true,
			'mcp' => array( 'public' => true, 'type' => 'tool' ),
		),
	));

	// ===== UPDATE MENU ITEM =====

	wp_register_ability( 'menus/update-menu-item', array(
		'label'       => 'Update Menu Item',
		'description' => 'Update properties of an existing menu item. Only provided fields are changed.',
		'category'    => 'menus',
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
		'execute_callback' => function( $input ) {
			$item_id = (int) $input['item_id'];
			$post    = get_post( $item_id );
			if ( ! $post || 'nav_menu_item' !== $post->post_type ) {
				return new WP_Error( 'not_found', "Menu item {$item_id} not found." );
			}

			$current = wp_setup_nav_menu_item( $post );
			$menus   = wp_get_object_terms( $item_id, 'nav_menu' );
			if ( empty( $menus ) || is_wp_error( $menus ) ) {
				return new WP_Error( 'no_menu', "Menu item {$item_id} is not assigned to any menu." );
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
		'permission_callback' => function() {
			return current_user_can( 'edit_theme_options' );
		},
		'meta' => array(
			'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
			'show_in_rest' => true,
			'mcp' => array( 'public' => true, 'type' => 'tool' ),
		),
	));

	} // end write

	// ===== MENUS — DELETE (continued) =====
	if ( ! empty( $perms['delete'] ) ) {

	// ===== DELETE MENU ITEM =====

	wp_register_ability( 'menus/delete-menu-item', array(
		'label'       => 'Delete Menu Item',
		'description' => 'Remove a single item from a menu.',
		'category'    => 'menus',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'item_id' ),
			'properties' => array(
				'item_id' => array( 'type' => 'integer', 'description' => 'The menu item post ID to delete.' ),
			),
		),
		'execute_callback' => function( $input ) {
			$item_id = (int) $input['item_id'];
			$post    = get_post( $item_id );
			if ( ! $post || 'nav_menu_item' !== $post->post_type ) {
				return new WP_Error( 'not_found', "Menu item {$item_id} not found." );
			}
			$result = wp_delete_post( $item_id, true );
			if ( ! $result ) {
				return new WP_Error( 'delete_failed', "Failed to delete menu item {$item_id}." );
			}
			return array( 'success' => true );
		},
		'permission_callback' => function() {
			return current_user_can( 'edit_theme_options' );
		},
		'meta' => array(
			'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ),
			'show_in_rest' => true,
			'mcp' => array( 'public' => true, 'type' => 'tool' ),
		),
	));

	} // end delete

	// ===== MENUS — WRITE (continued) =====
	if ( ! empty( $perms['write'] ) ) {

	// ===== REORDER MENU ITEMS =====

	wp_register_ability( 'menus/reorder-menu-items', array(
		'label'       => 'Reorder Menu Items',
		'description' => 'Reorder items within a menu by providing an array of item IDs in the desired order.',
		'category'    => 'menus',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'menu_id', 'item_order' ),
			'properties' => array(
				'menu_id'    => array( 'type' => 'integer', 'description' => 'The menu term ID.' ),
				'item_order' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'description' => 'Array of menu item IDs in desired order.' ),
			),
		),
		'execute_callback' => function( $input ) {
			$menu_id    = (int) $input['menu_id'];
			$item_order = $input['item_order'];
			$menu = wp_get_nav_menu_object( $menu_id );
			if ( ! $menu ) {
				return new WP_Error( 'not_found', "Menu {$menu_id} not found." );
			}
			if ( ! is_array( $item_order ) || empty( $item_order ) ) {
				return new WP_Error( 'invalid_order', 'item_order must be a non-empty array of item IDs.' );
			}

			// Build a set of valid item IDs that actually belong to this menu.
			$existing_items = wp_get_nav_menu_items( $menu_id );
			$valid_ids = array();
			if ( $existing_items ) {
				foreach ( $existing_items as $existing_item ) {
					$valid_ids[ (int) $existing_item->ID ] = true;
				}
			}

			$result = array();
			foreach ( $item_order as $position => $item_id ) {
				$item_id = (int) $item_id;
				// Skip any ID that isn't a real nav_menu_item belonging to this menu.
				if ( ! isset( $valid_ids[ $item_id ] ) ) {
					continue;
				}
				$order = $position + 1;
				wp_update_post( array( 'ID' => $item_id, 'menu_order' => $order ) );
				$result[] = array( 'id' => $item_id, 'position' => $order );
			}
			return array( 'success' => true, 'items' => $result );
		},
		'permission_callback' => function() {
			return current_user_can( 'edit_theme_options' );
		},
		'meta' => array(
			'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
			'show_in_rest' => true,
			'mcp' => array( 'public' => true, 'type' => 'tool' ),
		),
	));

	} // end write

	// ===== MENUS — READ (continued) =====
	if ( $perms['read'] ) {

	// ===== LIST LOCATIONS =====

	wp_register_ability( 'menus/list-locations', array(
		'label'       => 'List Menu Locations',
		'description' => 'List all registered theme menu locations with their current menu assignments.',
		'category'    => 'menus',
		'input_schema' => array(
			'type' => 'object',
		),
		'execute_callback' => function( $input ) {
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
		'permission_callback' => function() {
			return current_user_can( 'edit_theme_options' );
		},
		'meta' => array(
			'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
			'show_in_rest' => true,
			'mcp' => array( 'public' => true, 'type' => 'tool' ),
		),
	));

	} // end read

	// ===== MENUS — WRITE (continued) =====
	if ( ! empty( $perms['write'] ) ) {

	// ===== ASSIGN LOCATION =====

	wp_register_ability( 'menus/assign-location', array(
		'label'       => 'Assign Menu to Location',
		'description' => 'Assign a menu to a theme location. Replaces any previous assignment.',
		'category'    => 'menus',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'location', 'menu_id' ),
			'properties' => array(
				'location' => array( 'type' => 'string', 'description' => 'Theme location slug.' ),
				'menu_id'  => array( 'type' => 'integer', 'description' => 'The menu term ID to assign.' ),
			),
		),
		'execute_callback' => function( $input ) {
			$location = sanitize_key( $input['location'] );
			$menu_id  = (int) $input['menu_id'];
			$registered = get_registered_nav_menus();
			if ( ! isset( $registered[ $location ] ) ) {
				return new WP_Error( 'invalid_location', "Location \"{$location}\" is not registered. Available: " . implode( ', ', array_keys( $registered ) ) );
			}
			$menu = wp_get_nav_menu_object( $menu_id );
			if ( ! $menu ) {
				return new WP_Error( 'not_found', "Menu {$menu_id} not found." );
			}
			$locations = get_nav_menu_locations();
			$locations[ $location ] = $menu_id;
			set_theme_mod( 'nav_menu_locations', $locations );
			return array( 'success' => true, 'location' => $location, 'menu_id' => $menu_id, 'menu_name' => $menu->name );
		},
		'permission_callback' => function() {
			return current_user_can( 'edit_theme_options' );
		},
		'meta' => array(
			'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
			'show_in_rest' => true,
			'mcp' => array( 'public' => true, 'type' => 'tool' ),
		),
	));

	// ===== UNASSIGN LOCATION =====

	wp_register_ability( 'menus/unassign-location', array(
		'label'       => 'Unassign Menu from Location',
		'description' => 'Remove the menu assignment from a theme location, leaving it empty.',
		'category'    => 'menus',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'location' ),
			'properties' => array(
				'location' => array( 'type' => 'string', 'description' => 'Theme location slug to clear.' ),
			),
		),
		'execute_callback' => function( $input ) {
			$location = sanitize_key( $input['location'] );
			$registered = get_registered_nav_menus();
			if ( ! isset( $registered[ $location ] ) ) {
				return new WP_Error( 'invalid_location', "Location \"{$location}\" is not registered. Available: " . implode( ', ', array_keys( $registered ) ) );
			}
			$locations = get_nav_menu_locations();
			$locations[ $location ] = 0;
			set_theme_mod( 'nav_menu_locations', $locations );
			return array( 'success' => true, 'location' => $location );
		},
		'permission_callback' => function() {
			return current_user_can( 'edit_theme_options' );
		},
		'meta' => array(
			'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
			'show_in_rest' => true,
			'mcp' => array( 'public' => true, 'type' => 'tool' ),
		),
	));

	} // end write

}, 100 );
