<?php
/**
 * Site Health Abilities
 *
 * WordPress site health diagnostics (read-only).
 *
 * @package WordPress_Native_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', 'wp_native_register_site_health_abilities' );

function wp_native_register_site_health_abilities() {

	$perms = wp_abilities_suite_get_permissions( 'site-health' );

	// ===== SITE-HEALTH — READ =====
	if ( $perms['read'] ) {

	// ---- site-health/status ----
	wp_register_ability( 'site-health/status', array(
		'label'       => 'Site Health Status',
		'description' => 'Get the overall site health status (good, recommended, critical counts).',
		'category'    => 'site-health',
		'input_schema' => array(
			'type'       => 'object',
		),
		'execute_callback' => function() {
			if ( ! class_exists( 'WP_Site_Health' ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
			}
			$health = WP_Site_Health::get_instance();
			$tests  = WP_Site_Health::get_tests();

			$counts = array( 'good' => 0, 'recommended' => 0, 'critical' => 0 );
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
				'status' => $counts,
				'total_direct_tests'  => count( $tests['direct'] ?? array() ),
				'total_async_tests'   => count( $tests['async'] ?? array() ),
			);
		},
		'permission_callback' => function() { return current_user_can( 'view_site_health_checks' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) , 'tier' => 'free',),
	));

	// ---- site-health/list-tests ----
	wp_register_ability( 'site-health/list-tests', array(
		'label'       => 'List Health Tests',
		'description' => 'List all available site health tests (direct and async).',
		'category'    => 'site-health',
		'input_schema' => array(
			'type'       => 'object',
		),
		'execute_callback' => function() {
			if ( ! class_exists( 'WP_Site_Health' ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
			}
			$tests = WP_Site_Health::get_tests();
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
		'permission_callback' => function() { return current_user_can( 'view_site_health_checks' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) , 'tier' => 'free',),
	));

	// ---- site-health/run-test ----
	wp_register_ability( 'site-health/run-test', array(
		'label'       => 'Run Health Test',
		'description' => 'Run a specific direct site health test and return the result.',
		'category'    => 'site-health',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'test' => array( 'type' => 'string', 'description' => 'Test key (from list-tests output)' ),
			),
			'required' => array( 'test' ),
		),
		'execute_callback' => function( $params ) {
			if ( ! class_exists( 'WP_Site_Health' ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
			}
			$test_key = sanitize_text_field( $params['test'] ?? '' );
			$tests    = WP_Site_Health::get_tests();
			$direct   = $tests['direct'] ?? array();

			if ( ! isset( $direct[ $test_key ] ) ) {
				return wp_abilities_error( 'not_found', "Test '{$test_key}' not found in direct tests." );
			}

			$test = $direct[ $test_key ];
			$result = null;

			if ( is_callable( $test['test'] ) ) {
				$result = call_user_func( $test['test'] );
			} elseif ( is_string( $test['test'] ) ) {
				$health = WP_Site_Health::get_instance();
				if ( method_exists( $health, $test['test'] ) ) {
					$result = call_user_func( array( $health, $test['test'] ) );
				}
			}

			if ( ! $result ) {
				return wp_abilities_error( 'test_failed', "Could not execute test '{$test_key}'." );
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
		'permission_callback' => function() { return current_user_can( 'view_site_health_checks' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) , 'tier' => 'free',),
	));

	// ---- site-health/info ----
	wp_register_ability( 'site-health/info', array(
		'label'       => 'Site Health Info',
		'description' => 'Get comprehensive debug information (PHP, DB, server, WordPress versions, active plugins, theme).',
		'category'    => 'site-health',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'section' => array(
					'type'        => 'string',
					'description' => 'Specific section to return (e.g. "wp-core", "wp-server", "wp-database", "wp-plugins-active"). Omit for all.',
				),
			),
		),
		'execute_callback' => function( $params ) {
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
				// SMTP & mail credentials
				'SMTP_PASSWORD', 'smtp_pass', 'mail_password',
				// API keys & tokens
				'API_KEY', 'api_secret', 'SECRET_KEY', 'ACCESS_TOKEN',
				'PRIVATE_KEY', 'client_secret',
				// OAuth
				'OAUTH', 'oauth_token', 'refresh_token',
				// Generic sensitive patterns
				'password', 'secret', 'token', 'credential',
			);
			foreach ( $info as $section_key => &$section ) {
				if ( ! isset( $section['fields'] ) ) continue;
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

			// Summarize sections to avoid huge payloads.
			$summary = array();
			foreach ( $info as $key => $section ) {
				$summary[ $key ] = array(
					'label'       => $section['label'] ?? $key,
					'field_count' => count( $section['fields'] ?? array() ),
				);
			}
			return array( 'sections' => $summary, 'available_sections' => array_keys( $info ) );
		},
		'permission_callback' => function() { return current_user_can( 'view_site_health_checks' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) , 'tier' => 'free',),
	));

	} // end read
}
