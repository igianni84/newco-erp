---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-08
---

# Hot Cache

## Last Updated
**2026-07-08 (pm) — P3 sweep: task 2.1 green, the first real Action rides the mechanic.** The ralph loop is running on [`catalog-module-0-completeness-sweep`](openspec/changes/catalog-module-0-completeness-sweep/). **4 of 16 tasks done.** BR-Audit-1's Master half is proven end-to-end. Next: the Composite half.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Full suite **2115/2115 on SQLite** (11 049 assertions; +9 from 2.1) · PHPStan max **0** · Pint **clean** · `openspec validate --strict` valid. PG17: 2.1's blast radius (new file + `CatalogContentEditTest` + `ReviewFreshnessVerbFilterTest` + `ProductMasterLifecycleTest`) runs **53/53**; the last FULL PG17 run was 2080/2080 (pre-1.1) — task 7.2 re-runs the whole suite there.
- Run the suite via `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs at 128 MB). **PG17 recipe:** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest`.

## Active Change & Next Task
- **In flight: `catalog-module-0-completeness-sweep`** (branch `ralph/catalog-module-0-completeness-sweep`) — 12 delta reqs · design D1–D11 · **16 tasks / 7 groups**. Key decisions (interview 2026-07-08): re-versioning = in-place + `version`++ · edit scope AC-minimum · whitelist = pivot per-(Variant, Format), empty ⇒ permissive, gate at SKU activation · reviewed-state identity edit **re-arms review** · S1 = 4-suffix filter · RM-15 = projection widened.
- **DONE: 1.1** whitelist pivot · **1.2** review-freshness 4-suffix filtered derivation · **1.3** `CatalogContentEdit` · **2.1** `UpdateProductMasterIdentity`.
- **2.1's shape — copy it for 2.2.** The Action supplies ONLY the `$apply` closure; the mechanic owns txn + `lockForUpdate` + state guard + operator floor + `version`++ in ONE `UPDATE` + one `catalog.<segment>.<verb>` audit row + no event. Replacement semantics (all fields on every call) + a **diff against the LOCKED row**, so only changed fields reach the UPDATE and the audit snapshots. Re-checks (dedup) live INSIDE `$apply` — a rejected edit never invokes it. Related-row writes (wine attributes) join the same transaction.
- **NEXT: 2.2** — `UpdateCompositeSkuComposition` (ordered replace) on the mechanic. Verb `identity_updated`. `array_unique` + N ≥ 2 floor (reuse `InsufficientCompositeConstituents`); when the Composite is `active`, every NEW constituent PR must be `active` (localized rejection otherwise); replace = sync the constituents join preserving `position`. Audit before/after = **ordered PR-id lists** — `toEqual` compares nested lists element-wise by index, which is exactly why it must not be `toBe`. Empty `attributes` map: the Composite's content lives in the join table, so the `version`++ is the whole core UPDATE (1.3 and 2.1 both exercised that shape).
- **Known gap:** 3.1 + 4.1 need a NON-versioning edit (delta spec: no `version` change; 4.1 also fires an event + silent no-op). The mechanic increments unconditionally — add a sibling entry point on `CatalogContentEdit` then, don't retrofit now. 4.1's no-op rule makes the `TranslatableText`-as-map diff (`?->jsonSerialize()`, loose `!=`) load-bearing.
- Remaining loop landmines: R1 (RM-15 blast radius — grep `CreateProductMaster` callers), R2 (`EnumsTest` 2→3), R5 (i18n EN+IT console scanners), R6 (`{@see FQCN}` re-import reds `ModuleBoundariesTest`), and 6.1 must NOT make the identity dedup unconditional (`whereKeyNot` is defence-in-depth — see progress.md).
- **Then: RM-05** (capacity seat-set + WaitingList, last P1) via **K-side seam, ADR-first (grill-with-docs)** — dedicated session, do not fold into the sweep.

## Blockers & Decisions Needed
- None. Loop proceeding task-by-task; humans push and archive.

## Open Patterns
- **"This query was skipped" is testable** — `DB::listen` + a needle only that query emits (a table ALIAS), pinned lock-step with a positive assertion (2.1). Reusable for 3.2's permissive-empty-whitelist gate.
- **A `TranslatableText` diffs by its i18n-keyed map, never by object identity** — `null` is a legitimate value on either side (2.1).
- **A spec's "every OTHER row" guard can be unreachable-by-construction** — write it, document it as defence-in-depth, don't let a test claim it fires (2.1).
- **Derive-from-audit predicates must name their verb set** — and two writers feeding one trail must SHARE the derivation (`CatalogAuditEnvelope`).
- **A guard-then-closure mechanism should prove the closure never ran on rejection** — flip a flag inside the test's closure (1.3).
- **NEVER `->toBe()` a decoded jsonb snapshot map** — PG reorders keys by length; use `->toEqual()` (1.3).
