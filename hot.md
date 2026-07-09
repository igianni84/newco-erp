---
type: meta
description: Hot cache — repo-state digest, overwritten each operation. Chronology lives in log.md.
updated: 2026-07-09
---

# Hot Cache

## Last Updated
**2026-07-09 — RM-05 task 5.1 green: the console kit names two outcomes; ten mutants say it still gates nothing.** Two files. `ralph/parties-hero-package`, **12/16**, unpushed.

## Build & Quality Status
- PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **SQLite 2370/2370 · PG17 2370/2370** (+22). PHPStan **0** · Pint clean · `validate --strict` green.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). PG17 prefix: `tasks.md` header.

## Active Change & Next Task
- **`parties-hero-package` — 12/16.** Next: **5.2 — `ViewProfile`: `approve`/`decline` visible from `{applied, waiting_list}`; the approve toast tells the truth** (D11).
- **What 5.1 hands 5.2:** `lifecycleAction()`'s `$successKey` is now `string|Closure(mixed): string` — **no new parameter**, pass the resolver positionally. It takes **`mixed`**, narrowed with `$outcome instanceof Profile`: PHPStan max enforces callable contravariance, so `Closure(Profile): string` is rejected. **5.1 added no copy** — the *waitlisted* keys in `lang/{en,it}/operator_console.php` are 5.2's; the console i18n test enforces IT ⊆ EN.
- **Assert the notification TITLE, not just the state** — the old bug was invisible precisely because no test asserted one.
- **ADR `2026-07-09-hero-package-capacity-seat-set-and-waitinglist` is the plan (`design.md` D1–D11 restates it).** Don't re-derive; don't re-ground against canon.
- **⚠️ Closes against a documented SUBSET:** `J-14`/`J-15`/`J-15a`/`XM-19` NOT met. Task 7.2 forces this onto the tracker.

## Blockers & Decisions Needed
- **None blocking.** Every landmine is in `progress.md` § Codebase Patterns — **read it first.** The four that bite 5.2 / 6.1:
  1. **An absence claim is a placebo until mutation-tested**, one pin per route in. New from 5.1: an **absence-mutant can never red a happy-path row** — run one *regression* mutant beside them, or the positive controls are decoration. And **`toUse` resolves symbol references, not the `use` block**: an inline FQCN with no import IS flagged, a *type* (IS-A a forbidden base) is not — pair it with an `is_subclass_of` scan.
  2. **The console kit needs no DB and no Livewire**: `Action::call(['record' => …, 'data' => []])` runs standalone; `Notification::send()` only pushes onto `session('filament.notifications')`. `Notification::assertNotified()` compares titles only and **PULLS** — read the session array instead.
  3. **Pint turns a docblock `{@see \FQCN}` into a real `use` import.** Backticked prose only; `git diff` the `use` block after `pint`.
  4. **A Pest global helper is per-PROCESS** — a duplicate name fatals the whole run. Taken: `clubAtCapacity`, `seatClubTo`, `freeOneSeat`, `seatLedgerQueries`, `ungatedSeatClubTo`, `renewalSeatClubTo`, `heroPackage*`, `heroBoundary*`, `consoleOutcome*`.
- **Only the full suite is proof.** Test-env capacity default stays `null`; `0` is at parity for every from-state.
- **§ 7.1's residual grep must be READ, not counted** (it matches this change's own inverted prose), and must also cover `lang/*/parties.php` for `only from`.
- **Tracker §7:** F8 (MVP-DEC-030 → RM-26/27) · F9 (canon #18 OPEN: auth is **OTP**) · F10 (`spec/` 29 commits behind — needs an ADR).

## Open Patterns
- **A green acceptance test can pass against a racy implementation**, an omission-test against the omission's opposite, a name-shaped absence pin against a mutant standing elsewhere. Mutation is the only proof — and a campaign that reds no happy-path row has not tested the happy path.
- **An event payload snapshots the row; it never asserts its own trigger.**
