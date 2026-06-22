---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-22
---

# Hot Cache

## Last Updated
**2026-06-22 (`operator-console-parties-customer` — CLOSED: merged + archived + pushed; `main` in sync with `origin`).** Full §2.7 close ritual done: PG17 full-suite gate **1397/1397 (7683 assn)** green → `--no-ff` merge to `main` (`eaec03c`) → semantic-verify **clean** (0 CRITICAL / 0 WARNING; 2 SUGGESTIONs forwarded, not blocking) → `openspec archive` (`2094f08`, archived as `2026-06-22-operator-console-parties-customer`, **+3 requirements** merged into `openspec/specs/operator-console/spec.md`) → pushed (`109e12d..e30aa1b`), merged branch deleted. The change shipped the first **demand-side** Parties console: read-only `CustomerResource` (3 orthogonal lifecycle badges + Account status + Profiles, all cast-rendered), write-through `CreateCustomer` (platform operands, born `pending`, Account-only co-provision), `ViewCustomer` 4 form-less status verbs (activate/suspend/reactivate/close via `SurfacesDomainActions`), EN/IT i18n, and ADR `2026-06-21-...-rule-of-three-trait-vs-verb-list` closing D8 (trait stays the seam; `OperatorConsoleViewRecord` stays catalog-only).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (close gate): SQLite 1397/1397 + PG17 FULL 1397/1397 (7683 assn, container torn down); phpstan 0; pint clean; `openspec validate operator-console --type spec --strict` valid; `ModuleBoundariesTest` 3/3 unchanged.**
- Run-cmd: full `php -d memory_limit=-1 vendor/bin/pest`. PG17 = §2.7 ritual: `docker run -d --name pg … postgres:17 -p 55432:5432` → poll `pg_isready` → `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 … php -d memory_limit=-1 vendor/bin/pest` (full suite, or a folder + a Catalog i18n test so the shared sink helper loads) → `docker rm -f pg`. i18n tests via `--filter`/full suite, NEVER a bare path.

## Active Change & Next Task
- **No active change** (`openspec list` empty). The Customer slice is closed.
- **Next slice: `operator-console-parties-compliance`** — surfaces `PlaceHold`/`LiftHold` (+ KYC/sanctions writes). Its coupling ALSO moves Customer status, so its design MUST reference **D4** of the now-archived Customer change and treat the Hold-driven transition as **additive** to (not a replacement of) the direct activate/suspend/reactivate/close verbs already shipped. Prepare via `/spec-to-change` → human APPROVED → `./ralph.sh`.

## Blockers & Decisions Needed
- **None.** Giovanni approved the push: `main` pushed to `origin/main` (`109e12d..e30aa1b`), now **in sync**; merged branch `ralph/operator-console-parties-customer` deleted. Clean slate for the next slice. *(History note: the prior hot.md "2 unpushed supply-side commits" claim was STALE — supply-side had already been pushed.)*

## Open Patterns
- **Non-catalog status-FSM view page = `ViewRecord` + `use SurfacesDomainActions` + bespoke `getHeaderActions()`** (rule-of-three / D8 CLOSED — proven across Producer/Club/ProducerAgreement/Customer). A verb-list base needs NEW evidence beyond these four.
- **Console i18n completeness test = enumerate kit contract + 5 guards** (proven 6×). Recipe + 2 gotchas in the archived change's `progress.md` §Codebase Patterns.
- **Two-part closing-chain for a cross-slice-gated FSM:** gate-UNMET path through the REAL create page (graceful reject, D5), then a factory-seeded gate-MET record through the full FSM; intermediate single-element `toEqual([...])` localises the gate claim before the global `toEqualCanonicalizing`.
- **Forward (test depth):** the compliance/profile slices should add the exhaustive read-only negative-space asserts the Customer tests covered only representatively (profiles-rendered-with-a-seeded-Profile; no sanctions/account/profile write verb).
