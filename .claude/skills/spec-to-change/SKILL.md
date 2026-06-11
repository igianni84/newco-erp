---
name: spec-to-change
description: Convert a slice of the frozen NewCo ERP spec (spec/) into a validated OpenSpec change ready for the ralph loop. Use when the user asks to prepare/convert/draft a change, kick off a module or build-workplan phase, or says "spec to change" / "prepara il change" / "prossimo change". Interview-driven, zero invention, every requirement cites its spec source.
---

# Spec → OpenSpec Change

You convert a scoped slice of `spec/` (the immutable v0.3-MVP handoff) into one OpenSpec change under `openspec/changes/<name>/`. The output must be implementable by autonomous one-task-per-iteration loops with NO access to this conversation — everything the implementer needs goes into the artifacts.

## Inputs

The user names a target: a build-workplan phase, a module, or a capability slice (e.g. "Module 0 — product spine, first slice"). If ambiguous, ask before reading everything.

## Process

### 1. Ground yourself (use subagents for the big reads)
- `spec/05-release/Build_Workplan_v0.3-MVP.md` — sequencing + where this slice sits
- The relevant module PRD in `spec/02-prd/` (these are ~100K chars: read via subagents, pull back the sections for this slice)
- The matching acceptance doc in `spec/03-acceptance/`
- `spec/04-decisions/` register entries touching the slice
- `openspec/specs/` — what's already true (don't re-add existing requirements; use MODIFIED)
- `decisions/` ADRs + `CONTEXT.md` + `knowledge/*/rules.md`
- Check the open-ADR-gates table in root `CLAUDE.md`: if this slice steps through an undecided gate (DB engine, auth, event substrate…), STOP and tell the user which ADR must be written first.

### 2. Scope the slice
- A change should yield roughly **6–20 tasks, each completable in ONE loop iteration** (describable in 2–3 sentences). Modules are far bigger than one change: propose a split into sequential changes (name them), get agreement, then author only the first.
- Respect cross-module dependency order from the Build Workplan; respect R1–R4 reconciliations.

### 3. Interview (only what the spec doesn't answer)
Follow `.claude/skills/references/interview-methodology.md`: one question at a time, propose-don't-just-ask, zero tolerance for ambiguity on invariant-adjacent behavior. Never ask what the spec already answers — cite it instead.

### 4. Author the artifacts
Create via `openspec new change <name>` if the folder doesn't exist. For each artifact run `openspec instructions <artifact> --change <name> --json` and follow the returned template exactly. For long artifacts follow `.claude/skills/references/incremental-writing-protocol.md` (never one giant Write).

- **proposal.md** — Why / What Changes / Capabilities (new vs modified) / Impact. State the slice boundary explicitly (what is deliberately NOT in this change and which future change gets it).
- **specs/<capability>/spec.md** (deltas) — `## ADDED|MODIFIED|REMOVED Requirements`; every requirement: RFC-2119 text (SHALL/MUST) + ≥1 `#### Scenario:` in WHEN/THEN bullets. Derive scenarios from `spec/03-acceptance/` wherever possible. **Provenance is mandatory**: end each requirement with `_Source: spec/<file> § <section>_` (or an ADR reference). Never invent a requirement.
- **design.md** — Context / Goals & Non-Goals / Decisions (reference `decisions/` ADRs; if a new architectural decision emerges, write the ADR separately and link it) / Risks & trade-offs, including the landmines an autonomous agent must not step on.
- **tasks.md** — numbered groups, dependency-ordered (schema → domain → services → endpoints/UI → docs), each task with inline acceptance bullets including test expectations per `.claude/skills/references/acceptance-criteria.md` (tests are NEVER optional) and concrete test hints (what to assert, not just "tests pass").

### 5. Validate & cross-check
- `openspec validate <name> --strict` must pass — fix until green.
- Traceability: every PRD requirement in the slice maps to ≥1 delta requirement and ≥1 task; list any deliberate gaps in proposal.md under Impact.
- Terminology: all names match `CONTEXT.md` / root CLAUDE.md canonical terms.

### 6. Present & gate
Present a compact summary: slice boundary, requirement count, task list, open risks. Then ask for approval explicitly.
- **Only on explicit user approval**: `touch openspec/changes/<name>/APPROVED`, append to `log.md`, update `hot.md`, and suggest: `./ralph.sh --change <name> <iterations>`.
- If the user wants edits, iterate — never self-approve.

## What NOT to do
- Do not write implementation code.
- Do not touch `spec/**` or `openspec/specs/**`.
- Do not pad scope beyond the slice ("while we're here…" goes to a future change).
- Do not produce requirements without spec/ADR provenance.
- Do not create the APPROVED file without the user's explicit yes in this conversation.
