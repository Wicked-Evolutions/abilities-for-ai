=== Abilities Suite for WordPress ===
Contributors: influencentricity
Tags: ai, mcp, abilities, api, automation
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 3.10.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

138 native WordPress abilities across 18 modules — full AI control through the Abilities API. 77 free, 61 pro.

== Description ==

Abilities Suite for WordPress gives AI clients full control over native WordPress functionality through the [WordPress Abilities API](https://developer.wordpress.org/abilities/). 138 abilities across 18 modules — from content management to block editing, metadata to cron scheduling.

**Works with any MCP-compatible AI client** — Claude Code, Claude Desktop, Cursor, and more.

= Free Tier — The Round-Trip (77 abilities) =

The free tier gives AI full read access to everything plus a controlled write round-trip: create something, inspect it, delete it. AI agents prove competence before a site owner commits to Pro.

* **Read everything** (71) — content, blocks, settings, users, themes, site health, REST discovery, and more
* **Test write** (4) — content/create, taxonomies/create-term, plugins/install, media/create
* **Delete the test** (6) — content/delete, blocks/remove, taxonomies/delete-term, menus/delete-menu, menus/delete-menu-item, media/delete

= Pro Tier — The Juice (61 abilities) =

Pro unlocks the abilities that build on what exists: update content, modify blocks, bulk search-replace, manage themes, configure settings, assign taxonomies, reorder menus. The operations that turn an AI assistant into an AI operator.

= Pro Gate =

All 138 abilities are registered and visible to AI regardless of license. Pro abilities return a clear 403 with an upgrade URL at execution time. The AI discovers the gate naturally. License validation uses FluentCart API with 24-hour cache and 7-day grace period.

= 18 Modules =

* **Content** (11) — List, get, create, update, delete posts/pages/CPTs, search-replace, change type
* **Blocks** (8) — Parse, serialize, find, insert, replace, remove blocks in post content
* **Taxonomies** (8) — Discover taxonomies, CRUD terms, assign to content
* **Menus** (12) — Full navigation menu lifecycle with location management
* **Meta** (13) — Post, term, and user meta — list, get, update, delete, list-registered
* **Cache** (7) — Transient management, page cache purge, object cache status and flush
* **Plugins** (7) — List, get, search, install, activate, deactivate, delete
* **Media** (6) — List, get, create, upload, update, delete attachments
* **Users** (5) — User management with role support
* **Comments** (5) — Full comment lifecycle management
* **Patterns** (5) — List, get, list-categories, register, unregister block patterns
* **Settings** (6) — Read and update core WordPress settings with allowlist
* **Themes** (10) — List, activate, install, theme mods, theme.json, delete
* **Site Health** (4) — Status, tests, diagnostics, debug info
* **Filesystem** (5) — List directories, read/write files, theme assets (extension whitelist, ABSPATH containment)
* **REST Discovery** (4) — Namespaces, routes, schemas, full index
* **Cron** (5) — List events, schedules, get event, create, delete
* **Rewrite** (3) — Permalink structure, rules, flush
* **Suite** (1) — Suite status and diagnostics

= Permission Toggles =

Site owners control exactly what AI can do — per module, per operation type:

* **Read** — ON by default. Safe, non-destructive operations.
* **Write** — ON by default. Create and update operations.
* **Delete** — OFF by default. Must be consciously enabled.

Disabled abilities are not registered — they do not appear in the API at all.

= Admin Dashboard =

**Settings > Abilities Suite** with two tabs:

* **Abilities Explorer** — Browse all registered abilities with inline R/W/D toggles per module. Filter by module, operation type, or tier.
* **License** — Pro license validation. Network-wide license available for multisite.

= Security =

* WordPress capability checks on every ability execution
* Per-module permission toggles (R/W/D)
* Pro gate: both permission toggle AND license must pass
* Filesystem: ABSPATH containment, extension whitelist, DISALLOW_FILE_EDIT respected
* Delete operations default OFF

= Multisite =

Network activation supported. Network-wide license at $199 LTD covers all subsites.

== Installation ==

1. Upload `abilities-suite-for-wordpress/` to `wp-content/plugins/`
2. Activate the plugin
3. All 138 abilities auto-register through the Abilities API
4. Install [MCP Adapter for WordPress](https://github.com/Influencentricity/mcp-adapter-for-wordpress) for MCP protocol support
5. Connect your AI client via [WP Abilities MCP](https://github.com/Influencentricity/wp-abilities-mcp)

No Composer dependencies required.

== Frequently Asked Questions ==

= What is the WordPress Abilities API? =

The Abilities API is a standardized way for WordPress to expose functionality to AI clients. Each ability is a named operation with input/output schemas, permission checks, and meta annotations. It shipped in WordPress 6.9 core. WordPress 7.0 (April 2026) adds the JS client, completing the server-client loop.

= What AI clients work with this? =

Any client that speaks the Model Context Protocol (MCP) — Claude Code, Claude Desktop, Cursor, and others. You need the MCP Adapter for WordPress plugin to bridge between MCP and the Abilities API.

= What is the difference between free and pro? =

Free gives you full read access (71 abilities) plus a controlled write round-trip — create, inspect, delete — so AI can prove competence on your site. Pro unlocks the 61 abilities that modify, update, and manage production state.

= How does the pro gate work? =

All 138 abilities are visible to AI regardless of license. Pro abilities return a 403 response with an upgrade URL at execution time. The AI discovers the boundary naturally — no hidden capabilities.

= Is this safe? =

Yes. All abilities enforce WordPress capabilities (current_user_can). Delete operations are OFF by default. Per-module permission toggles give site owners full control. Both permission toggle AND license must pass for pro abilities.

= Does this work on multisite? =

Yes. Network activation supported. Network-wide license at $199 LTD covers all subsites.

= What about the filesystem module? =

Filesystem abilities use ABSPATH containment (realpath validation), an extension whitelist (.css, .js, .json, .md, .txt, .html — no .php), and respect DISALLOW_FILE_EDIT. Write permissions default OFF.

== Changelog ==

= 3.10.0 =
* Free/Pro tier realignment — round-trip model (read + test write + delete test)
* 9 abilities moved to free, 1 to pro
* GPL compliance headers added across all source files
* New split: 77 free / 61 pro (138 total)

= 3.9.0 =
* Per-ability permission overrides with execution-time gating

= 3.8.1 =
* Multisite licensing fix — current_product_id() for network-scoped licenses

= 3.8.0 =
* Unified admin dashboard — Abilities Explorer + License tabs
* FluentCart API license validation with 24-hour cache + 7-day grace period

= 3.7.0 =
* Added post_date param for content/create and content/update
* DISALLOW_FILE_EDIT check in filesystem abilities
* Dead code cleanup
* Ability count corrected to 138 (was 111)

= 3.6.0 =
* Added Filesystem abilities module (4 abilities): list-directory, read-file, write-file, theme/update-asset
* 18th ability category: filesystem
* Native PHP filesystem functions (CageFS/CloudLinux compatible)
* Extension whitelist security and ABSPATH containment

= 3.5.1 =
* Fixed content/list division by zero on multisite subsites
* Fixed cache/flush-page-cache single-post purge crash on LiteSpeed

= 3.5.0 =
* Added content/change-type — convert posts between post types
* Added content/search-replace — bulk find/replace with dry_run preview
* Added cache/flush-page-cache — purge page cache with auto-detection

= 3.3.0 =
* Security hardening: 10 findings from audit, all fixed
* Object-level authorization, SSRF protection, destructive ops default OFF
* MCP metadata normalized across all abilities

= 3.1.0 =
* Added per-module Read/Write/Delete permission toggles
* Added permission settings page in admin dashboard
* Added live ability count in admin UI
* All 18 modules wrapped in conditional registration based on permissions
* Delete abilities default to OFF for safety

= 3.0.0 =
* Added 10 new modules: blocks, patterns, meta, settings, site-health, cache, cron, themes, REST discovery, rewrite
* Added admin dashboard with abilities explorer
* Absorbed menu-abilities into core suite
* 93 abilities across 17 categories (initial v3 release)

= 2.0.0 =
* Initial release with content, taxonomies, plugins, media, users, comments, menus
* 51 abilities across 7 categories
