---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-20 (ralph — `operator-console-catalog-master` task 4.1 green, SQLite + PG17).** Built the console's first LIFECYCLE write surface: submit-for-review + reject **header Actions** on the Product Master view page. `ViewProductMaster::getHeaderActions()` registers two `Filament\Actions\Action`s routing through `app(SubmitProductMasterForReview)->handle($record)` (`draft→reviewed`, audit-only) and `app(RejectProductMasterReview)->handle($record, $notes)` (a `->form()` collecting notes; stays `reviewed`). A private static `surfaceLifecycleOutcome()` sends a success notification, or a **danger** notification carrying the domain rejection's localized message (caught by base `\RuntimeException` — no `Catalog\Exceptions` import). Never `$record->save()`. Suite 967/967; phpstan 0; PG17 47/47.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **967/967 green** (4825 assertions, +4 vs 3.1). phpstan 0; pint clean. `composer.json/lock` untouched; no migrations; no protected files. New: `getHeaderActions()` (submit+reject) + the surface helper + `operator_console` lang keys (actions.submit/reject, fields.rejection_notes, notifications.{submitted,rejected,action_failed}, EN+IT) + a 4-test feature file.
- **PG17 verified:** `tests/Feature/Modules/OperatorPanel` = 47/47 on docker `postgres:17` (audit jsonb `after` decision/notes + `IllegalLifecycleTransition` rollback clean on PG).
- ⚠ Full suite/phpstan: `php -d memory_limit=-1 vendor/bin/pest` (and `… phpstan analyse`) — bare `php artisan test` OOMs at 128M. PG run: `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 … php -d memory_limit=512M vendor/bin/pest tests/Feature/Modules/OperatorPanel`.

## Active Change & Next Task
- **Active: `operator-console-catalog-master`** (APPROVED, in progress — **6/11** done).
- **Next 4.2:** Activate action + "second actor required" affordance + Producer-gate surfacing → `app(ActivateProductMaster)->handle($record)`. REUSE the `surfaceLifecycleOutcome` helper (gate/governance rejections are `RuntimeException` too). Add a visible localized affordance (distinct-operator-required copy). Seed two `Operator`s + an `active` `ProducerState`; assert distinct A-submit/B-activate → `active` + **1** `ProductMasterActivated` (newco_ops, actor B); same-actor → governance rejection notification, unchanged, 0 events; producer not active → gate rejection notification, stays `reviewed`, 0 events. Read `ApprovalGovernance`/`ProducerActivationGate` first. **PG17 task.**

## Blockers & Decisions Needed
- None. `openspec validate operator-console-catalog-master --strict` green; on branch `ralph/operator-console-catalog-master`.

## Open Patterns
- **Filament 5 write-through lifecycle ACTION** is now in progress.md Codebase Patterns (read before 4.2/5.x): header-action `$record`/`$data` name-injection; `surfaceLifecycleOutcome` base-`\RuntimeException` catch → localized danger notification; `callAction` asserts visibility FIRST (don't `->visible()`-gate the illegal path); `assertNotified` needs no `Notification::fake()`; `DatabaseMigrations` for write tests.
- **Pint docblock trap (lessons.md 2026-06-20):** in any `OperatorPanel\Filament` class, name cross-module exceptions in PROSE, never `{@see FQCN}` — Pint's `fully_qualified_strict_types` adds a `use Catalog\Exceptions\…` that breaks the 1.3 boundary carve-out. Console cross-module surface stays exactly {Models, Actions}.
