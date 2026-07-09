---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-09
---

# Hot Cache

## Last Updated
**2026-07-09 — RM-05 task 2.1 green: `CreateProfile` births in `WaitingList` at capacity. §2 (domain) open.** Branch `ralph/parties-hero-package`, **5/16**. Local commits, not pushed.

## Build & Quality Status
- PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- SQLite **2280/2280** (2268 + 12) · PHPStan **0** · Pint clean · `validate parties-hero-package --strict` green. **PG17 not re-run** — first required at 3.2.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). PG17 env prefix: see the `tasks.md` header.

## Active Change & Next Task
- **`parties-hero-package` — 5/16.** Next: **2.2 — `ApproveProfile`: Club-row lock → seat count → gate.** Order: **lock BEFORE count** (`lockAndCountOccupiedSeats()`), inside the existing txn; keep the Profile-row + Customer-row locks. From-state widens to `{Applied, WaitingList}`. Free seat ⇒ unchanged (`Approved` transient → `ActivateProfile` → `Active`). At parity **from `Applied`** ⇒ **transition, don't throw**: write `waiting_list`, one `WaitingListJoined`, no charge / no OC lock / no `ProfileActivated`. At parity **from `WaitingList`** ⇒ throw `IllegalProfileTransition::clubAtCapacity()`, no second event, no state write.
- **2.2 must invert `ProfileApprovalConsoleTest.php:186`** — drop `'waiting_list → hidden + rejected'` from the reject-floor dataset. Its `toThrow` goes red once approve is legal from `waiting_list`. Also delete `ApproveProfile`'s false `ships UNCAPPED` docblock (`:58-63`).
- **2.2 injects BOTH** the `HeroPackageCapacityReader` and `ClubSeatOccupancy` — it throws, so it reads the capacity *number*. (2.1 injects the helper alone: an unread port is `property.onlyWritten` under PHPStan max.)
- **ADR `2026-07-09-hero-package-capacity-seat-set-and-waitinglist` is the plan; `design.md` D1–D11 restates it.** Don't re-derive it; don't re-ground against canon.
- **⚠️ Closes against a documented SUBSET:** `J-14`/`J-15`/`J-15a`/`XM-19` NOT met. Task 7.2 forces this onto the tracker.

## Blockers & Decisions Needed
- **None blocking.** Landmines (fuller in `design.md` / `tasks.md` / `progress.md` § Codebase Patterns):
  1. **No new class in `Actions/`** — `SupplyLifecycleChainTest` set-pins it. `Contracts/`/`Reads/`/`Support/`/`Exceptions/`/`Events/` unpinned.
  2. **Test-env capacity default stays `null`** — *why* the 2268 prior tests are unmoved. Per-test: `config()->set('parties.hero_package.capacity.by_club_id', [$club->id => N])`.
  3. **PHPStan max audits test fakes** (`return.unusedType` on a `?int` fake that never returns `null`) **and production ctors** (`property.onlyWritten` on an unread injected port). Strengthen the fake, never the signature.
  4. **Pint promotes a docblock `{@see \FQCN}` into a real `use` import** — name non-dependencies as backticked prose, or 4.1's *no `Modules\Allocation\*` import* assertion breaks on prose.
  5. **No `markTestSkipped` in `tests/`**; driver-gated lanes assert both halves (`ActorRoleConstraintTest:110`).
  6. **`MembershipSuspensionChainTest:265` pins `WaitingListJoined` at count 0** — a *runtime* absence in an uncapped chain. Stayed green through 2.1; if it reddens in 2.2, a fixture leaked a capacity — don't weaken the pin.
  7. Blast radius is behavioural — 7 of 16 test files drive the Actions via `callAction('approve')`. **Only the full suite is proof.**
- **Logged, out of scope:** an `operator-console` Profile `activate` verb that never existed; `DemoSeeder` rests `jeanluc-krugd` durably in `Approved`. Both contradict RM-03.
- **Tracker §7:** F8 (MVP-DEC-030 → RM-26/27) · F9 (canon #18 OPEN: auth is **OTP**) · F10 (`spec/` 29 commits behind — needs an ADR, Giovanni's call).

## Open Patterns
- **An iteration can crash mid-task and leave implemented-but-untested code.** 2.1 recovered exactly that (`.last-output` = *"Connection closed mid-response"*). Read the working tree before assuming a task is unstarted; review the orphaned diff against the shipped contract rather than rewriting it.
- **A green acceptance test can pass against a racy implementation.** `AC-K-J-13`'s 51st approve is *sequential*; the oversell race survives it. A criterion tests a scenario, not an invariant.
- **Lock-before-read is invisible on SQLite** (no lock clause compiled). Pin it as SQL **statement order** (`DB::listen`) + driver-gate `for update`. Order ≠ serialisation — **3.2 must still run.**
- **An event payload snapshots the row; it never asserts its own trigger.** A writer bug must reach the audit store, not be papered over by a hardcoded state.
- **A `\d`-based PII pin inverts on a cardinal-bearing message.** `club_at_capacity` carries the occupancy; assert *exactly* the gate's two numbers in order, never "no digits".
- **`0` is a capacity, not an absence**; an explicit `null` override is a pin, not a fallthrough. `is_numeric` / `array_key_exists`, never `??`. A capacity of `0` is also the cheapest at-parity fixture: an empty Club, already full.
- **`RenewProfile` is `lapsed→active` and cap-gated**; the *grandfathered* renewal is an unmodelled period rollover. Same word, opposite rule.
- **Always `MVP-DEC-NNN`**, never bare `DEC-NNN`. A verifier's finding is a **candidate** — check every quote against the file.
- At build time re-read `knowledge/filament/hypotheses.md` + `lessons.md` 2026-07-06.
