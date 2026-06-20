---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-20 (ralph — `operator-console-catalog-master` task 3.1 green, SQLite + PG17).** Built the console's FIRST WRITE surface: a Filament write-through Create page for Product Master. `handleRecordCreation()` routes the form into `app(CreateProductMaster)->handle(...)` (never `$model->save()`); the BR-Identity-1 dedup rejection is caught (`\RuntimeException`) and re-raised as a `ValidationException` on `data.name` (a form field error, not a 500); the actor envelope (`newco_ops` + operator id) comes from the `operator` guard. Suite 963/963; phpstan 0; PG17 43/43.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **963/963 green** (4780 assertions, +3 vs 2.1). phpstan 0; pint clean. `composer.json/lock` untouched; no migrations; no protected files. New: the Create page + Resource `form()`/`producerOptions()` + a header create LINK + `operator_console` lang keys (EN+IT) + a 3-test feature file; the 2.1 resource test's `getPages()` assertion updated to `{index, create, view}`.
- **PG17 verified** (first console WRITE): `tests/Feature/Modules/OperatorPanel` = 43/43 on docker `postgres:17`.
- ⚠ Full suite/phpstan/arch: `php -d memory_limit=-1 vendor/bin/pest` (and `… phpstan analyse`) — bare `php artisan test` OOMs at 128M, and the `tests/Architecture` PHPStan RuleTestCase OOMs under it too. PG run: `-d memory_limit=512M vendor/bin/pest` + `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 …`.

## Active Change & Next Task
- **Active: `operator-console-catalog-master`** (APPROVED, in progress — **5/11** done).
- **Next 4.1:** Submit-for-review + Reject Filament **Actions** (on the resource/view, NOT a page) → `app(SubmitProductMasterForReview)->handle($record)` (audit-only `draft→reviewed`) and `app(RejectProductMasterReview)->handle($record, $notes)` (a `->form()` collecting `notes`, stays `reviewed`). Domain exceptions → danger **notification**. Assert `reviewed` + **0** domain events + an `AuditRecord` with `actor_role: newco_ops`. Re-confirm `Filament\Actions\Action` `->action()`/`->form()`/`->requiresConfirmation()` + `Filament\Notifications\Notification` in `vendor/` first. **PG17 task.**

## Blockers & Decisions Needed
- None. `openspec validate operator-console-catalog-master --strict` green; on branch `ralph/operator-console-catalog-master`.

## Open Patterns
- **Filament 5 write-through CREATE page** is now in progress.md Codebase Patterns (read before 4.x/5.x): override `handleRecordCreation` → `app(<Action>)->handle()`; narrow `array<string,mixed>` form data with `is_string`/`is_numeric` guards (strict PHPStan rejects `(int)`/`strval`/`intval` on `mixed`); map domain rejection → `ValidationException::withMessages(['data.<field>' => …])`; `DatabaseMigrations` for write-action tests; the affordance is an `Action::make()->url(getUrl('create'))` LINK, never `CreateAction` (its inline-modal `$record->save()` bypasses the action — `ListRecords.php:94`).
- **Console cross-module surface = exactly {Models, Actions}.** Lifecycle tasks 4.1–5.2 catch domain rejections via base types (`\RuntimeException`/`\Throwable`) + `getMessage()` and render enums via the cast instance — so no later task re-amends the 1.3 carve-out. Two console guards live: 1.2 PHPStan no-Eloquent-write rule + 1.3 boundary carve-out.
