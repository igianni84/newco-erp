---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-06
---

# Hot Cache

## Last Updated
**2026-07-06 — RM-08 `parties-producer-approval-sod`, tasks 2.1 + 2.2 ✅ together (5/10): SoD floor wired into `ActivateProducer` + ALL activation call sites migrated to operators.** `ActivateProducer` ctor-injects `ProducerApprovalGovernance`; `guard('Producer', $id)` runs inside the txn AFTER the from-state assert and BEFORE the KYC gate (D6: from-state → operator-principal → distinct-actor → KYC → write) → any violation throws pre-write, status/event-log untouched. 2.1+2.2 landed in ONE iteration (inseparable under the green gate). Full suite **1969/1969**. Committed.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- Full suite **1969/1969** (10468 assn) · PHPStan max **0** · Pint clean · `openspec validate parties-producer-approval-sod --strict` valid. SQLite only (cross-engine `===` + the split chain assertions ride PG17 at close, task 6.1).
- ⚠ **Full/arch suite = `php -d memory_limit=-1 vendor/bin/pest`** — `php artisan test` (128 MB subprocess) OOMs on the full suite AND any single arch file. Per-`--filter`/per-file runs are fine under `artisan test`.

## Active Change & Next Task
- **ACTIVE = `parties-producer-approval-sod` (RM-08)**, APPROVED, building. Branch `ralph/parties-producer-approval-sod`. **5/10 done.**
- **NEXT = task 3.1:** `ViewProducer` console (`app/Modules/OperatorPanel/Filament/Resources/Parties/ProducerResource/Pages/ViewProducer.php`) — surface the **"second actor required"** affordance on the `activate` verb + map a thrown `SeparationOfDutiesViolation` to a notification (state unchanged), the "surface, not reimplement" contract Catalog uses. Keep the KYC-rejection notification path; add NO submit/reject/reopen. Bullets: tasks.md 3.1.
- **Pint `{@see}` trap is LIVE on 3.1** (`ViewProducer` docblock describes Catalog's mirror): name Catalog in **backticked prose**, never `{@see \…Catalog\…}` (Pint auto-adds a `use` → invariant-10 red; arch test absent from `--filter`). Re-run Pint + `grep -n Catalog` before the full suite. Rule: lessons.md 2026-07-06.
- Then 3.2 console tests, 4.1 DemoSeeder fixture, 5.1 ADR honesty, 6.1 close.

## Blockers & Decisions Needed
- None blocking. Scope (Giovanni 2026-07-06): Producer-only SoD, 2-step Creator→Approver.
- **Blast-radius lesson (2.1/2.2):** the floor broke SIX test files, only 1 named in tasks — all migrated (distinct-operator lineage OR factory-null-creator vacuous; SET-WIDE `every(System)`→split so ProducerActivated=NewcoOps). **Task 3.x: grep `callAction('activate')` sites, not just `ActivateProducer::class` callers** (`ProducerConsoleChainTest` was the sole first-run miss). lessons.md 2026-07-06 floor-blast-radius.
- **ADR honesty (5.1):** `decisions/2026-06-17-approval-separation-of-duties-role-gated` "already built in `parties-producer-lifecycle`" overclaim → in-place correction (RM-09-style, no supersede).
- **F2 go-live flag:** enforced Producer SoD → "1 prod operator = SoD unsatisfiable"; not a demo blocker (RM-07 seeds 3 ops).
- **Repo sync:** `origin/main` @ `067f459`; ralph branch ahead. Assistant `git push` classifier-gated → Giovanni pushes at close.

## Open Patterns
- **Two migration shapes for an actor floor:** real `Create*` lineage → activate under a DISTINCT operator; `factory()->create()` (null creator) → any single `actingAs`/`runAs(operator)` is vacuously distinct. `actingAs` can switch MID Livewire-chain (fresh component reads the guard at record time). Pest global helpers don't cross single-file runs → define per-file or inline `runAs`.
- **Parties i18n = per-key EN fallback (DEC-127):** per-locale file carries only its keys; every `it` key needs an `en` counterpart. Unit copy tests `uses(TestCase::class)`.
