---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — Building `catalog-review-freshness-resubmit` (RM-06), last Round-1 item. Task 2.2 done (review-freshness block-gate in the shared `ApprovalGovernance::guard()` + inverted the "not terminal" test); 4/10.** Round-1 compliance-remediation driven by Paolo Alfieri's 2026-07-01 mail. Verdict reports in **`docs/validation/`**; live backlog **`Remediation_Tracker.md`**. On origin/main: RM-07 `5b64cc8`, RM-04 `d8ec261`, RM-09+F3 `5eb415d`, RM-10 `04406b8`, RM-24 `4c373af`.

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **GREEN:** full suite **1787/1787** (+2 net from 2.2), PHPStan 0, Pint clean, `openspec validate --strict` valid.
- Run the full suite via `php -d memory_limit=-1 vendor/bin/pest` — bare `php artisan test` OOMs at 128M (lessons.md 2026-06-20).

## Active Change & Next Task
- **Change `catalog-review-freshness-resubmit` (RM-06) — APPROVED, 4/10 done.** Block-gate on `reviewed→active` + explicit `re-submit` (`reviewed→reviewed`), derive-from-audit (no schema). edit-re-arms→RM-14; canon MVP-DEC-019.
- **Done:** 1.1 ADR DEC-019; 1.2 factories; 2.1 `LifecycleTransition::resubmit()` + `ResubmitProductMasterForReview`; 2.2 `assertNotRejectionPending()` in `guard()` (runs BEFORE SoD; `is_string($latest) && str_ends_with($latest,'.rejected')`) + inverted "not terminal" → 3 block-gate tests.
- **NEXT: task 2.3** — Product Master 2-rejection-round scenario (spec — "Two rejection rounds…"; `AC-0-J-7`). ONE scenario test in `ProductMasterLifecycleTest`: reject → resubmit → reject → resubmit → activate. Assert activation BLOCKED after each reject (until the following resubmit), Master stays `reviewed` across both rounds, trail preserves `.rejected`=2 + `.resubmitted`=2 + `.activated`=1, final `active`, exactly one `ProductMasterActivated`, both rejection NOTES retrievable. **SoD across rounds:** final approver ≠ creator AND ≠ reviewer (= latest `.submitted` actor; `.resubmitted` does NOT match `%.submitted`, so `reviewerOf` stays the original submitter). Clean 3-operator shape: C creates; R submits + rejects both rounds; C resubmits both rounds; distinct A activates. Drive real `ActivateProductMaster` w/ `lifecycleProjectProducer('ProducerActivated',7,'active')`.
- **Spec anchors (verified):** delta `specs/product-catalog/spec.md` scenarios "A pending rejection blocks…" `:31` / "Two rejection rounds…" `:41`. Block msg key `catalog.lifecycle.activation_blocked_by_pending_rejection` (discriminator token `'un-remediated'`, absent from all SoD reasons).

## Blockers & Decisions Needed
- None for this change. ⚠️ **DEC-019 collision:** canon MVP-DEC-019 = review-freshness (this change); frozen spec's own DEC-019 = unrelated Module-S club composites — never conflate.
- Canon drift DEC-007→DEC-023 still open on Module K (RM-03, RM-05) — wait on Modules S/E/A.

## Open Patterns
- **Block-gate is live for all 7 spine entities NOW** (shared guard) but only breaks reject-then-activate paths — unique to PM's old "not terminal" test; other 6 spine + 7 console + DemoSeeder stayed green untouched. Task 3.x adds the six `Resubmit*`; the gate already enforces on them (3.2 proves the re-arm, not the gate).
- **`assertNotRejectionPending` mirrors `reviewerOf`** — latest catalog audit action read; `is_string` guard load-bearing (`->value()` null → `str_ends_with` TypeError under 8.5); latest-only, so `.resubmitted`/`.reopened` over a buried `.rejected` is NOT pending.
- **Derive-from-audit governance:** rejection-pending / creator / reviewer all read from `audit_records` (no per-entity columns; D5).
- **No exhaustive Catalog Action allow-list yet** — task 3.1 owns registering the seven `Resubmit*` (re-verify with a glob).
- **Incidental findings open (Tracker §7):** F1 DemoSeeder SQLite-only; F2 prod operator-mgmt missing → SoD unsatisfiable in prod.
