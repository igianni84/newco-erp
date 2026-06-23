---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-23
---

# Hot Cache

## Last Updated
**2026-06-23 (`operator-console-parties-kyc-sanctions` — ralph loop DONE; 13/13, `CHANGE_COMPLETE` emitted).** Task 5.2 (the change's LAST) was validation + memory only, NO source change: `openspec validate operator-console-parties-kyc-sanctions --strict` valid (exit 0), and the final-pass quality sweep re-run (not just trusted from 5.1, since this gate hands off to humans) — `pint --test` clean, `phpstan analyse` max **0 errors**, full Pest **1495/1495, 8263 assn, exit 0** (~41s). All five durable patterns the task names were verified already-consolidated in the change's `progress.md ## Codebase Patterns` (no padding added). Awaiting human review/merge/archive.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (5.2 final pass): full suite 1495/1495 (8263 assn, exit 0) on SQLite; PHPStan max 0 err; `pint --test` clean.** PG17 scoped run (4.2) was 372/372 (1854 assn). No source file changed in 5.1 or 5.2 — only the memory trio + checkbox flips.
- Full suite OOMs at PHP default 128 MB in result parsing — run pest with `php -d memory_limit=-1`.
- Diff vs main: NO `spec/` / `openspec/specs/` / `tests/Architecture/` change; NO composer dep; NO migration. `ModuleBoundariesTest` UNCHANGED (the `Parties\Enums` operand-enum import — `SanctionsStatus`/`ScreeningTriggerSource` — rides the existing carve-out; `KycStatus` never imported).

## Active Change & Next Task
- **`operator-console-parties-kyc-sanctions` — COMPLETE (13/13), `<promise>CHANGE_COMPLETE</promise>` emitted.** Delta on `operator-console`: 2 ADDED (KYC; sanctions) + 2 MODIFIED Customer reqs; 13 tasks / 5 groups all `- [x]`. Shipped: 3 form-less visibility-gated KYC verbs + 1 bespoke sanctions-screening form on `ViewCustomer`, KYC↔Hold auto-coupling proven, onboarding-first floor, the PG17 cross-slice chain test through the shipped Holds widget.
- **Next: HUMAN action — review → semantic-verify (GUIDE §2.7) → `openspec archive operator-console-parties-kyc-sanctions --yes`.** Do NOT archive/merge from the loop. No further ralph task on this change.
- After archive, the next slice is picked from `spec/05-release/Build_Workplan_v0.3-MVP.md` (Parties console KYC/sanctions now closed; check the workplan for the next operator-console or module slice).

## Blockers & Decisions Needed
- **None for this change.** No open-ADR gate crossed by the slice. 5.2 was pure validation + memory.
- **Holds push gate STILL pending** (separate, earlier change) — origin/main push of the Holds archive+merge commits + `git branch -d ralph/operator-console-parties-holds` await Giovanni's go (classifier-gated). Local merge+archive already done. Unrelated to this change's review.

## Open Patterns
- **Verify-only / memory-only closing task** (5.2 here, mirrors the 4.2 PG17 ritual + 5.1 quality gate): no new code, the commit carries only the memory trio + checkbox; the validation/sweep IS the deliverable. The full suite subsumes per-task `--filter` runs.
- **A change's closing memory-consolidation is usually a VERIFY** of the running `## Codebase Patterns` (each iteration already consolidated its own durable pattern) — confirm each named pattern is present/accurate, resist re-authoring redundant copies.
- **Prove a custom PHPStan rule's 0-errors is MEANINGFUL** by grepping `phpstan.neon` for its registration; confirm arch guards UNCHANGED via `git diff --stat main` — an unregistered rule / assumed-clean arch test silently "passes".
