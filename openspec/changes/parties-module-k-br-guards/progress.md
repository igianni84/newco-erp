# Progress — parties-module-k-br-guards

## Codebase Patterns
(consolidated reusable patterns — read first each iteration)

- **LIVE-canon grounding recipe (mini-ADRs 1.1–1.3).** The three canon DECs adopted here (MVP-DEC-009/010/022) are ABSENT from our frozen `spec/` (pin `4f48277` stops at MVP-DEC-007). Verify against LIVE canon, read-only: `git -C ../documentation fetch cmless main` (clone at `../documentation`, remote `cmless` → `github.com/c-mless/documentation`), then `git show cmless/main:handoff/04-decisions/MVP_Decisions_Register_v0.1.md` and `…/handoff/03-acceptance/Module_K_Acceptance_v0.3-MVP.md`. cmless/main is pinned at `360df0b` this session. **Never modify the clone.** Register-line map: MVP-DEC-007 = `:132`, MVP-DEC-008 = `:133`, MVP-DEC-009 = `:134`, MVP-DEC-010 = `:135`, MVP-DEC-011 = `:136` … MVP-DEC-015 = `:140` (one row per line, sequential). Module_K_Acceptance: AC-K-BR-Agreement-1 = `:192`, …-2 = `:193`, …-3 = `:194`, …-4 = `:195`.
- **Number-collision trap (canon MVP-DEC-NNN ≠ greenfield DEC-NNN).** Every canon-adoption ADR here must banner the collision: greenfield `DEC-009` = *"Crurated as Discovery supplier"* (`spec/04-decisions/decisions.md:71`, superseded `:139`), unrelated to MVP-DEC-009. Always use the full `MVP-DEC-NNN` token. Same trap flagged by RM-03 (DEC-016) and RM-06 (DEC-019).

---

## [2026-07-07 15:05] — 1.1 Mini-ADR MVP-DEC-009 (Agreement-4: per-Club scope requires active Club)
- **Recovered a crashed iteration.** The prior instance hit the session limit (`.last-output`: *"You've hit your session limit · resets 3pm"*) mid-1.1: it had written the 71-line ADR but left it **untracked** — never committed, no `decisions/INDEX.md` row, no memory update. This iteration verified + completed it.
- **What was implemented:** completed task 1.1 — the ADR `decisions/2026-07-07-adopt-mvp-dec-009-agreement-club-active-scope.md` (already on disk, verified faithful) + the `decisions/INDEX.md` row (the missing acceptance criterion).
- **Verification (re-grounded, not assumed):**
  - Re-fetched LIVE canon read-only (`git -C ../documentation fetch cmless main`); `cmless/main` = `360df0b` — exactly the ADR's pin.
  - `git show cmless/main:…MVP_Decisions_Register…:134` confirms MVP-DEC-009 verbatim (new per-Club scope requires `active` Club; `sunset`/`closed` not selectable; Producer-wide ungated; supersession inherits scope + exempt; *"erratum of omission — editorial completion of DEC-070, No new DEC"*; ✅ 2026-06-18). Line map exact: `:132`=MVP-DEC-007, `:134`=MVP-DEC-009.
  - `…Module_K_Acceptance…:195` confirms AC-K-BR-Agreement-4's four paths verbatim (reject sunset, reject closed, admit active, admit since-sunset supersession). `:192`=Agreement-1.
  - Frozen spec cross-check: `grep` confirms **BR-K-Agreement-1/2/3 only** (Agreement-4 absent) → the "erratum of omission" framing is correct; greenfield `DEC-009:71` = *Crurated as Discovery supplier* (real, unrelated collision); `spec.lock` `source_sha 4f48277` matches.
- **Files changed:** `decisions/2026-07-07-adopt-mvp-dec-009-agreement-club-active-scope.md` (from prior iteration, now committed) · `decisions/INDEX.md` (+1 row, top of table) · `tasks.md` (1.1 → `[x]`) · `progress.md`.
- **Quality loop:** green — doc-only task (no runtime surface → no code test; the acceptance is ADR-present + INDEX-row). `openspec validate parties-module-k-br-guards --strict` → **valid**. No PHP touched → format/test/typecheck/lint not triggered by this task.
- **Learnings for future iterations:**
  - The ADR body is a solid template for 1.2 (MVP-DEC-010) + 1.3 (MVP-DEC-022) — same collision-banner + LIVE-canon-References structure. Grounding recipe + line map now in Codebase Patterns above.
  - When recovering a crashed iteration: check `git status` for untracked artifacts BEFORE re-doing work; the file may already exist and only need verification + the missing sidecar updates.
  - Two orthogonal ProducerAgreement guards live in this change and must not be conflated: Agreement-4 (1.1/3.2) = **creation-time** Club-active; RM-20 (3.3) = **activation-time** cross-shape mutual-exclusion. Different chokepoints, different exceptions.
---
