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
**Log observations** for: plugins with available updates, plugins not in the WordPress.org repository, plugins with known vulnerabilities.

### Step 2 — Inactive Plugin Review
**Focus:** Plugins that are installed but not active
**Question to human:** "These are installed but not running. Do you still need them, or can we clean them up?"
**Log observations** for: each inactive plugin (severity: attention), especially those with security implications.

### Step 3 — Duplicate Function Detection
**Focus:** Multiple plugins serving the same purpose (e.g. two commerce solutions, two SEO plugins)
**Question to human:** "I notice you have [X] and [Y] both active — are both in use?"
**Log observations** for: each duplicate function pair (severity: attention).

### Step 4 — Security-Sensitive Plugins
**Focus:** Plugins that create access points (temporary login, debug tools, etc.)
**Question to human:** "This plugin allows [X] — is it still needed?"
**Log observations** for: each security-sensitive plugin (severity: action_needed if debug/temp login tools are active in production).

## Recording Observations

After each step, log notable findings as individual observations using `knowledge/add-observation`:

```
knowledge/add-observation
  session_id: (current session ID)
  category: "security" or "technical"
  severity: "info", "attention", or "action_needed"
  description: what was observed (one finding per call)
  source_diagnostic: "plugin-audit"
```

**What qualifies as an observation:**
- Inactive plugins (potential attack surface)
- Outdated plugins with available updates
- Duplicate functionality across plugins
- Security-sensitive plugins active in production
- Plugins not from WordPress.org repository
- Plugins with no recent updates (abandoned)

**NOT observations:** Active, up-to-date plugins functioning as expected.

## Pacing Rule

**Never chain steps.** Each step: run → present → pause → human chooses next.

## What Gets Written

After completing (any number of steps), ask the human: "Want me to save these findings?"

If yes, write:

1. **Diagnostic document** — full output: plugin inventory table, inactive review, duplicate analysis, security assessment, observations. Use `knowledge/create` with `doc_type: diagnostic`, `slug: plugin-audit`.
2. **Site identity update** — lean summary (plugin count, key concerns) into the site-identity document's Plugins section. Use `knowledge/update` if the document exists.
3. **Session log** — record the plugin audit as completed via `knowledge/log-session`.

---

*v0.2.0 — Observation recording and save instructions added.*
