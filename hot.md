---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-06
---

# Hot Cache

## Last Updated
**2026-07-06 — `reconcile-hold-registry-eight-types` MERGED (`--no-ff` 8f2f6bf) + ARCHIVED (`2026-07-06-…`). Close ritual §2.7 done locally (NOT pushed).** F4/RM-04 closed: the two truth-specs (`party-registry`, `operator-console`) now state the **eight-value** `HoldType` domain, matching shipped code (`d8ec261`) + canon MVP-DEC-008. Independent semantic verification (2nd agent, fresh read): **CLEAN-WITH-SUGGESTIONS** — zero CRITICAL/WARNING.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- This change touched **zero code/test/migration** → full suite byte-identical to pre-merge (1951/1951). Hold suite re-run green this session: **86/86** (571 assertions, ~3s, SQLite).
- ⚠ Hold filter via `php -d memory_limit=2G vendor/bin/pest --filter='HoldEnums|HoldRegistry|HoldLifecycle|ComplianceReadApi|CustomerAnonymisationHoldPrecedence|CustomerHoldsConsole|HoldStatusCoupling|CustomerHoldsChain'` (artisan `--filter` multi-class = 128M fatal). PG17 close-ritual recipe applies to **code-bearing** changes; **skipped here** (no code delta — nothing new to verify cross-engine).

## Active Change & Next Task
- **NO active change** — `openspec/changes/` clear of in-flight work.
- **NEXT:** author the next change (`/spec-to-change`). Candidate: **RM-01 `parties-anonymisation`** was authored + awaiting `APPROVED` (Remediation_Tracker §6); or the next Remediation / Build-Workplan item.

## Blockers & Decisions Needed
- **⭐ PUSH PENDING (gated):** local `main` is **8 commits ahead** of `origin/main` (`dc67aae`), 0 behind → clean fast-forward. Includes this change's full lifecycle + the earlier unpushed RM-03 memory commit (`a8c8bdb`). Awaiting Giovanni's OK (close-ritual push gate).
- **⭐ Giovanni Protected hand-edit (still open):** `CONTEXT.md` Hold-type glossary still six-value / self-contradictory — l.371 (half-fix: says "eight", lists six), l.372, l.215, l.222, l.234, l.379, l.380 (recommended prose in archived `…/2026-07-06-reconcile-hold-registry-eight-types/progress.md` §3.1). `CLAUDE.md` l.67 ✅ done (working-tree `M`, un-staged).
- **F4 bookkeeping:** `docs/validation/Remediation_Tracker.md` §7 l.30 still calls F4 "candidate (untriaged)" — mark resolved (archived 2026-07-06), mirroring F3's inline ✅. Not Protected; left to Giovanni's convention.
- **Stale docblock (out of scope, pre-existing):** `CustomerHoldsTable.php:51-53` & `:300-304` name four operator-liftable types; code at `:311` is correct (`!autoLiftable()`). Future one-line cleanup.

## Open Patterns
- **Spec-reconciliation change:** code shipped ahead of truth-spec → MODIFIED-only delta reproducing each requirement VERBATIM + surgical eight-value tokens; faithfulness = word-diff delta vs live + `openspec validate --strict` + independent semantic pass; `openspec archive` applies it into `openspec/specs/**`.
- **Half-fixed Protected file:** a pre-edit can swap a count token yet leave the body contradicting it — word-diff `git diff HEAD` + re-grep the working tree; report *current* per-line status, never the authored flag list.
- **Close ritual with uncommitted Protected WIP:** stage the archive with targeted `git add openspec/ hot.md log.md`, **NEVER `git add -A`** (would sweep Giovanni's un-staged `CLAUDE.md`/`CONTEXT.md`).
