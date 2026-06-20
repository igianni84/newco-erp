---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.

## Last Updated
**2026-06-20 (ralph — `operator-console-catalog-master` COMPLETE: 11/11, 6.2 green → CHANGE_COMPLETE).** The change's closing integration proof landed: a single self-contained feature test `ProductMasterConsoleChainTest` (1 test, 136 assertions) drives the WHOLE Product Master console slice end-to-end as a human demo (create + dedup-reject → submit(A) → self-approval-reject(A) → activate(B) → single-retire-preserves-child → cascade-retire-sibling-subtree → reopen → producer-gate-blocked path) and asserts the emergent event-SET: `%Reviewed%`/`%Reopened%` = 0, the `ProductMaster` event names `toEqualCanonicalizing({Created×3, Activated×2, Retired×2})`, the cascade's 4 `*Retired` in parent-before-child order, and `actor_role: NewcoOps` on EVERY domain event (producers seeded EVENT-FREE via `ProducerState::create()` → a pure operator-write event table). NO production code changed (the console was done after 2–6.1); 6.2 is the proof + the cross-engine close.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **996/996 green** (5115 assertions, +1 vs 6.1). phpstan 0; pint clean. composer untouched; no migrations; no protected files. 1 new file: `ProductMasterConsoleChainTest.php`.
- **PG17 ✓ (the cross-engine close):** docker `postgres:17` ran the ENTIRE `tests/Feature/Modules/OperatorPanel` + `tests/Feature/Modules/Catalog` + `tests/Architecture` = **244/244, 1702 assertions, exit 0**. The chain, the cascade ordering, the gate's event-free projection read, and the arch(1.2)/boundary(1.3) tests are all clean on PG.
- Run cmds: full suite `php -d memory_limit=-1 vendor/bin/pest` (bare `php artisan test` OOMs at 128M). PG: `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=secret php -d memory_limit=512M vendor/bin/pest <paths>` (start+teardown per run).

## Active Change & Next Task
- **`operator-console-catalog-master` is COMPLETE — all 11 tasks `- [x]`, `openspec validate … --strict` green.** Emitted `<promise>CHANGE_COMPLETE</promise>`. **Do NOT archive or merge — humans do that after review** (then `openspec archive`). On branch `ralph/operator-console-catalog-master`; commits are local (never push).
- **Next change (human-selected after merge):** the proposal names `operator-console-catalog-spine` (the other six catalog spine entities — Variant/Format/PR/CaseConfig/SKU/Composite) and a Parties console. The spine change is where the shared base-Resource / operator-action abstraction is meant to emerge (design L9 — deliberately NOT extracted in this change). Reuse the console Codebase Patterns (read-only resource, write-through create/lifecycle action, sink-anchored i18n scan, closing-chain test).

## Blockers & Decisions Needed
- None. Change is done and green on both engines.

## Open Patterns
- **Closing-chain console integration test (6.2 — progress.md Codebase Patterns; reuse for the spine close):** ONE hermetic feature test, uniquely-prefixed self-contained helpers (no sibling-file helper calls — Pest's single-file run won't load them), EVENT-FREE producer seeding via `ProducerState::create()` (the row the gate reads) for a pure event table, `whereNotIn(ids-before-action)` to isolate a multi-entity action's events (avoids the `(int) max()` phpstan `cast.int` trap), three-way emergent-set assertion (`%Reviewed%`=0 / `toEqualCanonicalizing` the entity set / `foreach` actor_role in separate `expect()`s).
- Console surface = exactly {Models, Actions}; catch domain rejections by base type, render enums via instance (no `Catalog\Exceptions`/`Enums` import). Filament-5 read-only resource / write-through create page / write-through lifecycle action + confirmation-affordance shapes all in progress.md Codebase Patterns.
