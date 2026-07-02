---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — Building `catalog-review-freshness-resubmit` (RM-06), last Round-1 item. Task 4.2 DONE (the visibility-gated `re-submit` header action on the OTHER SIX catalog consoles — Format / Case Configuration / Product Variant / Product Reference / Sellable SKU / Composite SKU — mirroring Master 4.1; +6 console tests +3 IT-dataset cases); 9/10.** Round-1 compliance-remediation driven by Paolo Alfieri's 2026-07-01 mail. Verdict reports in **`docs/validation/`**; live backlog **`Remediation_Tracker.md`**. On origin/main: RM-07 `5b64cc8`, RM-04 `d8ec261`, RM-09+F3 `5eb415d`, RM-10 `04406b8`, RM-24 `4c373af`.

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **GREEN:** full suite **1807/1807** (+9 from 4.2), PHPStan 0, Pint clean (format + lint), `openspec validate --strict` valid.
- Run the full suite via `php -d memory_limit=-1 vendor/bin/pest` — bare `php artisan test` OOMs at 128M (lessons.md 2026-06-20).

## Active Change & Next Task
- **Change `catalog-review-freshness-resubmit` (RM-06) — APPROVED, 9/10 done.** Block-gate on `reviewed→active` + explicit `re-submit` (`reviewed→reviewed`), derive-from-audit (no schema). edit-re-arms→RM-14; canon MVP-DEC-019.
- **Done:** 1.1 ADR; 1.2 factories; 2.1 `resubmit()`+Master action; 2.2 block-gate in `guard()`; 2.3 2-round scenario; 3.1 six other `Resubmit*` actions; 3.2 cross-entity uniformity; 4.1 Master console `re-submit`; 4.2 the other six consoles.
- **NEXT: task 5.1 (the change's LAST task)** — full-suite + PHPStan + Pint + **PG17 cross-engine close**. (a) Grep `grep -rn "not terminal\|->reject(\|latestGovernanceAction" tests/` — confirm EVERY reject-then-activate path now inserts a re-submit or asserts the block (the "not terminal" test was already inverted in 2.2; the six per-entity + console tests stop at the block or re-arm). (b) Run the catalog lifecycle + console suites on **SQLite AND PG17** — the block-gate reads `audit_records.action` (a string column, engine-neutral) via `orderByDesc('id')`; confirm the latest-action read behaves identically on PG17. (c) Confirm no exhaustive Action allow-list reds. (d) `openspec validate --strict` green. Then reply `<promise>CHANGE_COMPLETE</promise>` (humans archive/merge).

## Blockers & Decisions Needed
- None for this change. ⚠️ **DEC-019 collision:** canon MVP-DEC-019 = review-freshness (this change); frozen spec's own DEC-019 = unrelated Module-S club composites — never conflate.
- Canon drift DEC-007→DEC-023 still open on Module K (RM-03, RM-05) — wait on Modules S/E/A.

## Open Patterns
- **Console re-submit fan-out (4.2, reusable for 5.1 verify):** the six spine consoles now each override `getHeaderActions()` = `[...parent::getHeaderActions(), lifecycleAction('resubmit','resubmitted', …app(Resubmit{E}…))->visible(fn () => $this->isRejectionPending())]` (mirrors Master minus cascade); each needed a NEW `Filament\Actions\Action` import. i18n `actions.resubmit`+`notifications.resubmitted` are kit-resolved (invisible to the source scan) → live in `SpineConsoleI18nTest::spineConsoleKitKeys()`, authored EN+IT ×6. **The per-console test proves the label WITHOUT hardcoding it:** reject→visible→`callAction('resubmit')`→`assertActionHidden('resubmit')` — hidden-again only holds if the resubmit row was written under the record's `class_basename`. Build-to-`reviewed` needs only draft factory parents (create/submit/reject never gate on parent state — only activate does).
- **Run the i18n sink-scanner guard via the DIR/full suite, NEVER a bare `SpineConsoleI18nTest.php` path** — it depends on `scanOperatorConsoleHardcodedSinks()` declared in `ProductMasterConsoleI18nTest` (lessons.md 2026-06-20).
- **`{@see \App\Modules\Catalog\Lifecycle\X}` in a console docblock → Pint ADDS a forbidden import** — name Catalog governance/mechanism classes (e.g. `ApprovalGovernanceViolation`) in PROSE (lessons.md 2026-06-20, re-confirmed 4.1/4.2).
- **Incidental findings open (Tracker §7):** F1 DemoSeeder SQLite-only; F2 prod operator-mgmt missing → SoD unsatisfiable in prod.
