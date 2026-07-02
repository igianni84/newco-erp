---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — RM-01 (`parties-anonymisation`) task 1.3 ✅ — localized reason + CONTEXT.md terminology. 3 of 12 tasks done.** Added the sole anonymisation rejection copy `parties.anonymisation.blocked_by_compliance_hold` to `lang/en/parties.php` (`:customer` id interpolated — operator ref, NOT PII; names the rule, no name/email/phone/dob → log-safe). **Only one key** — `AnonymiseCustomer` is orthogonal to the status FSM + idempotent + has no illegal-state edge, so the Hold-precedence block is its *only* rejection (the task's "any illegal-state reason" resolves to none). CONTEXT.md: new **Address** term (after Account) + **both anonymisation seams flipped** *deferred → now landed* (naming `AnonymiseCustomer`/`anonymised_at`/`CustomerAnonymised`). New `AnonymisationExceptionsTest.php` (2 cases). **Next = task 2.1: `Address` model + `Customer hasMany Address` + `CreateCustomerAddress`.**

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **Green:** SQLite full suite **1819/1819** (9875 assertions; +2 vs the 1817 RM-06 baseline); PHPStan max **0**; Pint clean; `openspec validate parties-anonymisation --strict` valid.
- Full suite: `php -d memory_limit=-1 vendor/bin/pest` (bare `php artisan test` OOMs at 128M). PG17 cross-engine run is task 7.1 (close ritual).

## Active Change & Next Task
- **Active: `parties-anonymisation` (RM-01) — APPROVED ✅, BUILDING. 3/12 done.** Branch `ralph/parties-anonymisation`.
- **NEXT: task 2.1 — `Address` entity.** The `Address` Eloquent model (`parties_addresses`, cast the fields, no `version`), a **within-module** `Customer hasMany Address` / `Address belongsTo Customer`, and a thin `CreateCustomerAddress` action (`Create*`-named → stays OUT of the exhaustive non-`Create*` allow-list). Optional `company_name`/`vat_id`; `country_code` validated at the action boundary (ISO-3166 alpha-2, no DB CHECK). Assert `Customer` has no B2C/B2B discriminator (AC-K-XM-25); boundary check that `app/Modules/Parties` imports no other module's models.
- Then: 3.1 (`AnonymisedPlaceholders` deterministic helper) · 3.2 (`AnonymiseCustomer` gate+overwrite+`anonymised_at`) · 3.3 (audit redaction) · 3.4 (`CustomerAnonymised` + register the 2 non-`Create*` Actions) · 4.1 (Hold-precedence matrix) · 5.1 (`ExportCustomerData`) · 6.1 (console) · 7.1 (PG17 + full close).

## Blockers & Decisions Needed
- **None.** RM-01 APPROVED; the Hold-block-set contradiction is reconciled in the ADR (cite it, not the raw spec, for tasks 3.2/4.1).
- **Doc-forward flip (recorded):** CONTEXT.md calls the anonymisation seams "now landed" naming `AnonymiseCustomer`/`CustomerAnonymised`, but those land in 3.2/3.4 (only `anonymised_at` exists today). Deliberate per task 1.3 (the repo's front-loading pattern); **tasks.md/progress.md are the authoritative build-state, not CONTEXT.md.**
- **Incidental (candidate F4):** `party-registry` truth-spec *Hold Registry* still says "six-value" while code is **8** (RM-04 debt). RM-01's gate is `compliance`-only / count-independent so it does NOT block.

## Open Patterns
- **Anonymisation gate = `compliance`-only, count-independent** (tasks 3.2/4.1): key on `compliance` via `PartyComplianceStatusReader`; never the `Hold` model; never wire `sanctions_status`. Throw with `__('parties.anonymisation.blocked_by_compliance_hold', ['customer' => $id])`.
- **Owned-child FK CASCADE vs referenced-parent RESTRICT** (progress.md Codebase Patterns) — apply to `parties_addresses.customer_id` (CASCADE) in task 2.1.
- **New non-`Create*` Actions red the exhaustive allow-list** (`SupplyLifecycleChainTest`, lessons.md 2026-06-23): register `AnonymiseCustomer` + `ExportCustomerData` at task 3.4. `CreateCustomerAddress` is `Create*`-named → excluded.
- **Domain-reason keys live in `lang/en/parties.php` only** (per-key EN fallback, DEC-127); resolution tests co-locate in `tests/Unit/Modules/Parties/Exceptions/`.
