---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — Building `catalog-review-freshness-resubmit` (RM-06), last Round-1 item. Task 2.1 done (shared `resubmit()` + Product-Master action); 3/10.** Compliance-remediation Round 1 driven by Paolo Alfieri's 2026-07-01 mail. Verdict reports in **`docs/validation/`**; live backlog **`Remediation_Tracker.md`**. **On origin/main:** RM-07 `5b64cc8`, RM-04 `d8ec261`, RM-09+F3 `5eb415d`, RM-10 `04406b8`, RM-24 `4c373af`.

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **GREEN:** full suite **1785/1785** (+3 from 2.1's new tests), PHPStan 0, Pint clean, `openspec validate --strict` valid.
- Run the full suite via `php -d memory_limit=-1 vendor/bin/pest` — bare `php artisan test` OOMs at 128M (lessons.md 2026-06-20).

## Active Change & Next Task
- **Change `catalog-review-freshness-resubmit` (RM-06) — APPROVED, 3/10 tasks done.** Block-gate on `reviewed→active` + explicit `re-submit` (`reviewed→reviewed`, twin of `reject`), **derive-from-audit** (no schema). edit-re-arms→RM-14; canon MVP-DEC-019.
- **Done:** 1.1 mini-ADR DEC-019; 1.2 factories; 2.1 `LifecycleTransition::resubmit()` (twin of `reject()`) + thin `ResubmitProductMasterForReview` + 3 tests in `ProductMasterLifecycleTest`.
- **NEXT: task 2.2** — block-gate in `ApprovalGovernance::guard()`. In the existing `if ($type === LifecycleTransitionType::Activate)` branch (`ApprovalGovernance.php:69-71`, beside `assertSeparationOfDuties`) call a new `assertNotRejectionPending($entity, $entityId)`: read `AuditRecord` by `module=catalog`/`entity_type`/`entity_id`, `orderByDesc('id')`, `->value('action')`; throw `ApprovalGovernanceViolation::activationBlockedByPendingRejection($entity)` (factory exists, 1.2) **iff `str_ends_with($action, '.rejected')`** (NOT `.resubmitted`/`.reopened`). Then **INVERT** the shipped test — grep title `'lets the approval flow complete after a rejection (rejection is not terminal)'` (offset shifted ~+70 lines by 2.1's insert; do NOT trust old `:363-387`) → reject blocks activate (stays `reviewed`, no event), resubmit re-arms, distinct approver activates (rejection row preserved). Add no-false-block (never-rejected→active) + reopen (`retired→reviewed`, latest `.reopened`→active) cases. Full quality loop.
- **Spec anchors (verified):** truth `openspec/specs/product-catalog/spec.md` *Approval Governance* `:214`/`:220`/`:240`; frozen `AC-0-J-7`, `BR-Lifecycle-6` §4.3.

## Blockers & Decisions Needed
- None for this change. ⚠️ **DEC-019 collision:** canon MVP-DEC-019 = review-freshness (this change); frozen spec's own DEC-019 = unrelated Module-S club single-producer composites — never conflate.
- Canon drift DEC-007→DEC-023 still open on Module K (RM-03, RM-05) — wait on Modules S/E/A.

## Open Patterns
- **`assertNotRejectionPending` mirrors `reviewerOf`/`creatorOf`** (`ApprovalGovernance.php`) but reads the LATEST action across ALL governance verbs (no `.submitted` filter) — suffix `.rejected` only.
- **No exhaustive Catalog Action allow-list yet** — all `glob()` tests are under `Parties/`; task 3.1 owns registering the seven `Resubmit*` (re-verify with a glob first).
- **Derive-from-audit governance:** rejection-pending / creator / reviewer read from `audit_records` (no per-entity governance columns; `catalog-lifecycle-approval` D5). RM-06 reaffirms it.
- **Incidental findings open (Tracker §7):** F1 DemoSeeder SQLite-only; F2 prod operator-mgmt missing → SoD unsatisfiable in prod.
