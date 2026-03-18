<?php
/**
 * Knowledge Layer — Tag Model.
 *
 * Simple $wpdb wrapper for the kl_tags table.
 * Flat tags with CRUD, search/pagination, and slug generation.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 *
 * @package WickedEvolutions\AbilitiesForAI\Knowledge
 */

namespace WickedEvolutions\AbilitiesForAI\Knowledge;

defined( 'ABSPATH' ) || exit;

class Tag {

	/**
	 * Get the table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'kl_tags';
	}

	/**
	 * Find a tag by ID.
	 *
	 * @param int $id Tag ID.
	 * @return object|null Row with cast types, or null.
	 */
	public static function find( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE id = %d",
			self::table(),
			$id
		) );
		return $row ? self::cast( $row ) : null;
	}

	/**
	 * Find a tag by slug.
	 *
	 * @param string $slug Tag slug.
	 * @return object|null
	 */
	public static function findBySlug( $slug ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE slug = %s",
			self::table(),
			$slug
		) );
		return $row ? self::cast( $row ) : null;
	}

	/**
	 * List tags with optional search and pagination.
	 *
	 * @param array $args {
	 *     @type string $search   Search title.
	 *     @type int    $per_page Items per page. Default 20.
	 *     @type int    $page     Page number. Default 1.
	 *     @type string $order_by Column to sort by. Default 'title'.
	 * }
	 * @return array { 'items' => array, 'total' => int, 'page' => int, 'per_page' => int }
	 */
	public static function all( $args = array() ) {
		global $wpdb;

		$table  = self::table();
		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = 'title LIKE %s';
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
		$query_sql = "SELECT * FROM %i WHERE {$where_sql} ORDER BY title ASC LIMIT %d OFFSET %d";
		$rows      = $wpdb->get_results( $wpdb->prepare( $query_sql, array_merge( array( $table ), $values, array( $per_page, $offset ) ) ) );

		return array(
			'items'    => array_map( array( self::class, 'cast' ), $rows ?: array() ),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Count all tags.
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i", self::table() ) );
	}

	/**
	 * Create a new tag.
	 *
	 * @param array $data Tag data.
	 * @return object|WP_Error Created tag or error.
	 */
	public static function create( $data ) {
		global $wpdb;

		if ( empty( $data['title'] ) ) {
			return new \WP_Error( 'missing_title', 'Tag title is required.' );
		}

		$slug = ! empty( $data['slug'] ) ? sanitize_title( $data['slug'] ) : self::generateSlug( $data['title'] );
		if ( empty( $slug ) ) {
			return new \WP_Error( 'empty_slug', 'Tag slug cannot be empty.' );
		}

		// Check unique slug.
		if ( self::findBySlug( $slug ) ) {
			return new \WP_Error( 'duplicate_slug', "A tag with slug '{$slug}' already exists." );
		}

		$now = current_time( 'mysql', true );

		$insert = array(
			'title'       => $data['title'],
			'slug'        => $slug,
			'description' => $data['description'] ?? null,
			'color'       => $data['color'] ?? null,
			'created_at'  => $now,
			'updated_at'  => $now,
		);

		$result = $wpdb->insert( self::table(), $insert );
		if ( false === $result ) {
			return new \WP_Error( 'db_insert_error', 'Failed to create tag: ' . $wpdb->last_error );
		}

		return self::find( $wpdb->insert_id );
	}

	/**
	 * Update a tag.
	 *
	 * @param int   $id   Tag ID.
	 * @param array $data Fields to update.
	 * @return object|WP_Error Updated tag or error.
	 */
	public static function update( $id, $data ) {
		global $wpdb;

		$tag = self::find( $id );
		if ( ! $tag ) {
			return new \WP_Error( 'not_found', 'Tag not found.' );
		}

		$update = array( 'updated_at' => current_time( 'mysql', true ) );

		if ( isset( $data['title'] ) ) {
			$update['title'] = $data['title'];
		}

		if ( isset( $data['slug'] ) ) {
			$new_slug = sanitize_title( $data['slug'] );
			if ( $new_slug !== $tag->slug ) {
				$existing = self::findBySlug( $new_slug );
				if ( $existing && $existing->id !== $id ) {
					return new \WP_Error( 'duplicate_slug', "A tag with slug '{$new_slug}' already exists." );
				}
				$update['slug'] = $new_slug;
			}
		}

		if ( isset( $data['description'] ) ) {
			$update['description'] = $data['description'];
		}

		if ( isset( $data['color'] ) ) {
			$update['color'] = $data['color'];
		}

		$wpdb->update( self::table(), $update, array( 'id' => $id ) );

		return self::find( $id );
	}

	/**
	 * Delete a tag (hard delete). Cascades to kl_taggables.
	 *
	 * @param int $id Tag ID.
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;

		$tag = self::find( $id );
		if ( ! $tag ) {
			return new \WP_Error( 'not_found', 'Tag not found.' );
		}

		// Cascade: remove all taggable assignments for this tag.
		$wpdb->delete( $wpdb->prefix . 'kl_taggables', array( 'tag_id' => $id ), array( '%d' ) );

		// Delete the tag.
		$wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );

		return true;
	}

	/**
	 * Generate a unique slug from a title.
	 *
	 * @param string $title Tag title.
	 * @return string Unique slug.
	 */
	public static function generateSlug( $title ) {
		$slug = sanitize_title( $title );
		$base = $slug;
		$i    = 2;

		while ( self::findBySlug( $slug ) ) {
			$slug = $base . '-' . $i;
			$i++;
		}

		return $slug;
	}

	/**
	 * Cast types on a row object.
	 *
	 * @param object $row Database row.
	 * @return object Row with correct types.
	 */
	private static function cast( $row ) {
		$row->id = (int) $row->id;
		return $row;
	}
}
