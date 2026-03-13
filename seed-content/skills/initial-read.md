# PROTOCOL — Initial Read

> The first thing an AI does when it connects to a WordPress site through abilities.
> Before any work. Before any conversation. Before any diagnostic lane.

---

## Two Onboarding Layers

### Layer 1 — AI-to-AI (invisible to human)
The knowledge layer teaches the arriving AI:
- How to behave on first contact
- What to read first
- What NOT to do (don't flood, don't diagnose everything, don't break context)
- How to pace the conversation with the human

### Layer 2 — AI-to-Human (the conversation)
Guided by Layer 1, the AI walks the human through discovery together:
- Recognition — the human sees their site reflected back
- Surprise — "how can it know these things so fast?"
- Agency — the human chooses the pace and direction

Layer 1 governs Layer 2. Without it, the AI runs everything, returns everything, overwhelms.

---

## The Sequence
### Step 0 — Connection Check
Verify the MCP bridge is running and can reach the site.
If bridge is down: STOP. Tell the human. This is not skippable.

### Step 1 — Site Snapshot (`suite/get-status`)
**First ability called. Always.**

```
suite/get-status → compact site profile in one call
```

Returns: site name, URL, WP version, PHP version, multisite status, hosting,
abilities tier, active module count — everything needed to orient.

**Multisite check:** If `suite/get-status` shows this is a multisite network, list the available sites and ask the human which sites to include before proceeding to any diagnostics. All subsequent ability calls must target the agreed sites — never assume "main site only."

**NOT `discover-abilities`.** That returns 77KB+ and requires agent parsing.
The full ability manifest is loaded later, per-lane, when actually needed.

### Step 2 — Product Introduction (from knowledge, not discovery)
AI already knows what the plugin contains — this comes from baked-in knowledge,
not from scanning. Present the scope as curated categories:

```
"Your site has [tier] abilities across these areas:"

  1. Content & Publishing — posts, pages, media, taxonomies
  2. System & Health — site health, plugins, themes, cache, cron
  3. Users & Access — user management, roles, passwords
  4. CRM & Marketing — contacts, lists, campaigns, automations
  5. Commerce — products, orders, coupons
  6. Community — spaces, feeds, members, messaging
  7. Forms & Data — form entries, submission management
  8. Booking & Scheduling — appointments, availability
  9. Support — tickets, agents, responses
```

Each category is a door. The human picks which to open.

### Step 3 — First Choice (human-led)
Offer a recommended starting point:

```
"Where would you like to start?"

  1. Tell me about your site (you lead)
  2. Health Check (recommended — let's see how your site is doing)
  3. Pick a category above to explore
```

**The Pacing Rule:** Never chain ability calls without the human choosing
the next step. Never call any write, delete, or update ability until the human
gives you a directive. Every diagnostic is:

```
run ability → present findings → PAUSE → offer choices → human picks → repeat
```

Every step is a doorway the human walks through, not a corridor they're pulled through.

### Step 4 — Introduction Course (diagnostic protocols)
Before running any protocol, call `knowledge/get` with `doc_type: skill` and the protocol's slug to load its full instructions. Never improvise protocol steps.

Based on the human's choice, enter the appropriate protocol:

| Protocol | Lane | What It Fills |
|----------|------|--------------|
| Health Check | System | SITE-IDENTITY: Health, Plugins, Tasks, Cache |
| Content Structure | Content | SITE-IDENTITY: Content + Diagnostics/CONTENT-STRUCTURE |
| Theme and Design | Structure | SITE-IDENTITY: Structure section |
| Plugin Audit | System | Deep plugin analysis (beyond light scan) |
| Scheduled Tasks | System | Cron health deep dive |

Each protocol produces:
- **Summary** → written into SITE-IDENTITY (lean)
- **Detail** → written into a diagnostic document (ASCII diagrams, full tables)

### Step 5 — Story Read (targeted content reading)
After diagnostics map the structure, read the pages that tell the site's story.
This bridges **what the site IS** (diagnostics) with **what it MEANS** (ESSENCE).

**The AI already knows page titles from Content Structure (Step 4).**
It uses those titles to identify story-relevant pages — no bulk reading.

```
PRIORITY ORDER (read one at a time, pause between):

  1. About page (most sites have one)
  2. Home / Front page (the first story visitors see)
  3. Mission / Vision / Values (if they exist)
  4. Services / What We Do (if they exist)
  5. Product/offering pages (sample 1-2, not all)

SKIP: Checkout, Dashboard, Cookie Policy, system pages
```

**Pacing:** Read ONE page → present key findings → ask "read more or enough?" → human controls depth.

**Size safety for any site:**
- AI proposes which 3-5 pages to read based on titles
- Human confirms before any reading calls
- Large sites: AI picks the most story-relevant pages, never bulk-reads
- Each page read independently — if one is too large, skip to next

**Use `content/get-text`** (not `content/get`) for Story Read. It strips block markup
and HTML, returning only readable text (~2-20KB vs 50-200KB from `content/get`).
The response includes `word_count` so you can gauge page size before presenting.

### Step 6 — ESSENCE Synthesis
After diagnostics + Story Read, the AI synthesizes what it's learned into ESSENCE.

**Load the template first:** Call `knowledge/get` with `doc_type: template`, `slug: first-encounter-brief` to get the ESSENCE structure.

The ESSENCE combines narrative understanding with technical facts:

1. **Identity + Voice** — who this site is, what it's about, its tone and themes (from Story Read)
2. **Technical Profile** — environment, hosting, key plugins (from diagnostics)
3. **Content Architecture** — content types, page hierarchy as ASCII tree diagrams, taxonomy map (from Content Structure diagnostic)
4. **Observations** — key findings, gaps, action items (from all diagnostics)
5. **Infrastructure diagram** — ASCII diagram showing how the site's components relate (multisite structure, plugin ecosystem, content flow)

**Present as narrative first:**

```
"Based on what I've seen — your content, your products, your tools,
and the story your site tells — here's what I think this site is:

[narrative synthesis with embedded diagrams]

Does that sound right? What would you add or change?"
```

The human confirms, corrects, or expands. Then **save** the ESSENCE:
- Use `knowledge/create` with `doc_type: essence`, `slug: current`
- Include both the narrative and the structured sections (technical profile, diagrams, observations)
- This becomes the boot document for returning visits — it must be self-contained

This conversation naturally leads into deeper identity files: VISION, MISSION, AUDIENCE, PRODUCTS, SERVICES, BUSINESSMODEL.

### Step 7 — Introduction Complete
When the human has:
- Completed Health Check
- Mapped Content Structure
- Completed Story Read (at least About + Home)
- Confirmed ESSENCE

→ Introduction Course is complete. Level 1 unlocked.

## What This Protocol Does NOT Do
- Call `discover-abilities` as a first step (77KB, requires agent, bad UX)
- Diagnose everything at once
- Enter any diagnostic lane without the human choosing
- Present all abilities as a flat list
- Chain ability calls without pausing for human input
- Run multiple protocols without offering a choice between each
- Make assumptions about what the human wants to do
- Continue past any step without human input

## Design Principle

The protocol mirrors the Open Claw pattern adapted for WordPress:
- BOOT (every connection) → first read, orientation
- SOUL / IDENTITY → site profile (who is this site?)
- USER → the human operating through the conversation
- SKILLs → diagnostic lanes entered on demand
- The bootstrap happened once (plugin install + MCP connect). This protocol runs every conversation.

---

*v0.3.0 — Tested live against helenawillow.com. Story Read validated. Payload issue confirmed and documented.*
