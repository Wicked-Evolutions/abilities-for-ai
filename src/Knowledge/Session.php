<?php
/**
 * Knowledge Layer — Session Model.
 *
 * Append-only model for the kl_sessions table.
 * Sessions are never updated or deleted — only inserted.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 *
 * @package WickedEvolutions\AbilitiesForAI\Knowledge
 */

namespace WickedEvolutions\AbilitiesForAI\Knowledge;

defined( 'ABSPATH' ) || exit;

class Session {

	/**
	 * Get the table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'kl_sessions';
	}

	/**
	 * Find a session by session_id.
	 *
	 * @param string $session_id Unique session identifier.
	 * @return object|null
	 */
	public static function find( $session_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE session_id = %s",
			self::table(),
			$session_id
		) );
		return $row ? self::decode( $row ) : null;
	}

	/**
	 * List sessions with filters.
	 *
	 * @param array $args {
	 *     @type string $agent_type Filter by agent type.
	 *     @type string $since      ISO date — only sessions started after this.
	 *     @type int    $per_page   Items per page. Default 20.
	 *     @type int    $page       Page number. Default 1.
	 * }
	 * @return array { 'items' => array, 'total' => int, 'page' => int, 'per_page' => int }
	 */
	public static function list_sessions( $args = array() ) {
		global $wpdb;

		$table  = self::table();
		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['agent_type'] ) ) {
			$where[]  = 'agent_type = %s';
			$values[] = $args['agent_type'];
		}

		if ( ! empty( $args['since'] ) ) {
			$where[]  = 'started_at >= %s';
			$values[] = $args['since'];
		}

		$per_page = min( 100, max( 1, intval( $args['per_page'] ?? 20 ) ) );
		$page     = max( 1, intval( $args['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where_sql = implode( ' AND ', $where );

		$total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM %i WHERE {$where_sql}",
			array_merge( array( $table ), $values )
		) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, session_id, agent_type, model, started_at, ended_at, summary, whats_next, user_id, created_at FROM %i WHERE {$where_sql} ORDER BY started_at DESC LIMIT %d OFFSET %d",
			array_merge( array( $table ), $values, array( $per_page, $offset ) )
		) );

		return array(
			'items'    => $rows ?: array(),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Log a new session (append-only).
	 *
	 * @param array $data Session data.
	 * @return object|WP_Error Created session or error.
	 */
	public static function log( $data ) {
		global $wpdb;

		$session_id = $data['session_id'] ?? '';
		if ( empty( $session_id ) ) {
			return new \WP_Error( 'empty_session_id', 'Session ID is required.' );
		}

		// Reject duplicates.
		if ( self::find( $session_id ) ) {
			return new \WP_Error( 'duplicate_session', "Session '{$session_id}' already exists." );
		}

		$insert = array(
			'session_id'         => $session_id,
			'agent_type'         => $data['agent_type'] ?? '',
			'model'              => $data['model'] ?? '',
			'started_at'         => $data['started_at'] ?? current_time( 'mysql', true ),
			'ended_at'           => $data['ended_at'] ?? null,
			'summary'            => $data['summary'] ?? '',
			'protocols_run'      => wp_json_encode( $data['protocols_run'] ?? array() ),
			'documents_modified' => wp_json_encode( $data['documents_modified'] ?? array() ),
			'findings'           => wp_json_encode( $data['findings'] ?? array() ),
			'whats_next'         => $data['whats_next'] ?? null,
			'user_id'            => $data['user_id'] ?? get_current_user_id(),
			'created_at'         => current_time( 'mysql', true ),
		);

		$result = $wpdb->insert( self::table(), $insert );
		if ( false === $result ) {
			return new \WP_Error( 'db_insert_error', 'Failed to log session: ' . $wpdb->last_error );
		}

		return self::find( $session_id );
	}

	/**
	 * Get the most recent session.
	 *
	 * @return object|null
	 */
	public static function latest() {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i ORDER BY started_at DESC LIMIT 1",
			self::table()
		) );
		return $row ? self::decode( $row ) : null;
	}

	/**
	 * Count total sessions.
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM %i",
			self::table()
		) );
	}

	/**
	 * Decode JSON columns on a row object.
	 *
	 * @param object $row Database row.
	 * @return object
	 */
	private static function decode( $row ) {
		$row->id      = (int) $row->id;
		$row->user_id = (int) $row->user_id;

		foreach ( array( 'protocols_run', 'documents_modified', 'findings' ) as $col ) {
			if ( isset( $row->$col ) && is_string( $row->$col ) ) {
				$row->$col = json_decode( $row->$col, true );
			}
		}

		return $row;
	}
}
