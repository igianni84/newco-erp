---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-07
---

# Hot Cache

## Last Updated
**2026-07-07 — `parties-module-k-br-guards` ralph loop RUNNING. Task 6.3 (CustomerResource create surface — age gate on the date-of-birth field) DONE, committed — 19/23; §6 console now 3/4.** The FIRST two-field create-rejection routing. The `CreateCustomer` action raises `BelowMinimumRegistrationAge` (null/under-min DOB, pre-txn) AND `DuplicateCustomerEmail` (in-txn) — both `RuntimeException`s — but the shared base `handleRecordCreation` maps EVERY `RuntimeException` to ONE `createRejectionField()` (`email`), so the age gate would wrongly land on `email`. FIX in `CreateCustomer::createViaAction()`: wrap the action call in `try/catch (RuntimeException)`, DISCRIMINATE the age gate by re-deriving its pure-input condition (`$dob === null || $dob->greaterThan(CarbonImmutable::now()->subYears(CreateCustomerAction::MINIMUM_REGISTRATION_AGE))`) → re-raise `ValidationException` on `data.date_of_birth` carrying the domain's OWN `$exception->getMessage()`; else re-throw → base → `email`. Can't `instanceof` the exception (`Parties\Exceptions` ∉ the `{Models,Actions,Enums}` carve-out), so the discriminator re-uses the action's `public const` (importable via the Actions alias). ZERO new i18n key (reuses the domain message). NO Resource form change — "effectively required" is EMERGENT (domain rejects null/under-age, console surfaces it there), not schema `->required()`.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Task 6.3 full loop **green**: focused `CustomerCreateConsoleTest` 6/6 (60 assertions) → SQLite full suite **2077/2077** (2074 +3) · PHPStan max **0** · Pint clean · `openspec validate --strict` valid.
- **No PG17 run for 6.3** — console/form only, NO schema/SQL. **PG17 recipe (DB-schema tasks only):** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest` (`pg` container on :55432). Full suite via `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs at 128 MB).

## Active Change & Next Task
- **`parties-module-k-br-guards` — 19/23 done. §2–5 COMPLETE; §6 console = 3/4. NEXT = task 6.4 (the LAST loop task):** `ClubResource` + `CreateClub` page + `ClubsRelationManager` — the registration-flow SELECT must offer only the THREE launch values, EXCLUDING the latent `open_registration` (the enum still carries 4 cases; 4.3's `Club::booted()` `saving` guard is the server floor — 6.4 just narrows the console picker). Optionally add `assertFormFieldDoesNotExist('invite_only')` as the "no invite-only field" positive guard. _Acceptance:_ `ClubCreateConsoleTest`/`ClubConsoleI18nTest` — no invite-only field; the select excludes `open_registration`; i18n sink-scan green.
- **invite_only + i18n legs FULLY pre-done (2.3/4.3):** the field is already gone from `ClubResource`/`CreateClub`/`ClubsRelationManager`; the `operator_console.php` `fields.invite_only` key + `ClubConsoleI18nTest` L48/L62 already dropped. 6.4's ONLY substantive work = the registration-flow picker narrowing (+ the optional assertion). Grep `ClubResource` create form + `CreateClub` page + `ClubsRelationManager` for the `registration_flow_type` Select first.
- **After 6.4:** §7 (7.1 full quality gate incl. PG17; 7.2 traceability+i18n sweep; 7.3 Remediation_Tracker/hot/log) is HUMAN-GATED close (§2.7 — NOT part of the loop). After 6.4 flips, ALL loop tasks are `[x]` → emit `<promise>CHANGE_COMPLETE</promise>`.

## Blockers & Decisions Needed
- None. Change APPROVED; branch `ralph/parties-module-k-br-guards`.
- **Append memory files via the Edit tool** (git-guardrails hook false-positives on spec-path strings in `cat >>` heredocs). progress.md/hot.md Edits need a prior Read of the edit region (a truncated initial read / SessionStart injection does NOT satisfy it).

## Open Patterns
(full form in progress.md `## Codebase Patterns`)
- **Two-field create-rejection routing WITHOUT importing the exception (6.3):** catch by base `RuntimeException` in `createViaAction()`, discriminate by RE-DERIVING the guard's pure-input condition (anchored to the action's `public const`), re-raise `ValidationException` on the target field with the domain's own message (zero console i18n) — the `CreateProductReference` idiom generalized. "Effectively required" = EMERGENT (domain rejects null → console routes to field), never `->required()`/`->maxDate()` (those short-circuit before the action, breaking "the domain raises X"). Test: freeze clock + derive boundary DOBs from the const; DISTINCT freeze-helper name (never redeclare a sibling's top-level fn).
- Earlier §6 patterns: console guard-surfacing can be ZERO page code + non-lifecycle preference affordance (6.2) · operand-enum Select + reactive active-picker w/ the ACTION as server floor (6.1). §2–5 patterns (in progress.md): model `updating`/`saving`/`booted` guards (5.2/4.3), fail-fast boundary input-gate (5.1), cascade reusing audit-only Action (4.4), audit-only preference writer (4.2), required-reference-guard UNCONDITIONAL (4.1), value-domain-reject vs business-rule-guard (3.1).
