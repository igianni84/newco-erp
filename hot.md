---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — Building `catalog-review-freshness-resubmit` (RM-06), last Round-1 item. Task 3.2 done (new `CatalogReviewFreshnessUniformityTest`: cross-entity reject→block→resubmit→activate over all seven spine entities, green first run); 7/10.** Round-1 compliance-remediation driven by Paolo Alfieri's 2026-07-01 mail. Verdict reports in **`docs/validation/`**; live backlog **`Remediation_Tracker.md`**. On origin/main: RM-07 `5b64cc8`, RM-04 `d8ec261`, RM-09+F3 `5eb415d`, RM-10 `04406b8`, RM-24 `4c373af`.

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **GREEN:** full suite **1796/1796** (+7 from 3.2), PHPStan 0, Pint clean, `openspec validate --strict` valid.
- Run the full suite via `php -d memory_limit=-1 vendor/bin/pest` — bare `php artisan test` OOMs at 128M (lessons.md 2026-06-20).

## Active Change & Next Task
- **Change `catalog-review-freshness-resubmit` (RM-06) — APPROVED, 7/10 done.** Block-gate on `reviewed→active` + explicit `re-submit` (`reviewed→reviewed`), derive-from-audit (no schema). edit-re-arms→RM-14; canon MVP-DEC-019.
- **Done:** 1.1 ADR; 1.2 factories; 2.1 `resubmit()`+Master action; 2.2 block-gate in `guard()`; 2.3 2-round scenario; 3.1 six other `Resubmit*` actions; 3.2 cross-entity uniformity test.
- **NEXT: task 4.1** — add a `re-submit` HEADER action to the Product Master catalog **View page** via the shared console kit's `lifecycleAction` factory, write-through to `ResubmitProductMasterForReview`; `->visible()` gated to "record is rejection-pending" (the derived read: latest governance action ends in `.rejected`). The block-gate rejection needs NO new console code — `ApprovalGovernanceViolation` already routes through the kit's `surfaceLifecycleOutcome` `action_failed` notification (design D5). **⚠️ Adding `resubmit` to a console's header actions WILL red the `OperatorPanel/Catalog/*` `toEqualCanonicalizing` console-action-NAME exact-match sets** (contrast 3.1 which touched no console) — register it there. Test per lessons.md 2026-06-23/24: `assertActionVisible/Hidden('resubmit')` (a `->visible()`-false Filament action is UNDRIVABLE via helpers), drive the re-arm via the Action directly; live-verify the button if render-suppression suspected. Then 4.2 = same kit-factory action on the other six catalog consoles; 5.1 = full-suite + PHPStan + Pint + PG17 close.

## Blockers & Decisions Needed
- None for this change. ⚠️ **DEC-019 collision:** canon MVP-DEC-019 = review-freshness (this change); frozen spec's own DEC-019 = unrelated Module-S club composites — never conflate.
- Canon drift DEC-007→DEC-023 still open on Module K (RM-03, RM-05) — wait on Modules S/E/A.

## Open Patterns
- **Type-clean generic cross-entity test (3.2):** `match ($label)` → per-entity builder returning `array{0: Model (BASE), 1-3: Closure}` (Actions bound concretely inside); body reads `$model->getAttribute('lifecycle_state')` (real base-Model method), NEVER `->lifecycle_state` (magic prop, `property.notFound` on base Model under Larastan). `match` on open `string` needs `default => throw` or PHPStan reds `match.unhandled`.
- **Factory-active PARENTS open every within-module cascade gate** (`ProductMaster/Variant/Format/PR/CaseConfig::factory()->create(['lifecycle_state'=>Active])`); only the Master needs the real Producer projection. So a cross-entity flow needs the full chain for NO entity — only the entity-under-test goes through real create+submit.
- **Airtight re-arm proof:** activate the SAME fixture twice (rejection-pending → block `un-remediated`; post-`Resubmit*` → active). The Action `lockAndRefresh`es, so re-invoking the SAME captured closure re-reads the cleared trail.
- **Incidental findings open (Tracker §7):** F1 DemoSeeder SQLite-only; F2 prod operator-mgmt missing → SoD unsatisfiable in prod.
