---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-08
---

# Hot Cache

## Last Updated
**2026-07-08 (pm) — P3 sweep: task 2.2 green, BR-Audit-1 proven end-to-end on BOTH halves.** The ralph loop is running on [`catalog-module-0-completeness-sweep`](openspec/changes/catalog-module-0-completeness-sweep/). **5 of 16 tasks done.** Next: 2.3, the re-arm proof through the real actions.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Full suite **2124/2124 on SQLite** (11 106 assertions; +9 from 2.2) · PHPStan max **0** · Pint **clean** · `openspec validate --strict` valid. PG17: 2.2's blast radius (new file + `CatalogContentEditTest` + `CompositeSkuLifecycleTest` + `UpdateProductMasterIdentityTest` + `ReviewFreshnessVerbFilterTest`) runs **43/43**; the last FULL PG17 run was 2080/2080 (pre-1.1) — task 7.2 re-runs the whole suite there.
- Run the suite via `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs at 128 MB). **PG17 recipe:** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest`.

## Active Change & Next Task
- **In flight: `catalog-module-0-completeness-sweep`** (branch `ralph/catalog-module-0-completeness-sweep`) — 12 delta reqs · design D1–D11 · **16 tasks / 7 groups**. Key decisions (interview 2026-07-08): re-versioning = in-place + `version`++ · edit scope AC-minimum · whitelist = pivot per-(Variant, Format), empty ⇒ permissive, gate at SKU activation · reviewed-state identity edit **re-arms review** · S1 = 4-suffix filter · RM-15 = projection widened.
- **DONE: 1.1** whitelist pivot · **1.2** review-freshness 4-suffix filtered derivation · **1.3** `CatalogContentEdit` · **2.1** `UpdateProductMasterIdentity` · **2.2** `UpdateCompositeSkuComposition`.
- **The Action shape — copy it for 3.1/4.1.** The Action supplies ONLY the `$apply` closure; the mechanic owns txn + `lockForUpdate` + state guard + operator floor + `version`++ in ONE `UPDATE` + one `catalog.<segment>.<verb>` audit row + no event. Replacement semantics + a diff against the LOCKED row. **All re-checks live INSIDE `$apply`**, so the state/operator guards win over them (2.2 pins it: a 1-element edit of a `retired` Composite reports `retired`, not the count). Related-row writes (wine attributes, the constituents join) join the same transaction.
- **NEXT: 2.3** — the deferred DEC-019 re-arm leg, end-to-end through the REAL actions (delta *Approval Governance*). (a) submit → `UpdateProductMasterIdentity` → distinct-approver activate BLOCKED (`ApprovalGovernanceViolation`, discriminating token `edited`) → explicit `ResubmitProductMasterForReview` → activate succeeds with exactly ONE `ProductMasterActivated`. (b) the 2-round J-7 flow with a REAL edit inside each round (reject → edit → resubmit ×2 → distinct approver activates), full history preserved (2 rejections + 2 edits + 2 resubmits in `audit_records`). No new production code expected — 1.2 built the derivation, 2.1 feeds it the verb. Fixture lineage: `UpdateProductMasterIdentityTest`'s draft-clear test is the template (`identityEditProjectProducerActive(7)` + 3 distinct operators + `DatabaseMigrations`).
- **Known gap:** 3.1 + 4.1 need a NON-versioning edit (delta spec: no `version` change; 4.1 also fires an event + silent no-op). The mechanic increments unconditionally — add a sibling entry point on `CatalogContentEdit` then, don't retrofit now. 4.1's no-op rule makes the `TranslatableText`-as-map diff (`?->jsonSerialize()`, loose `!=`) load-bearing.
- Remaining loop landmines: R1 (RM-15 blast radius — grep `CreateProductMaster` callers), R2 (`EnumsTest` 2→3), R5 (i18n EN+IT console scanners), R6 (`{@see FQCN}` re-import reds `ModuleBoundariesTest`), and 6.1 must NOT make the identity dedup unconditional (`whereKeyNot` is defence-in-depth — see progress.md).
- **Then: RM-05** (capacity seat-set + WaitingList, last P1) via **K-side seam, ADR-first (grill-with-docs)** — dedicated session, do not fold into the sweep.

## Blockers & Decisions Needed
- None. Loop proceeding task-by-task; humans push and archive.

## Open Patterns
- **One invariant ⇒ one exception CLASS; one surface ⇒ one reason.** The edit path re-asserts the activation cascade, so it throws `ActivationCascadeViolation` — under a second factory + lang key, because the operator pressed *save composition*, not *activate* (2.2; task 6.3 surfaces that copy).
- **PHPStan max: `Collection::map()->all()` is `array<int,int>`, never `list<int>`** — wrap in `array_values()`, don't relax the return type. Will bite 3.1's CC-id sets and 6.3's multi-select.
- **"This query was skipped" is testable** — `DB::listen` + a needle only that query emits (a table ALIAS), pinned lock-step with a positive assertion (2.1). Reusable for 3.2's permissive-empty-whitelist gate.
- **NEVER `->toBe()` a decoded jsonb snapshot map** — PG reorders keys by length; `->toEqual()` still compares nested ordered LISTS element-wise (2.2's constituent lists).
- **Derive-from-audit predicates must name their verb set** — and two writers feeding one trail must SHARE the derivation (`CatalogAuditEnvelope`).
