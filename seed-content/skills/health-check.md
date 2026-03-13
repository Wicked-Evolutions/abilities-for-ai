# PROTOCOL — Health Check

> System lane diagnostic. Part of the Introduction Course.
> Sequential, paced, human-guided at every step.

---

## When To Use

- First diagnostic during onboarding (recommended starting point)
- Periodic site health reviews
- Before major changes (plugin updates, theme switches)

## Steps

### Step 1 — Site Health Status
**Ability:** `site-health/status`
**What it reveals:** WordPress built-in health tests — critical issues, recommendations, informational items
**Pause after:** Present results. Log any critical or recommended items as observations (see Recording Observations). Offer next layer.

### Step 2 — Plugin Landscape
**Ability:** `plugins/list`
**What it reveals:** Active/inactive count, plugin versions, potential conflicts, deprecated plugins, security-sensitive plugins
**Pause after:** Present findings. Log notable findings (conflicts, outdated plugins, security concerns) as observations. Offer next step or let human explore a finding.

### Step 3 — Scheduled Tasks
**Ability:** `cron/list-events`
**What it reveals:** Background jobs, their frequency, which plugins registered them, any overdue tasks
**Pause after:** Present findings. Log overdue tasks or orphaned hooks as observations. Offer cache check or return to map.

### Step 4 — Cache Status
**Ability:** `cache/list-transients`
**What it reveals:** Transient count, expired transients, cache health
**Pause after:** Summarize full health check. Log cache issues as observations. Offer to save findings.

## Recording Observations

After each step, log notable findings as individual observations using `knowledge/add-observation`:

```
knowledge/add-observation
  session_id: (current session ID)
  category: "technical" or "security"
  severity: "info", "attention", or "action_needed"
  description: what was observed (one finding per call)
  source_diagnostic: "health-check"
```

**What qualifies as an observation:**
- Critical or recommended items from site health tests
- Outdated plugins, security-sensitive plugins, or conflicts
- Overdue cron tasks or orphaned hooks
- Missing object cache, excessive transients
- Anything the human should know about or act on

**NOT observations:** Normal, healthy results. Only log what's notable.

## Pacing Rule

**Never chain steps.** Each step: run → present → pause → human chooses next.

## What Gets Written

After completing (any number of steps), ask the human: "Want me to save these findings?"

If yes, write:

1. **Diagnostic document** — full output: health test results, plugin table, cron jobs, cache status, observations. Use `knowledge/create` with `doc_type: diagnostic`, `slug: health-check`.
2. **Site identity update** — lean summary (health score, plugin count, key issues) into the site-identity document's Health section. Use `knowledge/update` if the document exists.
3. **Session log** — record the health check as completed via `knowledge/log-session`.

---

*Skeleton — steps will be refined through live testing.*
