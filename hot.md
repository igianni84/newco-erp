---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-09
---

# Hot Cache

## Last Updated
**2026-07-09 — RM-05: ADR + `/spec-to-change` → `parties-hero-package`, `APPROVED`. Ready for `./ralph.sh`.** No code written. **Committed + pushed** (`5da1c6b..545ed00`): `6896f8e` ADR, `545ed00` approve. `main` ↔ `origin` in sync, worktree clean.

## Build & Quality Status
- PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. **Untouched** (doc-only session).
- SQLite **2221/2221** · PG17 **2221/2221** (surplus assn = the PG-only CHECK lane). PHPStan **0** · Pint clean · `validate --all --strict` **11/11**.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). **PG17:** prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco` (container `pg` up).

## Active Change & Next Task
- **`parties-hero-package` — APPROVED, 0/16 tasks.** `./ralph.sh --change parties-hero-package 16`. Task 1.1 = capacity read-port + `config/parties.php` + bind.
- **13 reqs:** `party-registry` 3 ADDED (seat invariant · WaitingList placement/conversion/decline · capacity is read, never stored) + 7 MODIFIED; `operator-console` 3 MODIFIED.
- **ADR `2026-07-09-hero-package-capacity-seat-set-and-waitinglist` is the plan; `design.md` D1–D11 restates it.** Seat set = `Active`+`Suspended` · gate at RM-03's atomic approve under a **`parties_clubs` row lock** (PG17-only proof) · `Suspended → Active` never re-checked · `WaitingList` = FSM state, **two** entry points, `WaitingListJoined` at both · **no auto-promotion ever** · `qty` stays on Module A → read-port + config adapter, **zero capacity storage in K**.
- **⚠️ Closes against a documented SUBSET.** `J-14`/`J-15`/`J-15a`/`XM-19` **NOT met**. Task 7.2 forces this onto the tracker.

## Blockers & Decisions Needed
- **None blocking.** `APPROVED` exists. Stray branch `parties-anonymisation` predates this — untouched.
- **Build landmines (all in `design.md` + tasks):** (1) **No new class in `Actions/`** — `SupplyLifecycleChainTest` canonicalizes that dir; seat counter → `Support/`. (2) **Test-env capacity default stays `null`** (uncapped ⇒ 2221 tests unchanged). (3) `ProfileApprovalConsoleTest:154` + `:186` pin the opposite truth and go **red at task 2.2** — invert there, don't appease. (4) `CONTEXT.md:287` asserts `WaitingListJoined` is never recorded — invert. (5) **No `markTestSkipped` exists in `tests/`**; the PG lane asserts *both* halves (`ActorRoleConstraintTest:110`). (6) Blast radius is **behavioural, not structural**: no call site passes ctor args, so it compiles everywhere and breaks semantically; 7 of 16 files drive it via `callAction('approve')`. **Only the full suite is proof.**
- **Incidentals, logged, out of scope:** `operator-console` spec describes a Profile `activate` console verb that **never existed**; `DemoSeeder` seeds `jeanluc-krugd` in a **durable `Approved`**. Both contradict RM-03.
- **Tracker §7:** F8 (canon @ MVP-DEC-030 → RM-26/27) · F9 (canon issue #18 OPEN: auth is **OTP, not password**) · F10 (`spec/` 29 commits behind — Giovanni's call, needs an ADR).

## Open Patterns
- **A green acceptance test can pass against a racy implementation.** `AC-K-J-13` drives a *sequential* 51st approve; the oversell race survives it. A criterion tests a scenario, not an invariant.
- **A verifier's finding is a CANDIDATE, not a fact.** Verify every quote against the file.
- **A shipped symbol's name can carry the opposite rule.** `RenewProfile` = `lapsed→active`, **cap-gated**; the *grandfathered* renewal is an unmodelled period rollover.
- **Bare `DEC-NNN` is ambiguous — always `MVP-DEC-NNN`.** RM-05 hit a **triple** collision.
- At build time re-read `knowledge/filament/hypotheses.md` + `lessons.md` 2026-07-06.
