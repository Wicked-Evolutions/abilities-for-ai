<?php
/**
 * Site Health Abilities
 *
 * WordPress site health diagnostics (read-only).
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WordPress_Abilities_Suite
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {
	$reg = new WP_Abilities_Suite_Registrar( 'site-health', 'view_site_health_checks' );

	$reg->read( 'site-health/status', array(
		'label'       => 'Site Health Status',
		'description' => 'Get the overall site health status (good, recommended, critical counts).',
		'output_schema' => wp_abilities_suite_schema_item_output( array(
			'status'             => array( 'type' => 'object' ),
			'total_direct_tests' => array( 'type' => 'integer' ),
			'total_async_tests'  => array( 'type' => 'integer' ),
		) ),
		'callback' => function() {
			if ( ! class_exists( 'WP_Site_Health' ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
			}
			$health = WP_Site_Health::get_instance();
			$tests  = WP_Site_Health::get_tests();

			$counts  = array( 'good' => 0, 'recommended' => 0, 'critical' => 0 );
			$results = get_transient( 'health-check-site-status-result' );

			if ( $results ) {
				$results = json_decode( $results, true );
				if ( is_array( $results ) ) {
					$counts = array(
						'good'        => intval( $results['good'] ?? 0 ),
						'recommended' => intval( $results['recommended'] ?? 0 ),
						'critical'    => intval( $results['critical'] ?? 0 ),
					);
				}
			}

			return array(
				'status'             => $counts,
				'total_direct_tests' => count( $tests['direct'] ?? array() ),
				'total_async_tests'  => count( $tests['async'] ?? array() ),
			);
		},
	));

	$reg->read( 'site-health/list-tests', array(
		'label'       => 'List Health Tests',
		'description' => 'List all available site health tests (direct and async).',
		'output_schema' => wp_abilities_suite_schema_item_output( array(
			'direct' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'async'  => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function() {
			if ( ! class_exists( 'WP_Site_Health' ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
			}
			$tests  = WP_Site_Health::get_tests();
			$direct = array();
			$async  = array();

			foreach ( $tests['direct'] ?? array() as $key => $test ) {
				$direct[] = array( 'key' => $key, 'label' => $test['label'] ?? $key );
			}
			foreach ( $tests['async'] ?? array() as $key => $test ) {
				$async[] = array( 'key' => $key, 'label' => $test['label'] ?? $key );
			}

			return array( 'direct' => $direct, 'async' => $async );
		},
	));

	$reg->read( 'site-health/run-test', array(
		'label'       => 'Run Health Test',
		'description' => 'Run a specific direct site health test and return the result.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'test' => array( 'type' => 'string', 'description' => 'Test key (from list-tests output)' ),
			),
			'required' => array( 'test' ),
		),
		'output_schema' => wp_abilities_suite_schema_item_output( array(
			'test'        => array( 'type' => 'string' ),
			'label'       => array( 'type' => 'string' ),
			'status'      => array( 'type' => 'string', 'enum' => array( 'good', 'recommended', 'critical' ) ),
			'badge'       => array( 'type' => 'object' ),
			'description' => array( 'type' => 'string' ),
			'actions'     => array( 'type' => 'string' ),
		) ),
		'callback' => function( $params ) {
			if ( ! function_exists( 'get_core_updates' ) ) {
				require_once ABSPATH . 'wp-admin/includes/update.php';
			}
			if ( ! class_exists( 'WP_Site_Health' ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
			}
			if ( ! function_exists( 'wp_check_php_version' ) ) {
				require_once ABSPATH . 'wp-admin/includes/misc.php';
			}
			$test_key = sanitize_text_field( $params['test'] ?? '' );
			$tests    = WP_Site_Health::get_tests();
			$direct   = $tests['direct'] ?? array();

			if ( ! isset( $direct[ $test_key ] ) ) {
				return wp_abilities_error( 'not_found', "Test '{$test_key}' not found in direct tests." );
			}

			$test   = $direct[ $test_key ];
			$result = null;

			if ( is_callable( $test['test'] ) ) {
				$result = call_user_func( $test['test'] );
			} elseif ( is_string( $test['test'] ) ) {
				$health      = WP_Site_Health::get_instance();
				$method_name = $test['test'];
				// WP 6.x uses get_test_{key} convention; try both.
				if ( ! method_exists( $health, $method_name ) ) {
					$method_name = 'get_test_' . $method_name;
				}
				if ( method_exists( $health, $method_name ) ) {
					$result = call_user_func( array( $health, $method_name ) );
				}
			}

			if ( ! $result ) {
				return wp_abilities_error( 'ability_invalid_input', "Could not execute test '{$test_key}'." );
			}

			return array(
				'test'        => $test_key,
				'label'       => $result['label'] ?? '',
				'status'      => $result['status'] ?? '',
				'badge'       => $result['badge'] ?? array(),
				'description' => wp_strip_all_tags( $result['description'] ?? '' ),
				'actions'     => wp_strip_all_tags( $result['actions'] ?? '' ),
			);
		},
	));

	$reg->read( 'site-health/info', array(
		'label'       => 'Site Health Info',
		'description' => 'Get comprehensive debug information (PHP, DB, server, WordPress versions, active plugins, theme).',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'section' => array(
					'type'        => 'string',
					'description' => 'Specific section to return (e.g. "wp-core", "wp-server", "wp-database", "wp-plugins-active"). Omit for all.',
				),
			),
		),
		'output_schema' => wp_abilities_suite_schema_item_output( array(
			'sections'           => array( 'type' => 'object', 'description' => 'Section summary when no section specified' ),
			'available_sections' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'section'            => array( 'type' => 'string', 'description' => 'Section key when section specified' ),
			'data'               => array( 'type' => 'object', 'description' => 'Section data when section specified' ),
		) ),
		'callback' => function( $params ) {
			if ( ! function_exists( 'get_core_updates' ) ) {
				require_once ABSPATH . 'wp-admin/includes/update.php';
			}
			if ( ! function_exists( 'got_url_rewrite' ) ) {
				require_once ABSPATH . 'wp-admin/includes/misc.php';
			}
			if ( ! class_exists( 'WP_Debug_Data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
			}
			WP_Debug_Data::check_for_updates();
			$info = WP_Debug_Data::debug_data();

			// Redact sensitive fields from debug data.
			$sensitive_keys = array(
				'DB_PASSWORD', 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY',
				'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT',
				'NONCE_SALT', 'db_password',
				'SMTP_PASSWORD', 'smtp_pass', 'mail_password',
				'API_KEY', 'api_secret', 'SECRET_KEY', 'ACCESS_TOKEN',
				'PRIVATE_KEY', 'client_secret',
				'OAUTH', 'oauth_token', 'refresh_token',
				'password', 'secret', 'token', 'credential',
			);
			foreach ( $info as $section_key => &$section ) {
				if ( ! isset( $section['fields'] ) ) {
					continue;
				}
				foreach ( $section['fields'] as $field_key => &$field ) {
					foreach ( $sensitive_keys as $sensitive ) {
						if ( stripos( $field_key, $sensitive ) !== false ||
							 ( isset( $field['label'] ) && stripos( $field['label'], $sensitive ) !== false ) ) {
							$field['value'] = '[REDACTED]';
							break;
						}
					}
				}
			}
			unset( $section, $field );

			if ( ! empty( $params['section'] ) ) {
				$section = sanitize_text_field( $params['section'] );
				if ( ! isset( $info[ $section ] ) ) {
					return wp_abilities_error( 'not_found', "Section '{$section}' not found. Available: " . implode( ', ', array_keys( $info ) ) );
				}
				return array( 'section' => $section, 'data' => $info[ $section ] );
			}

			$summary = array();
			foreach ( $info as $key => $section ) {
				$summary[ $key ] = array(
					'label'       => $section['label'] ?? $key,
					'field_count' => count( $section['fields'] ?? array() ),
				);
			}
			return array( 'sections' => $summary, 'available_sections' => array_keys( $info ) );
		},
	));
});
