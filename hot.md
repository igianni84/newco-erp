---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-20 (ralph — `operator-console-catalog-master` task 1.1 green).** First task of the Operator Console (Catalog) change landed: the Filament panel's resource/page/widget **discovery is repointed into the OperatorPanel module**, plus the discovery directory skeleton. No Filament resource yet, no DB writes — pure panel-config seam. Suite 953/953 green.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **953/953 green** (949 prior + 4 new `PanelDiscoveryTest`, 4698 assertions). phpstan 0 errors; pint clean. `composer.json/lock` untouched; no migrations added; no protected files touched.
- ⚠ **Run the full suite as `php -d memory_limit=-1 vendor/bin/pest`** (and `… vendor/bin/phpstan analyse`) — bare `php artisan test` OOMs at the box's 128M in `laravel/pao` (false-red). See lessons.md 2026-06-20.

## Active Change & Next Task
- **Active: `operator-console-catalog-master`** (APPROVED, in progress — **1/11** done).
- **Done 1.1:** `AdminPanelProvider` discovery repointed `app/Filament/**` → `app/Modules/OperatorPanel/Filament/**` (namespaces `App\Modules\OperatorPanel\Filament\{Resources,Pages,Widgets}`); skeleton `Resources/Catalog/`, `Pages/`, `Widgets/` (each `.gitkeep`).
- **Next 1.2:** the **architecture test** — no Eloquent write (`save/saveQuietly/update/updateQuietly/delete/forceDelete/create/insert/fill/setAttribute`) on a model receiver under `app/Modules/OperatorPanel/`. Primary: a PHPStan custom rule wired into `phpstan.neon`; fallback: a Pest token-scan over `Filament/**`. MUST be proven red (planted violation) → green. Then **1.3** (amend the import-boundary test for the OperatorPanel read carve-out), then **2.1** (`ProductMasterResource` read surface — first PG17 task).

## Blockers & Decisions Needed
- None. `openspec validate operator-console-catalog-master --strict` green; on branch `ralph/operator-console-catalog-master`.

## Open Patterns
- **Operator-console (ADR 2026-06-19):** resources read-bind module models **read-only**; every write `app(<Action>)->handle()`; arch test (1.2, next) bans Eloquent writes in the namespace. 1.1 laid only the discovery seam.
- **Panel-discovery getters:** `Filament::getPanel('admin')->getResourceNamespaces()/getResourceDirectories()` (+Page/Widget) assert discovery config without a real resource; Filament no-ops on a missing discovery dir, so `.gitkeep` skeletons suffice (progress.md Codebase Patterns).
- Filament 5 write signatures (`handleRecordCreation`, `Filament\Actions\Action`) still to verify in `vendor/` before the 3.x–5.x write tasks (lessons.md).
