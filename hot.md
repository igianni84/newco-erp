---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-16
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-16 (ralph iter 4 — task 2.2 of `catalog-lifecycle-approval` DONE).** Built the **shared lifecycle-transition mechanism** (`app/Modules/Catalog/Lifecycle/`) — the foundation every later lifecycle task extends — and wired Product Master's two AUDIT-ONLY transitions: `SubmitProductMasterForReview` (`draft→reviewed`) + `ReopenProductMaster` (`retired→reviewed`), no domain event (Module 0 PRD § 14.2). Three new Lifecycle classes: `HasLifecycleState` (marker interface, one typed read method `lifecycleState()`), `LifecycleTransitionType` (4-edge FSM-map enum: from/to/auditVerb/rejection), `LifecycleTransition` (the service: txn → `lockForUpdate` + `$model->refresh()` → from-state assert → in-place update → one `AuditRecorder` row, basis `catalog-lifecycle`, action `catalog.<entity>.<verb>`). First real module use of `AuditRecorder`. Mechanism = sole `lifecycle_state` writer (D1); models persistence-only (ProductMaster gained `implements HasLifecycleState` + a read getter).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PG17.
- **Full suite 502/502 on SQLite** (was 496; +6), phpstan max 0, pint clean. **PG17 verified** (first DB-touching lifecycle task): new test 6/6 + `tests/Feature/Modules/Catalog`+`tests/Unit/Modules/Catalog`+`tests/Architecture` = **98/98** (real `FOR UPDATE` lock + audit + from-state guard proven on Postgres; boundaries green). `openspec validate --strict` ✓. Guards: no new migration, no composer drift, no protected files.

## Active Change & Next Task
- **`catalog-lifecycle-approval` — 4/17 tasks.** Module 0 lifecycle FSM + governance + Producer gate + first cross-module consumer (done 1.2) + cascades + 14 events.
- **Next: task 2.3** — **Approval governance** layered onto the shared mechanism's commercial-impact steps (`reviewed→active`, `active→retired`, `retired→reviewed`): separation-of-duties floor (no self-approval; approver ≠ creator/reviewer), operator-principal-required (`NewcoOps`+non-null `actorId`, else reject), role-count config (`config('catalog.approval.role_count')` default 3 — add `config/catalog.php`). Add `RejectProductMasterReview` (`reviewed→reviewed`, audit `decision: rejected`+notes; rejection-pending is audit-derived). Guard reads prior actors from `audit_records` (creator = the `*Created` event `actor_id`; reviewer = the `draft→reviewed` audit row). Extend `LifecycleTransition` (guard BEFORE the update). Test extends `ProductMasterLifecycleTest.php`. **PG17 required.**

## Blockers & Decisions Needed
- **None blocking.** Standing design judgment calls (accept/veto during ralph): (1) KYC conjunct enforced upstream — gate is producer-`active` only (D6); (2) producer-state projection in design D3, not standalone ADR; (3) separation-of-duties is audit-derived, no governance columns (D5).
- Deferred follow-ons (named): `catalog-operator-console` (Filament approval UI), `parties-compliance` (KYC tightens `ActivateProducer`), Phase-3 referencers (cross-module retirement-blocking refs).

## Open Patterns
- **Full suite = `php -d memory_limit=512M vendor/bin/pest`** (NOT `php artisan test` — 128M OOMs in arch plugin).
- **Shared generic transition mechanism:** thread `@template TModel of Model&HasLifecycleState` by operating on the PASSED `$model` (NOT the `firstOrFail()` result, which Larastan erases to base `Model`) — `lockForUpdate()->firstOrFail()` (discard, acquires lock) then `$model->refresh()` then `return $model`. Read contract = a typed interface METHOD (`lifecycleState()`), NOT a magic `@property` (Larastan's Eloquent extension shadows interface `@property` through the intersection). No forbidden `@var`/assert.
- **Audit action from the TABLE:** `catalog.{Str::singular(Str::after($table,'catalog_'))}.{verb}` — survives SKU acronyms (`catalog_sellable_skus→sellable_sku`); `Str::snake('SellableSKU')` mangles. Entity label passed explicitly (matches domain-event `entity_type`; `class_basename` ≠ canonical for SKUs).
- **Pint method-name trap:** `{@see lowercaseMethod()}` gets rewritten to `{@see CasedClass()}` when an imported class matches case-insensitively. Backtick same-class method refs. (Also: `{@see \FQCN}` auto-imports — keep cross-module/future refs in prose; breaks `ModuleBoundariesTest` for other-module FQCNs.)
- **PG17 gate** (DB-touching tasks): `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; bounded `pg_isready -q` loop; env `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 …`; `docker rm -f pg`. Traps in `knowledge/testing/rules.md`.
- **Consumer/lifecycle tests use `DatabaseMigrations`, not `RefreshDatabase`** (recorder's post-commit hook + the mechanism's own txn fire only at `transactionLevel 0` — D11).
