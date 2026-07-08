---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-08
---

# Hot Cache

## Last Updated
**2026-07-08 (pm) — P3 sweep BUILD STARTED: task 1.1 green.** The ralph loop is running on [`catalog-module-0-completeness-sweep`](openspec/changes/catalog-module-0-completeness-sweep/) (APPROVED + scaffolding committed as one `approve:` commit — exit-5 cannot fire). **1 of 16 tasks done.** The Layer-1 whitelist substrate has landed: table, model, Variant relation, 7 new tests green on both engines.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Full suite **2087/2087 on SQLite** (10 883 assertions; +7 from task 1.1) · PHPStan max **0** · Pint **clean** · `openspec validate --strict` valid. The new test file is green on **PG17** too; the last FULL PG17 run was 2080/2080 (pre-1.1) — task 7.2 re-runs the whole suite on PG.
- Run the suite via `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs at 128 MB). **PG17 recipe:** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest`.

## Active Change & Next Task
- **In flight: `catalog-module-0-completeness-sweep`** (branch `ralph/catalog-module-0-completeness-sweep`) — 12 delta reqs (5 ADDED / 7 MODIFIED on `product-catalog` + `operator-console`) · design D1–D11 · **16 tasks / 7 groups**. Key decisions (interview 2026-07-08): re-versioning = in-place + `version`++ (audit = old-version retrievability) · edit scope AC-minimum (Master identity ×4, Composite composition, Variant `tasting_notes`, whitelist) · whitelist = pivot per-(Variant, Format), empty ⇒ permissive, gate at SKU activation, maintenance audit-only even on `active` · reviewed-state identity edit **re-arms review** · **S1 = 4-suffix filter** (`.submitted/.resubmitted/.rejected/.identity_updated`) on BOTH readers · RM-15 = projection widened (`ProducerCreated` → `registered`, enum 2→3, existence guard in `CreateProductMaster`).
- **DONE: 1.1** — `catalog_variant_case_whitelists` (surrogate id; variant FK cascade, format + case-config FK restrict; `UNIQUE(variant, format, case_config)`), `VariantCaseWhitelistEntry` model, `ProductVariant::caseWhitelistEntries()`. Documented D6 micro-deviation: **no separate pair index** — the unique's leftmost prefix serves it (see progress.md).
- **NEXT: 1.2** — refactor review-freshness to the 4-suffix filtered derivation in BOTH readers (`ApprovalGovernance::assertNotRejectionPending` → review-stale, console `isRejectionPending` → `isReviewStale`). Design D4/D9/R3. Tests simulate edit rows via `AuditRecorder` directly (R4). Close with `grep -rn "orderByDesc('id')->value('action')"` showing no raw latest-action reads left.
- Remaining loop landmines: R1 (RM-15 blast radius — grep `CreateProductMaster` callers), R2 (`EnumsTest` 2→3), R5 (i18n EN+IT scanners), R6 (`{@see FQCN}` re-import reds `ModuleBoundariesTest`).
- **Then: RM-05** (capacity seat-set + WaitingList, last P1) via **K-side seam, ADR-first (grill-with-docs)** — dedicated session, do not fold into the sweep.

## Blockers & Decisions Needed
- None. Loop proceeding task-by-task; humans push and archive.

## Open Patterns
- **Derive-from-audit predicates must name their verb set** — any second audit writer breaks a raw latest-read (S1). Task 1.3 introduces exactly that second writer (`CatalogContentEdit`), so 1.2 must land first. Candidate for `knowledge/architecture/` once the build confirms it.
- **A composite unique's leftmost prefix IS the pair index** — don't add a redundant one (new, from 1.1).
- Console kit duplicates domain derivations (visibility gates) — sweep both sides when the domain predicate changes (design D9/R3).
