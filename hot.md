---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-21
---

# Hot Cache

## Last Updated
**2026-06-21 (`operator-console-parties-supply-side` — tasks 9.1–9.2 of 19 DONE → ProducerAgreement STATUS lifecycle).** Filled the bare `ViewProducerAgreement` stub: now `extends ViewRecord` + `use SurfacesDomainActions` (NOT catalog `OperatorConsoleViewRecord`, D1), `i18nKey()='producer_agreement'`, `getHeaderActions()` = two form-less `lifecycleAction`s (no `confirmationKey`, D3): `activate`→`activated`→`app(ActivateProducerAgreement::class)->handle($this->recordOf(ProducerAgreement::class,$r)->id)`; `terminate`→`terminated`→`app(TerminateProducerAgreement::class)->handle(…->id)`. **NO `supersede` verb** (D8 — supersession is `ActivateProducerAgreement`'s INLINE side-effect, never an operator action). EN/IT `producer_agreement.actions.{activate,terminate}` + new `notifications.{activated,terminated,action_failed}` (all IT≠EN). `ProducerAgreementLifecycleConsoleTest` 6/6 — incl. the supersession OR-branch (`causation_id` linkage) + terminate-no-Producer-cascade.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **GREEN after group 9:** full SQLite suite **1282/1282** (7223 assn, +6); `ProducerAgreementLifecycleConsoleTest` 6/6 (66 assn); `ModuleBoundariesTest` 3/3 (189 assn — admits `ActivateProducerAgreement`+`TerminateProducerAgreement`+`ProducerAgreement` in the page, {Models, Actions}, NO `Parties\Enums` on the lifecycle path); phpstan 0; pint + pint --test clean; `openspec validate … --strict` valid; composer diff vs `main` empty. (Group 9 is DatabaseMigrations/SQLite — PG17 is scoped to groups 6+11 only.)
- **Run-cmd gotchas:** full suite OOMs under bare `php artisan test` → use `php -d memory_limit=-1 vendor/bin/pest` / `… phpstan analyse`. **i18n tests reuse the helper `scanOperatorConsoleHardcodedSinks` (in `…/Catalog/ProductMasterConsoleI18nTest.php`) → run via `--filter`/full suite, NOT a bare path; APPEND that Catalog file for a folder-wide PG17 run.** PG17: docker container `newco-pg17-test` (Up), prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **Active:** `operator-console-parties-supply-side` (17/19 done: 1.1, 2.1–2.3, 3.1–3.2, 4.1–4.2, 5.1, 6.1, 7.1–7.3, 8.1–8.2, 9.1–9.2). Branch `ralph/operator-console-parties-supply-side`.
- **Next: group 10 (10.1).** `ProducerAgreementConsoleI18nTest` — the FIVE-guard kit-key-completeness pattern (progress.md Codebase Patterns §"Console i18n"; thrice-proven Producer+Club). Filter IT⊆EN by `str_starts_with($dotKey,'producer_agreement.')` (trailing dot load-bearing); sink scan scoped `ProducerAgreementResource`; reuse `scanOperatorConsoleHardcodedSinks` behind `function_exists` (run via `--filter`, NOT a bare path). **Kit** = label/plural_label + columns.{producer,club,status,term_start,term_end,version} + producer_wide + actions.{activate,terminate,create} + notifications.{activated,terminated,action_failed} + fields.{producer,club,term_start,term_end,settlement_cadence} → recompute test count (kit differs from Club's 21). Then group 11 = PG17 closing chain.

## Blockers & Decisions Needed
- **None.** Cleanup: `newco-pg17-test` docker container is running (`docker rm -f newco-pg17-test` when done with the change).

## Open Patterns
- **Lifecycle verb template now THRICE-proven (Producer+Club+ProducerAgreement).** `ViewProducerAgreement` = `ViewClub` with `sunset`/`close` → `activate`/`terminate`. Verb→successKey not identity (`activate`→`activated`, `terminate`→`terminated`). All three Parties view pages `extends ViewRecord` + `use SurfacesDomainActions` (trait level, NOT catalog base — D1).
- **Supersession = side-effect not verb (D8).** `ActivateProducerAgreement` records `ProducerAgreementActivated` first, then if a prior active in the NULL-safe `(producer_id,club_id)` scope exists records `ProducerAgreementSuperseded` INLINE with `causationId=activated->id`. Group 11 chain reuses this OR-branch (activate B in A's scope → A superseded). Test fixture: ONE shared Producer, prior+draft both `club_id=null` (two bare factories spawn separate Producers — no shared scope).
- **Nullable `belongsTo` display read (lessons.md 2026-06-21):** `$x=$record->rel; return $x===null ? __('…') : $x->attr;`. Group 11 reads the same `club` relation.
- **Group 10 i18n & group 11 closing-chain** full recipes in progress.md Codebase Patterns.
