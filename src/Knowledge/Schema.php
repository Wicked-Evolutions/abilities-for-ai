<?php
/**
 * Knowledge Layer — Database Schema.
 *
 * Creates and migrates the 7 Knowledge Layer tables via dbDelta().
 * Follows Fluent plugin patterns: kl_ prefix, bigint unsigned IDs,
 * varchar status fields, JSON metadata columns, utf8mb4.
 *
 * Schema design: Influencentricity OS vault → SCHEMA — Database Design v0.1.1
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 *
 * @package WickedEvolutions\AbilitiesForAI\Knowledge
 */

namespace WickedEvolutions\AbilitiesForAI\Knowledge;

defined( 'ABSPATH' ) || exit;

class Schema {

	/**
	 * Current schema version. Bump this when tables change.
	 */
	const VERSION = '0.5.0';

	/**
	 * Option key for stored schema version.
	 */
	const VERSION_KEY = 'kl_schema_version';

	/**
	 * Create or update all Knowledge Layer tables.
	 *
	 * Safe to call multiple times — dbDelta() is idempotent.
	 */
	public static function up() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = self::get_sql( $wpdb->prefix, $charset_collate );

		dbDelta( $sql );

		update_option( self::VERSION_KEY, self::VERSION, true );
	}

	/**
	 * Check if schema needs migration and run it.
	 */
	public static function maybe_migrate() {
		$current = get_option( self::VERSION_KEY, '0' );
		if ( version_compare( $current, self::VERSION, '<' ) ) {
			self::up();
			self::run_migrations( $current );
		}

		// Safety net: ensure seed tags exist even if the version-gated migration
		// was skipped (e.g., version was bumped before tags table was created).
		// seed_tags() is idempotent — it bails if any tags already exist.
		if ( self::tables_exist() ) {
			Seeder::seed_tags();
		}
	}

	/**
	 * Run version-specific migrations.
	 *
	 * @param string $from_version Previous schema version.
	 */
	private static function run_migrations( $from_version ) {
		// v0.2.0: Refresh seed document content from prototype files.
		if ( version_compare( $from_version, '0.2.7', '<' ) ) {
			Seeder::update_seeds();
		}

		// v0.3.0: Add FULLTEXT index for search (dbDelta doesn't handle FULLTEXT).
		if ( version_compare( $from_version, '0.3.0', '<' ) ) {
			self::add_fulltext_index();
		}

		// v0.3.1: Seed default tags for the tagging system.
		if ( version_compare( $from_version, '0.3.1', '<' ) ) {
			Seeder::seed_tags();
		}

		// v0.4.0: Add wp_post_id column (handled by dbDelta via get_sql).
		// No additional migration logic needed — dbDelta adds the column.
	}

	/**
	 * Add FULLTEXT index on title, content, excerpt for search.
	 *
	 * Runs as ALTER TABLE because dbDelta() does not support FULLTEXT indexes.
	 * Safe to call multiple times — checks for existing index first.
	 */
	private static function add_fulltext_index() {
		global $wpdb;

		$table = $wpdb->prefix . 'kl_documents';

		// Check if index already exists.
		$existing = $wpdb->get_results( "SHOW INDEX FROM `{$table}` WHERE Key_name = 'idx_fulltext'" );
		if ( ! empty( $existing ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
		$wpdb->query( "ALTER TABLE `{$table}` ADD FULLTEXT INDEX idx_fulltext (title, content, excerpt)" );
	}

	/**
	 * Check if the documents table exists.
	 *
	 * @return bool
	 */
	public static function tables_exist() {
		global $wpdb;
		$table = $wpdb->prefix . 'kl_documents';
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/**
	 * Generate the full SQL for all 7 tables.
	 *
	 * @param string $prefix           WordPress table prefix.
	 * @param string $charset_collate  Charset/collation string.
	 * @return string Combined SQL for dbDelta().
	 */
	private static function get_sql( $prefix, $charset_collate ) {
		$tables = array();

		// 1. kl_documents — main table for all knowledge layer documents.
		$tables[] = "CREATE TABLE {$prefix}kl_documents (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			slug varchar(191) NOT NULL,
			doc_type varchar(50) NOT NULL,
			title varchar(255) NOT NULL DEFAULT '',
			content longtext NOT NULL,
			excerpt text NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			source varchar(20) NOT NULL DEFAULT 'ai',
			version int unsigned NOT NULL DEFAULT 1,
			locked tinyint(1) NOT NULL DEFAULT 0,
			metadata longtext DEFAULT NULL,
			author_id bigint(20) unsigned NOT NULL DEFAULT 0,
			parent_id bigint(20) unsigned DEFAULT NULL,
			wp_post_id bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY idx_type_slug (doc_type, slug),
			KEY idx_type_status (doc_type, status),
			KEY idx_source (source),
			KEY idx_parent (parent_id),
			KEY idx_updated (updated_at),
			KEY idx_wp_post (wp_post_id)
		) {$charset_collate};";

		// 2. kl_sessions — append-only session log.
		$tables[] = "CREATE TABLE {$prefix}kl_sessions (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id varchar(64) NOT NULL,
			agent_type varchar(50) NOT NULL DEFAULT '',
			model varchar(100) NOT NULL DEFAULT '',
			started_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			ended_at datetime DEFAULT NULL,
			summary text NOT NULL,
			protocols_run longtext DEFAULT NULL,
			documents_modified longtext DEFAULT NULL,
			findings longtext DEFAULT NULL,
			whats_next text DEFAULT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY idx_session_id (session_id),
			KEY idx_started (started_at),
			KEY idx_agent (agent_type),
			KEY idx_user (user_id)
		) {$charset_collate};";

		// 3. kl_observations — append-only findings from diagnostics.
		$tables[] = "CREATE TABLE {$prefix}kl_observations (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id varchar(64) NOT NULL DEFAULT '',
			category varchar(50) NOT NULL DEFAULT '',
			severity varchar(20) NOT NULL DEFAULT 'info',
			description text NOT NULL,
			source_diagnostic varchar(100) DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			resolved_at datetime DEFAULT NULL,
			resolved_by bigint(20) unsigned DEFAULT NULL,
			resolution_note text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_status (status),
			KEY idx_session (session_id),
			KEY idx_category (category, status),
			KEY idx_created (created_at)
		) {$charset_collate};";

		// 4. kl_meta — polymorphic key-value extensibility (fcom_meta pattern).
		$tables[] = "CREATE TABLE {$prefix}kl_meta (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			object_type varchar(50) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			meta_key varchar(191) NOT NULL DEFAULT '',
			meta_value longtext DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY idx_object (object_type, object_id),
			KEY idx_key (meta_key)
		) {$charset_collate};";

		// 5. kl_revisions — document version history.
		$tables[] = "CREATE TABLE {$prefix}kl_revisions (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			document_id bigint(20) unsigned NOT NULL,
			version int unsigned NOT NULL DEFAULT 1,
			title varchar(255) NOT NULL DEFAULT '',
			content longtext NOT NULL,
			metadata longtext DEFAULT NULL,
			changed_by bigint(20) unsigned NOT NULL DEFAULT 0,
			change_summary varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_document_ver (document_id, version),
			KEY idx_created (created_at)
		) {$charset_collate};";

		// 6. kl_tags — flat tagging system for knowledge layer entities.
		$tables[] = "CREATE TABLE {$prefix}kl_tags (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(191) NOT NULL,
			slug varchar(191) NOT NULL,
			description text DEFAULT NULL,
			color varchar(7) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY idx_slug (slug),
			KEY idx_title (title)
		) {$charset_collate};";

		// 7. kl_activity — automatic ability execution log.
		$tables[] = "CREATE TABLE {$prefix}kl_activity (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ability_name varchar(191) NOT NULL,
			category varchar(100) NOT NULL DEFAULT '',
			input_hash varchar(64) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'success',
			error_code varchar(100) DEFAULT NULL,
			duration_ms int unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			session_id varchar(64) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_ability (ability_name),
			KEY idx_category (category),
			KEY idx_status (status),
			KEY idx_user (user_id),
			KEY idx_session (session_id),
			KEY idx_created (created_at)
		) {$charset_collate};";

		// 8. kl_taggables — polymorphic pivot for tag assignments.
		$tables[] = "CREATE TABLE {$prefix}kl_taggables (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			tag_id bigint(20) unsigned NOT NULL,
			taggable_id bigint(20) unsigned NOT NULL,
			taggable_type varchar(50) NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY idx_unique_tagging (tag_id, taggable_id, taggable_type),
			KEY idx_taggable (taggable_type, taggable_id),
			KEY idx_tag (tag_id)
		) {$charset_collate};";

		return implode( "\n", $tables );
	}
}
