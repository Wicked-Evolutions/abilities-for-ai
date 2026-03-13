# PROTOCOL — Theme and Design

> Structure lane diagnostic. Part of the Introduction Course.
> Understand the site's visual foundation.

---

## When To Use

- During onboarding to understand the site's design
- Before making design changes
- When the human asks about their theme

## Steps

### Step 1 — Active Theme
**Ability:** `themes/get-active`
**What it reveals:** Theme name, version, parent theme (if child), block theme or classic
**Deep dive:** If it's a block theme, also examine `themes/get-theme-json` for: typography (font families, sizes), color palette, layout settings (content/wide widths), custom templates, style variations.
**Log observations** for: outdated theme version, classic theme on modern WP, missing parent theme, unusual font loading, design split between theme.json and customizer.

### Step 2 — Theme Modifications
**Ability:** `themes/list-mods`
**What it reveals:** Customizer settings, custom CSS, logo, colors, layout choices
**Log observations** for: heavy customizer usage on a block theme (should be in theme.json), missing site logo, custom CSS that duplicates theme.json capabilities.

### Step 3 — Menu Structure
**Ability:** `menus/list-menus` + `menus/list-menu-items`
**What it reveals:** Navigation architecture — how many menus, where they're assigned, what pages they link to
**Log observations** for: no header navigation, orphan menus (not assigned to any location), test/placeholder menus, broken links.

### Step 4 — Patterns and Templates
**Ability:** `patterns/list`
**What it reveals:** Available block patterns — reusable design components
**Also check:** Template count and custom templates from Step 1's theme.json deep dive.
**Log observations** for: no custom patterns (relying entirely on parent theme), patterns that don't match the site's aesthetic, unused custom templates.

## Recording Observations

After each step, log notable findings as individual observations using `knowledge/add-observation`:

```
knowledge/add-observation
  session_id: (current session ID)
  category: "design"
  severity: "info", "attention", or "action_needed"
  description: what was observed (one finding per call)
  source_diagnostic: "theme-and-design"
```

**What qualifies as an observation:**
- Missing header navigation or broken menu structure
- Design split (e.g. different font stacks for different templates)
- No custom patterns on a customized theme
- Customizer overrides on a block theme (should be in theme.json)
- Test menus or placeholder content that should be cleaned up
- Accessibility concerns (contrast, font size)

**NOT observations:** Normal theme configuration, expected parent theme inheritance.

## Pacing Rule

**Never chain steps.** Each step: run → present → pause → human chooses next.

## What Gets Written

After completing (any number of steps), ask the human: "Want me to save these findings?"

If yes, write:

1. **Diagnostic document** — full output: theme profile, theme.json analysis, template inventory, pattern audit, menu structure, asset inventory, observations. Use `knowledge/create` with `doc_type: diagnostic`, `slug: theme-and-design`.
2. **Site identity update** — lean summary (theme name, type, key design facts) into the site-identity document's Structure section. Use `knowledge/update` if the document exists.
3. **Session log** — record the theme and design diagnostic as completed via `knowledge/log-session`.

---

*v0.2.0 — Observation recording, save instructions, and theme.json deep dive added. Tested live against wickedevolutions.com.*
