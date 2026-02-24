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
				wp_native_pagination_schema()
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

			$pag = wp_native_pagination( $params );

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
		'meta' => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
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
				return wp_native_error( 'not_found', "Transient '{$name}' not found or expired." );
			}
			$timeout = get_option( '_transient_timeout_' . $name );
			return array(
				'name'    => $name,
				'value'   => $value,
				'expires' => $timeout ? date( 'Y-m-d H:i:s', $timeout ) : 'never',
			);
		},
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
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
		'execute_callback' => function( $params ) {
			$name       = sanitize_text_field( $params['name'] ?? '' );
			$value      = $params['value'] ?? '';
			$expiration = intval( $params['expiration'] ?? 3600 );
			$result = set_transient( $name, $value, $expiration );
			return array( 'name' => $name, 'set' => (bool) $result, 'expiration' => $expiration );
		},
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ),
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
		'execute_callback' => function( $params ) {
			$name   = sanitize_text_field( $params['name'] ?? '' );
			$result = delete_transient( $name );
			return array( 'name' => $name, 'deleted' => (bool) $result );
		},
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => true ) ),
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
		'execute_callback' => function( $params ) {
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
		},
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ) ),
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
		'meta' => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
	));

	} // end read
}
