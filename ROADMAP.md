# ROADMAP — Abilities Suite for WordPress

> Source of truth for product development state. Obsidian roadmap references this file.
> Part of the Wicked Evolutions Trinity AI Suite for WordPress.

**Current version:** v3.10.0
**Abilities:** 138 total (77 free / 61 pro)
**Modules:** 18

---

## Open Bugs

| Bug | Priority | Notes |
|-----|----------|-------|
| `settings/get` output schema rejects non-string values | Medium | `value` field enforces `type: string` but options can be arrays/objects/integers. One-line schema fix. |
| `content/list` pagination on multisite | Low | GitHub #9. Parameter naming fixed (`per_page`), but multisite-specific WP_Query behavior may still cause issues. Needs live verification. |

## Gaps

| Gap | Priority | Notes |
|-----|----------|-------|
| `settings/update` missing front page settings | Medium | `show_on_front`, `page_on_front` not in `$writable_settings` allowlist. Two-line fix. |
| `content/create` missing `post_name` param | Medium | Cannot set custom slugs at creation time. AI must create then update slug separately. |
| `content/create` missing taxonomy terms param | Medium | Cannot assign categories/tags at creation time. Requires separate `taxonomies/assign-to-content` call (pro-gated). |
| Application Passwords abilities | Low | No ability to create/list/revoke Application Passwords. Required for self-service AI agent onboarding. Candidates: `users/create-application-password`, `users/list-application-passwords`, `users/revoke-application-password`. |
| `plugin/upload-zip` ability | Low | Install plugin from ZIP using Plugin_Upgrader. GitHub #6. |
| `.php` write support (dev mode) | Low | Filesystem writes restricted to safe extensions. Level B extension whitelist when `DISALLOW_FILE_EDIT` is false. |
| `fluent-snippets/create-snippet` | Low | Cross-product gap. Fluent Snippets active on WE but only read abilities exist. Lives in abilities-suite-for-fluent-plugins. |
| `custom_css` post type workaround | Info | Additional CSS can be created via `content/create` with `post_type: custom_css`, `post_name: {theme_slug}`. Works but fragile. |

## Not Started

| Item | Priority | Notes |
|------|----------|-------|
| Directory restructure for .org submission | Medium | Move abilities into `/modules/free/` and `/modules/pro/` with build script for Lite ZIP. **Risk: touches every include path — high breakage potential.** |
| GitHub Releases for v3.3.0+ | Low | Currently no historical releases. ZIP artifacts should be release attachments. |
| PHPUnit test coverage | Medium | Test infrastructure exists but no tests written. |

## Recently Completed

| Item | Version | Date |
|------|---------|------|
| Free/Pro tier realignment (77/61 split) | v3.10.0 | 2026-03-11 |
| GPL-2.0 compliance (LICENSE, headers, copyright) | v3.10.0 | 2026-03-11 |
| Per-ability permission overrides | v3.9.0 | 2026-03-10 |
| Unified admin dashboard (Explorer + License) | v3.8.0 | 2026-03-10 |
| FluentCart API license validation | v3.8.1 | 2026-03-10 |
| Multisite licensing (`current_product_id()`) | v3.8.1 | 2026-03-10 |
| CRUD completeness sprint (13 new abilities) | v3.8.0 | 2026-03-10 |
| Error code standardization (14 modules) | v3.7.2 | 2026-03-09 |
| `post_date` param on content/create and content/update | v3.7.0 | 2026-03-05 |
| `DISALLOW_FILE_EDIT` check in filesystem | v3.7.0 | 2026-03-05 |
| `post_author` param on content/create and content/update | v3.8.0 | 2026-03-10 |
| Role-based capability table in README | — | 2026-03-11 |
| Known Gaps section in README | — | 2026-03-11 |

## Resolved Bugs

| Bug | Fixed in | Notes |
|-----|----------|-------|
| `content/list` `per_page` param naming | v3.8.1 | Renamed `posts_per_page` to `per_page` in schema |
| `cache/object-cache-status` `persistent_cache` not boolean | v3.8.1 | `(bool)` cast |
| `content/discover-types` return structure | v3.7.2 | Wrapped in `{post_types, total}` |
| `plugins/list` schema mismatch | v3.7.2 | `schema_collection_output` pattern |
| `media/create` undefined function | v3.7.1 | Alias added to helpers.php |
| `cache/flush-page-cache` post_id type | v3.7.1 | Nullable integer |
| Dead code `content-abilities-v2.php` | v3.7.0 | Deleted — was inflating count to 113 |

## False Alarms (verified not bugs 2026-03-11)

These were logged as bugs/gaps but verified against code to not exist:

- ~~`filesystem/write-file` not visible as MCP tool~~ — Registrar sets `show_in_rest => true` automatically. Working as designed.
- ~~`theme/update-asset` not visible as MCP tool~~ — Same. Working as designed.
- ~~`post_author` missing on content/create and content/update~~ — Both have `author` param since v3.8.0.
