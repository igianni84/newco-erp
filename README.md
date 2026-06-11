# NewCo ERP

Implementation repository for **NewCo ERP v0.3-MVP** — a producer-club aggregator for fine wine (passive consignment, B2C, NewCo as Seller of Record). Nine modules, built entirely with Claude Code through autonomous, spec-driven iterations.

## The One Rule

`spec/` is the **immutable v0.3-MVP launch baseline** (copied from the handoff repo, commit `e55dfc8`, 2026-06-11). Everything we build traces back to it. Never edit it. The superseded v1.1 reference is *not* in this repo and must never be used as a build source. Read order: [`spec/README.md`](spec/README.md).

## Operating System

This repo runs on a fusion of four methodologies: [Ralph](https://github.com/snarktank/ralph) autonomous loops (via [ralph-potenziato](https://github.com/igianni84/ralph-potenziato)), [OpenSpec](https://github.com/Fission-AI/OpenSpec) spec-driven changes, [SecondBrainUltra](https://github.com/igianni84/SecondBrainUltra)-style persistent memory, and [Matt Pocock's engineering skills](https://github.com/mattpocock/skills).

```
spec/  (frozen PRDs + acceptance docs)
  │
  │  /spec-to-change        ← human + Claude: interview, zero invention,
  ▼                            every requirement cites its spec source
openspec/changes/<name>/    proposal · delta specs · design · tasks
  │
  │  human review  →  touch openspec/changes/<name>/APPROVED
  ▼
./ralph.sh --change <name>  ← autonomous loop: fresh context per iteration,
  │                            ONE task per iteration, tests mandatory,
  │                            quality loop with circuit breaker
  ▼
<promise>CHANGE_COMPLETE</promise>
  │
  │  human: review branch → merge → /opsx:verify → openspec archive
  ▼
openspec/specs/             ← accumulated truth: living documentation of
                               how the system actually behaves today
```

**Division of labor:** OpenSpec is the state machine (what to do, what's done, what's true). `ralph.sh` is a thin scheduler. `RALPH.md` is the per-iteration discipline. The memory files make iteration 300 smarter than iteration 3.

## Quickstart

```bash
# 0. Prerequisites: claude CLI, jq, php >= 8.4, composer, node >= 20, openspec
npm install -g @fission-ai/openspec@latest

# 1. Prepare a change (interactive, in Claude Code)
#    /spec-to-change <target>   e.g. "Module 0 — product spine, first slice"

# 2. Review the change folder, then approve it
touch openspec/changes/<name>/APPROVED

# 3. Run the loop (15 = max iterations)
./ralph.sh --change <name> 15

# 4. When the loop reports CHANGE_COMPLETE:
#    review the ralph/<name> branch → merge to main →
#    /opsx:verify (semantic check) → openspec archive <name> --yes
```

### Monitoring a running loop

```bash
grep -c '^\- \[x\]' openspec/changes/<name>/tasks.md   # tasks done
cat openspec/changes/<name>/progress.md                 # iteration narrative
tail -20 log.md                                         # global operations ledger
git log --oneline -10                                   # one commit per task
cat hot.md                                              # current state cache
```

Exit codes of `ralph.sh`: `0` change complete · `1` max iterations reached · `3` agent requested human help · `4` stalled (no progress for 3 iterations).

## Layout

| Path | Role |
|---|---|
| `spec/` | **Immutable** v0.3-MVP handoff: business model, 9 module PRDs, acceptance docs, decisions register, build workplan |
| `openspec/specs/` | Current behavioral truth (grows as changes are archived) — never hand-edited |
| `openspec/changes/` | In-flight work: one folder per change (proposal, delta specs, design, tasks, progress) |
| `CLAUDE.md` | Project rules: tech stack, **Key Invariants** (compliance floor), terminology, quality commands |
| `RALPH.md` | Per-iteration instructions for the autonomous agent |
| `ralph.sh` | The loop runner |
| `CONTEXT.md` | Ubiquitous-language glossary (grows via grill-with-docs) |
| `hot.md` | ~500-word state cache, overwritten each iteration, hook-injected at session start |
| `log.md` | Append-only operations ledger |
| `lessons.md` | Mistake → correction → rule (read at session start) |
| `knowledge/` | Domain insights with promotion lifecycle (observation → hypothesis ×3 → rule) |
| `decisions/` | Architectural decision records (ADR home — also when skills mention `docs/adr/`) |
| `docs/` | Generated developer documentation |
| `.claude/` | Hooks (hot-cache injection, git guardrails), skills, team memory |

## Skills

| Skill | Use |
|---|---|
| `/spec-to-change` | Convert a slice of `spec/` into a validated OpenSpec change (the bridge) |
| `/opsx:*` | OpenSpec lifecycle (propose, apply, verify, archive…) |
| `grill-with-docs` | Stress-test a plan against CONTEXT.md + decisions/, update docs inline |
| `tdd` | Red-green-refactor in vertical slices (the loop's testing discipline) |
| `diagnose` | Debugging: build a repro loop first, never guess |
| `zoom-out` | Go up one abstraction level before touching unfamiliar modules |
| `improve-codebase-architecture` | Periodic entropy control (run as its own change) |

## Credits

Methodology fused from: [snarktank/ralph](https://github.com/snarktank/ralph) (Geoffrey Huntley's pattern), [igianni84/ralph-potenziato](https://github.com/igianni84/ralph-potenziato), [igianni84/SecondBrainUltra](https://github.com/igianni84/SecondBrainUltra), [Fission-AI/OpenSpec](https://github.com/Fission-AI/OpenSpec), [mattpocock/skills](https://github.com/mattpocock/skills).
