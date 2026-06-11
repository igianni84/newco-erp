# Workflow Orchestration

## 1. Plan Mode Default
- Enter plan mode for ANY non-trivial task (3+ steps or architectural decisions)
- If something goes sideways, STOP and re-plan immediately — don't keep pushing
- Use plan mode for verification steps, not just building
- In loop mode (RALPH.md), the change artifacts ARE the plan — re-read them instead

## 2. Subagent Strategy
- Use subagents to keep the main context window clean: research, exploration, parallel analysis
- The module PRDs in `spec/02-prd/` are huge (~100K chars each) — read them via subagents and pull back only the relevant sections
- One task per subagent for focused execution

## 3. Self-Improvement Loop
- After ANY correction from the user: update `lessons.md` (root) with the Mistake → Correction → Rule pattern
- Review `lessons.md` at session start
- For accumulated domain knowledge, see Knowledge System below

## 4. Verification Before Done
- Never invent variable, function, class, table, route, or event names — verify existence before use
- Never mark a task complete without proving it works (tests, logs, demonstrated behavior)
- Ask yourself: "Would a staff senior engineer approve this?"
- Claims about the spec must cite file + section; claims about code must cite file paths

## 5. Demand Elegance (Balanced)
- For non-trivial changes: pause and ask "is there a more elegant way?"
- If a fix feels hacky: "Knowing everything I know now, implement the elegant solution"
- Skip this for simple, obvious fixes — don't over-engineer

## 6. Autonomous Bug Fixing
- When a bug is reported, do NOT start with a fix: write a failing test that reproduces it first (`diagnose` skill: repro loop → minimise → hypothesise → fix → regression-test)
- Point at logs, errors, failing tests — then resolve them

## Core Principles
- **Simplicity First:** every change as simple as possible, minimal code impact
- **No Laziness:** root causes, no temporary fixes, senior standards
- **Spec Fidelity:** when in doubt, the spec (`spec/`) and the truth specs (`openspec/specs/`) win over assumptions

---

# OpenSpec Workflow (work state machine)

- `openspec/specs/` — how the system behaves TODAY. Read the relevant capability spec before touching a module. Never hand-edit: it changes only when a change is archived.
- `openspec/changes/<name>/` — in-flight work: `proposal.md`, delta `specs/`, `design.md`, `tasks.md`, `progress.md`, optional `APPROVED` marker (human-created only).
- Lifecycle: `/spec-to-change` (author) → human review → `APPROVED` → `./ralph.sh` (implement) → human review/merge → `/opsx:verify` → `openspec archive <name> --yes`.
- Useful CLI: `openspec list`, `openspec status --change X --json`, `openspec validate X --strict`, `openspec show X`.
- Interactive implementation of a single task is fine (follow RALPH.md discipline manually); keep one-task-per-session granularity.

---

# Memory Systems

## Persistence Taxonomy

| Mechanism | Location | Scope | Purpose |
|---|---|---|---|
| **Hot Cache** | `hot.md` (root) | Repo state | ~500-word digest, OVERWRITTEN (never appended) on every significant operation. Injected at session start + post-compaction by hooks. Sections: Last Updated / Build & Quality Status / Active Change & Next Task / Blockers & Decisions Needed / Open Patterns. |
| **Operations Log** | `log.md` (root) | Chronology | Append-only ledger: `## [YYYY-MM-DD HH:MM] {op} \| {target} \| {outcome}`. Rotate past ~5000 lines to `log-archive-YYYY.md`. |
| **Lessons** | `lessons.md` (root) | Corrections | Mistake → Correction → Rule. Updated after any correction; read at session start. |
| **Knowledge System** | `knowledge/{domain}/` | Domain insights | Promotion lifecycle: observations → hypotheses (3 confirmations) → rules. |
| **Decision Journal** | `decisions/` | Architecture | ADRs with supersede semantics. This is the repo's `docs/adr/` equivalent. |
| **Team Memory** | `.claude/memory/` | Conventions | Workflow conventions, external references, project state. Committed, shared. |
| **Per-change progress** | `openspec/changes/<x>/progress.md` | One change | Iteration narrative + `## Codebase Patterns` consolidated at top. Travels to archive with the change. |
| **Auto Memory** | `~/.claude/projects/.../memory/` | Personal | The assistant's private notes about the user. Not shared, system-managed. |

### When to use which
- User corrects your approach → `lessons.md`
- Domain pattern discovered → `knowledge/{domain}/` (start as hypothesis unless certain)
- Architectural choice between alternatives → `decisions/` + update `decisions/INDEX.md`
- Workflow convention or external reference → `.claude/memory/` + `MEMORY.md` index
- Iteration-specific learnings → the change's `progress.md` (promote the durable ones)

## Knowledge System
- Before a task: review `knowledge/{domain}/rules.md` (apply by default) and `hypotheses.md` (test when possible) for relevant domains
- After a task: extract insights into the right domain folder; update `knowledge/INDEX.md` when creating a domain
- Promotion: hypothesis confirmed **3×** (dated confirmations) → move to `rules.md`. Rule contradicted → demote back to hypothesis
- Suggested domains: `architecture`, `data-model`, `laravel`, `filament`, `testing`, `integrations`, plus one per spec module as work begins (`module-a`, `module-b`, …)

## Decision Journal
File: `decisions/YYYY-MM-DD-{topic}.md`

```markdown
---
type: decision
status: active            # active | superseded
date: YYYY-MM-DD
supersedes: (optional)
superseded-by: (optional)
---
## Decision: {what}
## Context: {why this came up}
## Alternatives considered: {what else was on the table}
## Reasoning: {why this option won}
## Trade-offs accepted: {what you gave up}
## References: {spec sections, links, related ADRs}
```

- Before any similar decision: search `decisions/` first; follow precedent unless new information invalidates it
- Never edit a decided ADR's substance: supersede it (set both `supersedes`/`superseded-by` links) — old reasoning is historically valuable
- Always update `decisions/INDEX.md`

## Hot Cache & Log discipline
- Any session (interactive or loop) that materially changes the repo MUST end with: `log.md` appended + `hot.md` overwritten. The Stop hook reminds you if you forget.
- `hot.md` is a cache, not a journal: rewrite it from current state, don't accumulate history in it.
