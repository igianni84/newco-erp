---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-07
---

# Hot Cache

## Last Updated
**2026-07-07 — `parties-module-k-br-guards` ralph loop RUNNING. Task 6.2 (ProfileResource create surface + auto-renew toggle) DONE, committed — 18/23; §6 console now 2/4.** TWO legs. **(A) Create surface:** `ProfileResource`'s `club_id` CREATE Select swapped `clubOptions()` → new `private static activeClubOptions()` (filters `$c->status->value === 'active'`, no `ClubStatus` import). Kept `clubOptions()` (ALL) for the list `SelectFilter` (an existing membership under a since-`sunset` Club must stay filterable). Surfacing `ClubNotAcceptingMemberships` needed **ZERO page code** — 4.1's guard + the `CreateProfile` page's `createRejectionField()='club_id'` + the base RuntimeException→form-error catch already surface a forced non-active value. SINGLE create surface (no Profile RelationManager). **(B) Auto-renew toggle:** bespoke `ViewProfile::autoRenewAction()` = `Action::make('setAutoRenew')->form([Toggle->default(fn→record)])->action(surfaceLifecycleOutcome→SetProfileAutoRenew)`, appended after the 8 verbs, **UNGATED** (a preference, any state), audit-only (no event). NO migration/schema.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Task 6.2 full loop **green**: focused Profile-console 126/127 (the 1 = the known "sink scanner not loaded" isolation artifact, green co-loaded w/ `ProductMasterConsoleI18nTest` 97/97) → SQLite full suite **2074/2074** (2056 baseline +18: +3 create, +9 auto-renew, +6 i18n) · PHPStan max **0** · Pint clean · `openspec validate --strict` valid.
- **No PG17 run for 6.2** — console/form only, NO schema/SQL. **PG17 recipe (DB-schema tasks only):** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest` (`pg` container on :55432). Full suite via `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs at 128 MB).

## Active Change & Next Task
- **`parties-module-k-br-guards` — 18/23 done. §2–5 COMPLETE; §6 console = 2/4. NEXT = task 6.3:** `CustomerResource` create surface — surface `BelowMinimumRegistrationAge` on the date-of-birth field (effectively required). _Acceptance:_ `CustomerCreateConsoleTest` — an under-age submit surfaces the rejection (no Customer created); a valid adult submit records `CustomerCreated`. The 5.1 guard is fail-fast BEFORE the txn + references `CreateCustomer::MINIMUM_REGISTRATION_AGE` (public const, default 18). **WATCH:** unlike 6.2 leg A (free surfacing), the `CreateCustomer` page's `createRejectionField()` likely maps ONE field (`email`→`DuplicateCustomerEmail`); `BelowMinimumRegistrationAge` must surface on `date_of_birth` → 6.3 may need a REAL page change (a two-guard field mapping). Grep `CustomerCreateConsoleTest` + `CustomerResource` create form + `CreateCustomer` page's `createRejectionField()` FIRST.
- **Scope after 6.3:** 6.4 (registration-flow SELECT excluding latent `open_registration` + optional `assertFormFieldDoesNotExist('invite_only')`; invite_only lang leg FULLY pre-done by 2.3/4.3) → §7 close (7.1/7.2/7.3, human-gated, NOT part of loop).

## Blockers & Decisions Needed
- None. Change APPROVED; branch `ralph/parties-module-k-br-guards`.
- **Append memory files via the Edit tool, not `cat >>` heredocs** (git-guardrails hook false-positives on spec-path strings). progress.md + hot.md need a prior Read of the edit region before Edit/Write (a truncated initial read / SessionStart injection does NOT satisfy it).

## Open Patterns
(full form in progress.md `## Codebase Patterns`)
- **Console guard-surfacing can be ZERO page code (6.2):** if a prior task wired the guard + `createRejectionField` + base catch, the console leg = ONLY the picker narrowing + a forced-out-of-option reject test (Filament passes an out-of-option Select value straight to the action). Active-only picker = private+non-reactive when NOT parent-scoped; SPLIT from the ALL-values list-filter helper (existing rows under since-non-active refs stay filterable). Non-lifecycle preference affordance = bespoke `Action->form([Toggle])->action(surfaceLifecycleOutcome→AuditOnlyAction)`, UNGATED, reusing `SurfacesDomainActions`. New `*KitKeys()` entries auto-enroll in the IT-distinct dataset → author EN+IT DISTINCT.
- Earlier patterns (in progress.md): operator-console create surface operand-enum Select + reactive active-picker w/ the ACTION as server floor (6.1) · model `updating` conditional-state lock (5.2) · fail-fast boundary input-gate + migrate-omitting-callers (5.1) · cascade reusing audit-only Action + from-state filter at the query (4.4) · model `saving` guard on EVERY write path (4.3) · audit-only preference writer + Larastan-non-null-`first()`→ternary (4.2) · required-reference-guard UNCONDITIONAL (4.1) · value-domain-reject vs business-rule-guard (3.1).
