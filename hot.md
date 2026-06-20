---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.

## Last Updated
**2026-06-20 (ralph §3 of `operator-console-parties-producer` — Producer status lifecycle landed).** Tasks 3.1/3.2 done: `ViewProducer` rewritten from the bare read-only `ViewRecord` into the **non-catalog lifecycle View page** (the trait-reuse template the rest of Parties reuses, ADR 2026-06-20). It now `extends \Filament\Resources\Pages\ViewRecord` + `use SurfacesDomainActions`, `i18nKey() => 'producer'`, and assembles its OWN `getHeaderActions()` with the two **form-less, no-confirmation** status verbs — `activate`→`activated`→`ActivateProducer` and `retire`→`retired`→`RetireProducer`, each via `app(...)->handle($this->recordOf(Producer::class,$r)->id)` (Parties Actions take `int $id`, not the model — D4). It does NOT extend the catalog `OperatorConsoleViewRecord` (D1). Added `producer.actions.{activate,retire}` + `producer.notifications.{activated,retired,action_failed}` to en/it. Retire's Club-sunset cascade is the domain action's; the console just invokes it.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Branch `ralph/operator-console-parties-producer` GREEN: full suite 1154/1154 SQLite (6596 assn), phpstan 0, pint clean, `openspec validate --strict` OK, composer diff vs main empty.** (+16 tests vs the 1138 `main` baseline; +8 this iteration.) PG17 not yet re-run this change — the PG17 full gate is the §6 closing-chain task (6.1), per the catalog precedent.
- **Run-cmd gotcha:** full suite OOMs under bare `php artisan test` (128M). Use `php -d memory_limit=-1 vendor/bin/pest` and `php -d memory_limit=-1 vendor/bin/phpstan analyse`. PG17: docker `postgres:17`, prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **Active: `operator-console-parties-producer`** (APPROVED). 7 of 11 tasks done (§1+§2+§3 complete).
- **Next: §4 — task 4.1/4.2 (Producer KYC management on `ViewProducer`).** APPEND four KYC verbs to the SAME `getHeaderActions()` array via `lifecycleAction` (form-less): `requireKyc`→`kyc_required`→`RequireProducerKyc`; `waiveKyc`→`kyc_waived`→`WaiveProducerKyc`; `verifyKyc`→`kyc_verified`→`RecordProducerKycVerified`; `rejectKyc`→`kyc_rejected`→`RecordProducerKycRejected` (each `->handle($this->recordOf(Producer::class,$r)->id)`). Add `actions.{require_kyc,waive_kyc,verify_kyc,reject_kyc}` + `notifications.{kyc_required,kyc_waived,kyc_verified,kyc_rejected}` to `lang/{en,it}`. Test `ProducerKycConsoleTest`: each verb moves `kyc_status`, leaves `status` unchanged, records **NO** domain event (audit-only) + places no Hold; illegal KYC transition → `action_failed`; KYC↔activation gate end-to-end (pending blocks activate → verify → activate succeeds).
- Then §5 (kit-key i18n completeness), §6 (PG17 closing-chain). Read `design.md` (D1–D8) + `progress.md` Codebase Patterns each iteration.

## Blockers & Decisions Needed
- None. Reminder: ralph commits locally; **humans push**. No open ADR gate is crossed by this change.

## Open Patterns
- **Non-catalog View page = the Parties template (§3).** `ViewRecord` + `SurfacesDomainActions` + `i18nKey()` + own `getHeaderActions()`; verbs form-less unless the domain has a notes form / SoD. Closures pass `->id` (Parties Actions take the id). Rejections → `action_failed` via the trait's `RuntimeException` catch; name exceptions in PROSE backticks (Pint re-adds `{@see}` imports, but a same-module `OperatorConsoleViewRecord` `{@see}` import is fine — not a boundary break). **Console lifecycle test:** `DatabaseMigrations` + `Livewire::test(Page, ['record'=>$id])->callAction(verb)`; loose `toEqual` for the uncast `actor_id`/`causation_id` bigints (PG returns numeric strings), strict `toBe` for `entity_id`/`correlation_id` strings. Consolidated in the change's Codebase Patterns; Club/Agreement/Customer reuse it.
