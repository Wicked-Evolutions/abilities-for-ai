=== Abilities for AI ===
Contributors: wickedevolutions
Tags: ai, mcp, abilities, api, automation
Requires at least: 6.9
Tested up to: 6.9.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

138 native WordPress abilities across 18 modules — full AI control through the Abilities API. 77 free, 61 pro.

== Description ==

Abilities for AI gives AI clients full control over native WordPress functionality through the [WordPress Abilities API](https://developer.wordpress.org/abilities/). 138 abilities across 18 modules — from content management to block editing, metadata to cron scheduling.

**Works with any MCP-compatible AI client or IDE** — Claude Code, Claude Desktop, Gemini CLI, Cursor, Windsurf, VS Code, and any other tool that supports the Model Context Protocol.

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
* **Status** (1) — Plugin status and diagnostics

= Permission Toggles =

Site owners control exactly what AI can do — per module, per operation type:

* **Read** — ON by default. Safe, non-destructive operations.
* **Write** — ON by default. Create and update operations.
* **Delete** — OFF by default. Must be consciously enabled.

Disabled abilities are not registered — they do not appear in the API at all.

= Admin Dashboard =

**Settings > Abilities for AI** with two tabs:

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

1. Upload `abilities-for-ai/` to `wp-content/plugins/`
2. Activate the plugin
3. All 138 abilities auto-register through the Abilities API
4. Install [Abilities MCP Adapter](https://github.com/Wicked-Evolutions/abilities-mcp-adapter) for MCP protocol support
5. Connect your AI client via [Abilities MCP](https://github.com/Wicked-Evolutions/abilities-mcp)

No Composer dependencies required.

== Frequently Asked Questions ==

= What is the WordPress Abilities API? =

The Abilities API is a standardized way for WordPress to expose functionality to AI clients. Each ability is a named operation with input/output schemas, permission checks, and meta annotations. It shipped in WordPress 6.9 core. WordPress 7.0 (April 2026) adds the JS client, completing the server-client loop.

= What AI clients work with this? =

Any AI client or IDE that supports the Model Context Protocol (MCP) — Claude Code, Claude Desktop, Gemini CLI, Cursor, Windsurf, VS Code, and others. You need the Abilities MCP Adapter plugin to bridge between MCP and the Abilities API.

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

= 1.0.0 =
* First public release as Abilities for AI
* 138 abilities across 18 modules (77 free, 61 pro)
* Free/Pro tier with round-trip model — read + test write + delete test
* Per-module Read/Write/Delete permission toggles
* Per-ability permission overrides with execution-time gating
* Unified admin dashboard — Abilities Explorer + License tabs
* FluentCart API license validation with 24-hour cache and 7-day grace period
* Filesystem module with ABSPATH containment and extension whitelist
* Security hardening — capability checks, SSRF protection, destructive ops default OFF
* Multisite support with network-wide licensing

== Screenshots ==

1. Abilities Explorer — browse all registered abilities with inline permission toggles.
2. License activation — enter and validate your Pro license key.

== Upgrade Notice ==

= 1.0.0 =
First stable release. 138 abilities across 18 modules.
