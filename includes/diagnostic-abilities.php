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

	$reg->read( 'diagnostic/security-posture', array(
		'label'       => 'Security Posture Diagnostic',
		'description' => 'Compiled single-call security assessment. Evaluates user configuration, plugin hygiene, exposed settings, authentication state, and filesystem indicators. Read-only and non-invasive.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'sections' => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Optional section filter. Values: users, plugins, settings, authentication, filesystem. Omit for full assessment.',
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'generated_at' => array( 'type' => 'string' ),
			'flags'        => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => 'abilities_for_ai_diagnostic_security_posture',
	));

	$reg->read( 'diagnostic/theme-audit', array(
		'label'       => 'Theme Audit Diagnostic',
		'description' => 'Compiled single-call theme and design system audit. Evaluates theme.json configuration, enqueued assets, registered patterns, block type availability, and design consistency. Note: asset detection reflects REST API context — some frontend-only assets may not appear.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'sections' => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Optional section filter. Values: theme_info, design_tokens, assets, patterns, blocks. Omit for full audit.',
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'generated_at' => array( 'type' => 'string' ),
			'flags'        => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => 'abilities_for_ai_diagnostic_theme_audit',
	));

	$reg->read( 'diagnostic/content-narrative', array(
		'label'       => 'Content Narrative',
		'description' => 'Compiled site story — what this site is about, who it is for, how content is organised, and the publication timeline. The AI onboarding script.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'include_key_pages' => array(
					'type'        => 'boolean',
					'description' => 'Include first 200 chars of About/Home/Start Here page content (default: true).',
				),
				'max_recent_posts' => array(
					'type'        => 'integer',
					'description' => 'Number of recent posts to include (default: 10, max: 25). Titles + dates + categories only.',
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'generated_at' => array( 'type' => 'string' ),
			'flags'        => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => 'abilities_for_ai_diagnostic_content_narrative',
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
// Theme audit diagnostic
// ============================================================

/**
 * Compiled theme audit diagnostic callback.
 *
 * @param array|null $input Optional input with 'sections' filter.
 * @return array Theme audit report.
 */
function abilities_for_ai_diagnostic_theme_audit( $input = null ) {
	$result  = array( 'generated_at' => gmdate( 'Y-m-d H:i:s' ) );
	$flags   = array();
	$include = ! empty( $input['sections'] ) ? $input['sections'] : null;

	$theme = wp_get_theme();

	// --- Theme info ---
	if ( ! $include || in_array( 'theme_info', $include, true ) ) {
		try {
			$is_block    = $theme->is_block_theme();
			$parent      = $theme->parent();
			$theme_json  = file_exists( get_stylesheet_directory() . '/theme.json' );
			$supports    = array();
			$support_features = array(
				'align-wide', 'appearance-tools', 'border', 'color', 'custom-line-height',
				'custom-spacing', 'custom-units', 'editor-color-palette', 'editor-font-sizes',
				'editor-styles', 'responsive-embeds', 'wp-block-styles',
			);
			foreach ( $support_features as $feature ) {
				if ( current_theme_supports( $feature ) ) {
					$supports[] = $feature;
				}
			}

			$result['theme_info'] = array(
				'name'         => $theme->get( 'Name' ),
				'version'      => $theme->get( 'Version' ),
				'parent'       => $parent ? $parent->get( 'Name' ) : null,
				'parent_version' => $parent ? $parent->get( 'Version' ) : null,
				'block_theme'  => $is_block,
				'theme_json'   => $theme_json,
				'requires_wp'  => $theme->get( 'RequiresWP' ) ?: null,
				'requires_php' => $theme->get( 'RequiresPHP' ) ?: null,
				'theme_supports' => $supports,
			);

			global $wp_version;
			if ( ! $is_block && version_compare( $wp_version, '6.5', '>=' ) ) {
				$flags[] = array( 'severity' => 'info', 'area' => 'theme_info', 'message' => 'Classic theme on WP 6.5+. Consider a block theme for full Site Editor support.' );
			}
			if ( $is_block && ! $theme_json ) {
				$flags[] = array( 'severity' => 'warning', 'area' => 'theme_info', 'message' => 'Block theme without theme.json — design tokens and global styles will not work.' );
			}
			if ( $parent && version_compare( $theme->get( 'Version' ), $parent->get( 'Version' ), '<' ) ) {
				$flags[] = array( 'severity' => 'warning', 'area' => 'theme_info', 'message' => sprintf( 'Child theme version (%s) is lower than parent (%s).', $theme->get( 'Version' ), $parent->get( 'Version' ) ) );
			}
		} catch ( \Throwable $e ) {
			$result['theme_info'] = array( 'error' => $e->getMessage() );
		}
	}

	// --- Design tokens ---
	if ( ! $include || in_array( 'design_tokens', $include, true ) ) {
		try {
			$tokens = array();

			if ( function_exists( 'wp_get_global_settings' ) ) {
				$settings = wp_get_global_settings();

				// Colors.
				$palette = $settings['color']['palette']['theme'] ?? $settings['color']['palette'] ?? array();
				if ( is_array( $palette ) ) {
					$tokens['color_count'] = count( $palette );
					$tokens['color_slugs'] = array_slice( array_column( $palette, 'slug' ), 0, 30 );
				} else {
					$tokens['color_count'] = 0;
					$tokens['color_slugs'] = array();
				}

				// Gradients.
				$gradients = $settings['color']['gradients']['theme'] ?? $settings['color']['gradients'] ?? array();
				$tokens['gradient_count'] = is_array( $gradients ) ? count( $gradients ) : 0;

				// Typography — font families.
				$families = $settings['typography']['fontFamilies']['theme'] ?? $settings['typography']['fontFamilies'] ?? array();
				if ( is_array( $families ) ) {
					$tokens['font_family_count'] = count( $families );
					$tokens['font_families'] = array_slice( array_column( $families, 'name' ), 0, 20 );
				} else {
					$tokens['font_family_count'] = 0;
					$tokens['font_families'] = array();
				}

				// Font sizes.
				$sizes = $settings['typography']['fontSizes']['theme'] ?? $settings['typography']['fontSizes'] ?? array();
				if ( is_array( $sizes ) ) {
					$tokens['font_size_count'] = count( $sizes );
					$tokens['font_size_slugs'] = array_slice( array_column( $sizes, 'slug' ), 0, 20 );
				} else {
					$tokens['font_size_count'] = 0;
					$tokens['font_size_slugs'] = array();
				}

				// Spacing sizes.
				$spacing = $settings['spacing']['spacingSizes']['theme'] ?? $settings['spacing']['spacingSizes'] ?? array();
				$tokens['spacing_size_count'] = is_array( $spacing ) ? count( $spacing ) : 0;

				// Layout.
				$tokens['content_size'] = $settings['layout']['contentSize'] ?? null;
				$tokens['wide_size']    = $settings['layout']['wideSize'] ?? null;
			} else {
				$tokens['note'] = 'wp_get_global_settings() not available (requires WP 6.1+).';
			}

			$result['design_tokens'] = $tokens;

			if ( ( $tokens['color_count'] ?? 0 ) > 20 ) {
				$flags[] = array( 'severity' => 'info', 'area' => 'design_tokens', 'message' => sprintf( 'Large color palette: %d colors defined.', $tokens['color_count'] ) );
			}
			if ( ( $tokens['font_family_count'] ?? 0 ) === 0 ) {
				$flags[] = array( 'severity' => 'info', 'area' => 'design_tokens', 'message' => 'No font families defined in theme.json.' );
			}
			if ( ( $tokens['spacing_size_count'] ?? 0 ) === 0 ) {
				$flags[] = array( 'severity' => 'info', 'area' => 'design_tokens', 'message' => 'No spacing scale defined in theme.json.' );
			}
		} catch ( \Throwable $e ) {
			$result['design_tokens'] = array( 'error' => $e->getMessage() );
		}
	}

	// --- Assets ---
	if ( ! $include || in_array( 'assets', $include, true ) ) {
		try {
			$styles  = wp_styles();
			$scripts = wp_scripts();

			$style_handles  = array();
			foreach ( $styles->registered as $handle => $dep ) {
				$style_handles[] = $handle;
			}

			$script_handles  = array();
			$header_scripts  = 0;
			foreach ( $scripts->registered as $handle => $dep ) {
				$script_handles[] = $handle;
				// Scripts not marked for footer load in header.
				if ( empty( $dep->extra['group'] ) ) {
					$header_scripts++;
				}
			}

			$result['assets'] = array(
				'registered_styles'  => count( $style_handles ),
				'registered_scripts' => count( $script_handles ),
				'header_scripts'     => $header_scripts,
				'note'               => 'Counts reflect REST API context. Frontend-only enqueues may not appear.',
			);

			if ( count( $style_handles ) > 10 ) {
				$flags[] = array( 'severity' => 'warning', 'area' => 'assets', 'message' => sprintf( 'High registered stylesheet count: %d.', count( $style_handles ) ) );
			}
			if ( $header_scripts > 5 ) {
				$flags[] = array( 'severity' => 'info', 'area' => 'assets', 'message' => sprintf( '%d scripts registered in header (potential render-blocking).', $header_scripts ) );
			}
		} catch ( \Throwable $e ) {
			$result['assets'] = array( 'error' => $e->getMessage() );
		}
	}

	// --- Patterns ---
	if ( ! $include || in_array( 'patterns', $include, true ) ) {
		try {
			$registry = WP_Block_Patterns_Registry::get_instance();
			$all_patterns = $registry->get_all_registered();

			$by_source  = array( 'theme' => 0, 'plugin' => 0, 'core' => 0, 'other' => 0 );
			$categories = array();
			foreach ( $all_patterns as $pattern ) {
				// Determine source heuristically.
				$name = $pattern['name'] ?? '';
				if ( strpos( $name, 'core/' ) === 0 ) {
					$by_source['core']++;
				} elseif ( ! empty( $pattern['source'] ) && $pattern['source'] === 'theme' ) {
					$by_source['theme']++;
				} elseif ( ! empty( $pattern['source'] ) && $pattern['source'] === 'plugin' ) {
					$by_source['plugin']++;
				} else {
					// Fallback heuristic: check filePath for theme directory.
					if ( ! empty( $pattern['filePath'] ) && strpos( $pattern['filePath'], get_stylesheet_directory() ) !== false ) {
						$by_source['theme']++;
					} else {
						$by_source['other']++;
					}
				}
				foreach ( $pattern['categories'] ?? array() as $cat ) {
					if ( ! in_array( $cat, $categories, true ) ) {
						$categories[] = $cat;
					}
				}
			}

			$result['patterns'] = array(
				'total'      => count( $all_patterns ),
				'by_source'  => $by_source,
				'categories' => $categories,
			);

			if ( $theme->is_block_theme() && count( $all_patterns ) === 0 ) {
				$flags[] = array( 'severity' => 'info', 'area' => 'patterns', 'message' => 'Block theme with no registered patterns.' );
			}
		} catch ( \Throwable $e ) {
			$result['patterns'] = array( 'error' => $e->getMessage() );
		}
	}

	// --- Blocks ---
	if ( ! $include || in_array( 'blocks', $include, true ) ) {
		try {
			$block_registry = WP_Block_Type_Registry::get_instance();
			$all_blocks     = $block_registry->get_all_registered();

			$by_namespace = array();
			$ssr_count    = 0;
			foreach ( $all_blocks as $block ) {
				$name = $block->name;
				$ns   = strpos( $name, '/' ) !== false ? explode( '/', $name )[0] : 'ungrouped';
				$by_namespace[ $ns ] = ( $by_namespace[ $ns ] ?? 0 ) + 1;
				if ( $block->is_dynamic() ) {
					$ssr_count++;
				}
			}
			// Sort by count descending.
			arsort( $by_namespace );

			$result['blocks'] = array(
				'total'            => count( $all_blocks ),
				'by_namespace'     => $by_namespace,
				'server_rendered'  => $ssr_count,
			);

			if ( count( $all_blocks ) > 200 ) {
				$flags[] = array( 'severity' => 'info', 'area' => 'blocks', 'message' => sprintf( 'High registered block type count: %d.', count( $all_blocks ) ) );
			}
		} catch ( \Throwable $e ) {
			$result['blocks'] = array( 'error' => $e->getMessage() );
		}
	}

	$result['flags'] = $flags;

	return $result;
}

// ============================================================
// Security posture diagnostic
// ============================================================

/**
 * Compiled security posture diagnostic callback.
 *
 * @param array|null $input Optional input with 'sections' filter.
 * @return array Security posture report.
 */
function abilities_for_ai_diagnostic_security_posture( $input = null ) {
	$result  = array( 'generated_at' => gmdate( 'Y-m-d H:i:s' ) );
	$flags   = array();
	$include = ! empty( $input['sections'] ) ? $input['sections'] : null;

	// --- Users ---
	if ( ! $include || in_array( 'users', $include, true ) ) {
		try {
			$user_counts  = count_users();
			$admin_count  = 0;
			$no_role      = 0;
			foreach ( $user_counts['avail_roles'] as $role => $count ) {
				if ( $role === 'none' ) {
					$no_role = (int) $count;
				}
			}
			// Count users with manage_options (real admins).
			$admins = get_users( array( 'capability' => 'manage_options', 'fields' => 'ID' ) );
			$admin_count = count( $admins );

			$app_passwords_available = function_exists( 'wp_is_application_passwords_available' ) && wp_is_application_passwords_available();

			$result['users'] = array(
				'total'                    => $user_counts['total_users'],
				'by_role'                  => $user_counts['avail_roles'],
				'admin_count'              => $admin_count,
				'no_role_count'            => $no_role,
				'application_passwords'    => $app_passwords_available,
			);

			if ( $admin_count > 3 ) {
				$flags[] = array( 'severity' => 'info', 'area' => 'users', 'message' => sprintf( '%d users have administrator privileges.', $admin_count ) );
			}
			if ( $no_role > 0 ) {
				$flags[] = array( 'severity' => 'info', 'area' => 'users', 'message' => sprintf( '%d user(s) have no role assigned.', $no_role ) );
			}
		} catch ( \Throwable $e ) {
			$result['users'] = array( 'error' => $e->getMessage() );
		}
	}

	// --- Plugins ---
	if ( ! $include || in_array( 'plugins', $include, true ) ) {
		try {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$all_plugins   = get_plugins();
			$active_slugs  = get_option( 'active_plugins', array() );
			$active_count  = count( $active_slugs );
			$total_count   = count( $all_plugins );
			$inactive_count = $total_count - $active_count;

			$inactive_list = array();
			foreach ( $all_plugins as $file => $data ) {
				if ( ! in_array( $file, $active_slugs, true ) ) {
					$inactive_list[] = array( 'name' => $data['Name'], 'version' => $data['Version'], 'file' => $file );
				}
			}
			// Cap to 20 for compact output.
			$inactive_list = array_slice( $inactive_list, 0, 20 );

			$mu_count = count( get_mu_plugins() );

			$result['plugins'] = array(
				'active_count'   => $active_count,
				'inactive_count' => $inactive_count,
				'total_count'    => $total_count,
				'must_use_count' => $mu_count,
				'inactive'       => $inactive_list,
			);

			if ( $inactive_count > $active_count ) {
				$flags[] = array( 'severity' => 'warning', 'area' => 'plugins', 'message' => sprintf( 'More inactive (%d) than active (%d) plugins — potential attack surface.', $inactive_count, $active_count ) );
			}
		} catch ( \Throwable $e ) {
			$result['plugins'] = array( 'error' => $e->getMessage() );
		}
	}

	// --- Settings ---
	if ( ! $include || in_array( 'settings', $include, true ) ) {
		try {
			$registration  = (bool) get_option( 'users_can_register', false );
			$default_role  = get_option( 'default_role', 'subscriber' );
			$permalink     = get_option( 'permalink_structure', '' );
			$file_edit_off = defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT;
			$file_mods_off = defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS;

			$result['settings'] = array(
				'users_can_register'   => $registration,
				'default_role'         => $default_role,
				'blog_public'          => (bool) get_option( 'blog_public', '1' ),
				'default_comment_status' => get_option( 'default_comment_status', 'open' ),
				'permalink_structure'  => $permalink,
				'disallow_file_edit'   => $file_edit_off,
				'disallow_file_mods'   => $file_mods_off,
			);

			if ( $registration ) {
				$flags[] = array( 'severity' => 'warning', 'area' => 'settings', 'message' => 'Open user registration is enabled.' );
			}
			if ( $registration && $default_role !== 'subscriber' ) {
				$flags[] = array( 'severity' => 'warning', 'area' => 'settings', 'message' => sprintf( 'Default role for new registrations is "%s" (not subscriber).', $default_role ) );
			}
			if ( ! $file_edit_off ) {
				$flags[] = array( 'severity' => 'warning', 'area' => 'settings', 'message' => 'DISALLOW_FILE_EDIT is not set — theme/plugin editor is accessible.' );
			}
			if ( empty( $permalink ) ) {
				$flags[] = array( 'severity' => 'info', 'area' => 'settings', 'message' => 'Using plain permalinks (no pretty URLs).' );
			}
		} catch ( \Throwable $e ) {
			$result['settings'] = array( 'error' => $e->getMessage() );
		}
	}

	// --- Authentication ---
	if ( ! $include || in_array( 'authentication', $include, true ) ) {
		try {
			$debug       = defined( 'WP_DEBUG' ) && WP_DEBUG;
			$debug_log   = defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
			$debug_display = defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY;
			$env_type    = wp_get_environment_type();
			$log_exists  = $debug_log && file_exists( WP_CONTENT_DIR . '/debug.log' );

			// XML-RPC: check if the xmlrpc_enabled filter is being used to disable it.
			$xmlrpc_enabled = apply_filters( 'xmlrpc_enabled', true );

			$result['authentication'] = array(
				'debug_mode'       => $debug,
				'debug_log'        => $debug_log,
				'debug_log_exists' => $log_exists,
				'debug_display'    => $debug_display,
				'environment_type' => $env_type,
				'xmlrpc_enabled'   => $xmlrpc_enabled,
			);

			if ( $debug && $env_type === 'production' ) {
				$flags[] = array( 'severity' => 'warning', 'area' => 'authentication', 'message' => 'WP_DEBUG is enabled in production.' );
			}
			if ( $log_exists ) {
				$flags[] = array( 'severity' => 'warning', 'area' => 'authentication', 'message' => 'debug.log file exists in wp-content/ — may be publicly accessible.' );
			}
			if ( $debug_display && $env_type === 'production' ) {
				$flags[] = array( 'severity' => 'critical', 'area' => 'authentication', 'message' => 'WP_DEBUG_DISPLAY is enabled in production — errors visible to visitors.' );
			}
			if ( $xmlrpc_enabled ) {
				$flags[] = array( 'severity' => 'info', 'area' => 'authentication', 'message' => 'XML-RPC is enabled.' );
			}
		} catch ( \Throwable $e ) {
			$result['authentication'] = array( 'error' => $e->getMessage() );
		}
	}

	// --- Filesystem ---
	if ( ! $include || in_array( 'filesystem', $include, true ) ) {
		try {
			$htaccess_exists  = file_exists( ABSPATH . '.htaccess' );
			$webconfig_exists = file_exists( ABSPATH . 'web.config' );
			$debug_log_exists = file_exists( WP_CONTENT_DIR . '/debug.log' );
			$uploads_exists   = is_dir( WP_CONTENT_DIR . '/uploads' );

			$result['filesystem'] = array(
				'htaccess_exists'    => $htaccess_exists,
				'webconfig_exists'   => $webconfig_exists,
				'debug_log_exists'   => $debug_log_exists,
				'uploads_dir_exists' => $uploads_exists,
			);
		} catch ( \Throwable $e ) {
			$result['filesystem'] = array( 'error' => $e->getMessage() );
		}
	}

	$result['flags'] = $flags;

	return $result;
}

// ============================================================
// Content narrative diagnostic
// ============================================================

/**
 * Compiled content narrative callback — the AI onboarding script.
 *
 * @param array|null $input Optional input.
 * @return array Site narrative report.
 */
function abilities_for_ai_diagnostic_content_narrative( $input = null ) {
	$result = array( 'generated_at' => gmdate( 'Y-m-d H:i:s' ) );
	$flags  = array();

	$include_key_pages = $input['include_key_pages'] ?? true;
	$max_recent        = min( intval( $input['max_recent_posts'] ?? 10 ), 25 );

	// --- 1. Site Identity ---
	try {
		$theme_obj = wp_get_theme();
		$description = get_option( 'blogdescription', '' );

		$result['identity'] = array(
			'name'        => get_option( 'blogname', '' ),
			'tagline'     => $description,
			'site_url'    => get_site_url(),
			'home_url'    => get_home_url(),
			'theme'       => $theme_obj->get( 'Name' ),
			'block_theme' => $theme_obj->is_block_theme(),
		);

		if ( empty( $description ) || $description === 'Just another WordPress site' ) {
			$flags[] = array( 'severity' => 'info', 'area' => 'identity', 'message' => 'Site description is empty or default.' );
		}
	} catch ( \Throwable $e ) {
		$result['identity'] = array( 'error' => $e->getMessage() );
	}

	// --- 2. Content Structure ---
	try {
		$public_types = get_post_types( array( 'public' => true ), 'objects' );
		$type_counts  = array();
		foreach ( $public_types as $slug => $obj ) {
			$counts  = wp_count_posts( $slug );
			$publish = (int) ( $counts->publish ?? 0 );
			$draft   = (int) ( $counts->draft ?? 0 );
			if ( $publish > 0 || $draft > 0 ) {
				$type_counts[ $slug ] = array(
					'label'   => $obj->label,
					'publish' => $publish,
					'draft'   => $draft,
				);
			}
		}

		// Category tree.
		$categories = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'number' => 100 ) );
		$cat_tree   = array();
		if ( ! is_wp_error( $categories ) ) {
			foreach ( $categories as $cat ) {
				$cat_tree[] = array(
					'name'        => $cat->name,
					'slug'        => $cat->slug,
					'description' => $cat->description ? mb_substr( $cat->description, 0, 100 ) : null,
					'count'       => (int) $cat->count,
					'parent'      => (int) $cat->parent,
				);
			}
		}

		$has_descriptions = false;
		foreach ( $cat_tree as $c ) {
			if ( ! empty( $c['description'] ) ) {
				$has_descriptions = true;
				break;
			}
		}

		$result['content_structure'] = array(
			'post_types'  => $type_counts,
			'categories'  => $cat_tree,
		);

		if ( ! empty( $cat_tree ) && ! $has_descriptions ) {
			$flags[] = array( 'severity' => 'info', 'area' => 'content_structure', 'message' => 'Categories exist but none have descriptions.' );
		}
	} catch ( \Throwable $e ) {
		$result['content_structure'] = array( 'error' => $e->getMessage() );
	}

	// --- 3. Key Pages ---
	if ( $include_key_pages ) {
		try {
			$key_slugs = array( 'about', 'home', 'start-here', 'start', 'contact' );
			$key_pages = array();

			foreach ( $key_slugs as $slug ) {
				$page = get_page_by_path( $slug );
				if ( $page && $page->post_status === 'publish' ) {
					$plain = wp_strip_all_tags( strip_shortcodes( $page->post_content ) );
					$plain = preg_replace( '/<!--.*?-->/s', '', $plain );
					$plain = preg_replace( '/\s+/', ' ', trim( $plain ) );
					$key_pages[] = array(
						'slug'    => $slug,
						'title'   => $page->post_title,
						'excerpt' => mb_substr( $plain, 0, 200 ),
					);
				}
			}

			// Also check the front page.
			$front_page_id = (int) get_option( 'page_on_front', 0 );
			if ( $front_page_id > 0 ) {
				$front = get_post( $front_page_id );
				if ( $front && $front->post_status === 'publish' ) {
					$already = false;
					foreach ( $key_pages as $kp ) {
						if ( $kp['slug'] === $front->post_name ) {
							$already = true;
							break;
						}
					}
					if ( ! $already ) {
						$plain = wp_strip_all_tags( strip_shortcodes( $front->post_content ) );
						$plain = preg_replace( '/<!--.*?-->/s', '', $plain );
						$plain = preg_replace( '/\s+/', ' ', trim( $plain ) );
						array_unshift( $key_pages, array(
							'slug'    => $front->post_name,
							'title'   => $front->post_title . ' (Front Page)',
							'excerpt' => mb_substr( $plain, 0, 200 ),
						));
					}
				}
			}

			$result['key_pages'] = $key_pages;

			$slugs_found = array_column( $key_pages, 'slug' );
			if ( ! in_array( 'about', $slugs_found, true ) ) {
				$flags[] = array( 'severity' => 'info', 'area' => 'key_pages', 'message' => 'No About page found (slug: about).' );
			}
		} catch ( \Throwable $e ) {
			$result['key_pages'] = array( 'error' => $e->getMessage() );
		}
	}

	// --- 4. Publication Timeline ---
	try {
		global $wpdb;
		$first = $wpdb->get_var( "SELECT MIN(post_date) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post'" );
		$last  = $wpdb->get_var( "SELECT MAX(post_date) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post'" );
		$total_posts = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post'" );

		$span_days = 0;
		$avg_per_week = 0;
		if ( $first && $last ) {
			$span_days = max( 1, (int) ( ( strtotime( $last ) - strtotime( $first ) ) / 86400 ) );
			$avg_per_week = round( $total_posts / max( 1, $span_days / 7 ), 1 );
		}

		// Most active month.
		$active_month = $wpdb->get_var( "SELECT DATE_FORMAT(post_date, '%Y-%m') as m FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post' GROUP BY m ORDER BY COUNT(*) DESC LIMIT 1" );

		$result['timeline'] = array(
			'first_published'  => $first,
			'last_published'   => $last,
			'span_days'        => $span_days,
			'total_posts'      => $total_posts,
			'avg_per_week'     => $avg_per_week,
			'most_active_month' => $active_month,
		);

		if ( $last && ( time() - strtotime( $last ) ) > 30 * 86400 ) {
			$days_ago = (int) ( ( time() - strtotime( $last ) ) / 86400 );
			$flags[] = array( 'severity' => 'warning', 'area' => 'timeline', 'message' => sprintf( 'No posts published in the last %d days.', $days_ago ) );
		}
	} catch ( \Throwable $e ) {
		$result['timeline'] = array( 'error' => $e->getMessage() );
	}

	// --- 5. Authors ---
	try {
		global $wpdb;
		$authors_raw = $wpdb->get_results(
			"SELECT p.post_author, COUNT(*) as post_count
			 FROM {$wpdb->posts} p
			 WHERE p.post_status = 'publish' AND p.post_type = 'post'
			 GROUP BY p.post_author
			 ORDER BY post_count DESC
			 LIMIT 20"
		);

		$authors = array();
		foreach ( $authors_raw as $a ) {
			$user = get_userdata( (int) $a->post_author );
			if ( $user ) {
				$authors[] = array(
					'name'       => $user->display_name,
					'post_count' => (int) $a->post_count,
					'role'       => implode( ', ', $user->roles ),
				);
			}
		}

		$result['authors'] = $authors;

		if ( count( $authors ) === 1 ) {
			$flags[] = array( 'severity' => 'info', 'area' => 'authors', 'message' => 'All content is by a single author.' );
		}
	} catch ( \Throwable $e ) {
		$result['authors'] = array( 'error' => $e->getMessage() );
	}

	// --- 6. Navigation ---
	try {
		$menus = wp_get_nav_menus();
		$nav   = array();
		if ( ! empty( $menus ) ) {
			// Get the first menu (usually primary).
			$menu_items = wp_get_nav_menu_items( $menus[0]->term_id );
			if ( $menu_items ) {
				foreach ( $menu_items as $item ) {
					if ( (int) $item->menu_item_parent === 0 ) {
						$nav[] = array( 'label' => $item->title, 'url' => $item->url );
					}
				}
			}
		}

		$result['navigation'] = array(
			'menu_count'     => count( $menus ),
			'primary_items'  => $nav,
		);

		if ( empty( $menus ) ) {
			$flags[] = array( 'severity' => 'warning', 'area' => 'navigation', 'message' => 'No navigation menus registered.' );
		}
	} catch ( \Throwable $e ) {
		$result['navigation'] = array( 'error' => $e->getMessage() );
	}

	// --- 7. Recent Activity ---
	try {
		$recent_posts = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $max_recent,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		$recent = array();
		foreach ( $recent_posts as $p ) {
			$cats = wp_get_post_categories( $p->ID, array( 'fields' => 'names' ) );
			$recent[] = array(
				'title'      => $p->post_title,
				'date'       => $p->post_date,
				'categories' => is_wp_error( $cats ) ? array() : $cats,
				'author'     => get_the_author_meta( 'display_name', $p->post_author ),
			);
		}

		$result['recent_activity'] = $recent;
	} catch ( \Throwable $e ) {
		$result['recent_activity'] = array( 'error' => $e->getMessage() );
	}

	$result['flags'] = $flags;

	return $result;
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
