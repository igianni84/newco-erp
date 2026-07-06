---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-06
---

# Hot Cache

## Last Updated
**2026-07-06 — RM-08 `parties-producer-approval-sod`, task 3.1 ✅ (6/10): the Producer console now SURFACES the SoD floor.** `ViewProducer`'s `activate` verb gained `confirmationKey: 'affordance.second_actor'` (the ONLY code change — Filament's `requiresConfirmation()`+`modalDescription()` via `SurfacesDomainActions`). The SoD→notification half was ALREADY wired: `SeparationOfDutiesViolation extends RuntimeException` → `surfaceLifecycleOutcome()` catches by base type → `action_failed`; `ActivateProducer`'s rollback (2.1) leaves state unchanged. Added `operator_console.producer.affordance.second_actor` copy (EN+IT), fixed the stale "no affordance" comments. Full suite **1972/1972**. Committed.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- Full suite **1972/1972** (10479 assn) · PHPStan max **0** · Pint clean · `openspec validate parties-producer-approval-sod --strict` valid. SQLite only (cross-engine `===` + split chain assertions ride PG17 at close, task 6.1).
- ⚠ **Full/arch suite = `php -d memory_limit=-1 vendor/bin/pest`** — `php artisan test` (128 MB subprocess) OOMs on the full suite AND any single arch file. Per-`--filter`/per-file runs fine under `artisan test`.

## Active Change & Next Task
- **ACTIVE = `parties-producer-approval-sod` (RM-08)**, APPROVED, building. Branch `ralph/parties-producer-approval-sod`. **6/10 done.**
- **NEXT = task 3.2:** console tests (`tests/Feature/Modules/OperatorPanel/Parties/ProducerLifecycleConsoleTest.php`). Its 2nd bullet (creator self-approval → notification + draft + 0 event) and 3rd (affordance exposed) are ALREADY covered by 3.1's two tests. **Genuine remaining work:** the **distinct-operator happy path** — real `CreateProducer` lineage as op A → activate via console as DISTINCT op B → `active` + `ProducerActivated` w/ approver actor_id — plus migrate the existing factory-based happy path (lines 38–67, null-creator vacuous) to genuine distinct-operator lineage. Bullets: tasks.md 3.2.
- Then 4.1 DemoSeeder fixture, 5.1 ADR honesty, 6.1 close.

## Blockers & Decisions Needed
- None blocking. Scope (Giovanni 2026-07-06): Producer-only SoD, 2-step Creator→Approver.
- **3.2 pattern:** `actingAs($opA)` for create (via real `CreateProducer` → records `ProducerCreated` actor_id=opA), switch to `actingAs($opB)` before the activate `Livewire::test` (fresh component reads the guard at record time — the 2.2 `ProducerConsoleChainTest` pattern). `callAction('activate')` auto-confirms the affordance, so it does NOT break `callAction` tests.
- **Isolated-run false failure:** `ProducerConsoleI18nTest` alone fails its LAST test (`scanOperatorConsoleHardcodedSinks` is declared in `ProductMasterConsoleI18nTest`, loaded suite-wide only). Run both i18n files together or the full suite. Not a regression.
- **ADR honesty (5.1):** `decisions/2026-06-17-approval-separation-of-duties-role-gated` "already built in `parties-producer-lifecycle`" overclaim → in-place correction (RM-09-style, no supersede).
- **Repo sync:** `origin/main` @ `067f459`; ralph branch ahead. Assistant `git push` classifier-gated → Giovanni pushes at close.

## Open Patterns
- **Affordance blast radius is TINY, unlike the domain floor (2.1/2.2):** adding a console `confirmationKey` only breaks tests that ASSERT its absence (`!isConfirmationRequired()`) — grep those, not `callAction` sites. `callAction` auto-confirms. Sibling FSMs (ProducerAgreement/Club/Customer) untouched (Producer-only scope).
- **i18n guard extends via the kit-key list:** adding a resolved copy key to `producerConsoleKitKeys()` flows into EN-baseline + IT-distinct + IT⊆EN datasets automatically (mirror `ProductMasterConsoleI18nTest:183`). Every new console copy key belongs there.
- **Two migration shapes for an actor floor:** real `Create*` lineage → activate under a DISTINCT operator; `factory()->create()` (null creator) → any single `actingAs`/`runAs` is vacuously distinct.
- **Parties i18n = per-key EN fallback (DEC-127):** per-locale file carries only its keys; every `it` key needs an `en` counterpart.
