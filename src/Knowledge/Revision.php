<?php
/**
 * Knowledge Layer — Revision Model.
 *
 * Stores document version history in the kl_revisions table.
 * Supports pruning (keep last N revisions per document).
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 *
 * @package WickedEvolutions\AbilitiesForAI\Knowledge
 */

namespace WickedEvolutions\AbilitiesForAI\Knowledge;

defined( 'ABSPATH' ) || exit;

class Revision {

	/**
	 * Maximum revisions to keep per document.
	 * Oldest beyond this cap are pruned on each update.
	 */
	const MAX_PER_DOCUMENT = 50;

	/**
	 * Get the table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'kl_revisions';
	}

	/**
	 * Create a revision from a document's current state.
	 *
	 * @param object $doc            Document row (decoded).
	 * @param string $change_summary Brief description of the change.
	 * @return int|false Insert ID or false on failure.
	 */
	public static function create_from_document( $doc, $change_summary = '' ) {
		global $wpdb;

		return $wpdb->insert( self::table(), array(
			'document_id'    => $doc->id,
			'version'        => $doc->version,
			'title'          => $doc->title,
			'content'        => $doc->content,
			'metadata'       => wp_json_encode( $doc->metadata ?? new \stdClass() ),
			'changed_by'     => get_current_user_id(),
			'change_summary' => $change_summary,
			'created_at'     => current_time( 'mysql', true ),
		) );
	}

	/**
	 * List revisions for a document.
	 *
	 * @param int   $document_id Document ID.
	 * @param int   $per_page    Items per page. Default 20.
	 * @param int   $page        Page number. Default 1.
	 * @return array { 'items' => array, 'total' => int }
	 */
	public static function list_for_document( $document_id, $per_page = 20, $page = 1 ) {
		global $wpdb;

		$table    = self::table();
		$per_page = min( 100, max( 1, $per_page ) );
		$page     = max( 1, $page );
		$offset   = ( $page - 1 ) * $per_page;

		$total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM %i WHERE document_id = %d",
			$table,
			$document_id
		) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, document_id, version, title, change_summary, changed_by, created_at FROM %i WHERE document_id = %d ORDER BY version DESC LIMIT %d OFFSET %d",
			$table,
			$document_id,
			$per_page,
			$offset
		) );

		return array(
			'items'    => $rows ?: array(),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Get a specific revision with full content.
	 *
	 * @param int $document_id Document ID.
	 * @param int $version     Version number.
	 * @return object|null
	 */
	public static function get_version( $document_id, $version ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE document_id = %d AND version = %d",
			self::table(),
			$document_id,
			$version
		) );
		if ( $row && isset( $row->metadata ) && is_string( $row->metadata ) ) {
			$row->metadata = json_decode( $row->metadata, true );
		}
		return $row;
	}

	/**
	 * Restore a document to a previous version.
	 *
	 * @param int $document_id Document ID.
	 * @param int $version     Version to restore.
	 * @return object|WP_Error Updated document or error.
	 */
	public static function restore( $document_id, $version ) {
		$revision = self::get_version( $document_id, $version );
		if ( ! $revision ) {
			return new \WP_Error( 'revision_not_found', "No revision at version {$version} for this document." );
		}

		return Document::update( $document_id, array(
			'title'          => $revision->title,
			'content'        => $revision->content,
			'metadata'       => $revision->metadata,
			'change_summary' => "Restored to version {$version}",
		) );
	}

	/**
	 * Prune old revisions beyond the cap.
	 *
	 * @param int $document_id Document ID.
	 */
	public static function prune( $document_id ) {
		global $wpdb;

		$table = self::table();
		$count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM %i WHERE document_id = %d",
			$table,
			$document_id
		) );

		if ( $count <= self::MAX_PER_DOCUMENT ) {
			return;
		}

		$excess = $count - self::MAX_PER_DOCUMENT;

		// Delete the oldest revisions beyond the cap.
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM %i WHERE document_id = %d ORDER BY version ASC LIMIT %d",
			$table,
			$document_id,
			$excess
		) );
	}
}
