---
type: decision
status: active
date: 2026-06-11
---

## Decision: Laravel (latest stable) + Filament as the core stack

PHP ≥ 8.4, Laravel latest stable at bootstrap time, Filament (latest stable) for the operator panel. Pest for tests, Larastan for static analysis, Pint for format/lint. SQLite for dev/test. Exact installed versions get recorded in `docs/development.md` by the bootstrap change.

## Context

The spec deliberately does not prescribe a stack (DEC-073): the dev team decides in Phase 1. Constraints from the spec: EU data residency; mandatory patterns (dual-currency dual-record, i18n ×6 locales, feature flags, 10-year immutable audit, `actor_role` envelope); operations are manual-first at launch (D24), making the Admin Panel a first-class build target.

## Alternatives considered

- **Node/NestJS + React admin** — strong typing end-to-end, but no Filament-class admin accelerator; the Admin Panel is ~the largest operational surface at launch.
- **Django + admin** — admin included but weaker ecosystem fit for the team and for Filament-style rich operator consoles.
- **Symfony** — solid, but Laravel's ecosystem (Horizon, Pennant, Cashier-style packages, Pest) covers more of the spec's cross-cutting needs out of the box.

## Reasoning

1. The launch system is operator-console-heavy (manual-first D24): Filament turns each module's operator surface into configuration-grade work instead of custom UI.
2. Team familiarity (Giovanni's tooling and templates assume Laravel/Filament workflows).
3. Ecosystem match: Pennant (feature flags → D12 NFT decoupling), localization (6 locales), queues/Horizon, Pest/Larastan/Pint quality chain consumed by the ralph quality loop.
4. AI-agent friendliness: enormous training corpus + strong conventions = fewer hallucinated APIs in autonomous iterations.

## Trade-offs accepted

- PHP single-runtime monolith over polyglot flexibility.
- Filament couples the operator UI to Livewire; the consumer storefront stack stays an open decision.
- "Latest stable at bootstrap" trades version pinning for freshness; versions are frozen in composer.lock from bootstrap onward.

## Open sub-decisions (each needs its own ADR before its gate)

| Sub-decision | Gate |
|---|---|
| Production DB engine (PostgreSQL vs MySQL) | first Module 0 migration |
| Identity/auth (first-party vs IdP; customer vs operator) | Module K |
| Queue driver (Redis+Horizon vs database) | first async workflow |
| Domain-event substrate (in-process + outbox vs broker) | first cross-module event |
| Audit/financial event store (immutability mechanism) | first financial event |
| Object storage (invoices, statements) | INV1 issuance |
| Hosting (EU data residency) | staging environment |
| Consumer storefront (Livewire vs Inertia) | Module S storefront |

## References

spec/04-decisions (DEC-073) · spec/02-prd/Architecture_v0.3-MVP.md · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md · [[2026-06-11-modular-monolith-architecture]]
