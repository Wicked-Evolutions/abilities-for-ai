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
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {
	$reg = new Abilities_For_AI_Registrar( 'site-health', 'view_site_health_checks' );

	$reg->read( 'site-health/status', array(
		'label'       => 'Site Health Status',
		'description' => 'Get the overall site health status (good, recommended, critical counts).',
		'output_schema' => abilities_for_ai_schema_item_output( array(
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
		'output_schema' => abilities_for_ai_schema_item_output( array(
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
		'output_schema' => abilities_for_ai_schema_item_output( array(
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

	$reg->read( 'site-health/pulse', array(
		'label'       => 'Site Pulse',
		'description' => 'Single-call site health overview: WordPress version, PHP version, active theme, plugin count, content counts by type, site health status, disk usage estimate, and recent activity. Ideal for quick orientation without multiple tool calls.',
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'wordpress'     => array( 'type' => 'object' ),
			'theme'         => array( 'type' => 'object' ),
			'plugins'       => array( 'type' => 'object' ),
			'content'       => array( 'type' => 'object' ),
			'health'        => array( 'type' => 'object' ),
			'recent'        => array( 'type' => 'object' ),
		) ),
		'callback' => function() {
			// WordPress + PHP.
			$wp = array(
				'version'   => get_bloginfo( 'version' ),
				'php'       => PHP_VERSION,
				'multisite' => is_multisite(),
				'site_url'  => get_site_url(),
				'home_url'  => get_home_url(),
				'language'  => get_locale(),
				'timezone'  => wp_timezone_string(),
				'memory_limit' => WP_MEMORY_LIMIT,
			);

			// Active theme.
			$theme_obj = wp_get_theme();
			$theme = array(
				'name'        => $theme_obj->get( 'Name' ),
				'version'     => $theme_obj->get( 'Version' ),
				'parent'      => $theme_obj->parent() ? $theme_obj->parent()->get( 'Name' ) : null,
				'block_theme' => $theme_obj->is_block_theme(),
			);

			// Plugins.
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$all_plugins    = get_plugins();
			$active_plugins = get_option( 'active_plugins', array() );
			$plugins = array(
				'total'  => count( $all_plugins ),
				'active' => count( $active_plugins ),
			);

			// Content counts by post type.
			$public_types = get_post_types( array( 'public' => true ), 'names' );
			$content = array();
			foreach ( $public_types as $type ) {
				$counts = wp_count_posts( $type );
				$content[ $type ] = array(
					'publish' => (int) ( $counts->publish ?? 0 ),
					'draft'   => (int) ( $counts->draft ?? 0 ),
					'total'   => (int) ( $counts->publish ?? 0 ) + (int) ( $counts->draft ?? 0 ) + (int) ( $counts->pending ?? 0 ) + (int) ( $counts->private ?? 0 ),
				);
			}

			// Health status (cached from site health).
			$health  = array( 'good' => 0, 'recommended' => 0, 'critical' => 0 );
			$cached  = get_transient( 'health-check-site-status-result' );
			if ( $cached ) {
				$parsed = json_decode( $cached, true );
				if ( is_array( $parsed ) ) {
					$health = array(
						'good'        => intval( $parsed['good'] ?? 0 ),
						'recommended' => intval( $parsed['recommended'] ?? 0 ),
						'critical'    => intval( $parsed['critical'] ?? 0 ),
					);
				}
			}

			// Recent activity.
			$recent_posts = get_posts( array(
				'post_type'      => 'any',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 5,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			) );
			$recent = array();
			foreach ( $recent_posts as $rp ) {
				$recent[] = array(
					'id'       => $rp->ID,
					'title'    => $rp->post_title,
					'type'     => $rp->post_type,
					'status'   => $rp->post_status,
					'modified' => $rp->post_modified,
				);
			}

			return array(
				'wordpress' => $wp,
				'theme'     => $theme,
				'plugins'   => $plugins,
				'content'   => $content,
				'health'    => $health,
				'recent'    => $recent,
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
		'output_schema' => abilities_for_ai_schema_item_output( array(
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
