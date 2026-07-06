---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-06
---

# Hot Cache

## Last Updated
**2026-07-06 — RM-08 `parties-producer-approval-sod` building, task 1.2 ✅ (2/10): `SeparationOfDutiesViolation` exception landed.** New `app/Modules/Parties/Exceptions/SeparationOfDutiesViolation.php` — a `RuntimeException` with static factories `requiresOperatorPrincipal(string $entity)` + `creatorMayNotApprove(string $entity)`, each resolving `parties.approval.*` via a private `build()` = `(string) __(...)`. Exact shape-mirror of `Catalog\Exceptions\ApprovalGovernanceViolation` minus the reviewer leg (linear Producer FSM). No `Catalog\*` import (invariant 10) — prose docblock only. New Unit test 5/5. No behavior change yet — the guard (1.3) wires it in. Committed on `ralph/parties-producer-approval-sod`.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- Full suite **1961/1961** (10443 assn, was 1956 + 5 new) · PHPStan max **0** · Pint clean · `openspec validate parties-producer-approval-sod --strict` green. SQLite only (tasks 1.1–1.2 have no schema/PG-relevant delta; PG17 run is the close ritual's job, task 6.1).
- ⚠ **Run the full suite via `php -d memory_limit=-1 vendor/bin/pest`** — `php artisan test` (laravel/pao) caps its subprocess at 128 MB and OOMs on the full suite (fatal shows in `DemoSeeder.php`, misleading). Per-`--filter` runs are fine under `artisan test`.

## Active Change & Next Task
- **ACTIVE = `parties-producer-approval-sod` (RM-08)**, APPROVED, building. Branch `ralph/parties-producer-approval-sod`. **2/10 tasks done.**
- **NEXT = task 1.3:** the guard `app/Modules/Parties/Governance/ProducerApprovalGovernance.php` (constructor injects `App\Platform\Events\ActorContext`), method `guard(string $entityType, int|string $entityId): void`. Mirror `Catalog\Lifecycle\ApprovalGovernance` minus the reviewer leg: `operatorPrincipalOrFail()` (reject `role() !== ActorRole::NewcoOps` ∨ `actorId() === null` → `requiresOperatorPrincipal`, else return approver id); `creatorOf()` = earliest `DomainEvent` row for `(entity_type, entity_id)` by `id` via `orderBy('id')->value('actor_id')`, through a PRIVATE `normalizeActorId()` copy (PG numeric-string vs SQLite int → `===` holds cross-engine); if `creator !== null && approver === creator` → `creatorMayNotApprove`. TDD Feature test first (`tests/Feature/Modules/Parties/ProducerApprovalGovernanceTest.php`). **Before writing:** read `Catalog\Lifecycle\ApprovalGovernance` + `App\Platform\Events\ActorContext`/`ActorRole`; verify the `domain_events` columns (`actor_id`, `entity_type`, `entity_id`) against a real row.
- Then 2.x wire into `ActivateProducer` (order: from-state → operator-principal → distinct-actor → KYC → write), 3.x console verb, 4.1 DemoSeeder fixture, 5.1 ADR honesty, 6.1 verify+close.
- **Landmine (tasks 2.2/3.2/4.1):** existing Producer-activation tests + DemoSeeder activate under the default `System` actor → migrate to distinct operator principals via `ActorContext::runAs` (RM-07 pattern), else the new floor breaks them.
- **Scope:** distinct-actor SoD on `ActivateProducer` — activator ≠ Producer creator + reject `system`/null (must be `newco_ops`); KYC gate unchanged. Parties-local, no Catalog import. No ADR, no migration, no new event.

## Blockers & Decisions Needed
- None blocking. Scope resolved (Giovanni 2026-07-06): membership SoD deferred (Producer-only); 2-step Creator→Approver depth.
- **ADR honesty (task 5.1):** `decisions/2026-06-17-approval-separation-of-duties-role-gated` "already built in `parties-producer-lifecycle`" overclaim → in-place correction (RM-09-style, no supersede).
- **F2 go-live flag:** enforcing Producer SoD extends the "prod has 1 operator → SoD unsatisfiable" exposure (same as Catalog); not a demo blocker (RM-07 seeds 3 ops).
- **Repo sync:** `origin/main` @ `067f459`; the ralph branch is ahead. Assistant `git push` is classifier-gated → Giovanni pushes at close.

## Open Patterns
- **SoD on a linear FSM:** no `reviewed`/submit step → the faithful floor is 2-step creator≠approver, reusing `creatorOf` from `domain_events` + the operator-principal reject; mirror Catalog's guard minus `reviewerOf`/`roleCount`. The exception (1.2) is the Parties-local twin of `ApprovalGovernanceViolation` with only 2 factories.
- **Parties i18n = per-key EN fallback (DEC-127):** a per-locale file carries only the keys it translates; every `it` key MUST have an `en` counterpart (assert `array_diff(Arr::dot(it), Arr::dot(en)) === []`). Unit copy tests `uses(TestCase::class)` explicitly (Pest auto-binds only in Feature). Don't chain `->toBeString()->not->…` off `trans()` (union type breaks PHPStan-max).
