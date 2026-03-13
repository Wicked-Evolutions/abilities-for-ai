# PROTOCOL — Plugin Audit

> System lane diagnostic. Part of the Introduction Course.
> Deep dive into the plugin landscape — beyond what Health Check covers.

---

## When To Use

- After Health Check reveals plugin concerns
- When the human asks about their plugins
- Before installing new plugins
- Periodic review

## Steps

### Step 1 — Plugin Inventory
**Ability:** `plugins/list`
**What it reveals:** Full plugin list with versions, active/inactive status
**Present as:** Grouped by function (foundation, ecosystem, infrastructure, other)

### Step 2 — Inactive Plugin Review
**Focus:** Plugins that are installed but not active
**Question to human:** "These are installed but not running. Do you still need them, or can we clean them up?"

### Step 3 — Duplicate Function Detection
**Focus:** Multiple plugins serving the same purpose (e.g. two commerce solutions, two SEO plugins)
**Question to human:** "I notice you have [X] and [Y] both active — are both in use?"

### Step 4 — Security-Sensitive Plugins
**Focus:** Plugins that create access points (temporary login, debug tools, etc.)
**Question to human:** "This plugin allows [X] — is it still needed?"

## Pacing Rule

**Never chain steps.** Each step: run → present → pause → human chooses next.

---

*Skeleton — to be refined through live testing.*
