---
name: Where things live
description: Map of every state/memory/work file in this repo and who writes it
type: reference
---

| File / dir | What | Written by | Cadence |
|---|---|---|---|
| `spec/` | v0.3-MVP build authority: a vendored mirror of canon `c-mless/documentation:handoff/`, pinned in `spec.lock` | `scripts/sync-spec.sh` only (never by hand) | deliberate refresh, gated by `scripts/spec-staleness.sh` |
| `spec.lock` | The pin: which canon commit `spec/` mirrors | `scripts/sync-spec.sh` | with every refresh |
| `openspec/specs/` | Current behavioral truth | `openspec archive` only | per archived change |
| `openspec/changes/<x>/` | In-flight change artifacts + `progress.md` + `APPROVED` (human only) | /spec-to-change + ralph iterations | continuous |
| `hot.md` | ~500-word state cache (overwrite, never append) | every ralph iteration + interactive sessions | every significant op |
| `log.md` | Append-only operations ledger | same | every significant op |
| `lessons.md` | Mistake→Correction→Rule | agent after corrections | event-driven |
| `knowledge/{domain}/` | knowledge / hypotheses (N/3) / rules | agent | event-driven |
| `decisions/` | ADRs (supersede semantics) + INDEX | human+agent sessions | per decision |
| `CONTEXT.md` | Ubiquitous-language glossary | grill-with-docs + sessions | per resolved term |
| `docs/` | Generated developer docs + INDEX | changes that produce docs | per change |
| `RALPH.md` / `ralph.sh` / `CLAUDE.md` / `.claude/**` | The machine itself | humans only (protected) | rarely |
