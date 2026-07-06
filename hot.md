---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-06
---

# Hot Cache

## Last Updated
**2026-07-06 — RM-08 `parties-producer-approval-sod` building, task 1.3 ✅ (3/10): `ProducerApprovalGovernance` guard landed.** New `app/Modules/Parties/Governance/ProducerApprovalGovernance.php` — ctor-injects the `ActorContext` singleton; `guard('Producer', $id)` = operator-principal floor (reject non-`NewcoOps`/null → `requiresOperatorPrincipal`) then distinct-actor (`approver === creator` → `creatorMayNotApprove`; creator = earliest `DomainEvent` row via a private cross-engine `normalizeActorId()`, null = vacuous). Mirror of Catalog's guard MINUS the reviewer leg; **no `Catalog\*` import** (invariant 10 — Catalog in prose). Feature test 4/4, no behavior change yet (task 2.1 wires it into `ActivateProducer`). Committed.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- Full suite **1965/1965** (10450 assn) · PHPStan max **0** · Pint clean · `openspec validate parties-producer-approval-sod --strict` green. SQLite only (no schema delta yet; `normalizeActorId` cross-engine `===` runs on PG17 at close, task 6.1).
- ⚠ **Full/arch suite = `php -d memory_limit=-1 vendor/bin/pest`** — `php artisan test` (128 MB subprocess) OOMs on the full suite AND on a single arch file (pest-arch loads the whole class graph). Per-`--filter` runs are fine under `artisan test`.

## Active Change & Next Task
- **ACTIVE = `parties-producer-approval-sod` (RM-08)**, APPROVED, building. Branch `ralph/parties-producer-approval-sod`. **3/10 done.**
- **NEXT = task 2.1:** inject `ProducerApprovalGovernance` into `ActivateProducer`; call `guard('Producer', $producer->id)` inside the existing `DB::transaction`, AFTER the locked from-state assert, BEFORE the KYC gate + `update` (design D6: from-state → operator-principal → distinct-actor → KYC → write). Extend `ProducerLifecycleTest` (TDD) under distinct operators: self-approval + `system` → `SeparationOfDutiesViolation` (stays `draft`, 0 events); distinct op + KYC cleared → `active` + one `ProducerActivated`, `actor_id`=approver; self-approval on KYC-`pending` rejected on the **SoD** floor (before KYC). Full bullets: tasks.md 2.1.
- **Landmine (2.1 expected reds → fixed by 2.2):** existing `ProducerLifecycleTest` activations call `handle()` under the default `System` actor → the new floor breaks them. Write 2.1 under operators; **leave the old-test migration to 2.2** (note reds, don't fix ahead). Same for DemoSeeder (4.1).
- **Pint `{@see}` trap (2.x/3.x):** name Catalog in **backticked prose** in `ActivateProducer`/`ViewProducer` docblocks, never `{@see \…Catalog\…}` (Pint auto-adds a `use` → invariant-10 red; arch test absent from `--filter`). Re-run Pint + `grep -n Catalog` before the full suite. Rule: lessons.md 2026-07-06.
- Then 3.x console, 4.1 DemoSeeder fixture, 5.1 ADR honesty, 6.1 close.

## Blockers & Decisions Needed
- None blocking. Scope resolved (Giovanni 2026-07-06): membership SoD deferred (Producer-only); 2-step Creator→Approver depth.
- **ADR honesty (5.1):** `decisions/2026-06-17-approval-separation-of-duties-role-gated` "already built in `parties-producer-lifecycle`" overclaim → in-place correction (RM-09-style, no supersede).
- **F2 go-live flag:** enforced Producer SoD → "1 prod operator = SoD unsatisfiable" (same as Catalog); not a demo blocker (RM-07 seeds 3 ops).
- **Repo sync:** `origin/main` @ `067f459`; ralph branch ahead. Assistant `git push` classifier-gated → Giovanni pushes at close.

## Open Patterns
- **Direct-guard test pattern:** seed creator lineage via real `CreateProducer` inside `ActorContext::runAs(NewcoOps, $creatorId, …)` (restores context → system case = `(System,null)`, no logout); approve in a 2nd `runAs`; `factory()->create()` → null creator. Detail: progress.md Codebase Patterns.
- **Parties i18n = per-key EN fallback (DEC-127):** per-locale file carries only its keys; every `it` key needs an `en` counterpart. Unit copy tests `uses(TestCase::class)`. Don't chain `->toBeString()->not->…` off `trans()` (union breaks PHPStan-max).
