# PROTOCOL — End Session

> Runs at the end of every Knowledge Layer session.
> Ensures continuity between sessions by updating the site's knowledge files.
> The next AI reads SITE-STATE and SESSION-LOG on boot — this protocol writes them.

---

## When To Use

- At the end of every session, no exceptions
- When the human says "end session", "wrap up", "save and close", or similar
- When context is running low and work needs to be preserved

## Steps

### Step 1 — Gather Session Summary

Review what happened in the current session:
- Which agent mode was active (Diagnostician, Publisher, Designer, etc.)
- Which protocols were run
- Which knowledge files were created or updated
- What findings were discovered
- What decisions were made by the human
- What's still open or unresolved

### Step 2 — Update Site State

Update each section of the site-state document with current information:

| Section | What To Update |
|---------|---------------|
| Last Session | Date, time range, agent mode, model, focus |
| What Happened | Protocols completed, files updated, findings |
| What's Open | Unresolved items, pending human decisions, flagged observations |
| Items Pending User Decision | Suggested follow-up, available protocols, pending confirmations. The next AI will present these as choices — not execute them. Write them as options, not instructions. |
| Course Progress | Update completion status for each course/protocol |
| Observations | Append new observations (never delete previous ones) |

### Step 3 — Write Session Log Entry

Log the session via `knowledge/log-session`:

```
Session entry format:
- session_id: unique identifier
- agent_type: which agent mode was active
- model: AI model used
- started_at / ended_at: session time range
- summary: what happened
- protocols_run: list of protocol slugs executed
- documents_modified: list of document IDs created/updated
- findings: key findings
- pending_user_decisions: suggested follow-up for next session (presented as choices, not executed)
```

**Session log is append-only.** Never edit previous entries. The history tells the story.

### Step 4 — Verify Knowledge Files

Check that all work from this session is persisted:

| Check | How |
|-------|-----|
| Diagnostic results saved? | Each protocol run should have a diagnostic document |
| Site identity updated? | Summary data from each diagnostic in site-identity |
| ESSENCE current? | If Story Read was done, ESSENCE should reflect it |
| Observations logged? | All findings recorded via knowledge/add-observation |

If anything is missing: save it now, before closing.

### Step 5 — Present End Session Summary to Human

Brief summary of what was saved:

```
"Session saved. Here's what's persisted:

  Site State: [updated / created]
  Session Log: [entry appended]
  Diagnostics: [list of documents created/updated]
  ESSENCE: [status]
  Observations: [count of new observations]

  Pending user decisions: [items the next AI will present as choices]"
```

## Pacing Rule

End Session is NOT paced — it runs to completion without pausing.
The human has already indicated they want to end. Execute all steps, then present the summary.

## What This Protocol Does NOT Do

- Delete or overwrite previous session entries
- Modify ESSENCE without human confirmation
- Act on observations (only documents them)
- Push changes to external systems
- Make decisions about what comes next (only suggests)

---

## Design Principles

1. **Continuity over completeness** — save what happened, even if work is incomplete. The next AI needs to know where things stand, not wait for perfect documentation.
2. **Site state is the boot file** — the next AI reads this first. It must be accurate and current.
3. **Session log is the history** — append-only, never edited. Each entry is a snapshot in time.
4. **Observations accumulate** — they're never deleted. Resolved observations are marked resolved, not removed.
5. **The human doesn't need to direct this** — End Session is self-executing once triggered. The AI knows what to save because it knows what happened.

---

*v1.0.0 — Designed and tested live against helenawillow.com.*
