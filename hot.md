---
type: meta
description: Hot cache — repo-state digest, overwritten each operation. Chronology lives in log.md.
updated: 2026-07-09
---

# Hot Cache

## Last Updated
**2026-07-09 — RM-05 task 6.1 green: the seeded DRC Club is now genuinely at capacity, and the env var that does it ships disarmed.** Three files, zero production code. `ralph/parties-hero-package`, **14/16**, unpushed.

## Build & Quality Status
- PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **SQLite 2381/2381 · PG17 2381/2381** (+5). PHPStan **0** · Pint clean · `validate --strict` green.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). PG17 prefix: `tasks.md` header.

## Active Change & Next Task
- **`parties-hero-package` — 14/16.** Next: **7.1 — invert every shipped claim this change made false.**
- **What 7.1 must do:** rewrite `CONTEXT.md` (`:287` pins `WaitingListJoined` absent; `:131`/`:166`/`:170`/`:174` call the gate a deferred Module-A seam; `:267`/`:298` name this change), plus two stale *comments* whose assertions stay green — `MembershipSuspensionChainTest.php:260-265` and `SupplyLifecycleChainTest.php:424` (its *no-Action-class* half is still TRUE, D10).
- **The sweep grep is `UNCAPPED|uncapped|deferred Module-A seam|WaitingListJoined` over `app/ tests/ CONTEXT.md` + `lang/` — and it MUST BE READ, NOT COUNTED.** It self-matches this change's own *inverted, true* prose (`ProfileApprovalSeatRaceTest`, `HeroPackageCapacityBoundaryTest`, `DemoSeederHeroPackageCapacityTest`). `docs/` is out of scope.
- **It also misses two residual shapes prior tasks hit:** a copy file's *comments* restate the spec (5.2), and a widened guard falsifies `lang/*/parties.php`'s `"only from …"` reasons — so also `grep -n "only from" lang/en/*.php`.
- **ADR `2026-07-09-hero-package-capacity-seat-set-and-waitinglist` is the plan (`design.md` D1–D11 restates it).** Don't re-derive; don't re-ground against canon.
- **⚠️ Closes against a documented SUBSET:** `J-14`/`J-15`/`J-15a`/`XM-19` NOT met. Task 7.2 forces this onto the tracker.

## Blockers & Decisions Needed
- **None blocking.** Every landmine is in `progress.md` § Codebase Patterns — **read it first.** Beyond the grep above:
  1. **`.env.example` is a test-environment file.** `APP_ENV=testing` loads `.env` (no `.env.testing`); `phpunit.xml` overrides only its ~13 keys; `docs/development.md:23` says `cp .env.example .env`. An active `PARTIES_HERO_PACKAGE_CAPACITY` there caps the whole suite. Ships commented out, pinned by a test.
  2. **An absence claim is a placebo until mutation-tested**, one pin per route in, by NAME *and* by TYPE — and **run a positive-control mutant beside the absence-mutants**, or the happy-path rows are decoration (5.1 I; 5.2 M5; 6.1 M2).
  3. **Pest's JSON files a thrown exception under `error_details`, not `failures`** — a killed mutant reads as a survivor.
  4. **Pint turns a docblock `{@see \FQCN}` into a real `use` import.** Backticked prose only; `git diff` the `use` block after `pint`.
  5. **A Pest global helper is per-PROCESS** — a duplicate name fatals the whole run. Taken: `clubAtCapacity`, `seatClubTo`, `freeOneSeat`, `seatLedgerQueries`, `ungatedSeatClubTo`, `renewalSeatClubTo`, `heroPackage*`, `heroBoundary*`, `consoleOutcome*`, `approvalConsole*`, `demoHeroCapacity`, `demoDrc*`.
- **Only the full suite is proof.** 7 of the 16 files exercising these Actions drive them through `callAction`, invisible to `grep`.
- **Tracker §7:** F1 (DemoSeeder PG-truncate) did **not** bite. F8 (MVP-DEC-030 → RM-26/27) · F9 (canon #18 OPEN: auth is **OTP**) · F10 (`spec/` 29 commits behind — needs an ADR).

## Open Patterns
- **A green acceptance test can pass against a racy implementation**, an omission-test against the omission's opposite, a capped-behaviour test against a fixture that could never have qualified. Mutation is the only proof.
- **An event payload snapshots the row; it never asserts its own trigger.**
