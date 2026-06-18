---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-18
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-18 (interactive — `parties-holds` 6.3 DONE → CHANGE_COMPLETE, 11/11).** Added the closing proof `tests/Feature/Modules/Parties/HoldChainTest.php` (2 `it`, 46 assertions) driving the whole slice through real Actions: factory Customer (sanctions `passed`) + 2 Profiles → `RequireKyc` (kyc Hold auto-placed + `CustomerHoldPlaced`) → `PlaceHold(Admin)` → read-API NOT clear + cascade to both Profiles → operator-lift of the `kyc` Hold REJECTED (`IllegalHoldLift`) → `LiftHold(admin)` (kyc still active) → `RecordKycVerified` (kyc auto-lifts) → read-API CLEAR + cascade. Event-SET pinned EXACTLY `{CustomerHoldPlaced:2, CustomerHoldLifted:2}` (4 rows, all `parties`/`Hold`/System; `%Kyc%`=0; no demand-side status event); scope guard holds (Customer `pending`, Profiles `applied`). Backfilled the omitted 6.1/6.2 progress entries.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 783/783 SQLite** (3710 assertions, +2 from 781) AND **783/783 on PostgreSQL 17** (full suite — this cross-engine close doubles as the §2.7 pre-merge production-engine gate). PHPStan max 0 · Pint --test clean · `openspec validate parties-holds --strict` valid.
- PG-constraint evidence: `parties_holds` value-set CHECKs (hold_type/scope_type/status) + polymorphic-scope index `parties_holds_scope_status_index (scope_type, scope_id, status)` confirmed on PG; `HoldSchemaTest` 6/6 on PG.

## Active Change & Next Task
- **`parties-holds` — COMPLETE (11/11). `<promise>CHANGE_COMPLETE</promise>`.** Committed on branch `ralph/parties-holds`.
- **Next: GUIDE §2.7 human ritual (IN PROGRESS this session).** (1) review `git log/diff main..ralph/parties-holds`; (2) PG17 pre-merge gate ✅ already green (full suite on PG); (3) `git checkout main && git merge --no-ff ralph/parties-holds`; (4) semantic verify (completeness / correctness / coherence vs the delta specs) — a CRITICAL → fix-tasks + re-loop, else proceed; (5) `openspec archive parties-holds --yes` + commit. **Push only if the user asks.** After archive: overwrite this hot.md to the archived state + pick the next change.

## Blockers & Decisions Needed
- None. Root `CLAUDE.md` Invariant #7 reword (per-type lift) remains the human's call at the gate (Protected); ADR `2026-06-18-hold-lift-discipline-per-type.md` governs. Knowledge-promotion confirmation date for this change = its archive-dir date (2026-06-18).

## Open Patterns
- **Closing chain-test idioms (6.3).** Read active Holds through the read-API (`PartyComplianceStatusReader::forCustomer/forProfile`) — never re-declare a sibling file's global helper (`activeHoldTypesOnCustomer` lives in HoldRegistryTest; Pest loads ALL files → fatal redeclare). Order-insensitive `list<Enum>` set = `->toContain(e)->toContain(e)->toHaveCount(n)` (not `toEqualCanonicalizing` on enum INSTANCES); single/empty stay `->toBe([...])`. A pre-DML app-guard throw (`IllegalHoldLift`) needs NO savepoint for verify-after-throw on PG (trap 5 is for constraint RAISEs only). Factory-set sanctions `passed` keeps the chain's only events the Hold events.
- **Loop stopped one task short.** This run's ralph loop ended at 10/11 and printed the §2.7 "next steps (human)" banner prematurely; 6.3 was finished interactively. Lesson: when a loop banner says "human steps" but `openspec list` shows N−1/N, finish the last task before any merge.
