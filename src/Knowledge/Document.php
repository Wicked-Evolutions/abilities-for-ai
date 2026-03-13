<?php
/**
 * Knowledge Layer — Document Model.
 *
 * Simple $wpdb wrapper for the kl_documents table.
 * Handles JSON encoding/decoding for the metadata column,
 * soft delete, version incrementing, and slug generation.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 *
 * @package WickedEvolutions\AbilitiesForAI\Knowledge
 */

namespace WickedEvolutions\AbilitiesForAI\Knowledge;

defined( 'ABSPATH' ) || exit;

class Document {

	/**
	 * Valid document types.
	 */
	const TYPES = array(
		'knowledge',
		'agent',
		'skill',
		'essence',
		'site-identity',
		'site-state',
		'capabilities',
		'diagnostic',
		'boot',
		'course',
		'template',
		'config',
	);

	/**
	 * Get the table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'kl_documents';
	}

	/**
	 * Find a document by ID.
	 *
	 * @param int $id Document ID.
	 * @return object|null Row with decoded metadata, or null.
	 */
	public static function find( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE id = %d",
			self::table(),
			$id
		) );
		return $row ? self::decode( $row ) : null;
	}

	/**
	 * Find a document by doc_type + slug.
	 *
	 * @param string $doc_type Document type.
	 * @param string $slug     Document slug.
	 * @return object|null
	 */
	public static function find_by_slug( $doc_type, $slug ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE doc_type = %s AND slug = %s",
			self::table(),
			$doc_type,
			$slug
		) );
		return $row ? self::decode( $row ) : null;
	}

	/**
	 * List documents with filters.
	 *
	 * @param array $args {
	 *     @type string $doc_type Filter by type.
	 *     @type string $status   Filter by status. Default 'active'.
	 *     @type string $search   Search title and excerpt.
	 *     @type int    $per_page Items per page. Default 20.
	 *     @type int    $page     Page number. Default 1.
	 * }
	 * @return array { 'items' => array, 'total' => int, 'page' => int, 'per_page' => int }
	 */
	public static function list_documents( $args = array() ) {
		global $wpdb;

		$table    = self::table();
		$where    = array( '1=1' );
		$values   = array();

		if ( ! empty( $args['doc_type'] ) ) {
			$where[]  = 'doc_type = %s';
			$values[] = $args['doc_type'];
		}

		$status   = $args['status'] ?? 'active';
		if ( $status !== 'all' ) {
			$where[]  = 'status = %s';
			$values[] = $status;
		}

		// Exclude archived by default unless explicitly requested.
		if ( $status === 'all' ) {
			$where[] = "status != 'archived'";
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(title LIKE %s OR excerpt LIKE %s)';
			$values[] = $like;
			$values[] = $like;
		}

		$per_page = min( 100, max( 1, intval( $args['per_page'] ?? 20 ) ) );
		$page     = max( 1, intval( $args['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where_sql = implode( ' AND ', $where );

		// Count total.
		$count_sql = "SELECT COUNT(*) FROM %i WHERE {$where_sql}";
		$total     = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, array_merge( array( $table ), $values ) ) );

		// Fetch page.
		$query_sql = "SELECT id, slug, doc_type, title, excerpt, status, source, version, locked, author_id, parent_id, created_at, updated_at FROM %i WHERE {$where_sql} ORDER BY updated_at DESC LIMIT %d OFFSET %d";
		$rows      = $wpdb->get_results( $wpdb->prepare( $query_sql, array_merge( array( $table ), $values, array( $per_page, $offset ) ) ) );

		return array(
			'items'    => $rows ?: array(),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Create a new document.
	 *
	 * @param array $data Document data.
	 * @return object|WP_Error Created document or error.
	 */
	public static function create( $data ) {
		global $wpdb;

		$doc_type = $data['doc_type'] ?? '';
		if ( ! in_array( $doc_type, self::TYPES, true ) ) {
			return new \WP_Error( 'invalid_doc_type', 'Invalid document type: ' . $doc_type );
		}

		$slug = $data['slug'] ?? sanitize_title( $data['title'] ?? '' );
		if ( empty( $slug ) ) {
			return new \WP_Error( 'empty_slug', 'Document slug cannot be empty.' );
		}

		// Check unique constraint.
		if ( self::find_by_slug( $doc_type, $slug ) ) {
			return new \WP_Error( 'duplicate_slug', "A {$doc_type} document with slug '{$slug}' already exists." );
		}

		$now = current_time( 'mysql', true );

		$insert = array(
			'slug'       => $slug,
			'doc_type'   => $doc_type,
			'title'      => $data['title'] ?? '',
			'content'    => $data['content'] ?? '',
			'excerpt'    => $data['excerpt'] ?? '',
			'status'     => $data['status'] ?? 'active',
			'source'     => $data['source'] ?? 'ai',
			'version'    => 1,
			'locked'     => ! empty( $data['locked'] ) ? 1 : 0,
			'metadata'   => wp_json_encode( $data['metadata'] ?? new \stdClass() ),
			'author_id'  => $data['author_id'] ?? get_current_user_id(),
			'parent_id'  => $data['parent_id'] ?? null,
			'created_at' => $now,
			'updated_at' => $now,
		);

		$result = $wpdb->insert( self::table(), $insert );
		if ( false === $result ) {
			return new \WP_Error( 'db_insert_error', 'Failed to create document: ' . $wpdb->last_error );
		}

		$doc = self::find( $wpdb->insert_id );

		// Create initial revision.
		Revision::create_from_document( $doc, 'Initial version' );

		return $doc;
	}

	/**
	 * Update a document. Creates a revision before updating.
	 *
	 * @param int   $id   Document ID.
	 * @param array $data Fields to update.
	 * @return object|WP_Error Updated document or error.
	 */
	public static function update( $id, $data ) {
		global $wpdb;

		$doc = self::find( $id );
		if ( ! $doc ) {
			return new \WP_Error( 'not_found', 'Document not found.' );
		}

		if ( $doc->locked ) {
			return new \WP_Error( 'locked', 'This document is locked. Use knowledge/fork to create an editable copy.' );
		}

		// Snapshot current state before updating.
		$change_summary = $data['change_summary'] ?? 'Updated';
		Revision::create_from_document( $doc, $change_summary );

		$update = array( 'updated_at' => current_time( 'mysql', true ) );

		$allowed = array( 'title', 'content', 'excerpt', 'status' );
		foreach ( $allowed as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$update[ $field ] = $data[ $field ];
			}
		}

		if ( isset( $data['metadata'] ) ) {
			// Merge with existing metadata.
			$existing_meta   = $doc->metadata ?: array();
			$merged          = array_merge( (array) $existing_meta, (array) $data['metadata'] );
			$update['metadata'] = wp_json_encode( $merged );
		}

		// Increment version.
		$update['version'] = $doc->version + 1;

		$wpdb->update( self::table(), $update, array( 'id' => $id ) );

		// Prune old revisions.
		Revision::prune( $id );

		return self::find( $id );
	}

	/**
	 * Soft-delete a document (status → archived).
	 *
	 * @param int $id Document ID.
	 * @return true|WP_Error
	 */
	public static function archive( $id ) {
		global $wpdb;

		$doc = self::find( $id );
		if ( ! $doc ) {
			return new \WP_Error( 'not_found', 'Document not found.' );
		}
		if ( $doc->locked ) {
			return new \WP_Error( 'locked', 'Locked documents cannot be archived.' );
		}

		$wpdb->update(
			self::table(),
			array( 'status' => 'archived', 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => $id )
		);

		return true;
	}

	/**
	 * Fork a locked document — create an editable copy.
	 *
	 * @param int   $id    Document ID to fork.
	 * @param array $overrides Optional title/slug overrides.
	 * @return object|WP_Error The new forked document.
	 */
	public static function fork( $id, $overrides = array() ) {
		$doc = self::find( $id );
		if ( ! $doc ) {
			return new \WP_Error( 'not_found', 'Document not found.' );
		}
		if ( ! $doc->locked ) {
			return new \WP_Error( 'not_locked', 'Only locked documents can be forked. Use knowledge/update for unlocked documents.' );
		}

		$title = $overrides['title'] ?? $doc->title . ' (Custom)';
		$slug  = $overrides['slug'] ?? sanitize_title( $title );

		return self::create( array(
			'doc_type'  => $doc->doc_type,
			'title'     => $title,
			'slug'      => $slug,
			'content'   => $doc->content,
			'excerpt'   => $doc->excerpt,
			'status'    => 'active',
			'source'    => 'fork',
			'locked'    => false,
			'metadata'  => $doc->metadata,
			'parent_id' => $doc->id,
		) );
	}

	/**
	 * Decode metadata JSON on a row object.
	 *
	 * @param object $row Database row.
	 * @return object Row with decoded metadata.
	 */
	private static function decode( $row ) {
		if ( isset( $row->metadata ) && is_string( $row->metadata ) ) {
			$row->metadata = json_decode( $row->metadata, true );
		}
		$row->id        = (int) $row->id;
		$row->version   = (int) $row->version;
		$row->locked    = (bool) $row->locked;
		$row->author_id = (int) $row->author_id;
		$row->parent_id = $row->parent_id ? (int) $row->parent_id : null;
		return $row;
	}
}
