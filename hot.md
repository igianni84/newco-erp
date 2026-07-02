---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — Building `catalog-review-freshness-resubmit` (RM-06), last Round-1 item. Task 3.1 done (six thin `Resubmit{Entity}ForReview` actions + `ResubmitActionsTest`, green first run); 6/10.** Round-1 compliance-remediation driven by Paolo Alfieri's 2026-07-01 mail. Verdict reports in **`docs/validation/`**; live backlog **`Remediation_Tracker.md`**. On origin/main: RM-07 `5b64cc8`, RM-04 `d8ec261`, RM-09+F3 `5eb415d`, RM-10 `04406b8`, RM-24 `4c373af`.

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **GREEN:** full suite **1789/1789** (+1 from 3.1), PHPStan 0, Pint clean, `openspec validate --strict` valid.
- Run the full suite via `php -d memory_limit=-1 vendor/bin/pest` — bare `php artisan test` OOMs at 128M (lessons.md 2026-06-20).

## Active Change & Next Task
- **Change `catalog-review-freshness-resubmit` (RM-06) — APPROVED, 6/10 done.** Block-gate on `reviewed→active` + explicit `re-submit` (`reviewed→reviewed`), derive-from-audit (no schema). edit-re-arms→RM-14; canon MVP-DEC-019.
- **Done:** 1.1 ADR DEC-019; 1.2 factories; 2.1 `resubmit()`+Master action; 2.2 block-gate in `guard()`; 2.3 2-round scenario; 3.1 six other `Resubmit*` actions.
- **NEXT: task 3.2** — ONE parametrized/loop test over the seven spine entities proving reject→block→resubmit→activate is UNIFORM (not Master-only). For each: build to `reviewed`, reject → assert `activate` throws the `'un-remediated'` block, resubmit → assert a distinct approver's `activate` succeeds. **The block-gate is ALREADY live for all seven** (shared `guard()`, task 2.2) — 3.2 proves the per-entity RE-ARM, NOT the gate. **Respect each entity's activation prerequisites:** Master=Producer gate, Variant/PR/SKU=parent-active cascade, Format/CaseConfig=standalone. Reuse `CatalogLifecycleChainTest::chainActiveSpineUnderRealProducer` + `ProductVariantLifecycleTest::lifecycleActiveParentMaster` fixtures. Scalar-dataset shape (lessons.md 2026-06-18 — no bare-`array` spread into a string-keyed literal).

## Blockers & Decisions Needed
- None for this change. ⚠️ **DEC-019 collision:** canon MVP-DEC-019 = review-freshness (this change); frozen spec's own DEC-019 = unrelated Module-S club composites — never conflate.
- Canon drift DEC-007→DEC-023 still open on Module K (RM-03, RM-05) — wait on Modules S/E/A.

## Open Patterns
- **Audit `action` segment ⊥ `entity_type`:** action = `catalog.<Str::singular(table)>.<verb>` (table-derived, LABEL-independent); the `$entity` label lands in `entity_type`. To prove a thin action passed the right label, filter by the action segment, assert `entity_type` (genuine check, not circular). Copy-paste label bug surfaces here (reviewer/block-gate reads filter on `entity_type`).
- **Isolate a resubmit-wiring test:** `resubmit()` asserts only from-state `reviewed`, so factory-build `->create(['lifecycle_state' => LifecycleState::Reviewed])` (factories accept enum override + auto-provision parent FKs) + drive once; parent fixtures record no audit rows. Full reject→activate flow = 3.2.
- **No exhaustive Catalog Action allow-list exists** (re-confirmed 3.1: +6 Actions red 0 tests). Container auto-resolves via `LifecycleTransition` inject — registration-free. Task 4.x console `resubmit` DOES hit the `OperatorPanel/Catalog/*` `toEqualCanonicalizing` console-NAME sets.
- **SoD survives N rejection rounds:** `reviewerOf` = single original `.submitted` actor (`.resubmitted` ⊄ `%.submitted`); clean multi-round fixture = 3 operators.
- **Incidental findings open (Tracker §7):** F1 DemoSeeder SQLite-only; F2 prod operator-mgmt missing → SoD unsatisfiable in prod.
