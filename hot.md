---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-24
---

# Hot Cache

## Last Updated
**2026-06-24 — Operator-console PREMIUM finishing pass (for the Paolo demo).** Builds on UI pass #2 (`9edcc49`). A 360° "make it look like a premium product" sweep over all 13 Filament consoles + the panel chrome, driven by Giovanni's 8 feedback points. Local-only (NOT yet committed/pushed — close-ritual push is classifier-gated; ask first).

- **Brand chrome:** hand-tuned **OKLCH copper ramp** for `primary` (hue 47°, chroma muted ~10%, anchored on Pantone 8022 #A0715A at shade 500) — replaced `Color::hex()` whose auto-palette read as loud orange. Neutral chrome → `Color::Stone` (warm). `->font('Instrument Sans')` + custom Filament theme `resources/css/filament/admin/theme.css` (hairlines, softer shadows, branded login backdrop) via `->viteTheme()`. Logo asset was correct but rendered as an illegible sliver → **tight-cropped** the wordmark (glyph fills frame) + raised `brandLogoHeight` to 2.45rem; derived a warm-white dark logo + a clean concentric-circle **favicon/mark** (`public/images/brand/crcles-mark.png`). `->globalSearchKeyBindings(['mod+k'])`.
- **Shared kit helpers** on `OperatorConsoleResource`: `applyConsoleDefaults()` (newest-first + branded empty state), `stateFilter()` (distinct-token SelectFilter, no enum import), `badgedStateEntry()` (infolist state badge), `metadataSection()`, sortable `lifecycleStateColumn()`. Every console table now has filters + search + sortable cols; every detail page is icon-headed `Section`s + badges + collapsed Metadata.
- **Labels (#5):** killed raw `'#'.$id` from every option builder + `recordTitleAttribute='id'`; PR/SKU/constituent labels now read "Master — vintage — Format". Composite SKU view = `RepeatableEntry` (ordered constituents).
- **Geography (#3, light-cascade decision):** Country→Region **selects** from `config/wine_geography.php` (curated, NOT a domain table — spec is silent), Region **prefilled** from the producer via new `catalog_producer_states.region/country` columns (seeded in DemoSeeder, same channel as `producer_name`), Appellation = region-scoped **datalist** (free entry preserved — it's in BR-Identity-1).
- **Variant (#4):** sectioned view + Description (the existing `tasting_notes`); edit stays lifecycle-only (per decision).

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **GREEN:** full suite **1753/1753** (9494 assertions) · PHPStan **0** · Pint clean. Verified LIVE via Playwright screenshots (login/dashboard/PM/variant/SKU/composite/producer).

## Active Change & Next Task
- No openspec change open. This was an interactive polish pass on the existing operator console. **Next:** Giovanni reviews screenshots; then commit + push (gated — ask).

## Blockers & Decisions Needed
- None blocking. Geography "full cascade" (operator-populatable Country/Region/Appellation reference tables in Settings) was DEFERRED in favour of the light config-cascade — revisit post-demo with an ADR if wanted.

## Open Patterns
- **Filament gotcha (now a lesson + test guard):** a RelationManager is read-only on a `ViewRecord` page → its header `CreateAction` is DENIED before `canCreate()`; override `isReadOnly()→false` to surface it. `assertTableActionExists` does NOT catch this — live-verify (or assert `isReadOnly()===false`). This was the real cause of "no create button" (#3.4/#8).
- The DemoSeeder is still NOT chained into `DatabaseSeeder` — demo data needs `db:seed --class=Database\Seeders\DemoSeeder` explicitly.
