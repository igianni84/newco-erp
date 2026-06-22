---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-22
---

# Hot Cache

## Last Updated
**2026-06-22 (`operator-console-parties-kyc-sanctions` — ralph loop RUNNING; task 5.1 done, 12/13; only 5.2 left).** Ran the full quality-command sweep (verify-only — NO source change this iteration, the 4.2 twin one group up). All green: `vendor/bin/pint --test` clean (so `pint` format is a no-op), `vendor/bin/phpstan analyse` at max **0 errors** — including `NoEloquentWriteInOperatorPanelRule` (confirmed registered at `phpstan.neon:41`, not vacuously absent; `ViewCustomer`'s KYC/sanctions writes route only through the four domain Actions), and the full Pest suite **1495/1495, 8263 assn, exit 0, 37.5s**. Diff-shape acceptance also confirmed.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (5.1): full suite 1495/1495 (8263 assn, exit 0, 37.5s) on SQLite; PHPStan max 0 err; `pint --test` clean.** PG17 scoped run (4.2) was 372/372 (1854 assn). No source file changed this iteration — only the memory trio + the 5.1 checkbox flip.
- Full suite OOMs at PHP default 128 MB in result parsing — run pest with `php -d memory_limit=-1`.
- Diff vs main: NO `spec/` / `openspec/specs/` / `tests/Architecture/` change; NO composer dep; NO migration. `ModuleBoundariesTest` UNCHANGED (the `Parties\Enums` operand-enum import — `SanctionsStatus`/`ScreeningTriggerSource` — rides the existing carve-out; `KycStatus` never imported).

## Active Change & Next Task
- **`operator-console-parties-kyc-sanctions` — APPROVED, IN PROGRESS (12/13).** Delta on `operator-console`: 2 ADDED (KYC; sanctions) + 2 MODIFIED Customer reqs; 13 tasks / 5 groups. Groups 1–4 + task 5.1 DONE.
- **Next task 5.2 (the change's LAST):** `openspec validate operator-console-parties-kyc-sanctions --strict` (already green this iteration as a sanity check) + memory consolidation — append `log.md` (memlog.sh), overwrite `hot.md`, consolidate the durable patterns into this change's `progress.md ## Codebase Patterns` (form-less `lifecycleAction`+chained `->visible()` from-state gate; cast-value state-enum predicate vs operand-enum import; record-dependent option-set drop for onboarding-first; event-silent KYC verbs → assert coupled Hold/status events + absence of any KYC event; cross-slice chain via the shipped Holds table). Most are ALREADY consolidated there — 5.2 verifies/tightens.
- After 5.2: ALL 13 done → final pass (re-verify every acceptance bullet at a glance, full commands green, `openspec validate --strict` green, `hot.md` updated) → reply `<promise>CHANGE_COMPLETE</promise>`. Do NOT archive/merge — humans review → semantic-verify (GUIDE §2.7) → `openspec archive`.

## Blockers & Decisions Needed
- **None.** No open-ADR gate crossed by this slice. 5.2 is pure validation + memory work (no Docker, no new code).
- **Holds push gate STILL pending** (separate change) — origin/main push of the Holds archive+merge commits + `git branch -d ralph/operator-console-parties-holds` await Giovanni's go (classifier-gated). Local merge+archive already done.

## Open Patterns
- **Verify-only quality-gate task** mirrors the PG17 ritual (4.2): no new code, the command sweep IS the deliverable; the commit carries only the memory trio + checkbox.
- **Prove a custom PHPStan rule's 0-errors is MEANINGFUL** by grepping `phpstan.neon` for its registration — an unregistered rule silently "passes". Same for arch guards: confirm `tests/Architecture/` UNCHANGED via `git diff --stat main`.
