---
type: meta
description: Hot cache — repo-state digest, overwritten each operation. Chronology lives in log.md.
updated: 2026-07-09
---

# Hot Cache

## Last Updated
**2026-07-09 — RM-05 task 2.4 green: `RenewProfile` is cap-gated. Grace sub-gate first; at parity it THROWS, never diverts.** Branch `ralph/parties-hero-package`, **8/16**, unpushed.

## Build & Quality Status
- PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- SQLite **2323/2323** (2306 + 17) · PHPStan **0** · Pint clean · `validate --strict` green. **PG17 not re-run** (first required at 3.2).
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). PG17 prefix: `tasks.md` header.

## Active Change & Next Task
- **`parties-hero-package` — 8/16.** Next: **3.1 — `ReactivateProfile` + `ActivateProfile` stay UNGATED: prove it, delete the false docblocks.**
- **3.1 adds NO gate — the code change is docblocks only.** A `Suspended` Profile **keeps its seat**, so re-checking on restore would let a temporary Hold **evict** a member (`AC-K-FSM-2a`). `Approved` is transient, so gating `ActivateProfile` would count the same seat twice. Only `lapsed → active` re-consumes, because only `lapsed` left the seat set.
- Its load-bearing regression: at parity, **suspend a member then approve an `Applied` Profile ⇒ diverted to `WaitingList`** — `SuspendProfile` frees nothing.
- **ADR `2026-07-09-hero-package-capacity-seat-set-and-waitinglist` is the plan; `design.md` D1–D11 restates it.** Don't re-derive it; don't re-ground against canon.
- **⚠️ Closes against a documented SUBSET:** `J-14`/`J-15`/`J-15a`/`XM-19` NOT met. Task 7.2 forces this onto the tracker.

## Blockers & Decisions Needed
- **None blocking.** Landmines (all detailed in `progress.md` § Codebase Patterns — read it first):
  1. **Delta spec `:212` orders the from-state guard AFTER the capacity gate. It is wrong.** D8's table wins: guard first. Held by 2.2 and 2.4.
  2. **A guard-ORDER claim is pinned NEGATIVELY** — assert the skipped gate emitted no `parties_clubs` statement, never the reason it reported. 3.1's "ungated" proof is the same shape.
  3. **Widening a guard falsifies its `cannot_*` copy; adding a gate does not.** Grep `"only from" lang/en/*.php` after any guard change.
  4. **No new class in `Actions/`** (`SupplyLifecycleChainTest` set-pins it). `Contracts`/`Reads`/`Support`/`Exceptions`/`Events` are free.
  5. **Test-env capacity default stays `null`**; set it per-test via `config()->set(...)`. **`0` = cheapest at-parity fixture; an explicit `null` in `by_club_id` = the only honest uncapped one.**
  6. **A Pest global helper is per-PROCESS**; a duplicate name fatals the run. Taken: `clubAtCapacity`, `seatLedgerQueries`, `seatClubTo`/`freeOneSeat`, `fillClubForDecline`, `renewal*` ×3.
  7. **Pint turns a docblock `{@see \FQCN}` into a real `use` import** — name non-dependencies as backticked prose.
  8. **No `markTestSkipped` in `tests/`**; driver-gated lanes assert both halves (`ActorRoleConstraintTest:110`).
  9. **Only the full suite is proof** — blast radius is behavioural.
- **Out of scope, both contradicting RM-03:** an `operator-console` Profile `activate` verb that never existed; `DemoSeeder` resting `jeanluc-krugd` in `Approved`.
- **Tracker §7:** F8 (MVP-DEC-030 → RM-26/27) · F9 (canon #18 OPEN: auth is **OTP**) · F10 (`spec/` 29 commits behind — needs an ADR).

## Open Patterns
- **A green acceptance test can pass against a racy implementation.** `AC-K-J-13`'s 51st approve is *sequential*. **3.2 is the only proof of D3**, and it is the one PG17-only test.
- **An event payload snapshots the row; it never asserts its own trigger.**
- **Always `MVP-DEC-NNN`.** A verifier's finding is a **candidate** — check every quote against the file.
- At build time re-read `knowledge/filament/hypotheses.md` + `lessons.md` 2026-07-06.
