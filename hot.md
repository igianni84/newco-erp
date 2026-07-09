---
type: meta
description: Hot cache — repo-state digest, overwritten each operation. Chronology lives in log.md.
updated: 2026-07-09
---

# Hot Cache

## Last Updated
**2026-07-09 — RM-05 task 3.2 green: D3 is now falsifiable, and falsified.** The two-connection PG race lands 1 `Active` + 1 `WaitingList`; the racy implementation reds it. Branch `ralph/parties-hero-package`, **10/16**, unpushed.

## Build & Quality Status
- PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **SQLite 2333/2333 · PG17 2333/2333** — first full PG17 run of this change; the whole change's PG lane is verified, not just 3.2's file. PHPStan **0** · Pint clean · `validate --strict` green.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). PG17 prefix: `tasks.md` header.

## Active Change & Next Task
- **`parties-hero-package` — 10/16.** Next: **4.1 — assert zero capacity storage, zero Module-A coupling, zero auto-promotion** (`AC-K-XM-18`, `AC-K-XM-20`, D5, D10).
- **4.1 is an ABSENCE task, so it inherits the mutation obligation:** *"no listener/scheduler/job/observer promotes a waitlisted Profile"* is a placebo until you inject an auto-promoter and watch the test go red. Same for 5.1's *"the console re-checks no gate itself."*
- 4.1's behavioural half: at parity with a `WaitingList` Profile, drive `LapseProfile` / `CancelProfile` / `DeactivateProfile` ⇒ the waitlisted Profile is **still** `waiting_list`, the freed seat stays empty, no `ProfileActivated`.
- **ADR `2026-07-09-hero-package-capacity-seat-set-and-waitinglist` is the plan (`design.md` D1–D11 restates it).** Don't re-derive; don't re-ground against canon.
- **⚠️ Closes against a documented SUBSET:** `J-14`/`J-15`/`J-15a`/`XM-19` NOT met. Task 7.2 forces this onto the tracker.

## Blockers & Decisions Needed
- **None blocking.** Landmines — **all eleven are in `progress.md` § Codebase Patterns; read it before writing code.** Load-bearing for 4.x–5.x:
  1. **An absence / NON-gate claim is a placebo until mutation-tested** (inject the forbidden thing, watch it red). **4.1 and 5.1 carry this.**
  2. **Absence and guard-ORDER claims pin NEGATIVELY** — assert the statement never emitted, never the reason reported.
  3. **No new class in `Actions/`** (`SupplyLifecycleChainTest` set-pins it). 4.1 asserts this directly.
  4. **`getConstructor()` + `not->toUse([...])` + `DB::listen`** beat a literal `grep` for *"this Action references nothing"* — a grep passes on a renamed promoted property.
  5. **Pint turns a docblock `{@see \FQCN}` into a real `use` import** — which would manufacture the exact `Modules\Allocation\*` violation 4.1 asserts against. Backticked prose only.
  6. **Test-env capacity default stays `null`**; set per-test via `config()->set(...)`, merging `by_club_id` with `array_replace`. **`0` is at parity for every from-state.**
  7. **A Pest global helper is per-PROCESS** — a duplicate name fatals the whole run. Taken: `clubAtCapacity`, `seatClubTo`, `freeOneSeat`, `seatLedgerQueries`, `heroPackage*` (×4).
  8. **Only the full suite is proof** — blast radius is behavioural.
- **§ 7.1 sweep:** `deferred Module-A seam` survives only at `ProfileMembershipChainTest.php:124` (**not in 7.1's bullet list**) and `CONTEXT.md:170` / `:174`. **The sweep grep now also matches this change's own *inverted* prose** (incl. `ProfileApprovalSeatRaceTest`'s docblock) — read every hit, never count them.
- **Tracker §7:** F8 (MVP-DEC-030 → RM-26/27) · F9 (canon #18 OPEN: auth is **OTP**) · F10 (`spec/` 29 commits behind — needs an ADR).

## Open Patterns
- **A green acceptance test can pass against a racy implementation** — and an omission-test against the omission's opposite. Mutation is the only proof.
- **An event payload snapshots the row; it never asserts its own trigger.**
- **Always `MVP-DEC-NNN`.** A verifier's finding is a **candidate** — check every quote against the file.
- Before 5.x: re-read `knowledge/filament/hypotheses.md` + `lessons.md` 2026-07-06.
