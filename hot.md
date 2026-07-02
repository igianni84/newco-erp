---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — Building `catalog-review-freshness-resubmit` (RM-06), last Round-1 item. Task 1.2 done (exception factories + lang keys); 2/10.** Compliance-remediation Round 1 driven by Paolo Alfieri's 2026-07-01 mail. Verdict reports in **`docs/validation/`**; live backlog **`Remediation_Tracker.md`**. **On origin/main:** RM-07 `5b64cc8`, RM-04 `d8ec261`, RM-09+F3 `5eb415d`, RM-10 `04406b8`, RM-24 `4c373af`.

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **GREEN:** full suite **1782/1782** (+13 from 1.2's new tests), PHPStan 0, Pint clean, `openspec validate --strict` valid.
- Run the full suite via `php -d memory_limit=-1 vendor/bin/pest` — bare `php artisan test` OOMs at 128M (lessons.md 2026-06-20).

## Active Change & Next Task
- **Change `catalog-review-freshness-resubmit` (RM-06) — APPROVED, 2/10 tasks done.** Block-gate on `reviewed→active` + explicit `re-submit` (`reviewed→reviewed`, twin of `reject`) across all 7 spine entities, **derive-from-audit** (no schema). edit-re-arms→RM-14; canon MVP-DEC-019. Closes Round 1.
- **Done:** 1.1 mini-ADR DEC-019; 1.2 factories `IllegalLifecycleTransition::cannotResubmit()` (via `build()` → `catalog.lifecycle.cannot_resubmit`) + `ApprovalGovernanceViolation::activationBlockedByPendingRejection()` (bypasses `build()`, inlines `catalog.lifecycle.activation_blocked_by_pending_rejection` — copy in `lifecycle` group, class stays `ApprovalGovernanceViolation` for console surfacing; confirmed `extends RuntimeException`). + new `ApprovalGovernanceViolationTest`.
- **NEXT: task 2.1** — `LifecycleTransition::resubmit(Model&HasLifecycleState, string $entity)` mirroring `reject()` (at `LifecycleTransition.php:189-214`): one `DB::transaction`, `lockAndRefresh`, from-state assert `reviewed` (else `cannotResubmit`), `governance->requireOperator($entity)`, one `audit_records` row `catalog.<entity>.resubmitted` (before/after `{lifecycle_state}` + `decision: resubmitted`), **NO** domain event. + thin `App\Modules\Catalog\Actions\ResubmitProductMasterForReview`. Extend `ProductMasterLifecycleTest.php` (submit→reject→resubmit; no-operator → `ApprovalGovernanceViolation`; non-reviewed → `IllegalLifecycleTransition`). Full quality loop.
- **Spec anchors (verified):** truth `openspec/specs/product-catalog/spec.md` *Approval Governance* `:214`/`:220`/`:240`; frozen `AC-0-J-7`, `BR-Lifecycle-6` §4.3. **INVERT** `ProductMasterLifecycleTest.php:363-387` ("rejection not terminal") in task 2.2.
- After RM-06: Round 2 = floor builds (RM-01 erasure / RM-02 enhanced-KYC).

## Blockers & Decisions Needed
- None for this change. ⚠️ **DEC-019 collision:** canon MVP-DEC-019 = review-freshness (this change); frozen spec's own DEC-019 = unrelated Module-S club single-producer composites — never conflate.
- Canon drift DEC-007→DEC-023 still open on Module K (RM-03 membership flow, RM-05 capacity) — wait on Modules S/E/A.

## Open Patterns
- **Exception CLASS ⊥ lang GROUP:** block-gate thrown as `ApprovalGovernanceViolation` (console-surfacing) but copy in `lifecycle` group (semantic home) — factory inlines `__()`, skips `build()`. Reused by 2.2's guard wiring.
- **Derive-from-audit governance:** rejection-pending / creator / reviewer read from `audit_records` (`latestGovernanceAction` — `orderByDesc('id')->value('action')`), no per-entity governance columns (`catalog-lifecycle-approval` D5). RM-06 reaffirms it for the block-gate.
- **New lesson (2026-07-02):** never `use RuntimeException;`/global class in a Pest test (global ns → "has no effect" warning); bare `RuntimeException::class`.
- **Incidental findings open (Tracker §7):** F1 DemoSeeder SQLite-only; F2 prod operator-mgmt missing → SoD unsatisfiable in prod.
