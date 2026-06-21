---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-21
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.

## Last Updated
**2026-06-21 (`operator-console-parties-supply-side` — task 1.1 of 19 DONE).** Ralph loop active on `ralph/operator-console-parties-supply-side`. This change builds the **Club** + **ProducerAgreement** operator consoles (the supply-side trio's last two) over shipped Parties Actions — pure operator surface, no domain code. Task 1.1 widened the OperatorPanel module-boundary carve-out from `{Models, Actions}` → `{Models, Actions, Enums}` (ADR `2026-06-21-operator-console-operand-enum-carveout.md`), the prerequisite for the Club create (group 3) to construct `ClubRegistrationFlowType`.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **GREEN after 1.1:** full SQLite suite **1206/1206** (6859 assn), `ModuleBoundariesTest` 3/3 (189 assn), phpstan 0, pint clean, `openspec validate operator-console-parties-supply-side --strict` valid, composer diff vs `main` empty.
- **Run-cmd gotchas:** full suite OOMs under bare `php artisan test` (128M) → use `php -d memory_limit=-1 vendor/bin/pest` / `… phpstan analyse`. i18n tests reuse a top-level helper (`scanOperatorConsoleHardcodedSinks`, declared in `…/Catalog/ProductMasterConsoleI18nTest.php`) → run via `--filter`/full suite, NOT a bare file path (false-red); for a folder-wide PG17 run APPEND that Catalog file. PG17: docker `postgres:17` container `newco-pg17-test`, prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **Active:** `operator-console-parties-supply-side` (1/19 done). Branch `ralph/operator-console-parties-supply-side`.
- **Next task: 2.1** — read-only `ClubResource` (`extends OperatorConsoleResource`, own `status` badge via cast, NO `lifecycleStateColumn()`, getPages index/create/view). Then 2.2 (List + Create/View scaffolds for the getPages boot coupling), 2.3 (resource i18n). Recipe = predecessor `## Codebase Patterns` (`openspec/changes/archive/2026-06-20-operator-console-parties-producer/progress.md`) + design D1–D12.
- Group order: 1 (carve-out, DONE) → 2–6 Club → 7–11 ProducerAgreement. Groups 6 + 11 are PG17 closing chains.

## Blockers & Decisions Needed
- **None.** Carve-out is live; Club create (3.1) unblocked. No open stack-decision gates touched (pure operator surface). Cleanup: `newco-pg17-test` docker container may be running (`docker rm -f newco-pg17-test`).

## Open Patterns
- **Operand vs state enum split (now in CONTEXT.md + the carve-out ADR):** console imports/constructs **operand** enums (Action `handle()` params — `ClubRegistrationFlowType`); **state** enums (`ClubStatus`, `ProducerAgreementStatus`) stay rendered via the model cast (`->value` + `instanceof BackedEnum`), never imported (D2). Mechanical guard admits the whole `Enums` prefix for OperatorPanel only; operand-only is documented discipline, review-enforced.
- The non-catalog Parties console pattern (trait-reuse: `SurfacesDomainActions` + `OperatorConsoleCreateRecord` + `OperatorConsoleResource` helpers, own status column, form-less verbs, i18n kit-key guard, page-driven closing-chain test w/ `toEqualCanonicalizing` + loose `toEqual` for uncast bigints) is PROVEN + in the living spec. Club/Agreement reuse it directly; verb-list base generalization deferred to the rule-of-three demand-side trigger (D10).
