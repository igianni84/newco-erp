---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-07
---

# Hot Cache

## Last Updated
**2026-07-07 — `parties-module-k-br-guards` ralph loop RUNNING. Task 4.4 (RM-19) DONE, committed — 14/23; §4 COMPLETE.** `RetireProducer` now performs the § 10.2 Profile-leg cascade: after the club-sunset loop it drives the shipped audit-only `CancelProfile` for every `Active`/`Lapsed` Profile under a just-sunset Club (batch `Profile::whereIn('club_id', $activeClubs->pluck('id'))->whereIn('state',[Active,Lapsed])`), stamping `OFFBOARDING_CANCELLATION_REASON='producer_offboarding'` (a `public const`), all in the one txn. NO new event (leaf audit-only propagates → `%ProfileCancelled%===0`), NO migration.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Task 4.4 full loop **green**: focused `ProducerLifecycleTest` 22/22 → SQLite full suite **2037/2037** (2035 baseline +2 new) · PHPStan max **0** · Pint clean · `openspec validate --strict` valid.
- **No PG17 run for 4.4** — no schema/SQL; a within-module `whereIn('club_id',…)->whereIn('state',…)` read on columns the baseline already exercises on PG17 (as 3.2/3.3/4.1/4.2). Empty-parent edge: `whereIn(col,[])` = safe no-rows no-op on both engines. **PG17 recipe (DB-schema tasks only):** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest` (`pg` container UP on :55432). Full suite via `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs at 128 MB).

## Active Change & Next Task
- **`parties-module-k-br-guards` — 14/23 done. NEXT = task 5.1 (Identity-6):** age-gate in `CreateCustomer` → wires the 4th 2.4 exception `BelowMinimumRegistrationAge::belowMinimum(int)`/`missingDateOfBirth(int)` (call verbatim — fixed API). Reject a `date_of_birth` implying age < a configurable platform min-age (default 18) AND a null DOB, BEFORE any Customer/Account/`CustomerCreated`. Add the min-age constant (mirror the RM-02 enhanced-KYC threshold config pattern). Grep `CreateCustomer` callers for under-18/null DOB + migrate; `CustomerFactory` DOB `1990-01-01` keeps the suite green. _Acceptance:_ `CustomerTest`/`RegistrationAgeGateTest`. PII discipline: age-gate copy interpolates ONLY `:min_age` (never DOB/derived age).
- **Scope after 5.1:** 5.2 (Producer-5 `Producer::booted()` `updating` content-lock → wires the 5th 2.4 exc `ProducerReviewGovernedContentLocked::whileActive` — the SIBLING model-guard to 4.3's Club `saving` guard, RM-24 shape) → §6 console+i18n (6.1–6.4; 6.4 invite_only leg fully pre-done by 4.3, only the registration-flow SELECT remains) → §7 close (human-gated, NOT part of loop).

## Blockers & Decisions Needed
- None. Change APPROVED; branch `ralph/parties-module-k-br-guards`.
- **Append memory files via the Edit tool, not `cat >>` heredocs** (git-guardrails hook false-positives on spec-path strings).

## Open Patterns
(full form in progress.md `## Codebase Patterns`)
- **Cascade orchestration reusing an audit-only Action + from-state filter AT THE QUERY (4.4):** a cascade leg driving an EXISTING transition Action injects it as a dep + reuses it verbatim (no new Action/event — the leaf's audit-only property propagates, `%event%===0`). The `whereIn('state',[admissible])` filter is LOAD-BEARING for atomicity: the child's from-state guard throws on a wrong state → in one txn that rolls back the WHOLE parent. Scope to entities transitioned in THIS cascade (`$activeClubs`, not since-closed). No relation → walk by FK; `whereIn(col,[])` engine-safe no-op. Reason = plain uncast domain token as `public const` (test-referenced, DRY; i18n-12 binds display copy, not stored tokens). Migration-audit risk = STATE assertions (not event counts); a child-free integration test stays green unamended.
- Earlier patterns (in progress.md): model `saving` guard on EVERY write path (4.3) · audit-only preference writer + Larastan-non-null-`first()`→ternary (4.2) · required-reference-guard UNCONDITIONAL (4.1) · business-rule guard on related-entity state (3.2) · behaviour-inversion guard (3.3) · value-domain-reject vs business-rule-guard (3.1) · cast blast-radius (2.1) · additive-NOT-NULL-needs-default (2.2) · column-DROP-atomic-sweep (2.3) · localized-guard-exception-SoD-shape (2.4).
