---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-07
---

# Hot Cache

## Last Updated
**2026-07-07 — `parties-module-k-br-guards` APPROVED & ralph loop RUNNING. Task 1.1 (mini-ADR MVP-DEC-009 / Agreement-4) DONE, committed — 1/23.** Recovered a prior iteration that hit the session limit (`.last-output`: *"You've hit your session limit · resets 3pm"*) mid-1.1: it had written the 71-line ADR but left it **untracked** (no commit, no INDEX row, no memory). This iteration re-grounded the ADR vs LIVE canon (`cmless/main @ 360df0b`, read-only fetch — MVP-DEC-009 `:134` + AC-K-BR-Agreement-4 `:195` verbatim; frozen spec grep-confirmed Agreement-1/2/3-only), added the `decisions/INDEX.md` row, committed. `openspec validate --strict` **valid**.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. **No code changed yet** (1.1 = doc-only ADR + INDEX row).
- Baseline (RM-08 close): full suite **1975/1975** green on SQLite AND PG17 · PHPStan max **0** · Pint clean.
- **PG17 recipe:** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest` (`pg` container; NOT :5432). Full suite via `php -d memory_limit=-1 vendor/bin/pest` (artisan test OOMs at 128 MB).

## Active Change & Next Task
- **`parties-module-k-br-guards` — 1/23 done. NEXT = task 1.2:** mini-ADR `2026-07-07-adopt-mvp-dec-010-settlement-cadence-closed-set.md` (RM-22). LIVE canon MVP-DEC-010 (`:135`) + AC-K-BR-Agreement-2: closed set `{quarterly(default),monthly,semi-annual}`, server-enforced API+DB; `annual`/sub-monthly excluded → record `DemoSeeder annual→semi-annual` as a consequence. Then 1.3 (MVP-DEC-022, Club-6/Identity-6/Profile-5/Producer-5).
- **Grounding recipe + register line-map + collision-banner pattern are in the change `progress.md` → Codebase Patterns.** Reuse for 1.2/1.3 (same ADR shape).
- **Scope after the 3 ADRs:** §2 schema/enums/exceptions → §3 ProducerAgreement guards (RM-22/Agreement-4/**RM-20 inverts shipped coexistence tests**) → §4 Profile+Club (RM-21/Profile-5/Club-6-drops-`invite_only`/RM-19) → §5 Customer+Producer (Identity-6/Producer-5-lock) → §6 console+i18n → §7 close (human-gated).

## Blockers & Decisions Needed
- None. Change is APPROVED; branch `ralph/parties-module-k-br-guards`. `origin/main` == local `main` @ `bfb8fc7`.

## Open Patterns
- **Recover-before-redo:** on a fresh iteration, `git status` first — a crashed prior iteration may have left a complete artifact untracked (here: the whole ADR). Verify + add the missing sidecar (INDEX row, commit), don't rewrite.
- **Two orthogonal ProducerAgreement guards, never conflate:** Agreement-4 (1.1/3.2) = creation-time Club-active; RM-20 (3.3) = activation-time cross-shape mutual-exclusion. Different chokepoints/exceptions.
- **Trust code-grep over the tracker:** RM-20 has no "producer-wide flag" (single nullable `club_id`); RM-22 was FREE-TEXT not a dropdown; Club-6 drops `invite_only` (29 refs/16 files).
