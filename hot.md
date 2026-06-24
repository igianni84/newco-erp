---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-24
---

# Hot Cache

## Last Updated
**2026-06-24 — Operator-panel DEMO POLISH for Paolo (COMMITTED + PUSHED — `4ead0b5` → origin/main).** Bundled with the prior nav-grouping + DemoSeeder threads in one demo-prep commit; goal = raise PERCEIVED quality of the Module 0/K consoles before a Paolo demo, no structural rework. Delivered:
- **Brand (CRCLES):** `AdminPanelProvider` primary = `Color::hex('#A0715A')` (Pantone 8022 C, pixel-sampled from `CRURATED/NewCo/Branding`), brandName "CRCLES", light + dark brand logos (copied to `public/images/brand/`), collapsible sidebar.
- **Semantic status badges** across all 12 consoles: shared `stateBadgeColor()` / `stateBadgeIcon()` on the kit (string-keyed → NO enum import, keeps {Models, Actions} surface). green=active/verified, blue=reviewed, amber=pending/suspended, red=rejected/closed/failed, gray=draft/retired + heroicon. Brand on chrome, SEMANTIC on badges (at-a-glance, the actual ask).
- **`version` removed** from every list table (optimistic-lock noise); survives in the PM detail "Metadata" section.
- **Producer NAME** in Catalog: `producer_name` denormalized onto `catalog_producer_states` (new additive migration + DemoSeeder populates; projector left as-is). PM list/detail now show "Domaine de la Romanée-Conti", not "#1 · active". Event payload UNTOUCHED — `SpineCreationChainTest` guards against PII in the event store, so the name lives on the read model, not the event (runtime path falls back to `#id` until the producer events carry the name — deferred 1-liner).
- **PM detail** → icon-headed Sections (Identity/Classification/Provenance/Variants/Metadata) + child Variants table via new within-module `ProductMaster::variants()` hasMany + `RepeatableEntry`.

~21 files touched + 1 migration + 2 logos. Prior two threads still open ↓.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **GREEN:** full suite `php -d memory_limit=1G vendor/bin/pest` → **1754/1754** (9457 assertions) · PHPStan max **0** · Pint clean (1 auto-fix). DemoSeeder re-verified on a throwaway DB: `producer_name` populates, every master resolves to its real producer name; Leflaive stays unprojected (fallback marker).
- **Run the suite via `php -d memory_limit=-1 vendor/bin/pest`, NOT `artisan test`** (128 MB OOM; lesson 2026-06-20).

## Active Change & Next Task
- No openspec change (`openspec list` empty). The three demo-prep threads (nav-grouping + `DemoSeeder` + UI polish) are COMMITTED + PUSHED in `4ead0b5` (origin/main). Working tree clean.
- Dev DB already refreshed (`migrate` applied `producer_name` + DemoSeeder reseeded). Local dev server running on :8000 for the demo — /admin (`operator@newco.test` / `password`).

## Blockers & Decisions Needed
- No blocker — committed + pushed to origin/main with Giovanni's explicit approval.
- **Flagged follow-up:** runtime `producer_name` needs a 1-line `ProducerActivated`/`ProducerRetired` payload enrichment — currently demo/seed-only; the runtime projector path leaves it null and the console degrades to `#id`. Badge icons (4 per row on Customer) are easily vetoable if Giovanni finds them busy.

## Open Patterns
- **Brand-on-chrome + semantic-on-status** badge split. A single string-keyed color/icon helper on the console kit serves all 12 consoles without importing any `*\Enums` — the presentation concern stays in OperatorPanel, the boundary stays intact.
