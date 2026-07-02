---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — Building `catalog-review-freshness-resubmit` (RM-06), last Round-1 item. Task 2.3 done (Product Master 2-rejection-round scenario, `AC-0-J-7` — test-only, composed proof, passed on first run); 5/10.** Round-1 compliance-remediation driven by Paolo Alfieri's 2026-07-01 mail. Verdict reports in **`docs/validation/`**; live backlog **`Remediation_Tracker.md`**. On origin/main: RM-07 `5b64cc8`, RM-04 `d8ec261`, RM-09+F3 `5eb415d`, RM-10 `04406b8`, RM-24 `4c373af`.

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **GREEN:** full suite **1788/1788** (+1 from 2.3), PHPStan 0, Pint clean, `openspec validate --strict` valid.
- Run the full suite via `php -d memory_limit=-1 vendor/bin/pest` — bare `php artisan test` OOMs at 128M (lessons.md 2026-06-20).

## Active Change & Next Task
- **Change `catalog-review-freshness-resubmit` (RM-06) — APPROVED, 5/10 done.** Block-gate on `reviewed→active` + explicit `re-submit` (`reviewed→reviewed`), derive-from-audit (no schema). edit-re-arms→RM-14; canon MVP-DEC-019.
- **Done:** 1.1 ADR DEC-019; 1.2 factories; 2.1 `resubmit()` + `ResubmitProductMasterForReview`; 2.2 block-gate in `guard()`; 2.3 2-round scenario.
- **NEXT: task 3.1** — six thin `Resubmit{Entity}ForReview` actions: Product Variant, Product Reference, Format, Case Configuration, Sellable SKU, Composite SKU. Each a thin wrapper over `LifecycleTransition::resubmit($e, 'Label')`, modeled on `ResubmitProductMasterForReview` (`handle(Entity): Entity`). **Get each canonical label from the entity's existing `Reject*Review` action / `*Activated::ENTITY_TYPE` — do NOT invent.** Then register all seven `Resubmit*` in any exhaustive non-`Create*` Action allow-list a test asserts. **Re-verify FIRST with a glob:** the 2.1 progress note confirmed NO exhaustive Catalog Action allow-list exists yet (all `glob()` tests are under `tests/Feature/Modules/Parties/`; `CatalogLifecycleChainTest` imports specific Actions but does NOT enumerate them as a set), so adding six actions likely reds nothing — but glob `app/Modules/Catalog/Actions/` + grep `toEqualCanonicalizing` in `tests/` before assuming (lessons.md 2026-06-23).

## Blockers & Decisions Needed
- None for this change. ⚠️ **DEC-019 collision:** canon MVP-DEC-019 = review-freshness (this change); frozen spec's own DEC-019 = unrelated Module-S club composites — never conflate.
- Canon drift DEC-007→DEC-023 still open on Module K (RM-03, RM-05) — wait on Modules S/E/A.

## Open Patterns
- **Block-gate is live for all 7 spine entities NOW** (shared `ApprovalGovernance::guard()`); task 3.2 proves the per-entity re-arm, not the gate.
- **SoD survives N rejection rounds:** `reviewerOf` stays the single original `.submitted` actor (`.resubmitted` ⊄ `%.submitted` — the char before `submitted` is `e`, not `.`); clean multi-round fixture = 3 operators (C creates+re-submits / R submits+rejects / distinct A activates). A blocked activate rolls back whole (throw in `guard()` pre-write) → no `.activated` row/event, latest action stays `.rejected`.
- **PHPStan max:** bare `$collection[$i]->prop` over a `->get()` reds `property.nonObject` (offset is `Model|null`); `pluck()/map()->all()` or `?? default` guards it (lessons.md 2026-07-02).
- **Derive-from-audit governance:** rejection-pending / creator / reviewer all read from `audit_records` (no per-entity columns; D3/D5).
- **Incidental findings open (Tracker §7):** F1 DemoSeeder SQLite-only; F2 prod operator-mgmt missing → SoD unsatisfiable in prod.
