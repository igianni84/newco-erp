---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — `catalog-review-freshness-resubmit` (RM-06) is CHANGE COMPLETE (10/10).** Final task 5.1 (verification/reconciliation, no code) green on BOTH engines: SQLite full suite **1807/1807**, PG17 catalog+console+exception suites **391/391**. `<promise>CHANGE_COMPLETE</promise>` emitted — **awaiting human review → `openspec archive` + merge** (loop never archives/merges/pushes). RM-06 was the last Round-1 compliance-remediation item (Paolo Alfieri's 2026-07-01 mail). Verdict reports in **`docs/validation/`**; live backlog **`Remediation_Tracker.md`**. On origin/main: RM-07 `5b64cc8`, RM-04 `d8ec261`, RM-09+F3 `5eb415d`, RM-10 `04406b8`, RM-24 `4c373af`.

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **GREEN on both engines:** SQLite full suite **1807/1807** (9851 assertions); **PG17** (Docker `postgres:17`, GUIDE §2.7) `tests/Feature/Modules/Catalog` + `tests/Feature/Modules/OperatorPanel/Catalog` + `tests/Unit/Modules/Catalog/Exceptions` = **391/391** (3067 assertions); PHPStan max 0; Pint clean; `openspec validate --strict` valid.
- Full suite: `php -d memory_limit=-1 vendor/bin/pest` — bare `php artisan test` OOMs at 128M (lessons.md 2026-06-20).

## Active Change & Next Task
- **`catalog-review-freshness-resubmit` (RM-06) — COMPLETE, all 10 tasks `[x]`.** Block-gate on `reviewed→active` (activation blocked while the entity's latest governance action ends `.rejected`) + explicit `resubmit()` (`reviewed→reviewed`), derive-from-audit (no schema). edit-re-arms leg deferred to **RM-14**; canon MVP-DEC-019.
- **NEXT (human):** review branch `ralph/catalog-review-freshness-resubmit`, then `openspec archive catalog-review-freshness-resubmit --yes` + merge. The CI `tests-pgsql` lane re-runs the full suite on PG17 at the human push (development.md:86) — treat as merge acceptance.
- **NEXT (loop, new change):** Round-1 remediation drained with RM-06 — pick the next backlog item from `Remediation_Tracker.md` / prep via `spec-to-change`.

## Blockers & Decisions Needed
- None. ⚠️ **DEC-019 collision:** canon MVP-DEC-019 = review-freshness (this change); frozen spec's own DEC-019 = unrelated Module-S club composites — never conflate.
- Canon drift DEC-007→DEC-023 still open on Module K (RM-03, RM-05) — waits on Modules S/E/A.
- **Incidental findings open (Tracker §7):** F1 DemoSeeder SQLite-only; F2 prod operator-mgmt missing → SoD unsatisfiable in prod.

## Open Patterns
- **Local PG17 gate IS runnable here** (docker + `pdo_pgsql` present): boot `postgres:17` on **55432** (GUIDE §2.7), run `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=1024M vendor/bin/pest <paths>`, then `docker rm -f pg`. Run the `OperatorPanel/Catalog` FOLDER (self-contained — its i18n-scanner declaring file `ProductMasterConsoleI18nTest` is inside), never a bare console file (lessons.md 2026-06-20/122).
- **The review-freshness block-gate is engine-neutral** — one string column (`audit_records.action`) read via `orderByDesc('id')` + PHP `str_ends_with`; no PG-specific migration branch (CHECK / partial index / plpgsql trigger) touches it. 391/391 on PG17 with zero edits confirms.
- **Reconciliation shape (reusable):** literal `->reject(` = 0 in tests (reject via `Reject{E}Review` Actions / console `callAction('reject')`); the six sibling `*LifecycleTest`s keep reject and activate-success in SEPARATE closures (reject closure only asserts `%Activated%==0`, a read), so the block-gate broke only the ONE reject-then-activate-success path — `ProductMasterLifecycleTest`'s ex-"not terminal", inverted in 2.2. No exhaustive Catalog Action allow-list exists.
