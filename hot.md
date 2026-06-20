---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.

## Last Updated
**2026-06-20 (ralph §2 of `operator-console-parties-producer` — Producer create surface landed).** Tasks 2.1/2.2 done: the Producer write-through CREATE flow is complete. §1 had already shipped the `CreateProducer` page (real `createViaAction` → `CreateProducerAction`); §2 added the create **form** — which lives on `ProducerResource::form()` (Filament's `CreateRecord` inherits the Resource form; mirrors catalog `FormatResource`), NOT on the page — plus the `producer.fields.{name,region,country}` create labels, and proved the flow with `ProducerCreateConsoleTest`. The form is name/region/country required `TextInput` + appellation/website optional `TextInput` + description optional `Textarea`; it exposes **neither `status` nor `kyc_status`** (both born by the action — `draft`/NULL — and advanced only by the §3/§4 view-page actions, design D6).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Branch `ralph/operator-console-parties-producer` GREEN: full suite 1146/1146 SQLite (6511 assn), phpstan 0, pint clean, `openspec validate --strict` OK, composer diff vs main empty.** (+8 tests vs the 1138 `main` baseline; +3 this iteration.) PG17 not yet re-run this change — the PG17 full gate is the §6 closing-chain task (6.1), per the catalog precedent.
- **Run-cmd gotcha:** full suite OOMs under bare `php artisan test` (128M). Use `php -d memory_limit=-1 vendor/bin/pest` and `php -d memory_limit=-1 vendor/bin/phpstan analyse`. PG17: docker `postgres:17`, prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **Active: `operator-console-parties-producer`** (APPROVED). 5 of 11 tasks done (§1+§2 complete).
- **Next: §3 — task 3.1/3.2 (Producer status lifecycle on `ViewProducer`).** Rewrite `ViewProducer` (currently a bare `ViewRecord`) to `extends \Filament\Resources\Pages\ViewRecord` + `use SurfacesDomainActions` + `i18nKey() => 'producer'` + `getHeaderActions()` returning the two **form-less** status `lifecycleAction`s (D3/D4 — no `confirmationKey`, no SoD affordance): `activate`→`activated`→`app(ActivateProducer::class)->handle($this->recordOf(Producer::class,$r)->id)`; `retire`→`retired`→`RetireProducer`. Uniform `(Model $record, string $notes)` closure (`$notes` unused). Add `actions.{activate,retire}` + `notifications.{activated,retired,action_failed}` to `lang/{en,it}`. Test `ProducerLifecycleConsoleTest` (activate draft→active; retire active→retired incl. Club-sunset **cascade** with `causation_id`; out-of-state→`action_failed`; scope-guards `assertActionDoesNotExist('submit'/'reject'/'reopen')`, no second-actor affordance).
- Then §4 (4 KYC verbs), §5 (kit-key i18n completeness), §6 (PG17 closing-chain). Read `design.md` (D1–D8) + `progress.md` Codebase Patterns each iteration.

## Blockers & Decisions Needed
- None. Reminder: ralph commits locally; **humans push**. No open ADR gate is crossed by this change (operator auth shipped, Filament pinned, read/write boundary decided; no queued consumer, no document storage, no SPA).

## Open Patterns
- **Create form lives on the Resource (`form()`), never the page** — the kit `CreateRecord` inherits it; the page supplies only `createViaAction`/`createRejectionField`. The form omits any FSM attribute (`status`/`kyc_status`) — those are view-page-action-only. Consolidated in the change's Codebase Patterns; Club/Agreement/Customer create surfaces reuse it.
- **Non-catalog kit reuse is at the TRAIT level** (`SurfacesDomainActions` + `OperatorConsoleCreateRecord` + `OperatorConsoleResource` label/version), NOT the catalog `OperatorConsoleViewRecord`/`lifecycleStateColumn()`. The §3 `ViewProducer` is the template the rest of Parties (Club/Agreement/Customer) reuse for bespoke verb sets. Operated-Clubs read uses `pluck('display_name')->implode()` to avoid importing `Club`.
