---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-18
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-18 (ralph — `parties-holds` iteration 7/30, task 4.1 DONE).** Wired the one MODIFIED behaviour — the `kyc`-Hold coupling. `RequireKyc` now injects `PlaceHold` and (after `→ pending`) calls `$this->placeHold->handle(HoldType::Kyc, HoldScope::Customer, $customer->id)` in-tx (reason null; PlaceHold's nested tx is a savepoint → atomic — the `RetireProducer → SunsetClub` precedent). `RecordKycVerified` injects `DomainEventRecorder`+`ActorContext` and INLINES a system-lift (it CANNOT reuse `LiftHold` — operator path rejects `kyc` via `autoManaged`): resolve actor + one `CarbonImmutable::now()`, query active `kyc` Holds (`->where(...Customer->value/...Kyc->value/...Active->value)->lockForUpdate()->get()`), per Hold `update(lifted…, lift_reason=>null)` + `record(CustomerHoldLifted::NAME,…)`. `RecordKycRejected` UNCHANGED (Hold stays — § 9.1). Both docblocks flipped. No new Action (system-lift inline) → whitelist untouched.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 754/754 SQLite** (3566 assertions, +1 net from 753). PHPStan max 0 · Pint clean · `openspec validate parties-holds --strict` valid. Composer untouched; no protected files; no migration, no `lang/` change this task.
- **PG17 verified** (docker `postgres:17`:55432): `tests/Feature/Modules/Parties` + `tests/Architecture` **184/184** (1296 assertions, +1). The flipped lifecycle/independence/chain tests ARE the PG proof — the `kyc` Hold round-trips `active → lifted` driven by the KYC Actions; both Hold events record by-key PII-free. Container torn down.

## Active Change & Next Task
- **`parties-holds` — IN PROGRESS (7/11 done).** Phase 1 + 2.1 + Phase 3 (PlaceHold/LiftHold) + 4.1 (KYC coupling) COMPLETE. Remaining: 5.1, 6.1, 6.2, 6.3.
- **Next task: 5.1** — the read-API (design L6). NEW `app/Modules/Parties/Contracts/ComplianceStatus.php` (readonly DTO: `?SanctionsStatus $sanctionsStatus`, `list<HoldType> $activeHoldTypes`, `isClear(): bool` = sanctions `Passed` ∧ no active Hold — carries `HoldType`s, **NEVER** the `Hold` model). NEW `app/Modules/Parties/Contracts/PartyComplianceStatusReader.php` (interface: `forCustomer(int)`, `forProfile(int)`). NEW `app/Modules/Parties/Reads/DatabaseComplianceStatusReader.php` implementing CASCADE-at-read (`forProfile` = Profile's own active Holds ∪ parent-Customer active Holds + parent Customer's `sanctions_status` — BR-K-Hold-3; `forCustomer` = Customer-scope active Holds) — bind in `PartiesServiceProvider`. NO downstream enforcement surface (Module S/C/E consume later — scope guard). Test `tests/Feature/Modules/Parties/ComplianceReadApiTest.php`. **DB-touching → verify on PG17.**
- After 5.1: 6.1 registry/scope-guard test, 6.2 CONTEXT.md docs (no code), 6.3 full Hold-chain + cross-engine close.

## Blockers & Decisions Needed
- None. ADR `2026-06-18-hold-lift-discipline-per-type.md` governs the per-type lift; root `CLAUDE.md` Invariant #7 reword is the human's call at the gate (Protected). No open ADR gate stepped.
- **Noted (not blocking):** PlaceHold still does not resolve/validate the scope (design L1 risk note) — the one live caller (`RequireKyc`) places on an already-locked Customer; flagged for the gate. `payment` Holds have no live trigger this slice (the auto-lift signal is Module E — deferred seam).

## Open Patterns
- **KYC coupling is ASYMMETRIC (4.1).** `RequireKyc` REUSES `PlaceHold` (Action-calling-Action); `RecordKycVerified` INLINES a system-lift (can't reuse `LiftHold` — `autoManaged` rejects `kyc`). Enum-where uses `Enum::Case->value` (the `RetireProducer` precedent, NOT the bare enum).
- **PHPStan-max: `Collection::first()` is `Model|null`; `Builder::sole()` is non-null `Model`** — assert via `->sole()` (also asserts exactly-one), never chain off `->first()` without a null guard.
- **A handoff's "affected files" list is a HINT — grep the blast radius.** 4.1's 3.x handoff named 2 tests; `ComplianceChainTest` was a silent third (via `runComplianceChain()`). `grep -rn 'RequireKyc\b\|RecordKycVerified\b' tests` surfaced all 3. Recorded in `lessons.md`.
- **`%Kyc%` stays 0 after the coupling** — coupled events are `CustomerHold*`, not `*Kyc*`; KYC itself is still event-silent (design L3). Only `%Hold%`/total-count assertions move.
