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

## Operations & Integrations

**Vinlock**:
The single French warehouse operator at launch; applies NFC tags at receipt.

**Logilize**:
The WMS (separate tenant). Module C owns its 4 fulfilment streams; Module B owns its 5 inventory-state streams (spec R3).

**White-glove**:
The manual fallback fulfilment path for complex destinations (D3).

**Shipping Order**:
Module C's 5-state outbound FSM with `compliance_hold` / `manual_review` flags; bottle selection is late-binding (FIFO + manual tiebreak at launch).
