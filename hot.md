---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-06
---

# Hot Cache

## Last Updated
**2026-07-06 — `reconcile-hold-registry-eight-types` MERGED (`--no-ff` 8f2f6bf) + ARCHIVED (`2026-07-06-…`) + follow-ups. Close ritual §2.7 done locally (NOT pushed — classifier-blocked).** F4/RM-04 closed: the two truth-specs (`party-registry`, `operator-console`) now state the **eight-value** `HoldType` domain, matching shipped code (`d8ec261`) + canon MVP-DEC-008. Independent semantic verification (2nd agent, fresh read): **CLEAN-WITH-SUGGESTIONS** — zero CRITICAL/WARNING. **Follow-ups DONE locally:** `CONTEXT.md` Hold-type glossary reconciled 6→8 (8 spots), `Remediation_Tracker.md` F4 marked ✅ resolved (l.30 + l.92), `CustomerHoldsTable` docblock 4→6 types (Pint green).

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- This change touched **zero code/test/migration** → full suite byte-identical to pre-merge (1951/1951). Hold suite re-run green this session: **86/86** (571 assertions, ~3s, SQLite).
- ⚠ Hold filter via `php -d memory_limit=2G vendor/bin/pest --filter='HoldEnums|HoldRegistry|HoldLifecycle|ComplianceReadApi|CustomerAnonymisationHoldPrecedence|CustomerHoldsConsole|HoldStatusCoupling|CustomerHoldsChain'` (artisan `--filter` multi-class = 128M fatal). PG17 close-ritual recipe applies to **code-bearing** changes; **skipped here** (no code delta — nothing new to verify cross-engine).

## Active Change & Next Task
- **NO active change** — `openspec/changes/` clear of in-flight work.
- **NEXT:** author the next change (`/spec-to-change`). Candidate: **RM-01 `parties-anonymisation`** was authored + awaiting `APPROVED` (Remediation_Tracker §6); or the next Remediation / Build-Workplan item.

## Blockers & Decisions Needed
- **⭐ PUSH PENDING (classifier-blocked):** local `main` is ahead of `origin/main` (`dc67aae`), 0 behind → clean fast-forward. **Working tree is now clean — everything committed.** The in-session `git push` was **denied by the auto-mode classifier** — **Giovanni runs `! git push`** (or adds a Bash push permission). Bundles this change's full lifecycle + follow-ups (CONTEXT.md/tracker/docblock/CLAUDE.md) + the earlier unpushed RM-03 memory commit (`a8c8bdb`).
- **CONTEXT.md + CLAUDE.md reconciliation ✅ DONE & committed** — CONTEXT.md Hold-type glossary + 7 spots to eight-value (no "six" left); CLAUDE.md l.67 `(6 types)`→`(8 types)` (Giovanni-authorized commit of his hard-Protected edit).
- **F4 ✅ DONE** — `Remediation_Tracker.md` l.30 + l.92 mark F4 resolved 2026-07-06.
- **Stale docblock ✅ DONE** — `CustomerHoldsTable` docblock 4→6 operator-liftable types (Pint green).

## Open Patterns
- **Spec-reconciliation change:** code shipped ahead of truth-spec → MODIFIED-only delta reproducing each requirement VERBATIM + surgical eight-value tokens; faithfulness = word-diff delta vs live + `openspec validate --strict` + independent semantic pass; `openspec archive` applies it into `openspec/specs/**`.
- **Half-fixed Protected file:** a pre-edit can swap a count token yet leave the body contradicting it — word-diff `git diff HEAD` + re-grep the working tree; report *current* per-line status, never the authored flag list.
- **Close ritual with uncommitted Protected WIP:** stage the archive with targeted `git add openspec/ hot.md log.md`, **NEVER `git add -A`** (would sweep Giovanni's un-staged `CLAUDE.md`/`CONTEXT.md`).
