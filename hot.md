---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — RM-01 (`parties-anonymisation`) task 3.4 ✅ — `CustomerAnonymised` PII-free event. 8 of 12 done. Anonymisation CORE (3.1→3.4) COMPLETE.** Added the erasure signal as the final leg of `AnonymiseCustomer`, recorded LAST in the same `DB::transaction` (after overwrite/stamp/redact). New `Events/CustomerAnonymised` (final class, `CustomerClosed` 3-facet shape): `NAME`/`ENTITY_TYPE='Customer'`/`payload()`; **payload = `{customer_id, anonymised_at}` ONLY**, PII-free — `anonymised_at` reads the PERSISTED column (`?->toIso8601String()`, single source of truth, contrast `OriginatingClubLocked`'s columnless `now()`). `AnonymiseCustomer` ctor now = compliance reader + `AuditRecorder` + `DomainEventRecorder` + `ActorContext`; audit `redactEntity` literal aligned to `CustomerAnonymised::ENTITY_TYPE`. **`SupplyLifecycleChainTest` assertion UNCHANGED** (`AnonymiseCustomer` already whitelisted in `$anonymisationWriters`; 3.4 adds NO Action class — only a stale comment corrected). **Next = task 4.1: Hold-precedence matrix (pure tests).**

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **Green:** SQLite full suite **1853/1853** (10044 assertions; +1 test/+21 assertions vs the 1852 task-3.3 baseline); PHPStan max **0**; Pint clean; `openspec validate parties-anonymisation --strict` valid.
- Full suite: `php -d memory_limit=-1 vendor/bin/pest` (bare `php artisan test` OOMs at 128M). PG17 cross-engine run is task 7.1 (close ritual); the event `anonymised_at`==row assertion is same-source → cross-engine-safe by construction.

## Active Change & Next Task
- **Active: `parties-anonymisation` (RM-01) — APPROVED ✅, BUILDING. 8/12 done.** Branch `ralph/parties-anonymisation`.
- **NEXT: task 4.1 — Per-Hold-type precedence tests** (design D2; spec — *Anonymisation Hold Precedence*; `AC-K-J-9a`). PURE TESTS (no new src): a dataset-driven matrix over Hold types — `compliance` blocks; non-`compliance` (`payment`/`fraud`/…) proceeds; lifting the `compliance` Hold unblocks; the non-`compliance` Hold survives anonymisation. Gate is `compliance`-only + count-independent (cite ADR `2026-07-02-adopt-dec-015-…`). Isolated gate Hold: `Hold::factory()->create(['hold_type'=>HoldType::Compliance,'scope_type'=>HoldScope::Customer,'scope_id'=>$id])`. A blocked case now also asserts NO `CustomerAnonymised` recorded (event lands only on success).
- Then: 5.1 (`ExportCustomerData` — read-only + **MUST register in `$anonymisationWriters`**) · 6.1 (console Anonymise/Export) · 7.1 (PG17 + full close).

## Blockers & Decisions Needed
- **None.** RM-01 APPROVED; Hold-block-set contradiction reconciled in ADR `2026-07-02-adopt-dec-015-…` (cite it for the gate).
- **Incidental (candidate F4):** `party-registry` truth-spec *Hold Registry* still says "six-value" while code is **8** (RM-04 debt). RM-01's gate is `compliance`-only / count-independent → does NOT block.

## Open Patterns
- **PII-free event for an erasure/timestamp state reads the PERSISTED `*_at` column, not `now()`** (progress.md Codebase Patterns). Payload `'anonymised_at' => $model->anonymised_at?->toIso8601String()` → event moment == row (single source). Reuse the event `ENTITY_TYPE` as the audit-redaction scope in the same Action (one literal). Test: EXACT-keys assertion (structurally forbids extra PII keys) + `json_encode` substring guard (no `@`/original name/email) + `anonymised_at`==re-fetched-row (cross-engine-safe, no jsonb byte-compare); flip prior `count()===0` → `->sole()`/"exactly one"/"no second".
- **A new non-`Create*` Action MUST be registered in `SupplyLifecycleChainTest`'s exact `toEqualCanonicalizing` set the SAME iteration it lands.** `AnonymiseCustomer` done (3.2). `ExportCustomerData` (5.1) still pending. Adding an EVENT to an already-whitelisted Action (3.4) needs NO registration change (whitelist keys on Action CLASS, not events).
- **Anonymisation gate = `compliance`-only, count-independent** (4.1 cites this): key on `HoldType::Compliance` via `PartyComplianceStatusReader`; never the `Hold` model; never wire `sanctions_status` (separate FSM).
- **Parties transition-Action shape** = `Customer::query()->whereKey($id)->lockForUpdate()->firstOrFail()` + child-set re-read `->lockForUpdate()->get()` in ONE `DB::transaction`; event recorded LAST. Ctor carries only what it uses (now the full 4: reader+audit+recorder+actor).
