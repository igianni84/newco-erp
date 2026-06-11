---
type: decision
status: active
date: 2026-06-11
---

## Decision: Modular monolith with domain events as the inter-module API

One Laravel application. Nine bounded contexts under `app/Modules/` (Catalog=0, Parties=K, Allocation=A, Procurement=D, Commerce=S, Inventory=B, Fulfilment=C, Finance=E, OperatorPanel=Admin). Modules never touch each other's tables or models; they communicate exclusively through the ~120 domain events defined in the spec plus narrow read contracts (interfaces). Events are dispatched in-process at launch and append-logged for audit; the substrate (outbox vs broker) is a separate open ADR.

## Context

The spec defines 9 modules with explicit event contracts (R1–R4 corrected), a single warehouse, one ops team, and manual-first operations. We build with autonomous AI loops that need stable, enforceable boundaries.

## Alternatives considered

- **Microservices** — real module isolation, but at launch scale (one team, one warehouse, 8 external integrations) it multiplies deploy/observability/transaction complexity, and cross-module sagas (checkout → allocation → inventory) become distributed problems with no compensating benefit.
- **Plain layered monolith** (no module boundaries) — fastest start, but the spec's module contracts would erode immediately; cross-module Eloquent joins would silently violate the ownership boundaries (e.g. the two no-oversell layers MUST stay in their owning modules).

## Reasoning

1. The spec's modules are bounded contexts with event contracts — a modular monolith implements exactly that with in-process cheapness.
2. The compliance floor (no-oversell L1+L2, committed-inventory interlock) needs transactional integrity ACROSS module events at launch; one database makes the hard parts tractable.
3. Module boundaries that are conventions + tests (and enforceable by static analysis later) give a clean extraction seam if any module ever needs to become a service.
4. Autonomous loops behave better with explicit, mechanical rules ("no cross-module imports") than with distributed-system judgment calls.

## Trade-offs accepted

- Boundary discipline rests on convention/review/static-analysis instead of network isolation.
- A single DB at launch means scaling is vertical-first; per-module schemas can be revisited post-launch.
- Event versioning discipline is needed even in-process (events are append-logged for the 10-year audit floor).

## References

spec/02-prd/Architecture_v0.3-MVP.md · spec R1–R4 reconciliations (spec/README.md) · [[2026-06-11-tech-stack-laravel-filament]]
