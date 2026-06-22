---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-22
---

# Hot Cache

## Last Updated
**2026-06-22 (`operator-console-parties-holds` — task 5.2 DONE, 10/12).** VERIFY-ONLY (no code change): ran the GUIDE §2.7 PostgreSQL-17 gate, scoped to the Parties console slice. Recreated a fresh `pg` container (`docker rm -f pg` first — a healthy leftover from the crashed prior iteration existed, same config, but a gate shouldn't trust unknown provenance) → `pg_isready` 1s → PG **17.10** → `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest tests/Feature/Modules/OperatorPanel/Parties tests/Feature/Modules/OperatorPanel/Catalog/ProductMasterConsoleI18nTest.php` → **319 passed / 319, 1541 assn, 0 fail (~58s)** → `docker rm -f pg`. The Catalog i18n file is appended because it DEFINES the shared `scanOperatorConsoleHardcodedSinks` helper the Parties i18n tests CALL (Parties folder alone fatals). `CustomerHoldsChainTest` held PG-safe exactly as designed (loose `actor_id` `toEqual`, events by NAME+envelope not jsonb byte-compare, `DatabaseMigrations` so each action's own `DB::transaction` commits).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (5.1 baseline, unchanged — 5.2 touched no code): SQLite 1442/1442 (7950 assn); phpstan max 0; pint + pint --test clean; validate --strict valid.** NEW: **PG17 319/319 (1541 assn) on the Parties OperatorPanel folder + Catalog i18n helper** — production-engine gate passed.
- **Full suite OOMs at PHP's default 128 MB** → run `php -d memory_limit=-1 vendor/bin/pest`. `--filter` + `phpstan` fine at default; a scoped folder run (e.g. one OperatorPanel module) is fine with `-1`.

## Active Change & Next Task
- **Active: `operator-console-parties-holds` (APPROVED). 10/12 done.**
- **Next: task 6.1** — quality gates: `vendor/bin/pint` clean + `vendor/bin/phpstan analyse` at max green INCLUDING arch tests `NoEloquentWriteInOperatorPanelRule` + `ModuleBoundariesTest` **UNCHANGED** (do NOT widen the allowlist — `Parties\Enums`/`Actions` ride the existing operand-enum + `{Models,Actions,Enums}`-prefix carve-out, ADR 2026-06-21) + full `php -d memory_limit=-1 vendor/bin/pest` green. Assert the git diff vs `main` touches no `spec/**`, no `openspec/specs/**`, adds no composer dep, no migration.
- **Then 6.2 (LAST):** `openspec validate operator-console-parties-holds --strict` + memlog + overwrite hot.md + consolidate durable patterns into progress.md `## Codebase Patterns` (non-relation per-row-action table vehicle; bespoke-action-reusing-`surfaceLifecycleOutcome` multi-operand/Hold-id recipe; "operand-enum carve-out exercised, `ModuleBoundariesTest` needs no widening") → emit `<promise>CHANGE_COMPLETE</promise>` (do NOT archive/merge — human does §2.7 closure).

## Blockers & Decisions Needed
- **None blocking.** PG17 gate is green. 6.1 is a re-run + diff audit (no new code expected); 6.1/6.2 are verification + memory only. The §2.7 merge/push/archive is the HUMAN step after `CHANGE_COMPLETE` (loop never pushes).

## Open Patterns
- **NEW (→ progress.md next iter):** the PG17 ritual is TASK-SCOPED inside the loop — run JUST the module's OperatorPanel folder + the i18n file DEFINING `scanOperatorConsoleHardcodedSinks` (currently Catalog's); `docker rm -f pg` BEFORE `docker run` (a crashed iteration can leave the container up). All prior landmines stand: heterogeneous closing-chain envelope (Hold verbs `entity_type=Hold` / coupling `entity_type=Customer` — disambiguate by `entity_id`); Filament hidden record-action closure unreachable via any test helper (reject = domain `toThrow`, not a widget notification — lessons.md 2026-06-22); key per-row off typed `$record->id` (not `getKey()` → `cast.int`); state enums (`HoldStatus`) cast-only even in predicates.
