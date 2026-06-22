---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-22
---

# Hot Cache

## Last Updated
**2026-06-22 (`operator-console-parties-holds` — task 6.2 DONE, 12/12 → CHANGE_COMPLETE).** Final verify-and-persist task: re-ran the WHOLE final-pass gate set from a fresh iteration (not trusting 6.1's recorded green). `openspec validate --strict` **valid** · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` max **0 errors** · full `php -d memory_limit=-1 vendor/bin/pest` **1442/1442 (7950 assn)** (exactly the 5.1 baseline, no regression). `main`-diff forbidden-path audit each **NONE**: `^spec/` · `^openspec/specs/` (truth) · `^composer\.(json|lock)$` · `^database/migrations/` · `^tests/Architecture/` (allowlist UNCHANGED — Parties imports ride the prefix carve-out). Promoted the boundary carve-out rule to a standalone bullet in progress.md `## Codebase Patterns`. **All 12 tasks `- [x]`.**

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (this iter, full final pass): SQLite 1442/1442 (7950 assn); phpstan max 0; pint + pint --test clean; validate --strict valid.** PG17 319/319 (Parties OperatorPanel folder + Catalog i18n helper) from 5.2 still stands.
- **Full suite AND the arch `--filter` OOM at PHP's default 128 MB** in result *parsing* (`Collector->result()`) — NOT a test failure. Run with `php -d memory_limit=-1 vendor/bin/pest`. A SMALL `--filter` + `phpstan analyse` are fine at default; the architecture `expect()`-rule filters are NOT (give them `-1` too).

## Active Change & Next Task
- **`operator-console-parties-holds` is CHANGE_COMPLETE (12/12, APPROVED).** This iteration emitted `<promise>CHANGE_COMPLETE</promise>`.
- **Next = HUMAN step, NOT the loop:** the GUIDE §2.7 closure — semantic-verify → human review/merge of `ralph/operator-console-parties-holds` → push → `openspec archive operator-console-parties-holds --yes`. The loop NEVER pushes/merges/archives.
- After archive, author the next slice via `/spec-to-change` (e.g. the deferred Parties KYC / record-screening / account / profile verbs, or the next OperatorPanel module).

## Blockers & Decisions Needed
- **None.** All gates green, change complete. Only the human §2.7 step remains.

## Open Patterns
- **The whole Holds slice's reusable patterns live in the change's `progress.md` `## Codebase Patterns`** (travels to archive): non-relation per-row-action `TableWidget` footer-widget vehicle; `getFooterWidgets()` + explicit-`record` hosting on a `ViewRecord`; non-relation scope-set OR-query; bespoke multi-operand header action; bespoke + per-row write-through REUSING `surfaceLifecycleOutcome`; heterogeneous closing-chain envelope; and (6.2) the OperatorPanel→Parties boundary carve-out **"EXERCISED, never WIDENED"** rule (ADR 2026-06-21). Landmines still standing: a Filament hidden record-action closure is unreachable via any test helper (reject = domain `toThrow`, NOT `action_failed`); per-row key off typed `$record->id` not `getKey()` (`cast.int`); state enums (`HoldStatus`) stay cast-only even in predicates.
