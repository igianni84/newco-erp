---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-08
---

# Hot Cache

## Last Updated
**2026-07-08 (pm) — task 3.1 green; the mechanic now has TWO entry points.** Ralph loop on [`catalog-module-0-completeness-sweep`](openspec/changes/catalog-module-0-completeness-sweep/). **7 of 16 done.**

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Full suite **2143/2143 on SQLite** (11 234 assertions) · PHPStan max **0** · Pint clean · `openspec validate --strict` valid.
- PG17: 3.1's blast radius (new file + `CatalogContentEditTest` + `VariantCaseWhitelistSchemaTest` + both 2.x edit tests + `ProductVariantLifecycleTest` + both freshness tests) runs **69/69**. Last FULL PG17 run: 2080/2080 (pre-1.1) — task 7.2 re-runs the whole suite there.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs at 128 MB). **PG17:** prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **`catalog-module-0-completeness-sweep`** (branch `ralph/catalog-module-0-completeness-sweep`) — 12 delta reqs · design D1–D11 · 16 tasks / 7 groups. Interview decisions (2026-07-08): re-versioning = in-place + `version`++ · edit scope AC-minimum · whitelist = pivot per-(Variant, Format), empty ⇒ permissive, gate at SKU activation · reviewed-state identity edit **re-arms review** · S1 = 4-suffix filter · RM-15 = projection widened.
- **DONE: 1.1** whitelist pivot · **1.2** 4-suffix filtered freshness derivation · **1.3** `CatalogContentEdit` · **2.1** `UpdateProductMasterIdentity` · **2.2** `UpdateCompositeSkuComposition` · **2.3** re-arm e2e proof · **3.1** `SetVariantCaseWhitelist` + `CatalogContentEdit::maintain()` + `UnknownCatalogReference`.
- **The trap the loop flagged is discharged:** `CatalogContentEdit` now exposes `edit()` (version++) **and** `maintain()` (audit-only), both over one private `perform(..., bool $reVersion)`. **4.1 uses `maintain()` — do not reopen `edit()`.**
- Every reusable mechanic: `progress.md` → `## Codebase Patterns`. Read it first.
- **NEXT: 3.2** — whitelist activation gate in `ActivateSellableSku` (D6, R10). Resolve SKU → PR → (variant_id, format_id); pair with ≥1 whitelist row and the SKU's `case_configuration_id` not among them ⇒ new localized exception (`CaseConfigurationNotWhitelisted`); **zero rows ⇒ permissive**. Runs beside the existing PR+CC-active cascade conjuncts. **R10: consult the whitelist ONLY at activation** — an already-`active` SKU on a removed CC stays untouched (state/audit/events). Read shape + fixtures: `SetVariantCaseWhitelistTest` (`whitelistAdmittedIds()`, `whitelistFixtureEntry()` — 1.1's `vcwEntry()` is NOT reusable; Pest's top-level fns share one namespace).
- Landmines: R1 (RM-15 blast radius — grep `CreateProductMaster` callers), R2 (`EnumsTest` 2→3), R5 (i18n EN+IT console scanners), R6 (`{@see FQCN}` — Pint auto-imports it; reds `ModuleBoundariesTest` in `app/`).
- **Then: RM-05** (capacity seat-set + WaitingList, last P1) via **K-side seam, ADR-first (grill-with-docs)** — dedicated session, not folded into the sweep.

## Blockers & Decisions Needed
- None. Loop proceeding task-by-task; humans push and archive.

## Open Patterns
- **`version` is the IDENTITY version.** "Should this bump `version`?" is never the question — "is this the entity's identity?" is. The three facts *no `version`* / *no re-arm* / *no event* are ONE fact, and the `edit()` vs `maintain()` choice encodes it at the call site.
- **4.1's no-op is a `$apply`-contract change, not an entry-point one:** let the closure return `null` for *nothing to record*; `perform()` then writes nothing. Deliberately not built in 3.1 — no test could exercise it there (an identical whitelist replace still legitimately audits: the operator *did* decide the set).
- **5.2 should reuse `UnknownCatalogReference::forIds('Producer', [$id])`**, not mint `UnknownProducerReference` (the task text's class name was an `e.g.`).
- **NEVER `->toBe()` a decoded jsonb snapshot map** — PG reorders keys by length; `->toEqual()` still compares nested ordered LISTS element-wise.
- **PHPStan max: `Collection::map()->all()` is never a `list`** — wrap in `array_values()`. Third bite in this change (the Action, then a test helper).
