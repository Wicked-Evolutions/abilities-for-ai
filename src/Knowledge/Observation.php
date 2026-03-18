<?php
/**
 * Knowledge Layer — Observation Model.
 *
 * Append-only model for the kl_observations table.
 * Observations are inserted, can have their status changed,
 * but are never deleted (soft delete to archived).
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 *
 * @package WickedEvolutions\AbilitiesForAI\Knowledge
 */

namespace WickedEvolutions\AbilitiesForAI\Knowledge;

defined( 'ABSPATH' ) || exit;

class Observation {

	const CATEGORIES = array( 'technical', 'strategic', 'security', 'content', 'design' );
	const SEVERITIES = array( 'info', 'attention', 'action_needed' );
	const STATUSES   = array( 'open', 'resolved', 'wont_fix', 'deferred' );

	/**
	 * Get the table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'kl_observations';
	}

	/**
	 * Find an observation by ID.
	 *
	 * @param int $id Observation ID.
	 * @return object|null
	 */
	public static function find( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE id = %d",
			self::table(),
			$id
		) );
		if ( $row ) {
			$row->id = (int) $row->id;
		}
		return $row;
	}

	/**
	 * List observations with filters.
	 *
	 * @param array $args {
	 *     @type string $status   Filter by status. Default 'open'.
	 *     @type string $category Filter by category.
	 *     @type string $severity Filter by severity.
	 *     @type int    $per_page Items per page. Default 20.
	 *     @type int    $page     Page number. Default 1.
	 * }
	 * @return array
	 */
	public static function list_observations( $args = array() ) {
		global $wpdb;

		$table  = self::table();
		$where  = array( '1=1' );
		$values = array();

		$status = $args['status'] ?? 'open';
		if ( $status !== 'all' ) {
			$where[]  = 'status = %s';
			$values[] = $status;
		}

		if ( ! empty( $args['category'] ) ) {
			$where[]  = 'category = %s';
			$values[] = $args['category'];
		}

		if ( ! empty( $args['severity'] ) ) {
			$where[]  = 'severity = %s';
			$values[] = $args['severity'];
		}

		if ( ! empty( $args['session_id'] ) ) {
			$where[]  = 'session_id = %s';
			$values[] = $args['session_id'];
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
			"SELECT * FROM %i WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d",
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
	 * Add a new observation.
	 *
	 * @param array $data Observation data.
	 * @return object|WP_Error
	 */
	public static function add( $data ) {
		global $wpdb;

		$category = $data['category'] ?? '';
		if ( ! in_array( $category, self::CATEGORIES, true ) ) {
			return new \WP_Error( 'invalid_category', 'Invalid category. Must be one of: ' . implode( ', ', self::CATEGORIES ) );
		}

		$severity = $data['severity'] ?? 'info';
		if ( ! in_array( $severity, self::SEVERITIES, true ) ) {
			return new \WP_Error( 'invalid_severity', 'Invalid severity. Must be one of: ' . implode( ', ', self::SEVERITIES ) );
		}

		$insert = array(
			'session_id'        => $data['session_id'] ?? '',
			'category'          => $category,
			'severity'          => $severity,
			'description'       => $data['description'] ?? '',
			'source_diagnostic' => $data['source_diagnostic'] ?? null,
			'status'            => 'open',
			'created_at'        => current_time( 'mysql', true ),
		);

		$result = $wpdb->insert( self::table(), $insert );
		if ( false === $result ) {
			return new \WP_Error( 'db_insert_error', 'Failed to add observation: ' . $wpdb->last_error );
		}

		return self::find( $wpdb->insert_id );
	}

	/**
	 * Resolve an observation (or defer/won't-fix).
	 *
	 * @param int   $id     Observation ID.
	 * @param string $status New status (resolved, wont_fix, deferred).
	 * @param string $note   Resolution note.
	 * @return object|WP_Error
	 */
	public static function resolve( $id, $status, $note = '' ) {
		global $wpdb;

		if ( ! in_array( $status, array( 'resolved', 'wont_fix', 'deferred' ), true ) ) {
			return new \WP_Error( 'invalid_status', 'Status must be: resolved, wont_fix, or deferred.' );
		}

		$obs = self::find( $id );
		if ( ! $obs ) {
			return new \WP_Error( 'not_found', 'Observation not found.' );
		}

		$wpdb->update(
			self::table(),
			array(
				'status'          => $status,
				'resolved_at'     => current_time( 'mysql', true ),
				'resolved_by'     => get_current_user_id(),
				'resolution_note' => $note,
			),
			array( 'id' => $id )
		);

		return self::find( $id );
	}

	/**
	 * Count open observations.
	 *
	 * @return int
	 */
	public static function count_open() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM %i WHERE status = 'open'",
			self::table()
		) );
	}
}
