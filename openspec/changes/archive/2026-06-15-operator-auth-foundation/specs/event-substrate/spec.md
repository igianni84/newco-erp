## MODIFIED Requirements

### Requirement: Actor Context Resolution

The platform SHALL provide a reusable actor-context resolver that supplies the `actor_role` (and an optional `actor_id`) for the current execution context, so domain-event and audit emitters obtain the acting principal from one canonical seam rather than hardcoding a role at each call site. The resolver SHALL determine the acting principal in this precedence:

1. an explicit scoped **run-as override** — applying a given role (and optional actor id) for the duration of a callable and restoring the prior context afterward — SHALL take precedence over ambient authentication state;
2. otherwise, when the current request is authenticated on the **`operator` guard**, the resolver SHALL return `actor_role = newco_ops` with `actor_id =` the authenticated `Operator`'s id;
3. otherwise the resolver SHALL return `actor_role = system` with a null `actor_id` — the default for console, queue, and unauthenticated contexts, and for the `customer` and `producer` guards, which are **not yet wired** (their mapping to `customer` / `producer` arrives with the Module S storefront / TanStack frontend gate).

`actor_id` SHALL always be the **party / operator** id, never the login-principal's own id. The resolver SHALL read authentication state only through the guard **by name** (it SHALL NOT import the OperatorPanel `Operator` model — it stays cross-cutting platform code, not a cross-module dependency), and SHALL resolve at query time (never memoising the authenticated principal across requests). The four-value `ActorRole` set (`newco_ops` | `producer` | `customer` | `system`) and the NOT-NULL `actor_role` envelope column are unchanged (they already exist from the domain-events-audit change); this requirement now wires the operator mapping that the original gate-safe seam deferred.

#### Scenario: Default context resolves to System with a null actor

- **WHEN** the resolver is queried in a console, queue or unauthenticated context with no override and no operator-guard authentication
- **THEN** it returns `actor_role = system` and a null `actor_id`

#### Scenario: An authenticated operator resolves to newco_ops

- **WHEN** the resolver is queried during a request authenticated on the `operator` guard with no override
- **THEN** it returns `actor_role = newco_ops` and the authenticated `Operator`'s id as `actor_id`

#### Scenario: A non-operator context resolves to System

- **WHEN** the resolver is queried with no operator-guard authentication and no override — a console/queue/unauthenticated context, or a `customer`/`producer` surface that this change does not wire
- **THEN** it returns `actor_role = system` and a null `actor_id` (no auth state other than the operator guard is consulted)

#### Scenario: A scoped run-as override applies and then restores

- **WHEN** a callable is run under a run-as override of `newco_ops` with actor id `42`
- **THEN** the resolver returns `newco_ops` / `42` for the duration of that callable, and reverts to the prior context afterward

#### Scenario: Run-as overrides an authenticated operator

- **WHEN** a `system` run-as override wraps a callable during a request authenticated on the `operator` guard
- **THEN** the resolver returns `system` / null for that callable's duration, and reverts to `newco_ops` / the operator's id once it returns

_Source: decisions/2026-06-15-identity-auth.md (ActorContext wiring — `operator` authenticated → `newco_ops` / `Operator.id`; `actor_id` is always the party/operator id; the `producer`/`customer` guards are shape-decided but activated later; this is the change that wires the seam the gate-safe resolver deferred) · spec/05-release/Build_Workplan_v0.3-MVP.md § 2 Phase 1 (the `actor_role` audit envelope — every operator action carries actor_role + identity — is wired in Phase 1) · spec/02-prd/Architecture_v0.3-MVP.md § 2.3 + § 5.3 (the `actor_role` audit envelope on every operator action) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md § 1.3 (every operator action records `actor_role: newco_ops` + identity) · decisions/2026-06-12-event-substrate-and-audit-store.md (`actor_role` NOT NULL — invariant 8) · openspec/specs/event-substrate/spec.md Requirement: Domain Event Envelope (`actor_role` NOT NULL, one of `newco_ops` | `producer` | `customer` | `system`) · spec/04-decisions/decisions.md DEC-083 + DEC-115 (`actor_role: producer | newco_ops` on parity writes — the operator-on-behalf posture this seam now serves) · CLAUDE.md invariants 8 + 10._
