---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-19
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-19 (ralph — `parties-membership-suspension` task 4.1 GREEN, committed on `ralph/parties-membership-suspension`).** Wired the headline **Hold→`suspended` coupling, PLACE side** (ADR 2026-06-19) into `PlaceHold`: after recording `CustomerHoldPlaced` and **in the same transaction**, it `match($scope_type)` → a per-scope helper that **pre-checks the from-state** (lock-free `->first()`, `?->`) and invokes the matching explicit Suspend Action only when suspendable — `customer`/active ⇒ `SuspendCustomer` (cascade), `account`/active ⇒ `SuspendAccount` (audit-only), `profile`/Active ⇒ `SuspendProfile`. A Hold on a non-suspendable scope (onboarding `kyc` Hold on a `pending` Customer, a Hold on an `Applied`/already-`suspended` scope) records the Hold and transitions nothing. Injected the 3 Suspend Actions via constructor (the `RequireKyc → PlaceHold` Action-calling-Action precedent; no DI cycle). **9 of 11 tasks done.**

## Build & Quality Status
- Stack unchanged: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **937/937 green on SQLite** (931 baseline + 6 new `HoldStatusCouplingPlaceTest`) via `php -d memory_limit=512M vendor/bin/pest` (artisan test OOMs at 128M). PHPStan 0, Pint clean, `openspec validate parties-membership-suspension --strict` valid, `composer.json/lock` untouched.
- **Verified on PG17** (docker `postgres:17` :55432): `tests/Feature|Unit/Modules/Parties` **440/440** — the nested PlaceHold→Suspend* SAVEPOINT commits atomically with the Hold; the place→cascade causation-child linkage holds; the audit-only Account suspend records zero events on PG.

## Active Change & Next Task
- **`parties-membership-suspension` — IN PROGRESS on `ralph/parties-membership-suspension`.** Tasks 1.1–1.3 + 2.1–2.3 + 3.1–3.2 + 4.1 done (9 of 11).
- **Next: task 4.2** — Coupling on LIFT. In `LiftHold` + `RecordKycVerified`, after the Hold is lifted and **in the same transaction**, re-query coverage for the lifted Hold's `scope_type` (the `(scope) OR (its Customer)` shape — `DatabaseComplianceStatusReader::activeHoldTypesForScopes()` re-read under lock, `count()>0`); if the scope is `suspended`/`Suspended` **and no other active Hold covers it**, invoke the matching `Reactivate*` (customer ⇒ `ReactivateCustomer` cascade-restore; account ⇒ `ReactivateAccount`; profile ⇒ `ReactivateProfile`). Apply the SAME restore in `RecordKycVerified` after its system `kyc`-lift (no-op at onboarding — Customer was `pending`, never suspended; live on a post-activation re-screen). **Remove** the "performs NO status transition … deferred demand-side seam" docblock note in `LiftHold.php` (≈ L49-51). `grep -rn 'LiftHold\|RecordKycVerified' tests/` to re-derive blast radius; the multi-Hold partial-lift "still suspended" case is the subtle one. **DB + nested-tx → PG17 run is load-bearing.**

## Blockers & Decisions Needed
- None. Reviewer items remain resolved (design.md/ADR): coupling = coverage-recompute; `CloseCustomer` no cascade; cascade `ProfileSuspended` = causation child.
- Still open (human's call): **push `main` → `origin`** + delete merged `ralph/parties-membership-activation` branch.

## Open Patterns
- **Hold→status coupling on PLACE** (4.1): inject the Suspend Actions; after `CustomerHoldPlaced`, pre-check the from-state lock-free + invoke the explicit Suspend Action only when suspendable (the pre-check stops the Action's guard from throwing → rolling back the Hold). **The writer locks** — `PlaceHold` doesn't lock the scope; the Suspend Action's `lockForUpdate` re-read is a SAVEPOINT under the placement tx. 4.2 LIFT is the mirror (re-query coverage, restore iff uncovered).
- **Guard inversion = flip ONLY what breaks; the `active`-born Account breaks first.** A "Hold drives no status transition" guard breaks on its Account assertion (Customer born `pending`, Profile `applied` — non-suspendable); flip Account→`suspended`, KEEP the other two, and the event-absence loop stays valid (Account suspend is audit-only). `HoldChainTest` needed NO amendment (all Holds Customer-scope on a `pending` Customer). `grep` + RUN before amending — never pre-amend a green guard.
