---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-16
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-16 (ralph iter 5 — task 2.3 of `catalog-lifecycle-approval` DONE).** Layered the **Creator → Reviewer → Approver approval governance** (design D5) onto the shared lifecycle mechanism. New `ApprovalGovernance` guard runs INSIDE `LifecycleTransition::transition()` (after the from-state assert, before the write) for every commercial-impact step (`requiresApprovalGovernance()` = activate/retire/reopen): the **operator-principal floor** always (`newco_ops` + non-null `actorId`, else reject), plus the **separation-of-duties distinctness** at the APPROVE step (`reviewed→active`) — approver ≠ creator, and in three-step ≠ reviewer, with creator ≠ reviewer (three distinct). Creator read from the entity's first `domain_events` row; reviewer from the latest `%.submitted` `audit_records` row; null prior actors are vacuous. Added `reject()` (`reviewed→reviewed`, audit-only `decision: rejected` + notes) + `RejectProductMasterReview` Action, `config/catalog.php` (`approval.role_count`, default 3 ∈ {2,3}), `ApprovalGovernanceViolation` exception. The audit trail is the SoR — no governance columns.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PG17.
- **Full suite 510/510 on SQLite** (was 502; +8), phpstan max 0, pint clean. **PG17 verified**: `tests/Feature/Modules/Catalog` + `tests/Unit/Modules/Catalog` + `tests/Architecture` = **106/106** (the `actor_id` string-vs-int normalization — the critical PG-only path — + the `FOR UPDATE` lock + `LIKE '%.submitted'` reviewer read + jsonb rejection snapshot all proven; boundaries green). `openspec validate --strict` ✓. Guards: no new migration, no composer drift, no protected files.

## Active Change & Next Task
- **`catalog-lifecycle-approval` — 5/17 tasks.** Module 0 lifecycle FSM + governance (done 2.3) + Producer gate + 14 events + cascades.
- **Next: task 3.1** — `ProductMasterActivated` + `ProductMasterRetired` events (design D9): two `final` classes mirroring `ProductMasterCreated`, `NAME`/`ENTITY_TYPE` + static `payload(ProductMaster)` → `{product_master_id, producer_id, lifecycle_state}` (PII-free, producer by id). Category-neutral names (Naming Cascade — never `WineMaster*`). Test `tests/Unit/Modules/Catalog/Events/ProductMasterLifecycleEventsTest.php`. **No DB → no PG run** (pure event classes), but quality green.

## Blockers & Decisions Needed
- **None blocking.** Standing design calls (accept/veto during ralph): (1) approval-step SoD is `=== Activate`; retire/reopen carry only the operator floor (reopen now requires an operator principal — 2.2 tests stayed green, all `actingAs`); (2) "rejection-pending" is a derived read (latest `%.rejected`), no flag — a literal re-submit-from-reviewed is unreachable (§4.3 no revert-to-draft), so the reachable realization is "rejection is non-terminal, a superseding approval clears it"; (3) KYC conjunct enforced upstream (D6).
- Deferred follow-ons (named): `catalog-operator-console` (Filament approval queue UI, builds on the rejection-pending derivation), `parties-compliance` (KYC tightens `ActivateProducer`), Phase-3 referencers (cross-module retirement-blocking refs).

## Open Patterns
- **Full suite = `php -d memory_limit=512M vendor/bin/pest`** (NOT `php artisan test` — 128M OOMs in arch plugin).
- **Uncast bigint cross-engine:** `value('actor_id')` (or any column NOT in `casts()`) is `int` on SQLite but a numeric STRING on PG — normalize every read with `is_numeric($raw) ? (int) $raw : null` before a `===` (also satisfies phpstan-max). SQLite-invisible → THE reason actor-reading guards need the PG gate.
- **Multi-key jsonb assert cross-engine:** PG `jsonb` reorders keys, so assert `before`/`after` keys INDIVIDUALLY (`$a = $row->after ?? []; expect($a['k'] ?? null)->toBe(…)`) — whole-array `toBe` is order-sensitive and SQLite-only-green for 2+ keys. Single-key snapshot is safe with whole-array `toBe`.
- **Governance guard reads prior actors as VALUES** (`guard(type, entity, entityId)`) — model-free, no key-narrowing dup, import-clean (platform models + ActorContext only; no Parties — invariant 10). Order: from-state assert FIRST, then governance.
- **Shared mechanism threading** (TModel generic; operate on the PASSED `$model`, not the erased `firstOrFail()` result; typed interface METHOD `lifecycleState()` not a magic `@property`); **audit action from the TABLE** (`Str::singular(Str::after($table,'catalog_'))` — survives SKU acronyms). 3.2 extends `transition()` with optional Producer-gate predicate + event factory; the 2.3 bare-mechanism Activate tests stay valid.
- **PG17 gate:** `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; bounded `pg_isready -q`; env `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 …`; `docker rm -f pg`.
- **Lifecycle tests use `DatabaseMigrations`, not `RefreshDatabase`** (recorder's post-commit hook + the mechanism's txn fire only at `transactionLevel 0`).
