---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-20 (ralph — `operator-console-catalog-master` task 4.2 green, SQLite + PG17).** Added the console's APPROVAL step: an `activate` header Action on the Product Master view page. `Action::make('activate')->requiresConfirmation()->modalDescription(__('…affordance.second_actor'))` carries the visible **"second actor required"** affordance, and routes through `app(ActivateProductMaster)->handle($record)` via the reused 4.1 `surfaceLifecycleOutcome()` helper — never `$record->save()`. The console SURFACES the domain's two activation guards (the Creator→Reviewer→Approver SoD floor in `ApprovalGovernance`; the Producer gate in `ProducerActivationGate`) and re-checks neither: a self-approval or gate block (both `RuntimeException`, caught by base type) renders a danger notification, Master unchanged. Suite 972/972; phpstan 0; PG17 52/52.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **972/972 green** (4856 assertions, +5 vs 4.1). phpstan 0; pint clean. `composer.json/lock` untouched; no migrations; no protected files. New: `activate` action on `ViewProductMaster` + `operator_console` keys (actions.activate, affordance.second_actor, notifications.activated; EN+IT) + a `lifecycleConsoleProjectProducer` test helper + 5 tests.
- **PG17 verified:** `tests/Feature/Modules/OperatorPanel` = 52/52 on docker `postgres:17` (producer-state projection, governance `actor_id` bigint-as-string reads, activation event + gate/governance rollback all clean on PG).
- ⚠ Full suite/phpstan: `php -d memory_limit=-1 vendor/bin/pest` (and `… phpstan analyse`) — bare `php artisan test` OOMs at 128M. PG run: `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=secret php -d memory_limit=512M vendor/bin/pest tests/Feature/Modules/OperatorPanel` (container `postgres:17`, started+torn down per run).

## Active Change & Next Task
- **Active: `operator-console-catalog-master`** (APPROVED, in progress — **7/11** done).
- **Next 5.1:** Retire + Reopen header Actions → `app(RetireProductMaster)->handle($record)` (`active → retired`, single-entity, preserves active children, records `ProductMasterRetired`) and `app(ReopenProductMaster)->handle($record)` (`retired → reviewed`, audit-only). REUSE `surfaceLifecycleOutcome`. Drive a Master to `active` (3 distinct operators + producer active — see 4.2's `lifecycleConsoleProjectProducer` + role_count-3 note), seed an active child Variant via the Catalog create+activate actions; assert `callAction('retire')` → `retired` + **1** `ProductMasterRetired` (newco_ops) + child still `active`; `callAction('reopen')` → `reviewed` + **0** events. **PG17 task.**

## Blockers & Decisions Needed
- None. `openspec validate operator-console-catalog-master --strict` green; on branch `ralph/operator-console-catalog-master`.

## Open Patterns
- **Filament 5 write-through lifecycle ACTION** (progress.md Codebase Patterns — read before 5.x): header-action `$record`/`$data` name-injection; `surfaceLifecycleOutcome` base-`\RuntimeException` catch → localized danger notification. **NEW (4.2):** a "distinct/second actor" or warning affordance is the action's CONFIRMATION copy — `->requiresConfirmation()->modalDescription(__('…'))`; assert without HTML via `assertActionExists('<verb>', fn (Action $a) => $a->isConfirmationRequired() && $a->getModalDescription() === (string) __('…'))`. `assertNotified` takes the TITLE STRING only (object form matches by random id); prove WHICH guard fired by isolating it in setup, not by asserting the body. Default `catalog.approval.role_count` is **3** — a console activation success path needs THREE distinct operators (creator≠reviewer≠approver), no `config()` override.
- **Pint docblock trap (lessons.md 2026-06-20):** in any `OperatorPanel\Filament` class, name cross-module **exceptions/lifecycle** types in PROSE, never `{@see FQCN}` — Pint's `fully_qualified_strict_types` adds a `use Catalog\Exceptions\…`/`Catalog\Lifecycle\…` that breaks the 1.3 carve-out. `{@see ActivateProductMaster}` (an Action) is fine. Console cross-module surface stays exactly {Models, Actions}.
