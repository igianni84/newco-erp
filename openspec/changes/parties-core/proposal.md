# Proposal — parties-core

## Why

This is the **first structural slice of Module K (Parties)**, the second foundational module of Phase 2 (`spec/05-release/Build_Workplan_v0.3-MVP.md` §2 Phase 2 — "Foundations: Catalog (Module 0) and Parties (Module K)"; the Parties subtask line is `Build_Workplan_v0.3-MVP.md:104`). Parties is **"the compliance floor … read by every later module"** (`Build_Workplan_v0.3-MVP.md:96`): Allocation, Procurement, Commerce, Inventory, Fulfilment and Finance all key off Customer / Producer / Supplier ids. Nothing downstream can be built until the party spine exists.

The slice is deliberately the **structural identity spine only** — the same spine-first discipline as the archived `catalog-product-spine`. It is the one part of Module K that depends on **no open ADR gate**: Identity/auth is **closed** (`decisions/2026-06-15-identity-auth.md`), the event substrate is decided and delivers **inline** at launch (`decisions/2026-06-12-event-substrate-and-audit-store.md`), so recording `*Created` events introduces **no `queued` consumer** (the queue-driver gate stays untouched). It creates entities and records their creation, with only the structural creation invariants that need neither an external gate (KYC/sanctions provider, Module E payment events, Module A capacity) nor an integration.

This change decides nothing the spec or an existing ADR has not already decided, except the one representation point `spec/04-decisions/decisions.md` DEC-073 delegates to the dev team — the **physical representation of the Party-type marker** (marker-on-subtype; the unified Party Registry deferred) — recorded in a new ADR (`decisions/2026-06-15-party-type-marker-on-subtype.md`).

## What Changes

- **Seven core Module K entities** (`App\Modules\Parties`, owned code) as Postgres-truthful / SQLite-compatible tables + Eloquent models, table-prefixed `parties_*`:
  - **Producer** — the winery identity registry; **standalone (NOT a Party subtype)**; born `draft`; translatable producer story (six locales).
  - **Supplier** — the **minimal** commercial-counterpart entity (legal name + immutable party-type marker + timestamps); exists to give Module D something to reference by id.
  - **Club** — a Producer-operated membership program; **requires an operating Producer** (rejected if missing), **immutable** once set; born `active`; per-Club fee in **integer minor units + ISO 4217**.
  - **ProducerAgreement** — the NewCo↔Producer commercial agreement (DEC-070); Producer required, Club narrowing optional; born `draft`; the D19 settlement-cadence seam.
  - **Customer** — NewCo's **natural-person** registry; email **globally unique**; born `pending`; carries the immutable party-type marker `CUSTOMER`; the `originating_club_id` **field** (born `NULL`, no mutation surface).
  - **Account** — the per-Customer **billing/transactional container** (NOT a money ledger; there is no "Account Credit"); **co-provisioned** with the Customer, 1:1, born `active`, type `personal`.
  - **Profile** — the **membership** (the Netflix-style Customer↔Profile model; *there is no separate Membership entity*); belongs to exactly one Customer and one Club; born `Applied`; **multi-profile** across Clubs, one per (Customer, Club).
- **The Party-type marker as an immutable enum on the subtype** (Customer = `CUSTOMER`, Supplier = `SUPPLIER`), satisfying BR-K-Identity-5 by construction — distinct strongly-typed entities, a Customer can never become a Supplier. The unified `parties_parties` registry, the dormant `THIRD_PARTY_OWNER` subtype, and any marker overlap are **deferred** (none are exercised at launch; Producer↔Supplier overlap is a Module-D `SupplierProducerLink`, not a Party-registry marker). Recorded in the new ADR.
- **Creation events**: on creation, **Customer, Profile, Producer, Club and ProducerAgreement** each record their verbatim `*Created` domain event through `DomainEventRecorder` (module `parties`, actor from the `ActorContext` seam, **PII-free** payload — parties by id, money as `{minor_units, currency}`, **never** name/email/phone/DOB), inside the writing transaction. **Supplier and Account record no creation event** — the PRD event catalog (§15) names none, and inventing events violates spec fidelity.
- **State columns stored, transitions deferred**: every entity carries its full state domain (Customer `pending|active|suspended|closed`; Producer/ProducerAgreement `draft|…`; Club `active|sunset|closed`; Profile `applied|…|inactive`; Account `active|suspended|closed`) and is **born in its birth state**. **No** state transitions, **no** approval/activation workflow, **no** membership approve/decline, **no** `*Activated`/`OriginatingClubLocked`/lifecycle events in this change.
- **Docs**: extend `CONTEXT.md` with the resolved Parties glossary terms (Profile, Supplier, Club, Account [billing container], ProducerAgreement, party-type marker, Originating-Club field) and a Parties contract note documenting the five `*Created` payloads (PII-free) and the deliberate Supplier/Account event silence.

### Slice boundary — deliberately NOT in this change

| Deferred concern | Future change | Why not here |
|---|---|---|
| Entity **lifecycle transitions** (Customer/Account/Producer/Club/Profile/Agreement FSMs), Profile **approval/activation**, the single producer write (**membership approve/decline**, L-PP), **`OriginatingClubLocked`** firing at first approval, the **Hero Package Capacity Invariant**, the Customer-segment view, and all `*Activated`/lifecycle events | **`parties-membership-lifecycle`** | Transitions need actor identity flows + Module E `MembershipFeePaid` (activation) and Module A `qty` (Hero Package capacity, PRD §13.2); the OC lock fires on the **approval** write the user placed out of scope. The `originating_club_id` field + its no-mutation seam ship now; the lock travels here. |
| Unified, trigger-agnostic **Hold** registry (6 types) + the gate/decorator (DEC-181) | **`parties-holds`** | A cross-cutting gate; its value needs transaction surfaces (Modules S/E/C) and several Hold types are driven by Module E events. |
| **KYC / sanctions** four-state lifecycles + sanctions provider + enhanced-KYC thresholds | **`parties-compliance`** | External vendor adapter; enforcement is at Module S order completion. Customer's sanctions/KYC fields are nullable (DEC-071) and added additively then. |
| **Club Credit** entity + auto-issuance + one-active-per-Profile | **`club-credit`** | Issuance consumes Module E `MembershipFeePaid`; redemption is Module S. |
| **GDPR** soft-delete / retention / anonymisation; marketing-consent lifecycle | **`parties-gdpr-retention`** | Destructive workflow over data that must first exist + the Hold registry; the redaction seam is the substrate's `audit_records` UPDATE allowance. |
| **Filament** Parties operator console | **`parties-operator-console`** | Presentation layer (OperatorPanel module) over these domain entities. |
| The full **Party Registry** table, `THIRD_PARTY_OWNER` subtype, marker overlap | **`parties-party-registry`** (only if a later need surfaces) | Not exercised at launch; deferred per the marker-on-subtype ADR with the seam preserved. |

## Capabilities

### New Capabilities

- `party-registry`: the Parties module's core registry spine — the identity entities (Customer, Producer, Supplier), the per-Customer Account (billing container), and the membership/agreement structures (Club, Profile, ProducerAgreement) — their creation, the structural creation invariants (global email uniqueness; the immutable party-type marker; Club↔Producer required-and-immutable; Producer/Supplier no-auto-cross-create; the multi-profile one-per-(Customer,Club) rule; the Originating-Club field seam; the single-active-agreement rule noted as activation-time), the stored-but-untransitioned state columns, and the five `*Created` creation events. The umbrella capability the later Module K changes (lifecycle, holds, compliance, club-credit, GDPR) extend or sit beside.

### Modified Capabilities

_None._ This change adds a new capability and complies with the existing `module-architecture`, `event-substrate`, `money`, `i18n` and `operator-identity` capabilities without changing their requirements.

## Impact

- **New code** — `app/Modules/Parties/{Models,Events,Actions,Enums,Exceptions,Providers}`, migrations in `database/migrations/` (`parties_*` tables), factories in `database/factories/Parties/`, tests in `tests/Unit/Modules/Parties/` + `tests/Feature/Modules/Parties/`.
- **New ADR** — `decisions/2026-06-15-party-type-marker-on-subtype.md` (marker-on-subtype; unified Party Registry deferred; resolves DEC-073's delegated representation choice for the party-type marker) + a `decisions/INDEX.md` row.
- **Reuses, does not modify** — `DomainEventRecorder`, `ActorContext`/`ActorRole`, the `Module` enum (`Module::Parties->value === 'parties'`), `Money`/`MoneyCast`/`Currency`, `TranslatableText`/`TranslatableTextCast`/`SupportedLocale`. **No** new dependency.
- **Arch tests need no amendment** — `ModuleBoundariesTest` covers the new `App\Modules\Parties\*` namespaces by name-prefix (this slice has **no** cross-module reference — every reference is within Module K); `ModulePersistenceConventionsTest` requires the `parties_*` `$table` prefix on every model (the auth-principal exemption does **not** apply — Parties entities are not `Authenticatable`).
- **DB engines** — every migration is Postgres-truthful and SQLite-compatible (no PG extensions); every DB-touching test green on SQLite **and** verified on a local PostgreSQL 17 before close (`knowledge/testing/rules.md`).
- **Deliberate traceability gaps** — the deferred concerns above are each mapped to a named future change. Two spec-faithful asymmetries are recorded explicitly: **Supplier and Account have no `*Created` event** (none named in PRD §15), and the **Originating-Club lock fires at approval** (deferred with membership approve/decline), so only the `originating_club_id` field + its immutability seam ship here.
