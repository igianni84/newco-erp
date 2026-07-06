---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-06
---

# Hot Cache

## Last Updated
**2026-07-06 — RM-08 `parties-producer-approval-sod` COMPLETE (10/10) ✅ — awaiting human §2.7 merge/archive/push.** Task 6.1 (close/verify) green: full suite **1975/1975** (10497 assn) on **SQLite AND PG17** (the `pg` container, postgres:17.10 / `newco_test` — the `normalizeActorId` cross-engine `===`, the `actor_role` CHECK, partial indexes and plpgsql triggers finally exercised on Postgres), PHPStan max **0**, Pint `--test` clean, `openspec validate --strict` green. **GUIDE §2.7 semantic-verify** via an independent subagent (the "nuova finestra" intent) → **0 CRITICAL**: completeness (12/12 delta scenarios → asserting tests), correctness (self-approval can't slip — int-to-int cross-engine compare; D6 order proven; no bypass writer), coherence (D1–D8, invariant 10, surface-not-reimplement) all CLEAN. 1 WARNING (residual `parties-producer-lifecycle` overclaim in the ADR *References* "Shipped enforcement:" bullet — RM-09 "F3" residual class) **fixed in-place**; 1 SUGGESTION (console-test body assertion) deferred. All 10 tasks `[x]` → **CHANGE_COMPLETE**.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- Full suite **1975/1975** (10497 assn) green on **SQLite (~93s) AND PG17 (~490s)** · PHPStan max **0** · Pint clean · `openspec validate parties-producer-approval-sod --strict` valid.
- **PG17 recipe (env has it wired):** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest` (the `pg` docker container; phpunit sqlite `<env>` is `force=false` so real env wins — matches `docs/development.md` `tests-pgsql`). **NOT** `invoicing-system-db-1` on 5432 (different project).
- ⚠ Full suite = `php -d memory_limit=-1 vendor/bin/pest` — `php artisan test` (128 MB subprocess) OOMs on the full suite. Per-`--filter`/per-file runs fine under `artisan test`.

## Active Change & Next Task
- **RM-08 `parties-producer-approval-sod` is COMPLETE (10/10) and VERIFIED — but NOT merged/archived/pushed** (the loop never does that). Tracker RM-08 flipped ✅ *awaiting review* across §1/§3/detail/§6. Branch `ralph/parties-producer-approval-sod`.
- **NEXT action is the HUMAN's GUIDE §2.7 close:** `git checkout main && git merge --no-ff ralph/parties-producer-approval-sod` → `openspec archive 2026-07-06-parties-producer-approval-sod --yes` → `git push` → `git branch -d ralph/parties-producer-approval-sod`.
- No further ralph task in this change → this iteration emits `<promise>CHANGE_COMPLETE</promise>`. After merge, the next tracker item RM-05 (capacity seat-set) is ⏸️ blocked on Module A `qty`; the human picks the next change.

## Blockers & Decisions Needed
- None. Change built, all gates green on **both** engines, semantic-verify CLEAN.
- **Repo sync:** `origin/main` @ `067f459`; ralph branch ahead (7 commits incl. this close). Assistant `git push` classifier-gated → Giovanni pushes at §2.7.

## Open Patterns
- **PG17 is reachable here** (the `pg` container) — the close-ritual PG run is no longer a deferred "document-for-human" step; run it directly with the env-var recipe above.
- **Honesty-correction "reword every instance" must sweep pointer/reference lists too**, not just prose: a References bullet under an evaluative header ("Shipped enforcement:") listing the disproven change re-plants the overclaim. Generalizes RM-09 F3 → lessons.md 2026-07-06. Final step of any doc-honesty task: `grep -n '<disproven-name>' <adr>`, eyeball each hit.
- **RM-09-style in-place ADR correction:** dated `**Correction (YYYY-MM-DD · RM-XX).**` banner after frontmatter; NO supersede when the decision is unchanged; Decision/Alternatives/Trade-offs byte-identical + `decisions/INDEX.md` untouched.
- **Genuine-lineage fixture idiom:** `app(ActorContext::class)->runAs(NewcoOps, $creatorId, fn () => app(CreateProducer::class)->handle(...))` records `ProducerCreated` with the creator's actor_id; `callAction('activate')` auto-confirms the second-actor modal.
