<?php
/**
 * Knowledge Layer — MCP Boundary Event Logger Bootstrap.
 *
 * Wires BoundaryEventLogger into the abilities-mcp-adapter via two paths:
 *
 *   Path 1 (typed interface) — registers BoundaryEventLogger as the
 *   server-level observability handler when an adapter server is built
 *   inside our process. This is how our own first-party plugins (and any
 *   third-party plugin willing to depend on our PHP class) bind in.
 *
 *   Path 2 (action hook) — listens for `mcp_adapter_boundary_event` so
 *   third-party listeners receive sanitized boundary events without
 *   depending on the adapter's PHP classes. This file is also a
 *   third-party listener — it routes the action-hook firing back into
 *   the same writer used by Path 1, so events from either path land in
 *   the same table.
 *
 * Both paths gracefully degrade:
 *   - Adapter alone (no abilities-for-ai)            → NullMcpObservabilityHandler no-op + action hook with no listener.
 *   - abilities-for-ai alone (no adapter)            → BoundaryEventLogger never instantiates; action listener is registered but never fires.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

use WickedEvolutions\AbilitiesForAI\Knowledge\BoundaryEventLogger;
use WickedEvolutions\AbilitiesForAI\Knowledge\Schema;

/**
 * Path 1 — Interface registration.
 *
 * The adapter fires `mcp_adapter_register_observability` during its init
 * so listeners can attach handlers to specific servers without having to
 * reach into McpServerConfig themselves. The signature accepts the adapter
 * instance ($adapter); listeners may inspect server registration there.
 *
 * Today the typed handler binds at server-build time inside the adapter,
 * so this hook is mostly a discoverability surface for future use. We
 * still attach so first-party plugins can lazy-load by class name.
 */
add_action( 'mcp_adapter_register_observability', function( $adapter ) {
	// class_exists guard — adapter may be a different version than we
	// expect, or the namespace may have shifted. Failing closed is safe.
	if ( ! class_exists( BoundaryEventLogger::class ) ) {
		return;
	}
	// Nothing to do at this stage — the adapter wires the typed handler
	// via McpServerConfig['observability_handler'] at server-build time.
	// This callback exists so future first-party orchestration can attach
	// per-server overrides without the adapter knowing about us.
}, 10, 1 );

/**
 * Path 2 — Action hook listener.
 *
 * The adapter fires `mcp_adapter_boundary_event` for every boundary event,
 * AFTER sanitizing the tags against the metadata-only allowlist. We route
 * those into the same writer the typed handler uses.
 *
 * @param string     $event_name  e.g. 'boundary.auth.denied'
 * @param array      $tags        Sanitized metadata-only tags.
 * @param float|null $duration_ms Optional duration.
 */
add_action( 'mcp_adapter_boundary_event', function( $event_name, $tags = array(), $duration_ms = null ) {
	if ( ! class_exists( BoundaryEventLogger::class ) || ! class_exists( Schema::class ) ) {
		return;
	}
	if ( ! Schema::tables_exist() ) {
		return;
	}
	$logger = new BoundaryEventLogger();
	$logger->persist(
		(string) $event_name,
		is_array( $tags ) ? $tags : array(),
		is_numeric( $duration_ms ) ? (float) $duration_ms : null
	);
}, 10, 3 );

/**
 * Daily retention cron.
 *
 * Runs once a day, prunes kl_boundary rows older than the filter-controlled
 * retention window (default 90 days). Bounded by the idx_created index, so
 * the DELETE remains cheap even on large tables.
 */
add_action( 'abilities_kl_boundary_retention', function() {
	global $wpdb;

	if ( ! class_exists( Schema::class ) || ! Schema::tables_exist() ) {
		return;
	}

	$days = (int) apply_filters( 'kl_boundary_retention_days', 90 );
	if ( $days <= 0 ) {
		return; // 0 or negative disables retention.
	}

	$table = $wpdb->prefix . 'kl_boundary';

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix.
	$wpdb->query( $wpdb->prepare(
		"DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
		$days
	) );
} );

/**
 * Schedule the retention cron on plugins_loaded if not already scheduled.
 * Cheaper than tying it to activation alone — survives deactivate/reactivate
 * cycles and works on multisite where each subsite has its own cron.
 */
add_action( 'plugins_loaded', function() {
	if ( ! wp_next_scheduled( 'abilities_kl_boundary_retention' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'abilities_kl_boundary_retention' );
	}
}, 20 );

/**
 * Clear the cron on plugin deactivation. Activation hook lives in the
 * main plugin file; we clean up here so reactivation can re-register
 * cleanly.
 */
register_deactivation_hook(
	dirname( __DIR__ ) . '/abilities-for-ai.php',
	function() {
		wp_clear_scheduled_hook( 'abilities_kl_boundary_retention' );
	}
);
