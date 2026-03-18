<?php
/**
 * Knowledge Layer — FULLTEXT Search Provider.
 *
 * Default search implementation using MySQL/MariaDB FULLTEXT indexes
 * on the kl_documents table (title, content, excerpt).
 *
 * Works on MariaDB 10.x InnoDB (Hostinger shared hosting).
 * Falls back to LIKE-based search if the FULLTEXT index is missing.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 *
 * @package WickedEvolutions\AbilitiesForAI\Knowledge\Search
 */

namespace WickedEvolutions\AbilitiesForAI\Knowledge\Search;

use WickedEvolutions\AbilitiesForAI\Knowledge\Document;

defined( 'ABSPATH' ) || exit;

class Fulltext_Search_Provider implements KL_Search_Provider {

	/**
	 * {@inheritDoc}
	 */
	public function search( string $query, array $args = [] ): array {
		global $wpdb;

		$table    = Document::table();
		$where    = [];
		$values   = [];
		$select       = 'id, slug, doc_type, title, excerpt, status, source, version, locked, author_id, parent_id, created_at, updated_at';
		$order_by     = 'updated_at DESC';
		$query        = trim( $query );
		$match_expr   = '';

		// FULLTEXT search when a query is provided.
		if ( $query !== '' ) {
			// Pre-prepare the MATCH expression to avoid positional placeholder conflicts with %i.
			$match_expr = $wpdb->prepare(
				'MATCH(title, content, excerpt) AGAINST(%s IN NATURAL LANGUAGE MODE)',
				$query
			);
			$select   .= ", {$match_expr} AS relevance_score";
			$where[]   = $match_expr;
			$order_by  = 'relevance_score DESC';
		}

		// doc_type filter.
		if ( ! empty( $args['doc_type'] ) ) {
			$where[]  = 'doc_type = %s';
			$values[] = $args['doc_type'];
		}

		// status filter.
		$status = $args['status'] ?? 'active';
		if ( $status !== 'all' ) {
			$where[]  = 'status = %s';
			$values[] = $status;
		} else {
			$where[] = "status != 'archived'";
		}

		// Tag filtering — gracefully handle missing kl_taggables/kl_tags tables.
		if ( ! empty( $args['tags'] ) && is_array( $args['tags'] ) ) {
			$tag_ids = $this->resolve_tag_ids( $args['tags'] );
			if ( $tag_ids === false ) {
				// Tags tables don't exist yet — skip tag filtering silently.
			} elseif ( empty( $tag_ids ) ) {
				// Tags requested but none found — return empty result.
				return [
					'items'    => [],
					'total'    => 0,
					'page'     => max( 1, intval( $args['page'] ?? 1 ) ),
					'per_page' => min( 100, max( 1, intval( $args['per_page'] ?? 20 ) ) ),
				];
			} else {
				$placeholders = implode( ',', array_fill( 0, count( $tag_ids ), '%d' ) );
				$tag_count    = count( $tag_ids );
				$where[]      = "id IN (
					SELECT taggable_id FROM {$wpdb->prefix}kl_taggables
					WHERE taggable_type = 'document'
					AND tag_id IN ({$placeholders})
					GROUP BY taggable_id
					HAVING COUNT(DISTINCT tag_id) = %d
				)";
				$values = array_merge( $values, $tag_ids, [ $tag_count ] );
			}
		}

		// Pagination.
		$per_page = min( 100, max( 1, intval( $args['per_page'] ?? 20 ) ) );
		$page     = max( 1, intval( $args['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where_sql = ! empty( $where ) ? implode( ' AND ', $where ) : '1=1';

		// Count total.
		$count_sql = "SELECT COUNT(*) FROM %i WHERE {$where_sql}";
		$total     = (int) $wpdb->get_var(
			$wpdb->prepare( $count_sql, array_merge( [ $table ], $values ) )
		);

		// Fetch page.
		$fetch_sql = "SELECT {$select} FROM %i WHERE {$where_sql} ORDER BY {$order_by} LIMIT %d OFFSET %d";
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				$fetch_sql,
				array_merge( [ $table ], $values, [ $per_page, $offset ] )
			)
		);

		// Cast relevance_score to float.
		if ( $query !== '' && $rows ) {
			foreach ( $rows as $row ) {
				$row->relevance_score = (float) $row->relevance_score;
			}
		}

		return [
			'items'    => $rows ?: [],
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'fulltext';
	}

	/**
	 * Resolve tag slugs to tag IDs.
	 *
	 * Returns false if the tags table doesn't exist (graceful degradation
	 * when Track A hasn't deployed yet). Returns empty array if slugs
	 * don't match any existing tags.
	 *
	 * @param string[] $slugs Tag slugs.
	 * @return int[]|false Array of tag IDs or false if table missing.
	 */
	private function resolve_tag_ids( array $slugs ): array|false {
		global $wpdb;

		$tags_table = $wpdb->prefix . 'kl_tags';

		// Check if kl_tags table exists.
		$exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $tags_table )
		);
		if ( $exists !== $tags_table ) {
			return false;
		}

		$placeholders = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM `{$tags_table}` WHERE slug IN ({$placeholders})",
				$slugs
			)
		);

		return array_map( 'intval', $ids );
	}
}
