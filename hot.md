---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-22
---

# Hot Cache

## Last Updated
**2026-06-22 (`operator-console-parties-holds` — task 6.1 DONE, 11/12).** VERIFY-ONLY (no code change): ran all four quality gates on the full change + audited the `main` diff. `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` max **0 errors** · arch tests `ModuleBoundariesTest`+`NoEloquentWriteInOperatorPanelRule` **4/4 (201 assn)** green and UNCHANGED (diff lists no `tests/Architecture/` file → allowlist never widened; `Parties\Enums`/`Actions` ride the operand-enum + `{Models,Actions,Enums}`-prefix carve-out, ADR 2026-06-21) · full `php -d memory_limit=-1 vendor/bin/pest` **1442/1442 (7950 assn)** (exactly the 5.1 baseline, no regression) · `openspec validate --strict` valid. **Main-diff audit each NONE:** `^spec/` · `^openspec/specs/` (only the permitted DELTA spec under `changes/.../specs/`) · `^composer\.(json|lock)$` · `^database/migrations/`.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (this iter, full re-verify): SQLite 1442/1442 (7950 assn); phpstan max 0; arch 4/4 (201 assn) unchanged; pint + pint --test clean; validate --strict valid.** PG17 319/319 (Parties OperatorPanel folder + Catalog i18n helper) from 5.2 still stands.
- **Full suite AND the arch `--filter` OOM at PHP's default 128 MB** in `Collector->result()` during result *parsing* — NOT a test failure. Run with `php -d memory_limit=-1 vendor/bin/pest`. A SMALL `--filter` + `phpstan analyse` are fine at default; the architecture `expect()`-rule filters are NOT (they load many files) — give them `-1` too.

## Active Change & Next Task
- **Active: `operator-console-parties-holds` (APPROVED). 11/12 done.**
- **Next: task 6.2 (LAST).** `openspec validate operator-console-parties-holds --strict` (already green this iter) + `scripts/memlog.sh` + overwrite hot.md + consolidate the durable patterns into the change's `progress.md` `## Codebase Patterns` (the non-relation per-row-action `TableWidget` vehicle; the bespoke-action-reusing-`surfaceLifecycleOutcome` multi-operand/Hold-id write-through; "operand-enum carve-out exercised → `ModuleBoundariesTest` needs no widening"). Those three patterns are ALREADY present in progress.md's Codebase Patterns from 1.2–5.1 — 6.2 just confirms/tightens the wording, no new authoring expected. Then emit `<promise>CHANGE_COMPLETE</promise>`.

## Blockers & Decisions Needed
- **None.** All gates green. 6.2 is validate + memory only → `CHANGE_COMPLETE`. The §2.7 merge/push/archive is the HUMAN step AFTER `CHANGE_COMPLETE` (loop never pushes/archives/merges).

## Open Patterns
- **6.1 learning (recorded in progress.md):** the architecture `expect()`-rule filters OOM at default 128 MB exactly like the whole suite (the OOM is in result-*parsing*, not the test) — run them with `-d memory_limit=-1`. The diff audit is a `git diff --name-only main` grep of `^spec/` / `^openspec/specs/` / `^composer\.` / `^database/migrations/` (each NONE), NOT a guess — and the change's own delta spec under `openspec/changes/<name>/specs/` is PERMITTED (only `openspec/specs/` truth specs are forbidden hand-edits). All prior Holds-slice landmines still stand (heterogeneous closing-chain envelope Hold/Customer disambiguated by `entity_id`; Filament hidden record-action closure unreachable via any test helper → reject = domain `toThrow`; per-row key off typed `$record->id` not `getKey()`→`cast.int`; state enums cast-only even in predicates).
