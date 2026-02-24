<?php
/**
 * REST Discovery Abilities
 *
 * REST API namespace, route, and schema introspection.
 *
 * @package WordPress_Native_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', 'wp_native_register_rest_discovery_abilities' );

function wp_native_register_rest_discovery_abilities() {

	$perms = wp_abilities_suite_get_permissions( 'rest' );

	// ===== REST — READ =====
	if ( $perms['read'] ) {

	// ---- rest/list-namespaces ----
	wp_register_ability( 'rest/list-namespaces', array(
		'label'       => 'List REST Namespaces',
		'description' => 'List all registered REST API namespaces.',
		'category'    => 'rest',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => (object) array(),
		),
		'execute_callback' => function() {
			$server     = rest_get_server();
			$namespaces = $server->get_namespaces();
			return array(
				'count'      => count( $namespaces ),
				'namespaces' => array_values( $namespaces ),
			);
		},
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
	));

	// ---- rest/list-routes ----
	wp_register_ability( 'rest/list-routes', array(
		'label'       => 'List REST Routes',
		'description' => 'List all routes for a namespace with their HTTP methods.',
		'category'    => 'rest',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'namespace' => array( 'type' => 'string', 'description' => 'REST namespace to filter by (e.g. "wp/v2", "wp-abilities/v1")' ),
			),
		),
		'execute_callback' => function( $params ) {
			$server = rest_get_server();
			$routes = $server->get_routes();
			$ns     = $params['namespace'] ?? '';
			$result = array();

			foreach ( $routes as $route => $handlers ) {
				if ( $ns && strpos( $route, '/' . $ns ) !== 0 ) {
					continue;
				}
				$methods = array();
				foreach ( $handlers as $handler ) {
					if ( isset( $handler['methods'] ) ) {
						$methods = array_merge( $methods, array_keys( (array) $handler['methods'] ) );
					}
				}
				$result[] = array(
					'route'   => $route,
					'methods' => array_unique( $methods ),
				);
			}

			return array(
				'namespace'   => $ns ?: '(all)',
				'route_count' => count( $result ),
				'routes'      => $result,
			);
		},
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
	));

	// ---- rest/get-route-schema ----
	wp_register_ability( 'rest/get-route-schema', array(
		'label'       => 'Get Route Schema',
		'description' => 'Get the JSON Schema for a specific REST route\'s arguments and response.',
		'category'    => 'rest',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'route' => array( 'type' => 'string', 'description' => 'REST route path (e.g. "/wp/v2/posts")' ),
			),
			'required' => array( 'route' ),
		),
		'execute_callback' => function( $params ) {
			$server = rest_get_server();
			$routes = $server->get_routes();
			$route  = sanitize_text_field( $params['route'] ?? '' );

			if ( ! isset( $routes[ $route ] ) ) {
				return wp_native_error( 'not_found', "Route '{$route}' not found." );
			}

			$endpoints = array();
			foreach ( $routes[ $route ] as $handler ) {
				$endpoint = array(
					'methods' => array_keys( (array) ( $handler['methods'] ?? array() ) ),
				);
				if ( ! empty( $handler['args'] ) ) {
					$endpoint['args'] = $handler['args'];
				}
				if ( ! empty( $handler['schema'] ) && is_callable( $handler['schema'] ) ) {
					$endpoint['schema'] = call_user_func( $handler['schema'] );
				}
				$endpoints[] = $endpoint;
			}

			return array(
				'route'     => $route,
				'endpoints' => $endpoints,
			);
		},
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
	));

	// ---- rest/get-index ----
	wp_register_ability( 'rest/get-index', array(
		'label'       => 'Get REST Index',
		'description' => 'Get the full REST API index (equivalent to /wp-json/).',
		'category'    => 'rest',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => (object) array(),
		),
		'execute_callback' => function() {
			$request  = new WP_REST_Request( 'GET', '/' );
			$response = rest_get_server()->dispatch( $request );
			$data     = $response->get_data();
			return array(
				'name'        => $data['name'] ?? '',
				'description' => $data['description'] ?? '',
				'url'         => $data['url'] ?? '',
				'home'        => $data['home'] ?? '',
				'gmt_offset'  => $data['gmt_offset'] ?? '',
				'timezone_string' => $data['timezone_string'] ?? '',
				'namespaces'  => $data['namespaces'] ?? array(),
				'authentication' => $data['authentication'] ?? array(),
			);
		},
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
	));

	} // end read
}
