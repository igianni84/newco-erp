---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-09
---

# Hot Cache

## Last Updated
**2026-07-09 — RM-05 task 1.3 green: one capacity rejection, two callers, EN+IT.** Branch `ralph/parties-hero-package`, **3/16**. Local commits, not pushed.

## Build & Quality Status
- PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- SQLite **2263/2263** (2255 baseline + 8) · PHPStan **0** · Pint clean · `validate parties-hero-package --strict` green. **PG17 not re-run** — first required at 3.2.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). PG17 env prefix: see the `tasks.md` header.

## Active Change & Next Task
- **`parties-hero-package` — 3/16.** Next: **1.4** — `Events/WaitingListJoined.php`, static-holder shape exactly like `ProfileCreated` (`NAME`, `ENTITY_TYPE`, `payload(Profile): array`), payload `{profile_id, customer_id, club_id, state}`, PII-free. **Invert `ProfileState.php:20`**, whose docblock asserts the enum emits no `WaitingListJoined`. Unit pin in the style of `Events/ActivationEventsTest.php`.
- **Shipped 1.3 — `IllegalProfileTransition::clubAtCapacity($from, int $capacity, int $occupiedSeats)`:** one factory, two rejecting callers (`ApproveProfile` on an already-`waiting_list` Profile; `RenewProfile` within grace). `applied` at parity is **diverted, never rejected**. `int` (not `?int`) capacity is a typecheck-enforced proof that uncapped never reaches the throw. Key `parties.profile.club_at_capacity` in EN **and** IT (the first `profile` group in `lang/it`).
- **ADR `2026-07-09-hero-package-capacity-seat-set-and-waitinglist` is the plan; `design.md` D1–D11 restates it.** Don't re-derive it; don't re-ground against canon.
- **⚠️ Closes against a documented SUBSET:** `J-14`/`J-15`/`J-15a`/`XM-19` NOT met. Task 7.2 forces this onto the tracker.

## Blockers & Decisions Needed
- **None blocking.** Landmines (fuller in `design.md` / `tasks.md` / `progress.md` § Codebase Patterns):
  1. **No new class in `Actions/`** — `SupplyLifecycleChainTest` set-pins it. `Contracts/`/`Reads/`/`Support/`/`Exceptions/` unpinned.
  2. **Test-env capacity default stays `null`** — *why* the 2255 prior tests are unmoved. Per-test: `config()->set()` or a fake.
  3. **PHPStan max audits test fakes.** A `?int` fake never returning `null` draws `return.unusedType` — strengthen the fake, never the signature. 2.2/2.4/3.2/6.1 hit this.
  4. **Pint promotes a docblock `{@see \FQCN}` into a real `use` import** — name non-dependencies as backticked prose, or 4.1's *no `Modules\Allocation\*` import* assertion breaks on prose.
  5. **No `markTestSkipped` in `tests/`**; driver-gated lanes assert both halves (`ActorRoleConstraintTest:110`).
  6. Blast radius is behavioural — 7 of 16 test files drive the Actions via `callAction('approve')`. **Only the full suite is proof.**
- **Logged, out of scope:** an `operator-console` Profile `activate` verb that never existed; `DemoSeeder` rests `jeanluc-krugd` durably in `Approved`. Both contradict RM-03.
- **Tracker §7:** F8 (MVP-DEC-030 → RM-26/27) · F9 (canon #18 OPEN: auth is **OTP**) · F10 (`spec/` 29 commits behind — needs an ADR, Giovanni's call).

## Open Patterns
- **A green acceptance test can pass against a racy implementation.** `AC-K-J-13`'s 51st approve is *sequential*; the oversell race survives it. A criterion tests a scenario, not an invariant.
- **Lock-before-read is invisible on SQLite** (no lock clause compiled). Pin it as SQL **statement order** (`DB::listen`) + driver-gate `for update`. Order ≠ serialisation — **3.2 must still run.**
- **A `\d`-based PII pin inverts on a cardinal-bearing message.** `club_at_capacity` carries the occupancy; assert *exactly* the gate's two numbers in order, never "no digits".
- **`0` is a capacity, not an absence**; an explicit `null` override is a pin, not a fallthrough. `is_numeric` / `array_key_exists`, never `??`.
- **`RenewProfile` is `lapsed→active` and cap-gated**; the *grandfathered* renewal is an unmodelled period rollover. Same word, opposite rule.
- **Always `MVP-DEC-NNN`**, never bare `DEC-NNN`. A verifier's finding is a **candidate** — check every quote against the file.
- At build time re-read `knowledge/filament/hypotheses.md` + `lessons.md` 2026-07-06.
