---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-20 (ralph — `operator-console-catalog-master` task 5.1 green, SQLite + PG17).** Added the console's RETIRE + REOPEN steps: two header Actions on the Product Master view page. `retire` → `app(RetireProductMaster)->handle($record)` (`active → retired`, single-entity — records **1** `ProductMasterRetired`, PRESERVES existing active children, no cascade); `reopen` → `app(ReopenProductMaster)->handle($record)` (`retired → reviewed`, **audit-only**, no event). Both route through the **reused 4.1 `surfaceLifecycleOutcome()` helper** — never `$record->save()`. Neither carries `->requiresConfirmation()` (that's the cascade's distinguishing affordance, 5.2). Key call: retire/reopen need NO distinct-actor SoD — `ApprovalGovernance::guard` enforces distinctness ONLY for `Activate`; retire/reopen carry just the operator-principal floor, so any operator may perform them. Suite 976/976; phpstan 0; PG17 56/56.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **976/976 green** (4900 assertions, +4 vs 4.2). phpstan 0; pint clean. `composer.json/lock` untouched; no migrations; no protected files. New: `retire`+`reopen` actions on `ViewProductMaster` + `operator_console` keys (actions.retire/reopen, notifications.retired/reopened; EN+IT) + a `lifecycleConsoleActiveMaster` test helper + 4 tests.
- **PG17 verified:** `tests/Feature/Modules/OperatorPanel` = 56/56 on docker `postgres:17` (retire event+audit, audit-only reopen, producer watermark flip `ProducerRetired`→`retired`, gate-block rollback all clean on PG).
- ⚠ Full suite/phpstan: `php -d memory_limit=-1 vendor/bin/pest` (and `… phpstan analyse`) — bare `php artisan test` OOMs at 128M. PG run: `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=secret php -d memory_limit=512M vendor/bin/pest tests/Feature/Modules/OperatorPanel` (container `postgres:17`, started+torn down per run).

## Active Change & Next Task
- **Active: `operator-console-catalog-master`** (APPROVED, in progress — **8/11** done).
- **Next 5.2:** Cascade-retire header Action → `app(RetireProductMasterCascade)->handle($record)` with `->requiresConfirmation()->modalDescription(__('…'))` warning it retires active descendants (4.2's confirmation-affordance pattern; assert via `assertActionExists('retireCascade', fn (Action $a) => $a->isConfirmationRequired() && …)`). Seed a small active hierarchy (Master → Variant → PR → Sellable/Composite SKU) via the Catalog create+activate actions or factories (`RetirementCascadeTest::retirementActiveTree` is the reference; reuse 5.1's `lifecycleConsoleActiveMaster`); `callAction('retireCascade')` → every entity `retired` + each `*Retired` event parent-before-child (ascending `domain_events.id`), all `actor_role: newco_ops`. Add `actions.retire_cascade` + warning + `notifications.cascade_retired` to `lang/{en,it}`. **PG17 task.**

## Blockers & Decisions Needed
- None. `openspec validate operator-console-catalog-master --strict` green; on branch `ralph/operator-console-catalog-master`.

## Open Patterns
- **Filament 5 write-through lifecycle ACTION** (progress.md Codebase Patterns — read before 5.2): header-action `$record`/`$data` name-injection; `surfaceLifecycleOutcome` base-`\RuntimeException` catch → localized danger notification; a "distinct/second actor" or cascade WARNING affordance is the action's CONFIRMATION copy (`->requiresConfirmation()->modalDescription(__('…'))`), asserted via `assertActionExists('<verb>', fn (Action $a) => $a->isConfirmationRequired() && $a->getModalDescription() === (string) __('…'))`. `assertNotified` takes the TITLE STRING only.
- **Re-checking a read-time gate in a test (NEW 5.1):** to prove a gate re-runs on a later transition, FLIP the projected state between attempts (`lifecycleConsoleProjectProducer('ProducerRetired', …)`) — the `ProducerLifecycleProjector` watermark applies a strictly-higher event id, so a later event flips `catalog_producer_states` and the gate blocks. Prove WHICH guard fired by isolating it in setup (producer active → only SoD; distinct actors → only the gate). Default `catalog.approval.role_count` is **3**: an activation success path needs THREE distinct operators.
- **Pint docblock trap (lessons.md 2026-06-20):** in any `OperatorPanel\Filament` class, name cross-module **exceptions/lifecycle** types in PROSE, never `{@see FQCN}` — Pint's `fully_qualified_strict_types` adds a `use Catalog\Exceptions\…`/`Catalog\Lifecycle\…` that breaks the 1.3 carve-out. `{@see <Action>}` (imported) is fine. Console cross-module surface stays exactly {Models, Actions}.
