<?php
/**
 * Transients / Cache Abilities
 *
 * WordPress transient and object cache management.
 *
 * @package WordPress_Native_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', 'wp_native_register_transients_abilities' );

function wp_native_register_transients_abilities() {

	$perms = wp_abilities_suite_get_permissions( 'cache' );

	// ===== CACHE — READ =====
	if ( $perms['read'] ) {

	// ---- cache/list-transients ----
	wp_register_ability( 'cache/list-transients', array(
		'label'       => 'List Transients',
		'description' => 'List all transients stored in the options table with expiry times.',
		'category'    => 'cache',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				array(
					'search' => array( 'type' => 'string', 'description' => 'Filter transient names by keyword' ),
				),
				wp_abilities_pagination_schema()
			),
		),
		'execute_callback' => function( $params ) {
			global $wpdb;

			$search = ! empty( $params['search'] ) ? '%' . $wpdb->esc_like( sanitize_text_field( $params['search'] ) ) . '%' : '%';

			$total = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s",
				'_transient_' . $search,
				'_transient_timeout_%'
			));

			$pag = wp_abilities_pagination( $params );

			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options}
				 WHERE option_name LIKE %s AND option_name NOT LIKE %s
				 ORDER BY option_name ASC
				 LIMIT %d OFFSET %d",
				'_transient_' . $search,
				'_transient_timeout_%',
				$pag['per_page'],
				$pag['offset']
			));

			$transients = array();
			foreach ( $rows as $row ) {
				$name    = str_replace( '_transient_', '', $row->option_name );
				$timeout = get_option( '_transient_timeout_' . $name );
				$transients[] = array(
					'name'       => $name,
					'value_size' => strlen( $row->option_value ),
					'expires'    => $timeout ? date( 'Y-m-d H:i:s', $timeout ) : 'never',
					'expired'    => $timeout ? ( time() > $timeout ) : false,
				);
			}

			return array(
				'transients' => $transients,
				'total'      => intval( $total ),
				'page'       => $pag['page'],
				'pages'      => ceil( $total / $pag['per_page'] ),
			);
		},
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) , 'tier' => 'free',),
	));

	// ---- cache/get-transient ----
	wp_register_ability( 'cache/get-transient', array(
		'label'       => 'Get Transient',
		'description' => 'Get a specific transient value.',
		'category'    => 'cache',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'name' => array( 'type' => 'string', 'description' => 'Transient name' ),
			),
			'required' => array( 'name' ),
		),
		'execute_callback' => function( $params ) {
			$name  = sanitize_text_field( $params['name'] ?? '' );
			$value = get_transient( $name );
			if ( $value === false ) {
				return wp_abilities_error( 'not_found', "Transient '{$name}' not found or expired." );
			}
			$serialized = maybe_serialize( $value );
			if ( strlen( $serialized ) > MB_IN_BYTES ) {
				return array(
					'name'    => $name,
					'value'   => '[VALUE TOO LARGE: ' . size_format( strlen( $serialized ) ) . ']',
					'exists'  => true,
				);
			}
			$timeout = get_option( '_transient_timeout_' . $name );
			return array(
				'name'    => $name,
				'value'   => $value,
				'expires' => $timeout ? date( 'Y-m-d H:i:s', $timeout ) : 'never',
			);
		},
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) , 'tier' => 'free',),
	));

	} // end read

	// ===== CACHE — WRITE =====
	if ( ! empty( $perms['write'] ) ) {

	// ---- cache/set-transient ----
	wp_register_ability( 'cache/set-transient', array(
		'label'       => 'Set Transient',
		'description' => 'Set a transient with an optional TTL in seconds.',
		'category'    => 'cache',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'name'       => array( 'type' => 'string', 'description' => 'Transient name' ),
				'value'      => array( 'type' => 'string', 'description' => 'Value to store' ),
				'expiration' => array( 'type' => 'integer', 'description' => 'TTL in seconds (0 = no expiration)', 'default' => 3600 ),
			),
			'required' => array( 'name', 'value' ),
		),
		'execute_callback' => wp_abilities_suite_pro_gate('cache/set-transient', function( $params ) {
			$name       = sanitize_text_field( $params['name'] ?? '' );
			$value      = $params['value'] ?? '';
			$expiration = intval( $params['expiration'] ?? 3600 );
			$result = set_transient( $name, $value, $expiration );
			return array( 'name' => $name, 'set' => (bool) $result, 'expiration' => $expiration );
		}),
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) , 'tier' => 'pro',),
	));

	// ---- cache/flush-page-cache ----
	wp_register_ability( 'cache/flush-page-cache', array(
		'label'       => 'Flush Page Cache',
		'description' => 'Purge the full-page cache (LiteSpeed, WP Super Cache, W3 Total Cache, WP Fastest Cache, or wp_cache_flush fallback). Use after content changes, theme updates, or permalink changes when cached pages show stale content. Returns which cache system was detected and purged.',
		'category'    => 'cache',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_id' => array( 'type' => 'integer', 'description' => 'Purge cache for a specific post only (if supported by the cache plugin). Omit to purge all.' ),
			),
		),
		'execute_callback' => wp_abilities_suite_pro_gate('cache/flush-page-cache', function( $params ) {
			$post_id = ! empty( $params['post_id'] ) ? absint( $params['post_id'] ) : 0;
			$purged  = array();

			// LiteSpeed Cache.
			if ( $post_id && defined( 'LSCWP_V' ) ) {
				do_action( 'litespeed_purge_post', $post_id );
				$purged[] = 'litespeed (single post)';
			} elseif ( class_exists( 'LiteSpeed\Purge' ) && method_exists( 'LiteSpeed\Purge', 'purge_all' ) ) {
				LiteSpeed\Purge::purge_all();
				$purged[] = 'litespeed';
			} elseif ( defined( 'LSCWP_V' ) ) {
				do_action( 'litespeed_purge_all' );
				$purged[] = 'litespeed (via action)';
			}

			// WP Super Cache.
			if ( function_exists( 'wp_cache_clear_cache' ) ) {
				if ( $post_id && function_exists( 'wp_cache_post_change' ) ) {
					wp_cache_post_change( $post_id );
				} else {
					wp_cache_clear_cache();
				}
				$purged[] = 'wp-super-cache';
			}

			// W3 Total Cache.
			if ( function_exists( 'w3tc_flush_all' ) ) {
				if ( $post_id && function_exists( 'w3tc_flush_post' ) ) {
					w3tc_flush_post( $post_id );
				} else {
					w3tc_flush_all();
				}
				$purged[] = 'w3-total-cache';
			}

			// WP Fastest Cache.
			if ( function_exists( 'wpfc_clear_all_cache' ) ) {
				wpfc_clear_all_cache( true );
				$purged[] = 'wp-fastest-cache';
			}

			// WordPress object cache (always flush as baseline).
			wp_cache_flush();
			$purged[] = 'wp-object-cache';

			return array(
				'flushed'    => true,
				'post_id'    => $post_id ?: null,
				'systems'    => $purged,
			);
		}),
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) , 'tier' => 'pro',),
	));

	} // end write

	// ===== CACHE — DELETE =====
	if ( ! empty( $perms['delete'] ) ) {

	// ---- cache/delete-transient ----
	wp_register_ability( 'cache/delete-transient', array(
		'label'       => 'Delete Transient',
		'description' => 'Delete a specific transient.',
		'category'    => 'cache',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'name' => array( 'type' => 'string', 'description' => 'Transient name to delete' ),
			),
			'required' => array( 'name' ),
		),
		'execute_callback' => wp_abilities_suite_pro_gate('cache/delete-transient', function( $params ) {
			$name   = sanitize_text_field( $params['name'] ?? '' );
			$result = delete_transient( $name );
			return array( 'name' => $name, 'deleted' => (bool) $result );
		}),
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => true ) , 'tier' => 'pro',),
	));

	// ---- cache/flush ----
	wp_register_ability( 'cache/flush', array(
		'label'       => 'Flush Transients',
		'description' => 'Delete all expired transients from the database. Use with care.',
		'category'    => 'cache',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'all' => array( 'type' => 'boolean', 'description' => 'Flush ALL transients, not just expired (default: false)', 'default' => false ),
			),
		),
		'execute_callback' => wp_abilities_suite_pro_gate('cache/flush', function( $params ) {
			global $wpdb;

			$flush_all = ! empty( $params['all'] );

			if ( $flush_all ) {
				$deleted = $wpdb->query(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'"
				);
			} else {
				$time = time();
				$deleted = $wpdb->query( $wpdb->prepare(
					"DELETE a, b FROM {$wpdb->options} a
					 INNER JOIN {$wpdb->options} b ON b.option_name = CONCAT('_transient_', SUBSTRING(a.option_name, 20))
					 WHERE a.option_name LIKE '_transient_timeout_%%' AND a.option_value < %d",
					$time
				));
			}

			return array(
				'flushed' => $flush_all ? 'all' : 'expired_only',
				'rows_deleted' => intval( $deleted ),
			);
		}),
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ) , 'tier' => 'pro',),
	));

	} // end delete

	// ===== CACHE — READ (continued) =====
	if ( $perms['read'] ) {

	// ---- cache/object-cache-status ----
	wp_register_ability( 'cache/object-cache-status', array(
		'label'       => 'Object Cache Status',
		'description' => 'Check if a persistent object cache is active and get cache statistics.',
		'category'    => 'cache',
		'input_schema' => array(
			'type'       => 'object',
		),
		'execute_callback' => function() {
			global $wp_object_cache;
			$using_ext = wp_using_ext_object_cache();
			$info = array(
				'persistent_cache' => $using_ext,
				'drop_in_exists'   => file_exists( WP_CONTENT_DIR . '/object-cache.php' ),
			);
			if ( $using_ext && is_object( $wp_object_cache ) ) {
				if ( method_exists( $wp_object_cache, 'stats' ) ) {
					ob_start();
					$wp_object_cache->stats();
					$info['stats'] = ob_get_clean();
				}
				$info['class'] = get_class( $wp_object_cache );
			}
			return $info;
		},
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) , 'tier' => 'free',),
	));

	} // end read
}
