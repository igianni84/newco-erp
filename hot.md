---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — RM-01 (`parties-anonymisation`) BUILD STARTED. Task 1.1 ✅ (mini-ADR `decisions/2026-07-02-adopt-dec-015-anonymisation-hold-block-set.md` + INDEX row). 1 of 12 tasks done.** The ADR adopts canon MVP-DEC-015 (anonymisation Hold-precedence = **`compliance`-only**, count-independent) and records 3 companions: sanctions-retention via a `compliance` Hold (no `sanctions` Hold type — sanctions is the `sanctions_status` FSM), J-9b export minimal/synchronous/in-memory, PII-free `CustomerAnonymised` event over frozen §15.1's event-free anonymisation. A subagent verified the frozen spec against source — the Hold-precedence contradiction is **two-layered** (DEC-027 compliance-non-blocking vs §8.2/AC-K-J-9a compliance-blocks; plus all three name a "sanctions Hold" absent from the 6/8-type enum). **Next = task 1.2: the two additive migrations (`anonymised_at` on `parties_customers`; `parties_addresses`), PG-truthful + SQLite-compat, no PG extension.**

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **Doc-only iteration — zero PHP touched.** Last green baseline (RM-06 close): SQLite + PG17 **1807/1807** (9851 assertions); PHPStan max 0; Pint clean — unaffected. This iter: `openspec validate parties-anonymisation --strict` valid; `vendor/bin/pint --test` passed; `decisions/INDEX.md` links the ADR.
- Full suite: `php -d memory_limit=-1 vendor/bin/pest` (bare `php artisan test` OOMs at 128M).

## Active Change & Next Task
- **Active: `parties-anonymisation` (RM-01) — APPROVED ✅, BUILDING. 1/12 tasks done.** On branch `ralph/parties-anonymisation`.
- **NEXT: task 1.2 — migrations.** (a) `add_anonymised_at_to_parties_customers` (nullable `timestampTz`); (b) `create_parties_addresses_table` (FK → `parties_customers`, personal address fields + optional `company_name`/`vat_id`, `timestampsTz`). Additive-nullable, PG-truthful, SQLite-compatible, no PG extension. Then 1.3 (localized reasons + CONTEXT.md Address term).
- Scope recap: `AnonymiseCustomer` (compliance-Hold gate → deterministic id-keyed PII overwrite → `anonymised_at` → audit redaction → PII-free `CustomerAnonymised`; keyed history preserved; orthogonal to status FSM) · Address entity · `ExportCustomerData` (J-9b minimal). Close ritual §2.7 (PG17 full suite + semantic-verify) is task 7.1 → then archive.

## Blockers & Decisions Needed
- **None.** RM-01 APPROVED; both design Qs resolved; the Hold-block-set contradiction is now reconciled in the ADR (cite the ADR, not the raw self-contradictory spec, when building tasks 3.2/4.1).
- **Incidental (candidate F4):** `party-registry` truth-spec *Hold Registry* still says "six-value" while code is **8** (RM-04 debt). RM-01's gate is `compliance`-only / count-independent so it does NOT block — decide fold-in vs file separately.
- Canon drift MVP-DEC-007→023 still open on Module K (RM-03/DEC-016, RM-05/DEC-011/017) — waits on Modules S/E/A. Tracker §7: F1, F2 still open.

## Open Patterns
- **Anonymisation gate = `compliance`-only, count-independent** (progress.md Codebase Patterns): key on `compliance` alone via `PartyComplianceStatusReader`; never the `Hold` model; never wire `sanctions_status`. Immune to the 6→8 debt.
- **Canon-ADR discipline (lessons.md 2026-07-02, rule — 4th application):** adopting a canon MVP-DEC absent from frozen `spec/` (stops at 007) always earns a mini-ADR. RM-01/DEC-015 was tasks.md 1.1 ✅.
- **New non-`Create*` Actions red the exhaustive allow-list** (`SupplyLifecycleChainTest`, lessons.md 2026-06-23): register `AnonymiseCustomer` + `ExportCustomerData` when they land (task 3.4). `CreateCustomerAddress` is `Create*`-named → excluded.
