---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-06
---

# Hot Cache

## Last Updated
**2026-07-06 — RM-08 `parties-producer-approval-sod` BUILD STARTED. Task 1.1 ✅ (1/10): Parties SoD approval copy landed — `approval` group (`requires_operator_principal`, `creator_may_not_approve`) added to `lang/en/parties.php` + new `lang/it/parties.php` (authored IT, per-key EN fallback). New `PartiesApprovalCopyTest` (5/5). No behavior change yet — copy only.** Wording mirrors `lang/en/catalog.php` `approval` group minus the reviewer leg (Producer FSM is linear). Committed on `ralph/parties-producer-approval-sod`.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- Full suite **1956/1956** (10432 assn, was 1951 + 5 new) · PHPStan max **0** · Pint clean · `openspec validate parties-producer-approval-sod --strict` green. SQLite only (task 1.1 has no schema/PG-relevant delta; PG17 run is the close ritual's job).
- ⚠ **Run the full suite via `php -d memory_limit=-1 vendor/bin/pest`** — `php artisan test` (laravel/pao) caps its subprocess at 128 MB and OOMs on the full suite (fatal shows in `DemoSeeder.php`, misleading). Per-`--filter` runs are fine under `artisan test`.

## Active Change & Next Task
- **ACTIVE = `parties-producer-approval-sod` (RM-08)**, APPROVED, building. Branch `ralph/parties-producer-approval-sod`. **1/10 tasks done.**
- **NEXT = task 1.2:** add `app/Modules/Parties/Exceptions/SeparationOfDutiesViolation.php` (a `RuntimeException`) with static factories `requiresOperatorPrincipal(string $entity): self` + `creatorMayNotApprove(string $entity): self`, each resolving `parties.approval.*` via `(string) __(...)` — mirror `Catalog\Exceptions\ApprovalGovernanceViolation::build()`. Then 1.3 guard (`ProducerApprovalGovernance`), 2.x wire into `ActivateProducer`, 3.x console, 4.1 DemoSeeder fixture, 5.1 ADR honesty, 6.1 verify+close.
- **Landmine (tasks 2.2/3.2/4.1):** existing Producer-activation tests + DemoSeeder activate under the default `System` actor → migrate to distinct operator principals via `ActorContext::runAs` (RM-07 pattern), else the new floor breaks them.
- **Scope:** distinct-actor SoD on `ActivateProducer` — activator ≠ Producer creator (earliest `domain_events` row) + reject `system`/null (must be `newco_ops`); KYC gate unchanged (both hold). Parties-local guard, no Catalog import. No ADR, no migration, no new event.

## Blockers & Decisions Needed
- None blocking. Scope resolved (Giovanni 2026-07-06): membership SoD deferred (Producer-only); 2-step Creator→Approver depth.
- **ADR honesty (task 5.1):** `decisions/2026-06-17-approval-separation-of-duties-role-gated` "already built in `parties-producer-lifecycle`" overclaim → in-place correction (RM-09-style, no supersede).
- **F2 go-live flag:** enforcing Producer SoD extends the "prod has 1 operator → SoD unsatisfiable" exposure (same as Catalog); not a demo blocker (RM-07 seeds 3 ops).
- **Repo sync:** `origin/main` @ `067f459`; the ralph branch is ahead. Assistant `git push` is classifier-gated → Giovanni pushes at close. Pre-existing tracker/hot/log bookkeeping from the authoring session rode into the task-1.1 commit.

## Open Patterns
- **Parties i18n = per-key EN fallback (DEC-127):** a per-locale file carries only the keys it translates; every `it` key MUST have an `en` counterpart (assert `array_diff(Arr::dot(it), Arr::dot(en)) === []`). Unit copy tests `uses(TestCase::class)` explicitly (Pest auto-binds only in Feature). Don't chain `->toBeString()->not->…` off `trans()` (union type breaks PHPStan-max) — see progress.md Codebase Patterns.
- **SoD on a linear FSM:** no `reviewed`/submit step → the faithful floor is 2-step creator≠approver, reusing `creatorOf` from `domain_events` + the operator-principal reject; mirror Catalog's guard minus `reviewerOf`/`roleCount`.
