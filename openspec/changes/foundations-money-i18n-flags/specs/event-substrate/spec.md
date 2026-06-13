## ADDED Requirements

### Requirement: Actor Context Resolution

The platform SHALL provide a reusable actor-context resolver that supplies the `actor_role` (and an optional `actor_id`) for the current execution context, so domain-event and audit emitters obtain the acting principal from one canonical seam rather than hardcoding a role at each call site. Until the identity/auth ADR is decided (the open gate that precedes Module K), the resolver SHALL default to `actor_role = system` with a null `actor_id` for console, queue and unauthenticated contexts, and SHALL support an explicit scoped run-as override that applies a given role (and optional actor id) for the duration of a callable and restores the prior context afterward. The resolver SHALL NOT read authentication state — mapping an authenticated operator, producer or customer to their `actor_role` is the identity/auth ADR's responsibility (Module K), so this seam does not step through that gate. The four-value `ActorRole` set (`newco_ops` | `producer` | `customer` | `system`) and the NOT-NULL `actor_role` envelope column are unchanged (they already exist from the domain-events-audit change); this requirement adds only the canonical way to populate them.

_Source: spec/05-release/Build_Workplan_v0.3-MVP.md § 2 Phase 1 (MVP carries (iii): "the `actor_role` audit envelope — every operator action carries actor_role + identity + timestamp + action + entity reference — the Admin-Panel arm of the audit floor — is a Phase-1 audit-pattern concern") · spec/02-prd/Architecture_v0.3-MVP.md § 2.3 (the `actor_role` audit envelope) + § 5.3 (the `actor_role` audit envelope recorded on every operator action) · decisions/2026-06-12-event-substrate-and-audit-store.md (`actor_role` NOT NULL — invariant 8) · openspec/specs/event-substrate/spec.md Requirement: Domain Event Envelope (`actor_role` NOT NULL, one of `newco_ops` | `producer` | `customer` | `system`) · CLAUDE.md "Open stack decisions" (identity/auth — decide before Module K) + invariant 8 · spec/04-decisions/decisions.md DEC-083 + DEC-115 (`actor_role: producer | newco_ops` on parity writes — the future wiring this seam will serve)._

#### Scenario: Default context resolves to System with a null actor

- **WHEN** the resolver is queried in a console, queue or unauthenticated context with no override
- **THEN** it returns `actor_role = system` and a null `actor_id`

#### Scenario: A scoped run-as override applies and then restores

- **WHEN** a callable is run under a run-as override of `newco_ops` with actor id `42`
- **THEN** the resolver returns `newco_ops` / `42` for the duration of that callable, and reverts to the prior context (default `system`) afterward

#### Scenario: The resolver ignores authentication (gate-safe)

- **WHEN** an authenticated session exists and the resolver is queried with no explicit override
- **THEN** it still returns `system` — it reads no auth state, deferring operator/producer/customer wiring to the identity/auth ADR (Module K gate)
