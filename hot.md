---
type: meta
description: Hot cache — repo-state digest, overwritten each operation. Chronology lives in log.md.
updated: 2026-07-09
---

# Hot Cache

## Last Updated
**2026-07-09 — `parties-hero-package` COMPLETE (16/16). RM-05 built.** 7.2 closed the gate: both engines green, and the tracker now says RM-05 ships a **subset**. Branch `ralph/parties-hero-package` — **unpushed, unmerged, unarchived**; §2.7 is human-owned.

## Build & Quality Status
- PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **SQLite 2381/2381** (12 340 assn) · **PG17 2381/2381** (12 347 assn). PHPStan **0** · Pint clean · `validate --all --strict` **11/11**.
- The PG-only lanes are real, not vacuous: `--filter=SeatRace` gives **19 assertions on PG17 vs 15 on SQLite**; neither lane skips.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). PG17 prefix: `tasks.md` header.

## Active Change & Next Task
- **No active task.** 16/16. Next: **human §2.7 close ritual** — branch review → **semantic-verify** (never run) → merge `--no-ff` → `openspec archive parties-hero-package` → push.
- **One-look item for semantic-verify:** `openspec/specs/party-registry/spec.md:931` is **NOT stale — do not "fix" it.** It carries `CONTEXT.md:287`'s old sentence but **scoped** (*"by this change"*), which is why `MembershipSuspensionChainTest`'s pin stays green. Drop the qualifier and you re-create the defect 7.1 removed. Never hand-edit `openspec/specs/**`.
- **Read `progress.md` § Codebase Patterns first.**

## Blockers & Decisions Needed
- **None blocking.** Three things the close ritual must not lose:
  1. **RM-05 closes against a documented SUBSET.** `AC-K-J-14` / `J-15` / `J-15a` / `XM-19` are **NOT met** (Module A's capacity-adjust surface · the unmodelled `Active` period rollover · Module 0/S). Recorded in tracker §1/§3/§4/§6. `RM-05 ✅` means *no-oversell at the approve instant is enforced and proven*, **not** *capacity is compliant*.
  2. **Two canon escalations stay OPEN:** who evaluates the capacity-decrease seat floor (`AC-K-J-15` floors on **seats**, `BR-A-Mutability-1` on **vouchers issued**); and K PRD §1:77 (*S enforces*) vs §13 (*K enforces*). Both must settle before Module A's capacity-adjust lands. `WaitingListJoined`-on-birth was **never asked** of canon — worth filing.
  3. **`.env.example` is a test-environment file.** `PARTIES_HERO_PACKAGE_CAPACITY` ships **commented out**, pinned by a test — an active value there caps the whole suite.
- **Tracker §7:** **F1 ✅ falsified and inverted** → **F11 opened** (`DemoSeeder` is *not* re-runnable on SQLite though its docblock promises it is; on PG it "works" only because `TRUNCATE` bypasses the row trigger). F2 · F5–F7 · F8 (→ RM-26/27) · F9 (auth is **OTP**) · F10 (`spec/` 29 commits behind — needs an ADR).

## Open Patterns
- **A residual-claim sweep needs two greps — including on the doc you are editing right now.** A long bullet is not one claim; it is N claims. Rewriting its opening sentence leaves the rest false.
- **A §7 "incidental finding" is an unverified hypothesis wearing a status emoji.** F1 was written from a *sibling* method and pointed at the wrong engine for eight days. Run the thing before citing it.
- **A fresh-schema test cannot see a guard that only fires on non-empty state.** Anything documented "re-runnable" needs a test that runs it twice. And PG does not fire row triggers on `TRUNCATE` — invariant 4 has a hole no test can see.
- **A green test proves nothing about an absence until you mutate it.** Non-gates, omissions, capped behaviour: inject the forbidden code, watch it red.
