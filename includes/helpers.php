<?php
/**
 * Shared Helper Functions
 *
 * Pagination, sanitization, and shared utilities used across all modules.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Paginate results with standard parameters.
 *
 * @param array $input          Input with optional 'page' and 'per_page'.
 * @param int   $default_per_page Default items per page.
 * @return array [ 'page' => int, 'per_page' => int, 'offset' => int ]
 */
function wp_abilities_pagination( $input, $default_per_page = 20 ) {
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
 * @deprecated Use abilities_for_ai_schema_pagination() from schemas.php instead.
 *             This function is retained as a backwards-compatible alias and will
 *             be removed in a future version.
 * @return array Schema properties for page and per_page.
 */
function wp_abilities_pagination_schema() {
	return abilities_for_ai_schema_pagination();
}

/**
 * Format a standard error response.
 *
 * @param string $code    Error code.
 * @param string $message Error message.
 * @param array  $data    Optional error data.
 * @return WP_Error
 */
function wp_abilities_error( $code, $message, $data = array() ) {
	return new WP_Error( $code, $message, $data );
}

/**
 * Resolve a user across WordPress.
 *
 * @param string|int $identifier Email address or WordPress user ID.
 * @return array Basic user info.
 */
if ( ! function_exists( 'wp_abilities_resolve_user' ) ) {
	function wp_abilities_resolve_user( $identifier ) {
		$result = array();

		if ( is_numeric( $identifier ) ) {
			$user = get_userdata( (int) $identifier );
			$email = $user ? $user->user_email : null;
			$result['wp_user_id'] = (int) $identifier;
			$result['wp_user'] = $user ? $user->display_name : null;
		} else {
			$email = sanitize_email( $identifier );
			$user = get_user_by( 'email', $email );
			$result['wp_user_id'] = $user ? $user->ID : null;
			$result['wp_user'] = $user ? $user->display_name : null;
		}

		if ( $email ) {
			$result['email'] = $email;
		}

		return $result;
	}
}

/**
 * Validate that a post exists and the current user can edit it.
 */
function abilities_for_ai_require_editable_post( $post_id, $capability = 'edit_post' ) {
    $post = get_post( absint( $post_id ) );
    if ( ! $post ) {
        return wp_abilities_error( 'not_found', 'Post not found.' );
    }
    if ( ! current_user_can( $capability, $post->ID ) ) {
        return wp_abilities_error( 'rest_forbidden', 'You do not have permission to perform this action on this post.' );
    }
    return $post;
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

// ============================================================
// Permission Toggles
// ============================================================

/**
 * Default permission settings per module.
 *
 * Read = ON, Write = ON, Delete = OFF by default.
 * Modules with only read abilities omit write/delete keys.
 * Blocks and cache have delete ON (flush is safe).
 *
 * @return array Module permission defaults.
 */
function abilities_for_ai_permission_defaults() {
	return array(
		'content'    => array( 'read' => true, 'write' => true, 'delete' => false ),
		'taxonomies' => array( 'read' => true, 'write' => true, 'delete' => false ),
		'plugins'    => array( 'read' => true, 'write' => true, 'delete' => false ),
		'media'      => array( 'read' => true, 'write' => true, 'delete' => false ),
		'users'      => array( 'read' => true, 'write' => true, 'delete' => false ),
		'comments'   => array( 'read' => true, 'write' => true, 'delete' => false ),
		'menus'      => array( 'read' => true, 'write' => true, 'delete' => false ),
		'blocks'     => array( 'read' => true, 'write' => true, 'delete' => false ),
		'patterns'   => array( 'read' => true, 'write' => true, 'delete' => false ),
		'meta'       => array( 'read' => true, 'write' => true, 'delete' => false ),
		'settings'   => array( 'read' => true, 'write' => true, 'delete' => false ),
		'site-health' => array( 'read' => true ),
		'cache'      => array( 'read' => true, 'write' => true, 'delete' => false ),
		'cron'       => array( 'read' => true, 'write' => true, 'delete' => false ),
		'themes'     => array( 'read' => true, 'write' => true, 'delete' => false ),
		'rest'       => array( 'read' => true ),
		'rewrite'    => array( 'read' => true, 'write' => true, 'delete' => false ),
		'filesystem' => array( 'read' => true, 'write' => true, 'delete' => false ),
		'revisions'  => array( 'read' => true, 'write' => true, 'delete' => false ),
		'multisite'  => array( 'read' => true, 'write' => true ),
	);
}

/**
 * Human-readable labels for each module.
 *
 * @return array Module slug => label.
 */
function abilities_for_ai_module_labels() {
	return array(
		'content'    => 'Content',
		'taxonomies' => 'Taxonomies',
		'plugins'    => 'Plugins',
		'media'      => 'Media',
		'users'      => 'Users',
		'comments'   => 'Comments',
		'menus'      => 'Menus',
		'blocks'     => 'Block Editor',
		'patterns'   => 'Block Patterns',
		'meta'       => 'Meta Fields',
		'settings'   => 'Settings',
		'site-health' => 'Site Health',
		'cache'      => 'Cache / Transients',
		'cron'       => 'Cron / Scheduling',
		'themes'     => 'Themes',
		'rest'       => 'REST Discovery',
		'rewrite'    => 'Rewrite Rules',
		'filesystem' => 'Filesystem',
		'revisions'  => 'Revisions',
		'multisite'  => 'Multisite',
	);
}

/**
 * Get resolved permissions for a module, merging saved values with defaults.
 *
 * @param string $module Module slug (e.g. 'meta', 'content').
 * @return array Associative array with 'read', 'write', 'delete' keys (booleans).
 */
function abilities_for_ai_get_permissions( $module ) {
	static $perms = null;
	if ( $perms === null ) {
		$perms = get_option( 'abilities_for_ai_permissions', array() );
	}
	$defaults = abilities_for_ai_permission_defaults();
	$module_defaults = $defaults[ $module ] ?? array( 'read' => true );
	$module_saved    = $perms[ $module ] ?? array();

	return wp_parse_args( $module_saved, $module_defaults );
}

/**
 * Check if a specific ability is enabled, considering per-ability overrides.
 *
 * Resolution order:
 * 1. Per-ability override (if set) wins.
 * 2. Module-level permission is the fallback.
 *
 * @param string $ability_name Full ability name (e.g. 'content/search-replace').
 * @param string $module       Module slug.
 * @param string $op           Operation type: 'read', 'write', or 'delete'.
 * @return bool Whether the ability is permitted.
 */
function abilities_for_ai_ability_enabled( $ability_name, $module, $op ) {
	static $perms = null;
	if ( $perms === null ) {
		$perms = get_option( 'abilities_for_ai_permissions', array() );
	}

	$overrides = $perms['_overrides'] ?? array();

	// Per-ability override exists — use it.
	if ( isset( $overrides[ $ability_name ] ) ) {
		return ! empty( $overrides[ $ability_name ] );
	}

	// Fall back to module-level permission.
	$module_perms = abilities_for_ai_get_permissions( $module );
	return ! empty( $module_perms[ $op ] );
}

/**
 * Check if a specific operation type is allowed for a module.
 *
 * @param string $module Module slug.
 * @param string $op     Operation type: 'read', 'write', or 'delete'.
 * @return bool Whether the operation is permitted.
 */
function abilities_for_ai_can( $module, $op ) {
	$perms = abilities_for_ai_get_permissions( $module );
	return ! empty( $perms[ $op ] );
}

/**
 * Check if an IP address is in a private/internal range.
 */
function wp_abilities_is_private_ip( $ip ) {
    // IPv4 private/reserved ranges.
    $ipv4_ranges = array(
        '127.0.0.0/8',
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '169.254.0.0/16',
        '0.0.0.0/8',
    );

    $ip_long = ip2long( $ip );
    if ( $ip_long !== false ) {
        foreach ( $ipv4_ranges as $range ) {
            list( $net, $mask ) = explode( '/', $range );
            $net_long  = ip2long( $net );
            $mask_long = ~( ( 1 << ( 32 - (int) $mask ) ) - 1 );
            if ( ( $ip_long & $mask_long ) === ( $net_long & $mask_long ) ) {
                return true;
            }
        }
        return false;
    }

    // IPv6 — use inet_pton for proper binary comparison.
    $ip_bin = @inet_pton( $ip );
    if ( $ip_bin === false ) {
        return false; // Unparseable address — treat as not-private but caller should reject.
    }

    $ipv6_ranges = array(
        '::1/128',       // Loopback
        'fc00::/7',      // Unique Local (fc00::/7 covers both fc and fd)
        'fe80::/10',     // Link-Local
        '::ffff:0:0/96', // IPv4-mapped (re-check the mapped v4 address)
        '::/128',        // Unspecified
    );

    foreach ( $ipv6_ranges as $range ) {
        list( $net, $prefix_len ) = explode( '/', $range );
        $net_bin = @inet_pton( $net );
        if ( $net_bin === false ) {
            continue;
        }
        // Build bitmask: $prefix_len leading 1-bits, rest 0.
        $mask = str_repeat( "\xff", intdiv( (int) $prefix_len, 8 ) );
        $remainder = (int) $prefix_len % 8;
        if ( $remainder ) {
            $mask .= chr( 0xff << ( 8 - $remainder ) & 0xff );
        }
        $mask = str_pad( $mask, 16, "\x00" );

        if ( ( $ip_bin & $mask ) === ( $net_bin & $mask ) ) {
            // For IPv4-mapped addresses (::ffff:x.x.x.x), also check the embedded v4.
            if ( $net === '::ffff:0:0' ) {
                $mapped_v4 = substr( $ip_bin, 12, 4 );
                $mapped_long = unpack( 'N', $mapped_v4 )[1];
                foreach ( $ipv4_ranges as $v4_range ) {
                    list( $v4_net, $v4_mask ) = explode( '/', $v4_range );
                    $v4_net_long  = ip2long( $v4_net );
                    $v4_mask_long = ~( ( 1 << ( 32 - (int) $v4_mask ) ) - 1 );
                    if ( ( $mapped_long & $v4_mask_long ) === ( $v4_net_long & $v4_mask_long ) ) {
                        return true;
                    }
                }
                continue; // Mapped to a public v4 — not private.
            }
            return true;
        }
    }

    return false;
}

/**
 * Alias for wp_abilities_is_private_ip().
 *
 * @param string $ip IP address to check.
 * @return bool True if the IP is private/internal.
 */
function abilities_for_ai_is_private_ip( string $ip ): bool {
    return wp_abilities_is_private_ip( $ip );
}
