---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-08
---

# Hot Cache

## Last Updated
**2026-07-08 (pm) — task 2.3 green; the DEC-019 re-arm leg is discharged.** Ralph loop on [`catalog-module-0-completeness-sweep`](openspec/changes/catalog-module-0-completeness-sweep/). **6 of 16 done**, group 2 (RM-14) closed.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Full suite **2126/2126 on SQLite** (11 146 assertions) · PHPStan max **0** · Pint clean · `openspec validate --strict` valid.
- PG17: 2.3's blast radius (the new file + `ProductMasterLifecycleTest` + `UpdateProductMasterIdentityTest` + both freshness readers + `ResubmitActionsTest`) runs **55/55**. Last FULL PG17 run: 2080/2080 (pre-1.1) — task 7.2 re-runs the whole suite there.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs at 128 MB). **PG17:** prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.


## Active Change & Next Task
- **`catalog-module-0-completeness-sweep`** (branch `ralph/catalog-module-0-completeness-sweep`) — 12 delta reqs · design D1–D11 · 16 tasks / 7 groups. Interview decisions (2026-07-08): re-versioning = in-place + `version`++ · edit scope AC-minimum · whitelist = pivot per-(Variant, Format), empty ⇒ permissive, gate at SKU activation · reviewed-state identity edit **re-arms review** · S1 = 4-suffix filter · RM-15 = projection widened.
- **DONE: 1.1** whitelist pivot · **1.2** 4-suffix filtered freshness derivation · **1.3** `CatalogContentEdit` · **2.1** `UpdateProductMasterIdentity` · **2.2** `UpdateCompositeSkuComposition` · **2.3** re-arm e2e proof (test-only, no prod code).
- **The Action shape (copy it for 3.1/4.1)** and every other reusable mechanic: `progress.md` → `## Codebase Patterns`. Read it first.
- **NEXT: 3.1** — `SetVariantCaseWhitelist`: replace the (Variant, Format) pair's admitted CC set (D6). Audit-only: `catalog.product_variant.whitelist_updated`, before/after CC-id sets, **no `version` change, no event, no review-freshness effect** (a reviewed-then-whitelisted Variant must still activate). The mechanic increments `version` unconditionally ⇒ **3.1 and 4.1 both need a NON-versioning sibling entry point on `CatalogContentEdit`** — add it in 3.1, don't retrofit. Unknown Format/CC id ⇒ clean localized rejection, never a raw FK violation. Fixture: 1.1's `VariantCaseWhitelistEntry` + its `vcwEntry()` helper.
- Landmines: R1 (RM-15 blast radius — grep `CreateProductMaster` callers), R2 (`EnumsTest` 2→3), R5 (i18n EN+IT console scanners), R6 (`{@see FQCN}` — Pint auto-imports it; reds `ModuleBoundariesTest` in `app/`).
- **Then: RM-05** (capacity seat-set + WaitingList, last P1) via **K-side seam, ADR-first (grill-with-docs)** — dedicated session, not folded into the sweep.

## Blockers & Decisions Needed
- None. Loop proceeding task-by-task; humans push and archive.

## Open Patterns
- **A derived "latest wins" predicate needs a test where TWO arming causes coexist.** `.rejected` + a later `.identity_updated` is the only shape separating latest-wins from any-cause-pending — both block, both pass every count assertion. Assert the discriminating token (`edited` vs `un-remediated`), never a bare `->toThrow(Class)`.
- **Assert an append-only trail as ONE ordered list of action strings** — order, counts and no-extra-row in a single expectation.
- **PHPStan max: `pluck('col')` yields `mixed`** — hydrate and read the `@property string` instead of casting. `Collection::map()->all()` is never a `list`: wrap in `array_values()`.
- **`CreateProductMaster` writes no audit row** (its history is the `*Created` event); lifecycle transitions never touch `version` — only `CatalogContentEdit` does.
- **NEVER `->toBe()` a decoded jsonb snapshot map** — PG reorders keys by length; `->toEqual()` still compares nested ordered LISTS element-wise.
