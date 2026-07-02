---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — RM-01 (`parties-anonymisation`) task 3.3 ✅ — audit-records redaction leg (c). 7 of 12 done.** Wired the GDPR audit-redaction into `AnonymiseCustomer`. **Investigation finding (the documented deliverable):** Parties writes **ZERO** `audit_records` snapshots today (grep `AuditRecorder|audit_records app/Modules/Parties/` = only my new redaction caller; Parties records only PII-free domain events; `audit_records` written solely by Catalog + Platform) → Customer audit trail is **PII-free** → redaction is a **documented no-op** in production, capability wired for the future. New Platform mechanism `AuditRecorder::redactEntity(entityType, entityId): int` (before/after→NULL via base query builder, the sole trigger-permitted mutation; tx-guarded; returns redacted count). `AnonymiseCustomer` ctor now = compliance reader + `AuditRecorder`; calls `redactEntity('Customer', (string)$id)` after the Address overwrite, same tx. **Next = task 3.4: `CustomerAnonymised` PII-free event.**

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **Green:** SQLite full suite **1852/1852** (10023 assertions; +6 vs the 1846 task-3.2 baseline); PHPStan max **0**; Pint clean; `openspec validate parties-anonymisation --strict` valid.
- Full suite: `php -d memory_limit=-1 vendor/bin/pest` (bare `php artisan test` OOMs at 128M). PG17 cross-engine run is task 7.1 (close ritual) — the redaction before/after-only UPDATE is engine-parity by construction (base builder → real SQL NULL).

## Active Change & Next Task
- **Active: `parties-anonymisation` (RM-01) — APPROVED ✅, BUILDING. 7/12 done.** Branch `ralph/parties-anonymisation`.
- **NEXT: task 3.4 — `CustomerAnonymised` PII-free event** (design D3). EXTENDS the same `AnonymiseCustomer::handle`: add `DomainEventRecorder`+`ActorContext` to the ctor (now compliance+audit → +recorder+actor), record `CustomerAnonymised` (payload = id + `anonymised_at`, PII-free) after the redaction, same tx. `AnonymiseCustomer` is ALREADY registered in `SupplyLifecycleChainTest` (only `ExportCustomerData`/5.1 still needs adding). May switch the `redactEntity('Customer', …)` literal to `CustomerAnonymised::ENTITY_TYPE` (single source).
- Then: 4.1 (Hold-precedence matrix) · 5.1 (`ExportCustomerData`) · 6.1 (console) · 7.1 (PG17 + full close).

## Blockers & Decisions Needed
- **None.** RM-01 APPROVED; the Hold-block-set contradiction is reconciled in ADR `2026-07-02-adopt-dec-015-…` (cite it for the gate).
- **3.4 will FLIP two test-assertion families:** `CustomerAnonymisationTest`'s happy/closed/idempotent cases assert `DomainEvent::query()->count())->toBe(0)`; once 3.4 records `CustomerAnonymised`, change to "exactly one" (happy/closed) + "no SECOND" (idempotent). Grep `DomainEvent::query()->count())->toBe(0)`. My new 3.3 audit test does NOT assert DomainEvent count (audit writes create no events/deliveries) → unaffected.
- **Incidental (candidate F4):** `party-registry` truth-spec *Hold Registry* still says "six-value" while code is **8** (RM-04 debt). RM-01's gate is `compliance`-only / count-independent so it does NOT block.

## Open Patterns
- **Audit redaction = Platform MECHANISM + module POLICY** (progress.md Codebase Patterns). `AuditRecorder::redactEntity` is the substrate's before/after→NULL redaction (base query builder → real SQL NULL, no Eloquent cast/qualify trap; tx-guarded; returns count). Module erasure action injects it, calls `redactEntity(<EnvelopeEntityType>, (string)$id)`. **audit `entity_type` == domain-event `ENTITY_TYPE`** for the same entity (shared envelope core = `'Customer'`). Platform stays module-agnostic (string entity_type; NO `App\Modules` in a Platform docblock — same reason `$module` is a string).
- **Investigation-first "documented no-op" seam:** grep the WRITE path first; wire the mechanism + prove with a CONSTRUCTED PII row even when the module writes none today. The finding IS the deliverable.
- **A new non-`Create*` Action MUST be registered in `SupplyLifecycleChainTest` the SAME iteration it lands.** `AnonymiseCustomer` already registered (3.2); `ExportCustomerData` (5.1) still pending. `redactEntity` is a Platform method (not an Action) → needs NO registration.
- **Parties transition-Action shape** = `Customer::query()->whereKey($id)->lockForUpdate()->firstOrFail()` + child-set re-read `->lockForUpdate()->get()` inside ONE `DB::transaction`. Ctor carries only what it USES (reader+audit now; +recorder+actor with the event in 3.4).
- **Anonymisation gate = `compliance`-only, count-independent** (3.4/4.1 cite this): key on `HoldType::Compliance` via `PartyComplianceStatusReader`; never the `Hold` model; never wire `sanctions_status`. Isolated gate Hold: `Hold::factory()->create(['hold_type'=>HoldType::Compliance,'scope_type'=>HoldScope::Customer,'scope_id'=>$id])`.
