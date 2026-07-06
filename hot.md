---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-06
---

# Hot Cache

## Last Updated
**2026-07-06 — `reconcile-hold-registry-eight-types` MERGED (`--no-ff` 8f2f6bf) + ARCHIVED (`2026-07-06-…`) + follow-ups. Close ritual §2.7 COMPLETE + PUSHED (`dc67aae..067f459`, origin synced).** F4/RM-04 closed: the two truth-specs (`party-registry`, `operator-console`) now state the **eight-value** `HoldType` domain, matching shipped code (`d8ec261`) + canon MVP-DEC-008. Independent semantic verification (2nd agent, fresh read): **CLEAN-WITH-SUGGESTIONS** — zero CRITICAL/WARNING. **Follow-ups DONE (committed + pushed):** `CONTEXT.md` Hold-type glossary reconciled 6→8 (8 spots), `Remediation_Tracker.md` F4 marked ✅ resolved (l.30 + l.92), `CustomerHoldsTable` docblock 4→6 types (Pint green), `CLAUDE.md` l.67 `(6 types)`→`(8 types)`.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- This change touched **zero code/test/migration** → full suite byte-identical to pre-merge (1951/1951). Hold suite re-run green this session: **86/86** (571 assertions, ~3s, SQLite).
- ⚠ Hold filter via `php -d memory_limit=2G vendor/bin/pest --filter='HoldEnums|HoldRegistry|HoldLifecycle|ComplianceReadApi|CustomerAnonymisationHoldPrecedence|CustomerHoldsConsole|HoldStatusCoupling|CustomerHoldsChain'` (artisan `--filter` multi-class = 128M fatal). PG17 close-ritual recipe applies to **code-bearing** changes; **skipped here** (no code delta — nothing new to verify cross-engine).

## Active Change & Next Task
- **NO active change** — `openspec/changes/` clear of in-flight work; working tree clean.
- **RM-03 ✅ DONE + tracker reconciled** — ADR `2026-07-03-adopt-mvp-dec-016-membership-charge-on-approval` (active) + change archived `2026-07-03-parties-membership-charge-on-approval` (`e9892b9`, on main). `Remediation_Tracker.md` updated 🟡→✅ (§3 table, §2 round-plan + next-pointer, §4 detail).
- **NEXT actionable = RM-08** — Separation-of-Duties on Parties approval (Producer + membership four-eyes; K J-10; P2 demo, size M, 🔴). **No new ADR** — foundation is `decisions/2026-06-17-approval-separation-of-duties-role-gated`. Workflow: `/spec-to-change` → `APPROVED` → `./ralph.sh`. **RM-05** (capacity seat-set) ⏸️ (Module A `qty`); lower = P3/P4 backlog (RM-12/13/14/19-23). Giovanni confirms the pick.

## Blockers & Decisions Needed
- **✅ Repo fully synced** — `origin/main` @ `52b9983` (close ritual + follow-ups CONTEXT.md/tracker/docblock/CLAUDE.md + the RM-03 tracker reconcile, all pushed). **Note:** the auto-mode classifier blocks the assistant's own `git push` — Giovanni runs `! git push`; any bookkeeping commit made after a push trails until the next one (rides with the next change's close).
- **CONTEXT.md + CLAUDE.md reconciliation ✅ DONE & committed** — CONTEXT.md Hold-type glossary + 7 spots to eight-value (no "six" left); CLAUDE.md l.67 `(6 types)`→`(8 types)` (Giovanni-authorized commit of his hard-Protected edit).
- **F4 ✅ DONE** — `Remediation_Tracker.md` l.30 + l.92 mark F4 resolved 2026-07-06.
- **Stale docblock ✅ DONE** — `CustomerHoldsTable` docblock 4→6 operator-liftable types (Pint green).

## Open Patterns
- **Spec-reconciliation change:** code shipped ahead of truth-spec → MODIFIED-only delta reproducing each requirement VERBATIM + surgical eight-value tokens; faithfulness = word-diff delta vs live + `openspec validate --strict` + independent semantic pass; `openspec archive` applies it into `openspec/specs/**`.
- **Half-fixed Protected file:** a pre-edit can swap a count token yet leave the body contradicting it — word-diff `git diff HEAD` + re-grep the working tree; report *current* per-line status, never the authored flag list.
- **Close ritual with uncommitted Protected WIP:** stage the archive with targeted `git add openspec/ hot.md log.md`, **NEVER `git add -A`** (would sweep Giovanni's un-staged `CLAUDE.md`/`CONTEXT.md`).
