# Changelog

All notable changes to WordPress Abilities Suite will be documented in this file.

## [3.0.0] - 2026-02-24

### Added
- **10 new ability modules** with 54 new abilities across 10 new categories:
  - **Block Editor** (8): parse, serialize, list-types, get-type, find-in-post, insert, replace, remove
  - **Block Patterns** (5): list, get, list-categories, register, unregister
  - **Meta Fields** (11): post/term/user meta CRUD + list-registered
  - **Settings** (5): list, get, get-group, update (allowlisted), get-permalink-structure
  - **Site Health** (4): status, list-tests, run-test, info
  - **Cache & Transients** (6): list, get, set, delete, flush, object-cache-status
  - **Cron** (3): list-events, list-schedules, get-event
  - **Themes** (5): list, get-active, list-mods, get-mod, get-theme-json
  - **REST Discovery** (4): list-namespaces, list-routes, get-route-schema, get-index
  - **Rewrite Rules** (3): get-structure, list-rules, flush
- **Shared helpers module** (`includes/helpers.php`): pagination, error utilities, menu tree builder
- **10 new ability categories** (17 total): blocks, patterns, meta, settings, site-health, cache, cron, themes, rest, rewrite

### Fixed
- **15 JSON Schema violations** across all original v2.0 module files — every `type: "array"` property now includes required `items` definition. Without this fix, Claude Code silently rejects the entire MCP tool list.
  - `content-abilities.php` (2 fixes)
  - `taxonomy-abilities.php` (3 fixes)
  - `plugin-abilities.php` (2 fixes)
  - `user-abilities.php` (1 fix)
  - `media-abilities.php` (1 fix)
  - `comment-abilities.php` (1 fix)
  - `menu-abilities.php` (1 fix)

### Changed
- Version bumped from 2.0.0 to 3.0.0
- Total abilities: 51 → 103 (doubled)
- Total categories: 7 → 17
- Plugin description updated to reflect full scope

---

## [2.0.0] - 2025-12-21

### Added
- **Menu Management** (12 abilities): Full menu CRUD, item management, location assignments
- **Content v2** abilities: Enhanced list/get with extended filtering and raw/rendered output
- 7th ability category: menus

### Changed
- Total abilities: 40 → 51
- Total categories: 6 → 7

---

## [1.0.5] - 2025-12-12

### Added
- **NEW ABILITY:** `media/upload` - Upload media directly from base64-encoded file data

### Changed
- Total abilities: 39 → 40

---

## [1.0.4] - 2025-12-12

### Fixed
- **CRITICAL:** Abilities now persist in registry after plugin activation
- Root cause: WordPress Abilities API requires categories registered before abilities

### Added
- `includes/ability-categories.php` — 6 categories on `wp_abilities_api_categories_init` hook

---

## [1.0.3] - 2025-12-11

### Added
- Initial release: 39 abilities across 6 categories
- Admin dashboard with ability browser and test interface
- Network activation support for WordPress Multisite
- RESTful API integration via MCP adapter

---

## Version History

| Version | Date | Abilities | Categories | Key Changes |
|---------|------|-----------|------------|-------------|
| 3.0.0 | 2026-02-24 | 103 | 17 | 10 new modules, JSON Schema fixes |
| 2.0.0 | 2025-12-21 | 51 | 7 | Menu management, Content v2 |
| 1.0.5 | 2025-12-12 | 40 | 6 | Base64 media upload |
| 1.0.4 | 2025-12-12 | 39 | 6 | Ability persistence fix |
| 1.0.3 | 2025-12-11 | 39 | 6 | Initial release |

---

## License

GPL-2.0-or-later
