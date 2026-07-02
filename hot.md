---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — RM-01 (`parties-anonymisation`) task 3.1 ✅ — `AnonymisedPlaceholders`. 5 of 12 tasks done.** Built the pure value object the anonymisation overwrite reads: `AnonymisedPlaceholders::for(int $customerId)` (private ctor + static factory à la `Money::of`), two id-derived `public readonly` props (`email → anonymised+{id}@anonymised.invalid` UNIQUE-safe, `name → "Anonymised Customer {id}"`) + two projection maps — `customerAttributes()` (4 cols: email/name derived, phone/date_of_birth null) and `addressAttributes()` (8 `parties_addresses` fields: `line1/locality/postal_code → "Anonymised"`, `country_code → "ZZ"`, the 4 nullable → null). Deterministic, no random/faker. **Next = task 3.2: `AnonymiseCustomer` action.**

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **Green:** SQLite full suite **1839/1839** (9941 assertions; +8 vs the 1831 task-2.1 baseline); PHPStan max **0**; Pint clean; `openspec validate parties-anonymisation --strict` valid.
- Full suite: `php -d memory_limit=-1 vendor/bin/pest` (bare `php artisan test` OOMs at 128M). PG17 cross-engine run is task 7.1 (close ritual).

## Active Change & Next Task
- **Active: `parties-anonymisation` (RM-01) — APPROVED ✅, BUILDING. 5/12 done.** Branch `ralph/parties-anonymisation`.
- **NEXT: task 3.2 — `AnonymiseCustomer` action (gate + overwrite + `anonymised_at`)** (design D1/D2/D4). One `DB::transaction` + `lockAndRefresh` + operator floor: (a) **Hold-precedence gate** — read `PartyComplianceStatusReader::forCustomer(...)`, throw `__('parties.anonymisation.blocked_by_compliance_hold', ['customer' => $id])` iff an active **`compliance`** Hold covers the Customer (never the `Hold` model; never `sanctions_status`); (b) `$p = AnonymisedPlaceholders::for($customer->id)` → overwrite Customer with `$p->customerAttributes()` and EACH `$customer->addresses` with `$p->addressAttributes()` (DB-column-keyed maps drop into an Eloquent update); (c) set `anonymised_at`; (d) **idempotent** no-op if already anonymised. MUST NOT write `status` or record a status event (orthogonality). Add the `anonymised_at` cast to `Customer::casts()` (`immutable_datetime`).
- Then: 3.3 (audit redaction) · 3.4 (`CustomerAnonymised` event + register `AnonymiseCustomer`/`ExportCustomerData` in `SupplyLifecycleChainTest`'s non-`Create*` allow-list) · 4.1 (Hold-precedence matrix) · 5.1 (`ExportCustomerData`) · 6.1 (console) · 7.1 (PG17 + full close).

## Blockers & Decisions Needed
- **None.** RM-01 APPROVED; the Hold-block-set contradiction is reconciled in ADR `2026-07-02-adopt-dec-015-…` (cite it, not the raw spec, for tasks 3.2/4.1).
- **Doc-forward flip (recorded):** CONTEXT.md calls `AnonymiseCustomer`/`CustomerAnonymised` "now landed"; they land in 3.2/3.4. tasks.md/progress.md are the authoritative build-state.
- **Incidental (candidate F4):** `party-registry` truth-spec *Hold Registry* still says "six-value" while code is **8** (RM-04 debt). RM-01's gate is `compliance`-only / count-independent so it does NOT block.

## Open Patterns
- **Internal within-module value object → `app/Modules/{Module}/Support/`** (progress.md Codebase Patterns): pure/import-free, `::for()`/`::of()` factory. Safe vs all four arch gates (`ModuleConformanceTest` gates only the top-level modules dir; `SupplyLifecycleChainTest` globs `Actions/*.php` only → no allow-list registration; persistence-test skips non-Models; boundaries-test only bans cross-module imports). NOT `Contracts/` (cross-module surface). Pure unit test = no `uses(TestCase::class)`.
- **Fixed-width `string(N)`-column sentinel must be ≤ N chars** — SQLite ignores `varchar(N)`, PG enforces it; a longer placeholder passes `:memory:` and breaks the PG17 close. `country_code`/`ZZ` (2-char). Pin with `mb_strlen <= N` + format test.
- **Anonymisation gate = `compliance`-only, count-independent** (3.2/4.1): key on `compliance` via `PartyComplianceStatusReader`; never the `Hold` model; never wire `sanctions_status`.
- **Thin `Create*` = no event/no transaction/allow-list-filtered**; only NON-`Create*` writers (`AnonymiseCustomer`/`ExportCustomerData`, 3.4) register in `SupplyLifecycleChainTest`.
