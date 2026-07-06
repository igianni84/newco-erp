---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-06
---

# Hot Cache

## Last Updated
**2026-07-06 — RM-08 `parties-producer-approval-sod`, task 5.1 ✅ (9/10): ADR honesty correction, in-place, RM-09-style.** Corrected `decisions/2026-06-17-approval-separation-of-duties-role-gated.md`: the 2026-06-17 RESOLVED note over-claimed BOTH `catalog-lifecycle-approval` AND `parties-producer-lifecycle` "was already correct" on separation-of-duties. Only **Catalog** was — `parties-producer-lifecycle` explicitly **deferred** the multi-role Creator→Approver workflow (AC-K-J-10) and shipped `ActivateProducer` as a **single operator Action** via the `ActorContext` seam (KYC gated later by `parties-compliance`), with no distinct-actor floor + `system`/null actor accepted (grep-confirmed: zero SoD terms in that archive). **RM-08 makes the "already correct for Parties" claim true.** Added a dated `**Correction (2026-07-06 · RM-08).**` banner after the frontmatter + reworded the two overstated clauses (RESOLVED "Net effect" headline + Context "built in …" bullet). **NO supersede** (`status: active` untouched), **Decision/Alternatives/Trade-offs byte-identical**, **`decisions/INDEX.md` unchanged**. `git diff --stat` = 1 file, +4/−2. Committed.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- Full suite **1975/1975** (10497 assn) at HEAD's PHP tree · PHPStan max **0** · Pint clean · `openspec validate parties-producer-approval-sod --strict` **valid**. Task 5.1 was **doc-only** (zero PHP delta → code quality commands are no-ops on the last-green tree). SQLite only so far; the cross-engine `normalizeActorId` `===` + loose-`toEqual` bigint reads ride PG17 at close (task 6.1).
- ⚠ **Full/arch suite = `php -d memory_limit=-1 vendor/bin/pest`** — `php artisan test` (128 MB subprocess) OOMs on the full suite AND any single arch file. Per-`--filter`/per-file runs fine under `artisan test`.

## Active Change & Next Task
- **ACTIVE = `parties-producer-approval-sod` (RM-08)**, APPROVED, building. Branch `ralph/parties-producer-approval-sod`. **9/10 done.**
- **NEXT = task 6.1 (close/verify):** full suite green on **SQLite AND PG17** (the `normalizeActorId` cross-engine `===` + loose-`toEqual` bigint reads finally exercise Postgres); `vendor/bin/phpstan analyse` 0 (max); `vendor/bin/pint --test` clean; `openspec validate parties-producer-approval-sod --strict` green. Then the **GUIDE §2.7 semantic-verify** pass. Tracker RM-08 🟡→✅ + `log.md`/`hot.md` = the **close ritual proper (outside the task list)**. After 6.1 all 10 tasks `[x]` → emit `<promise>CHANGE_COMPLETE</promise>` (do NOT archive/merge — humans do that).

## Blockers & Decisions Needed
- None blocking. Scope (Giovanni 2026-07-06): Producer-only SoD, 2-step Creator→Approver.
- **6.1 needs a real PG17 run** (is Postgres reachable in this env? if not, note it, run SQLite green, and document the PG-truthful assertion for the human per the change's Migration Plan) — the one non-SQLite gate left.
- **Repo sync:** `origin/main` @ `067f459`; ralph branch ahead. Assistant `git push` classifier-gated → Giovanni pushes at close.

## Open Patterns
- **RM-09-style in-place ADR correction (reusable):** dated `**Correction (YYYY-MM-DD · RM-XX).**` blockquote banner after the frontmatter, back-pointed from each reworded clause ("see the Correction note above/at top"); NO supersede when the *decision* is unchanged (a supersede would falsely signal it was re-made); leave protected Decision/Alternatives/Trade-offs bodies byte-identical + `decisions/INDEX.md` untouched; reword EVERY instance of the overclaim in the non-protected sections so no residual contradiction survives (the RM-09 "F3" lesson). Doc-honesty still obeys "never invent names" — grep the archive to cite it.
- **Genuine-lineage seeder/fixture idiom:** `app(ActorContext::class)->runAs(NewcoOps, $creatorId, fn () => app(CreateProducer::class)->handle(...))` records `ProducerCreated` with the creator's actor_id; `callAction('activate')` auto-confirms the 3.1 `affordance.second_actor` modal.
- **Migrated tests grow the event set — scope activation assertions by NAME (`->where('name','ProducerActivated')->sole()`), never total-count.**
- **Isolated-run false failure:** `ProducerConsoleI18nTest` alone fails its last test (scanner declared in `ProductMasterConsoleI18nTest`, loaded suite-wide only) — run both i18n files together / full suite. Not a regression.
