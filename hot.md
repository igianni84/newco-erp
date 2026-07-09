---
type: meta
description: Hot cache — repo-state digest, overwritten each operation. Chronology lives in log.md.
updated: 2026-07-09
---

# Hot Cache

## Last Updated
**2026-07-09 — `parties-hero-package-residuals` 3.1 green** (4 of 6). The console create-at-capacity pin. `WaitingListJoined`'s **actor envelope at the birth `record()` call site** was blind repo-wide — `grep`ping the event name said otherwise.

## Build & Quality Status
- PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **SQLite 2388/2388** (12 396 assn) · **PG17 2388/2388** (12 403 assn) — both baselines +1 test, +10 assn: the new pin, nothing else moved.
- PHPStan **0** · Pint clean · `openspec validate parties-hero-package-residuals --strict` green.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). PG17 lane: prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco` (container `pg` up).

## Active Change & Next Task
- **`parties-hero-package-residuals`, APPROVED, 4/6 done.** Branch `ralph/parties-hero-package-residuals`. **Touches no `app/` file** — 4.1 fails the change if `git diff` does.
- **Next: 3.2**, console renew-at-capacity (acceptance in `tasks.md`). Then **4.1** closes.
- 3.2 lands on the **same console surface** 3.1 just mutated, so inherit both hazards: (a) a mutant's red is located by test **name + message**, never by the reported **line** (3.1 saw a line 114 past the failing test's brace, inside an unrelated comment); (b) its `title`+`status`+`body` pin is **three conjuncts** ⇒ up to three mutants — but check **domination** first, as `ProfileCreated`'s envelope was dominated in 3.1.
- Global Pest helper names are **process-wide**: `seatClubTo()` / `freeOneSeat()` (`ProfileApprovalCapacityGateTest`), `approvalConsoleToasts()` / `approvalConsoleSeatClubTo()` (`ProfileApprovalConsoleTest`), `clubAtCapacity()` (`ProfileBirthStateRoutingTest`) are all taken. Prefer inline `config()->set(...)`.

## Blockers & Decisions Needed
- **None blocking.** Three things the next reader must not lose:
  1. **RM-05 closed against a SUBSET.** `AC-K-J-14` / `J-15` / `J-15a` / `XM-19` are **NOT met** (Module A capacity-adjust · the unmodelled period rollover · Module 0/S). `RM-05 ✅` means *no-oversell at the approve instant is proven*, not *capacity is compliant*. This change closes none — 4.1 says so in the tracker.
  2. **Two canon escalations stay OPEN,** due before Module A's capacity-adjust: the capacity-decrease seat floor (`AC-K-J-15` floors on **seats**, `BR-A-Mutability-1` on **vouchers issued**); K PRD §1:77 (*S enforces*) vs §13 (*K enforces*).
  3. **`.env.example` is a test-environment file.** `PARTIES_HERO_PACKAGE_CAPACITY` ships **commented out**, pinned by a test — an active value caps the suite.
- **Tracker §7:** **F12** `Profile↔Customer` lock-order inversion (`ApproveProfile` locks Profile→Customer; `Suspend`/`ReactivateCustomer` the reverse) can deadlock — pre-existing, needs a *decision* before the producer HTTP surface. Also F11 · F2 · F5–F7 · F8 (→ RM-26/27) · F9 (**OTP**) · F10 (ADR).

## Open Patterns
- **An envelope pinned on event E in Action X says NOTHING about event E in Action Y.** Pins multiply by `record()` **call site**, not by event class. Measured: `WaitingListJoined.actor_role` was pinned at the divert, blind at the birth; `actor_id` blind at both.
- **A chained `expect()->and()` short-circuits ⇒ one mutant per CONJUNCT; the assertion COUNT proves which ran** (conjunct 1 red ⇒ baseline−1; conjunct 2 red ⇒ baseline). Demonstrated 3× inside this one change — which is why its hypothesis was **corrected 2/3 → 1/3**: one change is worth one confirmation, and it was about to self-promote to a rule.
- **A conjunct can be DOMINATED by an earlier assertion or by a sibling test.** Keep it if the requirement names it, but write the enumeration into the comment or a later iteration deletes it as decoration.
- **`domain_events` is UPDATE-rejecting (trigger) and `causation_id` is an FK.** Count a transaction's events before promising a `causationId` mutant: one event ⇒ no donor ⇒ no quiet mutant.
- **`openspec archive` rewrites truth specs three `*DocsTest` files read** — green before a doc-only step ≠ green after it.
- **A spec sentence that orders operations is a claim; it can ship false.**
