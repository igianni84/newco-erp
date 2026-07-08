---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-08
---

# Hot Cache

## Last Updated
**2026-07-08 (pm) — P3 sweep: task 1.2 green, the S1 hole is closed.** The ralph loop is running on [`catalog-module-0-completeness-sweep`](openspec/changes/catalog-module-0-completeness-sweep/). **2 of 16 tasks done.** Review-freshness is now a verb-filtered derivation in BOTH readers, so the second catalog audit writer (task 1.3) can land safely.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Full suite **2098/2098 on SQLite** (10 937 assertions; +11 from task 1.2) · PHPStan max **0** · Pint **clean** · `openspec validate --strict` valid. PG17: the 4 review-freshness files run **44/44**; the last FULL PG17 run was 2080/2080 (pre-1.1) — task 7.2 re-runs the whole suite there.
- Run the suite via `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs at 128 MB). **PG17 recipe:** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest`.

## Active Change & Next Task
- **In flight: `catalog-module-0-completeness-sweep`** (branch `ralph/catalog-module-0-completeness-sweep`) — 12 delta reqs · design D1–D11 · **16 tasks / 7 groups**. Key decisions (interview 2026-07-08): re-versioning = in-place + `version`++ · edit scope AC-minimum · whitelist = pivot per-(Variant, Format), empty ⇒ permissive, gate at SKU activation · reviewed-state identity edit **re-arms review** · S1 = 4-suffix filter · RM-15 = projection widened (`ProducerCreated` → `registered`, enum 2→3, existence guard in `CreateProductMaster`).
- **DONE: 1.1** — `catalog_variant_case_whitelists` + `VariantCaseWhitelistEntry` + Variant relation. Documented D6 micro-deviation: no separate pair index (the unique's leftmost prefix serves it).
- **DONE: 1.2** — `ApprovalGovernance::assertReviewIsFresh` (was `assertNotRejectionPending`) + console `isReviewStale` (was `isRejectionPending`; all 7 View pages). Latest of the 4 relevant verbs wins; stale iff `.rejected` or `.identity_updated`. New cause `activationBlockedByUnreviewedEdit` + key `catalog.lifecycle.activation_blocked_by_unreviewed_edit`; tokens `un-remediated` / `edited` discriminate the two causes. `grep "value('action')" app/` → no hits. **Deviation:** the key is EN-only — `lang/it/catalog.php` does not exist (catalog domain copy is EN-baseline; R5's EN+IT binds on `operator_console.*` keys, i.e. tasks 6.x).
- **NEXT: 1.3** — build `CatalogContentEdit` in `app/Modules/Catalog/Lifecycle/` (D3): txn + `lockForUpdate` re-read, state guard (`draft|reviewed|active`; `retired` rejected), `requireOperator` floor, field writes + `version`++ in one UPDATE, `catalog.<segment>.<verb>` audit with changed-fields + version before/after (R9), **no** domain event. Emit `identity_updated` (participates in review-freshness) vs `enrichment_updated`/`whitelist_updated` (do not) — both halves already pinned by `ReviewFreshnessVerbFilterTest`.
- Remaining loop landmines: R1 (RM-15 blast radius — grep `CreateProductMaster` callers), R2 (`EnumsTest` 2→3), R5 (i18n EN+IT console scanners), R6 (`{@see FQCN}` re-import reds `ModuleBoundariesTest`).
- **Then: RM-05** (capacity seat-set + WaitingList, last P1) via **K-side seam, ADR-first (grill-with-docs)** — dedicated session, do not fold into the sweep.

## Blockers & Decisions Needed
- None. Loop proceeding task-by-task; humans push and archive.

## Open Patterns
- **Derive-from-audit predicates must name their verb set** — confirmed by the build (S1). Promote to `knowledge/architecture/` once a second instance appears.
- **A composite unique's leftmost prefix IS the pair index** — don't add a redundant one (1.1).
- **`LIKE` with `_` is a prefilter, not a predicate** — narrow exactly in PHP; `ESCAPE` isn't engine-neutral (1.2).
- **A method rename reaches into test prose** — `grep tests/` after every rename (1.2: 7 files).
- **Console duplications of a domain predicate need a lock-step test** — visible ⇔ blocked, one dataset (1.2).
