---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — RM-01 (`parties-anonymisation`) is SLICE-COMPLETE ✅ — all 12/12 tasks done; task 7.1 (PG17 + full close) green on BOTH engines → `CHANGE_COMPLETE` returned.** Task 7.1 was the GUIDE §2.7 close ritual, verify-only leg (no source/test/migration — the existing suite passing on both engines IS the deliverable). SQLite full suite `php -d memory_limit=-1 vendor/bin/pest` = **1883/1883** (10189 assertions, 79s); PHPStan max **0**; Pint clean; `openspec validate parties-anonymisation --strict` valid. PG17 via the §2.7 docker ritual (`postgres:17` = **PostgreSQL 17.10** on port **55432** — 5432 is held by the unrelated always-on `invoicing-system-db-1` PG16; `pg_isready`-gated; `docker rm -f pg` after): whole suite `DB_CONNECTION=pgsql … DB_PORT=55432 … php -d memory_limit=-1 vendor/bin/pest` = **1883/1883** (10189 assertions, 324s), **identical** to SQLite. Proves the four named cross-engine risks: (1) both migrations Postgres-truthful under real DDL; (2) the audit-redaction `before/after→NULL` UPDATE (`AuditRecorder::redactEntity`, base query builder) binds true SQL NULL, immutability triggers intact; (3) the Hold read (`DatabaseComplianceStatusReader`) resolves identically; (4) the `country_code`/`ZZ` `string(2)` + `anonymised+{id}@…` email fit PG's ENFORCED varchar widths. `SupplyLifecycleChainTest` green on both (no Action allow-list red). **Loop is done for this change — the human does §2.7's merge/push + semantic-verify + archive.**

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **Green on SQLite AND PG17:** full suite **1883/1883** (10189 assertions) on both engines; PHPStan max **0**; Pint clean; `openspec validate parties-anonymisation --strict` valid.
- Full suite: `php -d memory_limit=-1 vendor/bin/pest` (bare `php artisan test` OOMs at 128M). PG17 close = the §2.7 docker recipe (port 55432, `memory_limit=-1`, whole suite).

## Active Change & Next Task
- **Active: `parties-anonymisation` (RM-01) — APPROVED ✅, BUILDING → COMPLETE. 12/12 done.** Branch `ralph/parties-anonymisation`. `<promise>CHANGE_COMPLETE</promise>` returned this iteration.
- **NEXT (human, not the loop): GUIDE §2.7 close** — `git merge --no-ff ralph/parties-anonymisation` onto main + push; then the semantic-verify prompt (per-requirement completeness/correctness/coherence); then `openspec archive parties-anonymisation --yes` (merges the 4 delta-spec requirements — *Customer Anonymisation*, *Anonymisation Hold Precedence*, *Customer Address*, *Customer Data Export* — into `openspec/specs/party-registry/`). The loop does NOT merge/archive.
- **After archive:** `/spec-to-change` for the next slice per the Build Workplan.

## Blockers & Decisions Needed
- **None.** RM-01 slice-complete, both engines green. Gate reconciled in ADR `2026-07-02-adopt-dec-015-…` (cite it, not the self-contradictory raw spec).
- **Incidental (candidate F4):** `party-registry` truth-spec *Hold Registry* still says "six-value" while code is **8** (RM-04 debt). RM-01's gate is `compliance`-only / count-independent → did NOT block; still open for a future change.

## Open Patterns
- **§2.7 PG17 close for the RALPH loop = VERIFY-ONLY** (task 7.1): run the docker ritual (`postgres:17` on **55432** to dodge the always-on invoicing PG16, `pg_isready`-gated, `docker rm -f pg` after) + both-engine green + `openspec validate`, then `CHANGE_COMPLETE`. The loop does NOT merge/push/archive/semantic-verify (human legs). `memory_limit=-1` is OOM-proof for the whole-suite PG run.
- **Whole-suite (not folder-only) PG17 run when a change touches migrations or a Platform mechanism.** A console-only/test-only change can run the change folder on PG; RM-01 changed schema + `AuditRecorder` (Platform) → the cross-engine blast radius is the whole app.
- **A cross-engine trap the SQLite loop can't catch:** a constant in a fixed-width `string(N)` column (`ZZ` in `country_code` `string(2)`) — SQLite ignores `varchar(N)`, PG enforces it. Pin with an `mb_strlen(...) <= N` unit assertion; the PG17 close is the backstop.
- **Anonymisation gate = `compliance`-only, count-independent:** key on `HoldType::Compliance` via `PartyComplianceStatusReader`; never the `Hold` model; never wire `sanctions_status`.
