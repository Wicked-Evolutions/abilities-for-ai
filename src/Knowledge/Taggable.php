<?php
/**
 * Knowledge Layer — Taggable Helper.
 *
 * Provides polymorphic tagging operations for any KL entity
 * (documents, sessions, observations) via the kl_taggables pivot table.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 *
 * @package WickedEvolutions\AbilitiesForAI\Knowledge
 */

namespace WickedEvolutions\AbilitiesForAI\Knowledge;

defined( 'ABSPATH' ) || exit;

class Taggable {

	/**
	 * Allowed taggable types (short strings, not FQCNs).
	 */
	const TYPES = array( 'document', 'session', 'observation' );

	/**
	 * Get the pivot table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'kl_taggables';
	}

	/**
	 * Validate a taggable_type value.
	 *
	 * @param string $type Type to validate.
	 * @return true|WP_Error
	 */
	private static function validate_type( $type ) {
		if ( ! in_array( $type, self::TYPES, true ) ) {
			return new \WP_Error(
				'invalid_taggable_type',
				sprintf( 'Invalid taggable type: %s. Allowed: %s', $type, implode( ', ', self::TYPES ) )
			);
		}
		return true;
	}

	/**
	 * Assign tags to an entity.
	 *
	 * Uses INSERT IGNORE to skip duplicates (UNIQUE index prevents them at DB level).
	 *
	 * @param array  $tag_ids       Tag IDs to assign.
	 * @param int    $taggable_id   Entity ID.
	 * @param string $taggable_type Entity type.
	 * @return array|WP_Error List of assigned tag IDs, or error.
	 */
	public static function assign( $tag_ids, $taggable_id, $taggable_type ) {
		global $wpdb;

		$valid = self::validate_type( $taggable_type );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$now      = current_time( 'mysql', true );
		$assigned = array();

		foreach ( $tag_ids as $tag_id ) {
			$tag_id = (int) $tag_id;

			// Verify tag exists.
			if ( ! Tag::find( $tag_id ) ) {
				continue;
			}

			// INSERT IGNORE — silently skips if UNIQUE constraint hit.
			$wpdb->query( $wpdb->prepare(
				"INSERT IGNORE INTO %i (tag_id, taggable_id, taggable_type, created_at) VALUES (%d, %d, %s, %s)",
				self::table(),
				$tag_id,
				$taggable_id,
				$taggable_type,
				$now
			) );

			$assigned[] = $tag_id;
		}

		return $assigned;
	}

	/**
	 * Remove tags from an entity.
	 *
	 * @param array  $tag_ids       Tag IDs to remove.
	 * @param int    $taggable_id   Entity ID.
	 * @param string $taggable_type Entity type.
	 * @return array|WP_Error List of removed tag IDs, or error.
	 */
	public static function unassign( $tag_ids, $taggable_id, $taggable_type ) {
		global $wpdb;

		$valid = self::validate_type( $taggable_type );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$removed = array();

		foreach ( $tag_ids as $tag_id ) {
			$tag_id = (int) $tag_id;

			$deleted = $wpdb->delete( self::table(), array(
				'tag_id'        => $tag_id,
				'taggable_id'   => $taggable_id,
				'taggable_type' => $taggable_type,
			), array( '%d', '%d', '%s' ) );

			if ( $deleted ) {
				$removed[] = $tag_id;
			}
		}

		return $removed;
	}

	/**
	 * Get all tags for an entity.
	 *
	 * @param int    $taggable_id   Entity ID.
	 * @param string $taggable_type Entity type.
	 * @return array|WP_Error Array of tag objects, or error.
	 */
	public static function getFor( $taggable_id, $taggable_type ) {
		global $wpdb;

		$valid = self::validate_type( $taggable_type );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$tags_table = Tag::table();
		$pivot      = self::table();

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT t.* FROM %i t INNER JOIN %i p ON t.id = p.tag_id WHERE p.taggable_id = %d AND p.taggable_type = %s ORDER BY t.title ASC",
			$tags_table,
			$pivot,
			$taggable_id,
			$taggable_type
		) );

		return array_map( function( $row ) {
			$row->id = (int) $row->id;
			return $row;
		}, $rows ?: array() );
	}

	/**
	 * Get all entity IDs with a specific tag.
	 *
	 * @param int    $tag_id        Tag ID.
	 * @param string $taggable_type Entity type.
	 * @return array|WP_Error Array of entity IDs, or error.
	 */
	public static function getEntities( $tag_id, $taggable_type ) {
		global $wpdb;

		$valid = self::validate_type( $taggable_type );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT taggable_id FROM %i WHERE tag_id = %d AND taggable_type = %s",
			self::table(),
			$tag_id,
			$taggable_type
		) );

		return array_map( 'intval', $ids ?: array() );
	}

	/**
	 * Sync tags for an entity — replace all current tags with the given set.
	 *
	 * @param array  $tag_ids       Tag IDs to sync.
	 * @param int    $taggable_id   Entity ID.
	 * @param string $taggable_type Entity type.
	 * @return array|WP_Error List of synced tag IDs, or error.
	 */
	public static function sync( $tag_ids, $taggable_id, $taggable_type ) {
		global $wpdb;

		$valid = self::validate_type( $taggable_type );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		// Remove all existing assignments for this entity.
		$wpdb->delete( self::table(), array(
			'taggable_id'   => $taggable_id,
			'taggable_type' => $taggable_type,
		), array( '%d', '%s' ) );

		// Assign the new set.
		return self::assign( $tag_ids, $taggable_id, $taggable_type );
	}
}
