---
type: meta
description: Hot cache — repo-state digest, overwritten each operation. Chronology lives in log.md.
updated: 2026-07-09
---

# Hot Cache

## Last Updated
**2026-07-09 — `parties-hero-package-residuals` task 1.1 green** (1 of 6). The guard-before-lock **negative ordering pin** now exists, and a second mutant proved it was the *only* thing in the repo that could see the defect.

## Build & Quality Status
- PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **SQLite 2387/2387** (12 382 assn) · **PG17 2387/2387** (12 389 assn). Baseline was 2381; the 6 new dataset rows are the whole delta.
- PHPStan **0** · Pint clean · `openspec validate parties-hero-package-residuals --strict` green.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). PG17 lane: prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco` (container `pg` up).

## Active Change & Next Task
- **`parties-hero-package-residuals`, APPROVED, 1/6 done.** Branch `ralph/parties-hero-package-residuals`. **Touches no `app/` file** — task 4.1 fails the change if `git diff` does.
- **Next: 2.1** — pin `WaitingListJoined` root-ness (`causation_id` null **and** `correlation_id === event_id`) at the **birth** entry point, in `ProfileBirthStateRoutingTest.php`. Mirror `ProfileActivationTest.php:74-75`. Then **2.2** pins the same at the **divert** entry point (`ApproveProfile.php:163`) — two `record()` call sites, so two pins; neither covers the other. Then **3.1 / 3.2** (console create-at-capacity, console renew-at-capacity), then **4.1** close.
- Done in 1.1: `ProfileApprovalCapacityGateTest` gained a 6-row `{active, lapsed}` × `{at parity, free seat, explicitly uncapped}` pin asserting a doomed approve emits **no `parties_clubs` statement at all**.

## Blockers & Decisions Needed
- **None blocking.** Three things the next reader must not lose:
  1. **RM-05 closed against a SUBSET.** `AC-K-J-14` / `J-15` / `J-15a` / `XM-19` are **NOT met** (Module A's capacity-adjust · the unmodelled period rollover · Module 0/S). `RM-05 ✅` means *no-oversell at the approve instant is enforced and proven*, **not** *capacity is compliant*. This change closes none of them — task 4.1 must say so in the tracker.
  2. **Two canon escalations stay OPEN,** due before Module A's capacity-adjust: the capacity-decrease seat floor (`AC-K-J-15` floors on **seats**, `BR-A-Mutability-1` on **vouchers issued**); and K PRD §1:77 (*S enforces*) vs §13 (*K enforces*).
  3. **`.env.example` is a test-environment file.** `PARTIES_HERO_PACKAGE_CAPACITY` ships **commented out**, pinned by a test — an active value caps the whole suite.
- **Tracker §7:** **F12** `Profile↔Customer` lock-order inversion (`ApproveProfile` Profile→Customer vs `Suspend`/`ReactivateCustomer` the reverse) can deadlock. Pre-existing; needs a *decision* before the producer HTTP surface. Also F11 · F2 · F5–F7 · F8 (→ RM-26/27) · F9 (**OTP**) · F10 (`spec/` behind canon — ADR).

## Open Patterns
- **Two mutants, not one.** A mutant that reds the new pin *and* the old tests proves the pin **fires**; only the weaker, realistic drift — which the existing suite **passes** — proves it is **needed**. Measured in 1.1: guard below the whole gate ⇒ 13 red (7 pre-existing); guard between lock/count and the gate `if` ⇒ 13 pre-existing **green**, only the 6 new rows red.
- **Pest's `toThrow($class, $message)` compares the message with `toBe` (exact)** — *"reason X, never reason Y"* needs no second assertion.
- **A green suite before a doc-only step is not green after it.** `openspec archive` rewrites truth specs that three `*DocsTest` files read.
- **A spec sentence that orders operations is a claim, and it can ship false.**
