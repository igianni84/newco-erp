# Ralph Agent Instructions (OpenSpec edition)

You are an autonomous coding agent working on the NewCo ERP. You run inside a loop: each iteration is a fresh instance of you, with no memory of previous iterations beyond what is written in the repository. Your job this iteration: complete exactly ONE task of the active OpenSpec change, leave the repo green, and persist what you learned.

## Directive Hierarchy

You MUST follow all directives from the auto-loaded `CLAUDE.md` files in addition to these instructions. Priority order on conflict:

1. **`./CLAUDE.md`** (root) — project rules, **Key Invariants (absolute, NEVER violate)**, tech stack, quality commands, protected files.
2. **`.claude/CLAUDE.md`** — workflow orchestration, memory systems, knowledge system, decision journal.
3. **`RALPH.md`** (this file) — per-iteration behavior.

If a CLAUDE.md directive conflicts with a RALPH.md instruction, the CLAUDE.md directive wins.

## Run Context

The loop prepends a `## Current Run Context` block with: the change name, the paths of `tasks.md`, `progress.md` and the last-output file, the iteration number, and current progress. If no Run Context block is present (manual invocation), determine the active change yourself: the alphabetically-first directory under `openspec/changes/` (excluding `archive/`) that contains an `APPROVED` file and at least one unchecked task.

## Your Task

1. **Load state** (read, don't skim):
   - `hot.md` — if it wasn't already injected at session start.
   - The change's `progress.md` — read the `## Codebase Patterns` section at the top first.
   - The last-output file, if it exists — the previous iteration may have crashed mid-task; diagnose from it.
   - Any `> ⚠ FAILED` notes under tasks in `tasks.md`.
2. **Branch check:** you must be on `ralph/<change-name>`. If not, check it out (create from the current branch if missing).
3. **Pick the task:** the FIRST unchecked `- [ ]` task in `tasks.md` (they are dependency-ordered). Work on ONE task only.
4. **Load the change context:** the change's `proposal.md`, `design.md`, its delta specs under `specs/`, and the `spec/` sections they cite. For module work, also read the relevant `openspec/specs/` truth specs and `knowledge/{domain}/rules.md`. Use the `zoom-out` skill discipline before touching unfamiliar modules. Verify every name (class, table, route, event) exists before using it — never invent APIs.
5. **Implement** that single task following the **Quality Loop** below.
6. **Persist memory** (see "What to Update").
7. **Commit** with message: `feat(<change-name>): <task-id> <task title>` (e.g. `feat(bootstrap-laravel-app): 2.3 larastan configured`). Include the flipped checkbox and progress.md in the same commit.

## Quality Loop (MANDATORY)

Do NOT commit until this loop is green.

### a. Implement
Write the implementation for the task. Follow existing code patterns and the module-boundary rules in root `CLAUDE.md`. TDD is the default discipline (`.claude/skills/tdd`): red → green → refactor, vertical slices.

### b. Write Tests
Every task MUST have tests (see `.claude/skills/references/acceptance-criteria.md`). Choose by what you built:

| What you built | Test type | Guidance |
|---|---|---|
| Service / action / utility | Unit (Pest) | Isolated business logic |
| Model / data layer | Unit | Data operations, casts, constraints |
| Controller / endpoint / Livewire | Feature | Request/response behavior |
| Filament resource / page | Feature | Render + authorization + happy action |
| Job / listener / event flow | Unit or Feature | Side effects |
| Migration / schema change | Feature | Schema correct after migrate |

**Rules:**
- Use factories (create them if missing); cover the happy path + at least one failure/edge case.
- Invariant-adjacent code (money, stock, FSM transitions, compliance gates) gets explicit edge-case tests — e.g. attempt to oversell MUST fail with the documented error.
- Follow sibling test conventions for naming and structure.

### c. Run & Fix Loop
Run the Quality Commands from root `CLAUDE.md` in this order. If ANY step fails, fix and re-run FROM THAT STEP. Do not skip ahead. Skip steps whose command is not configured or whose tool is not yet installed.

```
Step 1: format       Step 2: test_filter (your test)
Step 3: test (full)  Step 4: type_check   Step 5: lint
```

**If a step fails:** read the error, diagnose the root cause (use the `diagnose` skill discipline — build a repro, don't guess), fix implementation OR test, re-run from that step.

**Circuit breaker — maximum 5 fix attempts per step.** If still failing after 5:
- Do NOT commit. Do NOT flip the checkbox.
- Append a failure note directly under the task in `tasks.md`:
  `  > ⚠ FAILED YYYY-MM-DD: <one-line summary>`
- Write detailed notes in `progress.md`: what failed, exact errors, what you tried, your best hypothesis.
- Append a `blocked` line to `log.md`, update `hot.md` (Blockers section), and end your response normally — the next iteration picks it up with context.

### d. Verify Acceptance
Before committing, walk EVERY acceptance bullet of the task one by one — each must be demonstrably true. Then run `openspec validate <change-name> --strict`; it must pass whenever the change contains delta specs. If a criterion is unmet, go back to (a).

### e. Flip the checkbox
Mark the task `- [x]` in `tasks.md` only now.

## Escalation (instead of guessing)

Output `<promise>HUMAN_NEEDED</promise>` plus a one-paragraph reason, after writing the details to `progress.md` and `log.md`, when you hit any of:
- A task that cannot be done without violating a **Key Invariant** or modifying a **Protected File**.
- A contradiction between `spec/`, the change artifacts, and/or the code.
- A missing credential/external dependency, or an open ADR gate (root CLAUDE.md "Open stack decisions") that the task steps into.
- A genuinely ambiguous requirement where both readings are plausible and consequential.

## Progress Report Format

APPEND to the change's `progress.md` (never replace; timestamp from the real clock via `date`, never estimated):

```
## [YYYY-MM-DD HH:MM] — <task-id> <task title>
- What was implemented
- Files changed
- Quality loop: green | blocked at step N (attempt count)
- **Learnings for future iterations:**
  - Patterns discovered / gotchas / useful context
---
```

If you discovered a **reusable pattern**, consolidate it in the `## Codebase Patterns` section at the TOP of `progress.md` (create it if missing). General and reusable only — not task-specific details.

## What to Update

### ALWAYS (every iteration, success or failure):
- The change's `progress.md` (format above; Codebase Patterns at top).
- `log.md` — append exactly ONE line via `scripts/memlog.sh` (it stamps the real clock — NEVER hand-write or estimate the timestamp — and caps the outcome at 280 chars): `scripts/memlog.sh ralph "<change> <task-id>" "<green|blocked|human-needed> | <n> files"`. Rotate to `log-archive-YYYY-H{1,2}.md` past ~200KB (the memory-health Stop hook warns).
- `hot.md` — OVERWRITE completely (~300–500 words, ≤550 hard ceiling — the memory-health Stop hook warns past it; it's a cache, not a journal): `Last Updated` / `Build & Quality Status` / `Active Change & Next Task` / `Blockers & Decisions Needed` / `Open Patterns`.

### WHEN APPROPRIATE:
- `lessons.md` (root) — when you discover a mistake pattern future iterations must avoid (Mistake → Correction → Rule).
- `knowledge/{domain}/` — domain insights that transcend this change; follow the hypotheses→rules promotion in `.claude/CLAUDE.md` (+ update `knowledge/INDEX.md`).
- `decisions/` — when you chose between architectural alternatives that affect future work (+ update `decisions/INDEX.md`). Never overwrite an existing ADR; supersede with a new one that references it.
- `CONTEXT.md` — when you resolved a domain term ambiguity (glossary format, no implementation details).
- `docs/` — developer-facing documentation the task produced (+ `docs/INDEX.md`).

### NEVER modify:
`CLAUDE.md` (root) · `.claude/**` · `RALPH.md` · `ralph.sh` · `spec/**` · `openspec/specs/**` (truth merges happen only at archive) · any `APPROVED` file. Do not create or modify other changes under `openspec/changes/` — only the active one. **Never `git push`** — the loop commits locally; humans push. Never force-push, hard-reset, or `git clean` (a hook blocks these).

## Stop Conditions

After completing a task, check `tasks.md`:

- **Unchecked tasks remain** → end your response normally (the next iteration continues).
- **ALL tasks are `- [x]`** → final pass: every acceptance bullet of every task re-verified at a glance, full quality commands green, `openspec validate <change> --strict` green, `hot.md` updated. Then reply with exactly:
  `<promise>CHANGE_COMPLETE</promise>`
  (Do NOT archive the change and do NOT merge the branch — humans do that after review.)
- **Hard blocker** → `<promise>HUMAN_NEEDED</promise>` + reason (see Escalation).

## Important

- ONE task per iteration. Resist scope creep — adjacent improvements go to `progress.md` as suggestions, or `knowledge/`.
- Keep changes minimal and focused; follow existing patterns.
- Broken code never gets committed. CI stays green — broken code compounds across iterations.
- Read `## Codebase Patterns` before starting. Trust the repo state over your assumptions.
