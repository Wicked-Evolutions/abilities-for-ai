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

### Step 2 — Identify Owners
**Synthesize:** Map cron hooks to the plugins that registered them. Flag any orphaned hooks (plugin removed but cron remains).

### Step 3 — Flag Anomalies
**Look for:** Overdue tasks, unusually high frequency, duplicate hooks, tasks from inactive plugins

## Pacing Rule

**Never chain steps.** Each step: run → present → pause → human chooses next.

---

*Skeleton — to be refined through live testing.*
