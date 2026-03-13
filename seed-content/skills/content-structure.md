# PROTOCOL — Content Structure

> Content lane diagnostic. Part of the Introduction Course.
> Maps the content landscape — what exists, how it's organized, what's published vs draft.

---

## When To Use

- During onboarding to understand the site's content
- Before major content reorganization
- When the human asks "what do I have?"

## Multisite Scope
If the site is a multisite network, the human should have already chosen which sites to include during the Initial Read (Step 1). Run every step in this protocol against each included site. Present results per-site, then summarize differences.

## Steps
### Step 1 — Content Types
**Ability:** `content/discover-types`
**What it reveals:** All registered post types (posts, pages, custom post types)
**Present:** Table of types with labels, hierarchical/flat, REST availability.

**Pause.** Human chooses: count everything, taxonomies first, or both in parallel.

### Step 2 — Content Counts + Taxonomy Map (parallel)
**Abilities:**
- `content/list` per type with `per_page: 1` — gets total count from response metadata
- `taxonomies/discover` — all registered taxonomies

**PAYLOAD WARNING:**
`content/list` returns **full post content** (Gutenberg block markup).
Even `per_page: 1` for pages returned 200KB+ in testing.
For 11 pages at `per_page: 100` → 566KB. Unusable for mapping.

**Workaround (until diagnostic-weight abilities exist):**
- Use `per_page: 1` to get counts from response `total` field
- NEVER request `per_page: 100` for pages — content payload explodes
- For page structure mapping, use `per_page: 5` and extract only title/ID/parent/slug via post-processing

**Needed (not yet built):**
- `content/list-structure` — metadata only, no content field
- `content/get-site-map` — hierarchical tree, one call

**Present:** Content type table with counts. Taxonomy table with term counts. ASCII diagram of site shape.

**Pause.** Human chooses: explore page structure, explore taxonomy terms, or continue.

### Step 3 — Page Structure (if pages are primary content)
**Ability:** `content/list` with `per_page: 5`, paginated, post-processed for structure
**What it reveals:** Page hierarchy (parent/child), navigation shape, core vs utility pages

**Present:** ASCII tree diagram of page hierarchy. Categorize into: Core, Offerings, System/Utility.
**Log observations** for: orphan pages (no parent, not top-level), missing expected pages (no About, no Home), deep nesting (3+ levels).

**Pause.** Human chooses: taxonomy terms, commerce collections, or save findings.

### Step 4 — Taxonomy Terms (optional, per human choice)
**Ability:** `taxonomies/list-terms` per active taxonomy
**What it reveals:** How content is grouped, which terms have content, which are empty
**Log observations** for: empty taxonomies, unused terms, duplicate/overlapping categories.

### Step 5 — Save and Synthesize
**Ask the human:** "Want me to save these findings to the Knowledge Layer?"

If yes, write two things:

1. **Diagnostic document** — the full output: ASCII diagrams, page trees, content type tables, taxonomy maps, observations. Use `knowledge/create` with `doc_type: diagnostic`, `slug: content-structure`.
   - For multisite: include per-site sections and the comparison diagram.
2. **Site identity update** — a lean summary (content types, page count, key observations) into the site-identity document's Content section. Use `knowledge/update` if the document exists.

**Present:** "Here's the shape of your site's content" — narrative with ASCII diagrams, not a data dump. The diagram should show the site's page hierarchy as a tree, categorized (Core/Story, Commerce, Legal/System).

## Recording Observations

After each step, log notable findings as individual observations using `knowledge/add-observation`:

```
knowledge/add-observation
  session_id: (current session ID)
  category: "content"
  severity: "info", "attention", or "action_needed"
  description: what was observed (one finding per call)
  source_diagnostic: "content-structure"
```

**What qualifies as an observation:**
- Missing expected content types or pages
- Orphan pages, deep nesting, inconsistent hierarchy
- Empty taxonomies or unused terms
- Content type/taxonomy mismatches across multisite
- Large content volumes that need pagination strategy

**NOT observations:** Normal structure, expected counts. Only log what's notable.

## Pacing Rule

**Never chain steps.** Each step: run → present → pause → human chooses next.

---

*v0.3.0 — Observation recording added. Tested live against wickedevolutions.com.*
