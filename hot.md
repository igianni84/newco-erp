---
type: meta
description: Hot cache — repo-state digest, overwritten each operation. Chronology lives in log.md.
updated: 2026-07-09
---

# Hot Cache

## Last Updated
**2026-07-09 — `parties-hero-package` CLOSED via §2.7.** Merged `--no-ff` (`91adfdb`), archived (`24df7af`). RM-05 shipped, against a **documented subset**. `main` is **19 ahead of `origin/main` — UNPUSHED**.

## Build & Quality Status
- PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **SQLite 2381/2381** (12 340 assn) · **PG17 2381/2381** (12 347 assn) — verified at the close gate, and **re-run after the archive** (it rewrites `openspec/specs/**`, which three `*DocsTest` files read: not test-neutral).
- PHPStan **0** · Pint clean · `openspec validate --all --strict` **10/10** (was 11 — change item gone).
- PG-only lanes are real: `SeatRace` = **19 assn on PG17 vs 15 on SQLite**; neither skips.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs).

## Active Change & Next Task
- **No active change.** **Next (human):** `git push` (19 commits); delete local `ralph/parties-hero-package`.
- **Semantic-verify DID run** (4 agents × 13 delta requirements): **0 CRITICAL**, 5 WARNING, 8 SUGGESTION → archived per §2.7. Follow-up change candidates:
  1. **Truth spec, *Profile Membership Approval***: *"lock; count; read capacity; then, only if a seat is free: assert the from-state"*. Code guards the from-state **first** (D8's table governs; a doomed call locks no Club row). Fix via a change — **never** hand-edit `openspec/specs/**`.
  2. **`WaitingListJoined` root-ness is untested** (no `causation_id`; `correlation_id` = own `event_id`). Sibling pins it at `ProfileActivationTest.php:74`. Behaviour is correct.
  3. **Three docblocks cite a requirement that exists nowhere** (`ClubSeatOccupancy.php:14`, `ApproveProfile.php:22`, `RenewProfile.php:21`). Real name: *Hero Package Capacity Invariant*.
  4. **Create-at-capacity and renew-at-capacity** are proven only at the domain layer, never through the console page.

## Blockers & Decisions Needed
- **None blocking.** Three things the next reader must not lose:
  1. **RM-05 closed against a SUBSET.** `AC-K-J-14` / `J-15` / `J-15a` / `XM-19` are **NOT met** (Module A's capacity-adjust surface · the unmodelled `Active` period rollover · Module 0/S). `RM-05 ✅` means *no-oversell at the approve instant is enforced and proven*, **not** *capacity is compliant*.
  2. **Two canon escalations stay OPEN,** both due before Module A's capacity-adjust: who evaluates the capacity-decrease seat floor (`AC-K-J-15` floors on **seats**, `BR-A-Mutability-1` on **vouchers issued**); and K PRD §1:77 (*S enforces*) vs §13 (*K enforces*).
  3. **`.env.example` is a test-environment file.** `PARTIES_HERO_PACKAGE_CAPACITY` ships **commented out**, pinned by a test — an active value caps the whole suite.
- **Pre-existing, not introduced here:** a **Profile↔Customer lock-order inversion** (`ApproveProfile` locks Profile→Customer; `SuspendCustomer`/`ReactivateCustomer` the reverse). The new **Club** lock closes **no** cycle — every Action taking both takes Profile→Club.
- **Tracker §7:** F1 falsified → **F11** (`DemoSeeder` not re-runnable on SQLite). F2 · F5–F7 · F8 (→ RM-26/27) · F9 (**OTP** auth) · F10 (`spec/` behind canon — needs an ADR).

## Open Patterns
- **A green suite before a doc-only step is not green after it.** `openspec archive` rewrites truth specs that tests read.
- **A spec sentence that orders operations is a claim, and it can ship false.** So is a docblock citation — nothing greps for a requirement name that exists nowhere.
- **A green test proves nothing about an absence until you mutate it.** This change ran 7 + 10 + 6 mutants; that is why its non-gates are load-bearing.
