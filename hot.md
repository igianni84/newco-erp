---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-18
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-18 (ralph iter 7 — `parties-membership-activation` T3.1 DONE → CHANGE COMPLETE 7/7).** The close: `MembershipActivationChainTest` drives the WHOLE slice through the REAL Actions on one Customer / two Clubs — `CreateCustomer` → set 3 acceptance timestamps (direct update — stands in for the deferred registration surface, L1) → `RecordCustomerScreening`(passed,onboarding) → `ActivateCustomer` → `CreateProfile`×2 → `ApproveProfile`(clubC, OC locks) → `ApproveProfile`(clubD, NO 2nd lock) → `ActivateProfile`(clubC). Asserts the EXACT 7-event multiset `{CustomerCreated, ProfileCreated×2, CustomerOnboardingScreeningPassed, CustomerActivated, OriginatingClubLocked, ProfileActivated}` + **0** `ProfileApproved`/`ProfileRejected`/`MembershipApprovedByProducer`; OC lock once (`sole()`, pinned clubC); Account stays `active`. **Docs:** `CONTEXT.md` extended (Parties intro/Customer/Profile/OC entries updated, 2 new glossary terms, new "Parties demand-side activation events — payload contract" subsection: 3 payloads + deferred seams). No production code (all Actions+events shipped T1–T2).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green 836/836** (was 833; +3 from `MembershipActivationChainTest`, 17 assertions) on SQLite; phpstan 0; pint clean. **Cross-engine close: the ENTIRE suite 836/836 on PG17** (docker `postgres:17`, port 55432, `php -d memory_limit=512M vendor/bin/pest`) — Parties Feature+Unit + `tests/Architecture/` + every other module. `openspec validate parties-membership-activation --strict` valid. `git diff main -- composer.json composer.lock` empty. The 5 other guard files (`SpineCreationChainTest`/`ComplianceChainTest`/`CustomerTest`/`HoldChainTest`/`HoldRegistryTest`) confirmed unamended vs main + green.
- Branch `ralph/parties-membership-activation`; T3.1 committed locally next (not pushed — human's call).

## Active Change & Next Task
- **`parties-membership-activation` — 7 / 7 tasks done → `<promise>CHANGE_COMPLETE</promise>`.** Shipped end-to-end: 1 additive migration (3 acceptance timestamps); 2 `Illegal*Transition` exceptions; 3 root activation events; 4 Actions (`ApproveProfile`/`DeclineProfile` audit-only + OC one-shot lock, `ActivateProfile`, `ActivateCustomer` composite gate); both archived-change guard tests narrowed in-task; the closing cross-engine chain + docs.
- **Next: HUMAN** reviews/merges the branch, then archives (`openspec archive parties-membership-activation --yes`) — RALPH does NOT archive, merge, or push. No further ralph task on this change.
- **Follow-on seams (named, NOT started — human picks the next change):** `parties-membership-suspension` (Hold→`suspended`, `Active→Suspended|Lapsed|Cancelled|Inactive`, Account-status transitions); `parties-hero-package` (§13 capacity invariant + `Applied→WaitingList`, after Module A); `parties-customer-segments`; `club-credit`; `parties-operator-console` / producer portal.

## Blockers & Decisions Needed
- None. All deferred concerns stay deferred as named seams (NOT reads): §13 Hero-Package capacity → Module A; `MembershipFeePaid` listener + Club Credit → Module E; `OriginatingClubLocked` consumers (Module S settlement / E D19 accrual / HubSpot) → S/E. The 3 acceptance columns still have no production setter (deferred registration surface).

## Open Patterns
- **NEW (T3.1): the closing-chain test includes spine `*Created` when they're part of THIS slice's contract.** Rule-#42 (drive THROUGH Actions, assert the emergent event-SET) with a twist: when activation composes on a genuinely-created Customer+Profiles, use the REAL `Create*` Actions (not factories) and assert the full MULTISET (`pluck('name')->all()`, `ProfileCreated`×2) — opposite of the compliance/spine closes (factories, distinct-set) whose point was "ONLY the compliance/creation events". Decide by "is a `*Created` part of this slice's contract?". A precondition with no production setter (the 3 acceptance timestamps) is set directly in the chain (stands in for the deferred surface); KYC left NULL (cleared, DEC-071) so no `kyc` Hold event pollutes the set.
- **CONTEXT.md term-resolution at a slice close:** flip glossary entries that said "deferred" → "implemented by `<change>`", add resolved terms, add a payload-contract subsection mirroring the siblings (recorder + same-tx + module + ActorContext + PII-free + root; table; deferred-seams list). Definitions only.
- **`originating_club_id` reads use loose `toEqual`** (uncast bigint FK — trap 6); event-payload ids stay `toBe` (jsonb ints, trap 3). **Full suite needs `php -d memory_limit=512M vendor/bin/pest`** (arch-OOM on both engines); focused per-file runs clean at default memory.
- **PG17 close recipe** (knowledge/testing/rules.md): `docker run -d --name pg … -p 55432:5432 postgres:17` → `DB_CONNECTION=pgsql … php -d memory_limit=512M vendor/bin/pest` → `docker rm -f pg`. Run `vendor/bin/pest` directly (the pao stdout-teardown fatal swallows the artisan JSON summary on PG).
