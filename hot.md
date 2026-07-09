---
type: meta
description: Hot cache — repo-state digest, overwritten each operation. Chronology lives in log.md.
updated: 2026-07-09
---

# Hot Cache

## Last Updated
**2026-07-09 — RM-05 task 4.1 green: the boundary is pinned, and seven mutants prove each pin bites.** Zero production files touched. Branch `ralph/parties-hero-package`, **11/16**, unpushed.

## Build & Quality Status
- PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **SQLite 2348/2348 · PG17 2348/2348** (+15). PHPStan **0** · Pint clean · `validate --strict` green.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). PG17 prefix: `tasks.md` header.

## Active Change & Next Task
- **`parties-hero-package` — 11/16.** Next: **5.1 — `SurfacesDomainActions` gains an outcome-aware success notification** (D11), without changing the ~20 existing call sites' behaviour.
- **5.1 carries TWO obligations from 4.1, not one:**
  1. *"The console re-checks no gate itself"* is an **absence** ⇒ mutation-test it (inject a gate, watch it red).
  2. **Pin it by TYPE, not by NAME.** 4.1's mutant B — a `DomainEventConsumer` promoter planted in `Support/` — walked straight through the subdirectory set-pin; only `is_subclass_of` over every declared class caught it. For 5.1: assert structurally that the console imports nothing from a module's `Exceptions` namespace and catches `RuntimeException` by base type — don't grep names.
- **ADR `2026-07-09-hero-package-capacity-seat-set-and-waitinglist` is the plan (`design.md` D1–D11 restates it).** Don't re-derive; don't re-ground against canon.
- **⚠️ Closes against a documented SUBSET:** `J-14`/`J-15`/`J-15a`/`XM-19` NOT met. Task 7.2 forces this onto the tracker.

## Blockers & Decisions Needed
- **None blocking.** Landmines — **all thirteen are in `progress.md` § Codebase Patterns; read it before writing code.** Load-bearing for 5.x–6.x:
  1. **An absence / NON-gate claim is a placebo until mutation-tested**, and needs **one pin per route in** (directory name · implemented interface · container/dispatcher registration · `Schedule::events()`). A refactor of an absence test **invalidates its earlier falsification** — re-run the mutant.
  2. **Absence and guard-ORDER claims pin NEGATIVELY** — assert the statement never emitted, never the reason reported.
  3. **No new class in `Actions/`** — 4.1 now set-pins all 48 names, closing `SupplyLifecycleChainTest`'s `Create*` blind spot.
  4. **Pint turns a docblock `{@see \FQCN}` into a real `use` import** — and 4.1's mutant D proved an *unused* import still reds `not->toUse`. Backticked prose only; `git diff` the `use` block after the first `pint`.
  5. **The `Schema` facade erases `list<string>`** — read `DB::connection()->getSchemaBuilder()`; pass `schemaQualified: false` (SQLite `main.`, PG `public.`); compare columns with `toEqualCanonicalizing`.
  6. **Test-env capacity default stays `null`**; set per-test via `config()->set(...)`. **`0` is at parity for every from-state.**
  7. **A Pest global helper is per-PROCESS** — a duplicate name fatals the whole run. Taken: `clubAtCapacity`, `seatClubTo`, `freeOneSeat`, `seatLedgerQueries`, `ungatedSeatClubTo`, `renewalSeatClubTo`, `heroPackage*` (×4), `heroBoundary*` (×3).
  8. **Only the full suite is proof** — blast radius is behavioural.
- **§ 7.1 sweep:** `deferred Module-A seam` survives only at `ProfileMembershipChainTest.php:124` (**not in 7.1's bullet list**) and `CONTEXT.md:170` / `:174`. The sweep grep **also matches this change's own *inverted* prose** (incl. `ProfileApprovalSeatRaceTest`'s and `HeroPackageCapacityBoundaryTest`'s docblocks) — read every hit, never count them. 7.1 must **additionally** grep `lang/*/parties.php` for `only from`: a widened guard falsifies its copy.
- **Tracker §7:** F8 (MVP-DEC-030 → RM-26/27) · F9 (canon #18 OPEN: auth is **OTP**) · F10 (`spec/` 29 commits behind — needs an ADR).

## Open Patterns
- **A green acceptance test can pass against a racy implementation**, an omission-test against the omission's opposite, and a **name-shaped absence pin against a mutant that simply stands somewhere else.** Mutation is the only proof.
- **An event payload snapshots the row; it never asserts its own trigger.**
- **Always `MVP-DEC-NNN`.** A verifier's finding is a **candidate** — check every quote against the file.
- Before 5.x: re-read `knowledge/filament/hypotheses.md` + `lessons.md` 2026-07-06.
