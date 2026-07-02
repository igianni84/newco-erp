---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — RM-01 (`parties-anonymisation`) task 2.1 ✅ — Address entity. 4 of 12 tasks done.** Built the `Address` model (`parties_addresses`, `belongsTo Customer`, no `casts()` — all-string cols), `Customer::addresses()` within-module `hasMany`, and the thin `CreateCustomerAddress` action (no event, no transaction, single `Address::create()`). Country-code boundary guard: `/^[A-Z]{2}$/` (ISO 3166-1 alpha-2, fail-closed) → localized `InvalidAddressCountryCode` (`:country` interpolated — not PII). Added `parties.address.invalid_country_code` + `AddressFactory` (`forCompany()` state). Named `Create*` so `SupplyLifecycleChainTest`'s non-`Create*` whitelist filters it out (green unamended). **Next = task 3.1: `AnonymisedPlaceholders` deterministic helper.**

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **Green:** SQLite full suite **1831/1831** (9926 assertions; +12 vs the 1819 task-1.3 baseline); PHPStan max **0**; Pint clean; `openspec validate parties-anonymisation --strict` valid.
- Full suite: `php -d memory_limit=-1 vendor/bin/pest` (bare `php artisan test` OOMs at 128M). PG17 cross-engine run is task 7.1 (close ritual).

## Active Change & Next Task
- **Active: `parties-anonymisation` (RM-01) — APPROVED ✅, BUILDING. 4/12 done.** Branch `ralph/parties-anonymisation`.
- **NEXT: task 3.1 — `AnonymisedPlaceholders` deterministic helper** (design D1). A pure value-object/helper deriving per-Customer-unique placeholders from the Customer id: `email → anonymised+{id}@anonymised.invalid`, `name → "Anonymised Customer {id}"`, `phone → null`, `date_of_birth → null`, + Address personal-field placeholders. **Deterministic, never `random`/`faker`** (UNIQUE-email-safe: id makes each distinct). Unit test (no `RefreshDatabase`): same id → same placeholders; two ids → distinct emails.
- Then: 3.2 (`AnonymiseCustomer` gate+overwrite+`anonymised_at` — overwrites the Customer PII **and** the 8 Address fields `line1/line2/locality/region/postal_code/country_code/company_name/vat_id`) · 3.3 (audit redaction) · 3.4 (`CustomerAnonymised` + register the 2 non-`Create*` Actions) · 4.1 (Hold-precedence matrix) · 5.1 (`ExportCustomerData`) · 6.1 (console) · 7.1 (PG17 + full close).

## Blockers & Decisions Needed
- **None.** RM-01 APPROVED; the Hold-block-set contradiction is reconciled in the ADR (cite it, not the raw spec, for tasks 3.2/4.1).
- **Doc-forward flip (recorded):** CONTEXT.md calls the anonymisation seams "now landed" (`AnonymiseCustomer`/`CustomerAnonymised`), but those land in 3.2/3.4. Deliberate; **tasks.md/progress.md are the authoritative build-state, not CONTEXT.md.**
- **Incidental (candidate F4):** `party-registry` truth-spec *Hold Registry* still says "six-value" while code is **8** (RM-04 debt). RM-01's gate is `compliance`-only / count-independent so it does NOT block.

## Open Patterns
- **Thin `Create*` = no event, no transaction, allow-list-filtered** (progress.md Codebase Patterns): a pure creation Action skips `DB::transaction`/recorder and, being `Create*`-named, is filtered out of `SupplyLifecycleChainTest`. Only NON-`Create*` writers (3.4) register there.
- **ISO code boundary = enum-param (closed set) vs format-regex (open set)**: currency/locale → typed enum; country → `/^[A-Z]{2}$/` + localized exception. Same split for any future open code-set.
- **Anonymisation gate = `compliance`-only, count-independent** (tasks 3.2/4.1): key on `compliance` via `PartyComplianceStatusReader`; never the `Hold` model; never wire `sanctions_status`. Throw `__('parties.anonymisation.blocked_by_compliance_hold', ['customer' => $id])`.
- **Domain-reason keys live in `lang/en/parties.php` only** (per-key EN fallback, DEC-127); resolution tests co-locate in `tests/Unit/Modules/Parties/Exceptions/`.
