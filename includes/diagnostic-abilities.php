<?php
/**
 * Diagnostic Abilities — Compiled Scripts
 *
 * Single-call diagnostic reports that cross-correlate data from multiple modules.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {
	$reg = new Abilities_For_AI_Registrar( 'diagnostic', 'manage_options' );

	$reg->read( 'diagnostic/site-overview', array(
		'label'       => 'Site Overview Diagnostic',
		'description' => 'Compiled single-call site diagnostic. Combines environment, health, plugins, theme, cache, cron, settings, content, and abilities status into one structured report with cross-correlated diagnostic flags.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'sections' => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Optional section filter. Values: environment, health, plugins, theme, cache, cron, settings, content, abilities. Omit for full diagnostic.',
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'generated_at' => array( 'type' => 'string' ),
			'flags'        => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => 'abilities_for_ai_diagnostic_site_overview',
	));
});

/**
 * Compiled site overview diagnostic callback.
 *
 * @param array|null $input Optional input with 'sections' filter.
 * @return array Diagnostic report.
 */
function abilities_for_ai_diagnostic_site_overview( $input = null ) {
	$result  = array( 'generated_at' => gmdate( 'Y-m-d H:i:s' ) );
	$flags   = array();
	$include = ! empty( $input['sections'] ) ? $input['sections'] : null;

	// --- Environment ---
	if ( ! $include || in_array( 'environment', $include, true ) ) {
		try {
			$result['environment'] = abilities_for_ai_diagnostic_environment();

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && wp_get_environment_type() === 'production' ) {
				$flags[] = array( 'severity' => 'warning', 'area' => 'environment', 'message' => 'WP_DEBUG is enabled in production environment.' );
			}
		} catch ( \Throwable $e ) {
			$result['environment'] = array( 'error' => $e->getMessage() );
		}
	}

	// --- Health ---
	if ( ! $include || in_array( 'health', $include, true ) ) {
		try {
			$result['health'] = abilities_for_ai_diagnostic_health();

			if ( ( $result['health']['critical'] ?? 0 ) > 0 ) {
				$flags[] = array( 'severity' => 'critical', 'area' => 'health', 'message' => sprintf( '%d critical site health issue(s) detected.', $result['health']['critical'] ) );
			}
		} catch ( \Throwable $e ) {
			$result['health'] = array( 'error' => $e->getMessage() );
		}
	}

	// --- Plugins ---
	if ( ! $include || in_array( 'plugins', $include, true ) ) {
		try {
			$result['plugins'] = abilities_for_ai_diagnostic_plugins();

			if ( ( $result['plugins']['active_count'] ?? 0 ) > 20 ) {
				$flags[] = array( 'severity' => 'info', 'area' => 'plugins', 'message' => sprintf( 'High active plugin count: %d.', $result['plugins']['active_count'] ) );
			}
		} catch ( \Throwable $e ) {
			$result['plugins'] = array( 'error' => $e->getMessage() );
		}
	}

	// --- Theme ---
	if ( ! $include || in_array( 'theme', $include, true ) ) {
		try {
			$result['theme'] = abilities_for_ai_diagnostic_theme();

			global $wp_version;
			if ( ! ( $result['theme']['block_theme'] ?? false ) && version_compare( $wp_version, '6.5', '>=' ) ) {
				$flags[] = array( 'severity' => 'info', 'area' => 'theme', 'message' => 'Classic theme on WP 6.5+. Consider a block theme for full Site Editor support.' );
			}
		} catch ( \Throwable $e ) {
			$result['theme'] = array( 'error' => $e->getMessage() );
		}
	}

	// --- Cache ---
	if ( ! $include || in_array( 'cache', $include, true ) ) {
		try {
			$result['cache'] = abilities_for_ai_diagnostic_cache();

			if ( empty( $result['cache']['persistent_cache'] ) ) {
				$flags[] = array( 'severity' => 'warning', 'area' => 'cache', 'message' => 'No persistent object cache detected.' );
			}
		} catch ( \Throwable $e ) {
			$result['cache'] = array( 'error' => $e->getMessage() );
		}
	}

	// --- Cron ---
	if ( ! $include || in_array( 'cron', $include, true ) ) {
		try {
			$result['cron'] = abilities_for_ai_diagnostic_cron();

			if ( ( $result['cron']['overdue_count'] ?? 0 ) > 0 ) {
				$flags[] = array( 'severity' => 'warning', 'area' => 'cron', 'message' => sprintf( '%d overdue cron event(s).', $result['cron']['overdue_count'] ) );
			}
		} catch ( \Throwable $e ) {
			$result['cron'] = array( 'error' => $e->getMessage() );
		}
	}

	// --- Settings ---
	if ( ! $include || in_array( 'settings', $include, true ) ) {
		try {
			$result['settings'] = abilities_for_ai_diagnostic_settings();

			if ( ( $result['settings']['blog_public'] ?? true ) === false ) {
				$flags[] = array( 'severity' => 'warning', 'area' => 'settings', 'message' => 'Search engines are discouraged from indexing this site.' );
			}
			if ( ! empty( $result['settings']['users_can_register'] ) ) {
				$flags[] = array( 'severity' => 'info', 'area' => 'settings', 'message' => 'User registration is open.' );
			}
		} catch ( \Throwable $e ) {
			$result['settings'] = array( 'error' => $e->getMessage() );
		}
	}

	// --- Content ---
	if ( ! $include || in_array( 'content', $include, true ) ) {
		try {
			$result['content'] = abilities_for_ai_diagnostic_content();
		} catch ( \Throwable $e ) {
			$result['content'] = array( 'error' => $e->getMessage() );
		}
	}

	// --- Abilities ---
	if ( ! $include || in_array( 'abilities', $include, true ) ) {
		try {
			$result['abilities'] = abilities_for_ai_diagnostic_abilities();

			if ( empty( $result['abilities']['pro_active'] ) ) {
				$flags[] = array( 'severity' => 'info', 'area' => 'abilities', 'message' => 'Abilities for AI Pro license is not active.' );
			}
		} catch ( \Throwable $e ) {
			$result['abilities'] = array( 'error' => $e->getMessage() );
		}
	}

	$result['flags'] = $flags;

	return $result;
}

// ============================================================
// Section helpers — each returns a plain array.
// ============================================================

/**
 * Environment section.
 */
function abilities_for_ai_diagnostic_environment() {
	global $wpdb;

	return array(
		'wp_version'       => get_bloginfo( 'version' ),
		'php_version'      => PHP_VERSION,
		'mysql_version'    => $wpdb->db_version(),
		'multisite'        => is_multisite(),
		'site_url'         => get_site_url(),
		'memory_limit'     => defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : ini_get( 'memory_limit' ),
		'timezone'         => wp_timezone_string(),
		'language'         => get_locale(),
		'debug_mode'       => defined( 'WP_DEBUG' ) && WP_DEBUG,
		'environment_type' => wp_get_environment_type(),
	);
}

/**
 * Health section.
 */
function abilities_for_ai_diagnostic_health() {
	$counts = array( 'good' => 0, 'recommended' => 0, 'critical' => 0, 'critical_issues' => array() );

	$cached = get_transient( 'health-check-site-status-result' );
	if ( $cached ) {
		$parsed = json_decode( $cached, true );
		if ( is_array( $parsed ) ) {
			$counts['good']        = intval( $parsed['good'] ?? 0 );
			$counts['recommended'] = intval( $parsed['recommended'] ?? 0 );
			$counts['critical']    = intval( $parsed['critical'] ?? 0 );
		}
	}

	// If critical issues exist, run direct tests to get details.
	if ( $counts['critical'] > 0 ) {
		if ( ! class_exists( 'WP_Site_Health' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
		}
		if ( ! function_exists( 'get_core_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		if ( ! function_exists( 'wp_check_php_version' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}

		$health = WP_Site_Health::get_instance();
		$tests  = WP_Site_Health::get_tests();

		foreach ( $tests['direct'] ?? array() as $key => $test ) {
			try {
				$test_result = null;
				if ( is_callable( $test['test'] ) ) {
					$test_result = call_user_func( $test['test'] );
				} elseif ( is_string( $test['test'] ) ) {
					$method = $test['test'];
					if ( ! method_exists( $health, $method ) ) {
						$method = 'get_test_' . $method;
					}
					if ( method_exists( $health, $method ) ) {
						$test_result = call_user_func( array( $health, $method ) );
					}
				}
				if ( $test_result && ( $test_result['status'] ?? '' ) === 'critical' ) {
					$counts['critical_issues'][] = array(
						'test'  => $key,
						'label' => $test_result['label'] ?? $key,
					);
				}
			} catch ( \Throwable $e ) {
				// Skip failing tests — partial failure is fine.
				continue;
			}
		}
	}

	return $counts;
}

/**
 * Plugins section.
 */
function abilities_for_ai_diagnostic_plugins() {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$all_plugins    = get_plugins();
	$active_slugs   = get_option( 'active_plugins', array() );
	$mu_plugins     = get_mu_plugins();

	$active = array();
	foreach ( $active_slugs as $file ) {
		if ( isset( $all_plugins[ $file ] ) ) {
			$active[] = array(
				'name'    => $all_plugins[ $file ]['Name'],
				'version' => $all_plugins[ $file ]['Version'],
				'file'    => $file,
			);
		}
	}

	return array(
		'active_count'  => count( $active ),
		'total_count'   => count( $all_plugins ),
		'must_use_count' => count( $mu_plugins ),
		'active'        => $active,
	);
}

/**
 * Theme section.
 */
function abilities_for_ai_diagnostic_theme() {
	$theme = wp_get_theme();

	return array(
		'name'         => $theme->get( 'Name' ),
		'version'      => $theme->get( 'Version' ),
		'parent'       => $theme->parent() ? $theme->parent()->get( 'Name' ) : null,
		'block_theme'  => $theme->is_block_theme(),
		'requires_wp'  => $theme->get( 'RequiresWP' ) ?: null,
		'requires_php' => $theme->get( 'RequiresPHP' ) ?: null,
	);
}

/**
 * Cache section.
 */
function abilities_for_ai_diagnostic_cache() {
	$drop_in_exists = file_exists( WP_CONTENT_DIR . '/object-cache.php' );
	$persistent     = wp_using_ext_object_cache();
	$class_name     = null;

	if ( $drop_in_exists && isset( $GLOBALS['wp_object_cache'] ) ) {
		$class_name = get_class( $GLOBALS['wp_object_cache'] );
	}

	return array(
		'persistent_cache'   => $persistent,
		'drop_in_exists'     => $drop_in_exists,
		'object_cache_class' => $class_name,
	);
}

/**
 * Cron section.
 */
function abilities_for_ai_diagnostic_cron() {
	$crons = _get_cron_array();
	if ( ! $crons ) {
		return array( 'total_events' => 0, 'overdue_count' => 0, 'overdue_events' => array(), 'schedules' => array() );
	}

	$now           = time();
	$total         = 0;
	$overdue       = array();
	$schedule_names = array();

	foreach ( $crons as $timestamp => $hooks ) {
		if ( ! is_array( $hooks ) ) {
			continue;
		}
		foreach ( $hooks as $hook => $entries ) {
			if ( ! is_array( $entries ) ) {
				continue;
			}
			foreach ( $entries as $data ) {
				$total++;
				$sched = $data['schedule'] ?? 'single';
				if ( $sched && $sched !== 'single' && ! in_array( $sched, $schedule_names, true ) ) {
					$schedule_names[] = $sched;
				}
				if ( (int) $timestamp < $now ) {
					$overdue[] = array(
						'hook'      => $hook,
						'scheduled' => gmdate( 'Y-m-d H:i:s', $timestamp ),
						'overdue_by' => $now - (int) $timestamp,
					);
				}
			}
		}
	}

	// Cap overdue list to 10 to keep output compact.
	$overdue_count = count( $overdue );
	if ( $overdue_count > 10 ) {
		$overdue = array_slice( $overdue, 0, 10 );
	}

	return array(
		'total_events'  => $total,
		'overdue_count' => $overdue_count,
		'overdue_events' => $overdue,
		'schedules'     => $schedule_names,
	);
}

/**
 * Settings section.
 */
function abilities_for_ai_diagnostic_settings() {
	$permalink = get_option( 'permalink_structure', '' );

	return array(
		'permalink_structure' => $permalink,
		'using_permalinks'    => ! empty( $permalink ),
		'blog_public'         => (bool) get_option( 'blog_public', '1' ),
		'comments_open'       => get_option( 'default_comment_status', 'open' ) === 'open',
		'users_can_register'  => (bool) get_option( 'users_can_register', false ),
	);
}

/**
 * Content section.
 */
function abilities_for_ai_diagnostic_content() {
	$public_types = get_post_types( array( 'public' => true ), 'names' );
	$content      = array();

	foreach ( $public_types as $type ) {
		$counts  = wp_count_posts( $type );
		$publish = (int) ( $counts->publish ?? 0 );
		$draft   = (int) ( $counts->draft ?? 0 );
		$total   = $publish + $draft + (int) ( $counts->pending ?? 0 ) + (int) ( $counts->private ?? 0 );

		if ( $total > 0 ) {
			$content[ $type ] = array(
				'publish' => $publish,
				'draft'   => $draft,
				'total'   => $total,
			);
		}
	}

	return $content;
}

/**
 * Abilities section.
 */
function abilities_for_ai_diagnostic_abilities() {
	$pro_active = Abilities_For_AI_License_Manager::is_pro_active();

	$total = 0;
	if ( function_exists( 'wp_get_abilities' ) ) {
		$all = wp_get_abilities();
		$total = is_array( $all ) ? count( $all ) : 0;
	}

	$perms   = get_option( 'abilities_for_ai_permissions', array() );
	$enabled = array();
	foreach ( $perms as $module => $ops ) {
		if ( $module === '_overrides' ) {
			continue;
		}
		if ( ! empty( $ops['read'] ) ) {
			$enabled[] = $module;
		}
	}

	return array(
		'plugin_version'  => ABILITIES_FOR_AI_VERSION,
		'pro_active'      => $pro_active,
		'total_abilities' => $total,
		'enabled_modules' => $enabled,
	);
}
