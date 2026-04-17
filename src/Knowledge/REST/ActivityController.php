<?php
/**
 * Knowledge Layer — Activity REST Controller.
 *
 * Thin REST layer over the kl_activity table (automatic execution log).
 * Namespace: abilities-kl/v1/activity
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 *
 * @package WickedEvolutions\AbilitiesForAI\Knowledge\REST
 */

namespace WickedEvolutions\AbilitiesForAI\Knowledge\REST;

defined( 'ABSPATH' ) || exit;

class ActivityController extends \WP_REST_Controller {

	protected $namespace = 'abilities-kl/v1';
	protected $rest_base = 'activity';

	public function register_routes() {
		// GET /activity — paginated list with filters.
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_items' ),
			'permission_callback' => array( $this, 'admin_check' ),
			'args'                => $this->get_collection_params(),
		) );

		// GET /activity/stats — aggregate stats for dashboard.
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/stats', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_stats' ),
			'permission_callback' => array( $this, 'admin_check' ),
		) );
	}

	public function admin_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET /activity — paginated list with filters.
	 */
	public function get_items( $request ) {
		global $wpdb;

		$table    = $wpdb->prefix . 'kl_activity';
		$per_page = (int) ( $request->get_param( 'per_page' ) ?? 20 );
		$page     = (int) ( $request->get_param( 'page' ) ?? 1 );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$values = array();

		if ( $request->get_param( 'ability_name' ) ) {
			$where[]  = 'ability_name LIKE %s';
			$values[] = '%' . $wpdb->esc_like( $request->get_param( 'ability_name' ) ) . '%';
		}

		if ( $request->get_param( 'category' ) ) {
			$where[]  = 'category = %s';
			$values[] = $request->get_param( 'category' );
		}

		if ( $request->get_param( 'user_id' ) ) {
			$where[]  = 'user_id = %d';
			$values[] = (int) $request->get_param( 'user_id' );
		}

		if ( $request->get_param( 'status' ) ) {
			$where[]  = 'status = %s';
			$values[] = $request->get_param( 'status' );
		}

		if ( $request->get_param( 'session_id' ) ) {
			$where[]  = 'session_id = %s';
			$values[] = $request->get_param( 'session_id' );
		}

		if ( $request->get_param( 'date_from' ) ) {
			$where[]  = 'created_at >= %s';
			$values[] = $request->get_param( 'date_from' );
		}

		if ( $request->get_param( 'date_to' ) ) {
			$where[]  = 'created_at <= %s';
			$values[] = $request->get_param( 'date_to' );
		}

		// v0.6.0 (issue #123) — filters for new operational signal columns.
		if ( $request->get_param( 'caller_origin' ) ) {
			$where[]  = 'caller_origin = %s';
			$values[] = $request->get_param( 'caller_origin' );
		}

		$is_compiled = $request->get_param( 'is_compiled' );
		if ( $is_compiled !== null && $is_compiled !== '' ) {
			$where[]  = 'is_compiled = %d';
			$values[] = $is_compiled ? 1 : 0;
		}

		if ( $request->get_param( 'replaced_surface' ) ) {
			$where[]  = 'replaced_surface = %s';
			$values[] = $request->get_param( 'replaced_surface' );
		}

		if ( $request->get_param( 'response_hash' ) ) {
			$where[]  = 'response_hash = %s';
			$values[] = $request->get_param( 'response_hash' );
		}

		$where_sql = implode( ' AND ', $where );

		// Sort order — allow sorting by performance-relevant columns.
		$orderby_param  = $request->get_param( 'orderby' ) ?: 'created_at';
		$allowed_sort   = array( 'created_at', 'duration_ms', 'response_size_bytes', 'memory_delta_bytes', 'sql_query_count', 'input_size_bytes' );
		$orderby        = in_array( $orderby_param, $allowed_sort, true ) ? $orderby_param : 'created_at';
		$order          = strtoupper( $request->get_param( 'order' ) ?: 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';

		// Total count.
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		if ( ! empty( $values ) ) {
			$count_sql = $wpdb->prepare( $count_sql, ...$values );
		}
		$total = (int) $wpdb->get_var( $count_sql );

		// Items.
		$items_sql  = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$all_values = array_merge( $values, array( $per_page, $offset ) );
		$items_sql  = $wpdb->prepare( $items_sql, ...$all_values );
		$items      = $wpdb->get_results( $items_sql );

		// Cast numeric fields.
		foreach ( $items as $item ) {
			$item->id                  = (int) $item->id;
			$item->duration_ms         = (int) $item->duration_ms;
			$item->user_id             = (int) $item->user_id;
			$item->response_size_bytes = (int) ( $item->response_size_bytes ?? 0 );
			$item->input_size_bytes    = (int) ( $item->input_size_bytes ?? 0 );
			$item->memory_delta_bytes  = (int) ( $item->memory_delta_bytes ?? 0 );
			$item->sql_query_count     = (int) ( $item->sql_query_count ?? 0 );
			$item->is_compiled         = (bool) ( $item->is_compiled ?? false );
		}

		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', ceil( $total / max( 1, $per_page ) ) );

		return $response;
	}

	/**
	 * GET /activity/stats — aggregated activity statistics.
	 *
	 * v0.6.0 (issue #123) — extended with operational signal aggregations:
	 * caller origin distribution, compiled vs CRUD split, replaced surface
	 * coverage, memory/query hotspots, cache candidates (hash repeat rate).
	 */
	public function get_stats() {
		global $wpdb;

		$table = $wpdb->prefix . 'kl_activity';

		$total         = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$total_success = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'success'" );
		$total_error   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'error'" );
		$avg_duration  = (float) $wpdb->get_var( "SELECT AVG(duration_ms) FROM {$table}" );

		$by_category = $wpdb->get_results(
			"SELECT category, COUNT(*) as count FROM {$table} GROUP BY category ORDER BY count DESC LIMIT 20"
		);

		$top_abilities = $wpdb->get_results(
			"SELECT ability_name,
				COUNT(*) as count,
				AVG(duration_ms) as avg_ms,
				AVG(response_size_bytes) as avg_response_bytes,
				AVG(memory_delta_bytes) as avg_memory_bytes,
				AVG(sql_query_count) as avg_queries
			FROM {$table}
			GROUP BY ability_name
			ORDER BY count DESC LIMIT 10"
		);

		$recent = $wpdb->get_results(
			"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 10"
		);
		foreach ( $recent as $item ) {
			$item->id                  = (int) $item->id;
			$item->duration_ms         = (int) $item->duration_ms;
			$item->user_id             = (int) $item->user_id;
			$item->response_size_bytes = (int) ( $item->response_size_bytes ?? 0 );
			$item->input_size_bytes    = (int) ( $item->input_size_bytes ?? 0 );
			$item->memory_delta_bytes  = (int) ( $item->memory_delta_bytes ?? 0 );
			$item->sql_query_count     = (int) ( $item->sql_query_count ?? 0 );
			$item->is_compiled         = (bool) ( $item->is_compiled ?? false );
		}

		$out = array();
		foreach ( $by_category as $row ) {
			$out[ $row->category ] = (int) $row->count;
		}

		$abilities = array();
		foreach ( $top_abilities as $row ) {
			$abilities[] = array(
				'name'               => $row->ability_name,
				'count'              => (int) $row->count,
				'avg_ms'             => round( (float) $row->avg_ms, 1 ),
				'avg_response_bytes' => (int) round( (float) $row->avg_response_bytes ),
				'avg_memory_bytes'   => (int) round( (float) $row->avg_memory_bytes ),
				'avg_queries'        => round( (float) $row->avg_queries, 1 ),
			);
		}

		// v0.6.0: Caller origin distribution.
		$by_caller = $wpdb->get_results(
			"SELECT caller_origin, COUNT(*) as count FROM {$table} GROUP BY caller_origin ORDER BY count DESC"
		);
		$caller_origins = array();
		foreach ( $by_caller as $row ) {
			$key                    = $row->caller_origin ?: 'unknown';
			$caller_origins[ $key ] = (int) $row->count;
		}

		// v0.6.0: Compiled vs CRUD split.
		$total_compiled = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_compiled = 1" );
		$total_crud     = $total - $total_compiled;

		// v0.6.0: Replaced surface coverage — how many unique wp-admin
		// screens have been "replaced" by ability calls, plus top 10.
		$replaced_unique = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT replaced_surface) FROM {$table} WHERE replaced_surface IS NOT NULL AND replaced_surface != ''"
		);
		$replaced_top = $wpdb->get_results(
			"SELECT replaced_surface, COUNT(*) as count
			FROM {$table}
			WHERE replaced_surface IS NOT NULL AND replaced_surface != ''
			GROUP BY replaced_surface
			ORDER BY count DESC LIMIT 10"
		);
		$replaced_surfaces = array();
		foreach ( $replaced_top as $row ) {
			$replaced_surfaces[] = array(
				'surface' => $row->replaced_surface,
				'count'   => (int) $row->count,
			);
		}

		// v0.6.0: Cache candidates — abilities whose response_hash has
		// high repeat rate. We compute per-ability: total calls vs
		// unique response hashes. A high ratio means most calls return
		// the same result — prime cache target.
		$cache_candidates_raw = $wpdb->get_results(
			"SELECT ability_name,
				COUNT(*) as total_calls,
				COUNT(DISTINCT response_hash) as unique_hashes,
				AVG(duration_ms) as avg_ms,
				AVG(response_size_bytes) as avg_bytes
			FROM {$table}
			WHERE response_hash != '' AND status = 'success'
			GROUP BY ability_name
			HAVING total_calls >= 5
			ORDER BY (total_calls - unique_hashes) DESC, total_calls DESC
			LIMIT 10"
		);
		$cache_candidates = array();
		foreach ( $cache_candidates_raw as $row ) {
			$total_calls   = (int) $row->total_calls;
			$unique_hashes = (int) $row->unique_hashes;
			$repeat_rate   = $total_calls > 0 ? round( ( $total_calls - $unique_hashes ) / $total_calls, 3 ) : 0;
			$cache_candidates[] = array(
				'name'          => $row->ability_name,
				'total_calls'   => $total_calls,
				'unique_hashes' => $unique_hashes,
				'repeat_rate'   => $repeat_rate,
				'avg_ms'        => round( (float) $row->avg_ms, 1 ),
				'avg_bytes'     => (int) round( (float) $row->avg_bytes ),
			);
		}

		// v0.6.0: Memory + query hotspots.
		$top_memory = $wpdb->get_results(
			"SELECT ability_name, AVG(memory_delta_bytes) as avg_bytes, COUNT(*) as count
			FROM {$table}
			GROUP BY ability_name
			HAVING avg_bytes > 0
			ORDER BY avg_bytes DESC LIMIT 10"
		);
		$top_queries = $wpdb->get_results(
			"SELECT ability_name, AVG(sql_query_count) as avg_queries, COUNT(*) as count
			FROM {$table}
			GROUP BY ability_name
			HAVING avg_queries > 0
			ORDER BY avg_queries DESC LIMIT 10"
		);

		$memory_hotspots = array();
		foreach ( $top_memory as $row ) {
			$memory_hotspots[] = array(
				'name'      => $row->ability_name,
				'avg_bytes' => (int) round( (float) $row->avg_bytes ),
				'count'     => (int) $row->count,
			);
		}
		$query_hotspots = array();
		foreach ( $top_queries as $row ) {
			$query_hotspots[] = array(
				'name'        => $row->ability_name,
				'avg_queries' => round( (float) $row->avg_queries, 1 ),
				'count'       => (int) $row->count,
			);
		}

		return rest_ensure_response( array(
			'total'             => $total,
			'total_success'     => $total_success,
			'total_error'       => $total_error,
			'avg_duration'      => round( $avg_duration, 1 ),
			'by_category'       => $out,
			'top_abilities'     => $abilities,
			'recent'            => $recent,
			// v0.6.0 (issue #123):
			'caller_origins'    => $caller_origins,
			'compiled'          => array(
				'total_compiled' => $total_compiled,
				'total_crud'     => $total_crud,
				'compiled_pct'   => $total > 0 ? round( $total_compiled / $total * 100, 1 ) : 0,
			),
			'replaced_surfaces' => array(
				'unique'  => $replaced_unique,
				'top'     => $replaced_surfaces,
			),
			'cache_candidates'  => $cache_candidates,
			'memory_hotspots'   => $memory_hotspots,
			'query_hotspots'    => $query_hotspots,
		) );
	}

	public function get_collection_params() {
		return array(
			'per_page'         => array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ),
			'page'             => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
			'ability_name'     => array( 'type' => 'string', 'description' => 'Filter by ability name (LIKE search).' ),
			'category'         => array( 'type' => 'string', 'description' => 'Filter by ability category.' ),
			'user_id'          => array( 'type' => 'integer', 'description' => 'Filter by user ID.' ),
			'status'           => array( 'type' => 'string', 'enum' => array( 'success', 'error' ) ),
			'session_id'       => array( 'type' => 'string', 'description' => 'Filter by MCP session ID.' ),
			'date_from'        => array( 'type' => 'string', 'description' => 'ISO date — records created after this.' ),
			'date_to'          => array( 'type' => 'string', 'description' => 'ISO date — records created before this.' ),
			// v0.6.0 (issue #123):
			'caller_origin'    => array( 'type' => 'string', 'enum' => array( 'mcp', 'rest', 'wp-admin', 'wp-cron', 'cli', 'internal', '' ), 'description' => 'Filter by caller origin.' ),
			'is_compiled'      => array( 'type' => 'boolean', 'description' => 'Filter compiled abilities (true) vs CRUD (false).' ),
			'replaced_surface' => array( 'type' => 'string', 'description' => 'Filter by the wp-admin URL this ability replaces.' ),
			'response_hash'    => array( 'type' => 'string', 'description' => 'Filter by exact response hash.' ),
			'orderby'          => array( 'type' => 'string', 'enum' => array( 'created_at', 'duration_ms', 'response_size_bytes', 'memory_delta_bytes', 'sql_query_count', 'input_size_bytes' ), 'default' => 'created_at' ),
			'order'            => array( 'type' => 'string', 'enum' => array( 'ASC', 'DESC' ), 'default' => 'DESC' ),
		);
	}
}
