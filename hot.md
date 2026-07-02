---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — RM-01 (`parties-anonymisation`) task 4.1 ✅ — per-Hold-type precedence matrix (PURE TESTS). 9 of 12 done.** Pinned the *Anonymisation Hold Precedence* requirement (AC-K-J-9a; canon MVP-DEC-015) with a new file `tests/Feature/Modules/Parties/CustomerAnonymisationHoldPrecedenceTest.php` (11 tests / 45 assertions) — the gate itself was already built (3.2 gate + 3.4 event), so **zero source touched**. Four proofs: (1) **matrix** — a scalar-enum dataset `it(function (HoldType $type, bool $blocks) {...})->with([8 named rows])` over EVERY `HoldType`: `compliance`→BLOCKS (throws, `anonymised_at` NULL, 0 event), the other 7→proceed (anonymised, 1 event); BOTH branches assert the Hold ROW survives `active` (gate reads, never mutates — inv 7); (2) **completeness guard** `toEqualCanonicalizing(HoldType::cases())` (reds if the enum grows); (3) **lift-then-retry** via the REAL `LiftHold`; (4) **precedence dominance** — compliance+payment coexist→blocks; lift only compliance→proceeds + payment survives (proves PRESENCE-keyed, count-independent). No arch-gate churn (`SupplyLifecycleChainTest` untouched — no new Action). **Next = task 5.1: `ExportCustomerData`.**

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **Green:** SQLite full suite **1864/1864** (10089 assertions; +11 tests/+45 vs the 1853 task-3.4 baseline); PHPStan max **0**; Pint clean; `openspec validate parties-anonymisation --strict` valid.
- Full suite: `php -d memory_limit=-1 vendor/bin/pest` (bare `php artisan test` OOMs at 128M). PG17 cross-engine run is task 7.1 (close ritual); the new file re-fetches rows + asserts events BY NAME → cross-engine-safe by construction.

## Active Change & Next Task
- **Active: `parties-anonymisation` (RM-01) — APPROVED ✅, BUILDING. 9/12 done.** Branch `ralph/parties-anonymisation`.
- **NEXT: task 5.1 — `ExportCustomerData` action** (design D5; spec — *Customer Data Export*; canon J-9b). Read-only, synchronous, IN-MEMORY: assemble a structured payload = the Customer's PII + a by-id manifest of retained history (Profiles now; Order/Voucher/Invoice refs as they exist). **NO** file persistence, **NO** domain event, **NO** mutation. For an anonymised Customer it reflects the PLACEHOLDER PII. ⚠️ **MUST register `ExportCustomerData` in `SupplyLifecycleChainTest`'s `$anonymisationWriters` group + the `toEqualCanonicalizing` spread the SAME iteration** — it's non-`Create*`, so the `Actions/*.php` glob catches it and the exact set reds unless registered (the lesson from 3.2; `AnonymiseCustomer` already there).
- Then: 6.1 (console Anonymise/Export — visibility-gated, dev-browser verify) · 7.1 (PG17 + full close).

## Blockers & Decisions Needed
- **None.** RM-01 APPROVED; the `compliance`-only / count-independent gate is reconciled in ADR `2026-07-02-adopt-dec-015-…` (cite it, not the self-contradictory raw spec).
- **Incidental (candidate F4):** `party-registry` truth-spec *Hold Registry* still says "six-value" while code is **8** (RM-04 debt). RM-01's gate is `compliance`-only / count-independent → does NOT block.

## Open Patterns
- **Exhaustive per-enum-case gate matrix (task 4.1, new Codebase Pattern):** scalar-enum dataset (`(HoldType, bool)` — NEVER a bare-array param spread into `create([...])`, PHPStan-max, lessons 2026-06-18) branching block/proceed + a `toEqualCanonicalizing(Enum::cases())` completeness guard + **factory-place / real-Action-lift asymmetry** (`Hold::factory()` bypasses PlaceHold's suspend coupling → gate read isolated; the real `LiftHold` cleanly `active→lifted` since the un-`suspended` Customer makes its restore leg a no-op — filter its `CustomerHoldLifted` via `where('name', CustomerAnonymised::NAME)`, never total `count()`) + a **dominance** case (compliance+payment coexist → still blocks). Reusable for any gate keyed on one enum value. Only `LiftHold` an operator-liftable type (`compliance`, not `payment`/`kyc` → `autoManaged` throw).
- **A new non-`Create*` Action MUST be registered in `SupplyLifecycleChainTest`'s exact `toEqualCanonicalizing` set the SAME iteration it lands.** `AnonymiseCustomer` done (3.2). **`ExportCustomerData` (5.1) still pending** — add to `$anonymisationWriters` + spread.
- **Anonymisation gate = `compliance`-only, count-independent:** key on `HoldType::Compliance` via `PartyComplianceStatusReader`; never the `Hold` model; never wire `sanctions_status` (separate FSM).
- **PII-free event for an erasure/timestamp state reads the PERSISTED `*_at` column, not `now()`** (`CustomerAnonymised` ← `anonymised_at?->toIso8601String()`). Reuse `ENTITY_TYPE` as the audit-redaction scope in the same Action.
