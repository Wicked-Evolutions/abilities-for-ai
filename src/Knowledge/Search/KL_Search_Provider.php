<?php
/**
 * Knowledge Layer — Search Provider Interface.
 *
 * Abstraction for KL document search. The default implementation uses
 * MySQL FULLTEXT indexes. Future providers (vector/semantic search)
 * implement this same interface and are swapped via the
 * `kl_search_provider` filter hook.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 *
 * @package WickedEvolutions\AbilitiesForAI\Knowledge\Search
 */

namespace WickedEvolutions\AbilitiesForAI\Knowledge\Search;

defined( 'ABSPATH' ) || exit;

interface KL_Search_Provider {

	/**
	 * Search KL documents.
	 *
	 * @param string $query Search query (may be empty for filter-only queries).
	 * @param array  $args  {
	 *     Optional filters and pagination.
	 *
	 *     @type string   $doc_type Filter by document type.
	 *     @type string   $status   Filter by status. Default 'active'.
	 *     @type string[] $tags     Filter by tag slugs.
	 *     @type int      $per_page Items per page. Default 20.
	 *     @type int      $page     Page number. Default 1.
	 * }
	 * @return array {
	 *     @type array $items    Array of result objects (with relevance_score when query is provided).
	 *     @type int   $total    Total matching documents.
	 *     @type int   $page     Current page.
	 *     @type int   $per_page Items per page.
	 * }
	 */
	public function search( string $query, array $args = [] ): array;

	/**
	 * Provider name for identification.
	 *
	 * @return string e.g. 'fulltext', 'vector', 'hybrid'.
	 */
	public function get_name(): string;
}
