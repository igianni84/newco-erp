---
type: meta
description: Hot cache — repo-state digest, overwritten each operation. Chronology lives in log.md.
updated: 2026-07-09
---

# Hot Cache

## Last Updated
**2026-07-09 — RM-05 task 3.1 green: the two ungated transitions are documented AND falsified.** Docblocks only; injecting the forbidden gate killed 9/10 assertions. Branch `ralph/parties-hero-package`, **9/16**, unpushed.

## Build & Quality Status
- PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- SQLite **2331/2331** (2323 + 8) · PHPStan **0** · Pint clean · `validate --strict` green. **PG17 never run this change — 3.2 is where it first must.**
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). PG17 prefix: `tasks.md` header.

## Active Change & Next Task
- **`parties-hero-package` — 9/16.** Next: **3.2 — PG17-only proof that two same-Club approvals serialise on the `parties_clubs` row.**
- **3.2 is the ONLY proof D3 works.** `AC-K-J-13`'s 51st approve is *sequential* and passes green against the racy implementation; the 2.2/2.4 statement-order pins are not serialisation.
- Shape: `pgsql` — one free seat, two `Applied` Profiles, two concurrent connections both calling `ApproveProfile` ⇒ exactly one `Active`, one `WaitingList`. `sqlite` — assert the *positive* half (the sequential gate diverts the second) + document the asymmetry.
- **Assert both halves, never skip.** Idiom: `ActorRoleConstraintTest.php:110` + docblock `:17-23`. **No `markTestSkipped`/`->skip()` exists in `tests/` — don't introduce the first.** Run **both** lanes before closing.
- **ADR `2026-07-09-hero-package-capacity-seat-set-and-waitinglist` is the plan (`design.md` D1–D11 restates it).** Don't re-derive; don't re-ground against canon.
- **⚠️ Closes against a documented SUBSET:** `J-14`/`J-15`/`J-15a`/`XM-19` NOT met. Task 7.2 forces this onto the tracker.

## Blockers & Decisions Needed
- **None blocking.** Landmines — **all nine are spelled out in `progress.md` § Codebase Patterns; read it before writing code:**
  1. **A NON-gate / absence claim is a placebo until mutation-tested** (inject the gate, watch it go red). **4.1 and 5.1 carry the same obligation.**
  2. **Absence and guard-ORDER claims pin NEGATIVELY** — assert no `parties_clubs` statement was emitted, never the reason reported.
  3. **Delta spec `:212` orders the from-state guard AFTER the capacity gate. It is wrong** — D8's table wins: guard first.
  4. **Widening a guard falsifies its `cannot_*` copy; adding a gate does not.**
  5. **No new class in `Actions/`** (`SupplyLifecycleChainTest` set-pins it).
  6. **Test-env capacity default stays `null`**; set it per-test via `config()->set(...)`. **`0` is at parity for every from-state.**
  7. **A Pest global helper is per-PROCESS** — a duplicate name fatals the whole run.
  8. **Pint turns a docblock `{@see \FQCN}` into a real `use` import** — name non-dependencies as backticked prose.
  9. **Only the full suite is proof** — blast radius is behavioural.
- **§ 7.1 sweep:** `deferred Module-A seam` survives only at `ProfileMembershipChainTest.php:124` (**not in 7.1's bullet list**) and `CONTEXT.md:170` / `:174`.
- **Tracker §7:** F8 (MVP-DEC-030 → RM-26/27) · F9 (canon #18 OPEN: auth is **OTP**) · F10 (`spec/` 29 commits behind — needs an ADR).

## Open Patterns
- **A green acceptance test can pass against a racy implementation** — and an omission-test against the omission's opposite.
- **An event payload snapshots the row; it never asserts its own trigger.**
- **Always `MVP-DEC-NNN`.** A verifier's finding is a **candidate** — check every quote against the file.
- Before 5.x: re-read `knowledge/filament/hypotheses.md` + `lessons.md` 2026-07-06.
