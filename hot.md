---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-03
---

# Hot Cache

## Last Updated
**2026-07-03 — RM-03 task 3.1 DONE: cross-engine gate GREEN. Full suite 1951/1951 on BOTH SQLite `:memory:` (74.9s) AND PostgreSQL 17.10 (298.7s) — identical 10419 assertions, no PG-only trap. PHPStan max 0; Pint clean; `openspec validate --strict` valid. The 4 guards git-confirmed diff-free. Progress 4/5. Next = 4.1 (final memory → CHANGE_COMPLETE).**

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **Latest green (BOTH engines): full suite 1951/1951**, 10419 assertions.
- ⚠ **Full suite = `php -d memory_limit=2G vendor/bin/pest`** (`php artisan test` re-spawns a child ignoring `-d` → 128M fatal; on PG it also swallows the JSON summary via pao teardown). Filtered runs fit 128M.
- ⚠ **PG17 cross-engine recipe (worked, task 3.1):** `docker run -d --name pg --tmpfs /var/lib/postgresql/data --shm-size=256m -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; poll `docker exec pg pg_isready`; run the 2G pest cmd prefixed `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`; `docker rm -f pg` after. **5432 = invoicing PG16 — don't reuse.** phpunit.xml's `DB_CONNECTION=sqlite` is un-`force`d, so shell env overrides it (no xml edit needed).
- ⚠ **Bare path/dir on `OperatorPanel/**` reds the `*ConsoleI18nTest`s** (scanner declared in `ProductMasterConsoleI18nTest`, full-suite-only) — pass that file alongside to run one alone. Not a regression.

## Active Change & Next Task
- **ACTIVE (APPROVED): `parties-membership-charge-on-approval`** (branch `ralph/parties-membership-charge-on-approval`). RM-03 — atomic **approve = charge = activation**. **Progress 4/5.** 1.1✅ 1.2✅ 2.1✅ 3.1✅.
- **⭐ NEXT = task 4.1 (memory, docs-only): consolidate progress.md, overwrite hot.md, append log.md; decide knowledge/lessons promotion; then reply `<promise>CHANGE_COMPLETE</promise>`.** No code. RM-03 knowledge-promotion confirmation date = the change's FUTURE archive-dir date (not today). After 4.1 → all 5 done → final quality pass → CHANGE_COMPLETE (humans review/merge/archive).

## Blockers & Decisions Needed
- **⚠ FLAG for Giovanni (2.1 IT copy):** used **"Adesione approvata e attivata."** (block's «membership»→«adesione» convention, `lang/it` ~L630) over tasks.md's literal "Iscrizione…". Design marks it "subject to Giovanni's review" — one-word revert if he wants "Iscrizione".
- **Deferred (NOT in RM-03):** real charge (mandate/instrument/`fee_paid_at`/invoice) → Module S/E (F4–F6); Hero-Package **seat gate** (MVP-DEC-017) → **RM-05** (⏸️ Module A `qty`); SoD/four-eyes → **RM-08**.
- **⚠ Number collision:** `MVP-DEC-016` (membership) ≠ greenfield `DEC-016` (AI-copilot) — always the full token.

## Open Patterns
- **A red-green FSM-shape flip inverts EVERY observer in one iteration; the isolated writer's contract (`ProfileActivationTest`) + the source-scan guards (`SupplyLifecycleChainTest` allow-list, `ComplianceIndependenceTest` OC-write count, `SpineCreationChainTest`) stay diff-free** iff no Action/Event class is added/renamed and the literal write count is unchanged — held diff-free on PG too.
- **Relocate-before-delete (lesson 2026-07-03):** a task's "delete file X" can under-describe X's coverage — grep first, rehome orthogonal coverage, then delete.
- **F4 candidate (untriaged):** truth-spec *Hold Registry* still "six-value" vs code's 8 (RM-04 debt).
