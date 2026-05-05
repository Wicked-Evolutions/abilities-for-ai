# Changelog

All notable changes to Abilities for AI will be documented in this file.

## [Unreleased]

### Fixed
- **#145: Per-ability override sanitizer + ability counters silently dropped 7+ module prefixes.** `includes/permissions.php` carried three independently-maintained `$category_to_module` arrays — the sanitizer at lines 57-65, the counter at 117-138, and the enabled-count summary at 188-207 — all hardcoded copies of `array_keys( abilities_for_ai_permission_defaults() )`. They had drifted: sanitizer + counter were missing 7 (knowledge, diagnostic, editorial, astra, presto-player, spectra, surecart), enabled-count was missing 9 (those plus `revisions` and `multisite`). Net effect: per-ability override checkboxes for those modules' abilities silently vanished on save and execution fell back to module-level permissions. Fixed structurally with a single introspection helper `abilities_for_ai_module_prefix_map()` (in `includes/helpers.php`) that derives the prefix→module identity from `permission_defaults()`. All three call sites in `permissions.php` now use the helper — adding a new module to defaults automatically propagates to sanitizer + counter + summary, eliminating the drift bug class. Three unit tests lock the introspection contract + per-module override persistence + unknown-prefix rejection. (#145)

### Fixed
- **#144: `GET /documents?tags=...` filtered AFTER pagination, omitting matches on later pages.** `DocumentsController::get_items()` fetched a page from `Document::list_documents()` and only then ran `array_intersect` against the requested tag IDs. Two bugs chained: (1) `LIMIT/OFFSET` applied to the unfiltered set, so tagged docs on pages other than the current one were silently dropped; (2) `X-WP-Total` was overwritten with the page-local count of post-filter matches (often 0–3 vs. a true total in the dozens), breaking pagination UX in any client driving off `X-WP-Total`. Fixed by pushing the filter into `Document::list_documents()` as a correlated `EXISTS` subquery against `kl_taggables` — both the `COUNT(*)` and the `SELECT` page query now apply the same WHERE, so filtered total + filtered page agree. OR-semantics across `tag_ids` (any-of-tags match) preserved verbatim from the prior `array_intersect` behavior; the public contract is unchanged. (#144)

### Security
- **#143: `POST|PUT /documents/{id}/publish` route bypassed per-post edit checks.** The route's `permission_callback` correctly required `publish_posts`, but `publish_item()` then called `wp_update_post()` directly when the KL document had a `wp_post_id`, with no `current_user_can('edit_post', $doc->wp_post_id)` gate — so any author with `publish_posts` could overwrite a linked WP post owned by another user. The same handler also accepted a `post_author` from the request body with no cap check, allowing arbitrary author reassignment / attribution forgery. Fixed by adding two cap checks in `publish_item()`: (1) on update path, return `rest_forbidden` 403 if `! current_user_can('edit_post', $doc->wp_post_id)`; (2) on either path, return `rest_forbidden` 403 if `post_author` is set to a different user than the current one and `! current_user_can('edit_others_posts')`. Same-author `post_author` assignment (publishing your own KL doc as your own post) stays unrestricted — that's the legitimate flow Authors are supposed to use. (#143)


- **#142: `filesystem/fetch-remote` SSRF guard bypassable via redirect / DNS rebinding.** The pre-call private-IP check resolved the host once, then `wp_remote_get()` was invoked with default redirects and no DNS pinning — a public URL could pass preflight and then redirect (or DNS-rebind) into `169.254/16` (cloud metadata services), `127.0.0.0/8`, or any other internal range, with the response written to disk and read back. Fixed by extracting the existing `media/upload-from-url` DNS-pinning preflight into a shared helper in `includes/helpers.php` and **strengthening it with manual per-hop redirect validation**: `wp_abilities_safe_remote_get()` now sets `redirection => 0` + `CURLOPT_FOLLOWLOCATION = false`, follows redirects manually up to `WP_ABILITIES_SAFE_FETCH_MAX_REDIRECTS` (5) hops, and runs `wp_abilities_prepare_safe_fetch()` (re-resolution + private-IP rejection + DNS pin) on every hop. `wp_abilities_safe_download_url()` routes through the same loop. Both `filesystem/fetch-remote` and `media/upload-from-url` now share a unified, per-hop-validated SSRF boundary. Live verification on wickedevolutions confirmed redirect → 169.254 / 127.0.0.1 / IPv6 loopback all rejected; public → public redirect still works; 3-hop legitimate chain works; >5-hop loop bails with `Too many redirects`. (#142)
- **WP core's `reject_unsafe_urls` coverage gap on link-local / IPv6.** `wp_http_validate_url()` rejects 127/8, 10/8, 172.16-31/12, 192.168/16 — but not 169.254/16 (cloud metadata) or any IPv6 private range. The original C.1 plan recommended reusing the `media/upload-from-url` pattern verbatim, which relies on `reject_unsafe_urls` for redirect-target validation; live testing on wickedevolutions surfaced that this leaves 169.254/16 reachable on a redirect. The strengthened manual loop now uses `wp_abilities_is_private_ip()` (which covers 169.254 + 0.0.0.0/8 + IPv6 unique-local + IPv4-mapped-IPv6) on every hop, closing the gap on both consumer abilities. CTO-approved deviation from the original C.1 fix shape, 2026-05-05.
- **IPv6 bracketed-host preflight fix.** `wp_parse_url()` returns IPv6 hosts in bracketed form (`[::1]`, `[fc00::1]`, `[::ffff:127.0.0.1]`) and `filter_var(FILTER_VALIDATE_IP)` rejects those, so IPv6 private literals previously fell through to the unresolvable-hostname branch. Preflight now strips matching brackets before IP validation; covered by three unit tests in `tests/Unit/SafeFetchTest.php`. (#142)
- **DNS-rebind structural protection covered by unit tests.** `CURLOPT_RESOLVE` pins the resolved IP at preflight time, so a flipping DNS record between preflight and the actual TCP request cannot pivot the connection. Two unit tests model the rebind via a fake resolver and verify the closure captures the first resolution and the rebind targets are never consulted. (#142)

### Maintenance
- `composer.lock` refreshed against `composer.json`; `composer validate --strict` passes again. (Adjacent cleanup landing alongside #142.)

## [1.9.1] - 2026-05-02

Stretch-to-Stable post-alpha stabilization release. Two schema-correctness fixes that close a class of Anthropic API tool-registration rejection, plus a cross-cutting CI validator that protects every future ability from the same class of bug. Companion releases: [abilities-mcp-adapter v1.4.4](https://github.com/Wicked-Evolutions/abilities-mcp-adapter/releases/tag/v1.4.4), [abilities-mcp v1.5.1](https://github.com/Wicked-Evolutions/abilities-mcp/releases/tag/v1.5.1).

### Bug — High
- **#134: `presto-player/update-setting` `value` property used array-form `type`.** The `input_schema` for the SureCart-adjacent `update-setting` ability declared `'type' => array( 'string', 'integer', 'boolean' )` on the `value` property — accepted by raw JSON Schema draft 2020-12 but rejected by the Anthropic API's stricter tool-catalog profile. Submitting a tool catalog containing this schema returned a 400 `invalid_request_error` from `tools/list`, and the entire catalog was dropped — every WordPress ability went dark for any client backed by the Anthropic API. Fixed by switching the `value` property to `oneOf` with single-typed branches per the issue body's preferred shape; the union semantics are preserved without violating Anthropic-strict. (#134, PR #137)
- **#135: `Registrar::register()` default `input_schema` fallback emitted empty PHP array.** When an ability was registered without an explicit `input_schema` (every no-arg `read` ability uses the default), the fallback `$config['input_schema'] ?? array()` produced `{}`, which the MCP adapter wrapped into `{ "type": "object", "properties": {} }` downstream — the empty-`properties` shape Anthropic-strict rejects. Fixed structurally at `src/Core/Registrar.php` line 168: the fallback now emits `array( 'type' => 'object' )`, matching the canonical CLAUDE.md PHP rule about omitting `properties` for no-arg abilities. Single-line production change retroactively unblocks every no-arg ability across every suite without per-ability overrides; future no-arg abilities inherit the safe default by construction. New `RegistrarTest::test_no_arg_ability_default_schema_omits_properties` asserts the registered shape. (#135, PR #139)

### Test infra
- **Anthropic-strict draft 2020-12 validator shipped (cross-cutting deliverable from #134).** New `tests/Unit/InputSchemaDraft202012Test.php` walks every registered ability's `input_schema`, parses it with `opis/json-schema` against the draft 2020-12 meta-schema, then layers an explicit Anthropic-strict lint on top — the lint catches the two rules the meta-schema permits but the Anthropic API rejects: array-form `type` (#134) and empty `properties: {}` on `type: object` (#135). Discovery is via PHP source-text extraction so the gate runs in the existing Unit suite without `WP_TESTS_DIR`. The validator is permanent CI infrastructure: every future ability gets the same gate without per-ability work. Class-level docstring documents the profile by name and the raw-2020-12-vs-Anthropic distinction so a future contributor reading the test understands why the lint goes beyond the meta-schema. New `composer.json` require-dev: `opis/json-schema:^2.3`. (PR #137)

### CI
- **PHP 8.0 dropped from CI matrix; PHP 8.5 added.** PHPUnit 10's transitive dev-dependencies pinned in `composer.lock` require PHP `>=8.1`, so the 8.0 matrix entry had been failing on `main` since PHPUnit was bumped. Matrix is now `['8.1', '8.2', '8.3', '8.5']`. Plugin header `Requires PHP` and `composer.json` `require.php` aligned to `8.1`. Pre-C.1 housekeeping that unblocked Phase C's CI. (PR #138)

### Notes
- Discovered during this sprint and filed for v1.9.2: **#136** — `cache/flush-page-cache` `output_schema` declares array-form type (same bug class as #134, but in `output_schema` rather than `input_schema`; the validator currently walks `input_schema` only). **#140** — strict validator's source-walk discovery skips abilities whose registration omits `input_schema` entirely, so the Registrar runtime default (now `array( 'type' => 'object' )`) is structurally invisible to the validator; the unit test on the Registrar carries the load-bearing proof for that path. Both are out of scope for v1.9.1 per the sprint plan's scope-boundary rule.

## [1.9.0] - 2026-04-26

> v1.8.0 was an internal version captured in the BUILD CAPTURE document during the public alpha hardening sprint. Its `kl_activity` operational signal columns and annotation linter shipped via PR #127, merged together with v1.9.0's DB-1 work — there was no discrete v1.8.0 release event. The version jump from 1.7.1 to 1.9.0 reflects that consolidation.

### Added
- **#132 — `kl_boundary` sister table + writer + Activity tab.** New schema table for MCP boundary events (session lifecycle, auth denials, transport errors, rate-limit hits) at `Schema::VERSION = 0.7.0`. New `BoundaryEventLogger` class implements `McpObservabilityHandlerInterface` from `abilities-mcp-adapter` and listens on the new `mcp_adapter_boundary_event` action hook for third-party event sources. Both paths route to the same writer; writer applies a metadata-only allowlist as defense-in-depth on top of adapter-side sanitization.
- **REST routes:** `abilities-kl/v1/boundary`, `abilities-kl/v1/boundary/stats`, and `abilities-kl/v1/timeline` (UNION-paginated across `kl_activity` + `kl_boundary` by `created_at`). All gated by `manage_options`.
- **Activity admin tab/toggle** (Vue SPA): `Ability executions / Boundary events / Both` switches the existing Activity view between `kl_activity`, `kl_boundary`, and the merged timeline.
- **Daily retention cron** `abilities_kl_boundary_retention` — prunes rows older than `kl_boundary_retention_days` filter (default 90 days). Bounded by `idx_created`.

### Notes
- Graceful degradation in both directions: adapter alone (no abilities-for-ai) → adapter's `NullMcpObservabilityHandler` no-op + action hook with no listener. abilities-for-ai alone (no adapter) → `BoundaryEventLogger` never fires; action listener registered but never invoked.
- Five v0.1 events written: `boundary.session.init`, `boundary.session.terminated`, `boundary.auth.denied`, `boundary.transport.error`, `boundary.rate_limit_hit` (the last is column-reserved here; the actual rate-limiter that emits these events lives in [abilities-mcp-adapter v1.4.0+](https://github.com/Wicked-Evolutions/abilities-mcp-adapter/releases)).

## [1.7.1] - 2026-03-20

### Fixed
- **Multisite permissions self-heal:** Sites where `abilities_for_ai_permissions` option was never seeded (missed activation hook, file-copy deploy, or subsites created during edge conditions) now auto-seed defaults on first ability call. Prevents silent `delete: false` on subsites that appear fully enabled in the UI on other sites.

## [1.7.0] - 2026-03-20

### Fixed
- **#46 (FATAL):** Implemented `astra_abilities_map_input_to_meta()` helper — maps input fields to Astra post meta keys for Custom Layout create/update
- **#47:** Confirmed `astra/update-cpt-layout-defaults` is fully implemented — false positive from testing without Astra active
- **#53:** Made `asset_type` optional in `theme/update-asset` — auto-detects from filename extension
- **#52:** Fixed `presto-player/create-video` — changed `createAndGet()` to `create()` + fetch (Presto Player model API)
- **#50:** Improved permission error messages in both Registrars — now includes module name, required permission, and settings path
- **#49:** Added pagination (100/page, max 200), type guards, and normalized schedule values in `cron/list-events`
- **#70:** Added safety net in KL `Schema::maybe_migrate()` — unconditionally calls `seed_tags()` when tables exist

## [1.6.1] - 2026-03-20

### Added
- GitHub Releases auto-update fallback — users who install from GitHub get update notifications in wp-admin without a FluentCart license
- Store product page URLs (community.wickedevolutions.com)

## [1.6.0] - 2026-03-19

### Added
- `filesystem/create-directory` and `filesystem/write-binary` abilities
- Unblocked PHP extension whitelist for theme development (#73, #74, #75)

### Changed
- `content/append` refactored to clean string-only API

## [1.5.0] - 2026-03-17

### Added
- **Block API v2** — 6 nested block abilities: `blocks/get-at-path`, `blocks/find-nested`, `blocks/update-attributes`, `blocks/update-at-path`, `blocks/append-inner`
- `innerContent` normalizer for block insert/replace operations (#54)

## [1.4.0] - 2026-03-16

### Added
- `content/duplicate` ability

## [1.3.0] - 2026-03-16

### Added
- `theme/enqueue-asset`, `theme/dequeue-asset`, `theme/list-enqueued-assets` — runtime CSS/JS management without filesystem writes

## [1.2.0] - 2026-03-15

### Fixed
- Serialization safety — `abilities_for_ai_safe_value()` applied across all callbacks
- Security hardening — filesystem denylist for wp-config.php and sensitive files
- Suite-wide stdClass to array fix for Presto Player and Spectra callbacks

## [1.1.1] - 2026-03-15

### Fixed
- Spectra `get-theme-classes` output schema alignment

## [1.1.0] - 2026-03-14

### Added
- **SureCart suite** — 91 abilities across 14 domains
- **Astra suite** — 36 abilities
- **Spectra suite** — 25 abilities
- **Presto Player suite** — 33 abilities
- Suite auto-loader for third-party plugin integrations
- Permission defaults for presto-player and spectra modules (#36)

### Fixed
- SureCart category double-registration (#39)

## [1.0.5] - 2026-03-14

### Added
- License manager with FluentCart integration
- Plugin updater for auto-updates
- Network admin UI for multisite

## [1.0.4] - 2026-03-12

### Added
- `content/batch-update`, `content/get-site-map`, `content/list-structure`, `content/get-text`
- `suite/get-status` site pulse

## [1.0.3] - 2026-03-11

### Added
- Knowledge Layer v0.0.2 — database tables, models, 15 CRUD abilities, seed system

## [1.0.2] - 2026-03-10

### Added
- Revisions module, Multisite module, Application Password abilities

## [1.0.1] - 2026-03-09

### Added
- Filesystem abilities (4)

### Fixed
- `get-content-terms` object cast, `run-test` missing include

## [1.0.0] - 2026-03-11

### Changed
- **Renamed:** WP Abilities Suite → **Abilities for AI** (WordPress.org trademark compliance)
- Plugin slug: `abilities-suite-for-wordpress` → `abilities-for-ai`
- Namespace: `WickedEvolutions\AbilitiesSuite` → `WickedEvolutions\AbilitiesForAI`
- Constants: `WP_ABILITIES_SUITE_*` → `ABILITIES_FOR_AI_*`
- Options: `wp_abilities_suite_*` → `abilities_for_ai_*`
- GitHub repo: `Wicked-Evolutions/abilities-for-ai`
- Composer autoloader regenerated for new namespace
- Deployed to helenawillow.com and wickedevolutions.com with license + permission migration

### Fixed
- Stale Composer autoloader mapping (`AbilitiesSuite` → `AbilitiesForAI`) — `composer dump-autoload` required after namespace rename

---

## [3.7.2] - 2026-03-09

### Fixed
- `content/discover-types` — handler returned a plain array but output schema declared `schema_collection_output` (expected `{post_types: [], total: N}`). Wrapped return and added `array_values()` to re-index associative `get_post_types()` result.
- `plugins/list` — handler returned a plain array but schema declared paginated `schema_list_output`. Changed schema to `schema_collection_output` and wrapped return as `{plugins: [...], total: N}`.

---

## [3.7.1] - 2026-03-09

### Fixed
- `media/create` — fatal error: added missing `abilities_for_ai_is_private_ip()` alias in `helpers.php` (function existed as `wp_abilities_is_private_ip`, call-site used suite-prefixed name)
- `cache/flush-page-cache` — output schema mismatch: `post_id` declared as `integer` but returns `null` when no post ID provided; updated to `['integer', 'null']`

---

## [3.7.0] - 2026-03-05

### Added
- `post_date` parameter to `content/create` and `content/update` — accepts MySQL datetime or ISO 8601 format, enables future scheduling (closes #4)
- `DISALLOW_FILE_MODS` and `DISALLOW_FILE_EDIT` checks in `filesystem/write-file` and `theme/update-asset` execute callbacks (closes #7)

### Fixed
- Plugin description: corrected ability count 113 → 111, added filesystem to module list

### Changed
- Total abilities: 111 (net change 0 — dead code removed, count corrected)

---

## [3.6.0] - 2026-03-02

### Added
- **Filesystem abilities module** (4 abilities): list-directory, read-file, write-file, theme/update-asset
- 18th ability category: filesystem
- Native PHP filesystem functions (CageFS/CloudLinux compatible — no WP_Filesystem overhead)
- Extension whitelist security (css, js, json, md, txt, html allowed; php blocked)
- ABSPATH containment with realpath() validation and traversal rejection
- Write permissions default OFF — admin must explicitly enable
- **Free/Pro tier gate** (Phase 1): 69 free abilities (read) + 44 pro abilities (write)
- `license-manager.php` stub — any non-empty key activates Pro
- `tier-gate.php` — `abilities_for_ai_pro_gate()` closure wrapper for pro abilities
- Execution-time blocking: all 113 abilities registered and visible; pro abilities return 403 without license

### Changed
- Total abilities: 103 → 113 (69 free + 44 pro)
- Total categories: 17 → 18

---

## [3.5.1] - 2026-02-28

### Fixed
- `content/list` division by zero on multisite subsites — undefined `$per_page` → `$args['posts_per_page']`
- `cache/flush-page-cache` single-post purge crash on LiteSpeed — static call → `do_action('litespeed_purge_post', $post_id)`

---

## [3.5.0] - 2026-02-27

### Added
- `content/change-type` — convert posts between post types with taxonomy/permalink warnings
- `content/search-replace` — bulk find/replace across post content with dry_run preview
- `cache/flush-page-cache` — purge page cache with auto-detection (LiteSpeed, WP Super Cache, W3TC, WPFC)

### Changed
- Total abilities: 103 → 106

---

## [3.4.1] - 2026-02-27

### Security (second + third pass — GPT-5.2 Pro + Claude Opus 4.1)
- **Meta:** `edit_user` object-level checks on user meta; sensitive key denylist + redaction
- **Content:** type-specific capability checks; per-post `edit_post` filtering in list; `publish_posts` gate
- **Users:** `edit_user`, `promote_user`, `get_editable_roles` enforcement
- **Menus:** reorder validates item IDs against menu membership
- **Taxonomy:** per-taxonomy capability checks (manage/edit/delete/assign)
- **Comments:** object-level `edit_comment`/`delete_comment` checks
- **IPv6 SSRF:** full binary comparison for ULA, link-local, and IPv4-mapped ranges via `inet_pton`
- **SSRF TOCTOU:** DNS pinning via `CURLOPT_RESOLVE` in `media/upload-from-url`; DNS failure detection rejects unresolvable hostnames
- **media/upload:** actual decoded size verified after `base64_decode`
- **site-health/info:** expanded redaction (SMTP, API keys, OAuth, tokens)

### Fixed
- `content/list`: filtered count based on per-post cap check (not unfiltered `found_posts`)

---

## [3.3.0] - 2026-02-26

### Security (GPT-5.2 Pro review via Oracle CLI — 10 findings, all fixed)
- **Object-level authorization** on all single-post abilities (content, blocks, meta, taxonomy, media) — `require_editable_post()` helper validates post exists + user can edit it
- **SSRF protection** on `media/create` — scheme whitelist (http/https only), private IP blocking (127/10/172.16/192.168/169.254/::1), 10 MB file size limit
- **Base64 upload size limit** on `media/upload` — 10 MB estimated decode size cap
- **Media object-level auth** — `media/update` checks `edit_post`, `media/delete` checks `delete_post`
- **Destructive ops default OFF** — `blocks.delete` and `cache.delete` now default to `false`
- **Menu read permissions** — 4 read abilities changed from `edit_posts` to `edit_theme_options`
- **Sensitive data redaction** — site-health secrets redacted, cron event args stripped, transient values capped at 1 MB

### Fixed
- **blocks/parse index misalignment** — returns `original_index` field so replace/remove target the correct raw block
- **plugins/list mustuse filter** — no longer includes normal plugins when filtering for mustuse/dropins
- **Missing ABSPATH guard** in `taxonomy-abilities.php`
- **Multisite activation** — hook now accepts `$network_wide`, iterates all sites for permission defaults

### Changed
- **MCP metadata normalized** — 54 abilities across 10 v3 modules now include `show_in_rest` and `mcp.public` metadata (were silently missing)
- Plugin renamed from "Abilities for AI" to "Abilities for AI"
- Main file renamed from `wordpress-abilities-suite.php` to `abilities-for-ai.php`
- Schema audit script path updated for new plugin directory name

---

## [3.2.0] - 2026-02-25

### Added
- `content/get-snapshot` — single-call full post data (fields, all meta, taxonomy terms, featured image URL, author details) with `include`/`exclude` filtering

### Fixed
- Empty property schemas: removed `(object) array()` and `new \stdClass()` that caused MCP tool validation failures (39 tools were silently dropped)
- `menu-abilities.php`: empty properties schema fixed

---

## [3.1.0] - 2026-02-24

### Added
- **Permission toggles** — per-module enable/disable via `wp_options` (`includes/permissions.php`)
- **Admin dashboard** (`admin/dashboard.php`) — toggle UI with module descriptions and ability counts
- Dashboard CSS (`admin/css/dashboard.css`)
- `readme.txt` — WordPress plugin directory metadata and changelog

### Changed
- All 17 module files updated with centralized permission gate checks via `helpers.php`
- `helpers.php` updated with permission check functions

---

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
| 1.0.0 | 2026-03-11 | 138 | 18 | Renamed to Abilities for AI, WordPress.org trademark compliance |
| 3.10.0* | 2026-03-11 | 138 | 18 | Free/Pro tier realignment (77 free / 61 pro), GPL headers |
| 3.9.0* | 2026-03-10 | 138 | 18 | Per-ability permission overrides |
| 3.8.0* | 2026-03-10 | 138 | 18 | CRUD completeness sprint, unified dashboard |
| 3.7.2* | 2026-03-09 | 111 | 18 | Output schema fixes (discover-types, plugins/list) |
| 3.7.1* | 2026-03-09 | 111 | 18 | media/create helper alias, cache schema fix |
| 3.7.0* | 2026-03-05 | 111 | 18 | post_date scheduling, DISALLOW_FILE_EDIT checks |
| 3.6.0* | 2026-03-02 | 111 | 18 | Filesystem module (4 abilities), Free/Pro tier gate |
| 3.5.1* | 2026-02-28 | 106 | 17 | Division by zero fix, LiteSpeed purge fix |
| 3.5.0* | 2026-02-27 | 106 | 17 | content/change-type, content/search-replace, cache/flush-page-cache |
| 3.4.1* | 2026-02-27 | 103 | 17 | Security hardening second + third pass (14 findings) |
| 3.3.0* | 2026-02-26 | 103 | 17 | Security hardening (10 fixes), MCP metadata normalization |
| 3.2.0* | 2026-02-25 | 103 | 17 | content/get-snapshot, empty schema fix (39 tools recovered) |
| 3.1.0* | 2026-02-24 | 103 | 17 | Permission toggles, admin dashboard |
| 3.0.0* | 2026-02-24 | 103 | 17 | 10 new modules, JSON Schema fixes |
| 2.0.0* | 2025-12-21 | 51 | 7 | Menu management, Content v2 |
| 1.0.5* | 2025-12-12 | 40 | 6 | Base64 media upload |
| 1.0.4* | 2025-12-12 | 39 | 6 | Ability persistence fix |
| 1.0.3* | 2025-12-11 | 39 | 6 | Initial release |

*Pre-rename versions (released as "WP Abilities Suite")

---

## License

GPL-2.0-or-later
