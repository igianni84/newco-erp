---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-07
---

# Hot Cache

## Last Updated
**2026-07-07 — `parties-module-k-br-guards` ralph loop RUNNING. Task 5.1 (Identity-6 registration age gate) DONE, committed — 15/23; §5 half done (1/2).** `CreateCustomer` now age-gates: a fail-fast guard at the TOP of `handle()`, BEFORE `DB::transaction()` — null `date_of_birth` → `BelowMinimumRegistrationAge::missingDateOfBirth(MIN)`; a DOB after the `CarbonImmutable::now()->subYears(MIN)` cutoff → `belowMinimum(MIN)` (INCLUSIVE — exactly-18 admitted). Min-age = new `public const MINIMUM_REGISTRATION_AGE = 18` (RM-02 threshold-const shape, PUBLIC for test/console DRY). NO migration/schema/event (`date_of_birth` already present). Wired the 4th of 5 2.4 exceptions.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Task 5.1 full loop **green**: focused `RegistrationAgeGateTest` 6/6 + migrated files 29/29 → SQLite full suite **2043/2043** (2037 baseline +6 new) · PHPStan max **0** · Pint clean · `openspec validate --strict` valid.
- **No PG17 run for 5.1** — pure-PHP boundary guard, NO schema/SQL (the `date_of_birth` column + `immutable_date` cast were baseline-verified on PG17). **PG17 recipe (DB-schema tasks only):** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest` (`pg` container UP on :55432). Full suite via `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs at 128 MB).

## Active Change & Next Task
- **`parties-module-k-br-guards` — 15/23 done. NEXT = task 5.2 (Producer-5):** `Producer::booted()` `updating` guard throwing the LAST 2.4 exception `ProducerReviewGovernedContentLocked::whileActive(int)` (call verbatim) when `name`/`description`/`region`/`website` is dirty while the persisted `status = active`. The **SIBLING model-guard to 4.3's Club `saving` guard** (RM-24 shape — mirror `decisions/2026-07-02-adopt-dec-023-product-type-immutable.md`). A `draft` Producer edits freely; a status/KYC-only update passes (no false positive). _Acceptance:_ `ProducerContentLockTest` — drive via `setRawAttributes`/`forceFill` past casts; verify all existing Producer updates touch only `status`/`kyc_status` (grep first). Interim lock — full re-arm deferred (ADR 1.3).
- **Scope after 5.2:** §6 console+i18n (6.1 ProducerAgreement cadence-Select + active-Club picker; 6.2 Profile surface + auto-renew toggle; 6.3 Customer DOB age-gate surface — references `CreateCustomer::MINIMUM_REGISTRATION_AGE`; 6.4 invite_only leg fully pre-done by 4.3, only the registration-flow SELECT remains) → §7 close (human-gated, NOT part of loop).

## Blockers & Decisions Needed
- None. Change APPROVED; branch `ralph/parties-module-k-br-guards`.
- **Append memory files via the Edit tool, not `cat >>` heredocs** (git-guardrails hook false-positives on spec-path strings).

## Open Patterns
(full form in progress.md `## Codebase Patterns`)
- **Fail-fast boundary input-gate + migrate-every-omitting-caller (5.1):** a gate on an INPUT operand (age from `date_of_birth`) is a value-domain reject placed BEFORE the txn (no DB read; the 3.1 placement) → "nothing created" is structural. Date math on the immutable `now()->subYears(MIN)` cutoff + `$dob->greaterThan($cutoff)` (the `RenewProfile.php:70` idiom — input compared, never mutated; INCLUSIVE boundary). Threshold = `public const` (RM-02 shape, public for DRY — 4.4 precedent; test derives boundary DOBs from it). Blast radius: EVERY action caller that omitted the operand now throws → grep + migrate each; a DIRECT-`create()` seeder/factory BYPASSES the action-gate (no migration). Pest×PHPStan trap: assert a thrown message via `->toThrow(Class, $substr)`, NOT `try/catch`+`$this->fail()`+chained `->not->toContain`.
- Earlier patterns (in progress.md): cascade reusing audit-only Action + from-state filter at the query (4.4) · model `saving` guard on EVERY write path (4.3) · audit-only preference writer + Larastan-non-null-`first()`→ternary (4.2) · required-reference-guard UNCONDITIONAL (4.1) · business-rule guard on related-entity state (3.2) · behaviour-inversion guard (3.3) · value-domain-reject vs business-rule-guard (3.1) · localized-guard-exception-SoD-shape (2.4).
