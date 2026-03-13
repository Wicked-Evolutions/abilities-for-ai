# PROTOCOL — Content Structure

> Content lane diagnostic. Part of the Introduction Course.
> Maps the content landscape — what exists, how it's organized, what's published vs draft.

---

## When To Use

- During onboarding to understand the site's content
- Before major content reorganization
- When the human asks "what do I have?"

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

**Pause.** Human chooses: taxonomy terms, commerce collections, or save findings.

### Step 4 — Taxonomy Terms (optional, per human choice)
**Ability:** `taxonomies/list-terms` per active taxonomy
**What it reveals:** How content is grouped, which terms have content, which are empty

### Step 5 — Save and Synthesize
**Output:**
- **Summary** → site-identity document Content section (table + key insight)
- **Detail** → diagnostic document (ASCII diagrams, full tables, observations)

**Present:** "Here's the shape of your site's content" — narrative, not data dump.

## Pacing Rule

**Never chain steps.** Each step: run → present → pause → human chooses next.

---

*v0.2.0 — Tested live against helenawillow.com. Payload issue confirmed and documented.*
