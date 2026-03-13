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
**Pause after:** Present results. Offer next layer.

### Step 2 — Plugin Landscape
**Ability:** `plugins/list`
**What it reveals:** Active/inactive count, plugin versions, potential conflicts, deprecated plugins, security-sensitive plugins
**Pause after:** Present findings with observations. Offer next step or let human explore a finding.

### Step 3 — Scheduled Tasks
**Ability:** `cron/list-events`
**What it reveals:** Background jobs, their frequency, which plugins registered them, any overdue tasks
**Pause after:** Present findings. Offer cache check or return to map.

### Step 4 — Cache Status
**Ability:** `cache/list-transients`
**What it reveals:** Transient count, expired transients, cache health
**Pause after:** Summarize full health check. Offer to save findings.

## Pacing Rule

**Never chain steps.** Each step: run → present → pause → human chooses next.

## What Gets Written

After completing (any number of steps), offer to save findings to:
- Site identity document → relevant sections updated
- Site state document → health check recorded as completed
- Session log → session entry appended

---

*Skeleton — steps will be refined through live testing.*
