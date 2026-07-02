---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — RM-01 (`parties-anonymisation`) task 5.1 ✅ — `ExportCustomerData` (GDPR right-of-ACCESS, in-memory, read-only). 10 of 12 done.** Built the mirror-image of the erasure: `app(ExportCustomerData::class)->handle(int): array` assembles a structured **in-memory** payload and returns it — strictly READ-ONLY (NO file, NO event, NO mutation, NO transaction, NO ctor deps; the deliberate contrast to `AnonymiseCustomer`). Payload = `customer` (four PII cols + id) · `addresses` (a `list` of every scoped Address's personal fields — the SAME set the erasure overwrites) · `transactional_history` (a by-id manifest — `profiles` now, within-module; Order/Voucher/Invoice join later via a read contract, never a cross-module query). **Anonymisation-aware for FREE** — reads CURRENT row state, so an already-anonymised Customer's export reflects the placeholder PII with zero special-casing. New file `tests/Feature/Modules/Parties/CustomerDataExportTest.php` (4 tests). **Registered `ExportCustomerData` in `SupplyLifecycleChainTest`'s `$anonymisationWriters`** the same iteration (non-`Create*` → name-based glob catches it even though it never writes). **Next = task 6.1: console `Anonymise`+`Export`.**

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **Green:** SQLite full suite **1868/1868** (10118 assertions; +4 tests/+29 vs the 1864 task-4.1 baseline); PHPStan max **0**; Pint clean; `openspec validate parties-anonymisation --strict` valid.
- Full suite: `php -d memory_limit=-1 vendor/bin/pest` (bare `php artisan test` OOMs at 128M). PG17 cross-engine run is task 7.1 (close ritual); the export reads rows + returns a plain array (no jsonb byte-compare) → cross-engine-safe by construction.

## Active Change & Next Task
- **Active: `parties-anonymisation` (RM-01) — APPROVED ✅, BUILDING. 10/12 done.** Branch `ralph/parties-anonymisation`.
- **NEXT: task 6.1 — Customer console `Anonymise` + `Export`** (design D1/D5; console kit `decisions/2026-06-19`/`2026-06-20`). Add an `Anonymise` header action (write-through to `AnonymiseCustomer`, `->visible()`-gated: hidden once `anonymised_at` is set) and an `Export` action (write-through to `ExportCustomerData`) on the Customer View page via the shared console kit; the Hold-block/illegal-state surface via the kit's outcome-notification. ⚠️ **A `->visible()`-false action is UNDRIVABLE in Filament tests** (`lessons.md` 2026-06-23/24) → assert `assertActionVisible('anonymise')` fresh + `assertActionHidden('anonymise')` after `anonymised_at` set, and drive the DOMAIN effect via the Action directly; **live-verify the buttons in-browser (dev-browser skill)**.
- Then: 7.1 (PG17 + full close — run parties + operator-console suites on SQLite AND PG17; confirm the Action allow-list, migrations Postgres-truthful, audit-redaction before/after UPDATE + Hold read behave identically).

## Blockers & Decisions Needed
- **None.** RM-01 APPROVED; the `compliance`-only / count-independent gate is reconciled in ADR `2026-07-02-adopt-dec-015-…` (cite it, not the self-contradictory raw spec).
- **Incidental (candidate F4):** `party-registry` truth-spec *Hold Registry* still says "six-value" while code is **8** (RM-04 debt). RM-01's gate is `compliance`-only / count-independent → does NOT block.

## Open Patterns
- **Read-only Action (task 5.1, new Codebase Pattern):** plain in-memory array return, NO ctor/event/transaction (contrast the mutating `AnonymiseCustomer`) — but STILL non-`Create*`, so it MUST be added to `SupplyLifecycleChainTest`'s `$anonymisationWriters` + the spread the same iteration (the glob is NAME-based, blind to whether the Action writes). **PHPStan-max array-shape recipe:** `list<int>` of ids via `array_values($rel->orderBy('id')->get()->map(fn (Model $m): int => $m->id)->all())` (the `DatabaseComplianceStatusReader` idiom); `list<array{…}>` of row-shapes via a `foreach` pushing an INLINE array literal — NEVER `->map(fn: array => …)` (an explicit `: array` closure WIDENS to general `array` and breaks the declared shape). A read miss → `firstOrFail()` (`ModelNotFoundException`, not a localized exception).
- **A new non-`Create*` Action MUST be registered in `SupplyLifecycleChainTest`'s exact `toEqualCanonicalizing` set the SAME iteration it lands.** Both `AnonymiseCustomer` (3.2) and `ExportCustomerData` (5.1) done — no further non-`Create*` `Actions/` class pending (6.1 is a Filament console action, not an `Actions/` class; 7.1 is close-only).
- **Anonymisation gate = `compliance`-only, count-independent:** key on `HoldType::Compliance` via `PartyComplianceStatusReader`; never the `Hold` model; never wire `sanctions_status` (separate FSM).
- **PII-free event for an erasure/timestamp state reads the PERSISTED `*_at` column, not `now()`** (`CustomerAnonymised` ← `anonymised_at?->toIso8601String()`). Reuse `ENTITY_TYPE` as the audit-redaction scope in the same Action.
