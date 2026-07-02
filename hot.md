---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — RM-01 (`parties-anonymisation`) task 1.2 ✅ — the two additive erasure migrations. 2 of 12 tasks done.** Added `2026_07_02_000001_add_anonymised_at_to_parties_customers` (nullable `timestampTz`, no default/CHECK — a flag+timestamp orthogonal to the status FSM) and `2026_07_02_000002_create_parties_addresses_table` (`id`, `customer_id` FK→`parties_customers` **cascadeOnDelete**, `line1`/`line2?`/`locality`/`region?`/`postal_code`/`country_code(2)`, optional `company_name`/`vat_id`, `timestampsTz`; no `version` — mutable child). Both Postgres-truthful + SQLite-compatible, no PG extension, additive. New `AnonymisationSchemaTest.php` (10 cases) proves it at the raw-DB layer. **Next = task 1.3: localized reasons in `lang/en/parties.php` + `CONTEXT.md` (add the `Address` term; flip the `parties-anonymisation` seam entry to implemented-here).**

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **Green:** SQLite full suite **1817/1817** (9866 assertions; +10 vs the 1807 RM-06 baseline); PHPStan max **0**; Pint clean; `migrate:fresh` (SQLite) clean; `openspec validate parties-anonymisation --strict` valid.
- Full suite: `php -d memory_limit=-1 vendor/bin/pest` (bare `php artisan test` OOMs at 128M). PG17 cross-engine run is task 7.1 (close ritual).

## Active Change & Next Task
- **Active: `parties-anonymisation` (RM-01) — APPROVED ✅, BUILDING. 2/12 done.** Branch `ralph/parties-anonymisation`.
- **NEXT: task 1.3 — localized reasons + CONTEXT.md.** Add the anonymisation-blocked (and illegal-state) reason keys to `lang/en/parties.php` (dotted keys, non-PII placeholders); add the `Address` canonical term to `CONTEXT.md` (Module K, Customer-scoped billing, optional company_name/vat_id) + flip the `parties-anonymisation` seam entry (lines ~233/~282) from *deferred* to *implemented here*. Run the FULL-suite i18n sink scanner (not a bare path — lessons.md 2026-06-20).
- Then: 2.1 (`Address` model + `Customer hasMany` + `CreateCustomerAddress`) · 3.x (placeholders → `AnonymiseCustomer` gate+overwrite → audit redaction → `CustomerAnonymised` event) · 4.1 (Hold-precedence matrix) · 5.1 (`ExportCustomerData`) · 6.1 (console) · 7.1 (PG17 + full close).

## Blockers & Decisions Needed
- **None.** RM-01 APPROVED; the Hold-block-set contradiction is reconciled in the ADR (cite it, not the raw spec, for tasks 3.2/4.1).
- **FK-cascade call (task 1.2, recorded):** owned-child → `cascadeOnDelete` (inert in practice — Customers are never hard-deleted; anonymisation overwrites in place). Sibling *referenced-shared-parent* FKs stay RESTRICT. See progress.md Codebase Patterns.
- **Incidental (candidate F4):** `party-registry` truth-spec *Hold Registry* still says "six-value" while code is **8** (RM-04 debt). RM-01's gate is `compliance`-only / count-independent so it does NOT block.

## Open Patterns
- **Anonymisation gate = `compliance`-only, count-independent** (tasks 3.2/4.1): key on `compliance` via `PartyComplianceStatusReader`; never the `Hold` model; never wire `sanctions_status`.
- **Owned-child FK CASCADE vs referenced-parent RESTRICT** (progress.md Codebase Patterns) — the module rule; apply to every future FK.
- **New non-`Create*` Actions red the exhaustive allow-list** (`SupplyLifecycleChainTest`, lessons.md 2026-06-23): register `AnonymiseCustomer` + `ExportCustomerData` at task 3.4. `CreateCustomerAddress` is `Create*`-named → excluded.
- **Schema-test idiom:** read via `->value('col')` (not `->first()->prop` — PHPStan-max `stdClass|null`); raw `DB::table()->insert()` + an `xRow()` helper is the pre-model proof.
