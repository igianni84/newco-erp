---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-09
---

# Hot Cache

## Last Updated
**2026-07-09 — RM-05 task 1.4 green: the `WaitingListJoined` class exists; substrate §1 complete.** Branch `ralph/parties-hero-package`, **4/16**. Local commits, not pushed.

## Build & Quality Status
- PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- SQLite **2268/2268** (2263 + 5) · PHPStan **0** · Pint clean · `validate parties-hero-package --strict` green. **PG17 not re-run** — first required at 3.2.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). PG17 env prefix: see the `tasks.md` header.

## Active Change & Next Task
- **`parties-hero-package` — 4/16. Substrate §1 done; §2 (domain) begins.** Next: **2.1 — `CreateProfile` births in `WaitingList` at capacity.** Inject `HeroPackageCapacityReader` + `ClubSeatOccupancy`. **Club-active gate FIRST, capacity read second** — a `sunset` Club rejects, never waitlists. Born `waiting_list` ⇒ `ProfileCreated` **and** `WaitingListJoined`; born `applied` ⇒ `ProfileCreated` only. **No Club-row lock** (D6): use the lock-free `countOccupiedSeats()` — neither `Applied` nor `WaitingList` holds a seat. No migration: `waiting_list` is non-terminal, the partial-unique index already blocks a duplicate.
- **2.1 must also fix `ProfileState.php:12`** — *"A Profile is born `Applied`."* True today, false once 2.1 lands. Invisible to §7's sweep grep.
- **Shipped 1.4:** `Events/WaitingListJoined.php` — `final`, `NAME`/`ENTITY_TYPE`/`payload()`, payload `{profile_id, customer_id, club_id, state}`. A deliberate **superset** of `ProfileActivated`'s two-key shape (HubSpot must address *who* joined *which* Club's waitlist); don't harmonise it down. `ProfileState`'s docblock inverted.
- **ADR `2026-07-09-hero-package-capacity-seat-set-and-waitinglist` is the plan; `design.md` D1–D11 restates it.** Don't re-derive it; don't re-ground against canon.
- **⚠️ Closes against a documented SUBSET:** `J-14`/`J-15`/`J-15a`/`XM-19` NOT met. Task 7.2 forces this onto the tracker.

## Blockers & Decisions Needed
- **None blocking.** Landmines (fuller in `design.md` / `tasks.md` / `progress.md` § Codebase Patterns):
  1. **No new class in `Actions/`** — `SupplyLifecycleChainTest` set-pins it. `Contracts/`/`Reads/`/`Support/`/`Exceptions/`/`Events/` unpinned.
  2. **Test-env capacity default stays `null`** — *why* the 2263 prior tests are unmoved. Per-test: `config()->set()` or a fake.
  3. **PHPStan max audits test fakes.** A `?int` fake never returning `null` draws `return.unusedType` — strengthen the fake, never the signature. 2.2/2.4/3.2/6.1 hit this.
  4. **Pint promotes a docblock `{@see \FQCN}` into a real `use` import** — name non-dependencies as backticked prose, or 4.1's *no `Modules\Allocation\*` import* assertion breaks on prose.
  5. **No `markTestSkipped` in `tests/`**; driver-gated lanes assert both halves (`ActorRoleConstraintTest:110`).
  6. **`MembershipSuspensionChainTest:265` pins `WaitingListJoined` at count 0** — a *runtime* absence in an uncapped chain, not a class absence. Stays green through 2.1/2.2; if it reddens, a fixture leaked a capacity — don't weaken the pin.
  7. Blast radius is behavioural — 7 of 16 test files drive the Actions via `callAction('approve')`. **Only the full suite is proof.**
- **Logged, out of scope:** an `operator-console` Profile `activate` verb that never existed; `DemoSeeder` rests `jeanluc-krugd` durably in `Approved`. Both contradict RM-03.
- **Tracker §7:** F8 (MVP-DEC-030 → RM-26/27) · F9 (canon #18 OPEN: auth is **OTP**) · F10 (`spec/` 29 commits behind — needs an ADR, Giovanni's call).

## Open Patterns
- **A green acceptance test can pass against a racy implementation.** `AC-K-J-13`'s 51st approve is *sequential*; the oversell race survives it. A criterion tests a scenario, not an invariant.
- **Lock-before-read is invisible on SQLite** (no lock clause compiled). Pin it as SQL **statement order** (`DB::listen`) + driver-gate `for update`. Order ≠ serialisation — **3.2 must still run.**
- **An event payload snapshots the row; it never asserts its own trigger.** A writer bug must reach the audit store, not be papered over by a hardcoded state.
- **A `\d`-based PII pin inverts on a cardinal-bearing message.** `club_at_capacity` carries the occupancy; assert *exactly* the gate's two numbers in order, never "no digits".
- **`0` is a capacity, not an absence**; an explicit `null` override is a pin, not a fallthrough. `is_numeric` / `array_key_exists`, never `??`.
- **`RenewProfile` is `lapsed→active` and cap-gated**; the *grandfathered* renewal is an unmodelled period rollover. Same word, opposite rule.
- **Always `MVP-DEC-NNN`**, never bare `DEC-NNN`. A verifier's finding is a **candidate** — check every quote against the file.
- At build time re-read `knowledge/filament/hypotheses.md` + `lessons.md` 2026-07-06.
