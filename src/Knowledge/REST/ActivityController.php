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

		$where_sql = implode( ' AND ', $where );

		// Total count.
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		if ( ! empty( $values ) ) {
			$count_sql = $wpdb->prepare( $count_sql, ...$values );
		}
		$total = (int) $wpdb->get_var( $count_sql );

		// Items.
		$items_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$all_values   = array_merge( $values, array( $per_page, $offset ) );
		$items_sql    = $wpdb->prepare( $items_sql, ...$all_values );
		$items        = $wpdb->get_results( $items_sql );

		// Cast numeric fields.
		foreach ( $items as $item ) {
			$item->id          = (int) $item->id;
			$item->duration_ms = (int) $item->duration_ms;
			$item->user_id     = (int) $item->user_id;
		}

		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', ceil( $total / max( 1, $per_page ) ) );

		return $response;
	}

	/**
	 * GET /activity/stats — aggregated activity statistics.
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
			"SELECT ability_name, COUNT(*) as count, AVG(duration_ms) as avg_ms FROM {$table} GROUP BY ability_name ORDER BY count DESC LIMIT 10"
		);

		$recent = $wpdb->get_results(
			"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 10"
		);
		foreach ( $recent as $item ) {
			$item->id          = (int) $item->id;
			$item->duration_ms = (int) $item->duration_ms;
			$item->user_id     = (int) $item->user_id;
		}

		$out = array();
		foreach ( $by_category as $row ) {
			$out[ $row->category ] = (int) $row->count;
		}

		$abilities = array();
		foreach ( $top_abilities as $row ) {
			$abilities[] = array(
				'name'   => $row->ability_name,
				'count'  => (int) $row->count,
				'avg_ms' => round( (float) $row->avg_ms, 1 ),
			);
		}

		return rest_ensure_response( array(
			'total'          => $total,
			'total_success'  => $total_success,
			'total_error'    => $total_error,
			'avg_duration'   => round( $avg_duration, 1 ),
			'by_category'    => $out,
			'top_abilities'  => $abilities,
			'recent'         => $recent,
		) );
	}

	public function get_collection_params() {
		return array(
			'per_page'     => array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ),
			'page'         => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
			'ability_name' => array( 'type' => 'string', 'description' => 'Filter by ability name (LIKE search).' ),
			'category'     => array( 'type' => 'string', 'description' => 'Filter by ability category.' ),
			'user_id'      => array( 'type' => 'integer', 'description' => 'Filter by user ID.' ),
			'status'       => array( 'type' => 'string', 'enum' => array( 'success', 'error' ) ),
			'session_id'   => array( 'type' => 'string', 'description' => 'Filter by MCP session ID.' ),
			'date_from'    => array( 'type' => 'string', 'description' => 'ISO date — records created after this.' ),
			'date_to'      => array( 'type' => 'string', 'description' => 'ISO date — records created before this.' ),
		);
	}
}
