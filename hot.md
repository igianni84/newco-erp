---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — RM-01 (`parties-anonymisation`) task 3.2 ✅ — `AnonymiseCustomer` action (gate + overwrite + `anonymised_at`). 6 of 12 done.** Built the GDPR right-to-erasure core: `app(AnonymiseCustomer::class)->handle(int $id): Customer` in ONE `DB::transaction` — lock-re-read → **(d) idempotent** early-return if `anonymised_at` set → **(gate)** compliance Hold-precedence (throws `AnonymisationBlockedByComplianceHold`) → **(a)** overwrite Customer PII + every scoped Address (reads task-3.1 `AnonymisedPlaceholders::for($id)`) → **(b)** stamp `anonymised_at = CarbonImmutable::now()`. Orthogonal to status (writes no `status`, records no event). Records NO event / NO audit-redaction yet — those are the deferred legs 3.4/3.3 that EXTEND this same action. **Next = task 3.3: audit-records redaction leg.**

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **Green:** SQLite full suite **1846/1846** (9994 assertions; +7 vs the 1839 task-3.1 baseline); PHPStan max **0**; Pint clean; `openspec validate parties-anonymisation --strict` valid.
- Full suite: `php -d memory_limit=-1 vendor/bin/pest` (bare `php artisan test` OOMs at 128M). PG17 cross-engine run is task 7.1 (close ritual).

## Active Change & Next Task
- **Active: `parties-anonymisation` (RM-01) — APPROVED ✅, BUILDING. 6/12 done.** Branch `ralph/parties-anonymisation`.
- **NEXT: task 3.3 — audit-records redaction leg** (design D6). **Investigation-first**: determine which of the Customer's `audit_records` carry PII in `before`/`after`; within the SAME `AnonymiseCustomer` transaction, null those `before`/`after` via the redaction path (the sole mutation the immutability triggers permit — a `before`/`after`-only UPDATE; migration `2026_06_12_000004`). If Parties audit snapshots are PII-free today, wire the capability + document it as a no-op. Do NOT depend on a `redactor` DB role in tests (SQLite has none; the trigger permits the before/after-only UPDATE on both engines).
- Then: 3.4 (`CustomerAnonymised` PII-free event — adds `DomainEventRecorder`+`ActorContext` to the AnonymiseCustomer ctor; `AnonymiseCustomer` is ALREADY in `SupplyLifecycleChainTest` — only `ExportCustomerData`/5.1 still needs adding) · 4.1 (Hold-precedence matrix) · 5.1 (`ExportCustomerData`) · 6.1 (console) · 7.1 (PG17 + full close).

## Blockers & Decisions Needed
- **None.** RM-01 APPROVED; the Hold-block-set contradiction is reconciled in ADR `2026-07-02-adopt-dec-015-…` (cite it, not the raw spec, for the gate).
- **3.4 will FLIP two test-assertion families:** `CustomerAnonymisationTest`'s happy/closed/idempotent cases currently assert `DomainEvent::count() === 0`; once 3.4 records `CustomerAnonymised`, change to "exactly one" (happy/closed) + "no SECOND" (idempotent). Grep `DomainEvent::query()->count())->toBe(0)`.
- **Incidental (candidate F4):** `party-registry` truth-spec *Hold Registry* still says "six-value" while code is **8** (RM-04 debt). RM-01's gate is `compliance`-only / count-independent so it does NOT block.

## Open Patterns
- **A new non-`Create*` Action MUST be registered in `SupplyLifecycleChainTest` the SAME iteration it lands** (progress.md Codebase Patterns) — the `Actions/*.php` glob + exact `toEqualCanonicalizing` set (line ~389) reds the suite the instant an unregistered non-`Create*` file exists. Quality-Loop "stay green" overrides any task-plan note deferring "registration" to a later task. Event-free is fine (whitelist holds audit-only entries).
- **Parties transition-Action shape** = `Customer::query()->whereKey($id)->lockForUpdate()->firstOrFail()` + child-set re-read `->lockForUpdate()->get()` inside ONE `DB::transaction` (`CloseCustomer`/`SuspendCustomer`). NOT `lockAndRefresh` (Catalog-only). Ctor carries only what it USES (reader-only when event-free; add recorder+actor with the event).
- **Isolated Hold in a gate test:** `Hold::factory()->create(['hold_type' => HoldType::Compliance, 'scope_type' => HoldScope::Customer, 'scope_id' => $customer->id])` — bypasses `PlaceHold` status-coupling; `DatabaseComplianceStatusReader` reads `parties_holds` directly. Use for the 4.1 precedence matrix.
- **Anonymisation gate = `compliance`-only, count-independent** (3.4/4.1 still cite this): key on `HoldType::Compliance` via `PartyComplianceStatusReader`; never the `Hold` model; never wire `sanctions_status`.
- **Internal within-module value object → `app/Modules/{Module}/Support/`** (pure, `::for()`/`::of()` factory, safe vs all four arch gates). Fixed-width `string(N)`-column sentinel must be ≤ N chars (`country_code`/`ZZ`).
