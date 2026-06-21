---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-21
---

# Hot Cache

## Last Updated
**2026-06-21 (`operator-console-parties-supply-side` — tasks 8.1–8.2 of 19 DONE → ProducerAgreement CREATE surface).** Group 8 = the write-through Create form (the page's `createViaAction`/`createRejectionField` were already shipped real in group 7, so NO page change). Added `ProducerAgreementResource::form()`: producer required model-driven `Select` (`Producer::query()`); club OPTIONAL model-driven `Select` (`Club::query()`, blank=Producer-wide); `term_start`/`term_end` `DatePicker` (**FIRST DatePicker in the codebase** — dehydrates `'Y-m-d'`, page parses via `CarbonImmutable::parse`); `settlement_cadence` `TextInput`; **NO status, NO operand enum, NO Money** (D7 — ids/dates/string only). Two private model-driven option helpers (`producerOptions`/`clubOptions`, `#id · label`, within `{Models}`). EN/IT `producer_agreement.fields.{producer,club,term_start,term_end}` (all IT≠EN). `ProducerAgreementCreateConsoleTest` 5/5.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **GREEN after group 8:** full SQLite suite **1276/1276** (7157 assn, +5); `ProducerAgreementCreateConsoleTest` 5/5 (45 assn); `ModuleBoundariesTest` 3/3 (189 assn — admits `Producer`+`Club` in the Resource, the `{Models}` carve-out, no `Parties\Enums` — D7); phpstan 0; pint + pint --test clean; `openspec validate … --strict` valid; composer diff vs `main` empty. (Group 8 is DatabaseMigrations/SQLite — PG17 is scoped to groups 6+11 only.)
- **Run-cmd gotchas:** full suite OOMs under bare `php artisan test` → use `php -d memory_limit=-1 vendor/bin/pest` / `… phpstan analyse`. **i18n tests reuse the helper `scanOperatorConsoleHardcodedSinks` (in `…/Catalog/ProductMasterConsoleI18nTest.php`) → run via `--filter`/full suite, NOT a bare path; APPEND that Catalog file for a folder-wide PG17 run.** PG17: docker container `newco-pg17-test` (Up), prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **Active:** `operator-console-parties-supply-side` (15/19 done: 1.1, 2.1–2.3, 3.1–3.2, 4.1–4.2, 5.1, 6.1, 7.1–7.3, 8.1–8.2). Branch `ralph/operator-console-parties-supply-side`.
- **Next: group 9 (9.1–9.2).** Fill `ViewProducerAgreement` — `extends ViewRecord` + `use SurfacesDomainActions`, `i18nKey()='producer_agreement'`, two form-less verbs via `lifecycleAction` (no `confirmationKey`, D3): `activate`→`activated`→`app(ActivateProducerAgreement::class)->handle(…->id)`; `terminate`→`terminated`→`app(TerminateProducerAgreement::class)->handle(…->id)`; **NO `supersede` verb** (supersession is `ActivateProducerAgreement`'s side-effect — D8). + `actions.{activate,terminate}` & `notifications.{activated,terminated,action_failed}` i18n + `ProducerAgreementLifecycleConsoleTest` (activate draft→active 1 `ProducerAgreementActivated` no supersede; prior-active OR-branch → `ProducerAgreementSuperseded` carries the activate event id as `causation_id`; terminate active→terminated; out-of-state → `action_failed`; assert NO `supersede`/`submit`/`reject`/`reopen` action). **Verify the `ActivateProducerAgreement`/`TerminateProducerAgreement` action class names + the superseded-event `causation_id` wiring before coding.** Then 10 i18n / 11 PG17 chain.

## Blockers & Decisions Needed
- **None.** Cleanup: `newco-pg17-test` docker container is running (`docker rm -f newco-pg17-test` when done with the change).

## Open Patterns
- **DatePicker (NEW):** `Filament\Forms\Components\DatePicker` dehydrates `'Y-m-d'`; pre-shipped `createViaAction` parses via `CarbonImmutable::parse` + `is_string` guard. Test `fillForm(['term_start'=>'2026-03-01'])` round-trips to `->toDateString()` (date column, no TZ drift).
- **Nullable `belongsTo` display read (lessons.md 2026-06-21).** Only phpstan-clean shape: `$x = $record->rel; return $x === null ? __('…') : $x->attr;`. Groups 9/11 read the same `club` relation — reuse it.
- **Lifecycle verb template twice-proven (Producer+Club).** `ViewClub` = `ViewProducer` minus KYC, `activate`→`sunset`/`close`; verb→successKey not always identity (`close`→`closed`). Group 9 = activate/terminate, NO supersede verb.
- **i18n completeness (group 10) & closing-chain (group 11)** — full recipes in progress.md Codebase Patterns. Group 10 watch: filter `producer_agreement.`, scope `ProducerAgreementResource`, `producer_wide`, nullable `club`. Group 11: supersession OR-branch + `causation_id`.
- **Operand vs state enum split (D2/D7):** create constructs operand enums; state enums render via cast, never imported. ProducerAgreement create has NEITHER operand enum nor Money.
