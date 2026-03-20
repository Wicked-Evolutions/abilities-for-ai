# Abilities for AI

Native WordPress abilities for AI agents. Powers AI control through the official [WordPress Abilities API](https://developer.wordpress.org/reference/functions/wp_register_ability/).

| | |
|---|---|
| **Requires** | WordPress 6.9+, PHP 8.0+ |
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

1. **[Abilities MCP Adapter](https://community.wickedevolutions.com/item/abilities-mcp-adapter/)** — exposes abilities as MCP tools via REST API
2. **[Abilities MCP](https://github.com/Wicked-Evolutions/abilities-mcp)** bridge — connects your AI client to WordPress (`npx @wickedevolutions/abilities-mcp`)

See [docs/getting-started.md](docs/getting-started.md) for the full setup guide.

## Free and Pro

**Free** — full read access across all modules, plus a controlled write round-trip (create + delete) so AI agents can prove competence before a site owner commits to Pro.

**Pro** — unlocks write and delete abilities: update content, modify blocks, bulk search-replace, manage themes, configure settings, assign taxonomies, reorder menus. The operations that turn an AI assistant into an AI operator.

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

## Security Model

Every ability enforces WordPress capabilities at execution time. The WordPress user role assigned to your AI agent determines access:

| Role | Access |
|------|--------|
| **Administrator** | All modules |
| **Editor** | Content, Blocks, Taxonomies, Patterns, Meta, Media |

Per-module Read/Write/Delete toggles provide additional control. Pro abilities require both the permission toggle AND a valid license.

## Documentation

- [Getting Started](docs/getting-started.md) — installation + AI agent onboarding
- [Abilities API Architecture](docs/abilities-api-architecture.md) — how registration and execution work
- [Glossary](docs/glossary.md) — key terms
- [Register an Ability](docs/guides/register-an-ability.md) — build your own
- [Register a Category](docs/guides/register-a-category.md) — group abilities

## Links

- [Product page](https://community.wickedevolutions.com/item/abilities-for-ai/)
- [Abilities MCP Adapter](https://community.wickedevolutions.com/item/abilities-mcp-adapter/) — WordPress-side MCP protocol handler
- [Abilities MCP](https://github.com/Wicked-Evolutions/abilities-mcp) — MCP bridge for AI clients (`npx @wickedevolutions/abilities-mcp`)
- [Abilities for Fluent Plugins](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins) — FluentCRM, Community, Cart, Support, Boards, Booking, and more

## Evolving Knowledge

We continuously add knowledge docs, skills, and agent patterns to [knowledge.wickedevolutions.com](https://knowledge.wickedevolutions.com). We don't know every use case that humans and AI agents will discover together — the ecosystem grows from real usage.

## Version History

See [CHANGELOG.md](CHANGELOG.md) for the complete version history.

## Disclaimer

Humans make mistakes — as we know from the present day and history. Humans trained AI. AI acts accordingly. AI predicts probability based on the context window it holds. It is trained to sound certain, as if everything is truth, and to "fix" everything so the human becomes satisfied.

Learn how to communicate with AI. You are fully responsible for using AI in your life, business, and projects. Using these products is your personal responsibility to learn and own.

## License

GPL-2.0-or-later

Copyright 2026 Wicked Evolutions
