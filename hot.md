---
type: meta
description: Hot cache — repo-state digest, overwritten each operation. Chronology lives in log.md.
updated: 2026-07-09
---

# Hot Cache

## Last Updated
**2026-07-09 — `parties-hero-package-residuals` task 2.1 green** (2 of 6). `WaitingListJoined`'s root-ness is pinned at the **birth** entry point; two mutants proved each half load-bearing.

## Build & Quality Status
- PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **SQLite 2387/2387** (12 384 assn) · **PG17 2387/2387** (12 391 assn) — both baselines +2, the new assertions.
- PHPStan **0** · Pint clean · `openspec validate parties-hero-package-residuals --strict` green.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). PG17 lane: prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco` (container `pg` up).

## Active Change & Next Task
- **`parties-hero-package-residuals`, APPROVED, 2/6 done.** Branch `ralph/parties-hero-package-residuals`. **Touches no `app/` file** — 4.1 fails the change if `git diff` does.
- **Next: 2.2** — the same root-ness pair (`causation_id` null · `correlation_id === event_id`) on the `WaitingListJoined` recorded by `ApproveProfile`'s capacity **divert** (`ApproveProfile.php:163`), in `ProfileApprovalCapacityGateTest.php`. Its divert test is that file's first `it(...)` and already `sole()`s the event. **The divert records exactly one event**, so 2.1's "sibling roots" hazard is absent and a shared-`correlationId` mutant has no natural donor — fabricate one (`(string) Str::uuid7()`). Then **3.1 / 3.2** (console create-at-capacity, renew-at-capacity), then **4.1** close.

## Blockers & Decisions Needed
- **None blocking.** Three things the next reader must not lose:
  1. **RM-05 closed against a SUBSET.** `AC-K-J-14` / `J-15` / `J-15a` / `XM-19` are **NOT met** (Module A's capacity-adjust · the unmodelled period rollover · Module 0/S). `RM-05 ✅` means *no-oversell at the approve instant is proven*, **not** *capacity is compliant*. This change closes none — task 4.1 must say so in the tracker.
  2. **Two canon escalations stay OPEN,** due before Module A's capacity-adjust: the capacity-decrease seat floor (`AC-K-J-15` floors on **seats**, `BR-A-Mutability-1` on **vouchers issued**); and K PRD §1:77 (*S enforces*) vs §13 (*K enforces*).
  3. **`.env.example` is a test-environment file.** `PARTIES_HERO_PACKAGE_CAPACITY` ships **commented out**, pinned by a test — an active value caps the suite.
- **Tracker §7:** **F12** `Profile↔Customer` lock-order inversion (`ApproveProfile` Profile→Customer; `Suspend`/`ReactivateCustomer` the reverse) can deadlock. Pre-existing; needs a *decision* before the producer HTTP surface. Also F11 · F2 · F5–F7 · F8 (→ RM-26/27) · F9 (**OTP**) · F10 (`spec/` behind canon — ADR).

## Open Patterns
- **A chained `expect()->and()` short-circuits ⇒ one mutant per CONJUNCT.** The assertion *count* is the instrument: mutant A red at 12 383 assn (chain aborted); mutant B at 12 384 (conjunct 1 ran, passed). Same count in two mutants ⇒ one never reached its target. Task 3.2's `title`+`status`+`body` pin is three conjuncts.
- **Two mutants, not one — and run them against the FULL suite.** A mutant reddening the new pin *and* the old tests proves the pin **fires**; only the weaker, realistic drift the existing suite **passes** proves it is **needed**, and that necessity claim is repo-wide (in 2.1 both mutants left the other 2386 tests green).
- **A green suite before a doc-only step is not green after it.** `openspec archive` rewrites truth specs that three `*DocsTest` files read.
- **A spec sentence that orders operations is a claim, and it can ship false.**
