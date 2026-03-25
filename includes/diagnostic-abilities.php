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

	if ( is_multisite() ) {
		$reg->read( 'diagnostic/network-overview', array(
			'label'       => 'Network Overview Diagnostic',
			'description' => 'Compiled single-call multisite network diagnostic. Runs site-overview across all subsites with cross-site correlation flags. Only available on multisite installations.',
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'sections' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => 'Optional section filter per subsite. Same values as site-overview: environment, health, plugins, theme, cache, cron, settings, content, abilities.',
					),
					'sites' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'integer' ),
						'description' => 'Optional: only diagnose these blog IDs. Omit for all sites in the network.',
					),
					'max_sites' => array(
						'type'        => 'integer',
						'description' => 'Maximum sites to diagnose (default: 20, max: 50). Prevents timeout on large networks.',
					),
				),
			),
			'output_schema' => abilities_for_ai_schema_item_output( array(
				'generated_at'  => array( 'type' => 'string' ),
				'network'       => array( 'type' => 'object' ),
				'sites'         => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
				'network_flags' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			) ),
			'callback' => 'abilities_for_ai_diagnostic_network_overview',
		));
	}

	$reg->read( 'diagnostic/taxonomy-health', array(
		'label'       => 'Taxonomy Health Diagnostic',
		'description' => 'Compiled single-call taxonomy health assessment. Discovers all taxonomies, counts terms and content assignments, identifies empty terms, orphan terms, deep hierarchies, and overlapping terms.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'taxonomies' => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Optional: only analyze these taxonomy slugs. Omit for all public taxonomies.',
				),
				'max_terms_per_taxonomy' => array(
					'type'        => 'integer',
					'description' => 'Maximum terms to analyze per taxonomy (default: 200, max: 500).',
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'generated_at' => array( 'type' => 'string' ),
			'taxonomies'   => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'flags'        => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => 'abilities_for_ai_diagnostic_taxonomy_health',
	));
});

// ============================================================
// Orchestrator helpers
// ============================================================

/**
 * Gather diagnostic sections and per-site flags for the current blog context.
 *
 * Shared by site-overview (single site) and network-overview (per-subsite loop).
 *
 * @param array|null $input Optional input with 'sections' filter.
 * @return array { 'sections' => [...], 'flags' => [...] }
 */
function abilities_for_ai_diagnostic_gather_sections( $input = null ) {
	$sections = array();
	$flags    = array();
	$include  = ! empty( $input['sections'] ) ? $input['sections'] : null;

	// --- Environment ---
	if ( ! $include || in_array( 'environment', $include, true ) ) {
		try {
			$sections['environment'] = abilities_for_ai_diagnostic_environment();

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && wp_get_environment_type() === 'production' ) {
				$flags[] = array( 'severity' => 'warning', 'area' => 'environment', 'message' => 'WP_DEBUG is enabled in production environment.' );
			}
		} catch ( \Throwable $e ) {
			$sections['environment'] = array( 'error' => $e->getMessage() );
		}
	}

	// --- Health ---
	if ( ! $include || in_array( 'health', $include, true ) ) {
		try {
			$sections['health'] = abilities_for_ai_diagnostic_health();

			if ( ( $sections['health']['critical'] ?? 0 ) > 0 ) {
				$flags[] = array( 'severity' => 'critical', 'area' => 'health', 'message' => sprintf( '%d critical site health issue(s) detected.', $sections['health']['critical'] ) );
			}
		} catch ( \Throwable $e ) {
			$sections['health'] = array( 'error' => $e->getMessage() );
		}
	}

	// --- Plugins ---
	if ( ! $include || in_array( 'plugins', $include, true ) ) {
		try {
			$sections['plugins'] = abilities_for_ai_diagnostic_plugins();

			if ( ( $sections['plugins']['active_count'] ?? 0 ) > 20 ) {
				$flags[] = array( 'severity' => 'info', 'area' => 'plugins', 'message' => sprintf( 'High active plugin count: %d.', $sections['plugins']['active_count'] ) );
			}
		} catch ( \Throwable $e ) {
			$sections['plugins'] = array( 'error' => $e->getMessage() );
		}
	}

	// --- Theme ---
	if ( ! $include || in_array( 'theme', $include, true ) ) {
		try {
			$sections['theme'] = abilities_for_ai_diagnostic_theme();

			global $wp_version;
			if ( ! ( $sections['theme']['block_theme'] ?? false ) && version_compare( $wp_version, '6.5', '>=' ) ) {
				$flags[] = array( 'severity' => 'info', 'area' => 'theme', 'message' => 'Classic theme on WP 6.5+. Consider a block theme for full Site Editor support.' );
			}
		} catch ( \Throwable $e ) {
			$sections['theme'] = array( 'error' => $e->getMessage() );
		}
	}

	// --- Cache ---
	if ( ! $include || in_array( 'cache', $include, true ) ) {
		try {
			$sections['cache'] = abilities_for_ai_diagnostic_cache();

			if ( empty( $sections['cache']['persistent_cache'] ) ) {
				$flags[] = array( 'severity' => 'warning', 'area' => 'cache', 'message' => 'No persistent object cache detected.' );
			}
		} catch ( \Throwable $e ) {
			$sections['cache'] = array( 'error' => $e->getMessage() );
		}
	}

	// --- Cron ---
	if ( ! $include || in_array( 'cron', $include, true ) ) {
		try {
			$sections['cron'] = abilities_for_ai_diagnostic_cron();

			if ( ( $sections['cron']['overdue_count'] ?? 0 ) > 0 ) {
				$flags[] = array( 'severity' => 'warning', 'area' => 'cron', 'message' => sprintf( '%d overdue cron event(s).', $sections['cron']['overdue_count'] ) );
			}
		} catch ( \Throwable $e ) {
			$sections['cron'] = array( 'error' => $e->getMessage() );
		}
	}

	// --- Settings ---
	if ( ! $include || in_array( 'settings', $include, true ) ) {
		try {
			$sections['settings'] = abilities_for_ai_diagnostic_settings();

			if ( ( $sections['settings']['blog_public'] ?? true ) === false ) {
				$flags[] = array( 'severity' => 'warning', 'area' => 'settings', 'message' => 'Search engines are discouraged from indexing this site.' );
			}
			if ( ! empty( $sections['settings']['users_can_register'] ) ) {
				$flags[] = array( 'severity' => 'info', 'area' => 'settings', 'message' => 'User registration is open.' );
			}
		} catch ( \Throwable $e ) {
			$sections['settings'] = array( 'error' => $e->getMessage() );
		}
	}

	// --- Content ---
	if ( ! $include || in_array( 'content', $include, true ) ) {
		try {
			$sections['content'] = abilities_for_ai_diagnostic_content();
		} catch ( \Throwable $e ) {
			$sections['content'] = array( 'error' => $e->getMessage() );
		}
	}

	// --- Abilities ---
	if ( ! $include || in_array( 'abilities', $include, true ) ) {
		try {
			$sections['abilities'] = abilities_for_ai_diagnostic_abilities();

			if ( empty( $sections['abilities']['pro_active'] ) ) {
				$flags[] = array( 'severity' => 'info', 'area' => 'abilities', 'message' => 'Abilities for AI Pro license is not active.' );
			}
		} catch ( \Throwable $e ) {
			$sections['abilities'] = array( 'error' => $e->getMessage() );
		}
	}

	return array( 'sections' => $sections, 'flags' => $flags );
}

// ============================================================
// Ability callbacks
// ============================================================

/**
 * Compiled site overview diagnostic callback.
 *
 * @param array|null $input Optional input with 'sections' filter.
 * @return array Diagnostic report.
 */
function abilities_for_ai_diagnostic_site_overview( $input = null ) {
	$data = abilities_for_ai_diagnostic_gather_sections( $input );

	return array_merge(
		array( 'generated_at' => gmdate( 'Y-m-d H:i:s' ) ),
		$data['sections'],
		array( 'flags' => $data['flags'] ),
	);
}

/**
 * Compiled network overview diagnostic callback.
 *
 * @param array|null $input Optional input with 'sections', 'sites', 'max_sites' filters.
 * @return array|WP_Error Network diagnostic report.
 */
function abilities_for_ai_diagnostic_network_overview( $input = null ) {
	if ( ! is_multisite() ) {
		return new WP_Error( 'not_multisite', 'This ability is only available on multisite installations.' );
	}

	$max_sites   = min( intval( $input['max_sites'] ?? 20 ), 50 );
	$site_filter = ! empty( $input['sites'] ) ? array_map( 'intval', $input['sites'] ) : null;

	$site_args = array(
		'number'  => $max_sites,
		'orderby' => 'id',
		'order'   => 'ASC',
	);
	if ( $site_filter ) {
		$site_args['site__in'] = $site_filter;
	}

	$sites = get_sites( $site_args );

	$result = array(
		'generated_at' => gmdate( 'Y-m-d H:i:s' ),
		'network'      => abilities_for_ai_diagnostic_network_info( count( $sites ) ),
		'sites'        => array(),
	);

	$all_site_data = array();

	foreach ( $sites as $site ) {
		$blog_id = (int) $site->blog_id;

		switch_to_blog( $blog_id );

		try {
			$data       = abilities_for_ai_diagnostic_gather_sections( $input );
			$site_entry = array(
				'blog_id' => $blog_id,
				'url'     => get_site_url(),
				'name'    => get_bloginfo( 'name' ),
			);
			$site_entry = array_merge( $site_entry, $data['sections'] );
			$site_entry['flags'] = $data['flags'];

			$result['sites'][] = $site_entry;
			$all_site_data[]   = $site_entry;
		} catch ( \Throwable $e ) {
			$result['sites'][] = array(
				'blog_id' => $blog_id,
				'url'     => get_site_url(),
				'error'   => $e->getMessage(),
			);
		}

		restore_current_blog();
	}

	$result['network_flags'] = abilities_for_ai_diagnostic_network_flags( $all_site_data );

	return $result;
}

// ============================================================
// Network helpers
// ============================================================

/**
 * Network-level summary info.
 *
 * @param int $diagnosed_count Number of sites diagnosed in this run.
 * @return array Network info.
 */
function abilities_for_ai_diagnostic_network_info( $diagnosed_count ) {
	return array(
		'site_count'      => get_blog_count(),
		'diagnosed_count' => $diagnosed_count,
		'wp_version'      => get_bloginfo( 'version' ),
		'php_version'     => PHP_VERSION,
		'network_name'    => get_site_option( 'site_name' ),
		'registration'    => get_site_option( 'registration', 'none' ),
		'network_plugins' => count( get_site_option( 'active_sitewide_plugins', array() ) ),
	);
}

/**
 * Cross-site correlation flags — intelligence that only exists when comparing subsites.
 *
 * @param array $sites Array of per-site diagnostic data.
 * @return array Network-level flags.
 */
function abilities_for_ai_diagnostic_network_flags( $sites ) {
	$flags = array();

	if ( count( $sites ) < 2 ) {
		return $flags;
	}

	// Theme version mismatch across subsites.
	$themes = array();
	foreach ( $sites as $s ) {
		if ( isset( $s['theme']['name'], $s['theme']['version'] ) ) {
			$key = $s['theme']['name'];
			$themes[ $key ][] = array( 'url' => $s['url'], 'version' => $s['theme']['version'] );
		}
	}
	foreach ( $themes as $name => $instances ) {
		$versions = array_unique( array_column( $instances, 'version' ) );
		if ( count( $versions ) > 1 ) {
			$flags[] = array(
				'severity' => 'warning',
				'area'     => 'theme',
				'message'  => sprintf( 'Theme "%s" has version mismatch across subsites: %s.', $name, implode( ', ', $versions ) ),
			);
		}
	}

	// Cache inconsistency — some sites have persistent cache, others don't.
	$cache_states = array();
	foreach ( $sites as $s ) {
		if ( isset( $s['cache']['persistent_cache'] ) ) {
			$cache_states[ $s['url'] ] = ! empty( $s['cache']['persistent_cache'] );
		}
	}
	$has_cache = array_filter( $cache_states );
	$no_cache  = array_diff_key( $cache_states, $has_cache );
	if ( ! empty( $has_cache ) && ! empty( $no_cache ) ) {
		$flags[] = array(
			'severity' => 'warning',
			'area'     => 'cache',
			'message'  => sprintf( 'Inconsistent object cache: %d site(s) have it, %d do not.', count( $has_cache ), count( $no_cache ) ),
		);
	}

	// Sites with critical health issues.
	$critical_sites = array();
	foreach ( $sites as $s ) {
		if ( ( $s['health']['critical'] ?? 0 ) > 0 ) {
			$critical_sites[] = $s['url'];
		}
	}
	if ( ! empty( $critical_sites ) ) {
		$flags[] = array(
			'severity' => 'critical',
			'area'     => 'health',
			'message'  => sprintf( '%d subsite(s) have critical health issues: %s.', count( $critical_sites ), implode( ', ', $critical_sites ) ),
		);
	}

	// Content distribution — identify empty subsites.
	foreach ( $sites as $s ) {
		$total_content = 0;
		if ( isset( $s['content'] ) && is_array( $s['content'] ) && ! isset( $s['content']['error'] ) ) {
			foreach ( $s['content'] as $type => $counts ) {
				if ( is_array( $counts ) ) {
					$total_content += $counts['total'] ?? 0;
				}
			}
		}
		if ( $total_content === 0 && isset( $s['content'] ) && ! isset( $s['content']['error'] ) ) {
			$flags[] = array(
				'severity' => 'info',
				'area'     => 'content',
				'message'  => sprintf( 'Subsite %s has no content.', $s['url'] ),
			);
		}
	}

	// Plugin count variance — flag if one subsite has significantly more active plugins.
	$plugin_counts = array();
	foreach ( $sites as $s ) {
		if ( isset( $s['plugins']['active_count'] ) ) {
			$plugin_counts[ $s['url'] ] = $s['plugins']['active_count'];
		}
	}
	if ( count( $plugin_counts ) >= 2 ) {
		$avg = array_sum( $plugin_counts ) / count( $plugin_counts );
		foreach ( $plugin_counts as $url => $count ) {
			if ( $count > $avg * 2 && $count > 5 ) {
				$flags[] = array(
					'severity' => 'info',
					'area'     => 'plugins',
					'message'  => sprintf( '%s has %d active plugins — significantly above network average (%.0f).', $url, $count, $avg ),
				);
			}
		}
	}

	return $flags;
}

// ============================================================
// Taxonomy health diagnostic
// ============================================================

/**
 * Compiled taxonomy health diagnostic callback.
 *
 * @param array|null $input Optional input with 'taxonomies' and 'max_terms_per_taxonomy'.
 * @return array Taxonomy health report.
 */
function abilities_for_ai_diagnostic_taxonomy_health( $input = null ) {
	$result    = array( 'generated_at' => gmdate( 'Y-m-d H:i:s' ) );
	$flags     = array();
	$max_terms = min( intval( $input['max_terms_per_taxonomy'] ?? 200 ), 500 );
	$tax_filter = ! empty( $input['taxonomies'] ) ? $input['taxonomies'] : null;

	$all_taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
	$tax_reports    = array();

	foreach ( $all_taxonomies as $slug => $tax_obj ) {
		if ( $tax_filter && ! in_array( $slug, $tax_filter, true ) ) {
			continue;
		}

		try {
			$total_terms     = (int) wp_count_terms( array( 'taxonomy' => $slug, 'hide_empty' => false ) );
			$non_empty_terms = (int) wp_count_terms( array( 'taxonomy' => $slug, 'hide_empty' => true ) );
			$empty_terms     = $total_terms - $non_empty_terms;

			$report = array(
				'slug'            => $slug,
				'label'           => $tax_obj->label,
				'hierarchical'    => $tax_obj->hierarchical,
				'post_types'      => (array) $tax_obj->object_type,
				'total_terms'     => $total_terms,
				'non_empty_terms' => $non_empty_terms,
				'empty_terms'     => $empty_terms,
			);

			// Detailed term analysis (bounded).
			$terms = array();
			$max_depth = 0;
			$single_post_count = 0;

			if ( $total_terms > 0 ) {
				$term_objects = get_terms( array(
					'taxonomy'   => $slug,
					'hide_empty' => false,
					'number'     => $max_terms,
				) );

				if ( ! is_wp_error( $term_objects ) ) {
					foreach ( $term_objects as $term ) {
						// Only include empty/orphan terms or a summary.
						if ( (int) $term->count === 0 ) {
							$terms[] = array(
								'name'   => $term->name,
								'slug'   => $term->slug,
								'count'  => 0,
								'parent' => (int) $term->parent,
							);
						}
						if ( (int) $term->count === 1 ) {
							$single_post_count++;
						}

						// Hierarchy depth.
						if ( $tax_obj->hierarchical && (int) $term->parent > 0 ) {
							$depth  = 1;
							$parent = (int) $term->parent;
							while ( $parent > 0 && $depth < 10 ) {
								$parent_term = get_term( $parent, $slug );
								if ( ! $parent_term || is_wp_error( $parent_term ) ) {
									break;
								}
								$parent = (int) $parent_term->parent;
								$depth++;
							}
							$max_depth = max( $max_depth, $depth );
						}
					}
				}
			}

			$report['max_depth'] = $tax_obj->hierarchical ? $max_depth : null;
			// Cap empty term list to 20 for compact output.
			$report['empty_term_list'] = array_slice( $terms, 0, 20 );

			$tax_reports[] = $report;

			// --- Flags ---
			if ( $total_terms === 0 ) {
				$flags[] = array( 'severity' => 'info', 'area' => $slug, 'message' => sprintf( 'Taxonomy "%s" has no terms (unused).', $tax_obj->label ) );
			}
			if ( $total_terms > 0 && $empty_terms / $total_terms > 0.5 ) {
				$flags[] = array( 'severity' => 'warning', 'area' => $slug, 'message' => sprintf( 'High orphan ratio in "%s": %d of %d terms have no posts (%.0f%%).', $tax_obj->label, $empty_terms, $total_terms, ( $empty_terms / $total_terms ) * 100 ) );
			}
			if ( $max_depth > 3 ) {
				$flags[] = array( 'severity' => 'info', 'area' => $slug, 'message' => sprintf( 'Deep hierarchy in "%s": %d levels.', $tax_obj->label, $max_depth ) );
			}
			if ( $total_terms > 0 && $single_post_count / $total_terms > 0.3 ) {
				$flags[] = array( 'severity' => 'info', 'area' => $slug, 'message' => sprintf( 'Many single-post terms in "%s": %d of %d (%.0f%%).', $tax_obj->label, $single_post_count, $total_terms, ( $single_post_count / $total_terms ) * 100 ) );
			}
			if ( $total_terms > 100 ) {
				$flags[] = array( 'severity' => 'info', 'area' => $slug, 'message' => sprintf( 'Large taxonomy "%s": %d terms.', $tax_obj->label, $total_terms ) );
			}

			// Uncategorized default check.
			if ( $slug === 'category' ) {
				$uncat = get_term_by( 'slug', 'uncategorized', 'category' );
				if ( $uncat && (int) $uncat->count > 0 ) {
					$flags[] = array( 'severity' => 'info', 'area' => 'category', 'message' => sprintf( 'Default "Uncategorized" category has %d post(s).', $uncat->count ) );
				}
			}
		} catch ( \Throwable $e ) {
			$tax_reports[] = array( 'slug' => $slug, 'error' => $e->getMessage() );
		}
	}

	$result['taxonomies'] = $tax_reports;
	$result['flags']      = $flags;

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
