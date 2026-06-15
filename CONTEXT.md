# NewCo ERP — Ubiquitous Language

Glossary of record for the NewCo producer-club wine aggregator. Seeded from the v0.3-MVP spec; extended via grill-with-docs sessions as terms are resolved. Definitions only — no implementation details. Full semantics: the module PRDs in `spec/02-prd/`.

## Product Catalog (PIM)

The structural product spine of Module 0 — category-neutral by design (§16/§18): each entity has a neutral core with type-specific attributes held off it, so a future Product Type slots in additively without reshaping the core or the cross-module event contract. At launch the only Product Type is `WINE`. Spine entities are born `draft`; lifecycle transitions are out of scope until `catalog-lifecycle-approval`.

**Product Master**:
The top of the product hierarchy and the parent of every Product Variant. Its category-neutral core carries the product name, the Product Type, a producer reference **by id** (a plain identifier into Module K — never a cross-module relation, join or model import), lifecycle state and audit/version; its `WINE` attribute set holds appellation/region and the translatable winery story. For `WINE` a Master is unique on `producer + product name + appellation`; a creation colliding with a non-retired Master is rejected at creation. Wine-display alias: "Wine Master".
_Avoid_: Wine Master (a display alias, never a code/contract name), product, SKU

**Product Variant**:
A release of a Product Master, belonging to **exactly one** Master (structurally enforced). Its variant axis is a type-neutral identifier on the core; for `WINE` the axis is **vintage** (a year or a non-vintage marker) held in the `WINE` attribute set, never as a core column. Wine-display alias: "Wine Variant".
_Avoid_: Wine Variant (a display alias, never a code/contract name), vintage (that is the WINE axis, not the entity)

**Product Reference (PR)**:
The atomic product identity and the universal product key across modules, composed of **exactly two dimensions** — a Product Variant and a Format. A Case Configuration is **never** part of PR identity: the same Variant + Format resolves to the **same** PR whether later sold loose, in an OWC, or in a carton. The `(variant, format)` pair is unique and is the PR's identity (changing the composition is a new PR, not an in-place edit). Wine-display alias: "Bottle Reference (BR)".
_Avoid_: Bottle Reference, BR (display aliases, never code/contract names), SKU, packaging

**Format**:
A standalone PIM reference entity (no parent) representing the physical size/measure of the atomic unit — for `WINE`, the bottle size. A Product Reference references exactly one Format. Name kept unchanged by the naming cascade.
_Avoid_: bottle size (Format is category-neutral; bottle size is the WINE reading), packaging (that is Case Configuration)

**Case Configuration**:
A standalone PIM reference entity, distinct from Format, carrying packaging-form attributes only — units per case, packaging type, physical form — referenced **only** by a Sellable SKU (Intrinsic). It carries **no breakability flag**: whether a case may be split at sale is decided downstream (Module A Layer 2 / Module S Layer 3), never as a property here.
_Avoid_: Format (a distinct entity), Case (the physical Case entity is Module B), breakability flag

**Sellable SKU**:
The commercial sellable unit, in two shapes. An **Intrinsic** SKU = one Product Reference + one Case Configuration + commercial attributes (commercial name, marketing copy); it is the **only** SKU shape that references a Case Configuration. A **Composite SKU** = a curated, ordered bundle of **N ≥ 2** constituent Product References, where one PR may recur across composites (many-to-many). PIM is **producer-agnostic** about a Composite's constituents — single-producer admissibility is a Module S Offer-publication rule, not a PIM rule. Both names kept unchanged by the naming cascade.
_Avoid_: Offer (the published commercial proposition — Module S), Voucher (the per-bottle entitlement), bundle (use Composite SKU)

**Product Type**:
A first-class classifier on the Product Master; at launch the **only** value is `WINE`, and constructing a Master of any other type is rejected (fail-closed). It is the switch selecting, per product, the per-type attribute set, the variant-defining dimension and the type-defined identity key. Modelled as a backed enum (not EAV / a rules engine); a future type is a new enum case plus its attribute table(s).
_Avoid_: category (overloaded), EAV, dynamic/configurable type

**Naming cascade (category-neutral)**:
The §18 rule that the category-neutral `Product*` names — Product Master, Product Variant, Product Reference, and the `ProductMaster*`/`ProductVariant*`/`ProductReference*` event families — are the canonical code and contract identifiers, while the former wine-specific names ("Wine Master", "Wine Variant", "Bottle Reference"/"BR") survive **only as wine-display aliases** (presentation/documentation), never as structural or event identifiers. Format, Case Configuration, Sellable SKU and Composite SKU keep their names unchanged.
_Avoid_: WineMaster / WineVariant / BottleReference as code or event names

### Catalog spine creation events — payload contract

On creation, each spine entity records its `*Created` Domain Event through the platform `DomainEventRecorder`, in the **same transaction** as the write, tagged module `catalog`, with the `ActorContext`-resolved `actor_role`, the entity type + id, and a **PII-free** payload (ids + non-PII business data only — a producer is referenced by id, never any party/personal data). No `*Activated`/`*Retired` event is recorded by this change (transitions are deferred to `catalog-lifecycle-approval`). The §14.1 event names keep `SKU` upper-case (`SellableSKUCreated`, `CompositeSKUCreated`) while the canonical model classes are `SellableSku`/`CompositeSku` (the cascade). The payload keys below are the published inter-module contract:

| Event (`name`) | `entity_type` | Payload keys |
|---|---|---|
| `FormatCreated` | `Format` | `format_id`, `name`, `size_label`, `volume_ml`, `lifecycle_state` |
| `CaseConfigurationCreated` | `CaseConfiguration` | `case_configuration_id`, `name`, `units_per_case`, `packaging_type`, `lifecycle_state` |
| `ProductMasterCreated` | `ProductMaster` | `product_master_id`, `name`, `product_type`, `producer_id`, `lifecycle_state` |
| `ProductVariantCreated` | `ProductVariant` | `product_variant_id`, `product_master_id`, `variant_identifier`, `lifecycle_state` |
| `ProductReferenceCreated` | `ProductReference` | `product_reference_id`, `product_variant_id`, `format_id`, `lifecycle_state` |
| `SellableSKUCreated` | `SellableSku` | `sellable_sku_id`, `product_reference_id`, `case_configuration_id`, `commercial_name`, `lifecycle_state` |
| `CompositeSKUCreated` | `CompositeSku` | `composite_sku_id`, `constituent_product_reference_ids`, `constituent_count`, `lifecycle_state` |

## Commerce & Membership

**Club**:
A producer's membership community. Members pay an annual fee equal to that year's Hero Package price.
_Avoid_: subscription, tier

**Hero Package**:
The producer's curated annual package whose price sets the club's membership fee for that year.

**Originating Club (OC)**:
The club through which a customer first joined. It accrues a 5% share on that customer's Discovery purchases (accrued at INV1).
_Avoid_: home club, referral club

**Discovery**:
The cross-producer storefront available to all members, distinct from each club's own offerings.
_Avoid_: marketplace, shop

**Offer**:
A published sellable proposition with its own lifecycle FSM.
_Avoid_: listing, deal, promotion

**Voucher**:
A customer's entitlement to one specific bottle, held in the Cellar until shipment; 7-state FSM.
_Avoid_: coupon, credit, token, NFT

**Club Credit**:
A monetary credit entity attached to a membership (Module K). Entirely distinct from Voucher.
_Avoid_: voucher, balance

**Cellar**:
The customer's digital holding of vouchers/bottles in storage, from which shipment is requested.
_Avoid_: wallet, inventory (reserved for Module B)

**INV1 / INV2 / INV3**:
The three invoice types — bottle sale (INV1), shipment with excise/VAT (INV2), storage fees (INV3). Issuance lifecycle is Module-S-internal.
_Avoid_: receipt, bill

## Supply & Inventory

**Allocation**:
The single supply primitive: a quantity of a wine lot made sellable by a producer/supplier under commercial terms. Carries the Layer-1 no-oversell guard (`qty − issued ≥ 0`).
_Avoid_: lot (use for wine lots), batch (reserved for InboundBatch)

**Sub-pool**:
The partition of an allocation into `qty_to_serialize` and `qty_non_serialized`. No-oversell is enforced per sub-pool.

**Passive consignment**:
The launch sourcing model: the producer retains ownership until the sale trigger; NewCo procures at 87.5% of producer price on club sales.

**Seller of Record (SOR)**:
NewCo sells to the consumer in its own name and carries the tax/compliance obligations.

**ProcurementIntent / PurchaseOrder**:
The two-level procurement gate in Module D preceding any inbound.

**InboundEvent**:
Two-phase receipt record: `PHYSICALLY_ACCEPTED` then `COST_FINALIZED`.

**InboundBatch**:
The physical receipt of bottles into the warehouse — Module B's inventory entry point.
_Avoid_: shipment (reserved for outbound), delivery

**StockPosition**:
Module B's five-dimension inventory view; the source of Layer-2 ATP.

**ATP (Available-to-Promise)**:
Sellable quantity guard. Layer 1 = allocation-level (Module A); Layer 2 = physical-inventory-level (Module B). Both must hold.

**Case**:
A physical case entity with its own FSM; breakability governed by the Layer-1 whitelist.

**SerializedBottle / NS**:
An NFC-tagged bottle with canonical serial vs the non-serialized (NS) universal fallback path. NFT mint/burn is decoupled behind a feature flag.

## Compliance & Finance

**Hold**:
A unified, trigger-agnostic restriction on a customer account (6 types: chargeback, storage non-payment, KYC, sanctions, …). Never auto-lifted.
_Avoid_: ban, block, suspension (suspension is a distinct escalation state)

**KYC / Sanctions screening**:
Four-state customer verification flows checked at onboarding and at every transaction-initiation surface.

**actor_role**:
The audit envelope field recorded on every operator action (`newco_ops`, `producer`, …).

**ownership_flag** (inventory):
Module B flag on stock: `PRODUCER` or `CRURATED`. Keys to the supplier-payment signal. **Never confuse with PO `ownership`.**

**ownership** (purchase order):
Module D enum on POs: `PRODUCER` / `NEWCO` / `THIRD_PARTY`. Keys to the sale/shipment signal. **Never confuse with inventory `ownership_flag`.**

**Dual-currency recording**:
Every customer-facing financial event records the customer-currency amount AND the EUR amount with a locked FX rate; refunds settle at the original captured rate.

**Money**:
An amount as integer minor units plus an ISO 4217 currency code — never a float or a major-unit decimal (invariant 6). The platform money primitive that Club Credit, prices, refunds and Dual-currency recording are all built on. Negative values are valid (credits, reversals).
_Avoid_: float, decimal amount, major units

**Currency**:
An ISO 4217 code with its minor-unit exponent. The launch set is exactly five: EUR (the base, exp 2), USD, GBP, CHF, JPY (exp 0). An unknown code is rejected (fail-closed), never assumed to be exp 2.
_Avoid_: locale (a currency is not a locale), money (use Money)

**FX Rate**:
The exact decimal string locking a customer-currency↔EUR conversion — never a float (binary-rounding drift would break the exact-rate refund, invariant 5). Stored verbatim; well-formedness only — economic validity (positivity, bounds, snapshot timing) is Module E policy.
_Avoid_: exchange rate as a float, conversion factor

**Dual-Currency Amount**:
The pure-representation bundle that realises Dual-currency recording: a customer-currency Money, its EUR-equivalent Money, the locked FX Rate and the rate's timestamp. Carries no FX policy (snapshot timing, buffers and per-leg lock moments are Module E).
_Avoid_: converted amount, EUR-only amount

## Events & Audit

**Domain Event**:
A named, versioned business fact that one module announces and other modules consume — the sole inter-module API (~120 in the launch catalogue). Immutable once recorded; consumers tolerate cross-transaction arrival order. Payloads reference parties by ID and never carry personal data.
_Avoid_: message, notification, webhook

**Audit Record**:
The immutable trace of an operator or system action: who (actor_role), what, when, before/after state, and the authorization basis. Write-only — inspected for compliance, never consumed by modules. Distinct from a Domain Event (a contract between modules).
_Avoid_: log entry, history

**Financial Event**:
A domain event recording a monetary fact (Module E's catalogue, ~30 types at launch). Dual-currency recorded, immutable post-sync, corrections only via compensating events (credit notes), retained 10 years.
_Avoid_: transaction (overloaded), ledger entry

**Actor context**:
The single seam that resolves the acting `actor_role` and actor id for the current context — the one place an emitter obtains who is acting. An authenticated operator (read from the `operator` session guard) yields (`newco_ops`, the `Operator` id); console, queue, unauthenticated contexts — and the not-yet-wired customer/producer guards — yield (`system`, `null`); a scoped run-as override beats both. The customer and producer guards are a deferred seam that extends the same precedence.
_Avoid_: session, current user, auth guard

## Platform Foundations

**Supported locale**:
One of the six launch locales — `en` (the fallback), `it`, `fr`, `de`, `ja`, `zh_Hans` — the closed set the registry validates against. A locale is a language plus an optional script in Laravel's underscore form (`zh_Hans` = Chinese, Simplified). All six are fixed at launch; adding one is configuration, not a migration.
_Avoid_: language (`zh_Hans` is language + script), currency

**Translatable text**:
Per-row content held as i18n-keyed JSON (`{locale: text}`) with per-attribute English fallback — the mechanism for translatable entity attributes (PIM product copy, etc.). Distinct from the `lang/` files, which carry static UI chrome shared across rows.
_Avoid_: lang/ string, label, caption

**Feature flag / EXT-1**:
A named gate an operator can toggle without a deploy. **EXT-1** (`nft-on-chain`) is the single launch flag — the one named gate for every on-chain surface (NFT mint/burn, custodial wallet, on-chain recovery, the Bottle-Page chain-link), shipped OFF. While it is off, the non-serialized (NS) path is the universal fallback; the per-bottle serialization workflow is never flagged (decoupled, not deferred).
_Avoid_: config toggle, kill switch, NFT switch (it gates more than mint/burn)

## Identity & Access

**Operator**:
A NewCo staff principal who authenticates to the Admin Panel. Carries one or more operator roles; every action it drives records `actor_role: newco_ops`. **Not** a Module K party — operators have no Customer/Producer/Supplier row, so for an Operator the login principal and the acting identity are one and the same record. Owned by the OperatorPanel module.
_Avoid_: user (overloaded), admin (a role, not the principal), Customer/Producer (those are parties, not staff)

**Authentication principal**:
The thin, first-party login record (credentials only) that authenticates an actor — a platform-foundation concern, never a business module's. For a Customer or Producer it **references the Module K party by id** and holds no business identity of its own (the party stays authoritative — a Customer is identity+eligibility, never a login); for an Operator the principal *is* the identity. The Actor context resolves `actor_role` + `actor_id` from the authenticated principal, where `actor_id` is the party/operator id — never the principal's own id. No external IdP at launch (EU-resident, first-party); social/SSO is a deferred seam.
_Avoid_: User, account (the Module K Account is a billing container), password-on-the-Customer

## Operations & Integrations

**Vinlock**:
The single French warehouse operator at launch; applies NFC tags at receipt.

**Logilize**:
The WMS (separate tenant). Module C owns its 4 fulfilment streams; Module B owns its 5 inventory-state streams (spec R3).

**White-glove**:
The manual fallback fulfilment path for complex destinations (D3).

**Shipping Order**:
Module C's 5-state outbound FSM with `compliance_hold` / `manual_review` flags; bottle selection is late-binding (FIFO + manual tiebreak at launch).
