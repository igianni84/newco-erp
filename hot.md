---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-20 (ralph — `operator-console-catalog-master` task 2.1 green, SQLite + PG17).** Built the console's first Filament resource: `ProductMasterResource` — a READ-ONLY surface over Catalog `ProductMaster` (List + View), producer column resolved via the `ProducerState` projection, no create/edit/delete action, `operator_console` i18n group (EN+IT) seeded. Suite 960/960; phpstan 0; PG17 40/40.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **960/960 green** (4756 assertions, +5 vs 2.0). phpstan 0; pint clean. `composer.json/lock` untouched; no migrations; no protected files. Only new app code (`Filament/Resources/Catalog/**`), `lang/{en,it}/operator_console.php`, and a test.
- **PG17 verified** (first DB-action task): `tests/Feature/Modules/OperatorPanel` = 40/40 on docker `postgres:17`. Local box has no native psql; Docker Desktop daemon was down → `open -a Docker` then ran it (now running).
- ⚠ Full suite/phpstan: `php -d memory_limit=-1 vendor/bin/pest` (and `… phpstan analyse`) — bare `php artisan test` OOMs at 128M (false-red). PG run uses `-d memory_limit=512M vendor/bin/pest` + `DB_CONNECTION=pgsql …`. See lessons.md.

## Active Change & Next Task
- **Active: `operator-console-catalog-master`** (APPROVED, in progress — **4/11** done).
- **Next 3.1:** Create page → `app(CreateProductMaster::class)->handle(name, producerId, appellation, region, wineryStory)` via `handleRecordCreation(array $data): Model`; producer **select** from `ProducerState`; map the BR-Identity-1 dedup rejection to a **form field error** (catch via base exception type + `getMessage()`, NOT `use Catalog\Exceptions`). **First console WRITE** — drive the action (never `$model->save()`), assert `draft` Master + exactly 1 `ProductMasterCreated` with `actor_role: newco_ops` + `actor_id` (ActorContext via operator guard). Re-confirm Filament 5 `handleRecordCreation`/`Filament\Actions\Action` signatures in `vendor/` first. **PG17 task.**

## Blockers & Decisions Needed
- None. `openspec validate operator-console-catalog-master --strict` green; on branch `ralph/operator-console-catalog-master`.

## Open Patterns
- **Filament 5 read-only resource skeleton** (verified API) is now in progress.md Codebase Patterns — read before 3.x–5.x: table/infolist via `getStateUsing` (both from `HasCellState`); `Schema->components()`; `getPages()`={index,view}; read-only = add nothing (header actions default `[]`); labels via static `getModelLabel()`+`(string) __()`; authz no-policy+non-strict = allowed; test via `Livewire::test()` + Filament macros + `actingAs(Operator::factory()->create(),'operator')`.
- **`EloquentBuilder::value('enum_col')` applies the cast** (returns the enum, not a raw string) — render module enums via the cast instance (`->first()?->status->value`), never assume raw (lessons.md).
- **Console cross-module surface = exactly {Models, Actions}.** For 3.1–5.2: catch domain rejections via base types (`\Throwable`/`DomainException`/`ValidationException`), render enums via the cast instance — so no later task re-amends the 1.3 carve-out. Two console guards live: 1.2 PHPStan no-Eloquent-write rule + 1.3 boundary carve-out.
