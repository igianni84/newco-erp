# NewCo ERP — Ubiquitous Language

Glossary of record for the NewCo producer-club wine aggregator. Seeded from the v0.3-MVP spec; extended via grill-with-docs sessions as terms are resolved. Definitions only — no implementation details. Full semantics: the module PRDs in `spec/02-prd/`.

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
The single seam that resolves the acting `actor_role` and actor id for the current context. It defaults to `system` (console, queue, unauthenticated) and reads NO authentication state — it stays on the safe side of the identity/auth gate until that ADR wires it to an authenticated principal. The one place an emitter obtains who is acting.
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

## Operations & Integrations

**Vinlock**:
The single French warehouse operator at launch; applies NFC tags at receipt.

**Logilize**:
The WMS (separate tenant). Module C owns its 4 fulfilment streams; Module B owns its 5 inventory-state streams (spec R3).

**White-glove**:
The manual fallback fulfilment path for complex destinations (D3).

**Shipping Order**:
Module C's 5-state outbound FSM with `compliance_hold` / `manual_review` flags; bottle selection is late-binding (FIFO + manual tiebreak at launch).
