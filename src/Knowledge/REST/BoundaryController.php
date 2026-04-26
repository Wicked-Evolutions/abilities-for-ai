<?php
/**
 * Knowledge Layer — Boundary REST Controller.
 *
 * Thin REST layer over the kl_boundary table (MCP boundary event log).
 * Namespace: abilities-kl/v1/boundary
 *
 * Companion controller: ActivityController exposes kl_activity (ability
 * executions). BoundaryController exposes kl_boundary (protocol events).
 * The /timeline route UNIONs both for a single chronological view.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 *
 * @package WickedEvolutions\AbilitiesForAI\Knowledge\REST
 */

namespace WickedEvolutions\AbilitiesForAI\Knowledge\REST;

defined( 'ABSPATH' ) || exit;

class BoundaryController extends \WP_REST_Controller {

	protected $namespace = 'abilities-kl/v1';
	protected $rest_base = 'boundary';

	public function register_routes() {
		// GET /boundary — paginated list of kl_boundary rows.
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_items' ),
			'permission_callback' => array( $this, 'admin_check' ),
			'args'                => $this->get_collection_params(),
		) );

		// GET /boundary/stats — aggregate counts by event/severity.
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/stats', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_stats' ),
			'permission_callback' => array( $this, 'admin_check' ),
		) );

		// GET /timeline — UNION-paginated view across kl_activity + kl_boundary.
		register_rest_route( $this->namespace, '/timeline', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_timeline' ),
			'permission_callback' => array( $this, 'admin_check' ),
			'args'                => $this->get_timeline_params(),
		) );
	}

	public function admin_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET /boundary — paginated list with filters.
	 */
	public function get_items( $request ) {
		global $wpdb;

		$table    = $wpdb->prefix . 'kl_boundary';
		$per_page = (int) ( $request->get_param( 'per_page' ) ?? 20 );
		$page     = (int) ( $request->get_param( 'page' ) ?? 1 );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$values = array();

		if ( $request->get_param( 'event' ) ) {
			$where[]  = 'event = %s';
			$values[] = $request->get_param( 'event' );
		}

		if ( $request->get_param( 'severity' ) ) {
			$where[]  = 'severity = %s';
			$values[] = $request->get_param( 'severity' );
		}

		if ( $request->get_param( 'session_id' ) ) {
			$where[]  = 'session_id = %s';
			$values[] = $request->get_param( 'session_id' );
		}

		if ( $request->get_param( 'user_id' ) ) {
			$where[]  = 'user_id = %d';
			$values[] = (int) $request->get_param( 'user_id' );
		}

		if ( $request->get_param( 'date_from' ) ) {
			$where[]  = 'created_at >= %s';
			$values[] = $request->get_param( 'date_from' );
		}

		if ( $request->get_param( 'date_to' ) ) {
			$where[]  = 'created_at <= %s';
			$values[] = $request->get_param( 'date_to' );
		}

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		if ( ! empty( $values ) ) {
			$count_sql = $wpdb->prepare( $count_sql, ...$values );
		}
		$total = (int) $wpdb->get_var( $count_sql );

		$items_sql  = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$all_values = array_merge( $values, array( $per_page, $offset ) );
		$items_sql  = $wpdb->prepare( $items_sql, ...$all_values );
		$items      = $wpdb->get_results( $items_sql );

		foreach ( $items as $item ) {
			$item->id          = (int) $item->id;
			$item->user_id     = (int) $item->user_id;
			$item->status_code = (int) $item->status_code;
			$item->duration_ms = (int) $item->duration_ms;
		}

		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', ceil( $total / max( 1, $per_page ) ) );

		return $response;
	}

	/**
	 * GET /boundary/stats — aggregated boundary event statistics.
	 */
	public function get_stats() {
		global $wpdb;
		$table = $wpdb->prefix . 'kl_boundary';

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		$by_event = $wpdb->get_results(
			"SELECT event, COUNT(*) as count FROM {$table} GROUP BY event ORDER BY count DESC"
		);
		$events = array();
		foreach ( $by_event as $row ) {
			$events[ $row->event ] = (int) $row->count;
		}

		$by_severity = $wpdb->get_results(
			"SELECT severity, COUNT(*) as count FROM {$table} GROUP BY severity ORDER BY count DESC"
		);
		$severities = array();
		foreach ( $by_severity as $row ) {
			$severities[ $row->severity ] = (int) $row->count;
		}

		$recent = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 10" );
		foreach ( $recent as $item ) {
			$item->id          = (int) $item->id;
			$item->user_id     = (int) $item->user_id;
			$item->status_code = (int) $item->status_code;
			$item->duration_ms = (int) $item->duration_ms;
		}

		return rest_ensure_response( array(
			'total'       => $total,
			'by_event'    => $events,
			'by_severity' => $severities,
			'recent'      => $recent,
		) );
	}

	/**
	 * GET /timeline — UNION-paginated kl_activity + kl_boundary.
	 *
	 * `view` parameter:
	 *   activity  — kl_activity rows only (default behaviour of /activity)
	 *   boundary  — kl_boundary rows only
	 *   both      — UNIONed and sorted by created_at DESC
	 *
	 * Each row carries a `kind` field ('activity' | 'boundary') so the UI
	 * can render the right cell shape.
	 */
	public function get_timeline( $request ) {
		global $wpdb;

		$activity = $wpdb->prefix . 'kl_activity';
		$boundary = $wpdb->prefix . 'kl_boundary';

		$view     = $request->get_param( 'view' ) ?: 'both';
		$per_page = (int) ( $request->get_param( 'per_page' ) ?? 20 );
		$page     = (int) ( $request->get_param( 'page' ) ?? 1 );
		$offset   = ( $page - 1 ) * $per_page;

		// Date bounds — applied uniformly to both halves.
		$date_from = $request->get_param( 'date_from' );
		$date_to   = $request->get_param( 'date_to' );

		$activity_where = array( '1=1' );
		$boundary_where = array( '1=1' );
		$values         = array();

		if ( $date_from ) {
			$activity_where[] = 'created_at >= %s';
			$boundary_where[] = 'created_at >= %s';
		}
		if ( $date_to ) {
			$activity_where[] = 'created_at <= %s';
			$boundary_where[] = 'created_at <= %s';
		}

		$activity_select = "SELECT 'activity' as kind, id, ability_name as label, category as event, status, status as severity, duration_ms, user_id, session_id, '' as ip_truncated, created_at FROM {$activity} WHERE " . implode( ' AND ', $activity_where );
		$boundary_select = "SELECT 'boundary' as kind, id, event as label, event, severity as status, severity, duration_ms, user_id, session_id, ip_truncated, created_at FROM {$boundary} WHERE " . implode( ' AND ', $boundary_where );

		// Build UNION based on view.
		if ( 'activity' === $view ) {
			$select_sql = $activity_select;
			$count_sql  = "SELECT COUNT(*) FROM {$activity} WHERE " . implode( ' AND ', $activity_where );
			if ( $date_from ) {
				$values[] = $date_from;
			}
			if ( $date_to ) {
				$values[] = $date_to;
			}
		} elseif ( 'boundary' === $view ) {
			$select_sql = $boundary_select;
			$count_sql  = "SELECT COUNT(*) FROM {$boundary} WHERE " . implode( ' AND ', $boundary_where );
			if ( $date_from ) {
				$values[] = $date_from;
			}
			if ( $date_to ) {
				$values[] = $date_to;
			}
		} else { // both — UNION ALL.
			$select_sql = "({$activity_select}) UNION ALL ({$boundary_select})";
			$count_sql  = "SELECT (SELECT COUNT(*) FROM {$activity} WHERE " . implode( ' AND ', $activity_where ) . ") + (SELECT COUNT(*) FROM {$boundary} WHERE " . implode( ' AND ', $boundary_where ) . ")";
			// Activity bindings then boundary bindings (in WHERE order).
			$bindings = array();
			if ( $date_from ) {
				$bindings[] = $date_from;
			}
			if ( $date_to ) {
				$bindings[] = $date_to;
			}
			// Each WHERE half binds in the same order.
			$values = array_merge( $bindings, $bindings );
		}

		$count = $count_sql;
		if ( ! empty( $values ) ) {
			$count = $wpdb->prepare( $count_sql, ...$values );
		}
		$total = (int) $wpdb->get_var( $count );

		$paged_sql    = "{$select_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$paged_values = array_merge( $values, array( $per_page, $offset ) );
		$paged_sql    = $wpdb->prepare( $paged_sql, ...$paged_values );
		$items        = $wpdb->get_results( $paged_sql );

		foreach ( $items as $item ) {
			$item->id          = (int) $item->id;
			$item->user_id     = (int) $item->user_id;
			$item->duration_ms = (int) $item->duration_ms;
		}

		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', ceil( $total / max( 1, $per_page ) ) );

		return $response;
	}

	public function get_collection_params() {
		return array(
			'per_page'   => array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ),
			'page'       => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
			'event'      => array( 'type' => 'string', 'description' => 'Filter by exact event name (e.g. boundary.auth.denied).' ),
			'severity'   => array( 'type' => 'string', 'enum' => array( 'info', 'warn', 'error', 'critical' ) ),
			'session_id' => array( 'type' => 'string', 'description' => 'Filter by MCP session ID.' ),
			'user_id'    => array( 'type' => 'integer', 'description' => 'Filter by user ID.' ),
			'date_from'  => array( 'type' => 'string', 'description' => 'ISO date — records created after this.' ),
			'date_to'    => array( 'type' => 'string', 'description' => 'ISO date — records created before this.' ),
		);
	}

	public function get_timeline_params() {
		return array(
			'view'      => array( 'type' => 'string', 'enum' => array( 'activity', 'boundary', 'both' ), 'default' => 'both' ),
			'per_page'  => array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ),
			'page'      => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
			'date_from' => array( 'type' => 'string', 'description' => 'ISO date — records created after this.' ),
			'date_to'   => array( 'type' => 'string', 'description' => 'ISO date — records created before this.' ),
		);
	}
}
