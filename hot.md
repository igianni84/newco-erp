---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-07
---

# Hot Cache

## Last Updated
**2026-07-07 — `parties-module-k-br-guards` ralph loop RUNNING. Task 6.1 (ProducerAgreement console create surface) DONE, committed — 17/23; §6 (console) now 1/4.** BOTH ProducerAgreement create surfaces updated via shared `public static` resource helpers `settlementCadenceOptions()` + `activeClubOptions(?int)`: cadence `TextInput`→`Select` over the `SettlementCadence` operand enum (default `quarterly`); Club picker reactive (`producer_id` `->live()`, child reads `$get('producer_id')`) + active-only. The action KEEPS `?string` — it is the RM-22 floor that SURFACES an out-of-set token via the base catch; the console passes the string through (a page `Enum::from()` would 500 instead). NO migration/schema.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Task 6.1 full loop **green**: focused `ProducerAgreementCreateConsoleTest` 10/10 (67 assertions) → full OperatorPanel 821/821 → SQLite full suite **2056/2056** (2051 baseline +5 new) · PHPStan max **0** · Pint clean · `openspec validate --strict` valid.
- **No PG17 run for 6.1** — console/form only, NO schema/SQL. **PG17 recipe (DB-schema tasks only):** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest` (`pg` container UP on :55432). Full suite via `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs at 128 MB).

## Active Change & Next Task
- **`parties-module-k-br-guards` — 17/23 done. §2–5 COMPLETE; §6 console = 1/4. NEXT = task 6.2:** `ProfileResource` create surface — surface `ClubNotAcceptingMemberships` (the Profile requirement HAS an explicit "sunset/closed Club rejected+surfaced" scenario, unlike Agreement); Club picker presents `active` Clubs; ADD the `SetProfileAutoRenew` operator affordance to the Profile VIEW page (a toggle wired to the 4.2 Action). _Acceptance:_ `ProfileCreateConsoleTest` (sunset/closed rejected+surfaced, no Profile/event) + `ProfileConsole…Test` (the auto-renew toggle drives `SetProfileAutoRenew`). Reuse 6.1's active-club-picker pattern; NOTE `CreateProfile($customerId,$clubId)` takes NO operand enum (no cadence-style widening recurs) — the toggle is a VIEW-page verb (SurfacesDomainActions precedent), not a create field. Grep `ProfileResource` + `ProfileCreateConsoleTest` first.
- **Scope after 6.2:** 6.3 (`CustomerResource` DOB age-gate surface, references `CreateCustomer::MINIMUM_REGISTRATION_AGE`, `BelowMinimumRegistrationAge` on the DOB field); 6.4 (invite_only leg FULLY pre-done by 2.3/4.3 — only the registration-flow SELECT excluding latent `open_registration` + optional `assertFormFieldDoesNotExist('invite_only')` remain) → §7 close (human-gated, NOT part of loop).

## Blockers & Decisions Needed
- None. Change APPROVED; branch `ralph/parties-module-k-br-guards`.
- **Append memory files via the Edit tool, not `cat >>` heredocs** (git-guardrails hook false-positives on spec-path strings). progress.md needs a prior Read of the edit region before Edit.

## Open Patterns
(full form in progress.md `## Codebase Patterns`)
- **Operator-console create surface (6.1):** two surfaces per entity (nav-hidden standalone Resource::form()+page = test target; the parent's RelationManager = primary UI) — share `public static` option helpers, update both. Operand-enum Select drives options+default off the enum (carve-out import); the ACTION keeps its `?string`/resolved contract + is the floor that SURFACES out-of-set (base RuntimeException→ValidationException catch) — console passes the string, never a page `Enum::from()` (would 500). Reactive picker = `->live()` parent + `->options(fn (Get $get) => …intOrNull($get(...)))`. Filter related-entity state via `$m->status->value === 'active'` (CustomerHoldsTable precedent — NO state-enum import; only OPERAND enums cross). Filament KEEPS an out-of-option Select value → forced-value floor tests robust. Assert Select-ness via the `fn (Select $field)` type-hint (TypeErrors a TextInput).
- Earlier patterns (in progress.md): model `updating` conditional-state-gate lock (5.2) · fail-fast boundary input-gate + migrate-omitting-callers (5.1) · cascade reusing audit-only Action + from-state filter at the query (4.4) · model `saving` guard on EVERY write path (4.3) · audit-only preference writer + Larastan-non-null-`first()`→ternary (4.2) · required-reference-guard UNCONDITIONAL (4.1) · business-rule guard on related-entity state (3.2) · behaviour-inversion guard (3.3) · value-domain-reject vs business-rule-guard (3.1) · localized-guard-exception SoD shape (2.4).
