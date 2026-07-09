---
type: meta
description: Hot cache — repo-state digest, overwritten each operation. Chronology lives in log.md.
updated: 2026-07-09
---

# Hot Cache

## Last Updated
**2026-07-09 — `parties-hero-package-residuals` 2.2 green** (3 of 6). `WaitingListJoined`'s root-ness is pinned at **both** `record()` call sites. At the divert, `causation_id` has **no quiet mutant** — schema holds it, not the test.

## Build & Quality Status
- PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **SQLite 2387/2387** (12 386 assn) · **PG17 2387/2387** (12 393 assn) — both baselines +2, the new assertions.
- PHPStan **0** · Pint clean · `openspec validate parties-hero-package-residuals --strict` green.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). PG17 lane: prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco` (container `pg` up).

## Active Change & Next Task
- **`parties-hero-package-residuals`, APPROVED, 3/6 done.** Branch `ralph/parties-hero-package-residuals`. **Touches no `app/` file** — 4.1 fails the change if `git diff` does.
- **Next: 3.1**, console create-at-capacity (acceptance in `tasks.md`). The console pins assert what only the console can break — the **envelope** (`actor_role: newco_ops` + operator `actor_id`; the domain tests record `System`) and the toast — never the domain outcome again. Then **3.2**, then **4.1** close.
- `ProfileApprovalCapacityGateTest.php` declares Pest globals `seatClubTo()` / `freeOneSeat()` — the console files must not reuse either name (fatal redeclare, process-wide).

## Blockers & Decisions Needed
- **None blocking.** Three things the next reader must not lose:
  1. **RM-05 closed against a SUBSET.** `AC-K-J-14` / `J-15` / `J-15a` / `XM-19` are **NOT met** (Module A capacity-adjust · the unmodelled period rollover · Module 0/S). `RM-05 ✅` means *no-oversell at the approve instant is proven*, not *capacity is compliant*. This change closes none — 4.1 says so in the tracker.
  2. **Two canon escalations stay OPEN,** due before Module A's capacity-adjust: the capacity-decrease seat floor (`AC-K-J-15` floors on **seats**, `BR-A-Mutability-1` on **vouchers issued**); K PRD §1:77 (*S enforces*) vs §13 (*K enforces*).
  3. **`.env.example` is a test-environment file.** `PARTIES_HERO_PACKAGE_CAPACITY` ships **commented out**, pinned by a test — an active value caps the suite.
- **Tracker §7:** **F12** `Profile↔Customer` lock-order inversion (`ApproveProfile` locks Profile→Customer; `Suspend`/`ReactivateCustomer` the reverse) can deadlock — pre-existing, needs a *decision* before the producer HTTP surface. Also F11 · F2 · F5–F7 · F8 (→ RM-26/27) · F9 (**OTP**) · F10 (ADR).

## Open Patterns
- **`domain_events` is UPDATE-rejecting (trigger, `2026_06_12_000004`) and `causation_id` is an FK.** Causality is settable only at `record()` time, from a donor that already exists. **Count a transaction's events before promising a `causationId` mutant**: one event ⇒ no donor ⇒ no quiet mutant.
- **A conjunct can be DOMINATED by an earlier assertion in the same test.** Enumerate the property's *causes* into the test comment — an unfalsifiable assertion without one is decoration a later iteration correctly deletes.
- **A chained `expect()->and()` short-circuits ⇒ one mutant per CONJUNCT; the assertion COUNT proves which ran** (conjunct 1 red ⇒ baseline−1; conjunct 2 red ⇒ baseline). Task 3.2's `title`+`status`+`body` pin is three conjuncts — check for domination first.
- **`openspec archive` rewrites truth specs three `*DocsTest` files read** — green before a doc-only step ≠ green after it.
- **A spec sentence that orders operations is a claim; it can ship false.**
