# Abilities for AI

Native WordPress abilities for AI agents. Powers AI control through the official Abilities API.

| | |
|---|---|
| **Modules** | 21 (19 core + 4 third-party suites) |
| **Requires** | WordPress 6.9+, PHP 8.0+ |

## Free Tier — The Round-Trip

The free tier gives AI full read access to everything (65 abilities) plus knowledge docs (3), a status check (1), a controlled write round-trip (5 create + 6 delete). This lets AI agents prove competence before a site owner commits to Pro.

- **Read everything** (65) — browse content, inspect settings, discover capabilities, parse blocks, list users, check site health
- **Knowledge** (3) — `knowledge/getting-started`, `knowledge/gutenberg-blocks`, `knowledge/fluent-crm`
- **Status** (1) — `suite/get-status`
- **Test write** (5) — `content/create`, `taxonomies/create-term`, `plugins/install`, `media/create`, `users/create`
- **Delete the test** (6) — `content/delete`, `blocks/remove`, `taxonomies/delete-term`, `menus/delete-menu`, `menus/delete-menu-item`, `media/delete`

**80 free abilities total.** Enough to explore, prototype, and demonstrate value.

## Pro Tier — The Juice

Pro unlocks the abilities that build on what exists: update content, modify blocks, bulk search-replace, manage themes, configure settings, assign taxonomies, reorder menus. The operations that turn an AI assistant into an AI operator.

**48 pro abilities.** Everything that modifies, updates, or manages production state.

## Pro Gate

All 128 abilities are registered and visible to AI regardless of license. Pro abilities return a clear 403 with an upgrade URL at execution time. The AI discovers the gate naturally — no hidden capabilities, no surprise walls.

License validation uses FluentCart API with 24-hour cache and 7-day grace period for renewal gaps.

## 19 Modules

### Content (11)

| Ability | Op | Tier |
|---------|-----|------|
| `content/list` | Read | Free |
| `content/get` | Read | Free |
| `content/get-snapshot` | Read | Free |
| `content/discover-types` | Read | Free |
| `content/find-by-url` | Read | Free |
| `content/get-by-slug` | Read | Free |
| `content/create` | Write | Free |
| `content/update` | Write | Pro |
| `content/change-type` | Write | Pro |
| `content/search-replace` | Write | Pro |
| `content/delete` | Delete | Free |

### Blocks (8)

| Ability | Op | Tier |
|---------|-----|------|
| `blocks/parse` | Read | Free |
| `blocks/serialize` | Read | Free |
| `blocks/list-types` | Read | Free |
| `blocks/get-type` | Read | Free |
| `blocks/find-in-post` | Read | Free |
| `blocks/insert` | Write | Pro |
| `blocks/replace` | Write | Pro |
| `blocks/remove` | Delete | Free |

### Taxonomies (8)

| Ability | Op | Tier |
|---------|-----|------|
| `taxonomies/discover` | Read | Free |
| `taxonomies/list-terms` | Read | Free |
| `taxonomies/get-term` | Read | Free |
| `taxonomies/get-content-terms` | Read | Free |
| `taxonomies/create-term` | Write | Free |
| `taxonomies/update-term` | Write | Pro |
| `taxonomies/assign-to-content` | Write | Pro |
| `taxonomies/delete-term` | Delete | Free |

### Menus (12)

| Ability | Op | Tier |
|---------|-----|------|
| `menus/list-menus` | Read | Free |
| `menus/get-menu` | Read | Free |
| `menus/list-menu-items` | Read | Free |
| `menus/list-locations` | Read | Free |
| `menus/create-menu` | Write | Pro |
| `menus/add-menu-item` | Write | Pro |
| `menus/update-menu-item` | Write | Pro |
| `menus/reorder-menu-items` | Write | Pro |
| `menus/assign-location` | Write | Pro |
| `menus/unassign-location` | Write | Pro |
| `menus/delete-menu` | Delete | Free |
| `menus/delete-menu-item` | Delete | Free |

### Meta (13)

| Ability | Op | Tier |
|---------|-----|------|
| `meta/list-post-meta` | Read | Free |
| `meta/get-post-meta` | Read | Free |
| `meta/list-term-meta` | Read | Free |
| `meta/get-term-meta` | Read | Free |
| `meta/list-user-meta` | Read | Free |
| `meta/get-user-meta` | Read | Free |
| `meta/list-registered` | Read | Free |
| `meta/update-post-meta` | Write | Pro |
| `meta/update-term-meta` | Write | Pro |
| `meta/update-user-meta` | Write | Pro |
| `meta/delete-post-meta` | Delete | Pro |
| `meta/delete-term-meta` | Delete | Pro |
| `meta/delete-user-meta` | Delete | Pro |

### Cache (7)

| Ability | Op | Tier |
|---------|-----|------|
| `cache/list-transients` | Read | Free |
| `cache/get-transient` | Read | Free |
| `cache/object-cache-status` | Read | Free |
| `cache/set-transient` | Write | Pro |
| `cache/flush-page-cache` | Write | Pro |
| `cache/delete-transient` | Delete | Pro |
| `cache/flush` | Delete | Pro |

### Plugins (7)

| Ability | Op | Tier |
|---------|-----|------|
| `plugins/list` | Read | Free |
| `plugins/get` | Read | Free |
| `plugins/search-repository` | Read | Free |
| `plugins/install` | Write | Free |
| `plugins/activate` | Write | Pro |
| `plugins/deactivate` | Write | Pro |
| `plugins/delete` | Delete | Pro |

### Media (6)

| Ability | Op | Tier |
|---------|-----|------|
| `media/list` | Read | Free |
| `media/get` | Read | Free |
| `media/create` | Write | Free |
| `media/upload` | Write | Pro |
| `media/update` | Write | Pro |
| `media/delete` | Delete | Free |

### Users (5)

| Ability | Op | Tier |
|---------|-----|------|
| `users/list` | Read | Free |
| `users/get` | Read | Free |
| `users/create` | Write | Free |
| `users/update` | Write | Pro |
| `users/delete` | Delete | Pro |

### Comments (5)

| Ability | Op | Tier |
|---------|-----|------|
| `comments/list` | Read | Free |
| `comments/get` | Read | Free |
| `comments/create` | Write | Pro |
| `comments/update` | Write | Pro |
| `comments/delete` | Delete | Pro |

### Patterns (5)

| Ability | Op | Tier |
|---------|-----|------|
| `patterns/list` | Read | Free |
| `patterns/get` | Read | Free |
| `patterns/list-categories` | Read | Free |
| `patterns/register` | Write | Pro |
| `patterns/unregister` | Delete | Pro |

### Settings (6)

| Ability | Op | Tier |
|---------|-----|------|
| `settings/list` | Read | Free |
| `settings/get` | Read | Free |
| `settings/get-group` | Read | Free |
| `settings/get-permalink-structure` | Read | Free |
| `settings/update` | Write | Pro |
| `settings/delete` | Delete | Pro |

### Themes (10)

| Ability | Op | Tier |
|---------|-----|------|
| `themes/list` | Read | Free |
| `themes/get-active` | Read | Free |
| `themes/list-mods` | Read | Free |
| `themes/get-mod` | Read | Free |
| `themes/get-theme-json` | Read | Free |
| `themes/activate` | Write | Pro |
| `themes/install` | Write | Pro |
| `themes/set-mod` | Write | Pro |
| `themes/delete` | Delete | Pro |
| `themes/delete-mod` | Delete | Pro |

### Site Health (4)

| Ability | Op | Tier |
|---------|-----|------|
| `site-health/status` | Read | Free |
| `site-health/list-tests` | Read | Free |
| `site-health/run-test` | Read | Free |
| `site-health/info` | Read | Free |

### Filesystem (5)

| Ability | Op | Tier |
|---------|-----|------|
| `filesystem/list-directory` | Read | Free |
| `filesystem/read-file` | Read | Free |
| `filesystem/write-file` | Write | Pro |
| `theme/update-asset` | Write | Pro |
| `filesystem/delete-file` | Delete | Pro |

### REST Discovery (4)

| Ability | Op | Tier |
|---------|-----|------|
| `rest/list-namespaces` | Read | Free |
| `rest/list-routes` | Read | Free |
| `rest/get-route-schema` | Read | Free |
| `rest/get-index` | Read | Free |

### Cron (5)

| Ability | Op | Tier |
|---------|-----|------|
| `cron/list-events` | Read | Free |
| `cron/list-schedules` | Read | Free |
| `cron/get-event` | Read | Free |
| `cron/create-event` | Write | Pro |
| `cron/delete-event` | Delete | Pro |

### Rewrite (3)

| Ability | Op | Tier |
|---------|-----|------|
| `rewrite/get-structure` | Read | Free |
| `rewrite/list-rules` | Read | Free |
| `rewrite/flush` | Write | Pro |

### Knowledge (3)

| Ability | Op | Tier |
|---------|-----|------|
| `knowledge/getting-started` | Read | Free |
| `knowledge/gutenberg-blocks` | Read | Free |
| `knowledge/fluent-crm` | Read | Free |

### Status (1)

| Ability | Op | Tier |
|---------|-----|------|
| `suite/get-status` | Read | Free |

## Requirements

- WordPress 6.9+ (Abilities API in core)
- PHP 8.0+
- [Abilities MCP Adapter](https://github.com/Wicked-Evolutions/abilities-mcp-adapter) (for MCP integration)

WordPress 7.0 (April 2026) ships the JS client for the Abilities API, completing the server-client loop.

## Installation

1. Upload `abilities-for-ai/` to `wp-content/plugins/`
2. Activate the plugin
3. All 128 abilities auto-register through the Abilities API
4. Connect an MCP-compatible AI client via [Abilities MCP](https://github.com/Wicked-Evolutions/abilities-mcp)

No Composer dependencies.

## Admin Dashboard

**Settings > Abilities for AI** provides a unified dashboard with two tabs:

**Abilities Explorer** — Browse all registered abilities with inline Read/Write/Delete toggles per module. Filter by module, operation type, or tier. See exactly what AI can and cannot do on your site.

**License** — Enter and validate your Pro license key. Network-wide license available for multisite ($199 LTD).

## Security Model

### Capability Checks

Every ability enforces WordPress capabilities at execution time. The WordPress user role assigned to your AI agent determines which modules are accessible:

| Module | Capability | Administrator | Editor |
|--------|-----------|:---:|:---:|
| Content | `edit_posts` | Yes | Yes |
| Blocks | `edit_posts` | Yes | Yes |
| Taxonomies | `edit_posts` | Yes | Yes |
| Patterns | `edit_posts` | Yes | Yes |
| Meta | `edit_posts` | Yes | Yes |
| Media | `upload_files` | Yes | Yes |
| Comments | `moderate_comments` | Yes | — |
| Menus | `edit_theme_options` | Yes | — |
| Themes | `switch_themes` | Yes | — |
| Users | `list_users` | Yes | — |
| Plugins | `activate_plugins` | Yes | — |
| Settings | `manage_options` | Yes | — |
| Cache | `manage_options` | Yes | — |
| Cron | `manage_options` | Yes | — |
| Filesystem | `manage_options` | Yes | — |
| REST Discovery | `manage_options` | Yes | — |
| Rewrite | `manage_options` | Yes | — |
| Site Health | `view_site_health_checks` | Yes | — |

**Editor** gives access to 6 content-focused modules — ideal for publishing workflows where AI should write content but not manage infrastructure. **Administrator** unlocks all 19 modules.

### Permission Toggles

Per-module Read/Write/Delete toggles in the admin dashboard. Disabled abilities are not registered — they do not appear in the API at all. Progressive disclosure: start read-only, enable write when comfortable, enable delete when confident.

### Pro Gate

Pro abilities are always visible but return 403 at execution time without a valid license. Both the permission toggle AND the license must pass — neither alone is sufficient.

## Multisite Support

Network activation supported. Network-wide license at $199 LTD covers all subsites.

## Known Gaps

| Gap | Description | Workaround |
|-----|-------------|------------|
| Application Passwords | No ability to create, list, or revoke WordPress Application Passwords (`wp-json/wp/v2/users/{id}/application-passwords`). Required for self-service AI agent onboarding. | WP-CLI: `wp user application-password create <user> <name>` or WordPress Admin UI |

Candidate abilities: `users/create-application-password`, `users/list-application-passwords`, `users/revoke-application-password`.

## Links

- [Abilities MCP](https://github.com/Wicked-Evolutions/abilities-mcp) — MCP bridge for AI clients
- [Abilities MCP Adapter](https://github.com/Wicked-Evolutions/abilities-mcp-adapter) — WordPress-side MCP protocol handler
- [Product home](https://wickedevolutions.com)

## Version

**Current:** 1.3.0

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.3.0 | 2026-03-16 | `theme/enqueue-asset`, `theme/dequeue-asset`, `theme/list-enqueued-assets` — load CSS/JS without PHP file writes |
| 1.2.0 | 2026-03-15 | Serialization safety (`abilities_for_ai_safe_value()`), security hardening (filesystem denylist for wp-config.php etc.), suite-wide stdClass→array fix for Presto Player and Spectra |
| 1.1.1 | 2026-03-15 | Spectra `get-theme-classes` output schema alignment |
| 1.1.0 | 2026-03-14 | SureCart suite complete (91 abilities across 14 domains), permission defaults fix, category double-registration fix |
| 1.0.5 | 2026-03-13 | License manager, plugin updater, network admin UI, boot self-check, pre-download fix |
| 1.0.4 | 2026-03-12 | Batch abilities (`content/batch-update`), site pulse (`content/get-site-map`, `content/list-structure`, `content/get-text`), design snapshot |
| 1.0.3 | 2026-03-11 | Knowledge Layer v0.0.2 — database tables, models, 15 CRUD abilities, seed system, observation system |
| 1.0.2 | 2026-03-10 | Revisions + multisite modules, app password abilities, updater fix for multisite |
| 1.0.1 | 2026-03-09 | Filesystem abilities (4), knowledge auto-loading, `get-content-terms` object cast, `run-test` fix |
| 1.0.0 | 2026-03-08 | Rename from WP Abilities Suite → Abilities for AI. 19 core modules. |

### Third-Party Suites (added v1.0.5–v1.1.0)

| Suite | Abilities | Added |
|-------|-----------|-------|
| SureCart | 91 | v1.1.0 |
| Astra | 36 | v1.0.5 |
| Presto Player | 33 | v1.0.5 |
| Spectra | 25 | v1.0.5 |

## License

GPL-2.0-or-later

Copyright Influencentricity | Wicked Evolutions

## Author

[Influencentricity](https://influencentricity.com)
