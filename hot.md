---
type: meta
description: Hot cache — repo-state digest, overwritten each operation. Chronology lives in log.md.
updated: 2026-07-09
---

# Hot Cache

## Last Updated
**2026-07-09 — `parties-hero-package-residuals` 3.2 green** (5 of 6). Console renew-at-capacity. The toast the task asked for is **dominated by the shared kit**; the pin that mattered was the one nobody named — `assertActionVisible('renew')` against a **full** Club.

## Build & Quality Status
- PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **SQLite 2389/2389** (12 404 assn) · **PG17 2389/2389** (12 411 assn) — both +1 test, +8 assn over 3.1: the new pin, nothing else moved.
- PHPStan **0** · Pint clean · `openspec validate parties-hero-package-residuals --strict` green.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). PG17 lane: prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco` (container `pg` up).

## Active Change & Next Task
- **`parties-hero-package-residuals`, APPROVED, 5/6 done.** Branch `ralph/parties-hero-package-residuals`. **Touches no `app/` file** — 4.1 fails the change if `git diff` does.
- **Next: 4.1**, the close gate, and the last task. Acceptance in `tasks.md`. Three things it must not get wrong:
  1. Its sweep greps must be **read, not counted** — this change's own delta quotes the superseded prose in its `_Source:_` line, on purpose.
  2. The tracker must say **RM-05 closes against a documented SUBSET**: `AC-K-J-14` / `J-15` / `J-15a` / `XM-19` untouched. "Residuals closed" ≠ "capacity is compliant".
  3. Confirm `git diff --stat` names no file under `app/`.
- Pest global helper names are **process-wide**; six are already taken across the Parties tests. Prefer inline `config()->set(...)`.

## Blockers & Decisions Needed
- **None blocking.** Two canon escalations stay OPEN, due before Module A's capacity-adjust: the capacity-decrease seat floor (`AC-K-J-15` floors on **seats**, `BR-A-Mutability-1` on **vouchers issued**); K PRD §1:77 (*S enforces*) vs §13 (*K enforces*).
- **`.env.example` is a test-environment file.** `PARTIES_HERO_PACKAGE_CAPACITY` ships **commented out**, pinned by a test — an active value caps the suite.
- **Tracker §7:** **F12** `Profile↔Customer` lock-order inversion can deadlock — pre-existing, needs a *decision* before the producer HTTP surface. Also F11 · F2 · F5–F7 · F8 (→ RM-26/27) · F9 (**OTP**) · F10 (ADR).

## Open Patterns
- **A KIT-LEVEL field is never blind — every console test on that kit dominates it. A console pin's real content is the WIRING.** Measured: stripping the danger `->body()` from `SurfacesDomainActions` reds 6 tests, 5 pre-existing; a capacity gate on `renew`'s visibility reds exactly **1 of 2389**.
- **Fields of ONE structured value take an exact-array `toBe`, not a chain** — one assertion, no short-circuit, and it pins the collection's SIZE. Chaining is for conjuncts on *different* subjects.
- **An envelope pinned on event E in Action X says NOTHING about event E in Action Y.** Pins multiply by `record()` **call site**, not by event class. A dominated conjunct is kept only if the comment enumerates why.
- **A prescribed mutant is usually the loud one** — it proves the pin fires; necessity needs the quiet drift the suite passes. Locate its red by test NAME + MESSAGE, never the reported line (3.1: an unrelated comment; 3.2: a vendor frame).
- **A spec sentence that orders operations is a claim; it can ship false.** So can a test's own name (3.2 retired *"the sole UI-reachable reject"*).
