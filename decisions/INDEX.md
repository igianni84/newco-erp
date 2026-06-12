# Decisions Index

> Architectural Decision Records. Source of truth for "why is it like this". Supersede, never edit in place. This directory is the repo's ADR home (`docs/adr/` equivalent).

| Date | Decision | Status | File |
|---|---|---|---|
| 2026-06-12 | Event substrate + audit/financial event store (closes BOTH gates): transactional outbox on app DB — append-only `domain_events` log IS outbox + 10-year audit + financial store; `audit_records` for operator before/after; per-consumer delivery ledger (inline at launch, queued post queue-ADR); trigger+REVOKE immutability; PII-free event payloads; no partitioning at launch | active | [2026-06-12-event-substrate-and-audit-store.md](2026-06-12-event-substrate-and-audit-store.md) |
| 2026-06-12 | Production DB engine: PostgreSQL (floor 17, managed EU; C.UTF-8, zero extensions; Postgres-truthful migrations + pgsql CI lane) | active | [2026-06-12-production-db-engine.md](2026-06-12-production-db-engine.md) |
| 2026-06-11 | Stack versions pinned (Laravel 13.x + Filament 5.x) + Filament AI tooling (Boost) | active | [2026-06-11-stack-versions-and-filament-ai-tooling.md](2026-06-11-stack-versions-and-filament-ai-tooling.md) |
| 2026-06-11 | Tech stack: Laravel + Filament (with open sub-decisions) | superseded | [2026-06-11-tech-stack-laravel-filament.md](2026-06-11-tech-stack-laravel-filament.md) |
| 2026-06-11 | Architecture: modular monolith, events as module API | active | [2026-06-11-modular-monolith-architecture.md](2026-06-11-modular-monolith-architecture.md) |
| 2026-06-11 | Dev methodology: ralph loop × OpenSpec × SecondBrain memory | active | [2026-06-11-dev-methodology-ralph-openspec.md](2026-06-11-dev-methodology-ralph-openspec.md) |

## Open decisions (ADR required before the gate — see root CLAUDE.md)

Identity/auth · queue driver (requirements pre-set by the 2026-06-12 substrate ADR: at-least-once + per-job delay; gate = first `queued` consumer, expected F4–F6) · object storage · hosting (EU residency; founder direction registered 2026-06-12: probably hyperscaler EU region — non-binding) · consumer/producer frontend stack (founder direction: TanStack SPA — formal ADR at the Module S storefront gate).
