# NewCo ERP — Project Configuration

## What This Is

Implementation of **NewCo ERP v0.3-MVP**: the system of record for a producer-club fine-wine aggregator — commerce, custody, fulfilment and finance on passive consignment, B2C only, NewCo as Seller of Record. Nine modules: 0 (PIM/Catalog), K (Parties), A (Allocation), D (Procurement), S (Sales/Commerce), B (Inventory + Provenance), C (Fulfilment), E (Finance), Admin Panel (Operator Surface).

**Spec authority:** `spec/` is the immutable v0.3-MVP handoff baseline. Every requirement we implement must trace to it (file + section) or to an ADR in `decisions/`. Never edit `spec/**`. The v1.1 reference is frozen, lives outside this repo, and is never a build source. Start here: `spec/README.md` → `spec/05-release/Build_Workplan_v0.3-MVP.md` → `spec/02-prd/Architecture_v0.3-MVP.md`.

## Tech Stack and Tech Rules

- **PHP ≥ 8.4** · **Laravel 13.x** (`^13.0`) · **Filament 5.x** (`^5.0`) for the operator panel — pinned per ADR `decisions/2026-06-11-stack-versions-and-filament-ai-tooling.md`; exact installed versions recorded in `docs/development.md`.
- **Pest** for tests · **Larastan/PHPStan** for static analysis · **Pint** for format/lint.
- Dev/test database: **SQLite** (`:memory:` in tests). Production engine: **PostgreSQL ≥ 17** (managed, EU) — ADR `decisions/2026-06-12-production-db-engine.md`. Migrations are Postgres-truthful, SQLite-compatible; no PG extensions at launch
- Prefer framework conventions: Eloquent, FormRequests, Policies, Events, Jobs, Notifications. No new heavyweight dependency without an ADR in `decisions/`.
- **Money is always integer minor units + ISO 4217 currency code. Never floats.**
- All user-facing strings go through Laravel localization (6 locales: EN, IT, FR, DE, JP, ZH). No hardcoded copy.

### Open stack decisions — each REQUIRES an ADR before its gate

| Open decision | Decide before (gate) |
|---|---|
| Identity/auth (first-party vs external IdP; customer vs operator auth) | Module K |
| Queue driver (Redis + Horizon vs database) | first async workflow |
| Domain-event substrate (in-process sync + outbox vs broker) | first cross-module event |
| Audit/financial event store (10-year immutability mechanism) | first financial event |
| Object storage for documents (invoices, statements) | INV1 issuance |
| Hosting/infra (EU data-residency is mandatory) | staging environment |
| Consumer/producer frontend stack (founder direction: TanStack SPA — TypeScript, no PHP frontend; formal ADR at gate) | Module S storefront |

When a gate approaches, run a `grill-with-docs` session, write the ADR, then proceed.

## Architecture (decided — see decisions/2026-06-11-modular-monolith-architecture.md)

**Modular monolith.** One Laravel application; nine bounded contexts under `app/Modules/{Catalog,Parties,Allocation,Procurement,Commerce,Inventory,Fulfilment,Finance,OperatorPanel}` (module ↔ spec letter: Catalog=0, Parties=K, Allocation=A, Procurement=D, Commerce=S, Inventory=B, Fulfilment=C, Finance=E).

- **No cross-module Eloquent relationships, joins, or model imports.** Modules communicate via domain events and small read contracts (interfaces). The ~120 events in the spec are the inter-module API.
- Domain events are append-logged for audit (implementation per open ADR).
- The four spec reconciliations **R1–R4 are canonical**: `SupplierPaymentCompleted` is emitted by **Module E** (D and B consume it independently); storage fees and INV1/INV2/INV3 are **Module-S-internal**; Logilize streams split C=4 fulfilment / B=5 inventory-state; Allocation FSM activation is operator publish, not payment-triggered.
- NFT/on-chain is **decoupled behind a feature flag** (D12): serialization ships launch-ready; mint/burn stays flagged off. The non-serialized (NS) path is the universal fallback.

## Key Invariants (NEVER violate)

1. **No-oversell, Layer 1** — Allocation: `qty − issued ≥ 0` per sub-pool, enforced transactionally (Module A PRD).
2. **No-oversell, Layer 2** — Inventory: `physical_in_storage − reserved − quarantined − under_adjustment ≥ 0` per sub-pool (Module B PRD).
3. **Committed inventory** — an inventory adjustment must never breach outstanding voucher commitments (`VoucherCancelled` ↔ `InventoryShortfallDetected` interlock).
4. **Financial immutability** — financial events and synced invoices are immutable; corrections only via credit notes. 10-year retention.
5. **Dual currency** — every customer-facing financial event records customer currency AND EUR with a locked FX rate; refunds settle at the original captured rate (D18 floor).
6. **Money discipline** — integer minor units + currency code, everywhere.
7. **Compliance gates** — KYC / sanctions / Hold checks run at every transaction-initiation surface (order completion, charge, refund). Holds are never auto-lifted.
8. **Audit envelope** — every operator action records `actor_role`; audit trails are append-only.
9. **Invoicing ownership** — INV1/INV2/INV3 issuance lifecycle lives only in Module S (R2).
10. **Module boundaries** — no cross-module DB access; events + contracts only.
11. **Spec immutability** — never edit `spec/**`. Never hand-edit `openspec/specs/**` (truth changes only via change archive).
12. **i18n** — no hardcoded user-facing strings.
13. **EU data residency** for production data and backups.

If a task appears to require violating an invariant, STOP and escalate (`<promise>HUMAN_NEEDED</promise>` in loop mode; ask the user in interactive mode).

## Canonical Terminology (Product Domain)

Use spec terms verbatim in code identifiers. The glossary of record is **`CONTEXT.md`** (root) — extend it as terms are resolved. Core anchors:

| Canonical term | Meaning | Owner |
|---|---|---|
| **Allocation** | The single supply primitive: producer/supplier quantity made sellable under commercial terms | Module A |
| **Sub-pool** | Partition of an allocation: `qty_to_serialize` vs `qty_non_serialized` | Module A |
| **Voucher** | Customer's entitlement to one bottle, 7-state FSM (never "coupon"/"credit") | Module S |
| **Club Credit** | Monetary credit entity on membership (distinct from Voucher) | Module K |
| **Originating Club (OC)** | Club a customer joined through; accrues 5% on their Discovery purchases | Module K/S |
| **Hold** | Unified, trigger-agnostic account restriction (6 types); never auto-lifted | Module K |
| **InboundBatch / StockPosition** | Physical receipt / 5-dimension inventory authority view (ATP source) | Module B |
| **INV1 / INV2 / INV3** | Bottle / shipment (excise+VAT) / storage invoices — all Module S | Module S |
| **SerializedBottle / NS** | NFC-serialized bottle vs non-serialized fallback path | Module B |

**Hard rules:**
- Two distinct ownership fields exist (spec note N3) and must never be merged or confused: inventory `ownership_flag` (`PRODUCER`/`CRURATED`, keys to payment signal) vs purchase-order `ownership` (`PRODUCER`/`NEWCO`/`THIRD_PARTY`, keys to sale/shipment signal).
- When any skill or doc refers to `docs/adr/`, the ADR home in this repo is **`decisions/`**.

## Quality Commands

| Command | Purpose | Value |
|---|---|---|
| format | Code formatter | `vendor/bin/pint` |
| test_filter | Run specific test | `php artisan test --filter={name}` |
| test | Run tests | `php artisan test` |
| type_check | Static analysis | `vendor/bin/phpstan analyse` |
| lint | Linter (check only) | `vendor/bin/pint --test` |

Until the `bootstrap-laravel-app` change completes, commands that don't exist yet are skipped (per RALPH.md rules).

## Workflow & Where Things Live

- **Work state machine:** `openspec/` — `specs/` is current truth (read it before touching a module), `changes/` is in-flight work, `changes/archive/` is history. Lifecycle: `/spec-to-change` → human review → `APPROVED` file → `./ralph.sh` → review/merge → `/opsx:verify` → `openspec archive`.
- **Memory:** `hot.md` (state cache, overwritten), `log.md` (append-only ledger), `lessons.md` (corrections), `knowledge/` (hypotheses→rules), `.claude/memory/` (team memory), `decisions/` (ADRs). Mechanics in `.claude/CLAUDE.md`.
- **Loop:** `./ralph.sh` + `RALPH.md`. One task per iteration. Tests are never optional (`.claude/skills/references/acceptance-criteria.md`).

## Protected Files (never modify unless the user explicitly asks)

`CLAUDE.md` · `RALPH.md` · `ralph.sh` · `.claude/settings.json` · `.claude/hooks/**` · `.claude/skills/**` · `spec/**` · `openspec/specs/**` (by hand) · `*/APPROVED` files (only the human creates them).
