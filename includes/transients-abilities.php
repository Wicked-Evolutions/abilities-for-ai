<?php
/**
 * Transients / Cache Abilities
 *
 * WordPress transient and object cache management.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {
	$reg = new Abilities_For_AI_Registrar( 'cache', 'manage_options' );

	// ===== CACHE — READ =====

	$reg->read( 'cache/list-transients', array(
		'label'       => 'List Transients',
		'description' => 'List all transients stored in the options table with expiry times.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				array(
					'search' => abilities_for_ai_schema_search( 'Filter transient names by keyword' ),
				),
				abilities_for_ai_schema_pagination()
			),
		),
		'output_schema' => abilities_for_ai_schema_list_output( 'transients', array(
			'name'       => array( 'type' => 'string' ),
			'value_size' => array( 'type' => 'integer' ),
			'expires'    => array( 'type' => 'string' ),
			'expired'    => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $params ) {
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
				'total'      => intval( $total ),
				'pages'      => max( 1, (int) ceil( $total / $pag['per_page'] ) ),
				'page'       => $pag['page'],
				'per_page'   => $pag['per_page'],
				'transients' => $transients,
			);
		},
	));

	$reg->read( 'cache/get-transient', array(
		'label'       => 'Get Transient',
		'description' => 'Get a specific transient value.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'name' => array( 'type' => 'string', 'description' => 'Transient name' ),
			),
			'required' => array( 'name' ),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'name'    => array( 'type' => 'string' ),
			'value'   => array( 'type' => 'string', 'description' => 'Transient value (may be string, array, or serialized data)' ),
			'expires' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $params ) {
			$name  = sanitize_text_field( $params['name'] ?? '' );
			$value = get_transient( $name );
			if ( $value === false ) {
				return wp_abilities_error( 'not_found', "Transient '{$name}' not found or expired." );
			}
			$serialized = maybe_serialize( $value );
			if ( strlen( $serialized ) > MB_IN_BYTES ) {
				return array(
					'name'   => $name,
					'value'  => '[VALUE TOO LARGE: ' . size_format( strlen( $serialized ) ) . ']',
					'exists' => true,
				);
			}
			$timeout = get_option( '_transient_timeout_' . $name );
			return array(
				'name'    => $name,
				'value'   => abilities_for_ai_safe_value( $value ),
				'expires' => $timeout ? date( 'Y-m-d H:i:s', $timeout ) : 'never',
			);
		},
	));

	$reg->read( 'cache/object-cache-status', array(
		'label'       => 'Object Cache Status',
		'description' => 'Check if a persistent object cache is active and get cache statistics.',
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'persistent_cache' => array( 'type' => 'boolean' ),
			'drop_in_exists'   => array( 'type' => 'boolean' ),
			'class'            => array( 'type' => 'string' ),
			'stats'            => array( 'type' => 'string' ),
		) ),
		'callback' => function() {
			global $wp_object_cache;
			$using_ext = wp_using_ext_object_cache();
			$info      = array(
				'persistent_cache' => (bool) $using_ext,
				'drop_in_exists'   => file_exists( WP_CONTENT_DIR . '/object-cache.php' ),
				'class'            => '',
				'stats'            => '',
			);
			if ( $using_ext && is_object( $wp_object_cache ) ) {
				$info['class'] = get_class( $wp_object_cache );
				if ( method_exists( $wp_object_cache, 'stats' ) ) {
					ob_start();
					$wp_object_cache->stats();
					$info['stats'] = ob_get_clean();
				}
			}
			return $info;
		},
	));

	// ===== CACHE — WRITE =====

	$reg->write( 'cache/set-transient', array(
		'label'       => 'Set Transient',
		'description' => 'Set a transient with an optional TTL in seconds.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'name'       => array( 'type' => 'string', 'description' => 'Transient name' ),
				'value'      => array( 'type' => 'string', 'description' => 'Value to store' ),
				'expiration' => array( 'type' => 'integer', 'description' => 'TTL in seconds (0 = no expiration)', 'default' => 3600 ),
			),
			'required' => array( 'name', 'value' ),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'name'       => array( 'type' => 'string' ),
			'set'        => array( 'type' => 'boolean' ),
			'expiration' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $params ) {
			$name       = sanitize_text_field( $params['name'] ?? '' );
			$value      = $params['value'] ?? '';
			$expiration = intval( $params['expiration'] ?? 3600 );
			$result     = set_transient( $name, $value, $expiration );
			return array( 'name' => $name, 'set' => (bool) $result, 'expiration' => $expiration );
		},
	));

	$reg->write( 'cache/flush-page-cache', array(
		'label'       => 'Flush Page Cache',
		'description' => 'Purge the full-page cache (LiteSpeed, WP Super Cache, W3 Total Cache, WP Fastest Cache, or wp_cache_flush fallback). Use after content changes, theme updates, or permalink changes when cached pages show stale content. Returns which cache system was detected and purged.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_id' => array( 'type' => 'integer', 'description' => 'Purge cache for a specific post only (if supported by the cache plugin). Omit to purge all.' ),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'flushed' => array( 'type' => 'boolean' ),
			'post_id' => array( 'type' => array( 'integer', 'null' ) ),
			'systems' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
		) ),
		'callback' => function( $params ) {
			$post_id = ! empty( $params['post_id'] ) ? absint( $params['post_id'] ) : 0;
			$purged  = array();

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

			if ( function_exists( 'wp_cache_clear_cache' ) ) {
				if ( $post_id && function_exists( 'wp_cache_post_change' ) ) {
					wp_cache_post_change( $post_id );
				} else {
					wp_cache_clear_cache();
				}
				$purged[] = 'wp-super-cache';
			}

			if ( function_exists( 'w3tc_flush_all' ) ) {
				if ( $post_id && function_exists( 'w3tc_flush_post' ) ) {
					w3tc_flush_post( $post_id );
				} else {
					w3tc_flush_all();
				}
				$purged[] = 'w3-total-cache';
			}

			if ( function_exists( 'wpfc_clear_all_cache' ) ) {
				wpfc_clear_all_cache( true );
				$purged[] = 'wp-fastest-cache';
			}

			wp_cache_flush();
			$purged[] = 'wp-object-cache';

			return array(
				'flushed' => true,
				'post_id' => $post_id ?: null,
				'systems' => $purged,
			);
		},
	));

	// ===== CACHE — DELETE =====

	$reg->delete( 'cache/delete-transient', array(
		'tier'        => 'free',
		'label'       => 'Delete Transient',
		'description' => 'Delete a specific transient.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'name' => array( 'type' => 'string', 'description' => 'Transient name to delete' ),
			),
			'required' => array( 'name' ),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'name'    => array( 'type' => 'string' ),
			'deleted' => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $params ) {
			$name   = sanitize_text_field( $params['name'] ?? '' );
			$result = delete_transient( $name );
			return array( 'name' => $name, 'deleted' => (bool) $result );
		},
	));

	$reg->delete( 'cache/flush', array(
		'label'       => 'Flush Transients',
		'description' => 'Delete all expired transients from the database. Use with care.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'all' => array( 'type' => 'boolean', 'description' => 'Flush ALL transients, not just expired (default: false)', 'default' => false ),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'flushed'      => array( 'type' => 'string' ),
			'rows_deleted' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ),
		'callback' => function( $params ) {
			global $wpdb;

			$flush_all = ! empty( $params['all'] );

			if ( $flush_all ) {
				$deleted = $wpdb->query(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'"
				);
			} else {
				$time    = time();
				$deleted = $wpdb->query( $wpdb->prepare(
					"DELETE a, b FROM {$wpdb->options} a
					 INNER JOIN {$wpdb->options} b ON b.option_name = CONCAT('_transient_', SUBSTRING(a.option_name, 20))
					 WHERE a.option_name LIKE '_transient_timeout_%%' AND a.option_value < %d",
					$time
				));
			}

			return array(
				'flushed'      => $flush_all ? 'all' : 'expired_only',
				'rows_deleted' => intval( $deleted ),
			);
		},
	));
});
