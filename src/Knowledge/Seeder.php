<?php
/**
 * Knowledge Layer — Seeder.
 *
 * Seeds starter documents on plugin activation and handles
 * migration from v0.0.1 (filesystem .md files → database).
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 *
 * @package WickedEvolutions\AbilitiesForAI\Knowledge
 */

namespace WickedEvolutions\AbilitiesForAI\Knowledge;

defined( 'ABSPATH' ) || exit;

class Seeder {

	/**
	 * Run the full seed sequence.
	 *
	 * Safe to call multiple times — checks for existing slugs before inserting.
	 */
	public static function seed() {
		self::seed_boot();
		self::seed_agents();
		self::seed_skills();
		self::seed_knowledge();
		self::seed_courses();
		self::seed_config();
		self::seed_templates();
		self::migrate_v001();
	}

	/**
	 * Seed a single document if it doesn't already exist.
	 *
	 * @param array $data Document data (must include doc_type and slug).
	 * @return object|null Created document or null if already exists.
	 */
	private static function seed_doc( $data ) {
		$existing = Document::find_by_slug( $data['doc_type'], $data['slug'] );
		if ( $existing ) {
			return null;
		}

		$data = array_merge( $data, array(
			'source' => 'plugin',
			'locked' => true,
			'status' => 'seed',
		) );

		$result = Document::create( $data );
		return is_wp_error( $result ) ? null : $result;
	}

	/**
	 * Seed the boot sequence document.
	 */
	private static function seed_boot() {
		self::seed_doc( array(
			'doc_type' => 'boot',
			'slug'     => 'startup',
			'title'    => 'Boot Sequence',
			'excerpt'  => 'Startup sequence for AI connecting to this site.',
			'content'  => self::boot_content(),
			'metadata' => array(
				'steps' => array(
					'check_sessions',
					'read_essence',
					'read_site_identity',
					'read_site_state',
					'check_agents',
					'check_capabilities',
				),
			),
		) );
	}

	/**
	 * Seed the 5 core agent documents.
	 */
	private static function seed_agents() {
		$agents = array(
			array(
				'slug'     => 'diagnostician',
				'title'    => 'Diagnostician',
				'excerpt'  => 'Runs diagnostics and analyzes site health, content, structure, and performance.',
				'metadata' => array(
					'lanes'              => array( 'content', 'structure', 'system' ),
					'permission_level'   => 'read-only',
					'course_requirement' => null,
					'behavioral_rules'   => array( 'pacing_rule', 'read_before_write' ),
				),
				'content'  => "# Diagnostician\n\nThe Diagnostician agent runs structured diagnostics across content, structure, and system lanes. It observes, analyzes, and reports — but never modifies.\n\n## Behavioral Rules\n- Always follow the Pacing Rule: never chain ability calls without the human choosing the next step\n- Read before analyzing — gather data first, then interpret\n- Log all findings as observations\n- Suggest follow-up actions but never execute them\n\n## Available Protocols\n- Health Check\n- Content Structure\n- Theme and Design\n- Plugin Audit\n- Scheduled Tasks",
			),
			array(
				'slug'     => 'publisher',
				'title'    => 'Publisher',
				'excerpt'  => 'Creates and manages content — posts, pages, media, and taxonomies.',
				'metadata' => array(
					'lanes'              => array( 'content' ),
					'permission_level'   => 'read-write',
					'course_requirement' => 'introduction',
					'behavioral_rules'   => array( 'pacing_rule', 'confirm_before_publish' ),
				),
				'content'  => "# Publisher\n\nThe Publisher agent works in the content lane — creating, editing, and organizing posts, pages, media, and taxonomies.\n\n## Requirements\n- Must complete the Introduction Course before activating\n- Requires read-write permissions on the content module\n\n## Behavioral Rules\n- Always follow the Pacing Rule\n- Confirm with the human before publishing (moving to 'publish' status)\n- Respect existing content structure and taxonomies",
			),
			array(
				'slug'     => 'designer',
				'title'    => 'Designer',
				'excerpt'  => 'Works with themes, templates, patterns, blocks, and visual structure.',
				'metadata' => array(
					'lanes'              => array( 'structure' ),
					'permission_level'   => 'read-write',
					'course_requirement' => 'introduction',
					'behavioral_rules'   => array( 'pacing_rule', 'css_effects_only' ),
				),
				'content'  => "# Designer\n\nThe Designer agent works in the structure lane — themes, templates, patterns, blocks, and visual layout.\n\n## Requirements\n- Must complete the Introduction Course before activating\n- Requires read-write permissions on themes, blocks, and patterns modules\n\n## Behavioral Rules\n- Always follow the Pacing Rule\n- CSS is for effects only — layout through WordPress editor settings\n- Native Gutenberg blocks only — no third-party block plugins",
			),
			array(
				'slug'     => 'maintainer',
				'title'    => 'Maintainer',
				'excerpt'  => 'Handles system health, updates, security, cron, and plugin management.',
				'metadata' => array(
					'lanes'              => array( 'system' ),
					'permission_level'   => 'read-write',
					'course_requirement' => 'introduction',
					'behavioral_rules'   => array( 'pacing_rule', 'backup_before_update' ),
				),
				'content'  => "# Maintainer\n\nThe Maintainer agent works in the system lane — health checks, plugin updates, cron management, cache, and security.\n\n## Requirements\n- Must complete the Introduction Course before activating\n- Requires read-write permissions on plugins, cron, and settings modules\n\n## Behavioral Rules\n- Always follow the Pacing Rule\n- Recommend backups before any update operations\n- Flag security observations immediately",
			),
			array(
				'slug'     => 'operator',
				'title'    => 'Operator',
				'excerpt'  => 'Full-access agent with all lanes unlocked. For experienced users.',
				'metadata' => array(
					'lanes'              => array( 'content', 'structure', 'system', 'creative', 'ecosystem' ),
					'permission_level'   => 'read-write',
					'course_requirement' => 'introduction',
					'behavioral_rules'   => array( 'pacing_rule' ),
				),
				'content'  => "# Operator\n\nThe Operator agent has access to all lanes and all capabilities. Designed for experienced users who understand WordPress and want full AI-assisted control.\n\n## Requirements\n- Must complete the Introduction Course before activating\n\n## Behavioral Rules\n- Always follow the Pacing Rule\n- All other behavioral rules from specialized agents apply when working in their lanes",
			),
		);

		foreach ( $agents as $agent ) {
			self::seed_doc( array_merge( $agent, array( 'doc_type' => 'agent' ) ) );
		}
	}

	/**
	 * Seed starter skill/protocol documents.
	 *
	 * Content is loaded from seed-content/skills/*.md files to preserve
	 * full prototype fidelity. Metadata (steps, versions) matches the
	 * prototype protocols exactly.
	 */
	private static function seed_skills() {
		$skills = array(
			array(
				'slug'     => 'initial-read',
				'title'    => 'Initial Read Protocol',
				'excerpt'  => 'First contact sequence — introduces the product, runs first diagnostics, builds site ESSENCE.',
				'metadata' => array(
					'lane'    => 'system',
					'course'  => 'introduction',
					'version' => '0.3.0',
					'steps'   => array(
						'connection_check',
						'product_intro',
						'first_choice',
						'introduction_course',
						'story_read',
						'essence_synthesis',
					),
					'pacing' => true,
				),
			),
			array(
				'slug'     => 'health-check',
				'title'    => 'Health Check Protocol',
				'excerpt'  => 'System lane diagnostic — environment, plugins, cron, cache, security basics.',
				'metadata' => array(
					'lane'    => 'system',
					'course'  => 'introduction',
					'version' => '0.1.0',
					'steps'   => array( 'site_health_info', 'plugins_check', 'cron_check', 'cache_check' ),
					'pacing'  => true,
				),
			),
			array(
				'slug'     => 'content-structure',
				'title'    => 'Content Structure Protocol',
				'excerpt'  => 'Content lane diagnostic — content types, taxonomies, page hierarchy, commerce.',
				'metadata' => array(
					'lane'    => 'content',
					'course'  => 'introduction',
					'version' => '0.2.0',
					'steps'   => array( 'content_types', 'taxonomies', 'page_hierarchy', 'commerce' ),
					'pacing'  => true,
				),
			),
			array(
				'slug'     => 'theme-and-design',
				'title'    => 'Theme and Design Protocol',
				'excerpt'  => 'Structure lane diagnostic — active theme, modifications, menus, patterns.',
				'metadata' => array(
					'lane'    => 'structure',
					'course'  => 'introduction',
					'version' => '0.1.0',
					'steps'   => array( 'active_theme', 'theme_modifications', 'menu_structure', 'patterns' ),
					'pacing'  => true,
				),
			),
			array(
				'slug'     => 'end-session',
				'title'    => 'End Session Protocol',
				'excerpt'  => 'Session continuity — gather summary, update site state, write session log, verify, present.',
				'metadata' => array(
					'lane'    => 'system',
					'version' => '1.0.0',
					'steps'   => array(
						'gather_session_summary',
						'update_site_state',
						'write_session_log',
						'verify_knowledge_files',
						'present_summary',
					),
					'pacing'  => false,
				),
			),
			array(
				'slug'     => 'plugin-audit',
				'title'    => 'Plugin Audit Protocol',
				'excerpt'  => 'Deep plugin analysis — active/inactive, update status, redundancy, recommendations.',
				'metadata' => array(
					'lane'    => 'system',
					'version' => '0.1.0',
					'steps'   => array( 'list_plugins', 'check_updates', 'identify_redundancy', 'recommend' ),
					'pacing'  => true,
				),
			),
			array(
				'slug'     => 'scheduled-tasks',
				'title'    => 'Scheduled Tasks Protocol',
				'excerpt'  => 'Cron health analysis — list events, identify owners, flag anomalies.',
				'metadata' => array(
					'lane'    => 'system',
					'version' => '0.1.0',
					'steps'   => array( 'list_events', 'identify_owners', 'flag_anomalies' ),
					'pacing'  => true,
				),
			),
		);

		foreach ( $skills as $skill ) {
			$skill['content']  = self::load_seed_content( 'skills/' . $skill['slug'] . '.md' );
			$skill['doc_type'] = 'skill';
			self::seed_doc( $skill );
		}
	}

	/**
	 * Load seed content from a file in the seed-content/ directory.
	 *
	 * @param string $relative_path Path relative to seed-content/ (e.g. 'skills/initial-read.md').
	 * @return string File contents, or empty string if not found.
	 */
	private static function load_seed_content( $relative_path ) {
		$file = ABILITIES_FOR_AI_PATH . 'seed-content/' . $relative_path;
		if ( ! file_exists( $file ) ) {
			return '';
		}
		return file_get_contents( $file );
	}

	/**
	 * Seed starter knowledge documents.
	 */
	private static function seed_knowledge() {
		self::seed_doc( array(
			'doc_type' => 'knowledge',
			'slug'     => 'getting-started',
			'title'    => 'Getting Started with Abilities for AI',
			'excerpt'  => 'Onboarding guide for AI connecting to a WordPress site through Abilities for AI.',
			'content'  => self::getting_started_content(),
			'metadata' => array(),
		) );
	}

	/**
	 * Seed the Introduction Course.
	 */
	private static function seed_courses() {
		self::seed_doc( array(
			'doc_type' => 'course',
			'slug'     => 'introduction',
			'title'    => 'Introduction Course',
			'excerpt'  => 'Progressive learning path through initial diagnostics. Unlocks agent modes on completion.',
			'content'  => "# Introduction Course\n\nThe Introduction Course walks through the core diagnostic protocols to build a foundational understanding of the site.\n\n## Protocols (in order)\n1. Health Check — understand the system\n2. Content Structure — understand the content\n3. Theme and Design — understand the structure\n4. Story Read — understand the voice and purpose\n5. ESSENCE Synthesis — compile everything into the First Encounter Brief\n\n## Completion Criteria\n- All 5 protocols completed\n- ESSENCE document created and confirmed by human\n\n## Unlocks\nOn completion, the following agent modes become available:\n- Publisher\n- Designer\n- Maintainer\n- Operator",
			'metadata' => array(
				'level'               => 'introduction',
				'protocols'           => array( 'health-check', 'content-structure', 'theme-and-design', 'story-read', 'essence-synthesis' ),
				'completion_criteria' => array( 'health_check_done', 'content_mapped', 'theme_analyzed', 'story_read', 'essence_confirmed' ),
				'unlocks_agents'      => array( 'publisher', 'designer', 'maintainer', 'operator' ),
			),
		) );
	}

	/**
	 * Seed config documents.
	 */
	private static function seed_config() {
		// Diagnostic Lanes.
		self::seed_doc( array(
			'doc_type' => 'config',
			'slug'     => 'diagnostic-lanes',
			'title'    => 'Diagnostic Lanes',
			'excerpt'  => 'Defines the 5 core and 5 advanced diagnostic lanes.',
			'content'  => "# Diagnostic Lanes\n\n## Core Lanes\n- **Content** — Posts, pages, media, taxonomies, commerce\n- **Structure** — Themes, templates, patterns, blocks, navigation\n- **System** — Health, plugins, cron, cache, environment\n- **Creative** — Voice, brand, design patterns, visual identity\n- **Ecosystem** — Integrations, APIs, external services, CRM\n\n## Advanced Lanes\n- **Security** — Vulnerability scanning, hardening, access control\n- **Migration** — Site moves, content import/export, platform changes\n- **Performance** — Speed, caching, database optimization, CDN\n- **Scaling** — Multisite, load handling, infrastructure growth\n- **Accessibility** — WCAG compliance, screen reader support, keyboard nav",
			'metadata' => array(
				'core_lanes'     => array( 'content', 'structure', 'system', 'creative', 'ecosystem' ),
				'advanced_lanes' => array( 'security', 'migration', 'performance', 'scaling', 'accessibility' ),
			),
		) );

		// Pacing Rule.
		self::seed_doc( array(
			'doc_type' => 'config',
			'slug'     => 'pacing-rule',
			'title'    => 'Pacing Rule',
			'excerpt'  => 'Core behavioral rule: never chain ability calls without human choosing next step.',
			'content'  => "# Pacing Rule\n\nThe Pacing Rule is the most important behavioral constraint in the Knowledge Layer.\n\n## Rule\n**Never chain ability calls without the human choosing the next step.**\n\n## Why\n- Humans learn by choosing, not by watching\n- Each step should be a conscious decision\n- AI should present options, not execute sequences\n- Progressive disclosure prevents overwhelm\n\n## Exceptions\n- End Session Protocol (runs as a sequence for continuity)\n- Boot sequence checks (read-only orientation)\n\n## Enforcement\nAll protocol metadata includes `pacing: true/false`. Agents check this before executing steps.",
			'metadata' => array(
				'rule'       => 'never_chain_without_human_choice',
				'exceptions' => array( 'end-session', 'boot-checks' ),
			),
		) );
	}

	/**
	 * Seed template documents.
	 */
	private static function seed_templates() {
		self::seed_doc( array(
			'doc_type' => 'template',
			'slug'     => 'first-encounter-brief',
			'title'    => 'First Encounter Brief Template',
			'excerpt'  => 'Template for ESSENCE synthesis — the site identity document built through conversation.',
			'content'  => "# First Encounter Brief\n\n> Template for ESSENCE synthesis. Sections are filled through diagnostic protocols.\n\n## Identity\n- **Site name:**\n- **URL:**\n- **Owner/Brand:**\n- **Mission:**\n- **Primary audience:**\n\n## Technical Profile\n- **WordPress version:**\n- **Theme:**\n- **Key plugins:**\n- **Hosting environment:**\n\n## Content Architecture\n- **Post types:**\n- **Taxonomies:**\n- **Page hierarchy:**\n- **Commerce:**\n\n## Voice & Brand\n- **Tone:**\n- **Style:**\n- **Key themes:**\n\n## Observations\n- Key findings from diagnostics\n\n## Recommended Next Steps\n- Suggested actions based on analysis",
			'metadata' => array(
				'diagnostic_sources' => array( 'health-check', 'content-structure', 'theme-and-design', 'story-read' ),
				'output_doc_type'    => 'essence',
			),
		) );
	}

	/**
	 * Update existing seed documents with fresh content from seed files.
	 *
	 * Only updates documents where source = 'plugin' and locked = 1.
	 * This is called during schema migrations to refresh protocol content
	 * without losing user forks or custom documents.
	 */
	public static function update_seeds() {
		global $wpdb;
		$table = $wpdb->prefix . 'kl_documents';

		// Get all plugin-seeded skill documents.
		$seeds = $wpdb->get_results(
			"SELECT id, doc_type, slug FROM {$table} WHERE source = 'plugin' AND locked = 1 AND doc_type = 'skill'"
		);

		if ( empty( $seeds ) ) {
			return;
		}

		foreach ( $seeds as $seed ) {
			$file_content = self::load_seed_content( 'skills/' . $seed->slug . '.md' );
			if ( empty( $file_content ) ) {
				continue;
			}

			Document::update( $seed->id, array(
				'content' => $file_content,
			) );
		}
	}

	/**
	 * Migrate v0.0.1 filesystem knowledge docs into the database.
	 *
	 * Reads .md files from the plugin's knowledge/ directory and inserts
	 * them as knowledge documents if they don't already exist.
	 */
	private static function migrate_v001() {
		$knowledge_dir = ABILITIES_FOR_AI_PATH . 'knowledge/';
		if ( ! is_dir( $knowledge_dir ) ) {
			return;
		}

		$files = glob( $knowledge_dir . '*.md' );
		if ( empty( $files ) ) {
			return;
		}

		foreach ( $files as $file ) {
			$slug = basename( $file, '.md' );

			// Skip if already in database.
			if ( Document::find_by_slug( 'knowledge', $slug ) ) {
				continue;
			}

			$contents = file_get_contents( $file );
			if ( false === $contents ) {
				continue;
			}

			// Extract H1 as title.
			$title = $slug;
			if ( preg_match( '/^#\s+(.+)$/m', $contents, $matches ) ) {
				$title = trim( $matches[1] );
			}

			// Extract first blockquote as excerpt.
			$excerpt = "Knowledge doc: {$slug}";
			if ( preg_match( '/^>\s+(.+)$/m', $contents, $matches ) ) {
				$excerpt = trim( $matches[1] );
			}

			self::seed_doc( array(
				'doc_type' => 'knowledge',
				'slug'     => $slug,
				'title'    => $title,
				'excerpt'  => $excerpt,
				'content'  => $contents,
				'metadata' => array( 'migrated_from' => 'v0.0.1' ),
			) );
		}
	}

	/**
	 * Boot sequence content.
	 */
	private static function boot_content() {
		return "# Boot Sequence\n\nThis is the startup sequence for AI connecting to this WordPress site.\n\n## Steps\n\n1. **Check Sessions** — Are there previous sessions? If no → Bootstrap (first visit). If yes → Returning.\n2. **Read ESSENCE** — If it exists, load the site identity synthesis\n3. **Read Site Identity** — Technical facts from previous diagnostics\n4. **Read Site State** — Current state from last session end\n5. **Check Agents** — Which agent modes are available based on completed courses\n6. **Check Capabilities** — What abilities are available on this site\n\n## First Visit (Bootstrap)\nIf no previous sessions exist, skip steps 2-4 and begin the Initial Read Protocol.\n\n## Returning Visit\nLoad previous state and present: what happened last time, what's pending, suggested next steps.";
	}

	/**
	 * Getting started knowledge content.
	 */
	private static function getting_started_content() {
		return "# Getting Started with Abilities for AI\n\n> Your guide to understanding how AI works with this WordPress site.\n\n## What is Abilities for AI?\n\nAbilities for AI gives any AI assistant structured access to your WordPress site through the WordPress Abilities API. Instead of giving AI raw database access, every operation goes through a defined ability with permissions, validation, and safety checks.\n\n## How It Works\n\n1. **Abilities** — Individual tools the AI can use (list content, check health, create posts, etc.)\n2. **Knowledge** — Domain understanding that helps AI make sense of what it sees\n3. **Skills** — Step-by-step procedures (protocols) that guide AI through common tasks\n4. **Agents** — Operational modes that bundle capabilities for specific roles (Publisher, Designer, Maintainer, etc.)\n\n## The Onboarding Process\n\nWhen AI first connects to your site:\n1. It runs the **Boot Sequence** to orient itself\n2. It introduces itself and Abilities for AI\n3. You choose: guided tour (Introduction Course) or go directly to a specific task\n4. The Introduction Course walks through diagnostics that build a **First Encounter Brief** — a comprehensive understanding of your site\n\n## The Pacing Rule\n\nThe most important rule: **AI never chains actions without you choosing the next step.** You're always in control. AI presents options, you decide.\n\n## What Grows Over Time\n\nEvery session builds knowledge:\n- **ESSENCE** — Your site's identity, refined through conversation\n- **Observations** — Things AI notices that might need attention\n- **Session History** — What happened, what's next\n- **Skills & Agents** — New procedures and modes can be added as your site grows";
	}
}
