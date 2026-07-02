---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — Building `catalog-review-freshness-resubmit` (RM-06), last Round-1 item. Task 1.1 done (mini-ADR DEC-019); 1/10.** Compliance-remediation Round 1 driven by Paolo Alfieri's 2026-07-01 mail. Verdict reports in **`docs/validation/`**; live backlog **`Remediation_Tracker.md`**. **On origin/main:** RM-07 `5b64cc8`, RM-04 `d8ec261`, RM-09+F3 `5eb415d`, RM-10 `04406b8`, RM-24 `4c373af`.

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **GREEN:** full suite **1769/1769**, PHPStan/Pint clean (baseline). Task 1.1 was doc-only (no PHP) — Pint clean, `openspec validate --strict` valid.
- Run the full suite via `php -d memory_limit=-1 vendor/bin/pest` — bare `php artisan test` OOMs at 128M (lessons.md 2026-06-20).

## Active Change & Next Task
- **Change `catalog-review-freshness-resubmit` (RM-06) — APPROVED, 1/10 tasks done.** Block-gate on `reviewed→active` + explicit `re-submit` (`reviewed→reviewed`, twin of `reject`) across all 7 spine entities, **derive-from-audit** (no schema). edit-re-arms→RM-14; canon MVP-DEC-019. Closes Round 1.
- **Done:** 1.1 mini-ADR `decisions/2026-07-02-adopt-dec-019-review-freshness-resubmit.md` + INDEX row.
- **NEXT: task 1.2** — exception factories `IllegalLifecycleTransition::cannotResubmit()` + `ApprovalGovernanceViolation::activationBlockedByPendingRejection()` + `catalog.lifecycle.*` localized keys in `lang/en/catalog.php`. Unit tests, no `RefreshDatabase`. **From 1.2 on PHP is touched → full quality loop applies** (`php -d memory_limit=-1 vendor/bin/{pest,phpstan analyse}` + Pint).
- **Spec anchors (verified):** truth spec `openspec/specs/product-catalog/spec.md` *Approval Governance* `:214`/`:220`/`:240`; frozen `AC-0-J-7`, `BR-Lifecycle-6` §4.3. Code seam: `LifecycleTransition.php` (`reject()` `:189-214`, new `resubmit()`), `ApprovalGovernance.php` (`guard()`). **INVERT** `ProductMasterLifecycleTest.php:363-387` ("rejection not terminal").
- After RM-06: Round 2 = floor builds (RM-01 erasure / RM-02 enhanced-KYC).

## Blockers & Decisions Needed
- None for this change. ⚠️ **DEC-019 collision:** canon MVP-DEC-019 = review-freshness (this change); frozen spec's own DEC-019 = unrelated Module-S club single-producer composites — never conflate.
- Canon drift DEC-007→DEC-023 still open on Module K (RM-03 membership flow, RM-05 capacity) — wait on Modules S/E/A.
- **Rule firm (lessons.md 2026-07-02):** adopting a canon-DEC absent from frozen `spec/` → ALWAYS a mini-ADR (confirmed 3×; now 4× with DEC-019). Tracker "ADR?" column advisory.

## Open Patterns
- **Escalation-asymmetry** (memory `spec-divergence-from-cmless-documentation`) confirmed live: frozen handoff stops at DEC-007; canon DEC-008..023 never crossed. This validation IS the corrections-inbox — deltas → local ADRs, genuine gaps → c-mless issues.
- **Derive-from-audit governance:** rejection-pending / creator / reviewer all read from `audit_records` (`latestGovernanceAction` shape — `orderByDesc('id')->value('action')`), no per-entity governance columns (`catalog-lifecycle-approval` D5). RM-06 reaffirms it for the block-gate.
- **Divergent module maturity:** Catalog enforces SoD + Product-Type immutability; Parties does NOT enforce SoD (RM-08).
- **Incidental findings open (Tracker §7):** F1 DemoSeeder SQLite-only; F2 prod operator-mgmt missing → SoD unsatisfiable in prod. Log any incidental discovery in §7.
