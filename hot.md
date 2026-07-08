---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-08
---

# Hot Cache

## Last Updated
**2026-07-08 (pm) — P3 sweep: task 1.3 green, the edit mechanic exists.** The ralph loop is running on [`catalog-module-0-completeness-sweep`](openspec/changes/catalog-module-0-completeness-sweep/). **3 of 16 tasks done.** Module 0 now has TWO audit writers, and 1.2's verb filter is what makes that safe. Substrate group closed; RM-14's real Actions start next.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Full suite **2106/2106 on SQLite** (10 993 assertions; +8 from 1.3) · PHPStan max **0** · Pint **clean** · `openspec validate --strict` valid. PG17: the new file + the 2 lifecycle/freshness files run **44/44**; the last FULL PG17 run was 2080/2080 (pre-1.1) — task 7.2 re-runs the whole suite there.
- Run the suite via `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs at 128 MB). **PG17 recipe:** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest`.

## Active Change & Next Task
- **In flight: `catalog-module-0-completeness-sweep`** (branch `ralph/catalog-module-0-completeness-sweep`) — 12 delta reqs · design D1–D11 · **16 tasks / 7 groups**. Key decisions (interview 2026-07-08): re-versioning = in-place + `version`++ · edit scope AC-minimum · whitelist = pivot per-(Variant, Format), empty ⇒ permissive, gate at SKU activation · reviewed-state identity edit **re-arms review** · S1 = 4-suffix filter · RM-15 = projection widened.
- **DONE: 1.1** whitelist pivot · **1.2** review-freshness 4-suffix filtered derivation (both readers) · **1.3** `CatalogContentEdit`.
- **1.3's shape — what 2.x/3.x/4.x plug into:** `edit($model, $entity, $verb, $apply)` → txn → `lockForUpdate` re-read → state guard (`draft|reviewed|active`; `retired` → `IllegalContentEdit`) → operator floor → **`$apply($model)` against the locked row** → own columns + `version`++ in ONE `UPDATE` → one `catalog.<segment>.<verb>` audit row (changed fields + version before/after) → **no domain event**. Each Action's re-checks and related-row writes live inside `$apply`; a rejected edit never invokes it. `CatalogAuditEnvelope` (new, static) derives the action string for BOTH audit writers.
- **NEXT: 2.1** — `UpdateProductMasterIdentity` (name, appellation, region, winery_story) on the mechanic. Verb `identity_updated`. Name/appellation change re-checks BR-Identity-1 dedup vs every OTHER non-retired Master (the `CreateProductMaster` join, excluding self) inside `$apply`; region/winery_story-only edits skip the dedup query; wine attributes via the within-module relation. Prove: version 1→2 exact, stays `active`, audit before+after carry old/new name, NO domain event, dedup collision leaves values+version unchanged, draft-edit → submit → distinct-approver activate NOT blocked, retired/system rejected.
- **⚠ 1.3 gotcha that bites 2.1/2.2/3.1/4.1/6.x:** `->toBe()` on a decoded jsonb snapshot map is a coin-flip — PG reorders keys by key length. Use **`->toEqual()`** (ignores key order, still pins nested list order). `knowledge/testing/rules.md` trap 3 refined in place.
- **Known gap:** 3.1 + 4.1 need a NON-versioning edit (delta spec: no `version` change; 4.1 also fires an event + silent no-op). The mechanic increments unconditionally — add a sibling entry point on `CatalogContentEdit` then, don't retrofit now.
- Remaining loop landmines: R1 (RM-15 blast radius — grep `CreateProductMaster` callers), R2 (`EnumsTest` 2→3), R5 (i18n EN+IT console scanners), R6 (`{@see FQCN}` re-import reds `ModuleBoundariesTest`).
- **Then: RM-05** (capacity seat-set + WaitingList, last P1) via **K-side seam, ADR-first (grill-with-docs)** — dedicated session, do not fold into the sweep.

## Blockers & Decisions Needed
- None. Loop proceeding task-by-task; humans push and archive.

## Open Patterns
- **Derive-from-audit predicates must name their verb set** — and when two writers feed one trail that a third party string-matches, SHARE the derivation function (`CatalogAuditEnvelope`), don't copy it.
- **A guard-then-closure mechanism should prove the closure never ran on rejection** — flip a flag inside the test's closure (1.3).
- **A composite unique's leftmost prefix IS the pair index** — don't add a redundant one (1.1).
- **`LIKE` with `_` is a prefilter, not a predicate** — narrow exactly in PHP (1.2).
- **Console duplications of a domain predicate need a lock-step test** — visible ⇔ blocked, one dataset (1.2).
