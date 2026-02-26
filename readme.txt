=== Abilities Suite for WordPress ===
Contributors: influencentricity
Tags: ai, mcp, abilities, api, automation
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 3.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Complete native WordPress AI control through the Abilities API — content, blocks, meta, settings, cron, themes, patterns, site health, REST discovery, menus, and more.

== Description ==

Abilities Suite for WordPress gives AI clients full control over native WordPress functionality through the [WordPress Abilities API](https://developer.wordpress.org/abilities/). 93 abilities across 17 modules — from content management to block editing, metadata to cron scheduling.

**Works with any MCP-compatible AI client** — Claude Code, Claude Desktop, Cursor, and more.

= 17 Modules =

* **Content** — List, get, create, update, delete posts/pages/CPTs
* **Taxonomies** — Categories, tags, custom taxonomies CRUD
* **Plugins** — List, activate, deactivate, install from repository
* **Media** — Upload, list, edit, delete attachments
* **Users** — User management with role support
* **Comments** — Full comment lifecycle management
* **Menus** — Navigation menu CRUD with location management
* **Block Editor** — Parse, serialize, find, insert, replace, remove blocks
* **Block Patterns** — List, register, unregister patterns
* **Meta Fields** — Post, term, and user meta CRUD
* **Settings** — Read and update core WordPress settings (with allowlist)
* **Site Health** — Diagnostics, health tests, debug info
* **Cache / Transients** — Transient management and cache status
* **Cron / Scheduling** — List scheduled events and recurrence patterns
* **Themes** — List themes, read theme mods, inspect theme.json
* **REST Discovery** — Introspect REST API namespaces, routes, schemas
* **Rewrite Rules** — Permalink structure inspection and flush

= Permission Toggles =

Site owners control exactly what AI can do — per module, per operation type:

* **Read** — ON by default. Safe, non-destructive operations.
* **Write** — ON by default. Create and update operations.
* **Delete** — OFF by default. Must be consciously enabled.

Disabled abilities don't appear in the MCP tool list at all. Progressive disclosure — start read-only, enable write when comfortable, enable delete when confident.

= Admin Dashboard =

* **Abilities Explorer** — Browse, filter, and inspect all registered abilities across all plugins
* **Permission Toggles** — Per-module Read/Write/Delete control grid
* **Live Ability Count** — See exactly how many abilities are enabled
* **Debug Information** — Version info, source breakdown, copy-ready diagnostics

= Zero Vendor Lock-in =

* 100% open source, GPL v2+
* Runs on any WordPress site
* No external API calls, no tracking, no accounts
* Works with the WordPress Abilities API entering WP 6.9 core

== Installation ==

1. Upload the `wordpress-abilities-suite` folder to `/wp-content/plugins/`
2. Activate through the WordPress Plugins menu
3. Install the WordPress Abilities API plugin (entering WP core in 6.9)
4. Connect an MCP-compatible AI client via the MCP Adapter plugin

== Frequently Asked Questions ==

= What is the WordPress Abilities API? =

The Abilities API is a standardized way for WordPress to expose functionality to AI clients. Each "ability" is a named operation with input/output schemas, permission checks, and meta annotations. It's entering WordPress core in version 6.9.

= What AI clients work with this? =

Any client that speaks the Model Context Protocol (MCP) — Claude Code, Claude Desktop, Cursor, and others. You need the MCP Adapter for WordPress plugin to bridge between MCP and the Abilities API.

= Is this safe? =

Yes. All abilities respect WordPress capabilities (current_user_can). Delete operations are OFF by default and must be explicitly enabled. The permission toggles give site owners full control over what AI can do.

= Does this work on multisite? =

Yes. The plugin is network-compatible and can be activated network-wide or per-site.

== Changelog ==

= 3.1.0 =
* Added per-module Read/Write/Delete permission toggles
* Added permission settings page in admin dashboard
* Added live ability count in admin UI
* All 17 modules wrapped in conditional registration based on permissions
* Delete abilities default to OFF for safety

= 3.0.0 =
* Added 10 new modules: blocks, patterns, meta, settings, site-health, cache, cron, themes, REST discovery, rewrite
* Added admin dashboard with abilities explorer
* Absorbed menu-abilities into core suite
* 93 total abilities across 17 categories

= 2.0.0 =
* Initial release with content, taxonomies, plugins, media, users, comments, menus
* 51 abilities across 7 categories
