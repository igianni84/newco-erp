---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-20 (ralph — `operator-console-catalog-master` task 5.2 green, SQLite + PG17).** Added the console's CASCADE-retire step: a distinct `retireCascade` header Action on the Product Master view page. `retireCascade` → `app(RetireProductMasterCascade)->handle($record)` (`active → retired` for the Master AND its active descendants — Variants → PRs → SKUs — parent-before-child in ONE atomic transaction, each recording its `*Retired`). Routes through the **reused 4.1 `surfaceLifecycleOutcome()` helper** — never `$record->save()`. It carries `->requiresConfirmation()->modalDescription(__('…cascade_warning'))` — the cascade's distinguishing affordance (4.2's confirmation shape): the operator is WARNED before committing that descendants are retired too. Out-of-state cascade (draft Master) → danger notification. Cascade needs NO distinct-actor SoD (`ApprovalGovernance::guard` enforces distinctness ONLY for Activate) — any one operator may trigger it. Suite 980/980; phpstan 0; PG17 60/60.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **980/980 green** (4937 assertions, +4 vs 5.1). phpstan 0; pint clean. `composer.json/lock` untouched; no migrations; no protected files. Touched 4 files: `retireCascade` action on `ViewProductMaster` + `operator_console` keys (actions.retire_cascade, affordance.cascade_warning, notifications.cascade_retired; EN+IT) + a `lifecycleConsoleActiveTree` test helper (full active spine via real actions) + 4 tests.
- **PG17 verified:** `tests/Feature/Modules/OperatorPanel` = 60/60 on docker `postgres:17` (multi-entity retire in one txn, the 4 `*Retired` events' parent-before-child id order, actor envelope, out-of-state rollback all clean on PG).
- ⚠ Full suite/phpstan: `php -d memory_limit=-1 vendor/bin/pest` (and `… phpstan analyse`) — bare `php artisan test` OOMs at 128M. PG run: `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=secret php -d memory_limit=512M vendor/bin/pest tests/Feature/Modules/OperatorPanel` (container `postgres:17`, started+torn down per run).

## Active Change & Next Task
- **Active: `operator-console-catalog-master`** (APPROVED, in progress — **9/11** done).
- **Next 6.1:** i18n EN+IT for all console copy. `lang/{en,it}/operator_console.php` already exist (seeded 2.1, extended 3.1/4.1/4.2/5.1/5.2); all of 2–5's strings already route through `__('operator_console.…')` and reuse `__('catalog.…')` for rejection bodies. Acceptance wants: (a) a token-scan test proving **NO hardcoded user-facing literal** in any `App\Modules\OperatorPanel\Filament` class (allow `__()`/trans keys only); (b) a per-key EN-fallback test — a deliberately IT-missing key renders its EN value; (c) `app()->setLocale('it')` renders IT labels. Test file: `tests/Feature/Modules/OperatorPanel/Catalog/ProductMasterConsoleI18nTest.php`. **Not a PG17 task** (pure locale/scan).

## Blockers & Decisions Needed
- None. `openspec validate operator-console-catalog-master --strict` green; on branch `ralph/operator-console-catalog-master`.

## Open Patterns
- **Filament 5 write-through lifecycle ACTION** (progress.md Codebase Patterns — read before 6.x): header-action `$record`/`$data` name-injection; `surfaceLifecycleOutcome` base-`\RuntimeException` catch → localized danger notification; a "distinct/second actor" or cascade WARNING affordance is the action's CONFIRMATION copy (`->requiresConfirmation()->modalDescription(__('…'))`), asserted via `assertActionExists('<verb>', fn (Action $a) => $a->isConfirmationRequired() && $a->getModalDescription() === (string) __('…'))`. `assertNotified` takes the TITLE STRING only.
- **Seeding a deep active spine via real actions (NEW 5.2):** `lifecycleConsoleActiveTree($c,$r,$a,$producerId=7)` extends `lifecycleConsoleActiveMaster` into Master→Variant→Format→PR→CaseConfig→SellableSku, all create+submit+activate. The SoD floor is **per-entity** (each entity's own `*Created`+submit), so ONE creator→reviewer→approver lineage (3 mutually-distinct ops) satisfies every level — no fresh ops per level. Standalone Format + CaseConfiguration must be active to open the PR/SKU activation gates; the cascade does NOT descend into them (§ 4.7).
- **Pint docblock trap (lessons.md 2026-06-20):** in any `OperatorPanel\Filament` class, name cross-module **exceptions/lifecycle** types in PROSE, never `{@see FQCN}` — Pint's `fully_qualified_strict_types` adds a `use Catalog\Exceptions\…`/`Catalog\Lifecycle\…` that breaks the 1.3 carve-out. `{@see <Action>}` (imported) is fine. Console cross-module surface stays exactly {Models, Actions}.
