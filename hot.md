---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-19
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-19 (ralph — `parties-membership-suspension` task 2.2 GREEN, committed on `ralph/parties-membership-suspension`).** Shipped the Profile lapse/renew grace pair: `LapseProfile` (`Active → Lapsed`, stamps `lapsed_at = CarbonImmutable::now()` → root `ProfileExpired`, NOT `ProfileLapsed`) + `RenewProfile` (`Lapsed → Active`, DEC-034 30-day INCLUSIVE grace guard, clears `lapsed_at` → root `ProfileRenewed`, NOT `ProfileReactivated`). Both the verbatim self-edge template (inject recorder+actor; `DB::transaction` → `lockForUpdate` → from-state/grace guard FIRST → `update` → root `record`). Validity-expiry + `MembershipFeePaid` triggers = documented Module-E/scheduler seams (no Module-E class fabricated). Guard narrowed: both added to `SupplyLifecycleChainTest`'s `$demandSideStatusTransitions` whitelist. **5 of 11 tasks done.**

## Build & Quality Status
- Stack unchanged: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **890/890 green on SQLite** (875 baseline + 15 new `ProfileLapseGraceTest`) via `php -d memory_limit=512M vendor/bin/pest` (artisan test OOMs at 128M). PHPStan 0, Pint clean, `openspec validate parties-membership-suspension --strict` valid, `composer.json/lock` untouched.
- **Verified on PG17** (docker `postgres:17` :55432): `tests/Feature|Unit/Modules/Parties` **393/393** — `lapsed_at` timestamptz round-trips through the `immutable_datetime` cast; the frozen-clock grace boundary is deterministic.

## Active Change & Next Task
- **`parties-membership-suspension` — IN PROGRESS on `ralph/parties-membership-suspension`.** Tasks 1.1–1.3 + 2.1–2.2 done (5 of 11).
- **Next: task 2.3** — `CancelProfile` (audit-only) + `DeactivateProfile`. Same self-edge template, PLUS: `CancelProfile(int $id, ?string $reason = null)` guards `state ∈ {Active, Lapsed}` else `::cannotCancel`; `update(['state'=>Cancelled,'cancellation_reason'=>$reason])`; **records NO event** (audit-only — § 15.2 names no `ProfileCancelled`, L2). `DeactivateProfile` guards `state===Active` else `::cannotDeactivate`; `update(['state'=>Inactive])`; root `ProfileInactive`. **Narrow the guard** (L10): add BOTH to the SAME `$demandSideStatusTransitions` whitelist (CancelProfile is whitelisted even though event-silent). **Test (`ProfileCancellationTest.php`):** assert `CancelProfile` leaves `DomainEvent` count UNCHANGED + a fresh `CreateProfile` for the same Customer–Club pair succeeds (partial index admits terminal); `grep -rn 'ProfileCancelled' app/` stays empty. **DB-touching → PG17 run required.**

## Blockers & Decisions Needed
- None. Reviewer items remain resolved (design.md/ADR): Hold coupling = coverage-recompute (ADR 2026-06-19); `CloseCustomer` does NOT cascade Profiles; cascade `ProfileSuspended` = causation child of `CustomerSuspended` (pass `$root->id` as `causationId`+`correlationId`).
- Still open (human's call): **push `main` → `origin`** + delete merged `ralph/parties-membership-activation` branch.

## Open Patterns
- **Self-edge status-transition Action = `ActivateProfile`/`SuspendProfile` template** (progress.md Codebase Patterns): `lockForUpdate` re-read → from-state guard FIRST → state-preserving `update` → root `record(::NAME,…,::payload)`. Time-windowed variant (lapse/grace): also write/clear `lapsed_at`; `RenewProfile`'s INCLUSIVE 30-day guard uses strict `greaterThan` on `lapsed_at?->addDays(30)`.
- **Two traps that pass the full suite but fail PHPStan / isolated-run:** `CarbonImmutable::create()` is `|null` → use `::parse('… …','UTC')`; a sibling's Pest helper fails run-in-isolation → uniquely-name a file-local helper (`lapseGraceActiveProfile()`) and run the new file ALONE once. Assert a persisted `timestamptz` as an instant via `?->equalTo($now)` (trap #4), ids same-source (trap #6).
- **Guard blast radius:** a Profile self-edge trips ONLY `SupplyLifecycleChainTest`'s exact-set whitelist (add the Action name). `ComplianceIndependenceTest` stays green for Profile edges; its forbidden list drops `SuspendCustomer`/`CloseCustomer`/Account at 3.x; `HoldRegistryTest`/`HoldChainTest` invert ONLY at coupling 4.x. Always `grep -rn '<symbol>' tests/` to re-derive — don't trust design line numbers. **PG recipe:** docker `postgres:17` :55432 → `DB_CONNECTION=pgsql … php -d memory_limit=512M vendor/bin/pest` (artisan OOMs at 128M).
