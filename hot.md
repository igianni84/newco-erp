---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-21
---

# Hot Cache

## Last Updated
**2026-06-21 (`operator-console-parties-supply-side` — tasks 7.1–7.3 of 19 DONE → ProducerAgreement READ surface).** Pure operator surface over shipped Parties Actions, no domain code. Group 7 shipped the THIRD Parties console as ONE green unit (`getPages()` boot coupling): `ProducerAgreementResource` (read-bind `ProducerAgreement`; own `status` badge via cast; list = producer/club/status/term_start/term_end/version; infolist adds settlement_cadence; nullable `club` → `clubLabel()` = `display_name` or `producer_wide` placeholder) + `ListProducerAgreements` (header create-LINK) + the **real** `CreateProducerAgreement` (ids/dates/string narrowing, NO operand enum — D7; `CarbonImmutable::parse` for term DatePickers; dormant until 8.1's form) + bare `ViewProducerAgreement` (verbs 9.1) + EN/IT `producer_agreement.*`.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **GREEN after group 7:** full SQLite suite **1271/1271** (7112 assn, +6); `ProducerAgreementResourceTest` 6/6; `ModuleBoundariesTest` 3/3 (189 assn); phpstan 0; pint + pint --test clean; `openspec validate … --strict` valid; composer diff vs `main` empty. (Group 7 is RefreshDatabase/no-DB — PG17 is scoped to groups 6+11 only.)
- **Run-cmd gotchas:** full suite OOMs under bare `php artisan test` → use `php -d memory_limit=-1 vendor/bin/pest` / `… phpstan analyse`. **i18n tests reuse the helper `scanOperatorConsoleHardcodedSinks` (in `…/Catalog/ProductMasterConsoleI18nTest.php`) → run via `--filter`/full suite, NOT a bare path; APPEND that Catalog file for a folder-wide PG17 run.** PG17: docker container `newco-pg17-test` (Up), prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **Active:** `operator-console-parties-supply-side` (13/19 done: 1.1, 2.1–2.3, 3.1–3.2, 4.1–4.2, 5.1, 6.1, 7.1–7.3). Branch `ralph/operator-console-parties-supply-side`.
- **Next: group 8 (8.1–8.2).** `createViaAction` already shipped real, so 8.1 = `ProducerAgreementResource::form()` ONLY (producer required Select; club optional Select blank=Producer-wide; term_start/term_end `DatePicker`; settlement_cadence `TextInput`; **NO status/operand enum** — D7) + 8.2 = create `fields.*` i18n (`actions.create` already added). Test `ProducerAgreementCreateConsoleTest` (valid→`draft`+1 `ProducerAgreementCreated`; blank club→null; bad Producer→`MissingAgreementProducer` on `producer_id`; **two drafts same scope both succeed**; no `status` field). Order: 8 create → 9 lifecycle activate/terminate (**no supersede verb** — D8) → 10 i18n → 11 PG17 chain (supersession OR-branch, `causation_id`).

## Blockers & Decisions Needed
- **None.** Cleanup: `newco-pg17-test` docker container is running (`docker rm -f newco-pg17-test` when done with the change).

## Open Patterns
- **Nullable `belongsTo` display read (NEW, lessons.md 2026-06-21).** Only phpstan-clean shape: `$x = $record->rel; return $x === null ? __('…') : $x->attr;`. `$record->rel?->attr` reds `nullsafe.neverNull`; FK-column guard `rel_id === null` then `rel->attr` reds `property.nonObject`. Groups 9/11 read the same `club` relation — reuse it.
- **Read-surface group = ONE green unit (`getPages()` boot coupling).** Resource (read cols/infolist + own `status` badge via cast, NEVER `lifecycleStateColumn()`/`Parties\Enums`) + List (header create-LINK, never `CreateAction`) + the REAL Create (`OperatorConsoleCreateRecord`'s 2 abstract methods → clean scaffold IS the real page) + bare View. (Full pattern in progress.md Codebase Patterns.)
- **Closing-chain test (group 11) & i18n completeness test (group 10)** — both twice-proven, full recipes in progress.md Codebase Patterns. Group 10 watch: `columns.club` IT `Club di riferimento` ≠ EN `Club`; `producer_wide`. Group 11: supersession OR-branch + `causation_id`.
- **Operand vs state enum split (D2/D7):** create constructs operand enums; state enums render via cast, never imported. ProducerAgreement create has NEITHER operand enum nor Money (ids/dates/string only).
