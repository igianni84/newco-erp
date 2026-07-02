---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — Building `catalog-review-freshness-resubmit` (RM-06), last Round-1 item. Task 4.1 done (console `re-submit` header action on the Product Master View page, visibility-gated to a new shared base read `isRejectionPending()`; +2 console tests; green after fixing the `{@see}`→forbidden-import Pint trap); 8/10.** Round-1 compliance-remediation driven by Paolo Alfieri's 2026-07-01 mail. Verdict reports in **`docs/validation/`**; live backlog **`Remediation_Tracker.md`**. On origin/main: RM-07 `5b64cc8`, RM-04 `d8ec261`, RM-09+F3 `5eb415d`, RM-10 `04406b8`, RM-24 `4c373af`.

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **GREEN:** full suite **1798/1798** (+2 from 4.1), PHPStan 0, Pint clean, `openspec validate --strict` valid.
- Run the full suite via `php -d memory_limit=-1 vendor/bin/pest` — bare `php artisan test` OOMs at 128M (lessons.md 2026-06-20).

## Active Change & Next Task
- **Change `catalog-review-freshness-resubmit` (RM-06) — APPROVED, 8/10 done.** Block-gate on `reviewed→active` + explicit `re-submit` (`reviewed→reviewed`), derive-from-audit (no schema). edit-re-arms→RM-14; canon MVP-DEC-019.
- **Done:** 1.1 ADR; 1.2 factories; 2.1 `resubmit()`+Master action; 2.2 block-gate in `guard()`; 2.3 2-round scenario; 3.1 six other `Resubmit*` actions; 3.2 cross-entity uniformity test; 4.1 Master console `re-submit`.
- **NEXT: task 4.2** — add the SAME visibility-gated `re-submit` header action to the other SIX catalog consoles (Variant / Reference / Format / Case Configuration / Sellable SKU / Composite SKU View pages). Shared read `OperatorConsoleViewRecord::isRejectionPending()` already exists (base, landed in 4.1) — each of the six appends inline to `getHeaderActions()`: `$this->lifecycleAction('resubmit','resubmitted', fn (Model $r, string $n) => app(Resubmit{Entity}ForReview::class)->handle($this->recordOf({Entity}::class, $r)))->visible(fn () => $this->isRejectionPending())`. **⚠️ Those six pages currently DON'T override `getHeaderActions()`** (only supply `lifecycleInvocations()`) — add the override spreading `...parent::getHeaderActions()`. Then: (a) EN+IT `actions.resubmit`+`notifications.resubmitted` per entity in `lang/{en,it}/operator_console.php` (review-flow verb → author BOTH, like submit/reject/reopen); (b) **add `actions.resubmit`+`notifications.resubmitted` to `SpineConsoleI18nTest::spineConsoleKitKeys()`** (kit-contract list → drives the six-entity EN-baseline test); (c) per-console tests `assertActionHidden/Visible('resubmit')` + a re-arm (lessons 2026-06-23/24). Then 5.1 = full-suite + PHPStan + Pint + PG17 close.

## Blockers & Decisions Needed
- None for this change. ⚠️ **DEC-019 collision:** canon MVP-DEC-019 = review-freshness (this change); frozen spec's own DEC-019 = unrelated Module-S club composites — never conflate.
- Canon drift DEC-007→DEC-023 still open on Module K (RM-03, RM-05) — wait on Modules S/E/A.

## Open Patterns
- **Console visibility read on the BASE, action per-page (4.1):** `isRejectionPending()` (shared derivation, Platform `AuditRecord` only — no `module`/`App\Modules\Module`, since `entity_type`=`class_basename` is catalog-unique; UX-only, the domain block-gate is sole enforcement, design L4) lives on `OperatorConsoleViewRecord`; the gated `resubmit` action is inline per-page (mirrors `retireCascade`). **`{@see \App\Modules\Catalog\Lifecycle\X}` in a console docblock → Pint ADDS a forbidden import** — name Catalog classes in PROSE (lessons.md 2026-06-20, re-confirmed). No exhaustive console header-action-NAME set exists (`['index','create','view']` `toEqualCanonicalizing` = `getPages()` keys — a new header action reds NOTHING).
- **Factory-active PARENTS open every within-module cascade gate**; only the Master needs the real Producer projection (`lifecycleConsoleProjectProducer` records `ProducerActivated` in a `DB::transaction`).
- **SoD survives N rejection rounds:** `.resubmitted` ⊄ `%.submitted`, so `reviewerOf` stays the single original submitter; creator re-submits, reviewer rejects, distinct approver activates.
- **Incidental findings open (Tracker §7):** F1 DemoSeeder SQLite-only; F2 prod operator-mgmt missing → SoD unsatisfiable in prod.
