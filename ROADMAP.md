# ROADMAP — Abilities for AI

> Source of truth for development status. Updated with code commits.

## Current Version: v1.4.0

### Recently Shipped

| Feature | Version | Date | Issue |
|---------|---------|------|-------|
| `content/duplicate` — duplicate any post/page/CPT with meta + taxonomies | v1.4.0 | 2026-03-16 | — |
| `theme/enqueue-asset`, `theme/dequeue-asset`, `theme/list-enqueued-assets` | v1.3.0 | 2026-03-16 | — |

### Gaps — Open

| Gap | Priority | Issue | Notes |
|-----|----------|-------|-------|
| **Block API v2: Nested block addressing + attribute updates** | High | [#54](https://github.com/Wicked-Evolutions/abilities-for-ai/issues/54) | `blocks/update-attributes`, `blocks/find-nested`, `blocks/get-at-path`, path concept `[0,2,1]`, `innerContent` normalizer. Found 2026-03-16 during block learning session. |
| `content/append` — append content without replacing all | Medium | — | Needed for incremental page building. Currently must send entire 52KB+ page for any edit. |
| `post_author` on `content/create` and `content/update` | Low | — | Posts default to bridge user. Found 2026-03-07. |

### Resolved Bugs

| Bug | Version | Issue |
|-----|---------|-------|
| `blocks/insert` loses innerBlocks | — | Root cause identified in [#54](https://github.com/Wicked-Evolutions/abilities-for-ai/issues/54): missing `innerContent` reconstruction from JSON input |
