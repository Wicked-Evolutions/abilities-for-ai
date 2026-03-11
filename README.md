# Abilities Suite for WordPress

138 native WordPress abilities across 18 modules. Powers AI control through the official Abilities API.

| | |
|---|---|
| **Total abilities** | 138 |
| **Free tier** | 77 |
| **Pro tier** | 61 |
| **Modules** | 18 |

## Free Tier — The Round-Trip

The free tier gives AI full read access to everything (71 abilities) plus a controlled write round-trip: create something, inspect it, delete it. This lets AI agents prove competence before a site owner commits to Pro.

- **Read everything** (71) — browse content, inspect settings, discover capabilities, parse blocks, list users, check site health
- **Test write** (4) — `content/create`, `taxonomies/create-term`, `plugins/install`, `media/create`
- **Delete the test** (6) — `content/delete`, `blocks/remove`, `taxonomies/delete-term`, `menus/delete-menu`, `menus/delete-menu-item`, `media/delete`

**77 free abilities total.** Enough to explore, prototype, and demonstrate value.

## Pro Tier — The Juice

Pro unlocks the abilities that build on what exists: update content, modify blocks, bulk search-replace, manage themes, configure settings, assign taxonomies, reorder menus. The operations that turn an AI assistant into an AI operator.

**61 pro abilities.** Everything that modifies, updates, or manages production state.

## Pro Gate

All 138 abilities are registered and visible to AI regardless of license. Pro abilities return a clear 403 with an upgrade URL at execution time. The AI discovers the gate naturally — no hidden capabilities, no surprise walls.

License validation uses FluentCart API with 24-hour cache and 7-day grace period for renewal gaps.

## 18 Modules

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

### Suite (1)

| Ability | Op | Tier |
|---------|-----|------|
| `suite/get-status` | Read | Free |

## Requirements

- WordPress 6.9+ (Abilities API in core)
- PHP 7.4+
- [MCP Adapter for WordPress](https://github.com/Influencentricity/mcp-adapter-for-wordpress) (for MCP integration)

WordPress 7.0 (April 2026) ships the JS client for the Abilities API, completing the server-client loop.

## Installation

1. Upload `abilities-suite-for-wordpress/` to `wp-content/plugins/`
2. Activate the plugin
3. All 138 abilities auto-register through the Abilities API
4. Connect an MCP-compatible AI client via [WP Abilities MCP](https://github.com/Influencentricity/wp-abilities-mcp)

No Composer dependencies.

## Admin Dashboard

**Settings > Abilities Suite** provides a unified dashboard with two tabs:

**Abilities Explorer** — Browse all registered abilities with inline Read/Write/Delete toggles per module. Filter by module, operation type, or tier. See exactly what AI can and cannot do on your site.

**License** — Enter and validate your Pro license key. Network-wide license available for multisite ($199 LTD).

## Security Model

### Capability Checks

Every ability enforces WordPress capabilities at execution time:

- `edit_posts` — content, taxonomy, block, pattern, meta operations
- `activate_plugins` — plugin management
- `upload_files` — media operations
- `create_users` — user management
- `moderate_comments` — comment operations
- `edit_theme_options` — menu operations
- `manage_options` — settings, cache, cron, themes, site health, REST, rewrite, filesystem

### Permission Toggles

Per-module Read/Write/Delete toggles in the admin dashboard. Disabled abilities are not registered — they do not appear in the API at all. Progressive disclosure: start read-only, enable write when comfortable, enable delete when confident.

### Pro Gate

Pro abilities are always visible but return 403 at execution time without a valid license. Both the permission toggle AND the license must pass — neither alone is sufficient.

## Multisite Support

Network activation supported. Network-wide license at $199 LTD covers all subsites.

## Links

- [WP Abilities MCP](https://github.com/Influencentricity/wp-abilities-mcp) — MCP bridge for AI clients
- [MCP Adapter for WordPress](https://github.com/Influencentricity/mcp-adapter-for-wordpress) — WordPress-side MCP protocol handler
- [Product home](https://wickedevolutions.com)

## Version

**Current:** 3.10.0

## License

GPL-2.0-or-later

Copyright Influencentricity | Wicked Evolutions

## Author

[Influencentricity](https://influencentricity.com)
