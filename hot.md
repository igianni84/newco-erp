---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten each significant operation. Not a journal; chronology lives in log.md.
updated: 2026-07-09
---

# Hot Cache

## Last Updated
**2026-07-09 — RM-05 task 2.3 green: the waitlist's second exit. `DeclineProfile` widens to `{applied, waiting_list}` — no lock, no capacity, no constructor.** Branch `ralph/parties-hero-package`, **7/16**, not pushed.

## Build & Quality Status
- PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- SQLite **2306/2306** (2292 + 14) · PHPStan **0** · Pint clean · `validate parties-hero-package --strict` green. **PG17 not re-run** (first required at 3.2).
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). PG17 prefix: `tasks.md` header.

## Active Change & Next Task
- **`parties-hero-package` — 7/16.** Next: **2.4 — `RenewProfile`: cap-gated, grace sub-gate FIRST.**
- **2.4's order is fixed**: from-state guard → **30-day grace guard** → Club lock → count → capacity gate. Past-grace reports the **grace** reason regardless of capacity. At parity it **throws** `clubAtCapacity`, never diverts: canon draws no `Lapsed → WaitingList` edge, and diverting would burn the grace clock. Injects **both** ledger and port; changes no from-state set.
- **ADR `2026-07-09-hero-package-capacity-seat-set-and-waitinglist` is the plan; `design.md` D1–D11 restates it.** Don't re-derive it; don't re-ground against canon.
- **⚠️ Closes against a documented SUBSET:** `J-14`/`J-15`/`J-15a`/`XM-19` NOT met. Task 7.2 forces this onto the tracker.

## Blockers & Decisions Needed
- **None blocking.** Landmines — each explained in `progress.md` § Codebase Patterns, read it first:
  1. **Delta spec `:212` orders the from-state guard AFTER the capacity gate. It is wrong** — an `active` Profile in a full Club would divert onto the waitlist. D8's table wins: guard first.
  2. **Widening a guard falsifies its `cannot_*` copy, and no test catches it.** 2.2 shipped a false `cannot_approve`; 2.3 fixed both. **§7.1's grep misses this** — also `grep "only from" lang/en/*.php`.
  3. **No new class in `Actions/`** (`SupplyLifecycleChainTest` set-pins it). `Contracts`/`Reads`/`Support`/`Exceptions`/`Events` are free.
  4. **Test-env capacity default stays `null`** — why 2292 prior tests are unmoved. Set it per-test via `config()->set(...)`.
  5. **PHPStan max audits test fakes, ctors and `?int` offsets.**
  6. **A Pest global helper is per-PROCESS**; a duplicate name fatals the run. Taken: `clubAtCapacity`, `seatLedgerQueries`, `seatClubTo`/`freeOneSeat`, `fillClubForDecline`.
  7. **Pint turns a docblock `{@see \FQCN}` into a real `use` import** — name non-dependencies as backticked prose.
  8. **No `markTestSkipped` in `tests/`**; driver-gated lanes assert both halves (`ActorRoleConstraintTest:110`).
  9. **Only the full suite is proof** — blast radius is behavioural.
- **Logged, out of scope, both contradicting RM-03:** an `operator-console` Profile `activate` verb that never existed; `DemoSeeder` resting `jeanluc-krugd` in `Approved`.
- **Tracker §7:** F8 (MVP-DEC-030 → RM-26/27) · F9 (canon #18 OPEN: auth is **OTP**) · F10 (`spec/` 29 commits behind — needs an ADR).

## Open Patterns
- **A green acceptance test can pass against a racy implementation.** `AC-K-J-13`'s 51st approve is *sequential*. **3.2 is the only proof of D3**, and it is the one PG17-only test.
- **`RenewProfile` is `lapsed→active` and cap-gated**; the *grandfathered* renewal is an unmodelled period rollover. Same word, opposite rule — and 2.4 is next.
- **An event payload snapshots the row; it never asserts its own trigger.**
- **Always `MVP-DEC-NNN`.** A verifier's finding is a **candidate** — check every quote against the file.
- At build time re-read `knowledge/filament/hypotheses.md` + `lessons.md` 2026-07-06.
