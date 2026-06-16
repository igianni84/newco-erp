---
type: decision
status: active
date: 2026-06-16
---

## Decision: Run `/dreaming` as a weekly cloud routine (propose-only, via PR)

A scheduled cloud agent (claude.ai routine `trig_0178e8Bv8K5p1zfvcjPg76W5`) runs the repo's `/dreaming` skill **every Monday at 06:00 UTC** (= 08:00 Europe/Rome in CEST). Each run opens a `dream/<date>` branch + PR with the curation report as the body, and **never** commits to `main` — the PR is the human review gate.

- **Cadence:** `cron_expression: "0 6 * * 1"` (UTC), weekly. Next run 2026-06-22.
- **Model:** `claude-opus-4-8[1m]` (Opus 4.8, 1M context) — chosen over the `/schedule` skill's Sonnet default. Memory curation is judgment-heavy (promote/demote, never-invent-a-confirmation), low-frequency (weekly), and high-leverage; curation quality outweighs the per-run cloud cost. (See `lessons.md` 2026-06-16.)
- **Repo:** `github.com/igianni84/newco-erp`. **Tools:** `Bash, Read, Write, Edit, Glob, Grep`. **No MCP connectors** — least-privilege; the API auto-attached all five (Gmail/Slack/Drive/Notion/Calendar) on create, cleared via `clear_mcp_connections`.
- **Prompt:** self-contained (a cloud session starts cold) — points at `.claude/skills/dreaming/SKILL.md` and inlines the procedure + the hard safety rules (work only on `dream/<date>`; never touch `main` or Protected Files; timestamps from the real clock; conservative bias = propose, don't apply).

## Context
The `/dreaming` skill (PR #1, merged `37f413a`) is propose-only and safe to schedule *because* it lands via PR. The hypotheses→rules promotion lifecycle had silently stalled — all three `hypotheses.md` were empty while 6+ patterns recurred across changes, frozen in archived `progress.md`. Manual-only cadence is exactly what decays; a scheduled routine keeps the second brain from re-accreting drift. The `memory-health.sh` Stop hook already catches *mechanical* drift (hot.md word count, log.md size); Dreaming does the *semantic* curation it can't.

## Alternatives considered
- **Local `CronCreate`** — rejected: session-only (dies when the REPL exits), `durable: true` writes to the Protected `.claude/`, 7-day auto-expiry, and fires only while a local REPL is idle. Wrong tool for an unattended weekly job.
- **Sonnet 4.6 (skill default)** — rejected; see the model rationale above (Giovanni's call: Opus 4.8 1M).
- **Daily cadence** — rejected: too noisy for the project's change rate; a weekly batch is more reviewable.
- **Auto-apply to `main`** — never on the table; it would violate the skill's propose-only design.

## Reasoning
Monday-morning timing puts a curation PR in front of Giovanni at the start of the week, covering the prior week's accumulated memory. Opus 4.8 1M maximizes grading quality; the PR gate bounds any error. Least-privilege (no connectors, scoped tools) limits the blast radius of an unattended agent.

## Trade-offs accepted
- **Cost:** one weekly Opus 4.8 1M cloud run. Accepted for curation quality.
- **DST drift:** a UTC cron can't track Europe/Rome DST — `06:00 UTC` = 08:00 Rome in summer (CEST), 07:00 in winter (CET). Immaterial for a weekly memory job.
- **Not the in-session scheduler:** this is a claude.ai *routine*, so it does **not** auto-expire; it runs until disabled/deleted at the routines console.

## References
- Skill: `.claude/skills/dreaming/SKILL.md` · first run + report: `dreams/2026-06-16.md` · PR #1 merge `37f413a`.
- Routine: `trig_0178e8Bv8K5p1zfvcjPg76W5` — https://claude.ai/code/routines/trig_0178e8Bv8K5p1zfvcjPg76W5
- Memory discipline: `.claude/CLAUDE.md` → Memory Systems; mechanical-drift hook `.claude/hooks/memory-health.sh`.
- Correction that set the model: `lessons.md` 2026-06-16 ("Dreaming routine runs on Opus 4.8 1M").
