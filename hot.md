---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-21
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.

## Last Updated
**2026-06-21 (`operator-console-parties-supply-side` — group 6 / task 6.1 of 19 DONE → whole Club console COMPLETE).** Ralph loop builds the **Club** + **ProducerAgreement** operator consoles over shipped Parties Actions — pure operator surface, no domain code. Group 6 shipped **`ClubConsoleChainTest.php`**, the Club console's CLOSING integration proof: one `it()` driving a Club through the WHOLE slice via the **pages** (`CreateClub`→`ViewClub` sunset→close). Asserts the emergent set `->toEqualCanonicalizing(['ClubCreated','ClubSunset','ClubClosed'])`, `toHaveCount(3)` + `foreach` envelope (`module==='parties'`/`actor_role===NewcoOps`/non-null `actor_id`), representative `actor_id` `toEqual` operator on BOTH surfaces. Test-only — every page/key shipped groups 2–5.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **GREEN after group 6:** full SQLite suite **1265/1265** (7092 assn, +1); `ClubConsoleChainTest` 1/1; **PG17 folder-wide 142/142**; phpstan 0; pint + pint --test clean; `openspec validate … --strict` valid; composer diff vs `main` empty.
- **Run-cmd gotchas:** full suite OOMs under bare `php artisan test` (128M) → use `php -d memory_limit=-1 vendor/bin/pest` / `… phpstan analyse`. **i18n tests reuse the top-level helper `scanOperatorConsoleHardcodedSinks` (in `…/Catalog/ProductMasterConsoleI18nTest.php`) → run via `--filter`/full suite, NOT a bare path (false-red); APPEND that Catalog file for a folder-wide PG17 run.** PG17: docker `postgres:17` container `newco-pg17-test` (Up), prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **Active:** `operator-console-parties-supply-side` (10/19 done: 1.1, 2.1–2.3, 3.1–3.2, 4.1–4.2, 5.1, 6.1). Branch `ralph/operator-console-parties-supply-side`.
- **Next: group 7 (7.1–7.3).** The THIRD Parties console — `ProducerAgreementResource` read surface as ONE green unit (the `getPages()` boot coupling): Resource (read-bind `Parties\Models\ProducerAgreement`, `status` badge via cast — NO `Parties\Enums` import; list = producer/club/status/term_start/term_end/version; infolist adds settlement_cadence) + `ListProducerAgreements` (header create-LINK) + scaffold the REAL `CreateProducerAgreement` + bare `ViewProducerAgreement` + resource i18n. **Watch the nullable `club` column** (`$r->club?->display_name ?? __('…producer_wide')`). Order: 7 read → 8 create (NO operand enum/Money — ids/dates/string only, D7) → 9 lifecycle activate/terminate (**no supersede verb** — it's `ActivateProducerAgreement`'s side-effect, D8) → 10 i18n → 11 PG17 chain (supersession OR-branch, `causation_id`). Recipe = predecessor archive Codebase Patterns + this change's progress.md + design D1–D12.

## Blockers & Decisions Needed
- **None.** Cleanup: `newco-pg17-test` docker container is running (`docker rm -f newco-pg17-test` when done with the change).

## Open Patterns
- **Closing-chain integration test (twice-proven: Producer + Club; group 11 repeats).** One `it()`, `DatabaseMigrations`, drive the whole slice through the PAGES, assert the EMERGENT event set (`toEqualCanonicalizing` + `toHaveCount`) + set-wide envelope. **Grep-verify "no projection event leaks" before the count** (projectors write read-model rows, never domain events). Re-instantiate `Livewire::test(View…)` per `callAction`. Loose `toEqual` for PG numeric-string `actor_id`/`causation_id`.
- **Console i18n completeness test (twice-proven; group 10 repeats).** Five guards off `<entity>ConsoleKitKeys()`+`…ItDiffersKeys()`; enumerate literal `__()` keys too (incl. infolist-only). Run via `--filter`. Count = |kit|+|differs|+2+1+1.
- **Lifecycle View page (twice-proven).** `extends ViewRecord` + `use SurfacesDomainActions`; verb→successKey NOT identity (`close`→`closed`); form-less = no `confirmationKey`; out-of-state → base catch → `action_failed`; exceptions PROSE in docblocks (Pint).
- **Operand vs state enum split (D2/D7):** console constructs **operand** enums (create page); **state** enums render via the cast, never imported. Lifecycle/create tests `DatabaseMigrations`; read-only + i18n `RefreshDatabase`/no-DB.
