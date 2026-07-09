---
type: meta
description: Hot cache — repo-state digest, overwritten each operation. Chronology lives in log.md.
updated: 2026-07-09
---

# Hot Cache

## Last Updated
**2026-07-09 — RM-05 task 5.2 green: the console can finally convert a waitlisted Profile, and its approve toast stops lying.** Five files. `ralph/parties-hero-package`, **13/16**, unpushed.

## Build & Quality Status
- PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **SQLite 2376/2376 · PG17 2376/2376** (+6). PHPStan **0** · Pint clean · `validate --strict` green.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). PG17 prefix: `tasks.md` header.

## Active Change & Next Task
- **`parties-hero-package` — 13/16.** Next: **6.1 — make the near-capacity Club real in the demo** (tracker RM-08's *"post RM-05"* item; D2).
- **What 6.1 must do:** document `PARTIES_HERO_PACKAGE_CAPACITY=2` in `.env.example` + `docs/development.md`. The seeded DRC Club already sits at exactly 2 occupied seats (`hiroshi-drc → Active`, `carlos-drc → Suspended`), so the pre-seeded `eleanor-drc → WaitingList` becomes coherent and a third approve diverts — live. **Do not touch the seeded rows; the seeder writes no config.** `DemoSeederTest` stays green with capacity unset.
- **Two landmines 6.1 walks into.** (1) A Livewire-driven toast assertion must read **`filament.claimed_notifications`**, not `filament.notifications` — 5.1's helper copied verbatim returns `[]` and passes vacuously. (2) Bind capacity per-test via `config()->set(...)`; **the test-env default stays `null`** or all 2370 prior tests move.
- **ADR `2026-07-09-hero-package-capacity-seat-set-and-waitinglist` is the plan (`design.md` D1–D11 restates it).** Don't re-derive; don't re-ground against canon.
- **⚠️ Closes against a documented SUBSET:** `J-14`/`J-15`/`J-15a`/`XM-19` NOT met. Task 7.2 forces this onto the tracker.

## Blockers & Decisions Needed
- **None blocking.** Every landmine is in `progress.md` § Codebase Patterns — **read it first.** Beyond the two above:
  1. **An absence claim is a placebo until mutation-tested**, one pin per route in, by NAME *and* by TYPE. And **run a regression mutant beside the absence-mutants** — one that *adds* a gate can never red the happy-path row it admits, so positive controls stay decoration until falsified (5.1 mutant I; 5.2 mutant M5).
  2. **Pin a two-outcome verb on its TITLE, not on the state it landed in.** Every state assertion stayed green under the mutant restoring the fixed `'approved'` key — that mutant *is* the bug 5.2 fixed.
  3. **Pint turns a docblock `{@see \FQCN}` into a real `use` import.** Backticked prose only; `git diff` the `use` block after `pint`.
  4. **A Pest global helper is per-PROCESS** — a duplicate name fatals the whole run. Taken: `clubAtCapacity`, `seatClubTo`, `freeOneSeat`, `seatLedgerQueries`, `ungatedSeatClubTo`, `renewalSeatClubTo`, `heroPackage*`, `heroBoundary*`, `consoleOutcome*`, `approvalConsole*`.
- **Only the full suite is proof.** 7 of the 16 files exercising these Actions drive them through `callAction`, invisible to `grep`.
- **§ 7.1's residual grep must be READ, not counted** (it self-matches this change's own inverted prose), and must cover **`lang/`** as well as `app/ tests/ CONTEXT.md` — a copy file's *comments* restate the spec too (5.2 found and inverted one in both locales).
- **Tracker §7:** F8 (MVP-DEC-030 → RM-26/27) · F9 (canon #18 OPEN: auth is **OTP**) · F10 (`spec/` 29 commits behind — needs an ADR).

## Open Patterns
- **A green acceptance test can pass against a racy implementation**, an omission-test against the omission's opposite, a name-shaped absence pin against a mutant standing elsewhere, and an exact-list toast assertion against a list it never read. Mutation is the only proof.
- **An event payload snapshots the row; it never asserts its own trigger.**
