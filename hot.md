---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-21
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.

## Last Updated
**2026-06-21 (`operator-console-parties-supply-side` — group 2 / tasks 2.1·2.2·2.3 of 19 DONE).** Ralph loop on `ralph/operator-console-parties-supply-side`. This change builds the **Club** + **ProducerAgreement** operator consoles (the supply-side trio's last two) over shipped Parties Actions — pure operator surface, no domain code. Group 2 shipped the **Club read surface** as one green unit (the `getPages()` boot coupling forces Resource + 3 pages together): `ClubResource` (read columns/infolist, own `status` + `registration_flow_type` cast-rendered columns, NO `lifecycleStateColumn()`), `ListClubs` (header create-LINK), the **real** `CreateClub` (constructs `ClubRegistrationFlowType` operand enum + `Money` fee → `CreateClubAction`; first prod code to exercise the group-1 Enums carve-out), bare `ViewClub`, EN+IT `operator_console.club.*`.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **GREEN after group 2:** full SQLite suite **1211/1211** (6879 assn, +5), `ClubResourceTest` 5/5 (20 assn), `ModuleBoundariesTest` 3/3 (189 assn), phpstan 0, pint clean, `openspec validate operator-console-parties-supply-side --strict` valid, composer diff vs `main` empty.
- **Run-cmd gotchas:** full suite OOMs under bare `php artisan test` (128M) → use `php -d memory_limit=-1 vendor/bin/pest` / `… phpstan analyse`. i18n tests reuse a top-level helper (`scanOperatorConsoleHardcodedSinks`, in `…/Catalog/ProductMasterConsoleI18nTest.php`) → run via `--filter`/full suite, NOT a bare file/dir path (false-red); for a folder-wide PG17 run APPEND that Catalog file. PG17: docker `postgres:17` container `newco-pg17-test`, prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **Active:** `operator-console-parties-supply-side` (4/19 done: 1.1, 2.1, 2.2, 2.3). Branch `ralph/operator-console-parties-supply-side`.
- **Next: group 3 (3.1/3.2).** `CreateClub`'s `createViaAction` + operand-enum/Money/`createRejectionField` are ALREADY DONE (shipped in group 2 per design.md "scaffold Create… with its real createViaAction"). 3.1's remaining work = add `ClubResource::form()` (display_name TextInput; `producer_id` Select off `Producer::query()`; `registration_flow_type` Select; optional fee `amount`+`currency`; `generates_credit`/`invite_only` Toggles; NO `status`). 3.2 = `club.fields.{amount,currency}` i18n (`actions.create` already added). Then `ClubCreateConsoleTest`. Group order: 2 Club-read DONE → 3 Club-create → 4 Club-lifecycle → 5 Club-i18n → 6 Club-PG17 → 7–11 ProducerAgreement. Recipe = predecessor `archive/2026-06-20-operator-console-parties-producer/progress.md` Codebase Patterns + design D1–D12.

## Blockers & Decisions Needed
- **None.** Cleanup: `newco-pg17-test` docker container may be running (`docker rm -f newco-pg17-test`).

## Open Patterns
- **Read-surface = one green unit** (getPages boot coupling); the **real** Create page IS the scaffold (`OperatorConsoleCreateRecord` has 2 abstract methods; a throw-stub is hacky). Consolidated in progress.md Codebase Patterns.
- **Verify model attrs, not task prose:** `Producer` has `name` (not `display_name`); task 7.1 repeats the same `producer->display_name` error → use `producer->name` (lessons.md 2026-06-21). Booleans → `IconEntry->boolean()`.
- **Operand vs state enum split (D2/D7):** console constructs **operand** enums (`ClubRegistrationFlowType`, now imported in `CreateClub`); **state** enums (`ClubStatus`/`ProducerAgreementStatus`) stay rendered via the cast, never imported.
