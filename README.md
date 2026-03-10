# Abilities Suite for WordPress

111 native WordPress abilities across 18 modules — content, blocks, meta, settings, cron, themes, patterns, site health, REST discovery, menus, filesystem, and more. Powers AI control through the official Abilities API.

**Free tier:** 67 read abilities — browse content, inspect settings, discover capabilities.
**Pro tier:** 44 write abilities — create, update, delete, and manage your WordPress site.

See [CHANGELOG.md](CHANGELOG.md) for version history.

## Requirements

- WordPress 6.9+ (Abilities API in core)
- PHP 7.4+
- [MCP Adapter for WordPress](https://github.com/Influencentricity/mcp-adapter-for-wordpress) (for MCP integration)

## 111 Abilities

### Content Management (11)

| Ability | Type | Description |
|---------|------|-------------|
| `content/list` | read | List posts, pages, or custom post types with filtering |
| `content/get` | read | Get a specific post by ID |
| `content/get-snapshot` | read | Get content snapshot with metadata |
| `content/create` | write | Create new content |
| `content/update` | write | Update existing content |
| `content/delete` | write | Delete content |
| `content/discover-types` | read | Discover all available post types |
| `content/find-by-url` | read | Find content by URL |
| `content/get-by-slug` | read | Get content by slug |
| `content/change-type` | write | Convert post between types |
| `content/search-replace` | write | Bulk find/replace in post content |

### Taxonomy Management (8)

| Ability | Type | Description |
|---------|------|-------------|
| `taxonomies/discover` | read | List all available taxonomies |
| `taxonomies/list-terms` | read | List terms in a taxonomy |
| `taxonomies/get-term` | read | Get a specific term |
| `taxonomies/create-term` | write | Create new terms |
| `taxonomies/update-term` | write | Update existing terms |
| `taxonomies/delete-term` | write | Delete terms |
| `taxonomies/assign-to-content` | write | Assign terms to posts |
| `taxonomies/get-content-terms` | read | Get all terms for a post |

### Plugin Management (6)

| Ability | Type | Description |
|---------|------|-------------|
| `plugins/list` | read | List all installed plugins |
| `plugins/get` | read | Get detailed plugin information |
| `plugins/activate` | write | Activate plugins |
| `plugins/deactivate` | write | Deactivate plugins |
| `plugins/install` | write | Install from WordPress.org |
| `plugins/search-repository` | read | Search WordPress.org plugins |

### Media Library (5)

| Ability | Type | Description |
|---------|------|-------------|
| `media/list` | read | List media library items |
| `media/create` | write | Upload media from URL |
| `media/upload` | write | Upload media from base64 data |
| `media/update` | write | Update media metadata |
| `media/delete` | write | Delete media items |

### User Management (5)

| Ability | Type | Description |
|---------|------|-------------|
| `users/list` | read | List WordPress users |
| `users/get` | read | Get user details |
| `users/create` | write | Create new users |
| `users/update` | write | Update user information |
| `users/delete` | write | Delete users |

### Comment Management (5)

| Ability | Type | Description |
|---------|------|-------------|
| `comments/list` | read | List comments with filtering |
| `comments/get` | read | Get specific comment |
| `comments/create` | write | Create new comments |
| `comments/update` | write | Update comments |
| `comments/delete` | write | Delete comments |

### Menu Management (12)

| Ability | Type | Description |
|---------|------|-------------|
| `menus/list-menus` | read | List all navigation menus |
| `menus/get-menu` | read | Get menu with hierarchical items |
| `menus/create-menu` | write | Create a new menu |
| `menus/delete-menu` | write | Delete a menu |
| `menus/list-menu-items` | read | List items in a menu |
| `menus/add-menu-item` | write | Add item to a menu |
| `menus/update-menu-item` | write | Update a menu item |
| `menus/delete-menu-item` | write | Delete a menu item |
| `menus/reorder-menu-items` | write | Reorder menu items |
| `menus/list-locations` | read | List registered menu locations |
| `menus/assign-location` | write | Assign a menu to a location |
| `menus/unassign-location` | write | Unassign a menu from a location |

### Block Editor (8) — *New in v3.0*

| Ability | Type | Description |
|---------|------|-------------|
| `blocks/parse` | read | Parse post content into structured block array |
| `blocks/serialize` | read | Convert block array back to HTML |
| `blocks/list-types` | read | List all registered block types |
| `blocks/get-type` | read | Get single block type details |
| `blocks/find-in-post` | read | Find blocks by name or attribute in a post |
| `blocks/insert` | write | Insert blocks at position in post content |
| `blocks/replace` | write | Replace block at index in post content |
| `blocks/remove` | write | Remove block at index from post content |

### Block Patterns (5) — *New in v3.0*

| Ability | Type | Description |
|---------|------|-------------|
| `patterns/list` | read | List all registered block patterns |
| `patterns/get` | read | Get a specific block pattern |
| `patterns/list-categories` | read | List pattern categories |
| `patterns/register` | write | Register a new block pattern |
| `patterns/unregister` | write | Unregister a block pattern |

### Meta Fields (11) — *New in v3.0*

| Ability | Type | Description |
|---------|------|-------------|
| `meta/list-post-meta` | read | List all meta for a post |
| `meta/get-post-meta` | read | Get specific post meta value |
| `meta/update-post-meta` | write | Update post meta value |
| `meta/delete-post-meta` | write | Delete post meta key |
| `meta/list-term-meta` | read | List all meta for a term |
| `meta/get-term-meta` | read | Get specific term meta value |
| `meta/update-term-meta` | write | Update term meta value |
| `meta/list-user-meta` | read | List all meta for a user |
| `meta/get-user-meta` | read | Get specific user meta value |
| `meta/update-user-meta` | write | Update user meta value |
| `meta/list-registered` | read | List all registered meta keys |

### Settings (5) — *New in v3.0*

| Ability | Type | Description |
|---------|------|-------------|
| `settings/list` | read | List WordPress settings (allowlisted) |
| `settings/get` | read | Get a specific setting value |
| `settings/get-group` | read | Get multiple settings at once |
| `settings/update` | write | Update a setting value |
| `settings/get-permalink-structure` | read | Get permalink structure |

### Site Health (4) — *New in v3.0*

| Ability | Type | Description |
|---------|------|-------------|
| `site-health/status` | read | Get site health status summary |
| `site-health/list-tests` | read | List available health tests |
| `site-health/run-test` | read | Run a specific health test |
| `site-health/info` | read | Get detailed site health info |

### Cache & Transients (7) — *New in v3.0, expanded in v3.5*

| Ability | Type | Description |
|---------|------|-------------|
| `cache/list-transients` | read | List stored transients |
| `cache/get-transient` | read | Get a transient value |
| `cache/set-transient` | write | Set a transient with expiration |
| `cache/flush-page-cache` | write | Purge full-page cache (LiteSpeed, WP Super Cache, W3TC, WPFC) |
| `cache/delete-transient` | write | Delete a transient |
| `cache/flush` | write | Flush the object cache |
| `cache/object-cache-status` | read | Get object cache status |

### Cron (3) — *New in v3.0*

| Ability | Type | Description |
|---------|------|-------------|
| `cron/list-events` | read | List all scheduled cron events |
| `cron/list-schedules` | read | List cron schedule intervals |
| `cron/get-event` | read | Get details of a specific cron event |

### Themes (5) — *New in v3.0*

| Ability | Type | Description |
|---------|------|-------------|
| `themes/list` | read | List installed themes |
| `themes/get-active` | read | Get active theme details |
| `themes/list-mods` | read | List theme modifications |
| `themes/get-mod` | read | Get a specific theme mod value |
| `themes/get-theme-json` | read | Get resolved theme.json data |

### REST Discovery (4) — *New in v3.0*

| Ability | Type | Description |
|---------|------|-------------|
| `rest/list-namespaces` | read | List all REST API namespaces |
| `rest/list-routes` | read | List routes in a namespace |
| `rest/get-route-schema` | read | Get schema for a specific route |
| `rest/get-index` | read | Get the full REST API index |

### Rewrite Rules (3) — *New in v3.0*

| Ability | Type | Description |
|---------|------|-------------|
| `rewrite/get-structure` | read | Get current permalink structure |
| `rewrite/list-rules` | read | List all rewrite rules |
| `rewrite/flush` | write | Flush rewrite rules |

### Filesystem (4) — *New in v3.6.0*

| Ability | Type | Description |
|---------|------|-------------|
| `filesystem/list-directory` | read | List files and folders in a directory within the WordPress installation |
| `filesystem/read-file` | read | Read the content of a file within the WordPress installation (1MB limit) |
| `filesystem/write-file` | write | Write or append content to a file (extension whitelist: css, js, json, md, txt, html) |
| `theme/update-asset` | write | Write a file to the active theme's assets/ directory |

## Filesystem Security

The filesystem module (v3.6.0) uses native PHP filesystem functions with a layered security model:

- **ABSPATH containment** — all paths validated with `realpath()` to prevent traversal
- **Extension whitelist** — only `.css`, `.js`, `.json`, `.md`, `.txt`, `.html` can be written; `.php` is blocked
- **Write permissions default OFF** — admin must explicitly enable filesystem writes
- **Dual gate system** — both permission toggle AND Pro license must pass for write operations

## Free/Pro Tier System

All 112 abilities are registered and visible to AI agents regardless of license. Pro abilities return a clear 403 response at execution time without a valid license, creating natural discovery of available capabilities.

- **Permission toggle** — per-module write ON/OFF control in admin dashboard
- **License gate** — Pro abilities require a valid license key (Phase 1: any non-empty key activates)
- Both gates must pass — neither alone is sufficient

## Installation

1. Upload to `wp-content/plugins/abilities-suite-for-wordpress/`
2. Activate the plugin
3. All abilities are auto-discovered by the MCP Adapter

No Composer dependencies required.

## Admin Dashboard

The plugin includes a built-in admin dashboard at **Settings → Abilities Suite** with:
- Ability browser showing all registered abilities
- Category breakdown with counts
- Test interface for individual abilities
- System diagnostics

## Multisite Support

Network activation supported. Plugin management is multisite-aware.

## Security

All abilities include WordPress capability checks:
- `edit_posts` — content, taxonomy, block, pattern, and meta operations
- `activate_plugins` — plugin management
- `upload_files` — media operations
- `create_users` — user management
- `moderate_comments` — comment operations
- `edit_theme_options` — menu operations
- `manage_options` — settings, cache, cron, themes, site health, REST, rewrite, filesystem operations

## Version

**Current:** 3.6.0

## Author

[Influencentricity](https://influencentricity.com)

## License

GPL-2.0-or-later
