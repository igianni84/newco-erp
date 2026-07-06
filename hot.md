---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-06
---

# Hot Cache

## Last Updated
**2026-07-06 — RM-08 `parties-producer-approval-sod` MERGED to main + ARCHIVED locally ✅ — only `git push` left (human, gated).** GUIDE §2.7 close ritual run in a fresh window: (1) branch review — SoD core correct; (2) **PG17 pre-merge gate 1975/1975** (10497 assn, 301s) on the `pg` container; (3) **independent semantic-verify subagent → VERDICT CLEAN, 0 CRITICAL** — it re-ran the tests itself (34/34 SoD + 46/46 console/seeder/chain + 3/3 boundary, PHPStan 0, Pint clean) rather than trusting the loop: self-approval blocked, int/string trap closed by `normalizeActorId`, no bypass writer, D6 order honored, no doc overclaim; 1 cosmetic SUGGESTION (console test asserts notification title not body) deferred; (4) `git merge --no-ff` → main **`bf4aff4`**; (5) `openspec archive` → `2026-07-06-parties-producer-approval-sod`, both delta specs folded into `openspec/specs/` (operator-console + party-registry), **10/10 truth-specs validate**.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- Full suite **1975/1975** (10497 assn) green on **SQLite AND PG17 (301s)** · PHPStan max **0** · Pint clean · all 10 truth-specs validate.
- **PG17 recipe (env has it wired):** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest` (the `pg` docker container; **NOT** `invoicing-system-db-1` on 5432).
- ⚠ Full suite must run via `php -d memory_limit=-1 vendor/bin/pest` — `php artisan test` (128 MB subprocess) OOMs on the full suite. Per-`--filter`/per-file runs fine under `artisan test`.

## Active Change & Next Task
- **No active change.** `openspec list` → "No active changes found." RM-08 is merged to main (`bf4aff4`) + archived.
- **NEXT human action: `git push`** — local `main` is ahead of `origin/main` (`52b9983`) by the cosmetic hot.md commit + the merge + 10 branch commits + this archive commit. Then `git branch -d ralph/parties-producer-approval-sod` once pushed.
- After push, the human picks the next change. Tracker RM-05 (capacity seat-set) is ⏸️ blocked on Module A `qty`.

## Blockers & Decisions Needed
- None. Merged + archived locally, all gates green on both engines, semantic-verify CLEAN.
- **Repo sync:** `origin/main` @ `52b9983`; local `main` @ `bf4aff4` (ahead — push pending). Assistant `git push` classifier-gated → Giovanni pushes.

## Open Patterns
- **Independent semantic-verify as a subagent = faithful "nuova finestra"**: keeps main context clean AND is genuinely adversarial — re-run the suite yourself, don't trust the loop's self-report. Here CLEAN matched the loop's own report.
- **PG17 is reachable here** (the `pg` container :55432) — the close-ritual PG run is a direct pre-merge step, not a deferred "document-for-human" note.
- **RM-09-style in-place ADR correction:** dated `**Correction (YYYY-MM-DD · RM-XX).**` banner; NO supersede when the decision is unchanged; `decisions/INDEX.md` untouched.
- **Genuine-lineage fixture idiom:** `app(ActorContext::class)->runAs(NewcoOps, $creatorId, fn () => app(CreateProducer::class)->handle(...))` records `ProducerCreated` with the creator's actor_id; `callAction('activate')` auto-confirms the second-actor modal.
