---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.

## Last Updated
**2026-06-20 (ralph §4 of `operator-console-parties-producer` — Producer KYC management landed).** Tasks 4.1/4.2 done: the four KYC verbs APPENDED to the existing `ViewProducer::getHeaderActions()` array, proving the §3 `SurfacesDomainActions::lifecycleAction()` recipe scales to a SECOND FSM on one page. `requireKyc`→`kyc_required`→`RequireProducerKyc`; `waiveKyc`→`kyc_waived`→`WaiveProducerKyc`; `verifyKyc`→`kyc_verified`→`RecordProducerKycVerified`; `rejectKyc`→`kyc_rejected`→`RecordProducerKycRejected` — all form-less, each `app(...)->handle($this->recordOf(Producer::class,$r)->id)` (D4). KYC is **AUDIT-ONLY** (records NO domain event, places NO Hold) and SEPARATE from the status FSM (never moves `status`); rejections throw `IllegalKycTransition` (a `RuntimeException`) → the SAME base-type catch surfaces `action_failed`, zero extra wiring. Added `producer.actions.{require_kyc,waive_kyc,verify_kyc,reject_kyc}` + `notifications.{kyc_required,kyc_waived,kyc_verified,kyc_rejected}` to en/it.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Branch `ralph/operator-console-parties-producer` GREEN: full suite 1167/1167 SQLite (6720 assn), phpstan 0, pint clean, `openspec validate --strict` OK, composer diff vs main empty.** (+29 tests vs the 1138 `main` baseline; +13 this iteration.) PG17 not yet re-run this change — the PG17 full gate is the §6 closing-chain task (6.1), per the catalog precedent.
- **Run-cmd gotcha:** full suite OOMs under bare `php artisan test` (128M). Use `php -d memory_limit=-1 vendor/bin/pest` and `php -d memory_limit=-1 vendor/bin/phpstan analyse`. `openspec` is on PATH (NOT `vendor/bin/openspec`). PG17: docker `postgres:17`, prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **Active: `operator-console-parties-producer`** (APPROVED). 9 of 11 tasks done (§1+§2+§3+§4 complete).
- **Next: §5 — task 5.1 (i18n kit-key completeness, EN/IT).** Add `ProducerConsoleI18nTest.php` enumerating the kit-contract keys this console resolves by string concatenation (so they never appear as source literals): resource `label`/`plural_label`/`columns.{status,kyc_status,version}`; the six `actions.{activate,retire,require_kyc,waive_kyc,verify_kyc,reject_kyc}`; `notifications.{activated,retired,kyc_required,kyc_waived,kyc_verified,kyc_rejected,action_failed}`. Assert `Lang::has("operator_console.producer.{$suffix}", 'en', false)` for each (a removed key fails it). Add an IT-differs dataset (keys whose `it` value differs from `en`). Reuse the predecessor's suite-wide `scanOperatorConsoleHardcodedSinks` with a `function_exists` guard over `…/Resources/Parties/ProducerResource*` (zero hardcoded operator-facing strings). The full key list already exists in EN+IT — §5 only enumerates/asserts.
- Then §6 (PG17 closing-chain, task 6.1). Read `design.md` (D1–D8) + `progress.md` Codebase Patterns each iteration.

## Blockers & Decisions Needed
- None. Reminder: ralph commits locally; **humans push**. No open ADR gate is crossed by this change.

## Open Patterns
- **One View page, two FSMs (§4).** `getHeaderActions()` mixes status verbs (event-emitting, cascade) and KYC verbs (audit-only, no event/Hold) — same `lifecycleAction` shape, same `RuntimeException` base-type catch. **Verb id ≠ catalog verb id** (`rejectKyc` ≠ `reject`) so §3's `assertActionDoesNotExist('reject')` scope-guard survives the append. KYC test recipe = the lifecycle one (DatabaseMigrations + `Livewire::test(ViewProducer,['record'=>$id])->callAction(verb)`) but assert `kyc_status` moved, `status` unchanged, `DomainEvent::count()` == baseline (audit-only), `Hold::count()` 0. Consolidated in the change's Codebase Patterns; Club/Agreement/Customer reuse it.
