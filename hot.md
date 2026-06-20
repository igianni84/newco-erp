---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.

## Last Updated
**2026-06-20 (ralph loop — `operator-console-catalog-spine` task 5.2 DONE; CHANGE COMPLETE 10/10).** The change's **closing proof + cross-engine PG17 close**: one feature test (`SpineConsoleChainTest`, 250 assertions) driving the WHOLE Module-0 spine to `active` THROUGH the seven Filament consoles as a human demo — parent-before-child, every create/submit/activate/retire/reopen via Create/View pages (NOT raw actions). Tree: 2 Formats + 1 Case Config → Master → Variant → 2 PRs (Variant × each Format) → {Sellable over PR1+CaseConfig, Composite over [PR1,PR2]}. Exercises EVERY divergence inline: **cascade ordering PROVEN** (Variant created under a `reviewed` Master → activate BLOCKED → activate Master → activate Variant); the **two create form-errors** (dup PR; `<2` Composite); the **two retire reference-integrity blocks** (the active Sellable SKU references BOTH PR1 + Case Config → retiring either rejected, stays `active`); **reopen** (Composite). Emergent-set proofs: `%Reviewed%`/`%Reopened%` count 0, the 19-event `name` set `toEqualCanonicalizing`, a `foreach` asserting `module=catalog` + `actor_role=NewcoOps` + non-null `actor_id` on EVERY event (producers seeded event-free). **CONTEXT.md `Operator console` term already present** (lines 472–473) — no edit.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 1138/1138 SQLite (6442 assertions) — +1 vs the 1137 baseline (the new closing-chain test).** phpstan 0; pint clean. **PG17 ✓ (cross-engine close):** docker `postgres:17`, the ENTIRE `tests/Feature/Modules/OperatorPanel` + `tests/Feature/Modules/Catalog` + `tests/Unit/Modules/Catalog` + `tests/Architecture` = **429/429, 3191 assertions, exit 0** (+ the chain test re-run on PG = 1/1). composer/lock diff vs main empty; no migrations; ONE new file (`tests/.../SpineConsoleChainTest.php`), no protected/app/lang change.
- **Run-cmd gotcha:** the FULL suite OOMs under bare `php artisan test` (128M). Run `php -d memory_limit=-1 vendor/bin/pest` and `php -d memory_limit=-1 vendor/bin/phpstan analyse`. PG17: docker `postgres:17`, prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **ACTIVE:** `operator-console-catalog-spine` — **10/10 tasks done, `<promise>CHANGE_COMPLETE</promise>` emitted** (`APPROVED` present). All SIX spine consoles + the Master retrofit onto the shared kit, i18n-guarded, closed end-to-end on SQLite + PG17.
- **Next: HUMAN review → archive → merge** (the loop does NOT archive/merge — humans do). On archive, the `YYYY-MM-DD-<name>` dir date stamps the knowledge-confirmation dates for the domains this change reinforced. Then the next spec slice (`spec-to-change`) — candidate: a Parties operator console reusing the kit.

## Blockers & Decisions Needed
- None. **`main` is LOCAL-ONLY — not pushed.** Humans push; the loop only commits locally.

## Open Patterns
- **READ `progress.md` `## Codebase Patterns` before any reuse** — the operator-console kit (5 pieces) + standalone/hierarchical/leaf/N-constituent recipes + the kit-key i18n-completeness pattern + **the full-spine closing-chain shape (5.2, top): every console driven through its Filament pages, uniform `spineChain{Submit,Activate}` helpers (`Livewire::test()`'s facade `@method` is untyped → a `string $viewPage` param is phpstan-clean), event-free producer seed, cascade-ordering-proven idiom, `toEqualCanonicalizing` emergent-set proof.** Reusable for any future module operator console.
- The kit is the template for nine modules — the next console (Parties / A–E) reuses `Console/{OperatorConsoleResource,OperatorConsoleCreateRecord,OperatorConsoleViewRecord,Concerns/SurfacesDomainActions}` verbatim; per-entity it adds only the resource + 3 pages + lang block + 2 tests + the closing chain.
