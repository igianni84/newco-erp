---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (interactive author+approve) — `substrate-hardening` AUTHORED + APPROVED, ready for `./ralph.sh`.** Authored the OpenSpec change for the 2026-06-13 360° substrate audit (C1–C15): `proposal.md` · `design.md` (D1–D10) · `specs/event-substrate/spec.md` delta · `tasks.md` (17 tasks / 6 groups, dependency-ordered). `openspec validate substrate-hardening --strict` → valid. Every file:line in the tasks was verified against the live tree first. Delta = **2 ADDED reqs** on `event-substrate` (Concurrent Delivery Safety = C1; Delivery Failure Observability = C3); C7–C10 are coverage-only (no spec change), C2/C4–C6/C11–C15 are config/CI/doc. Created the empty `APPROVED` marker + logged; landing the `approve: substrate-hardening` commit on main (scaffolding + APPROVED + hot.md + log.md, per precedent `3fba1c7`).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15.0 · Filament 5.6.7 · Pennant v1.23.0 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` still `php ^8.3` — bump is task 2.1 of the approved change.)
- **`main@8fe0ec4`** (pre-approve): suite 243/243 · phpstan 0 @ max · pint clean · CI two-lane green (quality SQLite + tests-pgsql PG-17).

## Active Change & Next Task
- **ACTIVE = `substrate-hardening`** (APPROVED, 0/17 tasks). `openspec list` shows it.
- **NEXT = `./ralph.sh --change substrate-hardening`** (N ≈ (17×1.5)+2 ≈ 28). Loop preflight auto-stages the change folder; the approve commit already tracks APPROVED so the exit-5 gate cannot fire (lessons.md 2026-06-12).
- Task order: 1.x substrate (C1 race fix TDD → C3 logging TDD → C2 mutex TTL) · 2.x config (php 8.5 / .env / pgsql tz) · 3.x test gaps · 4.x CI concurrency · 5.x docs · 6.x cross-engine + validate.

## Blockers & Decisions Needed
- None active. C1 tests use **reflection on private `attempt()`/`recordFailure()`** (single-connection SQLite can't interleave; `lockForUpdate` no-op on SQLite / real lock on PG) — see design D2.
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). C15 newly tracks: secrets mgmt · observability · PCI boundary · security review.

## Open Patterns
- **PG verification:** CI `tests-pgsql` lane is the standing gate (PG not installed locally); C12 adds a local `docker run postgres:17` option to GUIDE §2.7 + task 6.1. Close ritual: push branch → CI both lanes green BEFORE merge → merge `--no-ff`, push, delete branch.
- **Approve ritual:** `approve: <change>` commit on main BEFORE `./ralph.sh` (scaffolding + APPROVED together) — precedent `3fba1c7`, `9a28cf6`.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB). hot.md ≤550 words.
- **Archived patterns:** `openspec/changes/archive/2026-06-13-foundations-money-i18n-flags/progress.md` (12 patterns: VOs, casts, enums, lang/, Pennant, ActorContext, doc-pins).
