<?php
/**
 * REST Discovery Abilities
 *
 * REST API namespace, route, and schema introspection.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {
	$reg = new Abilities_For_AI_Registrar( 'rest', 'manage_options' );

	$reg->read( 'rest/list-namespaces', array(
		'label'       => 'List REST Namespaces',
		'description' => 'List all registered REST API namespaces.',
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'total'      => array( 'type' => 'integer', 'description' => 'Total number of namespaces' ),
			'namespaces' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
		) ),
		'callback' => function() {
			$server     = rest_get_server();
			$namespaces = $server->get_namespaces();
			return array(
				'total'      => count( $namespaces ),
				'namespaces' => array_values( $namespaces ),
			);
		},
	));

	$reg->read( 'rest/list-routes', array(
		'label'       => 'List REST Routes',
		'description' => 'List all routes for a namespace with their HTTP methods.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'namespace' => array( 'type' => 'string', 'description' => 'REST namespace to filter by (e.g. "wp/v2", "wp-abilities/v1")' ),
			),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'namespace' => array( 'type' => 'string' ),
			'total'     => array( 'type' => 'integer' ),
			'routes'    => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $params ) {
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
				'namespace' => $ns ?: '(all)',
				'total'     => count( $result ),
				'routes'    => $result,
			);
		},
	));

	$reg->read( 'rest/get-route-schema', array(
		'label'       => 'Get Route Schema',
		'description' => 'Get the JSON Schema for a specific REST route\'s arguments and response.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'route' => array( 'type' => 'string', 'description' => 'REST route path (e.g. "/wp/v2/posts")' ),
			),
			'required' => array( 'route' ),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'route'     => array( 'type' => 'string' ),
			'endpoints' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $params ) {
			$server = rest_get_server();
			$routes = $server->get_routes();
			$route  = sanitize_text_field( $params['route'] ?? '' );

			if ( ! isset( $routes[ $route ] ) ) {
				return wp_abilities_error( 'not_found', "Route '{$route}' not found." );
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
	));

	$reg->read( 'rest/get-index', array(
		'label'       => 'Get REST Index',
		'description' => 'Get the full REST API index (equivalent to /wp-json/).',
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'name'           => array( 'type' => 'string' ),
			'description'    => array( 'type' => 'string' ),
			'url'            => array( 'type' => 'string' ),
			'home'           => array( 'type' => 'string' ),
			'namespaces'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'authentication' => array( 'type' => 'object' ),
		) ),
		'callback' => function() {
			$request  = new WP_REST_Request( 'GET', '/' );
			$response = rest_get_server()->dispatch( $request );
			$data     = $response->get_data();
			return array(
				'name'            => $data['name'] ?? '',
				'description'     => $data['description'] ?? '',
				'url'             => $data['url'] ?? '',
				'home'            => $data['home'] ?? '',
				'gmt_offset'      => $data['gmt_offset'] ?? '',
				'timezone_string' => $data['timezone_string'] ?? '',
				'namespaces'      => $data['namespaces'] ?? array(),
				'authentication'  => $data['authentication'] ?? array(),
			);
		},
	));
});
