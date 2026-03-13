# PROTOCOL — Scheduled Tasks

> System lane diagnostic. Part of the Introduction Course.
> What's running in the background on this site.

---

## When To Use

- During Health Check (Step 3)
- When investigating performance issues
- When the human asks "what's running on my site?"

## Steps

### Step 1 — List All Cron Events
**Ability:** `cron/list-events`
**What it reveals:** All scheduled tasks — hook name, schedule (hourly/daily/weekly), next run time
**Log observations** for: overdue tasks, unusually frequent schedules (< 5 minutes), large number of one-time events.

### Step 2 — Identify Owners
**Synthesize:** Map cron hooks to the plugins that registered them. Flag any orphaned hooks (plugin removed but cron remains).
**Log observations** for: each orphaned hook (severity: attention), hooks from inactive plugins.

### Step 3 — Flag Anomalies
**Look for:** Overdue tasks, unusually high frequency, duplicate hooks, tasks from inactive plugins
**Log observations** for: each anomaly found (severity based on impact — overdue = attention, security-related = action_needed).

## Recording Observations

After each step, log notable findings as individual observations using `knowledge/add-observation`:

```
knowledge/add-observation
  session_id: (current session ID)
  category: "technical"
  severity: "info", "attention", or "action_needed"
  description: what was observed (one finding per call)
  source_diagnostic: "scheduled-tasks"
```

**What qualifies as an observation:**
- Overdue scheduled tasks
- Orphaned cron hooks (plugin removed, hook remains)
- Unusually high-frequency schedules
- Duplicate hooks doing the same work
- Tasks from inactive or removed plugins
- Missing expected tasks (e.g. no update check, no cleanup)

**NOT observations:** Normal WordPress core cron events running on schedule.

## Pacing Rule

**Never chain steps.** Each step: run → present → pause → human chooses next.

## What Gets Written

After completing (any number of steps), ask the human: "Want me to save these findings?"

If yes, write:

1. **Diagnostic document** — full output: cron event table, owner mapping, anomaly list, observations. Use `knowledge/create` with `doc_type: diagnostic`, `slug: scheduled-tasks`.
2. **Site identity update** — lean summary (task count, key anomalies) into the site-identity document's Cron section. Use `knowledge/update` if the document exists.
3. **Session log** — record the scheduled tasks diagnostic as completed via `knowledge/log-session`.

---

*v0.2.0 — Observation recording and save instructions added.*
