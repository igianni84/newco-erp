---
type: decision
status: active
date: 2026-06-11
---

## Decision: Autonomous spec-driven development — ralph loop × OpenSpec × SecondBrain memory × engineering skills

Work is organized as OpenSpec changes (`openspec/changes/`) authored from the frozen spec via `/spec-to-change`, gated by a human-created `APPROVED` file, implemented by `ralph.sh` (fresh-context loop, ONE task per iteration, mandatory tests, 5-attempt circuit breaker, stall detection), and archived into `openspec/specs/` which accumulates the living behavioral documentation. Cross-iteration memory: `hot.md` (hook-injected cache), `log.md` (ledger), per-change `progress.md`, `lessons.md`, `knowledge/` (hypotheses→rules), `decisions/` (ADRs). Discipline skills: tdd, diagnose, grill-with-docs, zoom-out, improve-codebase-architecture.

## Context

~52K lines of spec, 9 modules, multi-month build, executed primarily by autonomous Claude Code iterations. Requirements: documentation must be produced AS development proceeds; quality must be enterprise-grade; human attention is the scarce resource.

## Alternatives considered

- **Vanilla ralph (prd.json)** — proven loop, but duplicates state between PRD JSONs and any spec layer; no accumulated behavioral truth; weaker validation (no `validate --strict`, no delta-merge audit trail).
- **Pure OpenSpec interactive** (`/opsx:apply` by hand) — great artifacts, but no autonomous scheduler, no circuit breakers, no memory across fresh contexts.
- **GitHub Spec Kit / heavyweight SDD frameworks** — rigid phase gates; poor fit for loop-driven iteration and for a spec that already exists.

## Reasoning

1. OpenSpec externalizes exactly what a fresh-context loop needs (tasks.md checkboxes as the ledger, contextFiles as the working set, `validate --strict` as a machine gate) — the loop stays a thin bash scheduler.
2. Archive-merges make documentation a structural by-product, not a chore: `openspec/specs/` is always "how the system behaves today", with `changes/archive/` as the why-history (compliance-friendly).
3. ralph-potenziato's quality loop + circuit breaker + failure handoff are the difference between a stuck story burning a night of API spend and a self-diagnosing retry; stall detection (3 no-progress iterations → exit 4) adds the missing human pager.
4. The SecondBrain layer (hot cache injection at SessionStart/PostCompact, append-only log) solves cold-start for every fresh instance and gives the PM grep-able visibility without reading transcripts.
5. The APPROVED-file gate keeps humans deciding WHAT gets built; agents only decide HOW within an approved change.

## Trade-offs accepted

- More moving parts than vanilla ralph (OpenSpec CLI dependency, two-place task state if changes are hand-edited mid-run — mitigated: tasks.md is the single ledger).
- `--dangerously-skip-permissions` in the loop is mitigated, not eliminated, by the git-guardrails PreToolUse hook and protected-files directives.
- Token cost of re-reading artifacts each iteration — accepted as the price of context-rot immunity.

## References

README.md (operating system diagram) · RALPH.md · .claude/skills/spec-to-change/ · snarktank/ralph · igianni84/ralph-potenziato · igianni84/SecondBrainUltra · Fission-AI/OpenSpec · mattpocock/skills · [[2026-06-11-tech-stack-laravel-filament]] · [[2026-06-11-modular-monolith-architecture]]
