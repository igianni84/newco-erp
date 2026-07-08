---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-08
---

# Hot Cache

## Last Updated
**2026-07-08 (pm) — P3 sweep AUTHORED + APPROVED ✅: [`catalog-module-0-completeness-sweep`](openspec/changes/catalog-module-0-completeness-sweep/) is ready to build.** `/spec-to-change` on RM-12+13+14+15 (Module 0 completeness) + the mandatory RM-06 S1 fold-in + the console-resubmit truth-spec sync (F4 precedent); `openspec validate --strict` green. Giovanni reviewed and created the `APPROVED` marker (14:53, human-only protected file); scaffolding + marker committed as one `approve:` commit (anti-exit-5). Details: tracker §1/§6 + the change's proposal/design.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Full suite **2080/2080 on SQLite AND PG17** (10 854 assertions each) · PHPStan max **0** · Pint **clean** (unchanged — this session wrote change artifacts + docs only, zero code).
- Run the suite via `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs at 128 MB). **PG17 recipe:** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest`.

## Active Change & Next Task
- **In flight (authored + APPROVED ✅, not yet built): `catalog-module-0-completeness-sweep`** — 12 delta reqs (5 ADDED / 7 MODIFIED on `product-catalog` + `operator-console`) · design D1–D11 · **15 tasks / 7 groups**. Key decisions (interview 2026-07-08, 3 rounds, 11 decisions): re-versioning = in-place + `version`++ (audit = old-version retrievability) · edit scope AC-minimum (Master identity ×4 fields, Composite composition, Variant enrichment `tasting_notes`, whitelist) · whitelist = pivot per-(Variant, Format), empty ⇒ permissive, gate at SKU activation, maintenance audit-only even on `active` · reviewed-state identity edit **re-arms review** (blocked until explicit resubmit) · **S1 = 4-suffix filter** (`.submitted/.resubmitted/.rejected/.identity_updated`) on BOTH readers — `ApprovalGovernance` AND console `isRejectionPending` (same raw-read hole, found at authoring) · RM-15 = projection widened (`ProducerCreated` → `registered`, enum 2→3, existence guard in `CreateProductMaster`; no mini-ADR, design D7 records it).
- **NEXT: launch the loop — `./ralph.sh --change catalog-module-0-completeness-sweep`** (APPROVED + scaffolding already committed as `approve:` — the integrity baseline contains the marker, exit-5 cannot fire). 15 tasks, one per iteration, group 1 first (whitelist schema → S1 filter → edit mechanic). Loop landmines pre-mapped: design R1 (RM-15 blast radius — grep `CreateProductMaster` callers), R2 (`EnumsTest` 2→3), R3 (console/domain freshness uniformity), R5 (i18n EN+IT scanners).
- **Then: RM-05** (capacity seat-set + WaitingList, last P1) via **K-side seam, ADR-first (grill-with-docs)** — dedicated session, do not fold into the sweep.

## Blockers & Decisions Needed
- None — the sweep is APPROVED and committed; only the ralph launch (Giovanni's call: loop vs interactive) remains.

## Open Patterns
- **Batched changes amortize the §2.7 ceremony without diluting rigor** — per-item grounding + per-item delta tracing held for a 4-item M-batch (lessons 2026-07-07 + 2026-07-08 amendment).
- **Derive-from-audit predicates must name their verb set** — any second audit writer breaks a raw latest-read (S1); the 4-suffix filter + verb-collision discipline (design D5) is the durable form. Watch it recur on future audit-adjacent gates (candidate for `knowledge/architecture/` after the build confirms).
- Console kit duplicates domain derivations (visibility gates) — sweep both sides when the domain predicate changes (design D9/R3).
