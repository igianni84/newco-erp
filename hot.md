---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-07
---

# Hot Cache

## Last Updated
**2026-07-07 — `parties-module-k-br-guards` ralph loop RUNNING. Task 5.2 (Producer-5 content lock) DONE, committed — 16/23; §2–5 (all guard-wiring) COMPLETE.** `Producer::booted()` now carries a `static::updating` guard: `getOriginal('status') === ProducerStatus::Active && isDirty(self::REVIEW_GOVERNED_FIELDS)` → `ProducerReviewGovernedContentLocked::whileActive($id)`. New `public const REVIEW_GOVERNED_FIELDS = ['name','description','region','website']`. The RM-24 immutability pattern (Catalog `ProductMaster::booted()`) + a persisted-status gate. **ALL FIVE 2.4 exceptions are now wired.** NO migration/schema/event.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Task 5.2 full loop **green**: focused `ProducerContentLockTest` 8/8 (18 assertions) → SQLite full suite **2051/2051** (2043 baseline +8 new) · PHPStan max **0** · Pint clean · `openspec validate --strict` valid.
- **No PG17 run for 5.2** — pure-PHP model guard, NO schema/SQL (the `status`/`kyc_status` columns + enum casts were baseline-verified on PG17). **PG17 recipe (DB-schema tasks only):** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest` (`pg` container UP on :55432). Full suite via `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs at 128 MB).

## Active Change & Next Task
- **`parties-module-k-br-guards` — 16/23 done. §2–5 guard-wiring COMPLETE. NEXT = task 6.1 (first CONSOLE task):** `ProducerAgreementResource` create surface — settlement-cadence free-text `TextInput` → a `Select` over `SettlementCadence` (default `quarterly`, constructs the operand enum); the Club picker offers only the Producer's `active` Clubs; surface the `ProducerAgreementClubNotActive` + out-of-set-cadence rejections. _Acceptance:_ `ProducerAgreementCreateConsoleTest` — cadence is a select (not free text), non-active Club not selectable/rejected + surfaced, valid create still records `ProducerAgreementCreated`. Grep the resource + console test first; `SurfacesDomainActions::surfaceLifecycleOutcome` catches `RuntimeException` by base type (the 3.3 console-reject precedent).
- **Scope after 6.1:** 6.2 (ProfileResource surface `ClubNotAcceptingMemberships` + `SetProfileAutoRenew` toggle); 6.3 (CustomerResource DOB age-gate surface, references `CreateCustomer::MINIMUM_REGISTRATION_AGE`); 6.4 (invite_only leg FULLY pre-done by 4.3 — only the registration-flow SELECT excluding latent `open_registration` + optional `assertFormFieldDoesNotExist('invite_only')` remain) → §7 close (human-gated, NOT part of loop).

## Blockers & Decisions Needed
- None. Change APPROVED; branch `ralph/parties-module-k-br-guards`.
- **Append memory files via the Edit tool, not `cat >>` heredocs** (git-guardrails hook false-positives on spec-path strings). progress.md needs a prior Read of the edit region before Edit.

## Open Patterns
(full form in progress.md `## Codebase Patterns`)
- **Model `updating` conditional-state-gate immutability guard (5.2):** `updating` (not `saving`) — draft-birth content is VALID, locks only after publish (contrast 4.3's Club `saving` on an always-invalid value). Gate on PERSISTED status via `getOriginal('status')` (returns the CAST enum; `mixed === EnumCase` is PHPStan-clean). Field-set as a shared `public const`; `isDirty(array)` = any-dirty. No-false-positive is STRUCTURAL — the RM-08 writer-sweep (all Producer writers touch only `status`/`kyc_status`; the Catalog projector `$state->update` is a boundary-clean read-model) proves it, the full suite is the empirical floor. Pint trap: a cross-module `{@see FQCN}` auto-imports → use backticked prose (the 2.1 enum-docblock rule generalizes).
- Earlier patterns (in progress.md): fail-fast boundary input-gate + migrate-omitting-callers (5.1) · cascade reusing audit-only Action + from-state filter at the query (4.4) · model `saving` guard on EVERY write path (4.3) · audit-only preference writer + Larastan-non-null-`first()`→ternary (4.2) · required-reference-guard UNCONDITIONAL (4.1) · business-rule guard on related-entity state (3.2) · behaviour-inversion guard (3.3) · value-domain-reject vs business-rule-guard (3.1) · localized-guard-exception-SoD-shape (2.4).
