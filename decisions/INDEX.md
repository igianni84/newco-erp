# Decisions Index

> Architectural Decision Records. Source of truth for "why is it like this". Supersede, never edit in place. This directory is the repo's ADR home (`docs/adr/` equivalent).

| Date | Decision | Status | File |
|---|---|---|---|
| 2026-06-14 | Catalog category-neutral representation: neutral core entities + per-type attribute tables (WINE `appellation` a real column; identity dedup at creation; a new Product Type adds its own attribute table — core/event-contract untouched). Resolves DEC-073's delegated representation choice for the product spine | active | [2026-06-14-catalog-category-neutral-representation.md](2026-06-14-catalog-category-neutral-representation.md) |
| 2026-06-12 | Event substrate + audit/financial event store (closes BOTH gates): transactional outbox on app DB — append-only `domain_events` log IS outbox + 10-year audit + financial store; `audit_records` for operator before/after; per-consumer delivery ledger (inline at launch, queued post queue-ADR); trigger+REVOKE immutability; PII-free event payloads; no partitioning at launch | active | [2026-06-12-event-substrate-and-audit-store.md](2026-06-12-event-substrate-and-audit-store.md) |
| 2026-06-12 | Production DB engine: PostgreSQL (floor 17, managed EU; C.UTF-8, zero extensions; Postgres-truthful migrations + pgsql CI lane) | active | [2026-06-12-production-db-engine.md](2026-06-12-production-db-engine.md) |
| 2026-06-11 | Stack versions pinned (Laravel 13.x + Filament 5.x) + Filament AI tooling (Boost) | active | [2026-06-11-stack-versions-and-filament-ai-tooling.md](2026-06-11-stack-versions-and-filament-ai-tooling.md) |
| 2026-06-11 | Tech stack: Laravel + Filament (with open sub-decisions) | superseded | [2026-06-11-tech-stack-laravel-filament.md](2026-06-11-tech-stack-laravel-filament.md) |
| 2026-06-11 | Architecture: modular monolith, events as module API | active | [2026-06-11-modular-monolith-architecture.md](2026-06-11-modular-monolith-architecture.md) |
| 2026-06-11 | Dev methodology: ralph loop × OpenSpec × SecondBrain memory | active | [2026-06-11-dev-methodology-ralph-openspec.md](2026-06-11-dev-methodology-ralph-openspec.md) |

## Open decisions (ADR required before the gate — see root CLAUDE.md)

**Stack gates** — mirror root CLAUDE.md's "Open stack decisions" table (that table is the authoritative, protected copy; this line tracks the same gates here for the ADR index):

Identity/auth · queue driver (requirements pre-set by the 2026-06-12 substrate ADR: at-least-once + per-job delay; gate = first `queued` consumer, expected F4–F6) · object storage · hosting (EU residency; founder direction registered 2026-06-12: probably hyperscaler EU region — non-binding) · consumer/producer frontend stack (founder direction: TanStack SPA — formal ADR at the Module S storefront gate).

**Operational / security gates** — cross-cutting; surfaced by the 2026-06-13 substrate audit (C15) and tracked only here, since root CLAUDE.md's stack-gate table is protected and stack-scoped (this registry is their single home):

- **Secrets management** — gate = staging environment / first stored production credential (DB, object storage, payment processor, Logilize). Covers credential storage, rotation, and CI/CD injection.
- **Observability** — logging / metrics / alerting shape. Gate = staging environment / first production alerting need. The substrate-hardening C3 work left the dead-letter (`Log::warning`/`Log::error`) and sweep-summary (`Log::info`) channel as a deliberate placeholder pending this gate.
- **PCI boundary** — gate = the first real payment-processor charge/capture integration (Module S order completion / Module E settlement). Fixes the cardholder-data boundary and SAQ scope (NewCo is Seller of Record, B2C).
- **Architectural security review** — gate = before the first customer-facing release / production cutover. A formal threat model + review across the event/audit substrate, the compliance gates (KYC / sanctions / Hold), and the module boundaries.
