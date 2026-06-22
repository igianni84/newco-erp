---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-22
---

# Hot Cache

## Last Updated
**2026-06-22 (`operator-console-parties-kyc-sanctions` — ralph loop RUNNING; task 2.2 done, 5/13).** Added the three KYC write-through `it()` blocks to `CustomerKycSanctionsConsoleTest` (test-only — verbs shipped in 2.1): (a) `callAction('requireKyc')` on an `active` Customer → `pending` + `kyc_required` + a system `kyc` Hold `active` + `suspended`; asserts exactly one `CustomerHoldPlaced` (`entity_type=Hold`, `->sole()`) + one `CustomerSuspended` (`entity_type=Customer`), each `module=parties`/`actor_role=NewcoOps`/`actor_id->toEqual($operator->id)` (loose), and `where('name','like','CustomerKyc%')->count()===0`. (b) verify-path Customer arranged THROUGH the real `RequireKyc` Action on an `active` Customer → `callAction('recordKycVerified')` → `verified` + Hold `lifted` + `active`; one `CustomerHoldLifted` + one `CustomerReactivated`, zero KYC events. (c) bare-factory `pending`+`kyc`-Hold Customer → `callAction('recordKycRejected')` → `rejected`, Hold STILL `active`, `DomainEvent::count()===0` (audit-only). Each asserts its success notification.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (this session): full suite 1475/1475 (8063 assn, exit 0)** — SQLite (was 1472/8018; +3 tests / +45 assn). `--filter=CustomerKycSanctionsConsoleTest` 13/13 (75 assn). PHPStan max 0 errors; `pint --test` clean; `openspec validate --strict` valid. Diff = `CustomerKycSanctionsConsoleTest.php` only (test-only); no production source, no `spec/`/`openspec/specs/`/`tests/Architecture/`, no migration, no composer dep. `ModuleBoundariesTest` UNCHANGED (no operand-enum import yet — that's 3.x).
- Full suite OOMs at PHP default 128 MB in result *parsing* (not a failure) — run pest with `php -d memory_limit=-1`. `--filter` + phpstan run fine at default. PG17 ritual is task 4.2 (not run this slice).

## Active Change & Next Task
- **`operator-console-parties-kyc-sanctions` — APPROVED, IN PROGRESS (5/13).** Delta on `operator-console`: 2 ADDED (KYC; sanctions) + 2 MODIFIED Customer reqs; 13 tasks / 5 groups. Group 1 (prep) + 2.1 (KYC visibility) + 2.2 (KYC write-through) DONE.
- **Next task 2.3:** the reject FLOOR (design D4) in `CustomerKycSanctionsConsoleTest` — for a verb out of from-state prove BOTH halves: surface HIDES it (`assertActionHidden('recordKycVerified')` on a non-`pending` Customer) AND the domain INDEPENDENTLY rejects an out-of-band call (`expect(fn () => app(RecordKycVerified::class)->handle($id))->toThrow(IllegalKycTransition::class)` — `IllegalKycTransition` imported FREELY in the test), `kyc_status` + event-log unchanged — NOT `assertNotified(action_failed)` (hidden verb can't raise it). Repeat for `requireKyc` out of `not_required`/null, `recordKycRejected` out of `pending`. No-waive: `assertActionDoesNotExist('waiveKyc')` (design D8). Independence (D7): a `sanctions_status=passed` Customer keeps it through require→verify, no sanctions event. Then group 3 (sanctions form), 4 (PG17 chain), 5 (gates+memory).
- After all 13: review/merge → semantic-verify (GUIDE §2.7) → `openspec archive`.

## Blockers & Decisions Needed
- **None blocking task 2.3.** No open-ADR gate crossed (operator auth shipped; verbs invoke synchronous domain Actions).
- **Holds push gate STILL pending** (separate change) — origin/main push of the Holds archive+merge commits + `git branch -d ralph/operator-console-parties-holds` await Giovanni's go (classifier-gated). Local merge+archive already done.

## Open Patterns
- **Event-envelope test idiom** (pinned by `CustomerHoldsConsoleTest`, reused in 2.2): `Hold.scope_id`→`toBe($customer->id)` (int cast), but `DomainEvent.entity_id`→`toBe((string) …)` and `DomainEvent.actor_id`→`toEqual($operator->id)` (loose — PG bigint-as-string). Confirmed `entity_type`: `CustomerHoldPlaced`/`Lifted`→`Hold`; `CustomerSuspended`/`Reactivated`→`Customer`. To arrange a `suspended`+`kyc`-Hold Customer you MUST start `active` then call `RequireKyc` (require on `pending` throws).
- **kyc-sanctions landmines:** (1) KYC verbs **event-silent** — assert the coupled Hold/status events, NEVER a `CustomerKyc%` event (D7); (2) `KycStatus`=state enum (cast `->value`, never imported); `SanctionsStatus`/`ScreeningTriggerSource`=operand enums (imported in 3.x — the carve-out, `ModuleBoundariesTest` UNCHANGED); (3) reject = surface-hides + domain-`toThrow` (D4), not `action_failed`; (4) chain-test (4.1) asserts exactly 5 events (`toEqualCanonicalizing` + `toHaveCount(5)`).
- **Form-less visibility-gated verb** + **i18n front-load discipline**: both consolidated in this change's `progress.md ## Codebase Patterns`.
