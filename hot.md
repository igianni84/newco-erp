---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-09
---

# Hot Cache

## Last Updated
**2026-07-09 — RM-05 build started. Task 1.1 green: the capacity read-port ships.** Branch `ralph/parties-hero-package`, **1/16**. Local commit, not pushed.

## Build & Quality Status
- PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- SQLite **2234/2234** (2221 baseline + 13 new) · PHPStan **0** · Pint clean · `validate parties-hero-package --strict` green. **PG17 not re-run** (no lock code yet; first required at 3.2).
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). **PG17:** prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco` (container `pg` up).

## Active Change & Next Task
- **`parties-hero-package` — 1/16.** Next: **1.2** — `Support/ClubSeatOccupancy.php`: Club-row lock **strictly before** the `Active`+`Suspended` count (that ordering *is* the fix). **`Support/`, never `Actions/`.**
- **Shipped in 1.1:** `Contracts/HeroPackageCapacityReader` (`forClub(int): ?int`, `null` ⇒ uncapped) · `Reads/ConfigHeroPackageCapacityReader` · `config/parties.php` (first `config()` in K) · plain `bind` in the provider. Zero Module-A imports, zero capacity storage.
- **ADR `2026-07-09-hero-package-capacity-seat-set-and-waitinglist` is the plan; `design.md` D1–D11 restates it.** Don't re-derive it; don't re-ground against canon.
- **⚠️ Closes against a documented SUBSET.** `J-14`/`J-15`/`J-15a`/`XM-19` **NOT met**. Task 7.2 forces this onto the tracker.

## Blockers & Decisions Needed
- **None blocking.**
- **Build landmines** (also in `design.md`, `tasks.md`, `progress.md` § Codebase Patterns):
  1. **No new class in `Actions/`** — `SupplyLifecycleChainTest` set-pins that dir. `Contracts/`/`Reads/`/`Support/` are unpinned (confirmed in 1.1).
  2. **Test-env capacity default stays `null`.** No `PARTIES_HERO_PACKAGE_CAPACITY` in `phpunit.xml`; no `.env.testing` exists. That default is *why* the 2221 pre-existing tests are unmoved. Per-test: `config()->set()` or bind a fake.
  3. **PHPStan max audits test fakes.** A `?int` fake that never returns `null` draws `return.unusedType` — strengthen the fake, never the signature. 2.2/2.4/3.2/6.1 will hit it.
  4. `ProfileApprovalConsoleTest:154` + `:186` pin the opposite truth and go **red at 2.2** — invert there, don't appease.
  5. `CONTEXT.md:287` asserts `WaitingListJoined` is never recorded — invert (1.4 / 7.1).
  6. **No `markTestSkipped` in `tests/`**; the PG lane asserts *both* halves (`ActorRoleConstraintTest:110`).
  7. Blast radius is **behavioural, not structural**: 7 of 16 files drive the Actions via `callAction('approve')`. **Only the full suite is proof.**
- **Incidentals, logged, out of scope:** `operator-console` spec describes a Profile `activate` console verb that **never existed**; `DemoSeeder` seeds `jeanluc-krugd` in a **durable `Approved`**. Both contradict RM-03.
- **Tracker §7:** F8 (canon @ MVP-DEC-030 → RM-26/27) · F9 (canon issue #18 OPEN: auth is **OTP, not password**) · F10 (`spec/` 29 commits behind — Giovanni's call, needs an ADR).

## Open Patterns
- **A green acceptance test can pass against a racy implementation.** `AC-K-J-13` drives a *sequential* 51st approve; the oversell race survives it. A criterion tests a scenario, not an invariant.
- **`0` is a capacity, not an absence**; an explicit `null` override is a *pin*, not a fallthrough. Guard config reads with `is_numeric` / `array_key_exists` — never `??` / `empty()`.
- **A verifier's finding is a CANDIDATE.** Verify every quote against the file.
- **A shipped symbol's name can carry the opposite rule.** `RenewProfile` = `lapsed→active`, **cap-gated**; the *grandfathered* renewal is an unmodelled period rollover.
- **Bare `DEC-NNN` is ambiguous — always `MVP-DEC-NNN`.** RM-05 hit a **triple** collision.
- At build time re-read `knowledge/filament/hypotheses.md` + `lessons.md` 2026-07-06.
