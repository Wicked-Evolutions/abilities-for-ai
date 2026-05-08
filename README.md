# Abilities for AI

> **A word from J, the director of this creation.**
>
> Everything you see here is built by a single human who does not read or write code and is written by AI. Everything is in constant motion and by observing that movement we create the illusion of being still. Change happens at any given moment. It is simply a law of evolution. Stillness is an act of conscious awareness, not a reality of life.

## Welcome, Wordpressnaut

Here is the spaceship, now you'll have to learn how to fly and please do remember, humans make mistakes, humans created AI so AI makes mistakes. Learning to fly is your job and to do that you'll need structure, systems, checklists, principles and understanding you stand before a magical leap of a steep and wonderful learning curve. Be patient and do backup things.

→ Knowledge layer (deeper traversal): [https://knowledge.wickedevolutions.com](https://knowledge.wickedevolutions.com)
→ [https://wickedevolutions.com](https://wickedevolutions.com)
→ [https://abilitiesforai.io](https://abilitiesforai.io)

Our development aim is the *Official WordPress Compatibility Contract* — see [PRINCIPLES.md](PRINCIPLES.md) for the full binding principles across the four-repo suite.

---

Native WordPress abilities for AI agents. Powers AI control through the official [WordPress Abilities API](https://developer.wordpress.org/reference/functions/wp_register_ability/).

| | |
|---|---|
| **Requires** | WordPress 6.9+, PHP 8.1+ |
| **License** | GPL-2.0-or-later |
| **Author** | [Wicked Evolutions](https://wickedevolutions.com) |

## What It Does

Registers abilities across WordPress core and supported third-party plugins. Each ability is a named operation that AI agents can discover, validate, and execute through the MCP protocol.

Ability counts grow with every release — check the [CHANGELOG](#version-history) for the latest numbers, or call `suite/get-status` on your site for the live count.

## Install

### From our store

Download from [community.wickedevolutions.com/item/abilities-for-ai/](https://community.wickedevolutions.com/item/abilities-for-ai/), then upload via **Plugins → Add New → Upload Plugin**.

### From GitHub

```bash
cd wp-content/plugins/
git clone https://github.com/Wicked-Evolutions/abilities-for-ai.git
```

### You also need

1. **[Abilities MCP Adapter](https://community.wickedevolutions.com/item/abilities-mcp-adapter/)** — exposes abilities as MCP tools via REST API and runs the OAuth 2.1 resource server + authorization server
2. **[Abilities MCP](https://github.com/Wicked-Evolutions/abilities-mcp)** bridge — connects your AI client to WordPress
   - Claude Desktop: drag `abilities-mcp.mcpb` from the [bridge's latest GitHub Release](https://github.com/Wicked-Evolutions/abilities-mcp/releases/latest), then upgrade to OAuth via `abilities-mcp upgrade-auth <site>` from terminal
   - Terminal MCP clients (Claude Code, Cursor, Codex, etc.): `npm install -g @wickedevolutions/abilities-mcp`, then `abilities-mcp add-site <url>` — OAuth by default

See [docs/getting-started.md](docs/getting-started.md) for the full setup guide.

## Free and Pro

**Free** — full read access across all modules, plus a controlled write round-trip (create + delete) so AI agents can prove competence before a site owner moves to Pro.

**Pro** — adds write and delete abilities: update content, modify blocks, bulk search-replace, manage themes, configure settings, assign taxonomies, reorder menus. The operations that turn an AI assistant into an AI operator.

All abilities are registered and visible to AI regardless of license. Pro abilities return a clear 403 with an upgrade path at execution time. No hidden capabilities, no surprise walls.

## WordPress Core Modules

These modules cover WordPress's native functionality. Available on every WordPress 6.9+ site.

### Content
`content/list` · `content/get` · `content/get-snapshot` · `content/get-text` · `content/list-structure` · `content/get-site-map` · `content/discover-types` · `content/find-by-url` · `content/get-by-slug` · `content/create` · `content/update` · `content/append` · `content/duplicate` · `content/change-type` · `content/search-replace` · `content/batch-update` · `content/delete`

### Blocks
`blocks/parse` · `blocks/serialize` · `blocks/list-types` · `blocks/get-type` · `blocks/find-in-post` · `blocks/get-at-path` · `blocks/find-nested` · `blocks/insert` · `blocks/replace` · `blocks/remove` · `blocks/update-attributes` · `blocks/update-at-path` · `blocks/append-inner`

### Taxonomies
`taxonomies/discover` · `taxonomies/list-terms` · `taxonomies/get-term` · `taxonomies/get-content-terms` · `taxonomies/create-term` · `taxonomies/update-term` · `taxonomies/assign-to-content` · `taxonomies/batch-assign` · `taxonomies/delete-term`

### Media
`media/list` · `media/get` · `media/create` · `media/upload` · `media/update` · `media/delete`

### Menus
`menus/list-menus` · `menus/get-menu` · `menus/list-menu-items` · `menus/list-locations` · `menus/create-menu` · `menus/add-menu-item` · `menus/update-menu-item` · `menus/reorder-menu-items` · `menus/assign-location` · `menus/unassign-location` · `menus/delete-menu` · `menus/delete-menu-item`

### Users
`users/list` · `users/get` · `users/create` · `users/update` · `users/delete` · `users/list-app-passwords` · `users/create-app-password` · `users/delete-app-password` · `users/delete-all-app-passwords`

### Meta
`meta/list-post-meta` · `meta/get-post-meta` · `meta/list-term-meta` · `meta/get-term-meta` · `meta/list-user-meta` · `meta/get-user-meta` · `meta/list-registered` · `meta/update-post-meta` · `meta/update-term-meta` · `meta/update-user-meta` · `meta/delete-post-meta` · `meta/delete-term-meta` · `meta/delete-user-meta`

### Comments
`comments/list` · `comments/get` · `comments/create` · `comments/update` · `comments/delete`

### Themes
`themes/list` · `themes/get-active` · `themes/list-mods` · `themes/get-mod` · `themes/get-theme-json` · `themes/design-snapshot` · `themes/activate` · `themes/install` · `themes/set-mod` · `themes/delete` · `themes/delete-mod`

### Plugins
`plugins/list` · `plugins/get` · `plugins/search-repository` · `plugins/install` · `plugins/activate` · `plugins/deactivate` · `plugins/delete`

### Settings
`settings/list` · `settings/get` · `settings/get-group` · `settings/get-permalink-structure` · `settings/update` · `settings/delete`

### Patterns
`patterns/list` · `patterns/get` · `patterns/list-categories` · `patterns/register` · `patterns/unregister`

### Cache
`cache/list-transients` · `cache/get-transient` · `cache/object-cache-status` · `cache/set-transient` · `cache/flush-page-cache` · `cache/delete-transient` · `cache/flush`

### Cron
`cron/list-events` · `cron/list-schedules` · `cron/get-event` · `cron/create-event` · `cron/delete-event`

### Filesystem
`filesystem/list-directory` · `filesystem/read-file` · `filesystem/write-file` · `filesystem/write-binary` · `filesystem/create-directory` · `filesystem/delete-file` · `theme/update-asset` · `theme/enqueue-asset` · `theme/dequeue-asset` · `theme/list-enqueued-assets`

### Site Health
`site-health/status` · `site-health/pulse` · `site-health/list-tests` · `site-health/run-test` · `site-health/info`

### Revisions
`revisions/list` · `revisions/get` · `revisions/restore` · `revisions/delete` · `revisions/purge`

### Rewrite
`rewrite/get-structure` · `rewrite/list-rules` · `rewrite/flush`

### REST Discovery
`rest/list-namespaces` · `rest/list-routes` · `rest/get-route-schema` · `rest/get-index`

### Multisite
`multisite/list-sites` · `multisite/get-site` · `multisite/update-site` · `multisite/get-network-settings`

### Knowledge Layer
`knowledge/list` · `knowledge/get` · `knowledge/create` · `knowledge/update` · `knowledge/delete` · `knowledge/fork` · `knowledge/search` · `knowledge/log-session` · `knowledge/list-sessions` · `knowledge/get-session` · `knowledge/add-observation` · `knowledge/list-observations` · `knowledge/resolve-observation` · `knowledge/get-revisions` · `knowledge/restore-revision`

### Status
`suite/get-status`

### Paired ability classes — architecture pattern

The plugin registers compact-vs-full pairs across the API by design. Each ability description names its payload tradeoff. Pick the pair member that matches the traversal you intend:

- **Bulk discovery (compact)** ↔ **targeted full inspection (full)**
  - `content-list-structure` (id/title/slug/status/date/link, ~0.5KB/post) ↔ `content-list` (full block markup, ~50–200KB/post)
  - `content-get-text` (plain text stripped, ~2–20KB) ↔ `content-get` (full block markup, ~50–200KB)

The pattern recurs across other categories — read the description before reaching for the heavy member when a compact member is available.

## Supported Third-Party Plugins

These modules register automatically when the corresponding plugin is active. No configuration needed.

### Astra Theme
Customizer settings, layouts, typography, colors, and CPT options. See [docs/suites/astra.md](docs/suites/astra.md) for the full ability list.

### Spectra (UAG)
Block CSS, theme classes, and Spectra-specific block settings. See [docs/suites/spectra.md](docs/suites/spectra.md) for the full ability list.

### Presto Player
Video management, presets, audio presets, email collection, stats, webhooks, and settings. See [docs/suites/presto-player.md](docs/suites/presto-player.md) for the full ability list.

### SureCart
Products, prices, orders, subscriptions, customers, coupons, bumps, shipping, tax, licenses, affiliates, and more. See [docs/suites/surecart.md](docs/suites/surecart.md) for the full ability list.

## Admin Dashboard

**Settings → Abilities for AI** provides:

- **Abilities Explorer** — browse all registered abilities with inline Read/Write/Delete toggles per module
- **License** — enter and validate your Pro license key (network-wide for multisite)

### Permissions UI save isolation (since v1.9.3)

The Permissions UI patches only the modules submitted in a save action — toggling a single module's Read/Write/Delete tier preserves every other module's state byte-identically. Per-blog isolation continues to apply on multisite (each blog's permissions live in `wp_abilities_suite_permissions` independently). The admin-post handler shipped in v1.9.3 ([#153](https://github.com/Wicked-Evolutions/abilities-for-ai/issues/153)) replaces the prior Settings API save which rebuilt the entire option from form input on every submit.

## Boundary activity log

Operators get a structured audit trail of MCP protocol events alongside ability execution. The plugin maintains two activity tables:

- `kl_activity` — every ability execution (request, status, duration, response size)
- `kl_boundary` — protocol-layer events (session lifecycle, auth denials, transport errors, rate-limit hits, settings audit changes)

The Activity tab in WP Admin offers a toggle: **Ability executions / Boundary events / Both** — the "Both" view is a UNION-paginated timeline across both tables, sorted by `created_at`.

REST routes (gated by `manage_options`):

- `abilities-kl/v1/activity` — paginated `kl_activity` rows
- `abilities-kl/v1/boundary` — paginated `kl_boundary` rows
- `abilities-kl/v1/boundary/stats` — event-type and severity counts
- `abilities-kl/v1/timeline` — UNION across both tables

The boundary log writer (`BoundaryEventLogger`) implements the `McpObservabilityHandlerInterface` from [Abilities MCP Adapter](https://github.com/Wicked-Evolutions/abilities-mcp-adapter) and listens on the `mcp_adapter_boundary_event` action hook. Both paths route to the same writer with metadata-only sanitization as defense-in-depth.

A daily cron (`abilities_kl_boundary_retention`) prunes rows older than the `kl_boundary_retention_days` filter (default 90 days).

Compatible with Abilities MCP Adapter v1.4.0+ (the version that emits the events this writer consumes).

## Security Model

Every ability enforces WordPress capabilities at execution time. The WordPress user role assigned to your AI agent is the baseline; the per-module Read/Write/Delete toggles in *Settings → Abilities for AI* compose on top of that capability check; OAuth scopes (handled by the [Abilities MCP Adapter](https://github.com/Wicked-Evolutions/abilities-mcp-adapter)) compose on top of those — together this is the four-layer permissions model below.

| Role | Access |
|------|--------|
| **Administrator** | All modules |
| **Editor** | Content, Blocks, Taxonomies, Patterns, Meta, Media |

### Four-layer permissions model

When an ability is denied, the rejection comes from one of four independent layers. The runtime error names the layer:

1. **Abilities for AI module permission** — per-blog Read/Write/Delete toggle in *WP Admin → Abilities for AI → Permissions*. The runtime returns `[ability_disabled]` with the module name and where to fix it. **This plugin is the layer that runs this check.**
2. **WordPress capability** — the WordPress user the request authenticates as lacks the relevant capability. WordPress core REST returns `rest_forbidden` / `rest_cannot_*` codes.
3. **OAuth scope** — the bearer token does not include the scope the ability requires. The adapter's `OAuthScopeEnforcer::check()` returns an `insufficient_scope` rejection at dispatch time.
4. **Unclear** — generic 500, timeout, or malformed response. Check server logs.

The four gates apply together by design (see [PRINCIPLES.md](PRINCIPLES.md), Principle 5 — *Permissions Stay Layered*). The runtime error tells you which gate fired so you can act at the right layer.

### Permission posture for the public alpha

Read access is enabled by default for every module. Write and delete are enabled by default for most modules — including filesystem and cron. The alpha trusts early operators to know what they're doing; visibility through the [boundary activity log](#boundary-activity-log) is the safety surface, not closed defaults.

Operators who want a stricter baseline disable per-module Write or Delete via the **Settings → Abilities for AI** UI. The choices are explicit: every module's permission state is shown, and changes are recorded in `kl_boundary` for audit. Per-module saves are isolated — toggling one module no longer affects sibling modules' state ([#153](https://github.com/Wicked-Evolutions/abilities-for-ai/issues/153) shipped in v1.9.3).

This posture pairs with [Abilities MCP Adapter](https://github.com/Wicked-Evolutions/abilities-mcp-adapter)'s response redaction filter, which sits between ability output and the MCP wire — so even with permissive Write defaults, sensitive fields (passwords, API keys, contact PII) are redacted by default before responses leave the site.

## Documentation

- [Getting Started](docs/getting-started.md) — installation + AI agent onboarding
- [Abilities API Architecture](docs/abilities-api-architecture.md) — how registration and execution work
- [Glossary](docs/glossary.md) — key terms
- [Register an Ability](docs/guides/register-an-ability.md) — build your own
- [Register a Category](docs/guides/register-a-category.md) — group abilities

## Links

- [Product page](https://community.wickedevolutions.com/item/abilities-for-ai/)
- [Abilities MCP Adapter](https://community.wickedevolutions.com/item/abilities-mcp-adapter/) — WordPress-side MCP protocol handler + OAuth resource server
- [Abilities MCP](https://github.com/Wicked-Evolutions/abilities-mcp) — MCP bridge for AI clients (`npm install -g @wickedevolutions/abilities-mcp`)
- [Abilities for Fluent Plugins](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins) — our continuously-enhanced first-party translator for the Fluent suite (FluentCRM, FluentCommunity, FluentForms, FluentBooking, FluentSupport, FluentBoards, FluentSMTP, FluentAuth, FluentSnippets, FluentMessaging, FluentCart, FluentAffiliate)

## Evolving Knowledge

We continuously add knowledge docs, skills, and agent patterns to [knowledge.wickedevolutions.com](https://knowledge.wickedevolutions.com). We don't know every use case that humans and AI agents will discover together — the ecosystem grows from real usage.

## Version History

See [CHANGELOG.md](CHANGELOG.md) for the complete version history.

## License

GPL-2.0-or-later

Copyright 2026 Wicked Evolutions
