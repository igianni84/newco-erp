# Decisions Index

> Architectural Decision Records. Source of truth for "why is it like this". Supersede, never edit in place. This directory is the repo's ADR home (`docs/adr/` equivalent).

| Date | Decision | Status | File |
|---|---|---|---|
| 2026-06-11 | Stack versions pinned (Laravel 13.x + Filament 5.x) + Filament AI tooling (Boost) | active | [2026-06-11-stack-versions-and-filament-ai-tooling.md](2026-06-11-stack-versions-and-filament-ai-tooling.md) |
| 2026-06-11 | Tech stack: Laravel + Filament (with open sub-decisions) | superseded | [2026-06-11-tech-stack-laravel-filament.md](2026-06-11-tech-stack-laravel-filament.md) |
| 2026-06-11 | Architecture: modular monolith, events as module API | active | [2026-06-11-modular-monolith-architecture.md](2026-06-11-modular-monolith-architecture.md) |
| 2026-06-11 | Dev methodology: ralph loop × OpenSpec × SecondBrain memory | active | [2026-06-11-dev-methodology-ralph-openspec.md](2026-06-11-dev-methodology-ralph-openspec.md) |

## Open decisions (ADR required before the gate — see root CLAUDE.md)

Production DB engine · identity/auth · queue driver · domain-event substrate · audit/event store · object storage · hosting (EU residency) · consumer/producer frontend stack (founder direction: TanStack SPA — formal ADR at the Module S storefront gate).
