---
type: decision
status: active
date: 2026-06-15
---

## Decision: First-party identity for all actors; auth is a platform foundation whose principal references the party by id; multi-guard; spatie for operator RBAC

Closes the **Identity/auth** gate (root CLAUDE.md "Open stack decisions"; gate = Module K). Unblocks all of Module K, the `catalog-lifecycle-approval` slice (the operator principal who approves), and the Catalog Filament console (operator login). This is the ADR that wires the `ActorContext` seam (CONTEXT.md) — built deliberately on the safe side of this gate, defaulting to `system` and reading no authentication state — to an authenticated principal.

### Three actor classes + system, populating one fixed contract

The event substrate already fixes `domain_events.actor_role NOT NULL ∈ {newco_ops | producer | customer | system}` and a nullable `actor_id` ("local user/party PK; does **not** foreclose the identity ADR — entities stay local rows under any IdP") — [[2026-06-12-event-substrate-and-audit-store]]. Identity must populate exactly this. Three authenticating actor classes plus the system default:

- **Operator** (NewCo staff) → `newco_ops`
- **Customer** (Module K `Customer` party) → `customer`
- **Producer** (Module K `Producer` party) → `producer`
- unauthenticated / console / queue / job → `system`

### First-party for all three, EU-resident — no external IdP at launch

- **Laravel Fortify** (headless auth backend: registration, login, password reset, email verification, TOTP 2FA) + **Laravel Sanctum** (API auth for the customer/producer SPA surfaces). Operators authenticate on Filament's session guard (Filament 5.x ships this — [[2026-06-11-stack-versions-and-filament-ai-tooling]]).
- Anchored to binding constraints, not taste: invariant #13 EU data-residency (`Architecture_v0.3-MVP.md §5.4` "primary stores in EU"); Module K as the authoritative party registry (`Module_K §4.1` — email globally unique; `§7` — a Customer is identity+eligibility, **never a login**); and the GDPR overwrite-in-place erasure already built in Module K plus the `redactor`-role audit redaction ([[2026-06-12-event-substrate-and-audit-store]]). A first-party principal keeps **one** identity, in our EU Postgres, inside the audit/erasure machinery that already exists.
- **Socialite is the documented seam** to add Google Workspace SSO (operators) or social/passwordless login (customers) later, without changing the principal model — the external provider would change only the *authentication method*, never the local principal row.

### Supabase evaluated explicitly and split in two

- **Supabase-as-backend** (Postgres + PostgREST + Row-Level-Security): **rejected** — collides with [[2026-06-12-production-db-engine]] (zero extensions), with the modular-monolith (domain events are the *only* inter-module API; PostgREST exposes tables directly to the SPA — [[2026-06-11-modular-monolith-architecture]]), and with the invariants that live in-transaction in the app (no-oversell L1/L2, KYC/sanctions/Hold at every transaction-initiation surface, the Voucher FSM) — none expressible as RLS policies on SPA-direct writes.
- **Supabase-Auth-only** (GoTrue as IdP): it is simply another external CIAM and loses on the same grounds as Auth0 / Cognito / Clerk / WorkOS — a **second identity store** (split-brain against Module K), customer-PII residency burden, per-MAU cost, and JWT-mapping plumbing — to buy hosted social/MFA we can add later via Socialite.

### Auth is a platform foundation; the principal references the party by id

- The **Authentication principal** (credentials only) is a thin platform-foundation record, never inside a business module. For a Customer or Producer it **references the Module K party by id** — the same "reference by id, never a cross-module relation, join or model import" pattern used everywhere (CONTEXT.md → Product Master). Module K stays pure identity+eligibility; **no password / 2FA-secret ever sits on the `Customer` row.**
- For an **Operator** the principal *is* the identity (operators have no party row). The Operator model and operator RBAC are owned by the **OperatorPanel** module.
- Glossary updated inline this session: CONTEXT.md → new **Identity & Access** section (**Operator**, **Authentication principal**).

### Multi-guard, separated by actor class — and by surface technology

- `operator` → **session guard + Filament** (server-rendered).
- `customer` → **Sanctum** (TanStack storefront SPA).
- `producer` → **Sanctum** (TanStack producer portal).

Because the surfaces separate by technology (Filament session vs Sanctum API), there is **no guard-precedence ambiguity**: a request is on the operator panel *or* the API. ActorContext queries the guard of the current surface; it never arbitrates between concurrent logins.

### ActorContext wiring

- `operator` authenticated → (`newco_ops`, `actor_id = Operator.id`)
- `customer` authenticated → (`customer`, `actor_id = Customer.id`) — the **party** id
- `producer` authenticated → (`producer`, `actor_id = Producer.id`) — the **party** id
- none → (`system`, `null`) — the default (console, queue, jobs, unauthenticated)

`actor_id` is always the **party / operator** id, never the login-principal id: the party is the identity that means something across modules and for ten years in the audit log.

### Operator RBAC

- **Mechanism: `spatie/laravel-permission`** — DB-backed, **runtime-configurable** roles/permissions (this is exactly what the spec means by "admin-configurable" — `Admin_Panel §1.4, §9.2`), guard-aware (operator-scoped). Filament authorizes via Laravel **Policies** that consult spatie. This ADR is the required ADR for adopting the dependency, per the modular-monolith "no new heavyweight dependency without an ADR" rule; the exact version is pinned at install per the stack-ADR discipline (`docs/development.md`).
- **RBAC is operator-scoped at launch.** Customers and producers are **not** in the permission matrix: a customer has no operator role; producer authorization = own-data scoping (when the portal ships) + the single membership write + the compliance gates (Hold/KYC).
- **The separation-of-duties FLOOR is module logic, not a permission.** The 3-step **Creator → Reviewer → Approver** (`Admin_Panel §3.0, §5.2`; `Module_0 §4.2`; `Module_K §4.4`) — three distinct actors, **never self-approval**, audited — is a per-instance, dynamic rule enforced in the approval *transition* (it reads the recorded prior actor from the entity's audit/FSM and rejects a colliding actor). A static permission package cannot express it. It holds at any configured step-count (a lighter 2-step is admin-configurable; the floor never bends).
- **Deferred per spec:** the role catalogue / authority-tier policy (which named role may run which capability) → `feedback_prd_rr_approval`. **Filament Shield deferred** — adopting it now would build the explicitly-deferred admin RBAC UI prematurely; spatie core + Policies suffice at launch.

### Scope deferred (coherent with the gate boundaries)

- **Customer/producer SPA session mechanics** (cookie same-site vs token; CORS/BFF) → the **Module S storefront gate**, with the TanStack frontend ADR (the stack-ADR's open sub-decision). This ADR fixes the *shape* (Sanctum, first-party, principal-references-party), not the cookie-vs-token mechanic.
- **The `producer` guard is shape-decided now but activated** when the Producer Portal login ships (with the TanStack frontend, Module S gate). Until then the single producer write — Module K membership approve/decline — is **operator-on-behalf → `newco_ops`** (`Admin_Panel §1.3, §2.1`), exactly the admin-first launch posture the PRDs prescribe. The `producer` actor_role exists in the contract from day one; it "switches on" when the portal arrives.

## Context

- The gate: root CLAUDE.md "Open stack decisions" blocks Module K, the catalog approval slice, and the Filament console on this ADR. The `ActorContext` seam (CONTEXT.md) was built specifically to be wired here.
- The spec is **tech-agnostic on auth**: `Architecture_v0.3-MVP.md §7.1` (DEC-073) places "security-implementation mechanisms (… MFA, IAM), API style, tenancy implementation" in **dev-team scope**. No spec section dictates the mechanism; the binding constraints are invariant #13 (EU residency), invariant #8 (audit envelope `actor_role`), invariant #10 (module boundaries), Module K as the party registry, and the substrate's `actor_role`/`actor_id` contract.
- The audiences are fixed at the product layer: operators (Admin Panel, `newco_ops` — `Admin_Panel §1.3`), customers (storefront, the Module K `Customer`), producers (portal, read-only + one write). Frontend direction: TanStack SPA for consumer/producer surfaces; Filament/Livewire is operator-only ([[2026-06-11-stack-versions-and-filament-ai-tooling]]).
- Decided in a grill-with-docs session (2026-06-15, five questions, all founder-confirmed): (1) scope — fix the customer-auth *shape* now, defer SPA session mechanics; (2) first-party vs IdP, including an explicit Supabase question; (3) principal-vs-party modeling; (4) ActorContext wiring + producer timing; (5) RBAC mechanism.

## Alternatives considered

- **External IdP (Auth0 / Cognito / Clerk / WorkOS / Google OIDC) for customers** — rejected at launch: split-brain identity against Module K, EU-residency burden for customer PII, per-MAU cost; the principal row stays local anyway. Google Workspace OIDC for *operators* was the one defensible external slice (deprovisioning hygiene) — folded into a Socialite seam, not needed for a tiny launch ops team.
- **Supabase (both readings)** — see Decision: the backend reading breaks three active ADRs + the invariants; the auth-only reading is a strictly-worse external IdP for this context.
- **Conflating principal and party** (a password column on the Module K `Customer`) — rejected: pollutes the party registry with credentials/secrets, contradicts `Module_K §7` (a Customer is never a login), and operators have no party row (asymmetric).
- **One unified polymorphic principal table** across all actors — not chosen: separate principals/guards per actor class are clearer in lifecycle and guard wiring, and the actors already separate by surface technology.
- **Hand-rolled operator RBAC** (enum/table + Policies) — viable and dependency-free, but the spec's "admin-configurable" requirement wants a DB-backed runtime-configurable model; hand-rolling reinvents spatie and would be re-done later.
- **Filament Shield now** — rejected at launch: builds the explicitly-deferred admin RBAC UI prematurely.

## Reasoning

1. **One authoritative identity.** Module K is the party registry by spec; a first-party principal referencing the party by id keeps a single source of truth, in our EU Postgres, inside the audit/erasure machinery already built.
2. **EU residency satisfied structurally** — no customer PII leaves our managed EU Postgres for a third-party IdP (invariant #13).
3. **The substrate contract is populated exactly**, with `actor_id` = party/operator id so the ten-year log references the business identity, not an incidental login row.
4. **Module boundary respected** — auth is a cross-cutting platform foundation reading parties by id, the sanctioned pattern, not a cross-module relation.
5. **First-party is "wire up framework features," not "invent auth"** — Fortify + Sanctum deliver registration/reset/verify/2FA and SPA/token auth out of the box; the TanStack SPA builds its own screens under *any* option anyway, so an external IdP saves little frontend work here.
6. **spatie fits the spec** — DB-backed/runtime-configurable ("admin-configurable"), guard-aware (operator-scoped), the Laravel standard; and the separation-of-duties floor lives where it belongs (module transition logic), never mis-modeled as a static permission.
7. **Solo-founder operability** — no external IdP to operate or pay for; every escape hatch (Socialite SSO/social, Shield, Google Workspace) is documented and non-breaking when the need is real.

## Trade-offs accepted

- **NewCo operates its own credential security** (hashing — framework default; reset; email verification; 2FA). Mitigated by Fortify; MFA-enforcement policy and session-security hardening fall under the "Architectural security review" gate (decisions/INDEX.md).
- **No hosted social/passwordless/anti-bot at launch** — added later via Socialite; abuse is partly covered by the KYC/sanctions/Hold compliance gates.
- **Auth transactional emails (verify / reset) vs `Module_K §16`** ("HubSpot owns outbound communication; Module K never sends a Customer communication directly"): whether auth/system emails route through HubSpot or a separate transactional channel is an **open downstream detail** to settle at the customer-storefront gate. It does not affect the identity architecture.
- **A new dependency** (`spatie/laravel-permission`) — deliberate, recorded here per the no-new-dep rule; version pinned at install.
- **SPA session mechanics deferred** — this ADR is incomplete-by-design on cookie-vs-token until the Module S/TanStack ADR; accepted to avoid pre-deciding what depends on the frontend stack.
- **CONTEXT.md → Actor context** still reads "reads NO authentication state … until that ADR wires it" — accurate until the wiring change ships; to be updated then.

## References

- Gate: root CLAUDE.md "Open stack decisions" (Identity/auth → Module K); decisions/INDEX.md open-decisions line.
- `spec/02-prd/Architecture_v0.3-MVP.md` §7.1 (DEC-073 — security = dev-team scope), §2.3 / §5.3 (`actor_role` envelope), §5.4 (EU residency, GDPR posture).
- `spec/02-prd/Module_K_PRD_v0.3-MVP.md` §4.1 (Customer; email globally unique), §7 (onboarding — a Customer is not a login), §4.4 (producer activation, 3-step), §9 (KYC / sanctions), §4.8 (Hold), §16 (HubSpot owns outbound communication).
- `spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md` §1.3 (`actor_role` envelope; `newco_ops` / `producer`), §1.4 + §9.2 (RBAC admin-configurable + downstream → `feedback_prd_rr_approval`), §2.1 (single producer write; operator-on-behalf), §3.0 + §5.2 (Creator → Reviewer → Approver; no self-approval).
- Invariants: CLAUDE.md #8 (audit envelope `actor_role`), #10 (module boundaries), #13 (EU residency).
- CONTEXT.md → Identity & Access (**Operator**, **Authentication principal**), Actor context, actor_role.
- ADRs: [[2026-06-12-event-substrate-and-audit-store]] (`actor_role`/`actor_id` contract), [[2026-06-12-production-db-engine]] (PG, zero extensions), [[2026-06-11-modular-monolith-architecture]] (events-as-API; no-new-dep rule), [[2026-06-11-stack-versions-and-filament-ai-tooling]] (Filament 5.x; TanStack direction; version-pinning discipline).
- Deferred to: the Module S storefront gate + the TanStack frontend ADR (SPA session mechanics; producer-portal login activation).
