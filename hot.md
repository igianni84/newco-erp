---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-19
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-19 (ralph — `parties-membership-suspension` task 2.3 GREEN, committed on `ralph/parties-membership-suspension`).** Shipped the Profile terminal pair, closing the Profile self-edge set: `CancelProfile(int $id, ?string $reason = null)` (`Active | Lapsed → Cancelled`, writes `cancellation_reason`, **records NO event** — AUDIT-ONLY, § 15.2 names no `ProfileCancelled`; the FIRST Action with NO recorder/actor injected at all) + `DeactivateProfile` (`Active → Inactive` → root `ProfileInactive`). Guard narrowed: both added to `SupplyLifecycleChainTest`'s `$demandSideStatusTransitions` whitelist; `ComplianceIndependenceTest` stayed green unamended (Profile self-edge writes no `originating_club_id`). **6 of 11 tasks done.**

## Build & Quality Status
- Stack unchanged: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **910/910 green on SQLite** (890 baseline + 20 new `ProfileCancellationTest`) via `php -d memory_limit=512M vendor/bin/pest` (artisan test OOMs at 128M). PHPStan 0, Pint clean, `openspec validate parties-membership-suspension --strict` valid, `composer.json/lock` untouched, no `ProfileCancelled.php` event file.
- **Verified on PG17** (docker `postgres:17` :55432): `tests/Feature|Unit/Modules/Parties` **413/413** — `cancellation_reason` uncast string round-trips; the partial index admits a fresh `applied` Profile after `cancelled`/`inactive` while still blocking on `suspended`/`lapsed` (non-terminal).

## Active Change & Next Task
- **`parties-membership-suspension` — IN PROGRESS on `ralph/parties-membership-suspension`.** Tasks 1.1–1.3 + 2.1–2.3 done (6 of 11).
- **Next: task 3.1** — `SuspendCustomer` (cascade) + `ReactivateCustomer` (cascade-restore, coverage-guarded). `SuspendCustomer`: guard `status===Active` else `IllegalCustomerTransition::cannotSuspend`; `update(status=Suspended)`; `$root = record(CustomerSuspended,…)`; **then** re-read Profiles `->lockForUpdate()->get()`, for each `Active` Profile `update(state=Suspended)` + `record(ProfileSuspended,…, causationId: $root->id, correlationId: $root->correlation_id)` — the FIRST **causation-child** events (L11). `ReactivateCustomer`: guard `Suspended`; restore each `Suspended` Profile to `Active` **iff** no active Hold covers it (reuse `DatabaseComplianceStatusReader::activeHoldTypesForScopes()` `(Profile) OR (its Customer)` shape, re-read under lock). **Guards:** add both to `SupplyLifecycleChainTest` whitelist AND **remove `SuspendCustomer` from `ComplianceIndependenceTest`'s forbidden list** (keep `ActivateAccount`/`Lock`/`Set`OriginatingClub). **DB-touching → PG17 run required.**

## Blockers & Decisions Needed
- None. Reviewer items remain resolved (design.md/ADR): Hold coupling = coverage-recompute (ADR 2026-06-19); `CloseCustomer` does NOT cascade Profiles; cascade `ProfileSuspended` = causation child of `CustomerSuspended`.
- Still open (human's call): **push `main` → `origin`** + delete merged `ralph/parties-membership-activation` branch.

## Open Patterns
- **Self-edge status Action template** (progress.md Codebase Patterns): `lockForUpdate` re-read → from-state guard FIRST → state-preserving `update` → root `record(::NAME,…,::payload)`. **Audit-only variant (`CancelProfile`/`Approve`/`Decline`):** inject NO recorder/actor, no `__construct`; the test proves it via event-count **delta 0** + `where('name','ProfileCancelled')->count()===0`.
- **Terminal-vs-non-terminal proof:** pair "fresh `CreateProfile` succeeds after cancel/deactivate" (terminal admitted) with "`DuplicateProfileForClub` still thrown over `Suspended`/`Lapsed`" (non-terminal blocked). Helper returns `{profile, customer, club}` → re-apply with clean factory-int ids (trap 6).
- **`ProfileCancelled` appears only in 2 docblocks** (prose: "does not exist") — the real no-invention guard is the `Events/*.php` glob `not->toContain`. Don't misread the prose-grep.
- **Guard blast radius:** Profile self-edge trips ONLY `SupplyLifecycleChainTest`'s exact-set whitelist. At **3.x** `ComplianceIndependenceTest`'s forbidden list drops `SuspendCustomer`/`CloseCustomer`/Account; `HoldRegistryTest`/`HoldChainTest` invert ONLY at coupling **4.x**. Always `grep -rn '<symbol>' tests/` to re-derive. **PG recipe:** docker `postgres:17` :55432 → `DB_CONNECTION=pgsql … php -d memory_limit=512M vendor/bin/pest`; `docker rm -f pg` after.
