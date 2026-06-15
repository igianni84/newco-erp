# Proposal — catalog-product-spine

## Why

This is the **first real module slice** of Phase 2 (`spec/05-release/Build_Workplan_v0.3-MVP.md` §2 Phase 2 — "Foundations: Catalog (Module 0) and Parties (Module K)"). Catalog holds product identity and is **read by every later module** (A/D/S/B/C all key off the Product Reference); nothing downstream can be built until the product spine exists. The F1 foundations (domain-event substrate + audit, Money, i18n, feature flags, module skeleton) are complete and green on both CI engines — Catalog is the **first module to exercise `App\Platform\Events\DomainEventRecorder` and the `App\Platform\I18n\TranslatableText` primitive** for real business entities.

The slice is deliberately the **structural spine only**, because it is the one piece of Module 0 that depends on **neither** the Identity/auth ADR **nor** Module K: it creates entities and records their creation, with no approval workflow (which needs actor identity) and no Producer-activation gate (which needs Module K's `ProducerActivated`). Building it now unblocks the rest of Phase 2 while those gates are still open.

This change decides nothing the spec or an existing ADR has not already decided, except the one point `spec/04-decisions/decisions.md` DEC-073 explicitly delegates to the dev team — the **physical representation** of the category-neutral model — recorded in a new ADR (`decisions/2026-06-14-catalog-category-neutral-representation.md`).

## What Changes

- **The seven product-spine entities** (`App\Modules\Catalog`, owned code) as Postgres-truthful / SQLite-compatible tables + Eloquent models, table-prefixed `catalog_*`:
  - **Product Master** — top of the hierarchy; category-neutral core (product name, `product_type`, producer **reference by id**, `lifecycle_state`, audit/version) + a **WINE attribute set** (appellation/region + translatable descriptive prose).
  - **Product Variant** — belongs to exactly one Product Master; type-neutral variant identifier on the core; the WINE attribute set carries **vintage** (+ translatable vintage prose).
  - **Product Reference (PR)** — the **atomic product key**: exactly two dimensions (Variant + Format); Case Configuration is **never** part of PR identity.
  - **Format** — standalone reference entity (WINE = bottle sizes).
  - **Case Configuration** — standalone reference entity (packaging form only; **carries no breakability flag** — §7 layered rule lives downstream).
  - **Sellable SKU (Intrinsic)** — the commercial unit = one PR + one Case Configuration + commercial attributes; the only SKU shape that references a Case Configuration.
  - **Composite SKU** — a bundle of **N ≥ 2 constituent PRs** (ordered, M:N); **producer-agnostic at PIM** (the Mod0-Q1 / D7 seam — single-producer admissibility is a Module S surface rule, PIM stays silent).
- **The §16 category-neutral generalisation**: `Product Type` is a first-class classifier on Product Master with **`WINE` the sole launch value**; the core entities stay category-neutral; wine-specific attributes live in **per-type attribute tables** off the core (ADR, per DEC-073). No second Product Type, no Format vocabulary, no EAV/rules engine (§16 guardrails).
- **The type-defined identity key**: for `WINE`, a Product Master is unique on **producer + product name + appellation** (`appellation` is a real column for an engine-portable unique index); deduplication is enforced at creation.
- **The §18 naming cascade as canonical code naming**: structural names + event families are the category-neutral `Product*` set (`ProductMaster*` / `ProductVariant* `/ `ProductReference*`); "Wine Master / Wine Variant / Bottle Reference (BR)" are retained as **wine-display aliases**; Format / Case Configuration / Sellable SKU / Composite SKU names are unchanged.
- **Creation events**: on creation, each spine entity records its **`*Created`** domain event through `DomainEventRecorder` (module `catalog`, actor from the `ActorContext` seam, PII-free payload referencing parties by id), inside the writing transaction.
- **`lifecycle_state` is stored** (enum `draft|reviewed|active|retired`); every entity is **born `draft`**. **No** state transitions, **no** approval workflow, **no** `*Activated`/`*Retired` emission in this change.
- **Docs**: extend `CONTEXT.md` with the resolved spine glossary terms (Product Master/Variant/Reference, Format, Case Configuration, Sellable SKU, Composite SKU, Product Type, the naming-cascade alias rule); add a Catalog contract note documenting the `*Created` event payloads.

### Slice boundary — deliberately NOT in this change

| Deferred concern | Future change | Why not here |
|---|---|---|
| 4-state FSM transitions + 3-step Creator→Reviewer→Approver approval + rejection handling + activation/retirement **cascades** + `*Activated`/`*Retired` events + emission-ordering invariant | **`catalog-lifecycle-approval`** | Approval needs **actor identity** (Identity/auth ADR) and the Master-activation gate needs Module K's `ProducerActivated`/`ProducerRetired`. Both gates are still open. |
| PR **immutability-once-referenced** full enforcement (BR-Identity-4) | **`catalog-lifecycle-approval`** (lands with the first downstream referencers) | No Allocation/voucher/stock/Offer exists yet to reference a PR; the identity-as-(variant,format) rule is modelled now, the cross-module "is-referenced?" guard arrives with referencers. |
| Composite SKU **atomicity-at-sale** (BR-SKU-3) + **immutability-after-active-Offer** (BR-SKU-4) | **`catalog-lifecycle-approval`** / Module S | Both are runtime/commercial rules requiring Modules A/B/S; PIM carries only the bundle structure now. |
| Layer-1 producer-side **breakability whitelist** (per-Format Case-Config whitelist on Product Variant) | **`catalog-breakability-whitelist`** | Independent feature; the no-oversell floor's PIM input. Out of the pure-structure spine. |
| **LWIN / Liv-ex enrichment adapter** (auto-populate, producer-match, capture-then-own, retry) | **`catalog-enrichment-lwin-adapter`** | Pluggable + **off the launch critical path** (D9 / Mod0-Q3 / MVP-DEC-005). The **manual baseline** — plain operator-entered creation — is the launch-critical path and is what this slice builds. |
| Bulk import; read API + Filament operator UX; Bottle-Page skeleton | later Module 0 changes | Not the data spine. |

## Capabilities

### New Capabilities

- `product-catalog`: the category-neutral product-catalog (PIM) spine — the seven product-identity entities (Product Master, Product Variant, Product Reference, Format, Case Configuration, Sellable SKU Intrinsic + Composite), their identity and relationship invariants (single-parent hierarchy; PR = Variant + Format with Case Configuration never in identity; the WINE type-defined uniqueness key; Composite N ≥ 2 producer-agnostic constituents), the `Product Type` = `WINE` category-neutral generalisation, the §18 naming cascade, the stored-but-untransitioned `lifecycle_state`, and the `*Created` creation events. The umbrella capability the later Module 0 changes (lifecycle/approval, breakability, enrichment, read API/UX) extend.

### Modified Capabilities

_None._ This change adds a new capability and complies with the existing `module-architecture`, `event-substrate` and `i18n` capabilities without changing their requirements.

## Impact

- **New code** — `app/Modules/Catalog/{Models,Events,ValueObjects,Enums,Providers}`, migrations in `database/migrations/` (`catalog_*` tables), factories in `database/factories/Catalog/`, tests in `tests/Unit/Modules/Catalog/` + `tests/Feature/Modules/Catalog/`.
- **New ADR** — `decisions/2026-06-14-catalog-category-neutral-representation.md` (per-type attribute tables; resolves DEC-073's delegated representation choice) + `decisions/INDEX.md` row.
- **Reuses, does not modify** — `DomainEventRecorder`, `ActorContext`/`ActorRole`, `Module` enum, `TranslatableText`/`TranslatableTextCast`/`SupportedLocale`. **No** new dependency. **No** Money (Catalog is identity-only — no prices). The boundary arch test needs **no amendment** (the new `App\Modules\Catalog\*` namespaces are covered by name-prefix; `producer_id` is a plain column, not a cross-module Eloquent relation).
- **DB engines** — every migration is Postgres-truthful and SQLite-compatible; every DB-touching test must be green on SQLite **and** verified on a local PostgreSQL 17 before close (`knowledge/testing/rules.md` — the five portability traps).
- **Deliberate traceability gaps** — the deferred concerns above (FSM/approval, breakability, enrichment adapter, PR-reference immutability enforcement, Composite atomicity/Offer-immutability) are each mapped to a named future change; the manual-baseline-only enrichment posture is authorised by MVP-DEC-005.
