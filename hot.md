---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter — task 5.2 final traceability + quality sweep ✅ green; §5 now 2/2 → ALL 14 tasks done → `CHANGE_COMPLETE` emitted).** Verification-only LAST task — **no code/test change** (the scenario walk found no gap). Ran the five Quality Commands in CLAUDE.md table order, all green. Walked every `#### Scenario:` across the four delta specs (`openspec show … --json --deltas-only` = **deltaCount 10**; `grep -rc '^#### Scenario:'` = **33 scenarios** = money 15 / i18n 10 / feature-flags 5 / event-substrate 3) and recorded the **scenario→covering-test mapping table (33/33 covered, 0 gaps)** in `progress.md`. Confirmed `git diff main -- composer.json composer.lock` is **Pennant-only** (`laravel/pennant ^1.23` the sole require add; `php ^8.3` unchanged) and **no protected file modified by the loop** (the `ralph.sh`/`APPROVED`/`GUIDE.md` diff hits are, respectively: Giovanni's own `fix(ralph):` human commit `bfcd885`; the human-created marker swept into the task-1.1 commit by the `git add -A` preflight; and `GUIDE.md`, which is not on the protected list and was a task-5.1 edit).

## Build & Quality Status
- Stack: PHP 8.5.2 runtime · Laravel 13.15.0 (^13.8) · Filament 5.6.7 · Pennant v1.23.0 (^1.23) · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` still `php ^8.3` — bump staged in `substrate-hardening`.)
- Branch `ralph/foundations-money-i18n-flags`: suite **243/243** green (860 assertions) · phpstan **0** @ max · pint clean · `pint --test` clean · `validate --strict` valid. `git diff main` composer = Pennant-only.

## Active Change & Next Task
- **`foundations-money-i18n-flags` — 14 of 14 done → COMPLETE.** §1 Money 5/5 · §2 i18n 4/4 · §3 flags 2/2 · §4 ActorContext 1/1 · §5 docs/sweep 2/2. This iteration emitted **`<promise>CHANGE_COMPLETE</promise>`**.
- **NEXT = HUMAN action, not a ralph task:** review the branch → merge → semantic-verify (GUIDE §2.7) → `openspec archive foundations-money-i18n-flags --yes`. The loop does NOT archive or merge. No unchecked tasks remain; re-running this change has nothing to do.
- **After archive, the next change is the staged sibling `substrate-hardening`** (carries the `php ^8.3`→`^8.5` bump + any deferred composer churn) or the next Build-Workplan phase — author via `/spec-to-change`, human `APPROVED`, then `./ralph.sh`.

## Blockers & Decisions Needed
- None active. Founder default calls stand (design Open Questions 1–3).
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S).
- **Staged sibling:** `substrate-hardening` (incl. `php ^8.3`→`^8.5`) — was kept out of THIS change (composer diff stays Pennant-only).

## Open Patterns
- **Read `progress.md` Codebase Patterns first** (12 consolidated patterns spanning VOs, casts, enums, lang/, Pennant, ActorContext, doc-pins).
- **NEW (5.2):** a `final sweep` task's deliverable is the **scenario→test traceability table** (build via `grep -rc '^#### Scenario:'` + `openspec show --json --deltas-only` deltaCount + `grep -rEn "^\s*(it|test|describe)\("` for test names); verification-only → no code change unless a gap is found. **Protected-file audit:** `git diff main --name-only | grep -E '<globs>'` then `git log --oneline main..HEAD -- <path>` per hit — a human `fix(ralph):`/swept `APPROVED` is not a loop violation; only a `feat(<change>):` commit editing a protected glob is.
- **Gotchas:** Pennant undefined feature → `false` (pair "off" asserts with `defined()`/`cases()`). HTTP/instance-method Feature tests use `Pest\Laravel\*` typed globals, never `$this->`. `config/` outside PHPStan → config-pin Feature test. `User::factory()->make()` = in-memory.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB). Closing ritual includes a LOCAL PostgreSQL verify.
