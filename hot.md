---
type: meta
description: Hot cache — repo-state digest, overwritten each operation. Chronology lives in log.md.
updated: 2026-07-09
---

# Hot Cache

## Last Updated
**2026-07-09 — `parties-hero-package` CLOSED via §2.7** (merged `91adfdb`, archived `24df7af`, **pushed**). RM-05 shipped, against a **documented subset**. A follow-up change is **drafted, not approved**.

## Build & Quality Status
- PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **SQLite 2381/2381** (12 340 assn) · **PG17 2381/2381** (12 347 assn) — verified at the close gate, and **re-run after the archive** (it rewrites `openspec/specs/**`, which three `*DocsTest` files read: not test-neutral).
- PHPStan **0** · Pint clean · `openspec validate --all --strict` **11/11**.
- PG-only lanes are real: `SeatRace` = **19 assn on PG17 vs 15 on SQLite**; neither skips.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs).


## Active Change & Next Task
- **`parties-hero-package-residuals` — drafted, 0/6 tasks, `validate --strict` green, NO `APPROVED`.** Next: **review → create `APPROVED` → `./ralph.sh --change parties-hero-package-residuals 11`**. Touches **no `app/` file** (task 4.1 fails the change if `git diff` does):
  1. **Truth spec, *Profile Membership Approval*** orders *"lock; count; read capacity; then, only if a seat is free: assert the from-state"*. Code guards the from-state **first** (`ApproveProfile.php:131` before `:139`) — D8 governs, and a doomed call must lock no Club row. Read literally, the old prose diverts an `Active` Profile onto the waitlist. Delta corrects it, and pins it **negatively** (the `parties_clubs` statement the doomed call never emits).
  2. **`WaitingListJoined` root-ness unpinned** at both entry points. Sibling pins it at `ProfileActivationTest.php:74`. Behaviour correct.
  3. **Two console scenarios** (create-at-capacity, renew-at-capacity) proven only at the domain layer.
- **Semantic-verify DID run** (4 agents, 13 reqs): **0 CRITICAL**, 5 WARNING, 8 SUGGESTION. Already fixed: the bad docblock citations (`9d6172b`); tracker **F12** (`83df84e`).

## Blockers & Decisions Needed
- **None blocking.** Three things the next reader must not lose:
  1. **RM-05 closed against a SUBSET.** `AC-K-J-14` / `J-15` / `J-15a` / `XM-19` are **NOT met** (Module A's capacity-adjust · the unmodelled period rollover · Module 0/S). `RM-05 ✅` means *no-oversell at the approve instant is enforced and proven*, **not** *capacity is compliant*. The residuals change closes none of them.
  2. **Two canon escalations stay OPEN,** due before Module A's capacity-adjust: the capacity-decrease seat floor (`AC-K-J-15` floors on **seats**, `BR-A-Mutability-1` on **vouchers issued**); and K PRD §1:77 (*S enforces*) vs §13 (*K enforces*).
  3. **`.env.example` is a test-environment file.** `PARTIES_HERO_PACKAGE_CAPACITY` ships **commented out**, pinned by a test — an active value caps the suite.
- **Tracker §7:** **F12** (new) `Profile↔Customer` lock-order inversion (`ApproveProfile` Profile→Customer vs `SuspendCustomer`/`ReactivateCustomer` the reverse) can deadlock. **Pre-existing**; RM-05's Club lock closes no cycle (all Actions take Profile→Club). Needs a *decision* before the producer HTTP surface. Also F11 · F2 · F5–F7 · F8 (→ RM-26/27) · F9 (**OTP**) · F10 (`spec/` behind canon — ADR).

## Open Patterns
- **A green suite before a doc-only step is not green after it.** `openspec archive` rewrites truth specs that tests read.
- **A spec sentence that orders operations is a claim, and it can ship false.** So is a docblock citation — nothing greps for a requirement name that resolves nowhere.
- **A green test proves nothing about an absence until you mutate it.** Inject the forbidden code; watch it red.
