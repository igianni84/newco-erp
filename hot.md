---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-24
---

# Hot Cache

## Last Updated
**2026-06-24 — Operator-panel UI PASS #2 (console IA restructure for the Paolo + Taha demo — COMMITTED + PUSHED, `9edcc49` → origin/main).** Builds on the prior demo-polish (`4ead0b5`: #A0715A brand, semantic badges, producer names). Reshapes the Module 0/K console information architecture per Giovanni's 8 points:
- **Dashboard:** dropped the Filament Account+Info widgets → two discovered analytics: `CatalogPartiesOverview` (6 KPI stats over Modules 0+K) + `MembershipsByStateChart` (bar, profiles-by-state).
- **Catalog:** `ProductVariant` hidden from sidebar → seen+created inside `ProductMaster` via `VariantsRelationManager` (replaced the static RepeatableEntry). New `CatalogSettings` Filament **cluster** ("Settings", in the Catalog group) holds Format · CaseConfiguration · ProductReference as tabs (each: `$cluster` + `getNavigationGroup()→null` → flat sub-nav). Sellable/Composite SKU stay top-level.
- **Parties:** `Club` + `ProducerAgreement` hidden → seen+created inside `Producer` via `ClubsRelationManager` + `ProducerAgreementsRelationManager` (added within-module `Producer::producerAgreements()` hasMany). `Customer` gains a read-only `MembershipsRelationManager` (profiles). `Profile` nav relabelled "Memberships" (model label stays "Profile"). New **`SupplierResource`** (the thin `parties_suppliers` model had no console) — list/view/create via `CreateSupplier`; no lifecycle, plain ViewRecord.
- **Branding:** light logo was wrongly the BLACK variant → swapped to `CRCLES_Logo Pantone 8022.png` (copper). Color #A0715A already in place.

RM creates route through the owning module's domain action (`->using($this->createX(...))`, owner id injected, parent picker dropped) — no Eloquent write; `canCreate()→true` (no per-model policy). i18n EN+IT (new top-level groups: cluster/relations/nav/supplier/dashboard).

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **GREEN:** full suite **1753/1753** · PHPStan **0** · Pint clean. New `OperatorConsoleUiPassTest` (7) + rewritten `OperatorConsoleNavigationTest` (the new IA = source of truth).
- **Run pest via `php -d memory_limit=1024M vendor/bin/pest`, NOT `artisan test`** (128 MB OOM; the `-d` flag never reaches artisan's child process, then Collision OOMs rendering a failure — masks the real error).
- `npm run build` FAILS (`vite` missing from node_modules) → custom Instrument Sans font not compiled; brand color (runtime CSS var) + logo (public/) unaffected, panel renders 200. `npm install` to restore the font.

## Active Change & Next Task
- No openspec change. Pushed in `9edcc49` (origin/main) with Giovanni's approval; working tree clean. DemoSeeder data present (8 producers, 12 customers, 10 clubs, 9 masters, 3 suppliers, 18 profiles); demo via `php artisan serve` → /admin · `operator@newco.test` / `password`.
- **NEXT:** demo to Paolo + Taha. Optional follow-ups in Blockers below.

## Blockers & Decisions Needed
- **Color nuance:** Filament generates a SATURATED palette from #A0715A's hue (47, copper) — buttons read more vivid than the muted metallic logo. Offered to pin an explicit muted palette if Giovanni wants an exact logo match. Not blocking.
- RM header-action create EXECUTION is not unit-tested (Filament's isolated-RM test helpers don't resolve header-action visibility) — verify live.

## Open Patterns
- **Nest a child console in its parent via a RelationManager + create-through-domain-action** (`->using`, `canCreate()→true`, reuse `<Resource>::table()` for columns, row View → resource view URL) — the repeatable recipe for decluttering the sidebar across the 7 remaining module consoles.
- **Filament cluster for "settings"-type reference data** — clustered resources return a null nav group for flat tabs; the cluster carries the group placement.
