# Documentation Index

> Generated developer documentation. Grows as changes are implemented — every change that introduces a developer-facing surface adds/updates a doc here and a row below.

## Where to read what

| Question | Source |
|---|---|
| How do I operate this machine, step by step? | `GUIDE.md` (root) — the operator's playbook (in Italian) |
| What must the system do? (requirements) | `spec/` — immutable v0.3-MVP handoff (start: `spec/README.md`) |
| How does the system behave **today**? | `openspec/specs/` — accumulated truth, merged at each change archive |
| Why is it built this way? | `decisions/` — ADRs (index: `decisions/INDEX.md`) |
| What do domain terms mean? | `CONTEXT.md` — ubiquitous-language glossary |
| What happened recently? | `log.md` (ledger) · `hot.md` (state cache) · `openspec/changes/*/progress.md` |
| How do I develop here? | `docs/development.md` — created by the bootstrap change |

## Developer docs

| Doc | Content | Added by change |
|---|---|---|
| [development.md](development.md) | Setup, quality commands, CI, ralph loop usage, AI tooling, installed-version snapshot | bootstrap-laravel-app (task 3.4) |
| [module-template.md](module-template.md) | The falsariga every F2+ module change follows: the nine modules, canonical layout, public surface & boundary law, provider / operator-surface / persistence / test conventions, naming cascade | foundations-modules-skeleton (task 3.1) |
