---
type: meta
description: Hot cache — repo-state digest, overwritten each operation. Chronology lives in log.md.
updated: 2026-07-10
---

# Hot Cache

## Last Updated
**2026-07-10 — F10 EXECUTED end-to-end + PUSHED: fail-closed staleness detector + spec-refresh to canon + full triage.** Three commits (`aaec37e` detector · `d435742` refresh · `b42d0f5` triage) merged to `main` and **pushed** (`main == origin/main`, verified via `git ls-remote`). `openspec/changes/` holds only `archive/`; tree clean.

## Build & Quality Status
- **SQLite 2401/2401** (12 435 assn) · **PG17 2401/2401** (12 442 assn) — +12 over the prior 2389 = the new `SpecStalenessDetectorTest`. **The refresh moved ZERO tests** (2401 = 2401 IDENTICAL before/after, both engines — ruling-3 invariant proven).
- PHPStan max **0** · Pint clean. `spec/` now @ canon **`b7f5ae7` = MVP-DEC-037** (`spec.lock` synced).
- Suite: `php -d memory_limit=1G vendor/bin/pest` (`artisan test` OOMs — the `-d` flag doesn't reach the child). PG17: prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco` (container `pg`, reused).
- **Staleness detector:** `scripts/spec-staleness.sh` (exit 0 fresh / 1 stale / 2 unknown). Wired at `SessionStart` (warn-only) + as a `/spec-to-change` step-0 precondition.

## Active Change & Next Task
- **None in flight.** Read `docs/validation/Remediation_Tracker.md` **§1 ▶️ NEXT** first.
- **Next = RM-26** (+ RM-27) Producer-retirement dual-control (`MVP-DEC-024`, S, reuses RM-08 primitive). The F10 authoring gate is clear: `spec/` is at canon, so `MVP-DEC-024` is simply *in* `spec/`.
- **Refresh-surfaced batch (Round 4, F13):** RM-28 `AC-K-J-7a` compensating control (real FLOOR gap, S, runbook, NOT blocked) · RM-29 Intrinsic-SKU event rename (`033`, M, needs ADR for A-vs-B scope — persisted `name` is a hand-pinned const, no history yet → near-zero-cost now) · RM-30 dedup residual (`023`) · RM-31 Case Config (`025`) · RM-33 `BR-K-Customer-3` (`021`, maybe assertion-only) · RM-32 transactional email (`035`, 🔵 deferred).

## Blockers & Decisions Needed
- **Doc-sync flag for Giovanni (protected files):** `MVP-DEC-028` renamed `ownership_flag` `CRURATED→NEWCO`; still `CRURATED` at **CLAUDE.md:73** + **CONTEXT.md:450/453**. Hand-edit when convenient; no shipped code depends on it (Module B unbuilt).
- **Root `CLAUDE.md`** still says `spec/` "immutable v0.3-MVP handoff baseline" — the wording the F10 ADR flagged for your explicit approval (protected; unedited).
- Queued (gate, not date): **F12** lock-order inversion (before producer HTTP surface) · two canon capacity escalations (before Module A) · **F2** prod operator-mgmt (🟥 go-live). Also: F5/F6/F7/F9/F11.

## Open Patterns
- **Ask the remote; never remember its state.** The detector caught canon at `b7f5ae7`, one commit past the ADR's own `9eaa341` — the number came from the remote, not the document. Exactly F10's thesis, proven mid-execution.
- **A vacuous gate is worse than no gate.** `ls-remote` on a reachable remote with an absent ref exits 0 with empty stdout — so exit-status alone would read "" as STALE. The detector also requires a 40-hex sha. Fail closed. (4th confirmation.)
- **A refresh changes what "correct" means, never what the code does** — zero test moved. Full set in `lessons.md`.
