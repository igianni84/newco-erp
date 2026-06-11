# NewCo ERP — Business Model Document

- **Version**: v0.9
- **Date**: 2026-05-09
- **Status**: **RELEASED v1.1 — 2026-05-09 (Stage 8 close per [`qa.s8.validation.md`](../03-qa/qa.s8.validation.md))** — folds DEC-185..DEC-196 (Stage 8 Module B inventory-authority restoration) into v0.8 at the BMD layer. Three substantive revisions (REQ-A §5.3 + §6 reframing — Logilize = execution arm, Module B = inventory authority per DEC-185; REQ-B §9.8 bottle-lifecycle restatement per InboundBatch entity ownership + cost-basis flow per DEC-195; REQ-C §6.3 + §11.3 Logilize-stream split per DEC-188 — Module C 4 fulfillment + Module B 5 inventory-state) + §6 four-way reconciliation discipline paragraph (NEW) + §5.1 receiving-discrepancy-split refresh per DEC-194 + §5.9 cross-link refresh per DEC-190 + §12.1 table refresh (12 new rows: DEC-185..DEC-196) + footer DEC-range bumps DEC-001..DEC-155 → DEC-001..DEC-196 + Glossary additions (InboundBatch, StockPosition, QuarantineRecord, Stocktake, InventoryAdjusted, InventoryShortfallDetected, Two-layer no-overselling guard, Four-way reconciliation discipline) + Logilize / Vinlock entries refreshed for inventory-authority split. v0.9 applies surgical edits only — structure and prose voice preserved from v0.8. v0.8 stays on disk for audit / diff.
- **Release history**: v1.1 — 2026-05-09 (Stage 8 close); BMD v0.8 RELEASED v1.0 — 2026-05-08 (Stage 7 close per DEC-004)
- **Owner**: Paolo
- **Predecessor**: [`NewCo_BusinessModel_v0.8.md`](NewCo_BusinessModel_v0.8.md) (RELEASED v1.0 2026-05-08 / Stage 7 close per DEC-004; superseded by v0.9 to fold Stage 8 Module B inventory-authority restoration — DEC-185..DEC-196 — at the BMD layer)
- **Successor**: TBD (Stage 8 / Phase F release stamp pending — `RELEASED v1.1 — <date>`)
- **Companion documents**: [`../04-decisions/decisions.md`](../04-decisions/decisions.md), [`../03-qa/qa.bm.md`](../03-qa/qa.bm.md), [`../03-qa/qa.mod0.md`](../03-qa/qa.mod0.md), [`../03-qa/qa.modK.md`](../03-qa/qa.modK.md), [`../03-qa/qa.modA.md`](../03-qa/qa.modA.md), [`../03-qa/qa.modD.md`](../03-qa/qa.modD.md), [`../03-qa/qa.modS.md`](../03-qa/qa.modS.md), [`../03-qa/qa.modB.md`](../03-qa/qa.modB.md), [`../03-qa/qa.modC.md`](../03-qa/qa.modC.md), [`../01-prd/Module_0_PRD_v0.2.md`](../01-prd/Module_0_PRD_v0.2.md), [`../01-prd/Module_K_PRD_v0.2.md`](../01-prd/Module_K_PRD_v0.2.md), [`../01-prd/Module_A_PRD_v0.1.md`](../01-prd/Module_A_PRD_v0.1.md), [`../01-prd/Module_D_PRD_v0.1.md`](../01-prd/Module_D_PRD_v0.1.md), [`../01-prd/Module_S_PRD_v0.1.md`](../01-prd/Module_S_PRD_v0.1.md), [`../02-elicitation/elicitation_v1.md`](../02-elicitation/elicitation_v1.md), [`../02-elicitation/elicitation_v2.md`](../02-elicitation/elicitation_v2.md)

### v0.9 changelog

The v0.9 consolidation folds Stage 8 Module B inventory-authority restoration (DEC-185..DEC-196 appended 2026-05-09) into v0.8. Stage 8 corrects v0.8's narrowing of Module B to a digital-provenance-only layer by restoring v17 §B.1's framing — Module B = ERP-side inventory authority (ledger discipline, entity ownership for InboundBatch / StockPosition / Case / QuarantineRecord, ATP source, receiving physical-match authority, stocktake + adjustment authority, two-layer no-overselling guard, committed-inventory protection); Logilize = physical-state execution arm (in-warehouse / in-transit / delivered / damaged / lost-in-custody state, pick-pack-dispatch execution, sub-warehouse storage-location detail). The four-way reconciliation discipline (Logilize ↔ Module B ↔ Module S ↔ Module E) is restored as a first-order design concern. Three REQUIRED substantive revisions + §6 intro four-way reconciliation paragraph (NEW) + §5.1 + §5.9 cross-link refreshes + §12.1 table refresh + footer / heading DEC-range bumps + Glossary additions. Structure and prose voice preserved from v0.8.

Edit categories applied in v0.9:

- **REQ-A — §5.3 WMS / Logilize integration framing reframed for Module B inventory authority (DEC-185 + DEC-188)**: §5.3 prose refreshed to distinguish the **workflow-execution axis** (pick-pack-dispatch; sub-warehouse storage-location detail per DEC-153 — Logilize is system of record) from the **inventory-state axis** (entity ownership for InboundBatch + StockPosition + Case + QuarantineRecord; ATP source per allocation; receiving physical-match check; stocktake + adjustment authority; provenance immutability; no-overselling at physical level; committed-inventory protection — Module B is system of record). v0.8's "Logilize = system of record for physical state, NewCo ERP = system of record for commercial state" framing is preserved on the workflow axis but extended on the inventory-state axis: Module B is the ERP-side inventory ledger; Logilize remains the physical-execution arm. The four-way reconciliation discipline (Logilize ↔ Module B ↔ Module S ↔ Module E) replaces v0.8's three-way regime (Logilize ↔ Module S ↔ Module E with Logilize as inventory-source-of-truth concentration).
- **REQ-B — §9.8 bottle-lifecycle restatement per InboundBatch entity ownership + cost-basis flow (DEC-195)**: §9.8 prose extended to name InboundBatch as the Module-B-owned logical container for goods arriving from a single source (PO or consignment receipt), with the v17 §B.2 attribute set: expected qty (from PO line), received qty (from Logilize physical match per DEC-194), serialization progress (`qty_planned_serialize` + `qty_actually_serialized` per DEC-186), ownership flag (per DEC-185 2-value enum at NewCo launch — `PRODUCER` or `CRURATED`), cost basis flow (provisional at PHYSICALLY_ACCEPTED → finalized at COST_FINALIZED per v13 Stage 2.3 split-inbound lineage), serialization-plan target. Bottle lifecycle states framed in terms of InboundBatch creation, Module B's physical-match check, and the cost-basis read at dispatch per Module C late-binding chain.
- **REQ-C — §6.3 + §11.3 Logilize-stream split per DEC-188 (supersedes DEC-140 on the inventory-state axis)**: §6.3 + §11.3 prose refreshed to name the 5-stream split — **Module C owns 4 fulfillment streams** (outbound pick instruction; pick confirmation; dispatch confirmation; delivery confirmation) per DEC-140's first four streams retained verbatim; **Module B owns 5 inventory-state streams** (storage-location tracking [Stream 5 migrated from Module C per the original DEC-140 framing]; receiving + physical-match per DEC-194; stocktake instruction + variance reporting per DEC-189; inventory-adjustment proposal-and-confirmation per DEC-190; QuarantineRecord resolution flow per DEC-191). The two-system split preserves DEC-140's clarity on the fulfillment side while restoring Module B's inventory-state authority.
- **REQ-D — §6 intro four-way reconciliation discipline paragraph (NEW; DEC-185 + Module B v0.2 §2.4)**: a new opening paragraph at the start of §6 names the four-way reconciliation discipline (Logilize physical execution ↔ Module B ERP-side inventory ledger ↔ Module S commercial state ↔ Module E financial state) restored as a first-order design concern, with each leg's role + the boundary lines between them. v0.8's three-way regime (Logilize + S + E with Logilize concentrating both physical execution and ERP-inventory-source-of-truth roles) is superseded; the four-way regime gives Module B independent ERP-side inventory-ledger authority, eliminating the vendor-risk concentration on Logilize and the Logilize-side-error-undetected failure mode.
- **REQ-E — §5.1 inbound sourcing receiving-discrepancy split (DEC-194)**: §5.1 prose refreshed to reference the receiving-discrepancy two-stage check — Module D is "documents in order" (3-gate inbound QC at PHYSICALLY_ACCEPTED per Module D PRD §7); Module B is "physical match" (Module B compares physically-counted bottles against the qty declared in Module D's `InboundEventPhysicallyAccepted` payload, emits `InboundBatchDiscrepancy` event back to Module D on variance, which reopens the InboundEvent into DISCREPANCY state). The two-stage check restores the v17 §B.9 KPI ("inbound physical-discrepancy rate <5%") that v0.8's Module-D-only acceptance gate could not detect.
- **REQ-F — §5.9 damages cross-link refresh per Module B v0.2 §13 inventory-adjustment workflow (DEC-190)**: §5.9 prose cross-link refreshed to reference the unified `InventoryAdjusted` event with `adjustment_type` discriminator (damage / loss / consumption / recount / transfer / found) emitted by Module B per DEC-190; Module E consumes for damage / loss / write-off financial-event recording per DEC-072. The existing Module C v0.1 / Module B v0.1 split per DEC-151 stays correct on the bottle-state axis (in-custody breakage = Module B per DEC-132; transit damage / loss = Module C per DEC-151).
- **REQ-G — §12.1 Decisions Register table refresh (DEC-185..DEC-196)**: 12 new rows appended in DEC-NNN order. DEC-185 + DEC-188 + DEC-194 + DEC-195 reference REQ-A / REQ-C / REQ-E / REQ-B BMD revisions respectively; DEC-186 / DEC-187 / DEC-189 / DEC-190 / DEC-191 / DEC-192 / DEC-193 / DEC-196 reference Module B PRD v0.2 scope (no BMD body revision); DEC-133 + DEC-140 supersession markers added in their respective rows.
- **REQ-H — Footer DEC-range bumps (6 sites)**: Reading-Guide bullet, §0.14 locked-decisions count, §12 intro paragraph, §12.1 heading, §12.1 intro paragraph, §12.1 footer — all updated DEC-001..DEC-155 → **DEC-001..DEC-196**. "155 decisions locked" → "**196 decisions locked**". "DEC-156+" → "**DEC-197+**" at append-only-register markers.
- **REQ-I — Glossary additions + refreshes**: 8 new entries added (**InboundBatch**, **StockPosition**, **QuarantineRecord**, **Stocktake**, **InventoryAdjusted**, **InventoryShortfallDetected**, **Two-Layer No-Overselling Guard**, **Four-Way Reconciliation Discipline**); existing **Logilize** entry refreshed to reflect physical-execution-arm role; existing **Vinlock** entry unchanged (warehouse operator at the workflow-execution layer); existing **Bottle Reference** + **Late Binding** + **Voucher** + **Storage Fee** entries unchanged. New entry **Ownership Flag** (formal, naming the 2-value enum at NewCo launch per DEC-185 + DEC-068 + DEC-001) added.

All other sections carry forward verbatim from v0.8.

---

## Document purpose

This Business Model Document (BMD) is the authoritative description of **what NewCo is, what it sells, to whom, on what commercial terms, and within what operating envelope**. It captures the business model — not the technical implementation. Per the seven-stage QA cadence (DEC-004), the BMD is Stage 2 and is the **hard gate** before any module-level PRD work begins.

The BMD is standalone. It is not written as a delta against the Crurated ERP PRD v17, even though NewCo borrows vocabulary and selected structural patterns from it. Where direct comparisons are useful for reviewers familiar with v17, those are isolated in **Appendix B**.

## Reading guide

- **BMD = what and why.** Business identity, customer model, producer relationship, commerce model, lifecycle, financial policy, jurisdiction, and operating envelope. No schemas, no APIs, no UX wireframes.
- **PRD = how.** Module-level technical design comes after BMD sign-off. Five waves (Wave 1 Foundations → Wave 5 Finance) per DEC-003.
- **Decision Register** is the source of truth. The BMD embeds DEC-001..DEC-196 inline at the relevant section and lists them all in §12.1. The Decision Register is append-only; new decisions taken during ongoing review will be appended there as DEC-197+.
- **Open questions** flagged Q-OQ-N are collected in **Appendix C** with proposed handling. Items resolved in v0.2 (via DEC-032..058) drop out of the active register; only the still-deferred items remain.
- **Conventions**:
  - Defined business terms appear **bold at first use** and are listed in **Appendix A — Glossary**.
  - Cross-section references use §section numbers (e.g., "see §2.3").
  - References to Crurated v17 use the v17 PRD's section numbers (e.g., "v17 §0.7"); the v17 PRD is FROZEN and never edited from this track.
  - Domain events are written in `MixedCase` shorthand (e.g., `MembershipApprovedByProducer`) — these are *business-level event names*, not technical schemas. The PRD will refine them. Note on the membership-event family: the BMD uses `Membership*` names as readable business shorthand; Module K PRD v0.2 §15 has standardised the canonical event family on `Profile*` (e.g., `ProfileActivated`, `ProfileRenewed`, `ProfileSuspended`, `ProfileReactivated`, `ProfileExpired`) — both name families refer to the same underlying business events.

## Top-line model in three sentences

NewCo is a producer-club aggregator for fine wine: collectors apply to one or more producer clubs, pay an annual fee that equals the price of that year's producer-curated **Hero Package**, and from the NewCo portal manage all their memberships, browse a global cross-producer **Discovery Tab**, store purchases in their cellar, and request shipment when ready. NewCo is the **Seller of Record** on every transaction, taking goods on **passive consignment** from producers; on club sales NewCo cuts a **PO at 87.5% of the producer-set price** and retains a **12.5% margin**, while on Discovery sales NewCo negotiates an allocation cost `C` with the producer, sets the customer-facing Discovery price `P_d` itself, captures the spread `P_d − C` as gross margin, and pays **5% of `P_d`** to each buyer's **Originating Club**. Vinlock operates the warehouse in France, Logilize is the WMS, Avalanche carries the NFT layer, Airwallex processes payments, HubSpot drives CRM, and Xero plus an Italian SDI connector handles accounting.

---

## Table of Contents

- [§0 Executive Summary](#0-executive-summary)
- [§1 Business Identity and Positioning](#1-business-identity-and-positioning)
- [§2 Customer and Membership Model](#2-customer-and-membership-model)
- [§3 Producer Relationship](#3-producer-relationship)
- [§4 Commerce Model](#4-commerce-model)
- [§5 Sourcing and Fulfillment](#5-sourcing-and-fulfillment)
- [§6 Serialization, NFC, and NFT Model](#6-serialization-nfc-and-nft-model)
- [§7 Front-End Surfaces (Business Responsibility, not UX)](#7-front-end-surfaces-business-responsibility-not-ux)
- [§8 Monetization and Financial Policy](#8-monetization-and-financial-policy)
- [§9 Lifecycle and Canonical Flows](#9-lifecycle-and-canonical-flows)
- [§10 Geography, Jurisdiction, Compliance](#10-geography-jurisdiction-compliance)
- [§11 Operating Model and External Systems](#11-operating-model-and-external-systems)
- [§12 Decision Log and Open Questions](#12-decision-log-and-open-questions)
- [§13 Out of Scope (explicit)](#13-out-of-scope-explicit)
- [Appendix A — Glossary](#appendix-a--glossary)
- [Appendix B — Deltas vs Crurated v17](#appendix-b--deltas-vs-crurated-v17)
- [Appendix C — Open Questions Register](#appendix-c--open-questions-register)

---

## §0 Executive Summary

> Synthesis of the business model: identity, customer, producer, commerce, fulfillment, finance, and the boundaries that keep NewCo focused at launch.

### §0.1 Top-line model

**NewCo is a producer-club aggregator for fine wine, with a global cross-producer Discovery layer and end-to-end fulfillment capability.** Collectors apply to one or more producer clubs, are approved (or waitlisted) at the producer's discretion, and pay an annual membership fee that equals the price of that year's producer-curated **Hero Package**. From a single NewCo Consumer Portal account they manage all memberships, browse Discovery, store purchases in a cellar at a single Vinlock-operated French warehouse, and request shipment when ready. NewCo is **Seller of Record** on every transaction and takes goods under **passive consignment** from producers; on club sales NewCo retains a **12.5% margin** (producer PO at 87.5% of the producer-set price), and on Discovery sales NewCo negotiates an allocation cost `C` per allocation, sets the customer-facing Discovery price `P_d`, captures the spread `P_d − C` as gross margin, and pays **5% × `P_d`** to each buyer's **Originating Club**.

### §0.2 What NewCo Sells, To Whom, How

- **What**: fine wine (still wine, champagne, spirits) — bottles, cases, mixed packages, verticals. **Club mixed-cases are always single-producer** (DEC-019, retained for clubs); **Discovery may publish multi-producer composite SKUs** inheriting the Crurated v17 §1.4 pattern (DEC-061). Liquid/pre-bottling sales are OUT at launch.
- **To whom**: individual collectors only — three customer segments (Member, Waiting-list applicant, Legacy), all consumer. **No B2B at launch** (DEC-017).
- **How**: every transaction is mediated by a producer relationship — either through the producer's **Club Page** (members-only, often lower-priced) or through the curated **Discovery Tab** (all NewCo customers, often higher-priced for the same Bottle Reference). Same Bottle Reference can appear on both at producer's discretion (DEC-023).

### §0.3 The Hero Package = Membership Mechanic (DEC-007)

The structural primitive of NewCo's customer model:

- The **Hero Package** is a producer-curated mixed case, released once per club year.
- The Hero Package's price = the year's annual membership cost.
- At registration / annual renewal, the customer pays this fee and receives the Hero Package as Vouchers in their cellar.
- If the package value < cost, the difference becomes **Club Credit** spendable on that club only. If the package value > cost, the customer still pays only the committed cost.
- **# of members = # of Hero Packages** the producer makes available — the upper bound on club capacity.
- Memberships **auto-renew** annually; both Customer and Producer can decline to renew.

### §0.4 The Discovery Tab (DEC-008)

NewCo's second core surface, alongside producer clubs:

- **Curated cross-producer marketplace**, accessible to all customer segments.
- Producer's discretion: an Allocation can be **CLUB_ONLY**, **DISCOVERY_ONLY**, or **BOTH** (DEC-023).
- Discovery extends NewCo's reach beyond customers' own producer-club memberships.
- 5% of the customer-facing Discovery price `P_d` flows to the buyer's **Originating Club** — the first club to approve them, locked at first approval, persisted for life (DEC-008, DEC-010, DEC-066). The basis is `P_d` (headline customer price), not the gross-margin spread `P_d − C` — see §3.6 and §8.14.

### §0.5 The Producer-Side Deal (DEC-010, DEC-032)

- **No producer-side fee** of any kind (no listing fee, no platform fee, no slotting allowance).
- **Club sales**:
  - Producer **sets the customer-facing price** on every club offer.
  - NewCo cuts a **Producer PO at 87.5% of the customer-facing price** on every club sale; NewCo retains **12.5%** as gross margin.
- **Discovery sales** (DEC-032 — different mechanic from club):
  - NewCo and the producer **negotiate an allocation cost `C`** per Discovery allocation (per-allocation, not formulaic).
  - **NewCo sets the customer-facing Discovery price `P_d`** itself; the producer does not set the Discovery price.
  - On sale, the producer of the bottle is paid `C`; NewCo's gross margin on the transaction is `P_d − C`.
  - **5% × `P_d`** is paid to the buyer's **Originating Club** producer (which can be a different producer than the bottle's). NewCo nets `P_d − C − 5% × P_d`.
  - A producer of a Discovery allocation does **not** need to operate a NewCo club — Discovery-only suppliers are admitted on the same eligibility criteria as club producers.
- **Settlement**: invoice-driven, quarterly by default, EUR. Cadence configurable per producer agreement (DEC-042).
- **Default sourcing**: passive consignment V2 (stock at Vinlock); V1 (stock at producer) for exceptionally rare bottles; **direct purchase** admitted as exceptional third option (NewCo pays at purchase, takes title at purchase) for strategic stock-up or where producers prefer outright sale (DEC-063). Sourcing model is independent of the club-vs-Discovery commercial mechanic.
- **Producer recruitment**: outbound only at launch; **5–10 producers committed** at launch (target 100+ within 24 months).
- **Producer discretion** on approve / reject / kick / non-renew is absolute.
- **Producer offboarding**: customer-facing obligations stay with NewCo regardless.

### §0.6 The Three Customer Segments (DEC-012)

| Segment | Producer-page access | Discovery access | Storage | Pays storage fees |
|---------|---------------------|------------------|---------|-------------------|
| Member | Yes | Yes | Yes | Yes (after 12 free months) |
| Waiting-list applicant | No | Yes | Yes | Yes |
| Legacy (ex-member with cellar) | No | Yes | Yes | Yes |

Customer-Profile-Membership: Netflix-style — one Customer record, one login per Customer, one Profile per club Membership, profile switcher inside the Consumer Portal.

### §0.7 Sourcing, Custody, Fulfillment

- **Sourcing**: passive consignment V2 default; V1 for very expensive / very rare bottles (DEC-011); direct purchase as exceptional third option (DEC-063).
- **Custody**: single Vinlock-operated warehouse in France (DEC-014). Same physical site as Crurated, separate contracts.
- **WMS**: Logilize (separate integration from Crurated's).
- **Late binding**: specific physical bottle assigned to a customer's voucher at shipment, not at sale.
- **Storage policy** (DEC-013): 12 months free per bottle; €3 / bottle / year thereafter; charged every 6 months or at shipment.
- **Shipping**: customer-paid quote; ~25 destination countries; case-by-case alcohol-restriction handling.
- **No returns post-shipment** (E10).

### §0.8 Serialization, NFC, NFT

- **Default-on serialization**: every bottle gets an NFC tag (applied by Vinlock under NewCo direction at warehouse receipt).
- **NFT minted on Avalanche** at NFC tag application; held in NewCo's wallet during custody; **burned at shipment**.
- **Bottle Page**: public, anonymous, NFC-scannable; provenance-focused; six launch locales (EN + IT + FR + DE + JP + ZH).
- **Non-serialized stock** as a producer-by-producer or product-by-product exception path.
- **NFT legal status**: provenance / authentication artifact, **not legal title** (working stance pending legal review).
- **Four NFC/NFT recovery scenarios** with proposed defaults in §6.11 per DEC-022.

### §0.9 The Four Front-End Surfaces

| Surface | Primary user | Mobile | Localization at launch |
|---------|--------------|--------|------------------------|
| Admin Panel | NewCo ops only | Desktop-first | EN + IT |
| Consumer Portal | Customers (all segments) | Web + mobile-web (no native) | EN + IT + FR + DE + JP + ZH |
| Producer Portal | Producer staff | Web + mobile-web | EN + IT (+ producer-opt-in) |
| Bottle Page | Public, anonymous | Mobile-first | EN + IT + FR + DE + JP + ZH |

**No SSO across surfaces**; profile switcher inside Consumer Portal (DEC-024).
**No native mobile apps** at launch (DEC-018).

### §0.10 Financial Posture

- **Revenue streams**: 12.5% margin on club sales; spread `P_d − C` net of 5% Originating-Club share on Discovery (DEC-032); storage fees; (services / experiences as TBD secondary line).
- **No producer-side fee**; **no customer-side platform fee** beyond Hero Package.
- **VAT regime**: MPV (Multi-Purpose Voucher), recognized at redemption (Crurated v17 lineage).
- **Multi-currency** at launch; EUR base ledger; FX policy mid-rate-plus-buffer.
- **Payments**: Airwallex (DEC-014) — card + bank transfer; multi-currency capture; saved cards for auto-renewal and storage charges.
- **CRM and email**: HubSpot (DEC-014).
- **Accounting**: Xero + Italian SDI connector (DEC-028).
- **Producer settlement**: quarterly by default, invoice-driven, net-30 working terms.

### §0.11 Compliance and Geography

- **Country of incorporation**: Italy (likely; DEC-015).
- **VAT registrations**: Italy + France (warehouse-driven); other countries as volume warrants.
- **Customer markets**: ~25 countries, case-by-case alcohol-restriction handling.
- **GDPR posture** (DEC-027): standard Italian / EU; soft-delete with anonymization for customers holding residual cellar; 10-year invoice retention; internal DPO; 72h breach notification to Italian Garante.
- **Sanctions screening** (DEC-030, DEC-041): light EU + Italian UIF + OFAC at onboarding (US is in the launch destination set per DEC-041); 12-month re-screen.
- **Data residency** (DEC-029): EU (Italian / French preferred); third-party SaaS configured for EU residency; on-chain non-PII only.
- **Cancellation**: 14-day Italian / EU consumer-law withdrawal right (working baseline).

### §0.12 What Is Out at Launch (§13)

- B2B / wholesale (DEC-017).
- Active consignment, drop-shipping, liquid sales (DEC-011, L4, L5).
- CruTrade-style P2P trading (L2).
- Native mobile apps (DEC-018).
- AI / Operator Copilot (DEC-021).
- Multiple warehouses / multi-site custody (E3).
- Death / inheritance / corporate dissolution policy (deferred, K8).
- Customer support tooling (deferred, J8).
- Intragroup mechanics with Crurated (DEC-001 / DEC-020).
- Data migration (greenfield, L7).

### §0.13 Strategic Trajectory

- **Launch**: 5–10 producers, single warehouse, six locales on customer surfaces.
- **24 months**: 100+ producer clubs.
- **Beyond fine wine**: same aggregator pattern extends to other luxury verticals (watches, leather goods); NewCo's data model and abstractions are designed wine-agnostic where extensible alternatives exist.

### §0.14 What's Locked, What's Open

- **196 decisions locked** (DEC-001..DEC-196, §12.1) — DEC-032..058 added 2026-05-02 from Paolo's review of v0.1 via [`qa.bm.md`](../03-qa/qa.bm.md); DEC-059..062 added 2026-05-02 from BMD v0.2 review and Wave 1 kickoff; **DEC-063** added 2026-05-02 from Wave 1 Module 0 elicitation Q-CL-2 follow-up; **DEC-064 + DEC-065** added 2026-05-03 from Wave 1 Module 0 elicitation; **DEC-066..DEC-071** added 2026-05-03 from Wave 1 Module K elicitation; **DEC-072** added 2026-05-03 from BMD v0.4 review (methodology — BMD / PRD do not take accounting policy positions); **DEC-073 + DEC-074** added 2026-05-03 (PRD-drafting methodology — PRD scope = product-spec layer; PRDs are self-contained delivery documents; refine DEC-060); **DEC-075..083** added 2026-05-04 from Wave 2 Module A elicitation; **DEC-084..094** added 2026-05-04 from Wave 2 Module D elicitation; **DEC-095..118** added 2026-05-04 from Wave 3 Module S elicitation (the substantive Wave-3 BMD revisions are DEC-108 / DEC-117 / DEC-118); **DEC-119** added 2026-05-06 during Wave 3 Module S PRD v0.1 review — flips storage-fee ownership Module E → Module S (supersedes DEC-118 ownership clause; DEC-118 mechanics preserved) + refines storage-clock-start trigger to bottle-at-warehouse-condition AND first-12-months-free-from-purchase double anchor (REQ-1 v0.8); **DEC-120..155** added 2026-05-08 from Wave 4 Module B + Module C elicitation 49-atom resolution; **DEC-156..184** added 2026-05-08 from Wave 5 Module E elicitation (Module-E payment / settlement / Xero / multi-currency / chargeback / refund / non-revenue cost catalogue; DEC-180..184 cross-cutting closure DECs); **DEC-185..DEC-196** added 2026-05-09 from Stage 8 Module B inventory-authority restoration elicitation (Phase A `qa.modB.s8.md`) — DEC-185 (Module B inventory-authority restoration scope), DEC-186 (NS at InboundBatch level — supersedes DEC-133), DEC-187 (ATP feed pattern Module B → Module A push), DEC-188 (Logilize stream split — Module C 4 fulfillment + Module B 5 inventory-state — supersedes DEC-140), DEC-189 (Stocktake authority + 4-state lifecycle), DEC-190 (Inventory-adjustment workflow + event catalogue), DEC-191 (QuarantineRecord entity + quarantine-before-trust), DEC-192 (Case entity + 3-state integrity FSM; recorder-not-gatekeeper), DEC-193 (ConsignmentPlacement deferred at NewCo launch), DEC-194 (Receiving discrepancy authority restored — Module D = documents, Module B = physical match), DEC-195 (InboundBatch entity ownership at Module B + cost-basis flow), DEC-196 (StockPosition aggregated view at 5-dimension intersection). The substantive v0.9 BMD revisions are **DEC-185 (REQ-A §5.3 reframing + REQ-D §6 four-way reconciliation paragraph), DEC-188 (REQ-C §6.3 + §11.3 Logilize-stream split), DEC-194 (REQ-E §5.1 receiving-discrepancy split), DEC-195 (REQ-B §9.8 bottle-lifecycle restatement)**; the remaining Stage 8 DECs (DEC-186 / DEC-187 / DEC-189 / DEC-190 / DEC-191 / DEC-192 / DEC-193 / DEC-196) are Module B PRD v0.2 scope (no BMD body revision) but compose into the §6 four-way reconciliation framing and the Glossary additions.
- **8 open questions still deferred** (Q-OQ-2 / 5 / 6 / 7 / 8 / 9 / 10 / 11; §12.2 + Appendix C). All Wave 1 Q-OQs (Q-OQ-2, Q-OQ-8, Q-OQ-9, Q-OQ-10, Q-OQ-11) re-confirmed deferred under DEC-058 during Wave 1 elicitation 2026-05-03.
- **All six BMD-drafting decisions resolved** (D-A1..D-A6, §12.3) — each promoted into a DEC during v0.2.

The BMD is the **hard gate** before module-level PRD work begins (DEC-004 / DEC-006). On Paolo's sign-off, the five-wave PRD drafting (DEC-003) starts with Wave 1 — Foundations (Module 0 + Module K).

---

## §1 Business Identity and Positioning

> What NewCo is, who it serves, the strategic rationale for the producer-club aggregator model, and the brand architecture across NewCo and the producer-branded club pages.

### §1.1 What NewCo Is

**NewCo** is a producer-club aggregator for fine wine, with a global cross-producer Discovery layer and end-to-end fulfillment capability. The legal entity name is TBD (DEC-001 leaves the venture name open; "NewCo" is the working placeholder used throughout this document and the Decision Register). NewCo is a separate venture from Crurated; the ownership / commercial structure between the two is OUT OF SCOPE for this BMD (DEC-001).

NewCo plays four simultaneous roles:

1. **Aggregator of producer-club memberships** — the primary customer surface. A collector applies to one or more producer clubs (e.g. "Club Pepe", "Club Dancer"), is approved by the producer, and from a single NewCo account manages all memberships, allocations, and stored bottles.
2. **Cross-producer marketplace** — the **Discovery Tab** is a curated global catalog drawn from any producer (and from Crurated as one commercial supplier among others, per DEC-020). Discovery is open to all NewCo customers — Members, Waiting-list applicants, and Legacy ex-members.
3. **Tech infrastructure for producers** — many fine-wine producers run their direct-to-consumer channel "with pen and paper". NewCo provides the application, allocation, ordering, voucher, and reporting backbone so a producer can run a club without operating any of the underlying technology.
4. **Logistics operator** — NewCo is the **Seller of Record** on every transaction, takes physical custody of stock at a single Vinlock-operated warehouse in France (DEC-014), and executes fulfillment globally to ~25 destination countries.

### §1.2 Customer Promise

To the collector, NewCo promises:

- **Curated access** — a route into producer clubs that are otherwise hard to find, opaque, or invitation-only. The producer retains absolute discretion on approval (DEC-012, B2/C16).
- **One account, many clubs** — Netflix-style, a single login with a profile per membership and a profile switcher inside the Consumer Portal (DEC-024). One club = one membership = one profile (B5/B7).
- **A trusted cellar** — bottles are held in temperature-controlled custody at Vinlock until the customer is ready to ship; serialization by NFC tag and on-chain record on Avalanche back the provenance claim (DEC-014, F4).
- **Discovery beyond their clubs** — even outside their producer's own allocations, the customer can shop a global cross-producer catalog from the same account (DEC-008).
- **A clear deal** — annual membership cost equals the price of that year's producer-curated **Hero Package**; if the package is cheaper than the membership cost the difference becomes a **Club Credit** spendable on that club; if it is more expensive the customer still pays only the membership cost (the cost can never exceed the package value) (DEC-007).

### §1.3 Producer Promise

To the producer, NewCo promises:

- **A turnkey direct-to-consumer channel** — application intake, member approval, allocation publication, order management, voucher tracking, fulfillment, and customer reporting all run through the Producer Portal. The producer brings the wine and the brand; NewCo brings the system and the warehouse.
- **Commercial control with operational off-load** — the producer sets pricing, decides what enters the club vs Discovery, decides who is approved or rejected, and decides Hero Package composition each year (DEC-023, B2, C5, C16). NewCo does not edit producer commercial decisions.
- **Predictable economics** — the producer always receives 87.5% of the customer-facing price on club sales (DEC-010). On Discovery sales (DEC-032), economics differ: NewCo and the producer negotiate an allocation cost `C` per Discovery allocation; the producer is paid `C` per unit sold (not a formulaic 87.5% of the customer-facing price); NewCo sets the customer-facing Discovery price `P_d` itself and captures the spread `P_d − C` as gross margin, of which 5% × `P_d` is paid to the buyer's **Originating Club** (see §3.6 and §8.14). Settlement is invoice-driven and quarterly by default (C10/H10, DEC-042; see §8.10).
- **Buyer-level visibility on club sales, aggregate visibility on Discovery sales** — for purchases on the producer's own club page, the producer sees buyer identity in real time; for Discovery purchases of the producer's wines, the producer sees only the aggregate amount, never the buyer (C13).
- **No producer-side fee** — there is no listing fee, joining fee, platform fee, or membership fee charged to the producer (DEC-010, C6, H1). NewCo's monetization is entirely the margin on resale and the storage fee.

### §1.4 Why This Model — Strategic Rationale

The producer-club aggregator pattern resolves three structural frictions in fine-wine DTC:

- **Producers want a club but not a tech stack.** Most fine-wine producers, particularly at the boutique end, lack engineering and operations capacity to run a real DTC channel. NewCo replaces that with a single integrated system.
- **Collectors want curated access, not yet another store.** Mid-market wine commerce is saturated. The aggregator pattern reframes the offer as access to producer-mediated allocations rather than another generalist e-commerce surface.
- **Storage and logistics are the recurring pain.** Bottles bought one or two cases at a time over a year are a fulfillment problem the customer cannot solve themselves. The Voucher model with late binding (DEC-011, E11) plus storage-as-a-service (DEC-013) turns this from a problem into a feature.

The Discovery Tab (DEC-008) bolts onto the aggregator pattern: once the customer base, the warehouse, the payment stack, and the membership model are in place, the marginal cost of a global cross-producer marketplace is small, and the cross-sell is real. Discovery also gives Waiting-list applicants and Legacy ex-members a meaningful surface other than waiting (DEC-012), which is what keeps them in the customer base.

### §1.5 Strategic Trajectory

- **Launch** — NewCo opens with **5–10 anchor producers already committed** (A5). The product surface at launch is the four front-ends described in §7 plus the operational stack in §11, with all features described in this BMD.
- **24 months** — target is **100+ producer clubs** (A4). The platform is designed to scale to that count without re-architecting the customer-profile-membership model (§2.2) or the allocation-visibility model (§4.5).
- **Beyond fine wine** — the long-term thesis is that the same aggregator model extends to other luxury verticals (e.g., curated allocations of high-end watches, leather goods, etc.). The fine-wine ERP must therefore avoid hardcoding wine-specific assumptions in the customer, membership, allocation, voucher, and fulfillment layers where extensible alternatives exist. This BMD records that intent; the PRD will specify which abstractions stay wine-agnostic.

### §1.6 Brand Architecture

NewCo runs a **hybrid brand model** (A6):

- **NewCo-branded chrome** — the portal frame, navigation, account, cellar, checkout, and the Discovery Tab itself are unambiguously NewCo-branded.
- **Producer-branded club pages** — each producer's club has its own page accessible via a switcher in the Consumer Portal. Each club page carries the producer's own logo, color palette, photography, and editorial voice. Producer brand presence on the club page is owned by the producer (subject to NewCo content guidelines, TBD); NewCo brand presence on the club page is minimal and frame-only.
- **Bottle Page** — public, NFC-scanned, anonymous to the public (DEC-024, F9). Brand presentation on the bottle page is owned by NewCo and curated centrally; producer-supplied content (provenance, tasting notes) is incorporated.
- **Producer Portal** — internal-facing for producer users; no external brand presence; standard NewCo chrome.
- **Admin Panel** — internal-facing for NewCo ops; no external brand presence.

The hybrid is intentional: the NewCo brand is the **promise of trust, curation, and logistics**; the producer brand is the **promise of the wine itself**. The two co-exist on the same screen and reinforce each other.

### §1.7 Boundaries — What NewCo Is Not

For positioning clarity, NewCo is explicitly **not**:

- **Not a generalist wine e-commerce store.** All commerce is mediated by either a producer's club or the producer-curated Discovery surface — there is no open SKU-by-SKU storefront unmediated by producer relationships.
- **Not a B2B / wholesale platform.** Consumer-only at launch (DEC-017). No wholesale customers, no on-trade, no resale tier, no B2B credit terms.
- **Not a négociant or distributor.** Producer eligibility is restricted to actual producers — wineries, champagne houses, distilleries, etc. Négociants and distributors are excluded at launch (C1, see §3.1).
- **Not a P2P marketplace.** No collector-to-collector resale at launch; CruTrade-style P2P is OUT for NewCo at launch (DEC-008 envelope, L2). Member-to-member gifting is the only customer-to-customer path (K7, B14, see §4.13).
- **Not a producer-curated single-house store.** Each producer's club is one of many; a customer can hold and switch between multiple memberships (B6).
- **Not Crurated.** Crurated may appear as one Discovery supplier on commercial-only terms (DEC-020), but NewCo runs an independent identity, customer base, accounting stack, blockchain, and payment provider (DEC-014). Intragroup mechanics with Crurated are OUT (DEC-001, see §13.11).

---

## §2 Customer and Membership Model

> The three customer segments (Member, Waiting-list, Legacy), the Netflix-style Customer-Profile-Membership model, the Hero Package = membership fee mechanic, suspension and recovery paths, storage eligibility, and the consent posture for marketing and privacy.

### §2.1 Three Customer Segments

NewCo recognizes **three customer segments**, all individual collectors (no B2B per DEC-017). All three exist within the same Customer entity; the segment a customer occupies depends on the *state* of their membership(s), not on a separate identity (DEC-012).

| Segment | Defined as | Producer-club page access | Discovery access | Cellar / Storage | Pays storage fees |
|---------|-----------|---------------------------|------------------|------------------|-------------------|
| **Member** | Has at least one approved, active membership | Yes — for clubs they are members of | Yes | Yes | Yes (per §2.7) |
| **Waiting-list applicant** | Has at least one application not yet approved, **and no approved membership** | No | Yes | Yes (only for Discovery purchases) | Yes |
| **Legacy** | Was a member; no longer active; still holds at least one unredeemed Voucher or stored Bottle | No | Yes | Yes (only for residual holdings + Discovery purchases) | Yes |

The three segments are not exclusive over time but are exclusive at any moment: a Customer with one approved membership and one pending application is a **Member** (the strongest segment governs access logic). A Customer who loses their last active membership but still has stored bottles transitions to **Legacy**.

**Target collector profile** (B1): the classical fine-wine collector. Predominantly male, ages 35–60. Detailed persona profile to follow (Q-OQ-10).

### §2.2 Customer ↔ Profile ↔ Membership Model

NewCo uses a **Netflix-style profile model** (B7, DEC-024):

- **Customer** — one natural person, one Customer record, one login. The Customer record holds identity (name, contacts, KYC fields, billing preferences, language preference, privacy/marketing consent state).
- **Profile** — one per club membership. A Profile is the customer's identity *inside that club*: it tracks which Allocations the customer has bought from that club, the club credit balance for that club, the billing history for that club's renewals, and the per-club preferences.
- **Membership** — the relationship between a Customer (via a Profile) and a Club. A Membership has a state machine (Applied → WaitingList → Approved → Active → Suspended/Lapsed/Cancelled — see §2.6). One Membership = one Profile (B5/B7).

A Customer with three approved memberships has one Customer record, three Profiles, three Memberships. The Consumer Portal opens at the Customer level and the customer switches between Profiles via a profile switcher.

**Cross-cutting state lives at the Customer level**, not the Profile level: the **Cellar** (stored bottles), Discovery purchases, identity, KYC/sanctions screening result, password, language preference, and marketing/privacy consent are all customer-level. Anything specific to a single producer relationship — Hero Package eligibility, club credit, club-specific billing history — lives at the Profile level.

The **Originating Club** (DEC-008/DEC-010) is a Customer-level field that locks the *first* club to approve the Customer. It does not change if the Customer later joins more clubs. It drives the 5% Discovery revenue share (§8.14) for the rest of the Customer's life on the platform.

### §2.3 Hero Package = Membership Fee (DEC-007)

The **Hero Package** is the structural primitive of NewCo's membership model.

**Definition.** A **Hero Package** is a producer-curated mixed case released once per club year. The producer chooses what bottles go into it, in what quantities, and at what total price. The price of the Hero Package *is* the annual membership cost for that club for that year.

**Annual event.** At registration (first approval) and at each renewal anniversary, the customer pays the Hero Package price. In return:

1. The Membership becomes Active for the next 12 months.
2. The customer receives the Hero Package as a Voucher in their cellar (the bottles are not shipped automatically; late binding and the standard Voucher lifecycle apply per §4.4 and §5.5).

**# of members = # of Hero Packages** (B2). The producer decides how many Hero Packages to make available for the year; that number is the upper bound on the active Members in the club for that year. A producer aiming for 100 active members must source 100 Hero Packages. **The Hero Package count is mutable mid-year** (DEC-069): a producer may scale up by adding Hero Packages to the year's allocation if demand exceeds the original commitment (e.g., 50 packages at year-start, sold out after 3 months, producer adds 20 more for 20 more members). The capacity invariant (`# active Members ≤ # Hero Packages`) is always evaluated against the *current* count. Capacity decreases below the count of currently-active Members are not supported (would orphan members). When capacity rises, waitlist applicants gain eligibility to be approved (priority order is producer-discretionary at launch — no FIFO auto-conversion).

**Club Credit when the package is cheaper than expected.** Producers commit to a price for the Hero Package at the start of the club year. If the actual package value (sum of constituent bottle values at producer-set prices) ends up *lower* than the committed price, the difference accrues to the Member as **Club Credit** spendable on that club only (DEC-007). Example: producer commits to a €600 Hero Package for the year, ultimately delivers €580 of wine, the Member has a €20 Club Credit on that club's profile.

**The price floor is asymmetric** (B2). The Hero Package value can never *exceed* the committed price — i.e., the Customer never pays more than the committed membership cost in a given year, even if the producer over-delivers. This is a customer-protection rule.

**Dual nature of the registration / renewal transaction.** The annual Hero Package payment is *both* a membership state change *and* a purchase order. The same payment event generates a Hero-Package sale event (NewCo retains 12.5% margin and the producer is settled 87.5% per DEC-010), issues one or more Vouchers in the customer's cellar, and updates the Membership state. The PRD will model this as a single transaction with multiple downstream effects in Modules S (sales), K (parties / membership), and E (events); downstream accounting (Xero per DEC-028 / DEC-072) determines treatment from those events. The BMD records the principle here.

**Domain events at registration / renewal**:
- `MembershipApplicationSubmitted` (when customer applies)
- `MembershipApprovedByProducer` (when producer approves)
- `HeroPackagePurchased` (the registration / renewal transaction)
- `VoucherIssued` (one or more, for the Hero Package contents)
- `MembershipActivated` (the membership becomes Active)
- `ClubCreditAccrued` (if the package value < cost)

### §2.4 Annual Renewal and Auto-Renewal

- **Auto-renewal is the default** (B2). At the end of the 12-month membership period the system attempts the renewal payment using the customer's stored payment method.
- **Customer can cancel auto-renewal at any time** before the renewal date through the Consumer Portal. Cancelled-but-not-yet-expired memberships continue to give full access until the end of the paid period.
- **Producer can refuse a renewal.** Renewal eligibility is the producer's discretion (parallel to initial approval). If the producer declines to renew, the renewal is not charged and the Membership lapses at the end of the period.
- **Renewal pricing**. Producers can adjust the Hero Package price each year, but the **baseline expectation is year-over-year price stability** — producers may vary the price as their cost base requires, but the platform's working assumption is no annual hike (DEC-033). NewCo notifies the customer of the upcoming renewal and the price **30 days before period end** (DEC-033); the cadence is a customizable platform setting and may be tightened or loosened by ops configuration without re-architecture.
- **Failed renewal payment.** Standard dunning: retry sequence + customer notice. If the renewal cannot be collected, the Membership lapses to Suspended → Cancelled per §2.6.

Domain events: `MembershipRenewed`, `MembershipRenewalFailed`, `MembershipAutoRenewalCancelledByCustomer`, `MembershipNotRenewedByProducer`.

### §2.5 Producer Approval, Waiting List, and Invitation

Two intake paths are allowed (B2):

1. **Customer-initiated application** — the customer applies through the Consumer Portal (or the public application section accessible to non-members and Waiting-list applicants). Application enters the producer's queue in the Producer Portal. Producer can **approve**, **reject**, or **move to Waiting List**.
2. **Producer-initiated invitation** — the producer invites a specific person (existing customer or external) directly via the Producer Portal. The invitation flow is essentially a pre-approved application: the invitee accepts and pays the Hero Package fee to activate.

**Waiting List behavior**:

- Waiting-list applicants are stored against the club but the producer has not yet approved.
- The producer can promote a Waiting-list applicant to Approved at any time (typically when capacity opens — i.e., when an existing Member fails to renew or is removed).
- Waiting-list applicants do **not** see the producer's club page; they see Discovery and the application section only (DEC-012).
- The Waiting List is unordered by default — i.e., there is no FIFO promise to applicants. The producer chooses whom to promote. (If a producer wants ordering, that is producer-side policy, not platform behavior.)

**Producer discretion is absolute** (C16). The producer can reject any applicant for any reason (or no reason). NewCo does not adjudicate producer approval decisions.

**Capacity = # of Hero Packages** (B2). A producer cannot approve more applicants than the number of Hero Packages they have committed to source for the year. The Producer Portal exposes this constraint at approval time.

Domain events: `MembershipApplicationSubmitted`, `WaitingListJoined`, `MembershipApprovedByProducer`, `MembershipRejectedByProducer`, `MembershipInvitationSent`, `MembershipInvitationAccepted`.

### §2.6 Suspension, Cancellation, Re-instatement

Triggers (B8):

- **Producer-initiated** — the producer can kick a member out at their discretion (mirror of the approval discretion). Use cases include behavior on the club's social surfaces (when implemented), competitor concern, or any other producer-side judgment.
- **NewCo-initiated** — for bad behavior (defined operationally), missed payments (renewal payment failure or unpaid storage fees), KYC/sanctions failure, customer fraud (DEC-025 row), or any other ground in the customer terms.
- **Customer-initiated** — the customer can cancel auto-renewal (the Membership lapses at end-of-period) or proactively close the account (which puts every Membership into Cancelled state). Cancelled customers with stored holdings transition to Legacy until those holdings are redeemed (K4, see §2.7).

State machine (Membership):

```
Applied → WaitingList   (producer routed)
        → Approved      (producer approved)
        → Rejected      (terminal)

Approved → Active       (after Hero Package paid)

Active → Suspended      (NewCo or producer suspended)
       → Lapsed         (renewal failed or not renewed)
       → Cancelled      (terminal, customer or NewCo closed)

Suspended → Active      (re-instatement after fix)
          → Cancelled   (terminal)

Lapsed → Active         (manual re-activation in renewal grace)
       → Cancelled      (terminal)
```

**Re-instatement** of Suspended memberships requires both NewCo and producer agreement (whoever initiated the suspension must lift it). Lapsed memberships can be re-activated within a **30-day grace window** after period end (DEC-034) by paying the renewal cost without re-applying; after grace they go to Cancelled and the customer must reapply (and the producer reapproves).

**Cancellation does not auto-eject stored bottles or unredeemed Vouchers** (K4). The customer becomes Legacy and the bottles / Vouchers stay in the cellar under the same storage fee rules until redeemed or disposed of. This mirrors Crurated's behavior (v17) and avoids forced ship-outs that may not be feasible (e.g., customer in a country with shipping restrictions).

Domain events: `MembershipSuspended`, `MembershipReinstated`, `MembershipLapsed`, `MembershipCancelledByCustomer`, `MembershipCancelledByNewCo`, `MembershipCancelledByProducer`, `CustomerSegmentChanged` (the broad event covering all four-segment transitions, e.g., Member → Waiting-list, Waiting-list → Member, Member → Legacy, Legacy → Member; per Module K PRD §15.1), `CustomerTransitionedToLegacy` (HubSpot-specific subset event preserved for the Member-to-Legacy specialisation, per Module K PRD §15.6).

### §2.7 Storage Eligibility Across Segments

Storage eligibility (DEC-013, DEC-118, DEC-119, B9):

- **All three segments are eligible to store bottles** in the NewCo / Vinlock cellar — Members, Waiting-list, Legacy. There is no segment-based gating on storage.
- **First 12 months free per bottle**, counted from purchase (or from a producer-defined release date for liquid / pre-bottling cases — N/A at launch since liquid sales are out per L4 / DEC-011). Charges begin month 13.
- **After 12 months**: **€3 per bottle per year = €0.25 per bottle per month** (DEC-013, DEC-118), accrued monthly. Any partial month counts as a full month (rounded up to the nearest month).
- **Storage-clock-start trigger** (double anchor per DEC-119): storage accrual begins for month M iff (i) M > 12 months after INV1 issuance AND (ii) the bottle was at Vinlock for any part of month M. Operationally `storage_accrual_start_date = max(INV1_date + 12 months, InboundEventPhysicallyAccepted_date_for_the_bound_allocation)`. **Sourcing-model implications**: for V2 (passive consignment, pre-positioned — NewCo default per DEC-092) the bottle is at Vinlock at INV1, so the trigger collapses to `INV1 + 12 months` and accrual begins month 13. For V1 (passive consignment, customer-order-driven) the bottle ships to Vinlock at customer-order time and physical arrival typically falls within the 12-month-free window. For Direct Purchase in-transit-at-INV1 (per DEC-081) the clock waits for physical arrival — no storage = no charge; a Voucher whose Allocation has not yet had `InboundEventPhysicallyAccepted` fire surfaces "in transit" status on the Cellar surface (see §5.5 + Module S / Module C).
- **Cadence**: storage fees are issued on a **semi-annual cadence — end of June + end of December** — covering the prior 6-month period (DEC-118). **Module S owns storage-fee computation, INV3 issuance, and the per-bottle storage-month accrual mechanic** (DEC-119; supersedes DEC-118's Module-E-ownership clause; mechanics preserved). The customer-facing artifact is a dedicated **INV3** invoice for storage services (see §8.4 / §8.13). **Mid-semester shipment carve-out**: when a bottle ships during a semester, the storage charge for the months stored in that semester rolls into that bottle's INV2 (shipping invoice) as an additional line item rather than appearing on a separate INV3 (DEC-118 mechanics; DEC-119 makes this Module-S-internal logic — Module S owns both the INV2 issuance and the storage clock, so no cross-module query is needed).
- **Charging mechanics**: INV3 charges are taken from the customer's saved payment method (per §4.7); Module E consumes Module S's `InvoiceINV3Issued` event, routes it to the Xero accounting integration (per DEC-072 boundary), and executes the Airwallex charge against the saved payment method (per DEC-014). If the charge fails, the dunning sequence applies and persistent failure escalates to Suspended state (§2.6) per DEC-047 chargeback / Hold mechanics.
- **Insurance basis**: deferred at BMD level (DEC-048). Vinlock / Crurated contractual conditions apply for now; the detailed valuation basis is determined operationally in alignment with the Vinlock contract terms and will be revisited as cellar volume grows.

Domain events: `StorageFeeAccrued` (per bottle per month, after the first 12 months and the bottle-at-warehouse condition is met — Module S emits per DEC-119), `InvoiceINV3Issued` (semi-annual aggregation by **Module S** per DEC-119; consumed by Module E for accounting routing + Airwallex charge execution), `StorageFeeChargeFailed`. Storage charges that roll into INV2 mid-semester carry through the INV2 event chain (§8.13) as Module-S-internal logic.

### §2.8 KYC, Age Verification, Sanctions Screening

NewCo is a consumer business (DEC-017) with alcohol as the product, so the compliance posture is tuned to consumer fine-wine commerce, not investment-grade collectibles (B10):

- **Age verification at registration**. Mandatory; gating signup. Standard self-attest plus card-on-file (the payment-method-bound minimum-age check) at the EU consumer-commerce baseline. No physical-document age verification at launch.
- **Sanctions screening at onboarding** (DEC-030). Lightweight EU consolidated sanctions list + Italian UIF (Unità di Informazione Finanziaria) check at customer onboarding. Cost ~€0.30–€1 per check via providers such as ComplyAdvantage or Onfido.
- **Re-screen every 12 months and on flagged events** — large-transaction triggers, country change, suspicious-activity flags.
- **OFAC screening at onboarding** (DEC-041). The US is in NewCo's launch destination set, so OFAC screening is required at launch in addition to the EU + UIF check. There is no longer a conditional carve-out: every customer is OFAC-screened at onboarding alongside EU + UIF.
- **Enhanced-KYC threshold** (DEC-035): triggered at **€10,000 single transaction** OR **€50,000 cumulative annual purchases per customer**. Below those thresholds, the standard light posture (DEC-030) holds. Above either threshold, NewCo escalates to ops review and (where appropriate) document-based identity verification.
- **Failed screening** → in the standard onboarding flow, screening runs synchronously at signup, so most Customers transition `pending → passed` before they ever leave the registration form, and a failed screening produces an immediate rejection (the customer is notified per platform policy). The data-model design (DEC-071) admits Customer records in `pending`, `failed`, or `under_review` states with nullable screening fields — exceptional paths (admin imports, support-team-created records, in-flight onboarding) can produce such records without a schema rework. The substantive **enforcement point is order completion**: a Customer in any non-`passed` state is blocked from completing an order (Module S checks `sanctions_status = passed` as a hard precondition). Compliance posture is therefore: nobody who has not cleared screening can move money or take physical product, regardless of whether their Customer record was created or not.
- **Existing customer who later fails a re-screen** → Membership(s) Suspended pending review (§2.6); the Customer record persists in `sanctions_status = failed` until ops clears or terminates.

Domain events: `CustomerOnboardingScreeningPassed`, `CustomerOnboardingScreeningFailed`, `CustomerRescreeningPassed`, `CustomerRescreeningFailed`.

### §2.9 Marketing and Privacy Consent

Posture per DEC-026 (consent) and DEC-027 (GDPR):

- **Marketing emails — double opt-in.** At signup the customer can opt into marketing; consent is confirmed by clicking a confirmation link sent to the email address. No marketing emails are sent until the second opt-in is recorded.
- **Transactional emails — single opt-in.** Order confirmations, shipment updates, allocation notifications for the customer's clubs, account-state changes (suspension, renewal failure), refund notifications, etc., do not require explicit marketing consent and are sent under the contract / legitimate-interest legal bases.
- **Lawful bases under GDPR** (DEC-027):
  - Contract — account, purchase, fulfillment.
  - Consent — marketing emails, optional analytics cookies, optional profile enrichments.
  - Legitimate interest — security, fraud prevention, anti-abuse, sanctions re-screening, internal reporting.
- **Data subject rights**: access, rectification, erasure, portability, objection, restriction. Process documented in the Consumer Portal and exposed via a self-service request page.
- **Erasure constraint** (DEC-027). NewCo cannot fully erase a customer who holds unredeemed Vouchers or stored Bottles (§2.6). The erasure path in those cases is **soft-delete**: anonymize personal identifiers (name, email, address, marketing preferences); retain transactional, fiscal, and custodial records under the **10-year Italian invoice retention** requirement; restore identity only if the customer returns and re-onboards.
- **Breach notification**. 72 hours to the Italian Garante per Article 33 GDPR.
- **Cookie / consent banner**. Required on the Consumer Portal and Bottle Page; the Producer Portal is exempted as an authenticated B2B-style tool used only by producer staff under contract.
- **DPO** — internal assignment; no external DPO mandate at NewCo's launch scale.

Domain events: `MarketingConsentGranted`, `MarketingConsentRevoked`, `DataSubjectRightsRequestSubmitted`, `DataSubjectRightsRequestFulfilled`, `CustomerSoftDeleted`.

---

## §3 Producer Relationship

> Who qualifies as a Producer, how producers are recruited and onboarded, the producer agreement shape, pricing authority, the 12.5% / 5% margin structure, the default passive-consignment sourcing model, settlement cadence, and producer offboarding mechanics.

### §3.1 Producer Definition and Eligibility

A **Producer** is the wine-identity counterpart who actually produces the goods sold through NewCo (C1). The data model separates the **Producer** (who *makes* the wine — the brand, identity, and reputation surface that customers experience) from the **Supplier** (who *transacts commercially* with NewCo for a given allocation — the legal counterpart NewCo cuts the PO to or settles with). The two roles are linked N:N via a `SupplierProducerLink`, with a **default 1:1 mapping** at launch (the same legal entity is both Producer and Supplier in nearly all NewCo arrangements). The separation is preserved as a future-flexibility axis (DEC-067) — see Glossary entries Producer / Supplier and §3.4. The eligibility framing in this section refers to the Producer (wine-identity) role; the Supplier role inherits eligibility from its linked Producer at launch. At launch, eligibility (Producer-side) is restricted to:

- Wine producers (domains, châteaux, estates).
- Champagne and sparkling-wine houses.
- Spirits distillers (whisky, cognac, armagnac, mezcal, rum, etc.).

Excluded at launch:

- **Négociants** — agents who buy and re-sell others' wines under their own label without producing themselves.
- **Distributors / importers / wholesalers**.
- **Auction houses, secondary-market resellers**.

Eligibility is tied to *actual production of the good*. The rationale is the strategic positioning in §1: NewCo's value proposition is curated access to producers, not yet another marketplace for resold inventory. Boundaries may relax post-launch (e.g., to admit grower-champagnes operating partial négoce, or to admit specific quasi-producer entities) but each relaxation is a discrete decision.

**Discovery-only suppliers are admitted** (DEC-032). A producer who supplies allocations to NewCo's Discovery Tab does not need to operate a NewCo club to qualify; they are recorded in the Producer Registry on the same eligibility criteria as club producers. The commercial mechanic is the negotiated allocation cost `C` per Discovery allocation (see §3.6 / DEC-032), not the 87.5% PO that applies to club sales.

**Discovery commonly transacts with Suppliers-not-Producers** (DEC-082): Discovery operates as the operational norm at launch with a Supplier counterparty distinct from the wine's Producer (one Supplier may aggregate allocations from many Producers; one Producer may distribute via several Suppliers). Allocations are recorded with `producer_id` always populated (the wine identity) and `supplier_id` populated whenever the commercial counterparty is a Supplier-not-Producer; settlement / PO routes to the Supplier when present (DEC-082 two-FK pattern). Crurated is one such Supplier (per the next paragraph), but the pattern admits any Supplier the commercial team brings forward; it is not a rare exception.

**Crurated as a Discovery supplier** is admitted on commercial-only terms (DEC-020). Crurated is recorded in the Producer Registry like any other producer. The commercial relationship operates under the standard Discovery mechanic when the allocation is Discovery-only and under the standard club mechanic if Crurated were ever to operate a NewCo club (not contemplated at launch). There is no shared identity, catalog cross-access, or any other intragroup mechanic with Crurated; intragroup mechanics remain OUT (DEC-001, see §13.11).

### §3.2 Producer Recruitment

**Outbound only at launch** (C2). NewCo's commercial team identifies and approaches target producers; there is no inbound application form for producers. The rationale: producer onboarding has high commercial, legal, and operational lift, and the launch portfolio is curated by NewCo's commercial judgment, not opened to the market.

Target launch portfolio: **5–10 producers committed at launch** (A5), scaling to **100+ within 24 months** (A4). Outbound recruitment continues post-launch as the commercial team expands.

The recruitment funnel is operationally simple at launch:

1. Commercial team identifies target.
2. Outbound approach + meeting.
3. Negotiation of agreement terms (see §3.3).
4. Agreement signed.
5. Producer-portal access provisioned.
6. Producer Onboarding Flow (§9.4) executed.
7. First Hero Package and first allocation released.

There is no automated producer-application surface. If demand for an inbound channel emerges later, it is a post-launch product decision.

### §3.3 Producer Agreement Shape

Each Producer signs a **written agreement** with NewCo at onboarding (C3). The current intent is a **24-month term** as the default duration, subject to renewal mechanics that are TBD pending legal review and Paolo's circulation of the agreement template (Q-OQ-9).

Each agreement is recorded as a **`ProducerAgreement`** entity in Module K (DEC-070) — a first-class data record of the commercial substance, with its own lifecycle (`draft → active → superseded → terminated`), an allocation-commitment record (§3.8), an optional settlement-cadence override against the §3.10 quarterly default, and a supersession chain so that successive agreements with the same Producer link backward to their predecessors. The PRD models the data shape; the BMD fixes the commercial content the entity carries.

The canonical commitments captured in the agreement are:

- **Producer commits to**:
  - A **minimum allocation commitment** for the term (§3.8).
  - The Hero Package design (composition and price) for at least the upcoming year, with a process for setting subsequent years.
  - Pricing authority on club and Discovery offers (§3.5).
  - Approval / rejection / kick discretion on members (§3.12).
  - Settlement-cadence terms (§3.10).
  - Conformance with NewCo's content guidelines for the producer-branded club page (§1.6).
- **NewCo commits to**:
  - Acting as Seller of Record on every sale (§3.5).
  - Providing the four front-ends (Admin, Consumer, Producer, Bottle — §7).
  - Operating the warehouse and fulfillment layer (§5).
  - Settling at 87.5% of the customer-facing price on every club sale (DEC-010, §3.6).
  - Settling Discovery sales at the equivalent share net of the 5% Originating-Club share (§8.14).
  - Producer reporting visibility per §3.11.
  - Producer offboarding mechanics per §3.13.

The agreement template itself is OUT OF SCOPE for the BMD; legal will own its drafting (Q-OQ-9). The BMD only fixes the *commercial substance*.

### §3.4 Multi-Brand and Multi-Club Producers

A single Producer may operate **multiple brands**, and each brand may run its own club (C4). Example: a producer with both a domain and a négociant arm (where allowed under §3.1's eligibility) might choose to run two clubs targeting different customer profiles. Each club:

- Has its own Hero Package, its own pricing authority, its own member capacity, its own page and brand presentation in the Consumer Portal.
- Operates under its own agreement with NewCo (or sub-agreements anchored to a master agreement — TBD with legal).
- Settles separately at the producer level (settlement may aggregate across brands or stay separated per §3.10's per-producer configurability).

A **Customer can be a Member of multiple clubs of the same Producer**, just as they can be a Member of clubs of different Producers (B6, §2.2). The Originating Club is whichever club approved the Customer first across *any* Producer (§2.2).

**Tier model — single-tier at launch; multi-tier capability inherited from v17** (DEC-062). Every NewCo club at launch is configured as **single-tier** (one default tier per club; one Hero Package; one membership-cost band). The data model retains v17's multi-tier club shape under the inheritance principle (DEC-060), so activating a second tier on a club post-launch is a **Producer Portal configuration change**, not a database migration. NewCo does not commit to ever activating multi-tier; it commits to leaving the door open at zero design cost. Multi-tier UX is not exposed in the Consumer Portal at launch.

**Club operator constraint at launch — Producer-only** (DEC-067). Only **Producers** (not Suppliers) operate Clubs at launch: every Club is linked to exactly one operating Producer entity, and the link is required and immutable for the Club's lifetime. **Supplier-operated Clubs are flagged as future flexibility** but explicitly OUT at launch — the data model preserves the affordance for a later activation, but no NewCo Club at launch can be operated by a Supplier that is not also a Producer. This rule pairs with the §3.1 Producer eligibility set: a Club at launch is always anchored to a wine-producing legal entity, never to a négociant / distributor / reseller acting only in the Supplier role.

### §3.5 Pricing Authority

NewCo is **always the Seller of Record** (DEC-010): NewCo invoices the customer, collects payment, and owns the customer-facing price book. Pricing authority differs between the club and Discovery surfaces (DEC-032):

- **Club offer pricing** — the **producer sets the price**. NewCo accepts and lists. Producer can change a price for future allocations; existing allocations on offer are locked at their listed price.
- **Discovery offer pricing** — **NewCo sets the customer-facing Discovery price `P_d`** after negotiating the per-allocation cost `C` with the producer (DEC-032). The producer of a Discovery allocation does not control the customer-facing price.
- **Dual-listing pricing differential** — when the same Bottle Reference is listed in BOTH the producer's club and Discovery (DEC-023), the producer sets the club price; NewCo sets the Discovery price after the negotiated `C` with the producer for that Discovery allocation. Typically the club price is lower (members get a privileged price). NewCo does not enforce a minimum spread between club and Discovery; commercial judgment on both sides governs.
- **Hero Package price** — set by the producer per year; this is the membership cost for that year (§2.3).
- **Platform promotions** (DEC-039):
  - NewCo **never silently discounts a producer-set price**. Any club promotion that affects the producer's price requires the producer's explicit per-campaign opt-in (Producer Portal exposes a toggle at campaign launch).
  - **No NewCo-funded promotions on club offers** — even when NewCo would absorb the discount, club offers stay at producer-set price unless the producer opts in.
  - On **Discovery**, NewCo controls pricing (per the negotiated `C`/`P_d` mechanic) and is therefore free to run promotions without producer consent — the producer is paid `C` per unit regardless of the customer-facing price.
- **Multi-currency** — for club offers, the producer sets prices in EUR by default; NewCo derives other currencies via the FX policy (§4.8, §8.8). For Discovery, NewCo's `P_d` is published in EUR and derived in non-EUR likewise.

**No producer-side fee for using the platform** (DEC-010, C6, H1). NewCo's monetization is on margin and storage (§8.1). There is no listing fee, joining fee, slotting allowance, or service fee charged to producers.

### §3.6 Margin Structure — Club 12.5% PO Mechanic; Discovery Negotiated-Cost Mechanic

The club and Discovery surfaces operate under **two structurally different commercial mechanics** (DEC-010 for club, DEC-032 for Discovery). The unifying principles are: NewCo is always Seller of Record; the producer of the bottle is always paid; and the buyer's Originating Club always earns a 5% revenue share on Discovery purchases.

**Club sale economics** (DEC-010, C5/C6):

- Customer pays the producer-set price `P` (plus VAT, plus shipping/storage as applicable per §4 / §8).
- NewCo is Seller of Record on the transaction at `P` (event sequence per the MPV regime + INV1/INV2 mechanic, §8.7 / §8.13; downstream accounting determines treatment per DEC-072).
- NewCo cuts a **Producer Purchase Order (Producer PO) at 87.5% × P** to the producer.
- NewCo retains **12.5% × P** as gross margin.
- The 12.5% is the *gross margin*; NewCo's net margin is 12.5% × P minus the platform's operating cost share (payments, blockchain gas, storage attribution, support attribution, etc.).
- No 5% Originating-Club share is computed on club sales — that share is a Discovery-only mechanic.

**Discovery sale economics** (DEC-032, supersedes the Discovery portion of the v0.1 description):

- Per Discovery allocation, NewCo and the producer of the bottle **negotiate an allocation cost `C`** (per allocation, not formulaic). `C` is the unit price NewCo will pay the producer for each unit sold from that allocation.
- **NewCo sets the customer-facing Discovery price `P_d`** itself. The producer does not set `P_d`.
- On sale of one unit: customer pays `P_d` (plus VAT / excise / shipping per §4 / §8). NewCo is Seller of Record on the transaction at `P_d` (event sequence per §8.13; downstream accounting determines treatment per DEC-072).
- Producer of the bottle is paid `C` (settled through the producer's standard quarterly settlement, §3.10).
- NewCo's **gross margin** on the transaction is `P_d − C`.
- **5% × `P_d`** is paid to the buyer's **Originating Club** producer (the producer of the buyer's first-approved club, locked at first approval per §2.2). The Originating-Club producer can be a *different* producer than the bottle's producer; in that case NewCo settles the 5% × `P_d` to the Originating-Club producer through their settlement statement.
- NewCo's **net margin** on the transaction is `P_d − C − (5% × P_d)`.

**Worked examples** (per DEC-032's worked example):

| Scenario | Customer pays `P_d` | Allocation cost `C` (paid to producer of bottle) | Originating-Club share (5% × `P_d`) | NewCo gross margin (`P_d − C`) | NewCo net (after OC share) |
|----------|---------------------|--------------------------------------------------|-------------------------------------|-------------------------------|----------------------------|
| Discovery sale: NewCo Discovery price €200; allocation cost €100 with Producer X; buyer A's Originating Club is **Producer B's** club | €200 | €100 (to Producer X) | €10 (to Producer B) | €100 | €90 |
| Same parameters but buyer A's Originating Club is **Producer X's** club (same producer as the bottle) | €200 | €100 (to Producer X) | €10 (also to Producer X — additive) | €100 | €90 |
| Same parameters but buyer A has **no Originating Club** (DEC-040) | €200 | €100 (to Producer X) | n/a — no 5% recipient | €100 | €100 |
| Club sale (for contrast): producer-set price €1,000 | €1,000 | n/a — Producer PO at €875 (87.5%) | n/a (club sales generate no Discovery share) | €125 (12.5%) | €125 |

The Originating-Club share is paid even if the buyer is no longer an active member of the Originating Club (e.g., they became Legacy after their first-approved membership lapsed). The Originating Club is locked once at first approval and tracked indefinitely (DEC-008, §2.2).

**Discovery purchase by customers with no Originating Club** is permitted (DEC-040). Where no Originating Club exists, no 5% accrues to anyone; the full Discovery gross margin (`P_d − C`) accrues to NewCo on that transaction. Past Discovery purchases do **not** retroactively gain an Originating Club when the customer is later approved by a club (the lock is forward-looking from first approval).

### §3.7 Sourcing Models — Passive Consignment V2 Default; V1 + Direct Purchase as Exceptions (DEC-011, DEC-063)

NewCo's sourcing models at launch are **passive consignment V2** (default), **passive consignment V1** (exception), and **direct purchase** (exception per DEC-063), all under NewCo's Seller-of-Record posture (DEC-011, DEC-063, C7, E1):

- **Passive consignment V2** (default) — stock sits *physically at the Vinlock-operated warehouse* in France. Title remains with the producer until each individual unit is sold to a customer; at sale, title flips through NewCo (Seller of Record) to the customer.
- **Passive consignment V1** (exception) — stock sits *physically at the producer's premises*. When NewCo takes a customer order, the producer ships to NewCo's warehouse, NewCo then ships to the customer (or stores it pending the customer's shipment request, with the customer holding a Voucher in the meantime). Used only for **very expensive / very rare bottles** where pre-emptive transfer to the warehouse is impractical or disproportionately costly to insure.
- **Direct purchase** (exception, DEC-063) — NewCo pays the producer / supplier outright at purchase, takes title at purchase, holds the inventory at Vinlock, and sells with full title transfer to the customer. Used for strategic stock-up on a limited release the producer will not consign, one-off Discovery sourcing where the producer prefers a clean sale, or supplier relationships where outright purchase is the commercial norm (e.g., Crurated as a Discovery supplier per DEC-020 may operate this way for some allocations). **Allocation is sellable from `SupplierPaymentCompleted`** (DEC-081); physical receipt at the warehouse gates customer shipment only, not voucher issuance — i.e., NewCo can publish offers and accept customer orders against the allocation as soon as the supplier is paid, with the in-transit voucher displaying ETA on the customer order page until the inbound is `PHYSICALLY_ACCEPTED`.

**NewCo's preferred sourcing model is passive consignment** (DEC-092). Across both Club and Discovery, passive consignment is the cashflow-positive default (no upfront cash leaves NewCo before sell-through); direct purchase is the FALLBACK, used when passive isn't on offer for that allocation (the producer / supplier insists on outright purchase, the lot is too rare to consign, etc.).

The producer-by-producer choice among the three models (or a mix across allocations) is recorded at allocation level — see §4.5 and §5.1.

The **physical sourcing model is independent of the commercial mechanic**: club allocations and Discovery allocations can use any of the three sourcing models for the physical flow. The commercial difference is on the settlement side — club allocations pay the producer 87.5% of the customer-facing price at sell-through under the canonical `percent_of_selling_price` instance of the commercial-terms shape (DEC-010 / DEC-092 — §3.10); passive-consignment Discovery allocations commonly settle via the `fixed_per_unit` shape at the negotiated allocation cost `C` per unit sold (DEC-032 framing) but may also adopt `percent_of_selling_price` when the parties prefer to share the customer-facing price; **direct-purchase allocations bypass sell-through settlement entirely** (the supplier was paid in full at PO issuance) regardless of which commercial-terms shape was negotiated. The 5% × `P_d` Originating-Club share on Discovery sales applies regardless of sourcing model.

**Allocation `qty` is mutable across all sourcing × visibility combinations** (DEC-079, generalising DEC-069's mid-year Hero Package mechanic to all allocation types): the producer (via Producer Portal) or NewCo ops (via Admin Panel) can scale an allocation's quantity up or down post-creation, subject to the **anti-orphan rule** — `qty` cannot decrease below the count of vouchers already issued against the allocation (i.e., decreases above the issued count are legal; below it is not, since that would orphan customer-held vouchers). The waitlist-conversion side-effect (capacity increase offers Member slots to next-in-line waitlisters) remains specific to club allocations per DEC-069. For Direct Purchase, capacity increase implies a follow-on PO to the supplier; for V1/V2, it expands the producer's commitment without immediate financial flow.

**Active consignment and drop-shipping remain OUT** at launch (DEC-011, L3, L5, see §13.1, §13.3). For passive-consignment allocations the producer-payment event fires at sell-through; direct-purchase allocations are the explicit exception (DEC-063), where the producer-payment event fires at purchase (the producer is paid in full at that moment) and title-transfer-to-NewCo also occurs at purchase. **The accounting integration (Xero per DEC-028) receives these events and determines accounting treatment per its own policy** (per DEC-072 — BMD / PRD do not take positions on accounting policy). **Refund-cost matrix note** (DEC-025): producer-fault clawback on direct-purchase batches is harder to recover operationally (the producer was already paid and may not accept a clawback) — flagged for operational handling at agreement-negotiation time.

### §3.8 Minimum Allocation Commitment

Each producer agreement records a **minimum allocation commitment** for the agreement term (C8). The minimum commitment is expressed against the planned cadence of releases (e.g., "X bottles across the year, distributed across the Hero Package and Y subsequent allocations").

The commitment is a **floor, not a ceiling**: the producer can release more if they choose, but cannot drop below the floor without renegotiation.

The commitment shape is producer-specific and lives in the agreement, not in the platform. The platform (Producer Portal + Module A) tracks actual allocations released and surfaces a comparison against the commitment as a reporting view; it does not enforce the commitment automatically.

**Agreement-level commitment vs Hero Package availability** are linked but distinct:

- Hero Package availability bounds the **active member count** (B2, §2.3).
- The minimum allocation commitment bounds the **total volume the producer commits across the term**.

A producer can cap members low (small Hero Package count) but offer high-volume subsequent allocations through the year — the model accommodates both.

### §3.9 Unsold Stock Handling

Stock that does not sell through (after a producer-defined / NewCo-defined window) is handled on a per-allocation basis (C9):

- **Recall to producer** — producer takes the unsold units back. **Recall scope is the unsold sub-pool only** (DEC-117): the recallable quantity is `Allocation.qty - issued`, i.e., the units that have not yet generated an ISSUED Voucher. **ISSUED Vouchers are immutable post-INV1** — once a Voucher's INV1 has fired and the customer has paid, the underlying allocation portion is committed to the customer; the producer's recall request **does not affect committed customer holdings**. NewCo arranges return shipment of the unsold units from the warehouse; cost handling is per agreement (default: producer-borne, since the goods are returning to the producer's title). The reverse-inbound flow records a `ReverseInboundEventRecorded` event (DEC-090) for the unsold portion only. Refined state-conditional matrix on individual Vouchers at recall: PENDING_PAYMENT (bank-transfer 7-day window per DEC-101) — edge case, ops review case-by-case if collision occurs (recall-before-INV1 → Voucher VOIDS without INV1 / refund; INV1-already-fired → Voucher committed and NOT subject to recall); ISSUED / REDEMPTION_REQUESTED / SHIPPED / CONSUMED / GIFTED — NOT void-target on producer recall; VOIDED / EXPIRED — terminal, recall has no effect.
- **Sold via Discovery** — the producer can re-list the unsold club allocation onto the Discovery Tab (or NewCo can propose this). At Discovery, the visibility flag is updated (DEC-023) and a Discovery offer is published. NewCo absorbs the (typically lower, because Discovery price targets non-members) margin on the conversion.
- **Hybrid** — partial recall + partial Discovery list, at producer discretion.

**Recall ≠ Producer Offboarding (DEC-117)**: this section covers unsold-stock recall (limited scope, ISSUED Vouchers immune); §3.13 covers producer offboarding (relationship lifecycle exit + NewCo's commitment to honour outstanding ISSUED Vouchers regardless of producer status).

Unsold Hero Packages are a special case: since the Hero Package is the membership-fee mechanic, an unsold Hero Package means an unfilled Member slot. The producer can either:
- Promote a Waiting-list applicant to fill the slot (preferred path), or
- Recall the unsold package contents to producer (at producer expense; same unsold-only scope), or
- Re-purpose the contents into a non-Hero allocation listed on Discovery.

Domain events: `AllocationUnsoldRecalledByProducer`, `ReverseInboundEventRecorded`, `AllocationRelistedToDiscovery`, `HeroPackageVacancyFilledByWaitingList`.

### §3.10 Producer Settlement

**Settlement = invoice-driven, quarterly by default** (C10, H10, DEC-042). The settlement amount on each producer / supplier counterparty's quarterly statement is computed off the **per-allocation commercial terms** (DEC-092) — a unified `{shape, value}` structure that lives on each Allocation, orthogonal to sourcing model (passive consignment V1 / V2 or direct purchase) and counterparty type (Producer or Supplier per DEC-082):

- **`commercial_terms.shape ∈ {fixed_per_unit, percent_of_selling_price}`**.
  - **`fixed_per_unit`** — `value` = a per-unit amount in the negotiated currency. NewCo pays the counterparty `value × qty_sold` per allocation per period. The pre-DEC-092 `negotiated_cost` framing for Discovery (the per-unit cost `C` that did not depend on the customer-facing price) is the canonical example of this shape and the operational default for Discovery passive-consignment allocations sourced from suppliers and producers who prefer a flat per-unit price.
  - **`percent_of_selling_price`** — `value` = a percentage. NewCo pays the counterparty `(value%) × selling_price × qty_sold` per allocation per period; the residual `(1 − value%) × selling_price` is NewCo's gross margin on each unit. The **club default is the 12.5% / 87.5% split** (DEC-010): producer-set price `P` per unit; Producer PO at 87.5% × `P`; NewCo retains 12.5% × `P`. The same percent mechanism is admissible in Discovery and Direct Purchase contexts whenever NewCo and the producer / supplier agree to share the selling price rather than fix a per-unit cost; the 12.5% / 87.5% split is the canonical club instance, not the only context where the shape applies.
- **Sourcing model drives PO timing** (DEC-086 / DEC-093); **commercial-terms shape drives the value computation**. The two dimensions are independent: a passive-consignment Discovery allocation may use either `fixed_per_unit` (negotiated `C`) or `percent_of_selling_price` (e.g., 12.5% / 87.5% with NewCo or producer setting the customer-facing price); a Direct Purchase allocation may likewise use either shape (most commonly `fixed_per_unit` because the financial commitment is at PO issuance, but a `percent_of_selling_price` Direct Purchase is not forbidden if the commercial relationship is structured that way).
- **Sourcing-model preference** (DEC-092): **passive consignment is NewCo's preferred sourcing model for both Club and Discovery** (cashflow-positive — no upfront cash leaves NewCo before sell-through). Direct purchase is the FALLBACK when passive isn't on offer (per §3.7 + DEC-063); it is admitted at launch but is not the Discovery default.

NewCo accumulates each counterparty's sell-through events over the quarter:

- **Sell-through events** under `percent_of_selling_price` shape: settlement amount = `value%` × selling price × qty sold. The club default is 87.5% × `P`. On Discovery, the same shape is admissible and yields `value%` × `P_d` (where `P_d` is NewCo's customer-facing Discovery price) when both parties agree to the percent split.
- **Sell-through events** under `fixed_per_unit` shape: settlement amount = `value` × qty sold per allocation. On Discovery passive-consignment allocations under the legacy framing this is the per-allocation negotiated cost `C` (per DEC-032); the customer-facing Discovery price `P_d` does not flow into the producer's settlement; only the agreed `value`. The same `fixed_per_unit` shape is admissible on club allocations if both parties agree to a flat per-unit cost rather than the percent default.
- **5% × `P_d` Originating-Club shares earned** by the Producer when their members purchase elsewhere on Discovery (the Producer is on the receiving end of the 5% share for those transactions per DEC-032 / DEC-040). The 5% share is computed on the customer-facing price `P_d`, independent of the bottle's own commercial-terms shape.
- **Direct-purchase allocations are NOT enumerated in sell-through settlement** (per §3.7 + DEC-063): the supplier was paid in full at PO issuance, so direct-purchase batches do not generate a sell-through PO at the moment a unit sells regardless of commercial-terms shape. Direct-purchase allocations may still appear on the counterparty's settlement statement as an informational reconciliation row (units sold from the batch, refunds against the batch) but they do not drive a settlement amount due.

- At quarter-end, NewCo presents a **settlement statement** to the Producer — sales detail (club + Discovery, separately broken down), refunds, returns, breakage absorption, Discovery 5% shares earned and paid, etc.
- The Producer **issues an invoice to NewCo** for the settlement amount. NewCo pays the invoice on **net-30 payment terms** by default (working baseline; DEC-042 locks the framework that each producer agreement may modify cadence and any related payment terms — agreements may negotiate shorter or longer per producer's preference).
- **Cadence is configurable per producer** (C10, DEC-042). Quarterly is the default; each producer agreement can modify cadence (e.g., monthly for high-priority producers, semi-annual for low-volume producers) and any related payment terms.

**Settlement currency**: **EUR by default** (C11). Producers based in non-EUR jurisdictions and selling in non-EUR currencies are settled either in EUR (with FX applied at NewCo's published rate at settlement date) or in their currency of choice (subject to Airwallex multi-currency settlement support, §11.4). The default position is EUR.

**Refunds and clawbacks within a settlement period** are netted against the settlement statement at quarter-end (so a refund issued in Q3 reduces the Q3 settlement amount). Refunds spanning settlement periods (e.g., a sale settled in Q3, refunded in Q4) are clawed back from the Q4 settlement.

Domain events: `SellThroughRecorded`, `ProducerPOEmitted`, `ProducerSettlementStatementGenerated`, `ProducerInvoiceReceived`, `ProducerSettlementPaid`, `ProducerSettlementClawback`.

### §3.11 Producer Reporting Visibility (C13)

The Producer Portal exposes:

- **For sales on the producer's own club page** — buyer-level detail in real time. Producer sees the customer's name, profile, membership tenure, and what they bought from this producer's club. This visibility is the basis on which the producer manages member relationships.
- **For Discovery sales of the producer's wines** — aggregate-only. Producer sees the bottle, the price, and the date, but **not** the buyer's identity, profile, or membership history. Buyer privacy on Discovery is a hard rule.
- **For 5% Originating-Club share earned** (i.e., when one of the producer's members buys a different producer's wine on Discovery) — the Originating-Club producer sees the share amount accrued, the (anonymized) reference to the Discovery transaction it derives from, and the running balance through the period. They do **not** see what bottle from which other producer their member bought.
- **Aggregate KPIs per club** — # active members, # Waiting-list, # cancelled in period, total sales, average order value, club credit outstanding, storage volume held by members.
- **Member-state digest** for membership lifecycle actions (applications pending, renewals upcoming, suspensions in flight).

The Producer Portal is in scope at launch in the Producer-Portal locale set (EN + IT, DEC-031).

### §3.12 Producer Discretion (Approve / Reject / Kick)

The Producer holds **absolute discretion** on three decisions (C16):

1. **Approve / Reject / Waitlist** an applicant.
2. **Kick out** an active Member (move to Cancelled, see §2.6).
3. **Don't renew** at the end of an annual period (the renewal does not auto-fire if the producer declines, see §2.4).

Producer discretion does not require justification to the customer or to NewCo. NewCo does not adjudicate producer decisions and does not provide an appeal path back to the producer (the customer can re-apply if the producer chooses to reconsider, but that is a producer-side decision, not a platform feature).

NewCo *does* hold its own kick discretion under bad-behavior / fraud / non-payment grounds (§2.6); a NewCo-initiated kick removes the customer from the Member segment platform-wide, not just from a single club.

### §3.13 Producer Offboarding

When a Producer leaves NewCo for any reason — end of agreement, breach, mutual decision, business closure, etc. — the offboarding rule is:

**Customer-facing obligations stay with NewCo** (C12, DEC-117). A Producer's departure does not extinguish:

- **Outstanding ISSUED Vouchers** held by Customers — those Vouchers are NewCo's obligation as Seller of Record, and **NewCo's commitment to honour them stands regardless of producer status** (DEC-117 + DEC-104). NewCo redeems them by drawing on whatever residual stock is available, by sourcing from another producer of comparable wine (subject to commercial terms with the customer, including refund if substitution is unacceptable), or by settling in store credit / cash refund per the refund-cost matrix (DEC-025, §4.11). Voucher substitution under offboarding is a **manual operator capability at NewCo launch** (DEC-104) — the operator records `VoucherSubstitutionExecuted` (original Voucher → substitute Bottle Reference / SKU + reason) via NewCo Admin Panel; full automation (catalogue-driven substitute matching, automated customer notification flow) is deferred to Phase 2+. With **passive consignment as NewCo's preferred sourcing model** (DEC-092), substitution scenarios are **operationally rare**: producer relationships typically continue through offboarding (residual stock is available), and where they do not, comparable-producer arrangement usually resolves the obligation. The exact playbook is a Module C / Module S design call deferred to the PRD.
- **Stored bottles** held by Customers in the cellar — those are already-sold goods sitting in custody; the Producer's departure does not change ownership. NewCo continues to store and ship them to the Customer's instructions.
- **Club Credit balances** — the Customer holds a credit on a club whose Producer has left. Per DEC-043, the credit is **converted to Discovery store credit at face value, valid for 12 months** from conversion. The customer can spend the converted credit on any Discovery offer; after 12 months, residual unspent credit expires.

**Offboarding ≠ Producer recall (DEC-117)**: §3.9 covers producer recall (unsold sub-pool only; ISSUED Vouchers immune); this section covers offboarding (relationship lifecycle exit; NewCo's honour-commitment on ISSUED Vouchers stands regardless). A producer can be in offboarding while NewCo continues to honour their previously-issued Vouchers — the two flows are operationally distinct.

**Pending allocations** that have not yet sold through (the unsold sub-pool only per DEC-117) are recalled to the Producer (subject to the Producer's willingness/ability to take them back) or sold through Discovery as in §3.9.

**Pending settlement** is paid out per the standard settlement mechanics through the offboarding date.

Producer Offboarding is a high-friction event with several customer-facing implications; the operating manual will document the playbook in detail post-BMD.

Domain events: `ProducerOffboardingInitiated`, `VoucherSubstitutionExecuted`, `ClubCreditMigratedToDiscovery`, `ProducerOffboardingCompleted`.

---

## §4 Commerce Model

> What customers can buy, the bottle / case / mixed-package / vertical surface, the Voucher model with late binding, the Allocation visibility model (Club / Discovery / Both), checkout and payment terms, purchase limits, cancellation, refund-cost matrix, and discount mechanics.

### §4.1 What Customers Can Buy

The customer-facing catalog is bounded by the producer-club aggregator model (§1.1). Customers can buy (D1):

- **Single bottles** — individual SKUs at bottle granularity.
- **Cases** — standard case configurations (e.g., OWC6, OWC12) defined per producer per allocation (D2).
- **Mixed packages** — producer-curated multi-product / multi-vintage selections sold as a single unit (D3, DEC-019). The Hero Package is the canonical example.
- **Verticals** — multi-vintage selections of the same wine, sold as a single multi-bottle unit.
- **Services and experiences** — secondary tier (estate visits, virtual tastings, masterclasses); free or paid; revenue mechanics TBD (Q-OQ-8). Where free (e.g., a club member booking an estate visit through the Consumer Portal), the booking is captured but no transaction. Where paid, mechanics will be specified in a future BMD revision.

Bottles, cases, packages, and verticals are all subject to **serialization** (§6) and the **Voucher model with late binding** (§4.4). Services / experiences sit outside the cellar / serialization model.

### §4.2 Granularity — Bottle, Case, Package, Vertical

Granularity choice is at producer discretion per allocation (D2). Standard patterns:

- **Bottle granularity** — sell as individual bottles. Customer can buy one or many; each becomes its own Voucher / Bottle Reference.
- **Case granularity (OWC6, OWC12, etc.)** — sell only as full cases. Customer cannot buy a single bottle out of an OWC unit. The case is a fulfillment unit. **Breakability is layered across three points** (per Crurated v17 §1.4 / §2.5 layered model carried into NewCo — see Appendix B.1): **Layer 1** at the **Wine Variant** level (a global default for the variant — e.g., a producer's flagship wine that is *always* shipped in OWC), **Layer 2** at the **Allocation** level (the producer's per-allocation override — e.g., a specific batch the producer wants kept unbreakable for collectibility reasons), **Layer 3** at the **Offer** level (NewCo's per-Offer override on Discovery — e.g., a Discovery composite that NewCo wants kept unbreakable for presentation). The effective rule is `Layer 1 OR Layer 2 OR Layer 3` (any unbreakable layer wins). The PRD owns the data shape; the BMD records the three-layer principle.
- **Mixed package granularity** — the package is a single unit of sale; the Customer buys the whole package. Constituents are tracked individually for cellar, voucher, and shipment-decomposition purposes.

The data model represents bottles, cases, and packages as discrete granularity options on the same underlying Allocation (PRD detail; the BMD records the principle).

### §4.3 Mixed-Case Constraints — Surface-Asymmetric (Club Single-Producer; Discovery Multi-Producer Allowed)

Mixed packages (cases that combine multiple SKUs / vintages / appellations into a single sale unit) follow a **surface-asymmetric constraint** (DEC-019 retained for clubs; DEC-061 amends for Discovery):

**Club mixed-case packages are always single-producer** (D3, DEC-019). A club case may combine multiple products (e.g., one Pepe Trebbiano + one Pepe Montepulciano + one Pepe Pecorino) and multiple vintages (e.g., five vintages of the same wine), but **never** multiple producers. The rationale is the producer-club aggregator model: a club is by definition one producer's identity, and every club allocation is anchored to that producer. The single-producer rule keeps:

- The club allocation model clean (one club allocation = one producer).
- The Hero Package mechanic clean (one producer curates one Hero Package per year for their club).
- The Producer PO at 87.5% × `P` (DEC-010) cleanly attributable to one producer per sale.

**Discovery composite SKUs may combine multiple producers** (DEC-061, inheriting Crurated v17 §1.4 Composite SKU). NewCo curates which constituent bottles combine into a Discovery composite. Settlement mechanics:

- Each constituent bottle settles to its own producer at that allocation's per-unit negotiated cost `C_i` (per DEC-032). NewCo holds a separate Discovery allocation with cost negotiation per constituent producer.
- NewCo's gross margin on the composite = `P_d − Σ C_i` across constituents.
- The 5% × `P_d` Originating-Club share goes to the **buyer's single Originating Club** producer regardless of how many bottle-producers contributed (one buyer = one Originating Club = one share recipient; the 5% is on the headline `P_d`, not split per constituent).
- Producer reporting visibility (per §3.11): each constituent producer sees the aggregate-Discovery view of their constituent's sales, never the composite's other constituents (preserves the buyer-anonymity-on-Discovery rule).

This club-vs-Discovery asymmetry is consistent with the BMD's other surface-asymmetric mechanics: pricing authority (DEC-032), promotion policy (DEC-039), and Originating-Club-share applicability.

### §4.4 Voucher Model and Late Binding

NewCo retains the Voucher model from Crurated v17 (D4, D5):

- **Voucher** = a customer's right to a quantity of a specific Bottle Reference at a specific producer-set price, redeemable for shipment on the customer's instruction. A Voucher is the immediate result of a purchase; the physical bottle assignment happens at shipment (late binding).
- **Late binding** (D5/E11) — the *specific physical bottle* assigned to a Customer's voucher is selected at shipment time, not at sale time. Until shipment, the Voucher is fungible against the producer's available stock at that Bottle Reference.

This is the same model as Crurated v17 §1.4 / §2.4 (consult v17 by section number for nuance). NewCo simplifies in two ways:

- **No CruTrade ON_CRUTRADE state**. CruTrade-style P2P trading is OUT (DEC-008 boundary, L2). The Voucher state machine is simpler: ISSUED → REDEEMED (shipment requested) → SHIPPED → CONSUMED, plus VOIDED (refund) and EXPIRED (if voucher-expiry policy is operationalized).
- **No active-consignment paths** (DEC-011, DEC-017). The Voucher derives only from passive-consignment V1 or V2 stock.

**Voucher expiry, buy-back, and liquidation** policies follow the Crurated v17 patterns by reference (D5, "all same as Crurated v17"). The PRD will name the exact policies; the BMD records the inheritance.

**Voucher transferability** — gifting allowed (B14, K7), P2P out (L2). See §4.13.

**Resolution / liquid handling** — N/A at launch since liquid sales are OUT (D6, L4, see §13.4).

Domain events: `VoucherIssued`, `VoucherRedemptionRequested`, `VoucherShipped`, `VoucherConsumed`, `VoucherVoided`, `VoucherExpired`, `VoucherGifted`.

### §4.5 Allocation Visibility — Club / Discovery / Both (DEC-023)

Every Allocation carries a **visibility flag** (DEC-023):

- **CLUB_ONLY** — visible only on the producer's club page; only club Members of that producer can purchase. Typically the lower-priced offer.
- **DISCOVERY_ONLY** — visible only in the Discovery Tab; all NewCo Customers (Members, Waiting-list, Legacy) can purchase, subject to per-offer rules.
- **BOTH** — the same Bottle Reference appears in both the producer's club (typically at a club-favored price) and in Discovery (typically at a higher price). Two parallel offers at two prices, drawing from the same producer commitment.

The producer chooses the visibility per allocation. NewCo curates which Discovery offers ultimately surface (i.e., NewCo can decline to publish an offer to Discovery), but cannot force a club-set allocation onto Discovery without the producer's consent.

**Time-based or tenure-based priority does NOT apply** (DEC-023, Q-CL-5). Within a club, all Members see and can buy a CLUB_ONLY allocation simultaneously; within Discovery, all Customers see and can buy a DISCOVERY_ONLY (or BOTH) allocation simultaneously. Pricing differential between club and Discovery is the only privileged-access lever; there is no first-look or first-buy window for any tier (each club has a single tier — Members — per B4).

**Module A vs Module S design implication** (DEC-023). The choice between (a) one allocation with two offers vs (b) two parallel allocations from the same producer commitment is a Module A design call deferred to the PRD work. The BMD fixes the customer-facing semantics: Customer sees the bottle in club at price X, optionally same bottle in Discovery at price Y, and can buy from either listing subject to segment access and purchase limits.

Domain events: `AllocationCreated` (with visibility flag), `OfferPublished` (with surface = club / discovery), `OfferRetired`.

### §4.6 Cart Hold with Timeout

NewCo adopts the Crurated v17 cart-hold-with-timeout model (D8). The customer adds offers to cart; the offer quantity is held against the allocation for a fixed timeout window; if the customer does not check out within the window, the hold expires and the units return to the available pool.

Specifics carried from v17:

- Hold begins on add-to-cart.
- Hold is a soft reservation against the Allocation, not a payment commitment.
- Timeout duration: per v17 §2.3 (the BMD does not re-derive the duration; the PRD will specify a value).
- Partial checkout permitted (the customer can buy a subset of cart contents and release the rest).

This is *the* cart-hold mode at NewCo. There is no "no-hold" alternate mode (paralleling Crurated's v11 simplification, see Appendix B).

Domain events: `CartHoldCreated`, `CartHoldExtended`, `CartHoldExpired`, `CartHoldConvertedToOrder`.

### §4.7 Checkout and Payment

Checkout terms (D9), under the **two-invoice mechanic** (DEC-045, mirroring Crurated v17 §0.7):

- **INV1 at checkout — bottle / Voucher only**. At checkout, the customer pays the bottle / Voucher amount only (the producer-set price `P` for club, or NewCo's Discovery price `P_d` for Discovery). **No excise duty** and **no destination-VAT** are added on INV1 — the destination is unknown under late binding and the MPV regime (§8.7) defers VAT recognition to the point of redemption.
- **INV2 at shipment — excise + destination-VAT**. When the customer requests shipment (§5.5) the destination is fixed; INV2 is generated covering destination-country excise, destination-country VAT, and shipping cost. The customer pays INV2 (or the delta against any pre-paid amounts) at the shipment step. There is no estimate-then-reconcile mechanic at checkout; INV1 simply does not include excise / destination-VAT.
- **Payment methods at launch**: **credit/debit card** + **bank transfer** (manual or automated SEPA / SWIFT, depending on customer geography). Both routed through **Airwallex** (DEC-014). Both INV1 and INV2 use the same payment-method options.
- **No B2B credit terms** (DEC-017, D9). Every customer is consumer; payment is collected at point of sale on INV1 and at shipment on INV2.
- **No PayPal, no crypto at launch.** Future evaluation on customer demand.
- **Bank-transfer flow**: order is placed in PENDING_PAYMENT state; the Customer is given transfer instructions; on confirmed receipt of funds, the order moves to PAYMENT_CONFIRMED → CONFIRMED, INV1 fires, and the Voucher transitions PENDING_PAYMENT → ISSUED (DEC-101 / DEC-102 / DEC-107 / DEC-112). Cart hold (§4.6) is **extended for 7 calendar days** (DEC-049) from order placement to cover SEPA same-region (1–2 days) and SWIFT cross-region (3–5 days) with margin. After 7 days without received funds, the hold expires, the order voids, and stock returns to the available pool. **PENDING_PAYMENT IS the bank-transfer credit-terms state at NewCo launch** (DEC-101): the Voucher is held in PENDING_PAYMENT pre-state until Airwallex confirms funds-cleared; INV1 issuance occurs at funds-cleared, not at customer cart-submit. **No other B2C credit-terms flow at launch** — card payments authorize-and-capture in one step (no PENDING_PAYMENT for cards); B2B credit-terms (e.g., net-30) are deferred per DEC-068. The 7-day bank-transfer window is the only credit-terms-equivalent at launch.
- **Saved payment methods** — supported for card; used for subscription auto-renewal (§2.4) and storage-fee charges (§2.7), and (where the customer has saved a card) for streamlined INV2 collection at shipment.
- **3-D Secure and SCA** — supported via Airwallex; mandatory in EEA per PSD2.

**Order = single transaction across club + Discovery + cart**. A customer's checkout can mix club offers and Discovery offers in the same cart and the same order (per the canonical happy-path member journey, K1, see §9.2). The order is one INV1 payment; line items are settled per their own commercial mechanic — club lines drive a Producer PO at 87.5% × `P` (DEC-010); Discovery lines drive a settlement to the producer of the bottle at the per-allocation `C` (DEC-032); the 5% × `P_d` Originating-Club share applies to Discovery lines only. Each line item carries its own commercial-mechanic and event sequence (§8.13); downstream accounting (Xero per DEC-028 / DEC-072) determines per-line treatment from those events.

Domain events: `OrderPlaced`, `OrderPaymentCaptured`, `OrderPaymentPending`, `OrderPaymentFailed`, `OrderShippedToFulfillment`, `OrderRefunded`.

### §4.8 Multi-Currency (D13/D14)

Multi-currency is in scope at launch (D13/D14, H8):

- **Base currency**: **EUR**. All producer settlement defaults to EUR (§3.10, C11).
- **Customer-facing currencies at launch** (DEC-037): **EUR + USD + GBP + CHF + JPY** — five currencies. EUR (base + Italy + France + EU buyers); USD (US is in the launch destination set per DEC-041); GBP (UK collector base); CHF (Switzerland — strong fine-wine buyer base); JPY (Japan — strategic Asian collector market matching the Bottle Page locale set). Additional currencies added post-launch on demand.
- **Price book**: for club offers the producer sets a EUR price; for Discovery the customer-facing `P_d` is set in EUR by NewCo. NewCo derives non-EUR customer-facing prices via a **daily-snapshot mid-rate-plus-buffer FX policy** (DEC-038) — the principle is locked here; the operational mechanics (snapshot time, buffer percentage, refresh cadence) are deferred to PRD Module E.
- **Customer pays in their selected currency**; Airwallex handles cross-currency capture; the order is recorded in EUR alongside the original currency (downstream accounting determines treatment per DEC-072).
- **Refund currency**: same as the original payment currency, at the original FX rate.
- **Multi-currency pricing book vs FX-derived**: at launch, FX-derived from EUR base. A future state where producers can quote per currency directly is on the post-launch roadmap, not at launch.

### §4.9 Purchase Limits

Purchase limits are in scope at launch as **customizable settings per club and per Discovery offer** (D15):

- **Per-offer limits** — max units per buyer per offer. Producer sets at allocation-creation time (typical for high-demand small-volume releases).
- **Per-customer per-period limits** — max units of a given Bottle Reference (or category) that one customer can buy across a rolling window. Less common; producer-configurable per club.
- **Per-club annual cap** — a club may cap the total spend or volume per Member per year (e.g., as a fairness mechanism).
- **Discovery limits** — set by NewCo per offer at curation time; producers may also flag preferred limits in their Discovery submission.

Limits are enforced at offer-render time (visible to the customer in the offer card) and again at cart-add and at checkout. Violations surface a clear customer-facing message.

The platform (Module S) will operationalize limit configuration as a generic per-offer / per-customer / per-period framework so that adding a new limit dimension post-launch does not require a re-architecture.

Domain events: `PurchaseLimitConfigured`, `PurchaseLimitEnforced`.

### §4.10 Cancellation

Cancellation is governed by the **jurisdiction of NewCo's incorporation** (D10, DEC-015). Italy is the likely jurisdiction at launch.

The **working baseline** at NewCo (DEC-057) is the **Italian / EU consumer-law withdrawal right**: the consumer can withdraw from a distance-selling contract within **14 days** of contract conclusion, without giving any reason, and recover the purchase price. Italian counsel review pre-launch is a non-blocking validation (DEC-057).

**Timer-start and pre-shipment-only scope (DEC-108)**: the 14-day window starts at **INV1 issuance** (= post-payment-cleared per DEC-112 = order confirmation; for bank-transfer flows this means after Airwallex confirms funds-cleared, not at cart submission). The window applies **pre-shipment only** — the customer can cancel while the Voucher is in PENDING_PAYMENT, ISSUED, or REDEMPTION_REQUESTED state. **Once the Voucher transitions REDEMPTION_REQUESTED → SHIPPED, the cancellation right is WAIVED** (the customer cannot cancel the purchase from that point on).

Rationale for the post-shipment WAIVER: returning shipped wine compromises provenance + temperature integrity; reverse-logistics cost is prohibitive at NewCo's scale. This is **permitted under EU Distance Contracts Directive 2011/83/EU Article 16**, whose carve-out from the withdrawal right covers "goods which are liable to deteriorate or expire rapidly" — wine post-shipment qualifies (the cold chain is broken at hand-off, and resale of a returned bottle is not a posture NewCo operates). The WAIVER must be **clearly disclosed in the customer-facing T&C** so the consumer enters the purchase informed of the post-shipment finality (v17 line-by-line confirmation: 14-day pre-shipment-only, no refund post-shipment).

Important nuances for fine wine + Voucher model:

- **Pre-shipment cancellation** (Voucher in PENDING_PAYMENT / ISSUED / REDEMPTION_REQUESTED, bottle still in custody) — within the 14-day window from INV1, customer can cancel and full refund per §4.11; Voucher VOIDED, Allocation `qty - issued` restored per DEC-079.
- **Post-shipment** (Voucher SHIPPED) — cancellation right WAIVED per DEC-108. Post-delivery issues (damage / loss / fault) are handled via **Module C returns + replacement flow** (NOT Module S cancellation): a replacement shipment is issued (no new Voucher, no new INV2 — per v17 line 3768); cost is recorded as a non-revenue event by Module E. Exceptional post-delivery refunds require supervisor override (auditable), per §4.11.
- **Membership cancellation** — distinct from purchase cancellation; see §2.4 (auto-renewal cancellation by the customer). If the Customer cancels a membership *within the 14-day window* of the Hero Package payment **and the Hero Package has not yet shipped**, the cancellation also voids the Hero Package purchase and the constituent Vouchers are voided per DEC-109's 1-voucher-per-bottle invariant. Once any constituent has shipped, that constituent is no longer cancellable and the membership-cancellation effect is partial.

The PRD will operationalize the cancellation workflow; the BMD records the policy stance.

### §4.11 Refund and the Refund-Cost Matrix (DEC-025)

NewCo supports **full refunds, partial refunds, and store-credit alternatives** (D11). The choice of refund mechanism depends on the cause:

| Reason | Refund issued to customer? | Cost absorbed by | Notes |
|--------|----------------------------|-----------------|-------|
| Bottle faulty / damaged / corked at the producer | Yes | **Producer** | Producer-fault refunds clawed back from producer settlement (§3.10) |
| Bottle damaged in NewCo / Vinlock custody | Yes | **NewCo** | Carrier-insurance recovery if applicable; gap absorbed by NewCo |
| Bottle damaged in transit | Yes | **Carrier insurance** + NewCo gap | NewCo absorbs uninsured gap |
| Customer cancellation in 14-day pre-shipment window (per §4.10 / DEC-108) | Yes | **NewCo** | Per Italian/EU consumer law; pre-shipment ONLY — post-shipment cancellation right WAIVED per DEC-108 (EU Distance Contracts Article 16 carve-out for goods that deteriorate / expire rapidly) |
| Customer dissatisfaction / taste post-shipment | **No** | n/a | No returns post-shipment (E10); cancellation WAIVED post-shipment per DEC-108 |
| Customer fraud (e.g., chargeback abuse) | Refund honored at chargeback level; recovered via dispute | **NewCo** absorbs immediately | NewCo pursues recovery via Airwallex dispute / legal channels |

**Refund forms** — full refund to original payment method by default; partial refund where partial responsibility (e.g., one bottle of a six-pack damaged); store credit as an alternative offered to the customer in cases where they prefer it. The **store-credit goodwill premium is admin-configurable per case, defaulting to 105% of refund face value** (DEC-044) — ops can adjust per case, and the customer can always opt for a cash refund at 100% face value if they prefer.

**Post-delivery issues — replacement-not-cancellation route (DEC-108)**: damage / loss / fault discovered after the bottle has shipped is handled via the **Module C returns + replacement flow**, NOT via Module S cancellation (cancellation is WAIVED post-shipment per DEC-108). A replacement shipment is issued without a new Voucher and without a new INV2; the cost is recorded as a non-revenue event by Module E. Exceptional post-delivery refunds (where replacement is not feasible or the customer specifically requests cash) require **supervisor override (auditable)** and follow the producer-/NewCo-fault attribution in the matrix above.

**Storage-fee refund on partial cellar refund** (DEC-046): when part of a customer's cellar is refunded, the accrued storage fee on the refunded item is refunded **pro-rata** back to the bottle's storage-clock start, **where the underlying refund cause warrants it** (e.g., NewCo-fault breakage refund — yes; customer-fraud refund — no). Cause-conditional, not blanket; the refund-cost matrix above governs which causes attract a storage-fee refund. Pro-rata reads INV3 / INV2 cycles + the first-12-months-free rule (DEC-118).

Domain events: `RefundRequested`, `RefundIssued`, `RefundClawbackToProducer`, `RefundChargebackOpened`, `RefundChargebackResolved`.

### §4.12 Discounts, Coupons, Club Credit (D12)

NewCo supports a unified discount layer at launch:

- **Promo codes** — NewCo-issued or producer-issued (with NewCo agreement) codes that apply a discount at checkout. Use cases: launch promotions, partner referrals, marketing campaigns. Per DEC-039, NewCo never silently discounts a producer-set price on club offers; club promotions require explicit per-campaign producer opt-in. On Discovery, NewCo controls pricing and is therefore free to run promotions without producer consent.
- **Store credit** — a customer-level balance that can be applied to any NewCo purchase (club or Discovery). Issued for refunds (§4.11), goodwill, customer-service interventions. Goodwill premium configurable per case (default 105% — DEC-044).
- **Club Credit** — a *profile-level* (i.e., club-specific) balance, spendable only on that club's offers. Issued primarily through the Hero Package mechanic (DEC-007, §2.3) and ad-hoc producer adjustments. **Auto-apply at checkout** (DEC-111): when a customer's cart contains at least one line eligible (Offer from that Profile's Club), Module S auto-applies the credit at checkout-render time to eligible line(s) up to **capacity needed** (= min(`credit.balance`, sum of eligible line totals)); the customer can **remove** the credit via explicit UX action (keeps cash payment + reserves credit for later). For multi-Profile customers, each credit auto-applies to its respective eligible lines (no cross-Club credit pooling). On producer offboarding, residual Club Credit converts to Discovery store credit at face value, valid for 12 months (DEC-043).
- **Hero Package = Membership Fee** is itself the structural anchor; not a discount but a fixed-value entitlement.
- **Loyalty / referral rewards** — not in scope at launch; on post-launch roadmap.

Stacking rules — promo + credit + Club Credit may stack subject to limits set per discount; the PRD will specify the exact stacking algebra. The BMD records the inventory of discount types.

Domain events: `PromoCodeApplied`, `StoreCreditIssued`, `StoreCreditApplied`, `ClubCreditAccrued`, `ClubCreditApplied`.

### §4.13 Gifting (Phase 1)

Member-to-member gifting is in scope at launch (B14, K7).

A Member can gift a Voucher (one or more bottles) to another Customer. The recipient must be a NewCo Customer (or accept an invitation to become one). The Voucher transfers from the giver's cellar to the recipient's cellar; ownership of the underlying physical stock follows.

Mechanics:

- **Ownership transfer at the Voucher level** — not a re-sale and not a new transaction at the producer level. Producer settlement is unchanged (the original sale is what was settled).
- **Recipient's segment** — gifting works across all three customer segments (Member, Waiting-list, Legacy). A Legacy customer can gift; a Waiting-list customer can receive.
- **Originating Club implications** — gifting does not change the giver's or recipient's Originating Club. (Edge case: a Discovery purchase that becomes a gift retains the giver's Originating-Club share calculation at the time of original purchase.)
- **Tax / fiscal handling** — gifting creates no new sales-tax event in NewCo's ledger (since no new sale); customs / cross-border implications are handled at shipment time as normal (§5.4). **Cross-border tax / gift-tax implications are the customer's own responsibility** (DEC-036) — NewCo records the transfer for audit and provides the customer with the transfer record on request, but does NOT compute, withhold, or remit gift-tax on the customer's behalf. Customer terms surface a brief "gifts may have tax implications in your jurisdiction" notice. NewCo's own VAT/excise position on the original sale is unchanged (no new sale event).

**P2P / collector-to-collector resale** is OUT at launch (L2, §13.5). CruTrade-style P2P trading is reserved for Phase 2 if and when it becomes a strategic priority.

Domain events: `VoucherGiftSent`, `VoucherGiftAccepted`, `VoucherGiftDeclined`.

### §4.14 Services and Experiences (Placeholder)

D1 and H3 reserve services and experiences as a secondary product line, with mechanics TBD (Q-OQ-8 / Q-OQ-13). At launch:

- **Storage** is the **only "service" operationalised at launch** (DEC-118 carve-out on Q-OQ-13). Mechanics + economics are fully locked at §2.7 / §5.6 / §8.4; INV3 is the dedicated customer-facing invoice (§8.13).
- **Free experiences** — bookable through the Consumer Portal (e.g., estate visits available to club Members). The booking captures the customer intent and notifies the producer; no transaction.
- **Paid experiences** — placeholder; not operationalized at launch. Mechanics, revenue split, refund policy, and seller-of-record stance all TBD.

The BMD does not lock paid-experience economics; future revisions will, once Paolo and Operations decide.

---

## §5 Sourcing and Fulfillment

> Inbound under passive consignment V1 and V2, custody at the single Vinlock-operated warehouse in France with Logilize as WMS, late binding at shipment, storage-as-a-service policy, shipping modes, country eligibility, breakage and transit policy, and the no-returns-post-shipment rule.

### §5.1 Inbound Sourcing — Passive Consignment V1 + V2 + Direct Purchase as Exception

NewCo's sourcing universe at launch comprises **passive consignment** (default, two sub-models V1 + V2) and **direct purchase** (exception per DEC-063). Active consignment and drop-shipping are out (DEC-011, L3, L5, see §13.1, §13.3). Three models coexist:

- **Passive Consignment V2 (default)**. Stock is shipped from the producer to the Vinlock-operated warehouse in France, accepted into NewCo / Vinlock custody, serialized (NFC tag applied per §6.3), and sold to customers from there. Title remains with the producer until each unit sells through; on sell-through, title flips through NewCo (Seller of Record) to the Customer.
- **Passive Consignment V1 (exception)**. Stock stays at the producer's premises. When a customer order materializes, the producer ships the specific units to the Vinlock warehouse for staging, and from there to the customer. Used only for **very expensive / very rare bottles** (C7) where pre-emptive transfer to the warehouse is impractical.
- **Direct purchase (exception, DEC-063)**. NewCo pays the producer / supplier outright at purchase, takes title at purchase, holds the inventory at Vinlock, and sells with full title transfer to the customer. Used for strategic stock-up, one-off Discovery sourcing where the producer prefers a clean sale, or supplier relationships where outright purchase is the commercial norm (e.g., Crurated as a Discovery supplier per DEC-020). **Event-wise**: the producer-payment event fires at purchase (not at sell-through); the title-transfer-to-NewCo event fires at purchase. The accounting integration (Xero per DEC-028) receives these events and determines accounting treatment per its own policy (per DEC-072 — BMD / PRD do not take positions on accounting policy). See §3.7 for the broader policy.

The passive-consignment models echo Crurated v17 §1.4 / §0.5 by reference — same V1 / V2 distinction, same passive-consignment commercial substance. **Direct purchase is NewCo-specific** (not a v17 carryover; v17 frames direct purchase as out under its B2C-only consignment posture). The simplification at NewCo is that there is no V1/V2 *active* counterpart (Crurated v17 supports active consignment for B2B; NewCo is consumer-only and does not).

**Inbound acceptance is a two-phase event** (consistent with Crurated v17 Stage 2.3 split-inbound model — see Appendix B for the inheritance):

1. **Physical acceptance** at the warehouse — Vinlock confirms the shipment matches the producer's manifest, units are usable (no damage in transit), and stock can enter the salable pool. Triggers `InboundPhysicallyAccepted`.
2. **Cost finalization** — once cost / pricing is reconciled with the producer (typically immediate for passive consignment since the producer's price is set at allocation creation), the inbound is *commercially* finalized. Triggers `InboundCostFinalized`. SLA for cost finalization: 5 working days post-physical-acceptance, mirroring v17.

V1 inbound differs only in timing: physical acceptance happens at the moment the producer ships to the warehouse for a customer order, not as a pre-emptive bulk shipment.

**Receiving discrepancy is a two-stage check at NewCo launch (DEC-194; v17 §B.9 lineage)**. The two stages divide responsibility cleanly: **Module D = documents in order** (the 3-gate inbound QC at PHYSICALLY_ACCEPTED — paperwork, provenance, physical-condition-on-arrival; Module D PRD §7); **Module B = physical match** (a separate downstream check on each InboundBatch — Module B compares physically-counted bottles against the qty declared in Module D's `InboundEventPhysicallyAccepted` payload). Any variance emits `InboundBatchDiscrepancy` event back to Module D, which reopens the InboundEvent into a DISCREPANCY state without retroactively invalidating already-live InboundBatch records. Resolution flows downstream through Module D's existing DiscrepancyResolution paths (Accept Shortage, Return + Reorder, Return for Credit, Adjustment, Supplier Replacement, Write-Off) per DEC-091; cost-basis reconciliation if discrepancy resolution adjusts qty flows back into `InboundEventCostFinalized` (Phase 2). The two-stage split restores the v17 §B.9 inbound-physical-discrepancy detection KPI that a Module-D-only acceptance gate could not deliver — Logilize-side count errors are caught at the ERP layer instead of propagating undetected to fulfillment.

Domain events: `InboundShipmentExpected`, `InboundPhysicallyAccepted`, `InboundCostFinalized`, `InboundDiscrepancyFlagged`, `InboundBatchDiscrepancy` (Module-B-emitted per DEC-194).

### §5.2 Custody — Vinlock-Operated Warehouse in France

NewCo operates a **single physical warehouse at launch** (E3, DEC-014):

- **Operator**: **Vinlock** — third-party warehouse operator (E2). Same physical warehouse facility as Crurated (E4), but with **separate contracts** between Vinlock and NewCo. The intragroup carve-out (DEC-001) holds: there is no shared inventory pool, no shared custody record, no shared API key. Inventory is fenced and tracked per-tenant inside the warehouse's operational systems.
- **Location**: France (E2). Specific site is shared with Crurated; legal and operational separation between the tenants is contractual.
- **Custody scope**: Vinlock receives, stores, picks, packs, and dispatches. NewCo's logistics manager directs operations; Vinlock executes (E8).
- **Insurance**: Vinlock holds custodial insurance for stock under its control; NewCo carries supplementary cover where contractual gaps exist. **Insurance basis is deferred at BMD level** (DEC-048) — the detailed valuation basis is determined operationally in alignment with Vinlock contractual conditions; not locked at BMD level. DEC-048 supersedes v0.1's working baseline.
- **Multi-warehouse expansion** is **OUT at launch** (E3, §13.8). Phase 2 may add sites; the data model is designed to admit additional warehouses without re-architecture but the launch operates one site only.

### §5.3 WMS — Logilize

NewCo runs **Logilize WMS** (DEC-014, E2/J3):

- Same WMS provider as Crurated, but a **separate Logilize integration** for NewCo. Independent tenant, independent API credentials, independent data.
- Integration scope at launch:
  - Inbound receipt + serialization (NFC tag application per §6.3 happens at receipt, captured in Logilize against the bottle's serial).
  - Stock state by Bottle Reference (available, reserved, picked, shipped) at the **physical-execution layer**.
  - Pick / pack / dispatch workflow execution.
  - Outbound shipment confirmation back to NewCo's ERP.
  - Sub-warehouse storage-location detail (row / rack / cellar zone — Logilize-internal; customer-facing display is warehouse-level only per DEC-153).

**Logilize is the system of record for *physical-execution* state on the workflow axis** (in-warehouse / in-transit / delivered / damaged / lost-in-custody location and movement at sub-warehouse granularity; pick-pack-dispatch execution). **NewCo's ERP — specifically Module B — is the system of record for *inventory-state* on the ledger axis** at the four orthogonal dimensions per DEC-185 + Module B v0.2 §2.1: (1) **ownership** (2-value enum at NewCo launch — `PRODUCER` for consigned stock with title held by the producer; `CRURATED` for direct-purchase stock and post-supplier-payment passive-consignment stock); (2) **custody** (warehouse + storage-location reference at warehouse-level summary); (3) **commercial status** (`available` / `committed` / `reserved`); (4) **allocation lineage** (immutable UUID per DEC-076 binding every bottle to its source Allocation). Module B owns the entity-level ledger — InboundBatch (per DEC-195), StockPosition (5-dimension aggregated view per DEC-196), Case (3-state integrity FSM per DEC-192), QuarantineRecord (quarantine-before-trust per DEC-191), Stocktake (4-state lifecycle per DEC-189) — and all inventory adjustments (per DEC-190). Module B's role is ledger discipline: ATP source, receiving physical-match authority (per DEC-194), stocktake variance computation, adjustment proposal-and-confirmation, no-overselling at the physical-inventory layer (the second leg of the two-layer guard per Q-CL-5 + DEC-187, with Module A's allocation-pool layer being the first leg), and committed-inventory protection (committed inventory cannot be diverted for adjustments without first releasing the commitment via Module A `VoucherCancelled`).

**Reconciliation flows through explicit adjustment events, never silent overrides** — Module B never creates inventory records from unverified Logilize data; unknown entities reported by Logilize land in QuarantineRecord pending manual supervisor investigation per DEC-191 ("quarantine before trust"). The two-system source-of-truth split eliminates the v0.8-era vendor-risk concentration on Logilize: Module B's independent ERP-side ledger detects what Logilize-side errors (serial mismatches, count drift, batch confusion) would otherwise propagate undetected. PRD detail at Module B PRD v0.2 + Module C PRD v0.2; the integration is split at the stream level per DEC-188 (see §6.3 + §11.3).

### §5.4 Excise, Customs, and Cross-Border Movement

Alcohol logistics has non-trivial regulatory overhead. The split (E8), under the **two-invoice mechanic** (DEC-045, §4.7, §8.6):

- **NewCo's logistics manager** owns the regulatory frame: alcohol distribution license held by NewCo (I6); excise calculations and pass-through to customers via INV2 (H7); customs documentation per destination country.
- **Vinlock** executes the operational steps under that frame: lodging documentation, bonding goods where needed, executing inbound/outbound declarations.
- **Excise duty timing**: excise is computed **at shipment time per destination** and applied to **INV2** (DEC-045). There is **no estimation at checkout** — INV1 (at checkout) covers only the bottle / Voucher amount; excise is added on INV2 at shipment when the destination is known. As a consequence there is no estimate-vs-actual reconciliation step on excise (Q-OQ-30 in v0.1 — RESOLVED via DEC-045).
- **Bonded warehousing** — typical for fine wine; the warehouse setup admits bonded movement, which means VAT and excise are deferred until release-for-consumption (i.e., shipment to a final consumer destination). The MPV VAT regime (§8.7) interacts with this: VAT and excise are recognized on INV2 at the regulatory event of ship-out, never on INV1.

### §5.5 Late Binding at Shipment

The Voucher model (§4.4) is paired with **late binding** of physical bottle to customer voucher (E11, D5):

- A Voucher is for a **Bottle Reference + quantity**, not for a specific physical bottle (with its specific NFC tag / NFT).
- At shipment-request time, the warehouse picks any available unit of that Bottle Reference from the producer's available stock (subject to the same producer's allocation pool the voucher draws from).
- The picked bottle's specific serial / NFT is bound to the customer's shipment at pick time and recorded against the customer (this is the moment the customer's identity becomes associated with that specific bottle, internally).
- The NFT is **burned at shipment** (§6.7); the customer's bottle leaves the wallet.
- **Bottle-page** privacy: the bottle page does not expose customer identity (DEC-024, F9), so even though there is an internal binding, the public bottle-page record stays anonymous (§6.8).

Late binding gives NewCo flexibility to optimize stock rotation (FEFO / FIFO at the warehouse level) without breaking customer-facing promises.

**Two-surface selection algorithm at launch** (DEC-137; Paolo refinement). Late binding operates on two surfaces: (i) **voucher-side** = FIFO by Voucher expiry — among vouchers in the same allocation pool referencing identical bottles, the longest-held / earliest-expiry voucher gets the bottle; (ii) **bottle-side** = Logilize warehouse-efficiency rules — among physical bottles in the same allocation pool, the most efficient pick wins (storage-location proximity, pick-routing optimisation). The allocation-pool boundary is invariant (cannot pick across pools). Producer-override of the selection algorithm (e.g., FEFO by drinking-window or specific-vintage-only) is deferred Phase 2+.

Domain events: `ShipmentRequested`, `ShipmentBound` (specific bottle assigned), `ShipmentDispatched`, `ShipmentDelivered`.

### §5.6 Storage as a Service

Storage policy (DEC-013, DEC-118, DEC-119, E6, B9, H4) — recap from §2.7:

- **First 12 months free per bottle**, counted from INV1 issuance. Charges begin month 13.
- **After 12 months**: **€3 / bottle / year = €0.25 / bottle / month**; partial months round up.
- **Storage-clock-start double-anchor** (DEC-119): accrual begins at the later of `INV1 + 12 months` and the bound Allocation's `InboundEventPhysicallyAccepted` date — bottle must be at Vinlock for any part of an accruing month. Collapses to `INV1 + 12 months` for V2 default; waits for physical arrival for V1 / Direct Purchase in-transit-at-INV1 cases (per DEC-081).
- **Cadence**: semi-annual issuance (end of June + end of December); covers the prior 6-month period. Mid-semester shipments roll the in-semester storage charge into that bottle's INV2 (§8.4 / §8.13).
- **All segments eligible** — Members, Waiting-list, Legacy.
- **Failed charge** → dunning → Suspension.

**Module S owns storage-fee computation + INV3 issuance + per-bottle accrual events** (DEC-119; supersedes DEC-118's ownership clause). Module S subscribes to Module D's `InboundEventPhysicallyAccepted` for the bound Allocation to anchor the storage-clock-start trigger; emits `StorageFeeAccrued` per bottle per month after both 12-months-free-from-INV1 and bottle-at-warehouse conditions are met; aggregates the prior 6 months into `InvoiceINV3Issued` at semester-end; rolls in mid-semester storage as additional INV2 line items as Module-S-internal logic (no cross-module query). Module E consumes Module S's customer-facing invoice events, routes them to Xero per DEC-072, and executes the Airwallex charge per DEC-014. Storage as a service is the only "service" operationalised at NewCo launch (Q-OQ-13 carve-out per DEC-118; cross-link §4.14) and is one of NewCo's monetization streams (§8.4), in addition to the headline 12.5% margin. The €3/bottle/year is configured per the Vinlock cost basis plus margin; the precise unit economics are Finance's territory.

### §5.7 Shipping Modes

Shipping modes at launch (E5):

- **Direct shipment to customer's stated address** (default). Carrier per destination country and bottle count, on a per-shipment quote.
- **Customer pickup** — customer collects from the Vinlock warehouse (case-by-case, by appointment; subject to Vinlock's ability to host).
- **Events** — shipment to a producer event, club event, or NewCo-organized event where the customer collects in person. Operationally similar to a B2B-style consignment shipment to the event venue.

**Shipping fees**: customer pays based on automated quote at checkout (H5). Quote considers carrier(s), bottle count, weight, destination country, and excise/customs handling fees. Shipping is not bundled into the bottle price; it is a separate line item.

The quote-generation system supports **two paths** at launch (DEC-145; Paolo refinement): (i) **automatic** — operator-configurable rule set + carrier-API integration where available; (ii) **manual** — operator-entered quote (fee + selected carrier + transit estimate), used in white-glove cases per the §5.8 fallback. The same `ShippingFeeQuoted` event fires in either case; a `quote_origin` discriminator (`auto` / `manual`) records which path produced the quote for audit. Carrier selection at launch is operator-configurable rule-set-driven, not customer-facing preference.

### §5.8 Shipping Eligibility by Country

Customer markets at launch are **global, with case-by-case alcohol-restriction handling** (E7, I3, I4):

- **Default shipping universe** (DEC-041): launch destination countries mirror Crurated's current shipping universe (~25 countries), **including the US**. The exact launch list is finalized by NewCo logistics + legal pre-launch.
- **Restrictions are country-specific** — alcohol importation rules, license requirements, age-verification at delivery, dry-state restrictions (US), import quantity caps, etc. **At launch the US-state rule matrix is intentionally narrow** (DEC-148): operators pre-clear easier states for the automated shipping path; harder states route through the white-glove customer-service fallback (described below). Detail rule-matrix expansion is Phase 2+ — country-by-country and state-by-state refinements are integrated as the operation matures, consistent with the simple-at-launch posture for non-EU shipping terms (DEC-149).
- **OFAC screening required at launch** as a direct consequence of the US being in the destination set (DEC-041 amends DEC-030 — OFAC is no longer conditional). Every customer is OFAC-screened at onboarding alongside the EU + UIF check (§2.8, §10.7).
- **Excluded destinations** — explicitly listed in NewCo's terms; visible to customers at signup (so customers in excluded markets are aware they cannot ship in-region — they can still buy and store).

**Two-tier eligibility model** (DEC-147; Paolo refinement). The platform operates two paths at launch:

- **Automated path**: for the pre-cleared destination subset. Destination address validated against the eligible-destinations list at Checkout (Module S) and at Shipping Order creation (Module C); eligible destination → standard checkout / SO progression with automated quote per §5.7.
- **White-glove customer-service fallback**: for non-eligible / complex destinations. Ineligible destination at Checkout / SO creation **does NOT terminate the path** — Customer-facing surface offers a "send shipping request" CTA → creates a Customer Care ticket; Customer Care reviews case-by-case and may approve with a manually-entered quote per DEC-145 (fee + selected carrier + transit estimate, recorded as `quote_origin = manual` for audit). If approved, the SO proceeds normally; if denied, the Customer chooses continued-storage or cancellation per the §4.10 14-day window (DEC-108). Composes with the Producer Portal ↔ Admin Panel parity contract (DEC-115 / DEC-083): the eligible-destinations list and the case-management surface are exposed in the NewCo Admin Panel.

### §5.9 Damages, Breakage, Transit Loss

Same handling pattern as Crurated v17 (E9), with the cost allocation per the refund-cost matrix (DEC-025, §4.11):

- **Damage detected at warehouse on inbound** — the unit is rejected, returned to producer or written off; producer absorbs.
- **Breakage in custody (warehouse)** — NewCo absorbs; insurance recovery if applicable.
- **Transit damage** — customer reports; carrier insurance kicks in for covered scenarios; NewCo absorbs the gap.
- **Total loss in transit (lost shipment)** — same as transit damage; NewCo coordinates carrier claims; customer is made whole (refund or replacement at customer's choice and subject to availability).

**Adjustment workflow + event catalogue (Stage 8 per DEC-190)**. Module B owns the inventory-adjustment workflow at NewCo launch with a unified `InventoryAdjusted` event carrying an `adjustment_type` discriminator (`damage` / `loss` / `consumption` (Phase-2+ placeholder) / `recount` / `transfer` / `found`); proposal flow is operator-initiated (or stocktake-variance-derived per DEC-189; or QuarantineRecord-resolution-derived per DEC-191) → supervisor approval → terminal-state event emission. **Committed-inventory protection guard**: a proposal that would reduce committed inventory below outstanding vouchers is rejected and instead emits `InventoryShortfallDetected` to Module A — the proposal cannot proceed until Module A `VoucherCancelled` first releases the commitment. **Module E consumes** `InventoryAdjusted` for damage / loss / write-off financial-event recording per DEC-072; Xero decides GL treatment per `feedback_bmd_prd_no_accounting`. The bottle-state-axis split per DEC-151 stays unchanged: in-custody breakage = Module B (per DEC-132 §6.11.4 recovery scenario); transit damage / loss / write-off = Module C (per DEC-151 + DEC-138 returns + replacement workflow); the adjustment event catalogue per DEC-190 unifies the financial-event ingestion shape regardless of the bottle-state-axis owner.

Domain events: `BottleBreakageInCustody`, `BottleBreakageInTransit`, `BottleLossInTransit`, `BottleWriteOff`, `InventoryAdjusted` (Module-B-emitted per DEC-190), `InventoryShortfallDetected` (Module-B-emitted per DEC-190 §13.4), `InsuranceClaimOpened`, `InsuranceClaimResolved`.

### §5.10 No Returns Post-Shipment

**Once a bottle has left the warehouse, it cannot be returned to NewCo's custody** (E10).

The rationale is double:

1. **Provenance integrity** — fine wine's value depends on continuous custody; a returned bottle has a custody gap NewCo cannot vouch for. The NFT is burned at shipment (§6.7), reinforcing the custody-finality posture.
2. **Operational simplicity** — returns inbound from end-customers introduce a workflow (acceptance, condition check, re-stocking, refund) that NewCo does not operate at launch.

Refund flows post-shipment are restricted to the cases in DEC-025 that allow refund without physical return (e.g., transit-damage where the carrier handles the lost goods, customer-fraud chargeback). Customer dissatisfaction post-shipment yields no refund (DEC-025).

Withdrawal-right cancellation per Italian/EU consumer law within the legal window may technically allow physical return of intact goods; the BMD's working assumption is that NewCo does not operate this as a routine workflow but handles it as an exception with manual logistics support. Italian counsel review pre-launch is a non-blocking validation (DEC-057).

---

## §6 Serialization, NFC, and NFT Model

> Default-on serialization with a non-serialized exception path, NFC tag application by Vinlock employees, NFT mint timing on Avalanche under NewCo wallet custody, burn at shipment, public anonymous Bottle Page, NFT legal status posture, and the four recovery scenarios with proposed defaults. The serialization layer sits within a broader **four-way reconciliation discipline** that binds physical execution, ERP-side inventory ledger, commercial state, and financial state across NewCo's eight modules.

**Four-way reconciliation discipline (Stage 8 per DEC-185 + Module B v0.2 §2.4)**. NewCo's launch ERP architecture rests on four orthogonal authorities each owning a distinct slice of bottle / inventory / commercial / financial state, reconciled through explicit cross-module event flows rather than silent overrides:

1. **Logilize = physical execution authority** (§5.3, §11.3) — in-warehouse / in-transit / delivered / damaged / lost-in-custody location and movement at sub-warehouse granularity; pick-pack-dispatch execution; Stream 1–4 fulfillment instructions and confirmations from Module C per DEC-188.
2. **Module B = ERP-side inventory ledger authority** (this section + §5.3 + §9.8) — InboundBatch + StockPosition + Case + QuarantineRecord + Stocktake entity ownership; ATP source per allocation; receiving physical-match check (per DEC-194); stocktake + adjustment authority; provenance immutability; no-overselling at the physical-inventory layer; committed-inventory protection; the digital-provenance sub-layer (NFC + NFT + Bottle Page + recovery scenarios — content of §6 below).
3. **Module S = commercial state authority** (§4 + §8) — Allocation visibility + Offer publication + Cart Hold + Order FSM + Voucher FSM + customer-facing INV1 / INV2 / INV3 issuance; storage-fee computation + per-bottle accrual.
4. **Module E = financial state authority** (§8 + §11.4 + §11.6) — payment execution against Airwallex; supplier-side settlement; chargeback ingestion; non-revenue cost recording (replacement / breakage / transit-loss / write-off / insurance recovery); Xero routing for accounting + document generation per DEC-028 + DEC-072 + DEC-119 clarification; multi-currency dual recording.

The four-way regime supersedes v0.8's three-way regime (Logilize ↔ Module S ↔ Module E with Logilize concentrating both physical execution and ERP-inventory-source-of-truth roles) by giving Module B independent ERP-side inventory-ledger authority. The architectural cost of running with three-way regime — vendor-risk concentration on Logilize, undetected Logilize-side count / batch / serial errors, non-serialized stock without an ERP-side home, WMS-agnostic future blocked — is eliminated by Stage 8's restoration. Reconciliation between the four legs flows through three primitives: **receiving** (Module D = documents, Module B = physical match per DEC-194; `InboundBatchDiscrepancy` reopens the InboundEvent into DISCREPANCY); **stocktake** (Module B owns Stocktake entity + 4-state lifecycle per DEC-189 with Logilize executing the physical count via DEC-188 Stream 5); **adjustment** (Module B owns proposal-and-confirmation workflow per DEC-190 with `InventoryAdjusted` + `InventoryShortfallDetected` emission); plus the **QuarantineRecord gatekeeper** (Module B never creates inventory records from unverified Logilize data per DEC-191). The two-layer no-overselling guard at hold-placement / voucher-issuance — Module A allocation-pool layer (`qty − issued ≥ 0` per DEC-099) plus Module B physical-inventory layer (`physical_in_storage − reserved − quarantined − under_adjustment ≥ 0` per Module B v0.2 §10.5) — is the day-to-day load-bearing application of the discipline.

### §6.1 Coverage — Default Every Bottle, Non-Serialized as Exception

**Default-on serialization**: every bottle entering NewCo / Vinlock custody is serialized via NFC tag and represented on-chain by an NFT (F1).

**Non-serialized as a producer-by-producer or product-by-product exception**: some producers, or some specific products, may not be serialized. Reasons can include:

- Producer commercial preference (some producers reject NFC serialization on principle).
- Bottle format incompatible with the NFC application (tiny half-bottles, magnums with awkward neck profiles, certain unconventional packaging).
- Cost / return-on-effort consideration for very low-value SKUs (rare but possible).

NewCo retains the **two-identity model from Crurated v17**: serialized bottles and non-serialized bottles co-exist, with serialized as the default. The Allocation entity carries a `serialization_type` flag (mirroring the Crurated v13 Stage 2.4 design — see Appendix B).

For non-serialized bottles, the sale, voucher, custody, and shipment flows all work without the NFT layer. Bottle Page is unavailable for non-serialized bottles (no NFT, no NFC scan target). The customer-facing implication (no Bottle Page, no NFT-backed provenance) is surfaced upfront so the customer can make an informed choice.

**The non-serialized exception is admissible across all allocations** (DEC-080, broadening DEC-052). Producer can opt out of serialization at allocation creation regardless of `visibility` (CLUB_ONLY or DISCOVERY_ONLY) or `sourcing_model` (passive consignment V1 / V2 or direct purchase) — i.e., the opt-out is **not gated by visibility**. The Allocation entity carries a `non_serialized_offer_admitted` boolean (default FALSE — serialization-by-default per the policy above stands); the producer (via Producer Portal) or NewCo ops (via Admin Panel) flips the flag to TRUE when the producer prefers to admit a non-serialized offer for that allocation. The flag is **per-allocation, not per-producer**: one producer may opt-in for some allocations and out for others (e.g., the producer's Hero Package allocation may be serialized while a year-round normal allocation is not). Non-serialized stock may be exposed on Discovery with a clear "non-serialized" badge on the offer (the original DEC-052 surface treatment) and is equally admissible on club allocations under the broadened DEC-080 framing. Sub-pool partition mechanics (`qty_to_serialize` / `qty_non_serialized` numerics; pre-issuance rebalance; post-issuance not-yet-issued-only mutation) carry forward unchanged across all allocations.

### §6.2 Serialized vs Non-Serialized Stock

| Aspect | Serialized | Non-serialized |
|--------|-----------|----------------|
| NFC tag | Yes (applied at receipt) | No |
| NFT mint | Yes (at NFC application) | No |
| Bottle Page | Yes — public, NFC-scannable | No |
| Custody tracking | Per-unit by NFT | By-quantity, by-Bottle-Reference |
| Voucher and late binding | Yes (§4.4, §5.5) | Yes — late binding still applies; but the bound unit is by quantity, not by NFT |
| Discovery listing | Yes | Yes, with "non-serialized" badge (DEC-052); admissible across all allocations per DEC-080 (club listings too — opt-out is per-allocation, not visibility-gated) |

### §6.3 NFC Tag Application

NFC tags are applied **at the warehouse, by Vinlock employees, instructed by NewCo** (F2). Tags are **not** applied at producer site at bottling — i.e., the producer ships an unserialized bottle and NewCo / Vinlock serializes it on receipt.

Workflow:

1. Bottle arrives at warehouse (passive consignment V2 inbound).
2. Vinlock receives, inspects, accepts under §5.1 phase 1.
3. Vinlock employee applies the NFC tag per NewCo's tag-application standard (placement, validation read).
4. The NFT is minted on Avalanche at this moment (§6.5), associated with the NFC tag's unique identifier and the bottle's metadata (Bottle Reference, producer, vintage, allocation source).
5. The bottle enters salable state — Module B's StockPosition view (per DEC-196) reflects the bottle in the available sub-pool of the source Allocation; Module A's ATP cache (per DEC-187) is updated through Module B's push.

**For passive consignment V1**, the same workflow applies once the producer ships the unit to the warehouse for a customer order — the unit is tagged on its way through the warehouse.

The tag-application standard, including failure modes and re-attempts, is an operational document maintained by NewCo logistics; the BMD records that the responsibility lies with Vinlock under NewCo's instruction.

**Logilize stream split (Stage 8 per DEC-188; supersedes DEC-140 on the inventory-state axis)**. Logilize and the NewCo ERP communicate across two axis-aligned stream sets, divided at the Module C / Module B boundary. **Module C owns 4 fulfillment streams** (DEC-140's first four streams retained verbatim): outbound pick instruction; pick confirmation; dispatch confirmation; delivery confirmation. **Module B owns 5 inventory-state streams** (Stage 8 net-new): storage-location tracking (Stream 5 migrated from Module C — the original DEC-140 framing placed it under Module C, but it is inventory-state, not fulfillment, per DEC-185's four-orthogonal-dimensions framing); receiving + physical-match per DEC-194 (the InboundBatch creation chain at Phase 1 PHYSICALLY_ACCEPTED + Module B's downstream physical-count check); stocktake instruction + variance reporting per DEC-189; inventory-adjustment proposal-and-confirmation per DEC-190; QuarantineRecord resolution flow per DEC-191. Module B observes Module C dispatch events for SerializedBottle digital-state transitions (NFT burn cross-module chain per DEC-134 unchanged) but does not own them. The two-system split preserves DEC-140's clarity on the fulfillment side while restoring Module B's inventory-state authority. PRD detail at Module C PRD v0.2 §4.2 + Module B PRD v0.2 §15.

Domain events: `BottleSerialized`, `NFCTagApplied`, `NFTMinted`.

### §6.4 Blockchain Choice — Avalanche

NewCo uses **Avalanche** as the blockchain layer for NFT minting and lifecycle events (DEC-014, F3, J9). Avalanche is a deliberate choice for NewCo and **differs from Crurated's chain** (Crurated runs on a different chain, see Appendix B).

Reasons informing the Avalanche choice (commercial substance, not technical):

- Compatibility with NewCo's mint cadence and gas-cost target at expected volume.
- EVM-compatible smart-contract substrate (broad tooling).
- Acceptable finality guarantees and uptime track record.

The smart-contract architecture is technical scope (PRD Module B); the BMD records the platform choice and the principle that **all on-chain records are non-PII** (DEC-029): NFT IDs, hashes, provenance fingerprints — never names, emails, addresses. PII stays off-chain in EU-region storage.

### §6.5 NFT Mint Timing

NFTs are minted **at the moment the NFC tag is applied** (F4). This is earlier than at-bottling and earlier than at-customer-purchase, both of which are alternative timings rejected for NewCo:

- *At bottling* would require producer-side minting infrastructure and would tie the NFT mint to the producer rather than to a NewCo-controlled custody event — incompatible with the "tags applied by Vinlock" rule (F2).
- *At customer purchase* would mean stock is unserialized in custody, which removes the auditability of in-custody operations.

Mint-at-NFC-application is the cleanest tie of "physical custody under serialization" to "on-chain record".

The NFT metadata at mint is **expected to include** non-PII catalog identity sufficient to verify the bottle's provenance and link the on-chain record to the physical NFC tag — illustratively: Bottle Reference, producer, allocation reference, vintage / SKU details, and the NFC tag's unique identifier. The **literal on-chain payload composition is Module B's call** (DEC-054 / DEC-073) and may differ (e.g., a content-addressed hash + URL pointing to off-chain metadata for gas-cost reasons); the BMD-level commitment is the *intent* (non-PII provenance + NFC-UID linkage), not a specific field list. **No customer identity at mint** (the bottle is not yet bound to a customer; late binding happens at shipment, §5.5).

**Operational details deferred to PRD Module B** (DEC-054): the BMD locks the *timing principle* (mint at NFC tag application). The PRD will specify whether mints are batched (e.g., per receipt batch) or per-bottle, the gas-cost target, the latency tolerance, and the retry / failover behavior on chain congestion. Not a BMD-level decision.

### §6.6 NFT Custody — NewCo Wallet

NFTs are held in a **NewCo-controlled wallet** while the bottle is in custody (F5).

- The Customer never holds the NFT directly during the NewCo-custody phase. The Customer's claim on the bottle is the **Voucher**, which is a NewCo-side record (off-chain, in the ERP), not the NFT itself.
- NewCo's wallet is operated under standard custody best practices (cold-storage of master keys, hot wallet for transactional minting / burning, multi-signature for sensitive operations) — operational details TBD with Module B engineering.
- This differs from "self-custody" or "customer-held NFT" models. NewCo's choice is operator-custody for the entire custody phase.

The NewCo wallet is the system of record for the NFT; the ERP is the system of record for the Voucher; the two are correlated by the Bottle Reference + serial.

### §6.7 NFT Burn at Shipment

When a bottle is dispatched (the customer requests shipment per §5.5), the NFT is **burned on Avalanche** as part of the shipment workflow (F6).

The burn transaction:

- Removes the NFT from circulation (it can no longer be transferred or reused).
- Records the shipment event on-chain (timestamp, anonymized reference to the customer-side transaction, no PII).
- Closes the on-chain provenance record. The bottle's record up to that point persists on-chain as immutable history (it is the *active token* that is burned, not the *transaction history*).

After burn, the customer holds the physical bottle with a NFC tag that is no longer associated with an active NFT. The Bottle Page becomes a "post-burn" view (§6.8) showing the historical record up to dispatch but no live ownership data.

Burn-at-shipment is the NewCo equivalent of Crurated v17's NFT burn pattern (see Appendix B). It is the cleanest fit for a custody-only-during-storage posture.

Domain events: `BottleNFTBurned`, `BottleDispatched`.

### §6.8 Bottle Page — Public, Anonymous

The **Bottle Page** is a public web page accessible by scanning the NFC tag (F7, F8, F9, DEC-024):

- **Public**: accessible without login; reachable via the URL embedded in the NFC tag.
- **Anonymous**: does **not** expose customer identity, even after the bottle has been shipped to a Customer. The Customer's claim of ownership is held off-chain (in the ERP); the bottle page reveals the bottle's *provenance*, not its *current owner* (DEC-024, F9).
- **Authored by NewCo** (F8) — content guidelines and editorial control sit with NewCo, with producer-supplied inputs (provenance narrative, tasting notes, photography).
- **Pre-burn content** (bottle still in NewCo custody): provenance from producer to warehouse; producer profile; bottle metadata; tasting notes; allocation context; on-chain link to the NFT.
- **Post-burn content** (bottle has been shipped): a **chronological provenance trail** of location waypoints with dates (e.g., "in producer cellar from 1996 → in NewCo warehouse from dd/mm/yyyy → delivered to private cellar on dd/mm/yyyy") — no personal information; data about the bottle is available, but the customer-as-actor framing is replaced by customer-as-anonymous-destination per DEC-128 (Paolo refinement of the prior "shipped to Customer in anonymized form" language). On-chain link still works (history persists). The data feed contains zero customer identifiers (no Customer.id, no Profile.id, no Voucher.id, no recipient-name, no shipment-address); the predecessor / successor chain (per DEC-120) records on-chain provenance only — NFT IDs, NFC UIDs, allocation references.
- **Locale handling** (DEC-031): EN + IT + FR + DE + JP + ZH at launch (§7.7). Auto-detect from browser `Accept-Language`; EN fallback; manual selector.
- **Cookie / consent banner** required (DEC-027).

The bottle page is **not** a customer-personalization surface (DEC-024). Even if a logged-in NewCo customer scans the tag in the same browser session, the page does not personalize. The Bottle Page exists for provenance proof, brand storytelling, and authentication — not for transactional engagement.

**Core content only at launch** (DEC-053): link to NFT, provenance chain, producer profile, tasting notes, allocation context. Richer media (video, immersive experiences, AR) is deferred to a post-launch Content / Brand iteration once the platform is stable; the Bottle Page is a brand-trust artifact and over-engineering at launch is unnecessary.

### §6.9 Bottle Page Locale (DEC-031)

Per DEC-031 — six launch locales: **EN + IT + FR + DE + JP + ZH** for Bottle Page. Auto-detect first; EN fallback; manual override. Data-model i18n-ready from day one (translatable bottle-narrative strings, locale-aware date / number / currency formatting, locale-aware sort).

The producer-supplied content elements (tasting notes, provenance narrative) need to be sourced or translated at launch into the six locales. Translation workflow / cost / cadence is an operational concern owned by NewCo content; the BMD records the language scope.

### §6.10 NFT Legal Status — Working Stance

**Confirmed stance** (DEC-050): the NFT is a **provenance and authentication artifact**, **not** a legal title to the wine. Ownership of the wine is governed by the off-chain transactional record under the Italian sale-of-goods regime (the customer paid NewCo, NewCo as Seller of Record sold the bottle, the customer holds title per the contract).

Implications:

- Burning the NFT at shipment does **not** transfer legal title (which transfers per the sale contract regardless of the NFT).
- A customer's loss of access to the NFT (in scenarios where they hold it) does not extinguish their ownership of the wine.
- The NFT serves as a marketing / provenance / brand device, not a legal instrument.
- This stance keeps NewCo on conservative legal ground at launch and avoids tying the platform to NFT-as-property regulation, which is in flux globally.

**Italian counsel review pre-launch** is a non-blocking validation (DEC-050). If a future strategic decision elevates NFT to legal title (e.g., to enable token-based transfer in some jurisdiction), the BMD will be revised.

### §6.11 Recovery Scenarios — Confirmed Defaults (DEC-022, DEC-055)

Per DEC-022 and DEC-055, this section captures the confirmed default policies for the four NFC/NFT recovery scenarios identified in F12. All four were proposed in v0.1 BMD and confirmed in DEC-055, with **one amendment** to §6.11.2: the post-shipment re-tagging exception path is **removed** per DEC-051 (case-by-case customer-service handling replaces it).

#### §6.11.1 Damaged tag in warehouse (pre-shipment)

**Scenario**: the bottle is in custody. The NFC tag is physically damaged (broken seal, snapped substrate, unreadable on multiple devices). The bottle itself is fine. The NFT exists.

**Proposed default**:

1. Vinlock flags the bottle for NFC re-tagging in Logilize.
2. NewCo authorizes re-tag.
3. A new NFC tag is applied to the bottle (replacing the damaged one).
4. A **new NFT** is minted on Avalanche, linked to the new tag's unique ID. The new NFT carries a `predecessor_nft` reference pointing to the damaged-tag NFT.
5. The old (damaged-tag) NFT is **burned with reason = `tag_damaged_in_custody`**. A small registry contract entry (or equivalent off-chain ledger) records the predecessor-successor relationship to preserve the chain of provenance for any future audit.
6. Bottle continues normal lifecycle.

**Cost absorption**: NewCo (custody event, in line with §4.11 / DEC-025).

**Customer-facing impact**: none (bottle still in custody; customer is unaware unless they had previously viewed the bottle page).

Domain events: `NFCTagDamagedInCustody`, `NFCTagReapplied`, `NFTReissued`, `NFTBurnedAsTagDamaged`.

#### §6.11.2 Damaged tag post-shipment (customer reports)

**Scenario**: the customer has received the bottle; the NFC tag is damaged or unreadable. The NFT was already burned at shipment (§6.7), so no live NFT exists.

**Confirmed default** (DEC-051, amends v0.1's default — physical re-tagging exception path is **removed**):

1. NewCo offers the Customer a **provenance certificate** — a non-NFT, signed digital and/or physical document that attests:
   - The bottle was originally serialized under NFT [hash] (now burned).
   - The original purchase order, shipment date, and shipment destination.
   - The pre-burn provenance chain is preserved on-chain and accessible by the original NFT history reference.
2. NewCo does **not** re-mint a new NFT post-burn. (A re-mint would create a "ghost token" floating outside the lifecycle and conflicts with the burn-at-shipment finality posture.)
3. NewCo does **not** offer physical re-tagging via bottle return (DEC-051 removes the v0.1 exception path). The §5.10 no-returns-post-shipment rule remains in force; tag damage post-shipment does not cause an exception inflow.
4. **Customer service handles each case individually** (DEC-051): bottle replacement (where producer / inventory permits), goodwill coupon, store credit (default 105% of relevant face value per DEC-044), or other case-appropriate intervention. The provenance certificate (step 1) remains available alongside any of these remedies.

**Customer-facing impact**: provenance certificate on demand; case-by-case customer-service intervention as the primary remediation path.

**Cost absorption**: certificate — NewCo (operational); case-by-case remedy — NewCo absorbs operationally as a customer-experience cost; not allocated to producer.

Domain events: `BottlePostShipmentTagIssueReported`, `ProvenanceCertificateIssued`, `CustomerServiceRemedyApplied`.

#### §6.11.3 NFT lost in NewCo wallet

**Scenario**: the bottle is in custody and the NFC tag is fine. The NFT, however, is unrecoverable from NewCo's wallet — wallet key loss, smart-contract incident, on-chain anomaly, etc.

**Proposed default**:

1. NewCo confirms the NFT is unrecoverable (i.e., not just transient unavailability).
2. NewCo mints a **new NFT** linked to the **same NFC tag** (no re-tagging needed — the tag itself is fine), with `predecessor_nft = [lost NFT ID]` and `predecessor_status = lost_in_wallet`.
3. A registry-contract entry (or equivalent off-chain attestation) records the lost-and-replaced status.
4. The lost NFT is *not* burned (since NewCo cannot reach it to burn it) — it remains as a dangling token, but its `lost` status in the registry warns any future scanner that it is stale.
5. Bottle continues normal lifecycle under the new NFT.

**Customer-facing impact**: minimal — the NFC tag still resolves, now to the new NFT. Public bottle-page handles the predecessor-successor link gracefully.

**Cost absorption**: NewCo (operational).

**Risk note**: scenario 3 is a low-frequency but non-zero risk; the wallet operations posture (multi-signature, cold storage, regular audit) should drive the actual frequency to near-zero.

Domain events: `NFTLossInWalletDetected`, `NFTReissuedDueToWalletLoss`.

#### §6.11.4 Bottle destroyed pre-shipment, NFT alive

**Scenario**: a bottle in custody is physically destroyed (drop, leak, contamination, theft). The NFT is alive in NewCo's wallet.

**Proposed default**:

1. The bottle is recorded as written off in Logilize and the ERP per §5.9.
2. The NFT is **burned immediately**, with `reason = bottle_destroyed_in_custody` and a cross-reference to the write-off record.
3. Allocation pool is debited by 1 unit. If a Voucher had **already been bound** to this specific physical bottle for a Customer order (extreme edge case, since binding happens at shipment-pick, §5.5), an alternative unit of the same Bottle Reference is selected from available stock. If no alternative is available, refund per §4.11 (NewCo absorbs custody breakage; carrier insurance N/A since pre-shipment).
4. The producer is notified and the destroyed unit is reflected in the next settlement statement (§3.10) — typically as a NewCo-absorbed loss, not a producer clawback (since the destruction happened in NewCo / Vinlock custody).

**Customer-facing impact**: under late binding (§5.5), almost always none — the customer's Voucher is fungible to other units of the same Bottle Reference. Only if NewCo cannot fulfill is there customer impact, in which case refund or substitution applies.

**Cost absorption**: NewCo (custody breakage), per DEC-025.

Domain events: `BottleDestroyedInCustody`, `BottleNFTBurnedAsDestroyed`, `AllocationPoolDebitedDueToLoss`, `VoucherSubstitutionExecuted` (if needed).

---

These four defaults are confirmed (DEC-055), with §6.11.2 amended to remove the post-shipment re-tagging exception path (DEC-051). Future amendments will be appended as new DEC supersessions in the Decision Register.

---

## §7 Front-End Surfaces (Business Responsibility, not UX)

> The four surfaces (Admin, Consumer, Producer, Bottle), their primary users and business actions, the cross-surface identity model (no SSO, profile switcher inside Consumer Portal), the launch localization scope per surface, and the mobile-web-only posture.

### §7.1 Four Surfaces — Overview

NewCo operates **four front-end surfaces** at launch. This BMD captures the **business responsibilities** of each surface, not the UX. UX drafting is a separate track and out of scope here.

| Surface | Primary user | Authentication | Mobile? | Localization at launch |
|---------|--------------|----------------|---------|------------------------|
| **Admin Panel** | NewCo operations | Authenticated, role-based | Desktop-first; mobile-web sufficient | EN + IT |
| **Consumer Portal** | Customers (Member, Waiting-list, Legacy) | Authenticated; one login per Customer | Web + mobile-web; no native at launch | EN + IT + FR + DE + JP + ZH |
| **Producer Portal** | Producer staff | Authenticated, role-based | Web + mobile-web | EN + IT (+ producer-local on opt-in) |
| **Bottle Page** | Public (anyone scanning the NFC tag) | None — public, anonymous | Web (mobile-first, since NFC scans are typically on phone) | EN + IT + FR + DE + JP + ZH |

There is **no SSO across surfaces** (DEC-024, G10). Each surface has its own login or its own anonymous-public model. Inside the Consumer Portal, a single Customer login surfaces all the Customer's profiles via a profile switcher (§7.6).

### §7.2 Admin Panel — NewCo Operations

**Primary user**: NewCo operations and support staff (G1). Admin Panel is **NewCo-only at launch**; it is not delegated to support partners, accounting firms, or producers.

**Role and permission segmentation** (G2): the platform must support role definition and per-role access control from day one. At launch the role inventory will be lean (e.g., `super_admin`, `ops`, `support`, `finance_read_only`); the framework supports adding roles without re-architecture.

**Business actions in scope at launch**:

- **Customer management** — view Customer record, profile state, segment, KYC/sanctions status, suspension actions, soft-delete, GDPR request handling.
- **Producer management** — onboard / offboard producer, configure agreement terms, set settlement cadence, view producer-side reporting.
- **Allocation management** — view all allocations across producers, audit visibility flags, intervene where needed (rare).
- **Discovery curation** — review producer-submitted Discovery offers, accept / decline / request changes; curate the Discovery Tab editorially.
- **Order management** — view, search, intervene on orders (cancellation, refund, manual adjustment).
- **Voucher management** — search by Customer / Bottle Reference / state; intervene on stuck vouchers; substitute units in operational exceptions (§6.11.4).
- **Inventory dashboard** — surface from Logilize integration; not authoring.
- **Refund and dispute management** — execute refunds per the matrix (DEC-025), open / close chargeback disputes.
- **Storage-fee management** — visibility on accruals and charges; manual adjustment for exceptional cases.
- **Financial close support** — generate settlement statements, sync with Xero, produce reporting.
- **Sanctions screening dashboard** — flagged customers, re-screening cadence.
- **Compliance / audit logs** — read-only view of system events.

**Localization**: EN + IT at launch (DEC-031). Internal team is principally Italian and EN-fluent; expansion to other locales follows team composition, not external need.

### §7.3 Consumer Portal — Customers

**Primary users**: Customers in all three segments (Member, Waiting-list, Legacy) (G3, G4).

**Format**: Web + mobile-web (responsive). **No native iOS / Android apps at launch** (DEC-018, G3). Native apps may be added post-launch if customer demand justifies the investment.

**Business actions in scope at launch**:

- **Authentication and account** — sign up, log in, password reset, account settings, language preference, marketing-consent management.
- **Application and waiting-list** — apply to a club; view application state; receive approval / rejection / waiting-list notification; pay the Hero Package on approval.
- **Profile switcher** — at the top of the portal, switch between profiles (one per club membership) (DEC-024, see §7.6).
- **Producer club page** — for clubs the Customer is a Member of: see allocations on offer, allocation details, member-only pricing, club-level credit balance, recent purchases on this club, club-specific content (producer profile, news from producer, upcoming events).
- **Discovery Tab** — global cross-producer marketplace (DEC-008). Filter, search, sort. See offers on currently-active Discovery allocations; add to cart.
- **Cart and checkout** — combined cart spanning club + Discovery items; cart hold timeout (§4.6); checkout with card / bank transfer (§4.7); apply promo codes / store credit / club credit; multi-currency pricing.
- **Cellar** — view all bottles / cases / packages held; per-bottle metadata; storage fee accruals; aging info; provenance link to bottle page (§6.8).
- **Shipment requests** — request shipment of a subset of cellar items; address selection; shipment quote; confirmation; tracking once dispatched.
- **Voucher management** — gift a voucher to another customer (§4.13); see voucher state; resolve voucher questions.
- **Order history and invoices** — list past orders; download invoices; refund status visibility.
- **Membership management** — see all memberships and statuses; toggle auto-renewal; view renewal price and date; cancel membership; see Originating Club (read-only, locked at first approval).
- **Storage-fee dashboard** — accruals, last charge, next charge, payment-method.
- **Customer support entry** — contact-us form / help center entry (G5 community features deferred per Q-OQ-6).
- **GDPR self-service** — submit data subject rights request (access, rectification, erasure, portability).

**Localization**: EN + IT + FR + DE + JP + ZH at launch (DEC-031, §7.7). Auto-detect first visit; persist preference in profile thereafter.

### §7.4 Producer Portal — Producer Staff

**Primary users**: producer-side users — typically a small team per producer, with role segregation (G6).

**Roles configurable from day one** (G6) — at minimum: `producer_admin`, `producer_operator`, `producer_view_only`. The platform supports adding roles per producer where needed.

**Business actions in scope at launch** (G7):

- **Application review** — see pending applications and waiting list; approve, reject, move to waiting list; set Hero Package terms for new approvals.
- **Membership management** — see active members; promote waiting-listers; suspend / kick out members; view member detail (per the buyer-level visibility on club, §3.11).
- **Allocation management** — create allocations (Hero Package + subsequent releases); set price; set quantity; set visibility flag (`CLUB_ONLY / DISCOVERY_ONLY` — 2-value enum at launch per DEC-076; `BOTH` is dropped, with split commitments materialising as sibling Allocation rows per visibility); set per-offer purchase limits.
- **Hero Package design** — compose the year's Hero Package (constituent Bottle References + quantities + price); save / publish.
- **Sales reporting** — buyer-level view of all sales on the producer's club; aggregate view of sales of producer's wines on Discovery; 5%-share earned on Discovery (origin-club credits); period-by-period summaries.
- **Settlement reporting** — view current-period accumulation, last quarter's settlement statement, payment status.
- **Producer-page content** — manage producer-branded club page content (logo, photography, copy, news posts); subject to NewCo content guidelines.
- **Communication** (G7 "communicate maybe later") — defer at launch (Q-OQ-7); minimal status notifications only.

**Localization**: EN + IT at launch (DEC-031). Per-producer locale on opt-in (e.g., FR for Burgundy/Bordeaux houses); not all six consumer locales at launch — producer staff are typically multilingual professionals and the user base is small.

### §7.5 Bottle Page — Public, Anonymous

Detailed content in §6.8 / §6.9. Recap from front-end perspective:

- **Public** — accessible by anyone scanning the NFC tag. No authentication required. URL is embedded in the NFC tag.
- **Anonymous** (DEC-024, F9) — does not expose customer identity; shows provenance, producer profile, bottle metadata, link to on-chain NFT record.
- **Authored by NewCo** (F8); curated centrally; producer-supplied content elements (provenance narrative, tasting notes) editorially incorporated.
- **Mobile-first** — NFC scans are typically performed on smartphones; the page is optimized for that.
- **Localization**: EN + IT + FR + DE + JP + ZH; auto-detect from `Accept-Language`; manual selector.
- **Cookie / consent banner** required (DEC-027).

The Bottle Page **does not personalize even for a logged-in NewCo Customer** in the same browser (DEC-024). The page is a brand and authentication artifact, not a customer-engagement surface.

### §7.6 Identity and Profile Switching

Per DEC-024:

- **No SSO across surfaces.** Admin, Consumer, Producer, and Bottle are independent surfaces. A Customer who is also a Producer's staff member would need separate logins (an unusual but possible case at launch — manageable manually).
- **Inside Consumer Portal**: one Customer = one login; the login surfaces a **profile switcher** at the top (Netflix-style B7). The Customer chooses which profile (= which club membership) is active. The active profile drives the producer-club content shown; switching is instant. The Cellar, Discovery, account settings, and Customer-level info are profile-independent and always visible.
- **Bottle Page**: anonymous public; no personalization based on a logged-in Consumer-Portal session in the same browser (DEC-024).

For an authenticated session in Consumer Portal, the relationship between Customer (login) and Profile (active context) is:

- The Customer authenticates once.
- The active Profile is selectable in the UI.
- All actions performed within a profile context (e.g., applying purchase limits, viewing club credit) bind to that Profile.
- Cross-cutting actions (e.g., requesting shipment, paying with store credit, gifting) operate at the Customer level.

### §7.7 Localization Per Surface (DEC-031)

| Surface | Launch locales | Approach |
|---------|----------------|----------|
| Bottle Page | EN + IT + FR + DE + JP + ZH | Auto-detect from `Accept-Language`; EN fallback; manual selector |
| Consumer Portal | EN + IT + FR + DE + JP + ZH | Auto-detect first visit; persist preference in customer profile |
| Producer Portal | EN + IT (plus producer-opt-in locales) | Per-producer locale set; defaults EN+IT |
| Admin Panel | EN + IT | Internal team locales |

**Data-model i18n-readiness from day one** (DEC-031): translatable strings are externalized; locale-aware date / number / currency formatting; locale-aware sort. Adding a new locale post-launch is a configuration + translation effort, never a migration.

**Translation responsibility**: NewCo Content (or a contracted partner) for the launch locales of Bottle Page and Consumer Portal; producers are expected to supply producer-specific content in EN + their preferred locale; NewCo translates as needed.

### §7.8 Mobile-Web vs Native (No Native at Launch)

**No native iOS / Android apps at launch** (DEC-018, G3).

Reasons for the launch posture:

- Mobile-web responsive UX covers the customer use cases at launch.
- App-store distribution adds release / approval friction unwarranted at the launch user base.
- The customer behavior pattern (occasional purchases, occasional shipment requests, longer engagement on producer-page reading) is not tightly mobile-app-shaped (it is more like a web experience).

Native apps may be added post-launch if customer telemetry indicates significant mobile engagement that mobile-web cannot serve well.

The Bottle Page is mobile-first (NFC scans are phone-driven), but it is mobile-web, not native.

---

## §8 Monetization and Financial Policy

> Revenue streams (margin on club sales, margin plus 5% Originating-Club share on Discovery, storage fees, shipping fees), no producer-side fee, MPV VAT regime, multi-currency, Airwallex payments, quarterly invoice-driven producer settlement, refund-cost allocation, and the Xero + SDI accounting stack.

### §8.1 Revenue Streams Overview

NewCo's revenue streams at launch (H1, H4):

| Stream | Source | Magnitude |
|--------|--------|-----------|
| **Margin on club sales** | 12.5% of every club-page sale | Primary revenue |
| **Margin on Discovery sales** | Spread `P_d − C` per unit (negotiated cost mechanic per DEC-032), net of 5% × `P_d` Originating-Club share when applicable | Secondary; scales with Discovery adoption; depends on negotiated `C` per allocation |
| **Storage fees** | €3 / bottle / year after 12 free months | Recurring; scales with cellar volume |
| **Shipping fees** | Customer-paid per quote (typically pass-through with no margin or thin margin) | Cost-recovery, not margin |
| **Services and experiences** | TBD (Q-OQ-8) — placeholder revenue line | Negligible at launch |

**No producer-side membership fee** (§8.3, H1, H2). **No customer-side platform fee** beyond the Hero Package (which is a wine purchase, not a fee). No listing, slotting, or subscription fee on either side.

The economics are purposefully simple: NewCo is monetized as a percentage of GMV plus a per-bottle storage cost. The 12.5% margin on GMV is the volume-scaling stream; storage fees are the long-term recurring stream that grows with the cellar.

### §8.2 Margin Capture (DEC-010, DEC-032)

Recap from §3.6 with a finance-side framing. The two surfaces capture margin under different mechanics:

- **Club sale**: customer pays `P` (producer-set). NewCo is Seller of Record on the transaction at `P` (event sequence per §8.7 / §8.13; downstream accounting determines treatment per DEC-072). Producer PO is `0.875 × P`. NewCo gross margin is `0.125 × P`. No 5% Originating-Club share applies on club sales.
- **Discovery sale** (per DEC-032 — negotiated allocation cost mechanic, **supersedes** the Discovery portion of v0.1's description):
  - For each Discovery allocation, NewCo and the producer of the bottle agree a per-unit allocation cost `C`. NewCo sets the customer-facing Discovery price `P_d` itself.
  - Customer pays `P_d`. NewCo is Seller of Record on the transaction at `P_d` (event sequence per §8.13).
  - Producer of the bottle is settled `C` per unit sold (through their standard quarterly settlement, §3.10).
  - NewCo's gross margin is `P_d − C`. The spread varies per allocation (it is not a fixed percentage).
  - **5% × `P_d` is owed to the buyer's Originating Club producer** (settled through that producer's normal settlement statement, see §8.14) when an Originating Club exists.
  - NewCo's net margin is `P_d − C − (5% × P_d)`. Where the buyer has no Originating Club (DEC-040), the full `P_d − C` accrues to NewCo with no 5% deduction.

**Worked examples** (echoing DEC-032's worked example):

| Scenario | Customer pays `P_d` | Cost `C` to producer of bottle | Originating-Club share (5% × `P_d`) | NewCo gross (`P_d − C`) | NewCo net (after OC share) |
|----------|---------------------|--------------------------------|-------------------------------------|--------------------------|----------------------------|
| Discovery: `P_d` €200, allocation cost `C` €100 with Producer X; buyer's OC = Producer B's club (different producer) | €200 | €100 | €10 (to Producer B) | €100 | €90 |
| Same parameters but buyer's OC = Producer X's club (same producer as bottle) | €200 | €100 | €10 (also to Producer X — additive on top of `C`) | €100 | €90 |
| Same parameters but buyer has no Originating Club | €200 | €100 | n/a | €100 | €100 |
| Club sale (for contrast): producer-set price €1,000 | €1,000 | n/a — Producer PO at €875 (87.5%) | n/a | €125 (12.5%) | €125 |

Worked through, the consolidated finance view:

- Customer flow into NewCo per club bottle of price €1,000: €1,000 in, €875 out to bottle's producer, €125 retained.
- Customer flow into NewCo per Discovery bottle of `P_d` €200 with `C` €100 to a customer with a different-producer Originating Club: €200 in, €100 out to bottle's producer, €10 out to Originating-Club producer, €90 retained.

These are gross-margin numbers. NewCo's operating expenses (Vinlock storage, payment processing fees, blockchain gas, support, technology, marketing, headcount) come out of this gross margin to yield operating profit. Discovery's spread mechanic gives NewCo direct control of customer-facing margin per allocation; club margin is fixed at 12.5% by the producer-set price model.

### §8.3 No Producer-Side Membership Fee or Platform Fee

NewCo charges producers **nothing** to participate (DEC-010, C6, H1, H2):

- No membership fee.
- No listing fee.
- No slotting allowance.
- No annual platform fee.
- No "premium positioning" fee.
- No revenue floor / guarantee from the producer.

NewCo's margin is the entire monetization on the producer side. This is a deliberate posture: it lowers the friction to producer recruitment and aligns NewCo's incentives with producer success (NewCo earns when sales happen, not when producers join).

### §8.4 Storage Fees (DEC-013, DEC-118, DEC-119)

Storage fees per DEC-013 + DEC-118 + DEC-119 (recap from §2.7 / §5.6):

- **First 12 months free per bottle** from INV1 issuance; charges begin month 13.
- **After 12 months: €3 / bottle / year = €0.25 / bottle / month**, accrued monthly. Partial months round up to a full month.
- **Storage-clock-start double-anchor** (DEC-119): accrual begins at the later of `INV1 + 12 months` and the bound Allocation's `InboundEventPhysicallyAccepted` date — i.e., bottle must be at Vinlock for any part of an accruing month. Collapses to `INV1 + 12 months` for V2 default; waits for physical arrival for V1 / Direct Purchase in-transit-at-INV1 cases (per DEC-081). No storage = no charge.
- **Cadence**: semi-annual issuance — **end of June** + **end of December** — covering the prior 6-month period.
- **All segments charged** (Member, Waiting-list, Legacy).
- Charged via the saved payment method (card preferred; bank-transfer fallback for customers without a stored card).

**INV3 — the third customer-facing invoice type at NewCo launch (DEC-118 + DEC-119)**. Beyond INV1 (commerce, at checkout) and INV2 (excise + destination-VAT + shipping, at redemption), NewCo introduces **INV3** as a recurring service invoice for storage. INV3 is **Module-S-issued** under DEC-119 (supersedes DEC-118's Module-E-ownership clause; mechanics preserved). Module S fires `InvoiceINV3Issued` on the semi-annual cadence above, aggregating the prior 6 months of `StorageFeeAccrued` events for each customer; Module E consumes the event, routes it to Xero per DEC-072, and executes the Airwallex charge against the saved payment method (DEC-047 chargeback / Hold mechanics apply on failure). INV3 is net-new at NewCo: while Crurated's current ERP charges storage on an INV-3-equivalent line, the formal three-invoice typology (INV1 / INV2 / INV3) is locked at NewCo launch under DEC-118; DEC-119 aligns ownership uniformly under Module S so that all three customer-facing invoices (INV1 / INV2 / INV3) are emitted by the same module.

**Mid-semester shipment carve-out** (DEC-118 mechanics; DEC-119 makes this Module-S-internal). If a bottle ships during a semester, the storage charge for the months stored in that semester does **not** appear on a separate INV3. Instead, Module S's INV2 issuance for that shipment includes any unbilled storage months on the shipped bottle as additional line items on the INV2 — Module-S-internal logic with zero cross-module query (Module S owns both the INV2 issuance and the storage clock per DEC-119; the bidirectional Module S ↔ Module E coordination contract that DEC-118's framing had introduced is eliminated). After the bottle ships, no further storage accrues against it.

Storage-fee event sequence:

- `StorageFeeAccrued` fires monthly per bottle (one event per bottle per month after both the 12-month-free window expires AND the bottle-at-warehouse condition is met per DEC-119) — emitted by Module S.
- **`InvoiceINV3Issued`** fires semi-annually (end of June + end of December) per customer, aggregating the prior 6 months of `StorageFeeAccrued` events that have not been rolled into an intervening INV2 — emitted by Module S per DEC-119; consumed by Module E for accounting routing + Airwallex charge execution.
- For mid-semester shipments, the bottle's prior-month storage accruals roll into the bottle's INV2 line items at `InvoiceINV2Issued` rather than into the next INV3 cycle — Module-S-internal logic per DEC-119 (cross-event coordination point documented at §8.13).
- Downstream accounting (Xero per DEC-028 / DEC-072) determines treatment from these events.
- Failed INV3 charges drive dunning + Suspension per §2.7.

Storage fees are a **recurring revenue line** that scales with cellar volume. As the customer base ages and cellar volumes grow, storage becomes a meaningful component of NewCo's revenue mix.

### §8.5 Shipping Fees

Shipping is **customer-paid**, computed per shipment (H5):

- Quote at checkout based on automated computation: carriers available for destination, bottle count and weight, distance / zone, excise/customs handling, insurance.
- Customer pays the quoted amount as a separate line item on the order.
- NewCo passes through the carrier cost; any positive margin on shipping is small and not a primary revenue stream.

**Shipping for Voucher redemption** (i.e., a customer requesting shipment of bottles from their cellar) follows the same model: quote at request time, customer pays.

**Storage-fee accrual stops at shipment** for the shipped bottles (no double-charging).

### §8.6 Excise Pass-Through (H7, DEC-045)

**Excise duty is passed through to the customer at fulfillment** (H7), via the **two-invoice mechanic** (DEC-045):

- The destination country's excise rate is computed at shipment time and applied to **INV2** (the shipment invoice). It is **never** added to INV1 (the checkout invoice for the bottle / Voucher only).
- NewCo's logistics manager owns the excise calculation framework; Vinlock executes the operational compliance.
- Because INV1 carries no excise estimate, there is no estimate-vs-actual reconciliation step on excise. The customer simply pays the destination-country excise on INV2 at shipment time, when destination is fixed.

NewCo does not absorb excise duty as a margin sink; the pass-through model keeps NewCo's economics decoupled from country-by-country excise variance. (Q-OQ-30 in v0.1 — RESOLVED via DEC-045.)

### §8.7 VAT Regime — MPV (Multi-Purpose Voucher) + Two-Invoice Mechanic

NewCo adopts the **MPV (Multi-Purpose Voucher)** VAT regime (H6), inheriting the same VAT mechanic that Crurated uses (see Crurated v17 §0.7 for the foundational pattern), operationalized via the **two-invoice mechanic** (DEC-045).

The MPV regime fits NewCo's Voucher model (§4.4) because:

- The Voucher is a *right to a future delivery*, not yet a fulfilled sale.
- The destination country's VAT may not be determinable at point-of-sale (since late binding can deliver to a different destination).
- MPV defers VAT recognition to the point of *redemption* (i.e., shipment / fulfillment), at which point the destination is fixed.

Operational mechanics under DEC-045's two-invoice mechanic:

- **At INV1 (checkout)**: customer pays the bottle / Voucher amount only. VAT is **not** recognized on INV1; no destination-country VAT, no Italian VAT presumption — the MPV regime defers VAT recognition entirely to redemption.
- **At INV2 (shipment / redemption)**: VAT is recognized at the destination country's rate, applied to the customer's total on INV2. Accounting entries flow into Xero / SDI per §8.12.
- For specific scenarios (e.g., physical pickup at warehouse, immediate-ship orders where INV1 and INV2 collapse into a single event) the VAT treatment is recognized at the moment of redemption; the PRD (Module E) will specify the matrix.

**Italy as country of incorporation** (DEC-015) means NewCo's VAT registration and reporting is anchored to Italian tax authorities, with **French VAT registration** added because the Vinlock warehouse is in France (storage / dispatch from France triggers French VAT obligations for some flows), plus **EU One-Stop-Shop (OSS) registration** at launch to cover most cross-border B2C sales within the EU under the distance-selling threshold framework (DEC-056). Non-EU destinations (UK, CH, JP, US, etc.) are handled via DDP / DAP shipping terms — VAT/duty paid by customer at delivery via carrier; NewCo not registered in destination. Re-evaluated quarterly post-launch as volume builds.

### §8.8 Multi-Currency

Multi-currency posture per §4.8 / DEC-031 (data model i18n-ready) / D13/D14:

- **Base ledger currency**: EUR.
- **Customer-facing currencies at launch** (DEC-037): **EUR + USD + GBP + CHF + JPY** — five currencies. Add others post-launch on demand.
- **Producer settlement**: defaults to EUR (§3.10, C11).
- **FX policy**: BMD records the principle (EUR base; daily-snapshot mid-rate-plus-buffer); operational mechanics (snapshot time, buffer percentage, refresh cadence) deferred to PRD Module E (DEC-038).
- **Multi-currency support in Airwallex and Xero**: required (DEC-014, DEC-028); both selected partly for this capability.

### §8.9 Payment Methods — Airwallex (DEC-014)

**Payment provider at launch: Airwallex** (DEC-014, H9, J4):

- **Card payments** — credit and debit cards across the launch markets. 3-D Secure / SCA in EEA per PSD2.
- **Bank transfer** — SEPA in EU; SWIFT / wire for non-EU customers.
- **No PayPal, no crypto** at launch (re-evaluated post-launch on demand).
- **Saved payment methods** — supported for cards, used for auto-renewal of memberships (§2.4) and storage-fee charges (§8.4).
- **Multi-currency capture** — Airwallex multi-currency capability is one of the key reasons for selection (DEC-014).
- **Dispute / chargeback management** — in Airwallex; NewCo follows up on disputes via Airwallex tooling.

Airwallex differs from Crurated's Stripe (see Appendix B) — a deliberate platform divergence for NewCo.

### §8.10 Producer Settlement — Invoice-Driven, Quarterly (Default)

Recap from §3.10:

- **Quarterly cadence by default** (C10, DEC-042), configurable per producer agreement.
- **Invoice-driven** (H10, C10): producer issues an invoice to NewCo for the quarter's settlement amount; NewCo pays on **net-30 payment terms** by default; agreements may negotiate shorter or longer.
- Settlement amount per producer reflects: club sell-throughs at 87.5% × `P` (DEC-010); Discovery sell-throughs at the negotiated `C` per unit (DEC-032); and any 5% × `P_d` Originating-Club shares the producer earned on Discovery transactions by their members.
- **Settlement statement** is generated by NewCo and sent to the producer in advance of the producer's invoice; the producer's invoice is expected to match the statement.
- **Refunds and clawbacks** netted within the period; cross-period clawbacks deducted from the next period.
- **Settlement currency**: EUR by default (§3.10).

The cadence is producer-friendly: it lets producers operate with predictable cash flow rather than a per-sale settlement that would create accounting noise.

### §8.11 Refund Cost Allocation (DEC-025)

Recap of the matrix in §4.11 / DEC-025:

| Reason | Cost absorbed by |
|--------|------------------|
| Producer fault | Producer (clawback against settlement) |
| In-custody breakage (NewCo / Vinlock) | NewCo (insurance recovery if applicable) |
| Transit damage | Carrier insurance + NewCo gap |
| Customer cancellation in legal window | NewCo |
| Customer dissatisfaction post-shipment | No refund |
| Customer fraud | NewCo absorbs immediately, recovers via dispute |

Producer-fault clawbacks reduce the producer's settlement statement at next settlement; NewCo-absorbed refunds reduce NewCo's net margin (downstream accounting determines period attribution per DEC-072).

### §8.12 Accounting — Xero + Italian SDI Connector (DEC-028)

**Accounting platform: Xero** with a **third-party Italian SDI (Sistema di Interscambio) connector** for e-invoicing compliance (DEC-028, H12).

- **Xero**: best-in-class API, multi-currency support, MPV-VAT-compatible, cost-efficient (~€30–50/month at launch scale).
- **SDI connector**: bridges Xero to the Italian e-invoicing system. Required for an Italian-incorporated entity (DEC-015). Several third-party connectors available; specific choice TBD by Finance.
- **Integration scope**:
  - Outbound invoices: customer invoices generated by NewCo flow to Xero, then to SDI for Italian compliance.
  - Inbound invoices: producer invoices flow into Xero for AP processing.
  - Bank reconciliation: Airwallex transactions sync to Xero.
  - VAT reporting: Xero supports the multi-jurisdictional VAT views needed for Italy + France (and additional countries as registered).
- **Differs from Crurated** (see Appendix B): NewCo's accounting choice is Xero where Crurated uses a different stack.

**Audit / 10-year retention** (DEC-027): Italian invoice retention requires 10-year archival of invoices and tax documents; Xero plus the SDI connector plus NewCo-side archival policy together meet the requirement.

### §8.13 INV1 / INV2 / INV3 Event Sequence (Two/Three-Invoice Mechanic)

NewCo's invoice typology at launch (DEC-045 + DEC-118 + DEC-119) is **three** customer-facing invoice types, not two. INV1 (commerce, at checkout) and INV2 (excise + destination-VAT + shipping, at redemption) inherit the two-invoice mechanic of DEC-045 and are order-event-driven; **INV3** (storage services, semi-annual recurring) is added at NewCo launch under DEC-118 and is calendar-cadence-driven. **All three customer-facing invoices are Module-S-emitted** under DEC-119 (which supersedes DEC-118's Module-E-ownership clause for INV3; mechanics preserved). Module E consumes the customer-facing invoice events, routes them to Xero per DEC-072, and executes the Airwallex charge per DEC-014. Each transaction emits a deterministic event sequence; **downstream accounting (Xero per DEC-028 / DEC-072) determines how those events become GL entries**. The BMD captures the events; the PRD (Module E) specifies the GL mapping.

**Event sequence per surface**:

- **Sale of bottle (Voucher-issuing)**: at checkout, `OrderPaymentCaptured` fires on INV1 (covering the bottle / Voucher amount only — no destination-VAT, no excise); `VoucherIssued` fires for each constituent unit. At redemption (shipment), `VoucherRedeemed` and `OrderShippedToFulfillment` fire on INV2; destination-country VAT and excise pass-through are computed and applied to INV2. Per DEC-107, the canonical Module S event names for the customer-facing invoices are `InvoiceINV1Issued` (at order confirmation, post-payment-cleared per DEC-112) and `InvoiceINV2Issued` (at fulfilment).
- **Hero Package sale**: same INV1 / INV2 sequence, with one INV1 covering the package and individual `VoucherIssued` events per constituent bottle. Each constituent bottle redeems independently (the Customer can request shipment of subsets at different times).
- **Discovery sale**: same INV1 / INV2 sequence at the customer-facing price `P_d`. On the sale, `DiscoveryRevenueShareAccrued` fires for the buyer's Originating Club at `5% × P_d` (where the buyer has an Originating Club per §8.14); per DEC-112 this fires at INV1 issuance (= post-payment-cleared, NOT at OrderPlaced). Producer of the bottle is settled `C` through the producer's quarterly settlement statement (§3.10), driven by `SellThroughRecorded` events for passive-consignment allocations; direct-purchase allocations skip sell-through settlement entirely (§3.7).
- **Storage fee — INV3 cycle (DEC-118 + DEC-119)**: `StorageFeeAccrued` fires monthly per bottle (after the 12-month-free window AND the bottle-at-warehouse condition per DEC-119) — emitted by Module S. Module S aggregates the prior 6 months at semester-end and emits **`InvoiceINV3Issued`** (end of June + end of December); Module E consumes for accounting routing + Airwallex charge execution. INV3 is calendar-cadence-driven, distinct from INV1 / INV2 which are order-event-driven.
- **Mid-semester shipment carve-out — INV2 roll-in (DEC-118 + DEC-119)**: under DEC-119 this is **Module-S-internal logic**, not a cross-module coordination point. When a bottle ships mid-semester, Module S includes any unbilled storage months on the shipped bottle as additional line items on `InvoiceINV2Issued` rather than carrying them forward into the next INV3 cycle. This means a bottle's storage costs always appear on exactly one customer-facing invoice — INV3 if the bottle stays in custody through semester-end, or INV2 if it ships during the semester. The bidirectional Module S ↔ Module E contract that DEC-118's framing had introduced is eliminated under DEC-119 (Module S has all storage state natively because it owns the Voucher and the storage clock).
- **Shipping fee**: `ShippingFeeQuoted` at checkout; `ShippingFeeCharged` on INV2 at shipment (§8.5).
- **Refund / clawback**: `RefundIssued` fires at refund time. Refund cost allocation per §4.11 / §8.11 / DEC-025 determines whether the producer absorbs (clawback netted against next settlement statement) or NewCo absorbs.
- **Excise pass-through**: computed and applied on INV2 at shipment per §8.6 / DEC-045 — never on INV1, never on INV3.
- **MPV regime**: the regime defers VAT events to redemption (INV2) under EU MPV rules (§8.7); INV1 carries no VAT line. INV3 covers a recurring service (storage) and is treated under the applicable service-VAT regime determined by Module E + Xero per DEC-072.

**Per DEC-072, the BMD does not prescribe how these events become GL entries.** Xero, configured per Italian / EU norms for the Italian-incorporated entity (H13, DEC-015), receives these events and determines accounting treatment per its own policy. The PRD Module E specifies the integration scope and event-to-account mapping; the BMD records the event sequence the integration consumes.

### §8.14 Originating Club and 5% Discovery Share

The **Originating Club** (DEC-008, DEC-010, DEC-032, DEC-066, §2.2):

- The Originating Club is a **`Club` entity** (not a Producer entity) — DEC-066. The link target is the specific Club that approved the Customer first; the Producer who operates that Club is found by dereferencing the Club's operating-Producer link (per §3.4's Producer-only-operates-Club constraint at launch). This distinction matters when a single Producer operates multiple Clubs: the Customer's Originating Club is the specific Club they joined first, not the Producer in general.
- Locked at the **first time a Customer is approved by any Club**, gated on the Originating Club link being currently unset (one-shot lock; the `OriginatingClubLocked` event fires once per Customer).
- Stored at the Customer level (not Profile level, since it persists across all the Customer's subsequent memberships).
- Does **not** change if the Customer subsequently joins more Clubs.
- Does **not** change if the Customer leaves the Originating Club (becomes Legacy w.r.t. that Club). The Originating Club identity persists indefinitely — the share continues to accrue even after the Customer's first-approved Profile lapses or cancels.
- Drives the **5% revenue share on Discovery purchases**, paid to the Producer who operates the Originating Club (via that Producer's settlement statement).
- **Narrative role** (Module K PRD §6): the Originating Club also surfaces in the Customer Portal as the "you joined NewCo via Club X" anchor — a customer-facing identity statement, distinct from the share mechanic.

**Formula** (DEC-032): the share is **5% of the customer-facing Discovery price `P_d`** — not 5% of the gross margin (`P_d − C`), and not 5% of the producer of the bottle's settled cost. The share is computed off the headline customer-facing number.

**Operational mechanics**:

- On every Discovery sale by Customer C of bottle B, **5% × `P_d`** accrues to the Producer who operates C's Originating Club.
- Accrual is recorded against that Producer's settlement statement, alongside their direct-club sales.
- At quarterly settlement, the Producer receives: 87.5% × `P` (their direct club sales) + the negotiated `C` per unit (their direct Discovery sales) + accumulated 5% × `P_d` (Discovery 5% shares earned by virtue of operating their Originating-Club members' first-approved Club).

**Edge cases**:

- **Customer with no Originating Club** (DEC-040): allowed; no 5% share is computed; full Discovery gross margin (`P_d − C`) accrues to NewCo. Past Discovery purchases do not retroactively gain an Originating Club when the customer is later approved by a Club.
- **Originating-Club Producer offboarded** (§3.13): the 5% share continues to accrue but is held in suspense until the offboarding is fully resolved; payment to the offboarded Producer is per offboarding terms, not future settlements.

Domain events: `OriginatingClubLocked` (one-shot per Customer; emitted by Module K), `DiscoveryRevenueShareAccrued`, `DiscoveryRevenueSharePaidThroughSettlement`.

---

## §9 Lifecycle and Canonical Flows

> End-to-end happy-path flows for member onboarding, browsing and purchase, Discovery, producer onboarding, producer day-to-day, allocation and voucher lifecycles, bottle lifecycle, renewal, suspension / cancellation, offboarding (producer and customer), and edge-case flows for refund, damage, dispute, and loss.

### §9.1 Member Onboarding Flow — Apply → Approve → Pay Hero Package

The canonical happy-path member onboarding flow (K1, §2.5, §2.3):

1. **Visit and discover.** A prospective customer lands on the NewCo Consumer Portal (or arrives via a producer's marketing). They can browse the **Discovery Tab** anonymously (read-only — actual purchase requires an account, see §9.3 prerequisite).
2. **Sign up.** Customer creates an account (email + password + age attestation + accept terms). Customer record created. Sanctions screening fires (DEC-030) — pass / fail.
3. **Apply to a club.** Customer browses available clubs, selects one (or several), submits an application. Application records the customer's interest. Producer is notified.
4. **Producer reviews.** Producer logs into Producer Portal, sees the application in their queue. Producer can:
   - **Approve** — application moves to Approved. Customer notified; payment workflow triggered (step 5).
   - **Reject** — application moves to Rejected. Customer notified.
   - **Move to Waiting List** — application held; customer notified; customer transitions to Waiting-list segment (§2.1).
5. **Pay Hero Package.** On approval, customer is prompted to pay the Hero Package fee = annual membership cost (§2.3). Customer pays via Airwallex (card or bank transfer, §8.9).
6. **Membership activates.** Payment captured → `HeroPackagePurchased` → Voucher(s) issued for Hero Package contents → `MembershipActivated`. Customer transitions from Waiting-list/None to Member segment.
7. **Originating Club locks** (if this is the customer's *first* approved membership across all clubs) — `OriginatingClubLocked`.
8. **Customer in cellar.** Hero Package contents appear as Vouchers in customer's cellar. Customer can browse the producer's club page, see members-only allocations, etc.

**Variants**:

- **Producer-initiated invitation** (B2): producer invites a specific person; the flow starts at step 5 (the customer's account creation may be combined with invitation acceptance).
- **Multi-club applications**: customer can apply to multiple clubs in parallel; each is reviewed independently by its producer; each approval triggers its own Hero Package payment.

Domain events: `CustomerSignedUp`, `CustomerOnboardingScreeningPassed/Failed`, `MembershipApplicationSubmitted`, `MembershipApprovedByProducer`, `HeroPackagePurchased`, `VoucherIssued`, `MembershipActivated`, `OriginatingClubLocked` (only on first-ever approval).

### §9.2 Member Browse → Cart → Checkout → Cellar → Ship Flow

The canonical member day-to-day flow (K1):

1. **Browse club page.** Customer (Member of the club) visits the producer's club page. Sees current allocations on offer. Each offer shows price, available quantity, purchase limits, member-only or also-on-Discovery indicator (visibility from §4.5).
2. **Browse Discovery.** Customer switches to Discovery Tab, sees global cross-producer catalog. Filters by producer, vintage, region, price.
3. **Add to cart.** Customer adds offers from both surfaces (club + Discovery) into a single combined cart. Each add triggers `CartHoldCreated` against the underlying allocation (§4.6); hold timer starts.
4. **Checkout.** Customer reviews cart, applies any promo / store credit / club credit, reviews multi-currency pricing (§4.8), enters shipping address (or selects "store" instead of immediate ship), confirms.
5. **Pay.** Customer pays via card or bank transfer. `OrderPlaced`. Payment captured → `OrderPaymentCaptured` (or `PaymentPending` for bank transfer).
6. **Vouchers issue.** For each line item, `VoucherIssued` against the customer. Cart hold(s) convert to Voucher(s); allocation pool debits accordingly.
7. **Cellar update.** Customer's cellar reflects the new Vouchers. Storage clock starts (§5.6 / §8.4) — first 12 months free per voucher.
8. **(Later) Request shipment.** When customer wants to receive bottles, they go to the cellar, select Vouchers to ship, choose address, get a shipping quote, pay shipping (and any post-12-month storage fees), confirm.
9. **Late binding.** At pick time, Vinlock selects specific physical bottles for the customer's order (§5.5). NFTs of selected bottles are bound to the shipment internally.
10. **Dispatch.** Vinlock dispatches → `ShipmentDispatched`, `BottleNFTBurned` (per bottle), `VoucherShipped`.
11. **Delivery.** Carrier delivers → `ShipmentDelivered` (best-effort tracking; not always confirmable).
12. **Cellar updated.** Shipped bottles removed from cellar; voucher state moves to CONSUMED.

**Notes**:

- A customer can request shipment of a *partial* cellar (some Vouchers ship, others stay).
- Storage-fee accrual continues for un-shipped Vouchers.
- Card-on-file is used for storage-fee charges (§8.4) and auto-renewal (§2.4) without re-prompting the customer.

### §9.3 Discovery Purchase Flow (Non-Member, Waiting-list, Legacy)

Discovery is open to all NewCo customers — Members, Waiting-list applicants, and Legacy ex-members (DEC-008, DEC-012). The flow for non-members is a subset of §9.2:

1. Customer signs up (if not already a customer) — same as §9.1 step 2.
2. Customer applies to one or more clubs (optional but recommended; without an approved membership, the customer is Waiting-list segment).
3. Customer browses Discovery — visible to all.
4. Customer adds Discovery offers to cart, checks out, pays.
5. Vouchers issue; cellar populates.
6. (Later) ship-out flow per §9.2 steps 8–12.

**Originating Club edge case** (DEC-040): if the customer has not yet been approved by any club at the time of their Discovery purchase, no Originating Club is locked (the lock fires only on first approval, §2.2). The 5% Discovery share has no recipient — the full Discovery gross margin (`P_d − C`) accrues to NewCo on that transaction. Discovery purchase by entirely-unapproved customers is **permitted** (DEC-040), since otherwise the Waiting-list segment would have no real Discovery access. Past Discovery purchases do not retroactively gain an Originating Club when the customer is later approved by a club.

### §9.4 Producer Onboarding Flow

(Per §3.2 / §3.3, producer-side):

1. **Outbound recruitment.** NewCo commercial team identifies and approaches producer.
2. **Negotiation.** Commercial terms agreed (margin baseline 12.5% per DEC-010, settlement cadence, minimum allocation commitment, Hero Package design intent for year 1, etc.).
3. **Agreement signed.** 24-month default term (C3, Q-OQ-9).
4. **Producer-portal access provisioned.** Roles created (admin / operator / view-only); user accounts created for the producer's team.
5. **Club page setup.** Producer-supplied logo, color palette, photography, copy uploaded. Page reviewed by NewCo content team for guideline conformance, published to Consumer Portal.
6. **Hero Package designed.** Producer composes the year-1 Hero Package in the Producer Portal — bottles, quantities, total committed price.
7. **First allocation seeded.** Hero Package and (optionally) initial subsequent allocations created with visibility flags.
8. **Producer goes live.** Customers can apply; producer reviews applications. First Hero Package payment triggers first inbound (V2 default).
9. **Inbound to warehouse.** Producer ships Hero Package quantities to Vinlock; Vinlock receives, NFC-tags, NFTs mint (§6.3 / §6.5). Stock available in salable state.
10. **Sell-through, settlement.** First quarter accumulates sales; first settlement statement and producer invoice flow.

Domain events: `ProducerOnboarded`, `ProducerAgreementExecuted`, `ProducerPortalProvisioned`, `ClubPagePublished`, `HeroPackageDesigned`, `AllocationCreated`.

### §9.5 Producer Daily Flow

Routine producer activity (K2):

- **Review pending applications**, approve / reject / waitlist.
- **Manage existing memberships** — promote waiting-listers to fill vacant slots, suspend or kick members where needed.
- **Create allocations** — new releases through the year. Set price, quantity, visibility flag, purchase limits, eligibility (club / Discovery / both).
- **Inbound additional stock** as allocations are created.
- **Monitor sales** — buyer-level on club page, aggregate on Discovery.
- **Monitor settlement accumulation** — see what is accruing for the next quarterly settlement.
- **Update club page content** — news posts, event announcements, producer profile updates.

### §9.6 Allocation Lifecycle

State machine of an Allocation:

```
DRAFT → PUBLISHED → ON_OFFER → SOLD_THROUGH (or PARTIALLY_SOLD)
              ↓
         WITHDRAWN (producer or NewCo retire)

ON_OFFER + visibility_change → re-evaluation (e.g., CLUB_ONLY repurposed to DISCOVERY_ONLY for unsold remainder; per DEC-076 the visibility flag is 2-value at launch and split commitments are sibling rows)
PARTIALLY_SOLD → RELISTED_ON_DISCOVERY (§3.9 unsold-stock path)
PARTIALLY_SOLD → RECALLED_TO_PRODUCER (§3.9)
```

- **DRAFT**: producer creates allocation in Producer Portal.
- **PUBLISHED**: allocation goes live to its visibility scope; offer(s) become buyable.
- **ON_OFFER**: actively buyable. Cart holds, sales, etc., happen here.
- **SOLD_THROUGH / PARTIALLY_SOLD**: at the end of the offer's window (or when stock depletes), state advances.
- **WITHDRAWN**: producer or NewCo retires the offer.
- **RELISTED_ON_DISCOVERY** / **RECALLED_TO_PRODUCER**: unsold-stock disposition per §3.9.

The visibility flag (`CLUB_ONLY / DISCOVERY_ONLY` — 2-value enum at launch per DEC-076; `BOTH` is dropped, with split commitments materialising as sibling Allocation rows per visibility) is a property of the allocation and can be modified during its lifecycle (with corresponding offer publication / retirement on each surface; visibility mutation is subject to the anti-orphan rule per DEC-076 — the unsold portion of an allocation may be repurposed to the other surface, but already-issued vouchers under the original visibility remain bound to the original commercial relationship).

Domain events: `AllocationCreated`, `AllocationPublished`, `AllocationVisibilityChanged`, `AllocationWithdrawn`, `OfferPublished`, `OfferRetired`, `AllocationFullySoldThrough`, `AllocationRelistedToDiscovery`, `AllocationRecalledByProducer`.

### §9.7 Voucher Lifecycle

State machine of a Voucher (8 states at NewCo launch per DEC-102):

```
PENDING_PAYMENT → ISSUED → REDEMPTION_REQUESTED → SHIPPED → CONSUMED
       ↓             ↓             ↓                  ↓
     VOIDED       VOIDED         VOIDED           (terminal)
  (7-day timeout) EXPIRED       (refund pre-ship)
                  GIFTED
```

- **PENDING_PAYMENT**: bank-transfer pre-state covering the 7-day credit-terms window (DEC-049 / DEC-101). Voucher transitions PENDING_PAYMENT → ISSUED on funds-cleared at Airwallex; INV1 fires at the same transition (DEC-107 / DEC-112). Failure to clear in 7 days auto-VOIDS without INV1 issuance; Allocation reservation releases; no financial event. Card payments do NOT pass through this pre-state (authorize-and-capture is one step).
- **ISSUED**: Voucher is held in the customer's cellar. Storage fee accrues per §8.4 (after the first 12 free months).
- **REDEMPTION_REQUESTED**: customer requests shipment of this Voucher. Allocation/inventory pool consumes the unit (late binding, §5.5). Storage accrual stops.
- **SHIPPED**: bottle is dispatched. NFT burned (§6.7). Cancellation right WAIVED from this point per DEC-108 (post-shipment issues handled via Module C returns + replacement, not cancellation; see §4.10 / §4.11).
- **CONSUMED**: terminal state; the Voucher's lifecycle closes.
- **VOIDED**: refund or substitute occurred; Voucher is invalidated. Sources include 14-day pre-shipment cancellation per DEC-108, refund per §4.11, substitution under offboarding per DEC-104, or PENDING_PAYMENT 7-day timeout per DEC-101.
- **EXPIRED**: voucher-expiry policy applies (DEC-103). Trigger = scheduled job firing on the bound `Allocation.expiry_date` for any Voucher not yet REDEMPTION_REQUESTED / SHIPPED / CONSUMED / VOIDED / GIFTED. `Allocation.expiry_date` is optional (default null = no expiry); when null, the Voucher persists indefinitely.
- **GIFTED**: Voucher transferred to another customer per DEC-116 (§4.13). 7-day accept window; voucher locked PENDING_TRANSFER during the window; OC reference preserved on the gifted voucher (giver's `originating_club_id` per BMD §4.13). Recipient gates: registered NewCo Customer + KYC `passed` + Offer-eligibility match. Terminal Vouchers (REDEMPTION_REQUESTED / SHIPPED / CONSUMED / VOIDED / EXPIRED) NOT transferable.

v17 ON_CRUTRADE state DROPPED at NewCo launch (no CruTrade marketplace per §4.4); v17 RESOLVED + BottlingResolution N:M reissuance machinery + BOUGHT_BACK state DEFERRED post-launch (no liquid voucher product per §13).

Domain events: `VoucherIssued`, `VoucherRedemptionRequested`, `VoucherShipped`, `VoucherConsumed`, `VoucherVoided`, `VoucherExpired`, `VoucherGifted`, `VoucherSubstitutionExecuted` (offboarding manual operator capability per DEC-104).

### §9.8 Bottle Lifecycle

State machine of a Bottle (physical unit):

```
EXPECTED → INBOUND_PHYSICAL_ACCEPTED → COST_FINALIZED → SERIALIZED → AVAILABLE
                                                                       ↓
                                                                     PICKED → DISPATCHED → DELIVERED
                                                                       ↓
                                                                  WRITTEN_OFF (in custody loss)
```

- **EXPECTED**: producer has notified NewCo of an inbound shipment; manifest registered.
- **INBOUND_PHYSICAL_ACCEPTED**: Vinlock confirms receipt and condition (§5.1 phase 1). Module D emits `InboundEventPhysicallyAccepted`; Module B consumes to **create the InboundBatch entity** (per DEC-195) — the Module-B-owned logical container for goods arriving from a single source (PO line or consignment receipt). Module B's downstream physical-match check (per DEC-194) compares physically-counted bottles against the qty declared in the `InboundEventPhysicallyAccepted` payload; on variance, Module B emits `InboundBatchDiscrepancy` back to Module D, which reopens the InboundEvent into DISCREPANCY state (§5.1 two-stage check).
- **COST_FINALIZED**: commercial reconciliation done within 5-working-day SLA (§5.1 phase 2). Module D emits `InboundEventCostFinalized`; Module B flips the InboundBatch's **cost-basis** from provisional (set at PHYSICALLY_ACCEPTED) to finalized — the v13 Stage 2.3 split-inbound lineage carried into NewCo per DEC-195. The InboundBatch carries: expected qty (from PO line), received qty (from Module B's physical-match check), serialization progress (`qty_planned_serialize` + `qty_actually_serialized` per DEC-186), ownership flag (per DEC-185 2-value enum at NewCo launch — `PRODUCER` for consigned stock with title held by the producer; `CRURATED` for direct-purchase stock and post-supplier-payment passive-consignment stock), cost basis (provisional → finalized), serialization-plan target. The cost-basis is referenced at dispatch-time per Module C late-binding chain (DEC-142) — the `inventory_cost_basis` payload on `BottlePicked` reads from the InboundBatch.
- **SERIALIZED**: NFC tag applied + NFT minted (§6.3 / §6.5). Module B records `NFCTagApplied` + `NFTMinted`; the SerializedBottle entity references the InboundBatch from which it derived. **Non-serialized inventory** stays at the InboundBatch level with the v17 §B.5a counter set per DEC-186 (supersedes DEC-133): `qty_planned_serialize` + `qty_actually_serialized` + `qty_non_serialized_committed` + `qty_non_serialized_reserved`; NS-pool ATP = `received_quantity − qty_planned_serialize − qty_non_serialized_committed − qty_non_serialized_reserved`. NS stock has full inventory-ledger discipline (counters, ATP, audit, adjustments); DEC-133's five no-op clauses are re-scoped to **digital-provenance only** — no SerializedBottle, NFC, NFT, Bottle Page, or recovery chain on NS stock; full inventory-ledger surface present at the InboundBatch level.
- **AVAILABLE**: salable; in stock pool for the producer / Bottle Reference. Module B's StockPosition aggregated view (per DEC-196) reports the bottle at the 5-dimension intersection: `(bottle_reference, warehouse, case_config, allocation, ownership)` with `total_quantity`, `committed_quantity`, `available_quantity` (= `total − committed − reserved − quarantined − under_adjustment`). The available count feeds Module A's ATP cache via Module B → Module A push (per DEC-187) and Module S's storefront read path under the lesser-of-(allocation-pool ATP, physical-inventory ATP) contract.
- **PICKED**: bound to a customer's shipment under late binding (§5.5). The bottle's source InboundBatch + cost basis are recorded on the `BottlePicked` payload per Module C late-binding chain.
- **DISPATCHED**: NFT burned (§6.7); shipment dispatched. **Bottle ownership flag** flips to `CRURATED` at this point if not already (Module B v0.2 §2.2 ownership-transition trigger; Module E records the financial-event consequence).
- **DELIVERED**: best-effort tracking confirms delivery.
- **WRITTEN_OFF**: in-custody loss (§5.9, §6.11.4). Module B emits `InventoryAdjusted` with `adjustment_type = damage / loss / write-off` per DEC-190; Module E consumes for financial-event recording per DEC-072.

For non-serialized stock, the SERIALIZED state is skipped; the bottle moves AVAILABLE → PICKED → DISPATCHED with batch-quantity binding rather than per-bottle late binding (no SerializedBottle row; the InboundBatch counter set tracks state).

**Quarantine carve-out (Stage 8 per DEC-191)**. Bottle-equivalents reported by Logilize that Module B did not anticipate (serial mismatches, batch confusion) land in QuarantineRecord pending manual supervisor investigation — they do **not** enter the AVAILABLE pool. Resolution paths: associate with existing InboundBatch / create new inventory record / reject as invalid / escalate; resolved QuarantineRecords are immutable post-resolution. Resolution-driven inventory state changes emit `InventoryAdjusted` per DEC-190 if needed.

Domain events: `InboundShipmentExpected`, `InboundPhysicallyAccepted`, `InboundCostFinalized`, `InboundBatchDiscrepancy` (Module-B-emitted per DEC-194), `BottleSerialized` (skipped for non-serialized), `BatchSerializationDiscrepancy` (Module-B-emitted per DEC-186 if NS plan-vs-actual diverges), `BottleQuarantined` / `BottleQuarantineResolved` (per DEC-191), `StocktakeReconciled` (per DEC-189), `InventoryAdjusted` / `InventoryShortfallDetected` (per DEC-190), `OwnershipTransitioned` (per DEC-185 + Module B v0.2 §2.2), `BottlePicked`, `ShipmentDispatched`, `BottleNFTBurned` (skipped for non-serialized), `BottleDelivered`, `BottleWriteOff`.

### §9.9 Membership Renewal Flow

(Per §2.4):

1. **30 days before period end** (DEC-033): customer notified of upcoming renewal and price; cadence is a customizable platform setting.
2. **Producer can opt out of renewing** the customer (mirror of approval discretion). If producer declines, renewal does not fire; membership lapses at end of period.
3. **Customer can cancel auto-renewal** any time before period end. Membership remains Active until end of paid period, then Lapsed.
4. **Period-end day**: if neither party opted out, renewal fires automatically. Hero Package payment charged to saved payment method.
5. **Payment captured** → `MembershipRenewed` → new Voucher(s) issued for the new year's Hero Package → year resets.
6. **Payment failure** → dunning sequence → if unrecovered, `MembershipRenewalFailed` → membership Lapsed → cancelled per §2.6.

### §9.10 Suspension / Cancellation / Recovery Flow

(Per §2.6):

- **NewCo-initiated suspension**: bad behavior, fraud, KYC/sanctions failure, missed payment. Membership state goes Suspended; customer notified; clear cause and remediation path provided.
- **Producer-initiated suspension/kick**: producer's discretion (§3.12). Effective immediately; customer notified.
- **Re-instatement**: requires whoever initiated the suspension to lift it.
- **Lapsed → Active** within grace window: customer pays the renewal cost and membership re-activates.
- **Cancelled (terminal)**: customer transitions to Legacy if they hold residual cellar items.

### §9.11 Producer Offboarding Flow

(Per §3.13):

1. **Trigger**: end-of-term, breach, mutual agreement, business closure.
2. **Stop new allocations**: producer cannot create new allocations after offboarding starts.
3. **Existing on-offer allocations**: continue selling through unless paused; producer-side reporting continues.
4. **Pending Vouchers**: customer-facing obligation stays with NewCo. Honored by drawing on residual stock, sourcing comparable wine (with customer consent), or refunding (per DEC-025 / §3.13).
5. **Stored bottles** held by customers: stay in cellar; ship-out continues normally.
6. **Pending settlements** paid out through offboarding date; any post-offboarding accruals (e.g., the 5% share earned on Discovery purchases by their members) handled per offboarding terms.
7. **Club Credit balances** of members: converted to Discovery store credit at face value, valid for 12 months from conversion (DEC-043).
8. **Producer-portal access revoked**.

### §9.12 Customer Offboarding Flow

(Per K4, §2.6):

1. **Customer requests cancellation** (via Consumer Portal account close).
2. **All memberships move to Cancelled**.
3. **Stored cellar items**: customer remains as Legacy until cellar is empty (K4). Customer can ship items out, gift items, request refund per applicable rules (§4.11), or just hold.
4. **Storage fees continue** for as long as items remain in custody.
5. **GDPR-driven erasure request**: per DEC-027, soft-delete with anonymization; cannot fully erase while customer holds unredeemed Vouchers / Bottles. Customer's transactional records retained 10 years per Italian law.

### §9.13 Edge-Case Flows

**Refund flow** (per §4.11):

1. Cause identified (producer fault / custody breakage / transit damage / cancellation in legal window / fraud).
2. Refund issued per refund-cost matrix (DEC-025).
3. Voucher voided (if pre-shipment) or refund posted (if post-shipment, where allowed).
4. If producer fault: clawback recorded against next settlement.
5. Customer notified; refund landed on original payment method.

**Damage / breakage flow** (per §5.9, §6.11.4):

- In-custody breakage: bottle written off, NFT burned with reason, allocation pool debited, customer's voucher substitutes against same Bottle Reference (or refund if no substitute available).
- Transit damage: customer reports; NewCo opens carrier insurance claim; refund or replacement per case.

**Chargeback / dispute flow** (DEC-047):

1. Customer disputes a charge with their card issuer (often via Airwallex).
2. NewCo is notified; gathers evidence (order record, Voucher state, communication trail, shipment record where applicable) from the Admin Panel.
3. NewCo Operations responds via the **Airwallex standard dispute interface** within **7 business days** of notification.
4. Outcome: customer wins (refund stands, NewCo absorbs loss; loss tracked in Finance; customer flagged for fraud-pattern review) or NewCo wins (refund reversed). Either way, internal records are updated.
5. If customer is judged to be acting fraudulently, NewCo can suspend / kick (§2.6).

**Launch KPI**: chargeback rate **under 2%** (DEC-047). Escalation triggers if exceeded.

**Loss / theft flow**: handled per §5.9 (in-custody), §6.11.4 (in-custody with NFT).

**Death / inheritance / corporate dissolution flow** (K8): **deferred at launch** (Q-OQ-11 — DECISION DEFERRED). Operationally handled case-by-case until a policy is set.

---

## §10 Geography, Jurisdiction, Compliance

> Italy as likely country of incorporation, French VAT registration tied to the Vinlock warehouse, ~25 destination countries with case-by-case alcohol-restriction handling, EU data residency, light EU + Italian UIF sanctions screening, GDPR posture under DEC-027, and Italian-consumer-law cancellation default.

### §10.1 Country of Incorporation — Italy (Likely)

**Italy is the likely country of incorporation** (DEC-015, I1). Final legal-entity confirmation pending. Italian incorporation drives several downstream consequences captured in this BMD:

- **Italian e-invoicing** via SDI (DEC-028, §8.12).
- **Italian invoice retention** — 10-year archival (DEC-027, §2.9).
- **Italian Garante** as the GDPR supervisory authority (§10.8).
- **Italian consumer law** for cancellation / withdrawal rights (§10.9).
- **Italian UIF** as the financial-intelligence unit for sanctions screening (DEC-030, §10.7).

If the legal-entity decision changes (e.g., incorporation moves to a different EU jurisdiction), several of these threads will need re-derivation.

### §10.2 VAT Registrations at Launch (DEC-056)

Per DEC-056, NewCo holds **three VAT registrations at launch**:

- **Italian VAT** (default — country of incorporation). NewCo's primary VAT identity.
- **French VAT** (DEC-015, I2) — required because the Vinlock warehouse is in France. Stock held in France and dispatched from France triggers French VAT obligations for some flows (e.g., domestic French sales, intra-EU dispatches from a French stock holding). The exact French VAT scope is operational detail with Finance and Italian/French tax counsel.
- **EU One-Stop-Shop (OSS)** registration — covers most cross-border B2C sales within the EU under the distance-selling threshold framework with a single VAT registration.

**Non-EU destinations** (UK, CH, JP, US, etc.) are handled via DDP / DAP shipping terms — VAT/duty is paid by the customer at delivery via the carrier; NewCo is not registered in destination. Re-evaluated quarterly post-launch as volume builds (e.g., if specific country thresholds force a local VAT registration).

### §10.3 Customer Markets — Global, ~25 Countries (DEC-041)

Customer markets at launch are **global, with case-by-case alcohol-restriction handling** (I3, I4).

- **Reference baseline** (DEC-041): ~25 destination countries mirroring Crurated's current shipping universe, **including the US**. Final NewCo launch list confirmed by Logistics + Legal pre-launch.
- **Restrictions are country-specific** — alcohol importation rules, license requirements, age verification at delivery, dry-state restrictions (US specifically — handled per state), import quantity caps, documentation requirements.
- **Excluded destinations** are explicitly listed in customer terms; visible at signup.
- **No outright market exclusions at launch** beyond regulatory necessity (I4) — NewCo aims for the broadest sensible reach.

Customer can sign up from any country; whether they can ship to that country is a separate eligibility check at shipment time.

### §10.4 Producer Geography

Producers are sourced multi-country at launch (I5). Producer geography is unrestricted:

- EU and non-EU producers welcomed.
- Cross-border inbound flows (e.g., a US distillery shipping to French warehouse) handled through the standard inbound process plus customs / excise compliance.
- Producer's home jurisdiction does not affect their commercial relationship with NewCo (NewCo's terms are uniform; producer's local VAT / commercial regime is producer-side).

### §10.5 Alcohol Distribution License

**NewCo holds the alcohol distribution license** (I6) for the territories from which it sells.

- **France**: license required for the French warehouse operations (typically a *récépissé* and warehouse-keeper status); coordinated with Vinlock's local licensing.
- **Italy**: license required for Italian-incorporated entity selling alcohol cross-border.
- **Other destinations**: per-country requirements; some countries require a registered importer or local representative; arrangements TBD per the launch destination list.

The alcohol-license framework is operationally complex; detailed mapping is owned by Logistics + Legal and out of scope for the BMD.

### §10.6 Data Residency — EU

Per DEC-029:

- **Primary databases, backups, customer-data warehouses**: EU regions (Italian / French regions preferred).
- **Third-party SaaS**: configured for EU residency where the provider supports it (HubSpot, Airwallex, Xero, Logilize, Vinlock-side systems). DPAs signed with each provider.
- **Avalanche on-chain**: global by nature. Mitigated by **non-PII-only on-chain** (DEC-029) — NFT IDs, hashes, anonymized references; no names, emails, addresses on-chain.
- **Off-chain personal data**: stays in EU regions.

This posture aligns with GDPR baseline + Italian DPO / Garante expectations and ensures cross-border data transfers to non-EU SaaS providers are governed by appropriate legal mechanisms (Standard Contractual Clauses, adequacy decisions, etc.).

### §10.7 Sanctions / UIF / OFAC Screening

Per DEC-030 (amended by DEC-041) and §2.8:

- **At onboarding**: lightweight screening against EU consolidated sanctions list + Italian UIF list + **OFAC list** (US is in the launch destination set per DEC-041). Cost ~€0.30–€1 per check via providers such as ComplyAdvantage or Onfido.
- **Re-screen every 12 months and on flagged events** (large transactions, country change, suspicious activity).
- **Failed screening** → application rejected at onboarding; existing customer flagged → membership(s) suspended pending review.
- **Provider choice** (ComplyAdvantage, Onfido, or similar) — TBD by Compliance.

### §10.8 GDPR Posture

Per DEC-027 (recap of §2.9 from a compliance angle):

- **Lawful bases**: contract (account / purchase / fulfillment), consent (marketing), legitimate interest (security / fraud / anti-abuse / re-screening).
- **Data subject rights**: access, rectification, erasure, portability, objection, restriction. Self-service request page on Consumer Portal.
- **Erasure constraint**: cannot fully erase customer holding unredeemed Vouchers or stored Bottles. Path: soft-delete + anonymize + retain transactional records for 10-year Italian invoice retention.
- **Internal DPO** (no external mandate at NewCo's launch scale).
- **72-hour breach notification** to Italian Garante per Article 33 GDPR.
- **Cookie / consent banner**: required on Consumer Portal and Bottle Page; Producer Portal exempt as authenticated B2B-style tool.
- **Consent capture**: double opt-in for marketing (DEC-026); single opt-in for transactional.
- **Right to object to automated decision-making**: NewCo's automated decisions at launch (e.g., sanctions screening result, fraud detection) are configurable; the customer's right to a human review is preserved per Article 22 GDPR.

### §10.9 Cancellation per Italian Consumer Law (DEC-057)

Per §4.10 / D10 / DEC-057:

- **Italian distance-selling consumer law** applies (NewCo is Italian-incorporated, sells to consumers).
- **14-day withdrawal right** is the working baseline — customer can withdraw from the contract within 14 days without giving any reason.
- **No reliance on carve-outs** at launch (DEC-057). The standard 14-day withdrawal-right carve-outs (perishable goods, customized items) may apply to fine wine sold under the Voucher model in custody, but the BMD's working stance is to assume the **full 14-day right applies** to all NewCo customer purchases. Italian counsel review pre-launch is a non-blocking validation.
- **Practical implementation**:
  - Pre-shipment cancellation in legal window: refund per §4.11 (NewCo absorbs). The Voucher model + custody makes 14-day cancellation operationally clean (Voucher voids cleanly pre-shipment).
  - Post-shipment cancellation in legal window: technically allowed if goods are intact and unopened; operationally handled as exception (since §5.10 no-routine-returns rule applies); refund per §4.11.
  - Membership cancellation in legal window of Hero Package payment: voids the Hero Package purchase and the membership.

### §10.10 KYC / AML Posture

Per B10 / §2.8:

- **Consumer business** — KYC/AML rigor calibrated to consumer fine-wine commerce, not investment-grade collectibles.
- **Age verification at registration** — mandatory; self-attest plus payment-method-bound minimum-age check.
- **Sanctions screening at onboarding** — EU + Italian UIF + OFAC per DEC-030 / DEC-041 (§10.7).
- **Default light KYC** — no document-based identity proof, no source-of-funds verification, no biometric verification at launch for the standard customer population.
- **AML transaction monitoring** — at the level of payment-provider Airwallex's standard fraud and AML controls; NewCo augments with internal anomaly detection on customer behavior (post-launch enhancement scope; minimal at launch).
- **Enhanced KYC threshold** (DEC-035): triggered at **€10,000 single transaction** OR **€50,000 cumulative annual purchases per customer**. Above either threshold: ops review + (where appropriate) document-based identity verification. The thresholds are anchored on Italian AML simplification thresholds for consumer commerce.

---

## §11 Operating Model and External Systems

> In-house vs outsourced split, logistics with Vinlock, WMS with Logilize, payments with Airwallex, CRM and email with HubSpot, accounting with Xero + Italian SDI connector, customer support deferred at launch, blockchain on Avalanche, and AI / Operator Copilot deferred at launch.

### §11.1 In-House vs Outsourced Functions

Function ownership at launch (J1, J2):

| Function | In-house | Outsourced |
|----------|---------|-----------|
| Commercial / producer recruitment | ✓ | |
| Product / engineering | ✓ | |
| Operations (Logistics manager) | ✓ | (Vinlock executes operations) |
| Finance / accounting | ✓ (with Xero + bookkeeping support) | |
| Marketing / brand | ✓ | (potentially partner agencies) |
| Customer support | ✓ (operations team at launch) | (no dedicated tooling, see §11.7) |
| Content (translation, editorial) | ✓ + partner translators | |
| Legal | (advisor relationship) | ✓ (external counsel for compliance, agreements) |
| Logistics execution | | ✓ (Vinlock) |
| Tech infra (cloud, CDN, observability) | ✓ (configured) | (cloud providers as vendors) |
| Blockchain operations | ✓ (NewCo wallet) | (Avalanche network) |

**Headcount and exact ownership scaling depends on funding** (J1) and is not fixed in this BMD.

### §11.2 Logistics Partner — Vinlock (DEC-014)

**Vinlock** is the warehouse operator (DEC-014, J2):

- Operates the single French warehouse at launch (E2, §5.2).
- Same physical site as Crurated, **separate contracts and separate operational fences**.
- Receives, stores, picks, packs, dispatches.
- Applies NFC tags under NewCo direction (§6.3).
- Coordinates excise / customs documentation under NewCo's logistics manager (§5.4).

NewCo retains a logistics manager in-house who interfaces with Vinlock daily.

### §11.3 WMS — Logilize (DEC-014)

**Logilize** is the WMS (DEC-014, J3):

- Same provider as Crurated, **separate Logilize integration** for NewCo (independent tenant, independent credentials).
- **System of record for physical-execution state on the workflow axis** — in-warehouse / in-transit / delivered / damaged / lost-in-custody location and movement at sub-warehouse granularity (row / rack / cellar zone — Logilize-internal); pick-pack-dispatch execution. This is the role v0.8 framed as "physical state".
- **NewCo's ERP — specifically Module B — is system of record for the inventory ledger on the ERP-side** (per DEC-185 Stage 8 restoration): InboundBatch + StockPosition + Case + QuarantineRecord + Stocktake entity ownership; ATP source per allocation; receiving physical-match authority; stocktake variance computation; inventory-adjustment proposal-and-confirmation; provenance immutability; no-overselling at the physical-inventory layer; committed-inventory protection.
- **Module S = system of record for commercial state** (allocations, vouchers, orders); **Module E = system of record for financial state** (payments, settlements, multi-currency dual recording, Xero routing). The four authorities reconcile through explicit cross-module event flows (§6 four-way reconciliation discipline).
- **Stream split (Stage 8 per DEC-188; supersedes DEC-140 on the inventory-state axis)**: Module C owns 4 fulfillment streams with Logilize (outbound pick instruction; pick confirmation; dispatch confirmation; delivery confirmation); Module B owns 5 inventory-state streams (storage-location tracking — Stream 5 migrated from Module C; receiving + physical-match per DEC-194; stocktake instruction + variance per DEC-189; inventory-adjustment proposal-and-confirmation per DEC-190; QuarantineRecord resolution flow per DEC-191). The PRD-level integration detail lives in Module C PRD v0.2 §4 + Module B PRD v0.2 §15.
- Reconciliation discipline (DEC-141) preserved: source-of-truth split between physical-execution (Logilize) and ERP-side (Module B / Module S / Module E) is maintained via real-time event-driven cadence; conflicts surface in NewCo Admin Panel discrepancy queue. Module B's quarantine-before-trust principle (DEC-191) ensures unverified Logilize-emitted entities never silently enter Module B's ledger.

### §11.4 Payments — Airwallex (DEC-014)

**Airwallex** is the payment provider (DEC-014, J4, H9):

- Card payments (credit, debit) across launch markets.
- Bank transfer (SEPA, SWIFT/wire).
- Multi-currency capture (a key reason for selection).
- 3-D Secure / SCA in EEA per PSD2.
- Saved cards for auto-renewal, storage-fee charges, and INV2-at-shipment collection (§4.7, §8.13).
- **Dispute / chargeback handling via Airwallex's standard dispute interface** (DEC-047). NewCo Operations submits the evidence package within 7 business days of notification; launch KPI is chargeback rate under 2%.
- Differs from Crurated's Stripe (see Appendix B).

### §11.5 CRM and Marketing Automation — HubSpot

**HubSpot** for CRM and marketing automation (DEC-014, J6, J7):

- Customer relationship records synced from the ERP.
- Marketing automation: email campaigns, drip flows, segmentation.
- Producer relationship records (CRM-side; producer business operations are in the Producer Portal).
- Marketing-consent state (DEC-026) honored — marketing emails only to double-opt-in confirmed customers.
- Transactional emails may be routed through HubSpot or via a dedicated transactional provider (TBD operational choice).

### §11.6 Accounting — Xero + Italian SDI Connector (DEC-028)

**Xero** as the accounting platform with a third-party **Italian SDI connector** (DEC-028, §8.12):

- Outbound customer invoices flow Xero → SDI for Italian e-invoicing compliance.
- Inbound producer invoices land in Xero for AP processing.
- Bank reconciliation: Airwallex → Xero.
- Multi-currency, multi-jurisdiction VAT views (Italy + France + as registered).
- 10-year invoice retention (DEC-027).
- Differs from Crurated's accounting stack (see Appendix B).

### §11.7 Customer Support — Deferred at Launch (Q-OQ-5)

No dedicated customer-support tooling at launch (J8, Q-OQ-5).

- At launch, customer-support workload is handled by the NewCo operations team using the Admin Panel, the Consumer Portal's contact-us form, and standard email.
- Support tooling (Zendesk, Intercom, custom) deferred to a post-launch decision, scoped to actual support volume after a few months of operation.

### §11.8 Blockchain — Avalanche (DEC-014)

**Avalanche** is the blockchain layer (DEC-014, J9, F3):

- NFT minting, burning, lifecycle events on Avalanche.
- NewCo wallet operates under standard custody best practices (cold-storage masters, hot-wallet for operations, multi-signature for sensitive operations).
- Non-PII only on-chain (DEC-029).
- Differs from Crurated's chain (see Appendix B).

The smart-contract architecture is Module B / PRD scope; the BMD records the platform choice and the on-chain-PII restriction.

### §11.9 Operator Copilot / AI — Deferred at Launch (DEC-021)

AI / Operator Copilot is **DEFERRED at launch** (DEC-021, supersedes DEC-016, §13.7).

- Initial intent (DEC-016) was to keep AI in scope at launch; reversed in DEC-021 after Q-CL-2 resolution.
- AI scope (operator copilot, customer-facing assistant, anomaly / fraud detection, content recommendation, etc.) is **all deferred** at launch.
- Re-evaluation post-launch, analogous to Crurated v17's deferred-AI posture.
- The platform should not assume any AI capability; modules are designed to be useful without AI augmentation.

This deferral is consequential for several elicitation answers (e.g., K6 "automated fraud detection would be nice"): at launch, automated fraud detection is at the level of standard Airwallex fraud controls, not custom AI models.

---

## §12 Decision Log and Open Questions

> The 196 locked decisions (DEC-001..DEC-196) summarized inline with pointers to the Decision Register, plus the 8 still-deferred open questions, plus the resolution of all six v0.1 drafting decisions.

### §12.1 Locked Decisions (DEC-001..DEC-196)

The Decision Register at [`../04-decisions/decisions.md`](../04-decisions/decisions.md) is the source of truth. The 196 currently locked decisions are summarized below in compact form. Each is referenced in the relevant section of the BMD. DEC-032..DEC-058 were appended on 2026-05-02 from Paolo's review of v0.1 via [`qa.bm.md`](../03-qa/qa.bm.md); DEC-059..DEC-062 on 2026-05-02 from BMD v0.2 review and Wave 1 kickoff; DEC-063 on 2026-05-02 from Wave 1 Module 0 elicitation Q-CL-2 follow-up; DEC-064 + DEC-065 on 2026-05-03 from Wave 1 Module 0 elicitation; DEC-066..DEC-071 on 2026-05-03 from Wave 1 Module K elicitation; **DEC-072** on 2026-05-03 from BMD v0.4 review (methodology — BMD / PRD do not take accounting policy positions; accounting integration decides GL treatment); **DEC-073 + DEC-074** on 2026-05-03 (PRD-drafting methodology — PRD scope = product-spec layer; PRDs are self-contained delivery documents; refine DEC-060); **DEC-075..094** on 2026-05-04 from Wave 2 Module A + Module D elicitations; **DEC-095..118** on 2026-05-04 from Wave 3 Module S elicitation (32-atom resolution; 24 DECs warranted); **DEC-119** on 2026-05-06 during Wave 3 Module S PRD v0.1 review (storage-fee ownership flip Module E → Module S; supersedes DEC-118 ownership clause; mechanics preserved); **DEC-120..155** on 2026-05-08 from Wave 4 Module B + Module C elicitation 49-atom resolution (17 Module B DECs + 18 Module C DECs + 1 Q-OQ batch DEC); **DEC-156..184** on 2026-05-08 from Wave 5 Module E elicitation 21-atom resolution (Module-E payment / settlement / Xero / multi-currency / chargeback / refund / non-revenue cost catalogue + DEC-180..184 cross-cutting closure DECs); **DEC-185..DEC-196** on 2026-05-09 from Stage 8 Module B inventory-authority restoration elicitation (Phase A `qa.modB.s8.md`). DEC-073 / DEC-074 carry no BMD body revision (PRD methodology only); among Wave 2 DECs, DEC-080 and DEC-092 drove BMD body revisions in v0.6; among Wave 3 DECs, DEC-108 / DEC-117 / DEC-118 drove substantive BMD body revisions in v0.7. The substantive v0.8 BMD revisions were **DEC-119 (REQ-1) and DEC-147 (REQ-2)**; DEC-124 / DEC-128 / DEC-137 / DEC-145 / DEC-148 drove optional clarifications applied at v0.8. **The substantive v0.9 BMD revisions are DEC-185 (REQ-A §5.3 reframing + REQ-D §6 four-way reconciliation paragraph), DEC-188 (REQ-C §6.3 + §11.3 Logilize-stream split), DEC-194 (REQ-E §5.1 receiving-discrepancy split), DEC-195 (REQ-B §9.8 bottle-lifecycle restatement)**; DEC-186 / DEC-187 / DEC-189 / DEC-190 / DEC-191 / DEC-192 / DEC-193 / DEC-196 are Module B PRD v0.2 scope (no BMD body revision) but compose into the §6 four-way reconciliation framing and the App A Glossary additions. DEC-133 (DEC-186 supersession marker) + DEC-140 (DEC-188 supersession marker) recorded.

| DEC | Title | Date | Section reference |
|-----|-------|------|-------------------|
| DEC-001 | NewCo greenfield ERP track opened (intragroup OUT) | 2026-05-01 | §1.1, §13.11 |
| DEC-002 | BMD outline approved | 2026-05-01 | (this document's outline) |
| DEC-003 | Five-wave module sequence | 2026-05-01 | (PRD scope, not BMD) |
| DEC-004 | Seven-stage QA cadence | 2026-05-01 | (process, see README) |
| DEC-005 | Folder structure approved | 2026-05-01 | (process) |
| DEC-006 | Hard gate on BMD sign-off | 2026-05-01 | (process) |
| DEC-007 | Hero Package = membership fee | 2026-05-01 | §2.3 |
| DEC-008 | Discovery Tab as core feature | 2026-05-01 | §1.1, §4.5, §8.14 |
| DEC-009 | Crurated as Discovery supplier (provisional) | 2026-05-01 | superseded by DEC-020 |
| DEC-010 | 12.5% margin on club, 5% revenue share on Discovery | 2026-05-01 | §3.6, §8.2, §8.14 — **amended by DEC-032 for Discovery side** |
| DEC-011 | Both V1 and V2 passive consignment in scope; no active consignment | 2026-05-01 | §3.7, §5.1, §13.1 |
| DEC-012 | Three customer segments (Member / Waiting-list / Legacy) | 2026-05-01 | §2.1 |
| DEC-013 | Storage fee: 12 months free + €3/bottle/year | 2026-05-01 | §2.7, §5.6, §8.4 |
| DEC-014 | External system stack (Vinlock, Logilize, Avalanche, Airwallex, HubSpot, Xero+SDI) | 2026-05-01 | §11 |
| DEC-015 | Italy incorporation likely; French VAT registration likely | 2026-05-01 | §10.1, §10.2 |
| DEC-016 | AI/Copilot kept at launch | 2026-05-01 | superseded by DEC-021 |
| DEC-017 | No B2B; consumer-only NewCo | 2026-05-01 | §13.2 |
| DEC-018 | Mobile-web only at launch | 2026-05-01 | §7.8, §13.6 |
| DEC-019 | Mixed-case packages always single-producer | 2026-05-01 | §4.3 — **amended by DEC-061: club-only constraint; Discovery inherits v17 multi-producer composite** |
| DEC-020 | Crurated as Discovery supplier — commercial only | 2026-05-01 | §3.1, §13.11 |
| DEC-021 | AI/Copilot DEFERRED at launch | 2026-05-01 | §11.9, §13.7 |
| DEC-022 | NFC/NFT recovery policies — Claude proposes defaults | 2026-05-01 | §6.11 |
| DEC-023 | Allocation visibility model — club + Discovery + dual-listing | 2026-05-01 | §4.5 |
| DEC-024 | No SSO across surfaces; profile switcher inside Consumer Portal | 2026-05-01 | §7.6, §6.8 |
| DEC-025 | Refund-cost matrix | 2026-05-01 | §4.11, §8.11 |
| DEC-026 | Marketing consent — double opt-in marketing, single opt-in transactional | 2026-05-01 | §2.9 |
| DEC-027 | GDPR posture — standard Italian EU compliance | 2026-05-01 | §2.9, §10.8 |
| DEC-028 | Accounting platform — Xero + Italian SDI connector | 2026-05-01 | §8.12, §11.6 |
| DEC-029 | Data residency — EU (Italian/French preferred) | 2026-05-01 | §10.6 |
| DEC-030 | Sanctions screening — lightweight EU + Italian UIF at onboarding | 2026-05-01 | §2.8, §10.7 — **amended by DEC-041 (OFAC required)** |
| DEC-031 | Localization scope across four surfaces | 2026-05-01 | §7.7 |
| DEC-032 | Discovery margin model: negotiated allocation cost `C`, NewCo-set `P_d`, 5% × `P_d` Originating-Club share — **amends DEC-010 for Discovery** | 2026-05-02 | §0.5, §1.3, §3.1, §3.5, §3.6, §3.7, §3.10, §8.2, §8.14, App A, App B |
| DEC-033 | Renewal notice 30 days, customizable; Hero Package price stable in principle | 2026-05-02 | §2.4 |
| DEC-034 | Lapsed grace window 30 days post-period | 2026-05-02 | §2.6 |
| DEC-035 | KYC threshold: €10k single / €50k cumulative annual | 2026-05-02 | §2.8, §10.10 |
| DEC-036 | Cross-border gifting tax: customer responsibility | 2026-05-02 | §4.13 |
| DEC-037 | Customer-facing currency launch set: EUR + USD + GBP + CHF + JPY | 2026-05-02 | §4.8, §8.8 |
| DEC-038 | FX policy mechanics deferred to PRD | 2026-05-02 | §4.8, §8.8 |
| DEC-039 | Platform promotions: never silent on producer prices; NewCo-funded only on Discovery | 2026-05-02 | §3.5, §4.12 |
| DEC-040 | Discovery purchase without Originating Club: allowed; no 5% recipient | 2026-05-02 | §3.6, §8.14, §9.3 |
| DEC-041 | Destination country list at launch: mirror Crurated; **US INCLUDED**; **OFAC required** — **amends DEC-030** | 2026-05-02 | §0.11, §2.8, §5.8, §10.3, §10.7 |
| DEC-042 | Producer settlement: quarterly default, agreement-configurable | 2026-05-02 | §3.10, §8.10 |
| DEC-043 | Club Credit on producer offboarding: convert to Discovery store credit at face, 12-month validity | 2026-05-02 | §3.13, §4.12 |
| DEC-044 | Store-credit goodwill premium admin-configurable (default 105%) | 2026-05-02 | §4.11, §4.12 |
| DEC-045 | Two-invoice model — INV1 bottle/voucher only at checkout; INV2 at shipment with excise + destination-VAT | 2026-05-02 | §4.7, §5.4, §8.6, §8.7, §8.13 |
| DEC-046 | Storage-fee refund on partial cellar refund: pro-rata where cause warrants | 2026-05-02 | §4.11 |
| DEC-047 | Chargeback process via Airwallex; ops responds within 7 business days; <2% chargeback KPI | 2026-05-02 | §9.13, §11.4 |
| DEC-048 | Insurance basis deferred to Vinlock/Crurated conditions — **reverses v0.1 working baseline** | 2026-05-02 | §2.7, §5.2 |
| DEC-049 | Bank-transfer cart-hold extension: 7 calendar days | 2026-05-02 | §4.7 |
| DEC-050 | NFT legal status: provenance/authentication artifact, not legal title | 2026-05-02 | §6.10 |
| DEC-051 | Post-shipment NFC tag damage: no re-tagging; customer service case-by-case — **amends §6.11.2 v0.1 default** | 2026-05-02 | §6.11.2 |
| DEC-052 | Discovery listing of non-serialized stock allowed with badge — **broadened by DEC-080 to all allocations (any sourcing × visibility); per-allocation opt-out, not visibility-gated** | 2026-05-02 | §6.1 |
| DEC-053 | Bottle Page core content only at launch | 2026-05-02 | §6.8 |
| DEC-054 | NFT mint timing operational details deferred to PRD Module B | 2026-05-02 | §6.5 |
| DEC-055 | NFC/NFT recovery defaults confirmed (with §6.11.2 amendment per DEC-051) | 2026-05-02 | §6.11 |
| DEC-056 | VAT registrations at launch: Italy + France + EU OSS | 2026-05-02 | §8.7, §10.2 |
| DEC-057 | Italian/EU 14-day withdrawal right: full right working baseline; counsel review non-blocking | 2026-05-02 | §4.10, §10.9 |
| DEC-058 | Confirmed-deferred items batch (Q-OQ-2 / 5 / 6 / 7 / 8 / 9 / 10 / 11) | 2026-05-02 | §12.2, App C |
| DEC-059 | Producer PO model: club vs Discovery commercially distinct (DEC-010 vs DEC-032); data-model representation (one entity with purpose flag vs two) deferred to Module A / S PRD | 2026-05-02 | §3.6, §3.10, §8.10, App A (Producer PO) |
| DEC-060 | PRD inherits Crurated v17 as default substrate; BMD Appendix B is the divergence map | 2026-05-02 | (methodology — not folded into BMD body; lives in Decision Register and Wave-prompt files) |
| DEC-061 | Multi-producer composite SKU: club mixed-cases stay single-producer; Discovery inherits v17 multi-producer composite — **amends DEC-019** | 2026-05-02 | §0.2, §4.3, App B.1, App B.3 |
| DEC-062 | Multi-tier membership: data model inherited from v17; launch single-tier; multi-tier is a post-launch configuration, not a migration | 2026-05-02 | §3.4, App B.1, App B.2 |
| DEC-063 | Direct purchase admitted as exceptional sourcing model alongside passive consignment V1+V2 — **amends DEC-011** | 2026-05-02 | §0.5, §0.7, §3.7, §5.1, App A (Direct Purchase) |
| DEC-064 | Translatable strings: i18n-keyed JSON per attribute on existing PIM entities (no separate translation registry at launch) — **refines DEC-031 / DEC-053** | 2026-05-03 | (Module 0 PRD scope; no BMD body revision) |
| DEC-065 | Module 0 PIM scope at launch: bottled wine only (LiquidProduct stripped; no Service / Experience SKU subtype) | 2026-05-03 | (Module 0 PRD scope; operationalises BMD §13.4 + Appendix B.3 + DEC-058 within Module 0) |
| DEC-066 | Originating Club FK target = Club entity; locked one-shot via `OriginatingClubLocked` event — **refines DEC-008 / DEC-040** | 2026-05-03 | (Module K PRD scope; no BMD body revision) |
| DEC-067 | Producer ≠ Supplier separation carries forward verbatim from v17; Supplier-operated Clubs flagged as future flexibility | 2026-05-03 | §3.1 (Glossary entries Producer / Supplier added in v0.4); operationalises App B.1 |
| DEC-068 | B2B segment dropped at Customer level; company-billing details preserved at Address / Payment-method level for individual collectors — **refines DEC-017** | 2026-05-03 | (Module K + Module E PRD scope; no BMD body revision; §13.2 stance unchanged) |
| DEC-069 | Hero Package Allocation `qty` is mutable mid-year; producers can scale capacity, waitlist applicants gain eligibility on increase — **refines DEC-007** | 2026-05-03 | §2.3 |
| DEC-070 | Producer agreement = separate `ProducerAgreement` entity in Module K (Path Y) | 2026-05-03 | (Module K PRD scope; operationalises BMD §3.3 at data-model level) |
| DEC-071 | Sanctions screening on Customer: separate `sanctions_status` enum + nullable fields; screening mandatory by application logic, not schema constraint — **implements DEC-030 + DEC-041** | 2026-05-03 | (Module K PRD scope; no BMD body revision) |
| DEC-072 | BMD and PRD scope: event-determining, not accounting-policy-determining; accounting integration (Xero) decides GL / balance-sheet / P&L treatment per its own policy | 2026-05-03 | §2.3, §3.6, §3.7, §3.10, §4.7, §4.8, §5.1, §8.2, §8.4, §8.11, §8.13, §8.14, App B (folded throughout v0.5 accounting-policy scrub; reinforces and generalises Module E boundary; applies to all future BMD / PRD revisions) |
| DEC-073 | PRD scope = product-spec layer translating BMD into module behaviour (concepts, state machines, business event names, ownership / observation contracts); JSON shapes, schema field tables, FKs, nullability are tech-implementation and lifted out of PRDs | 2026-05-03 | (methodology — no BMD body revision; PRD-drafting discipline only) |
| DEC-074 | NewCo PRDs are self-contained delivery documents; v17 inheritance preserved as audit-trail trace appendix, not load-bearing prose; refines DEC-060 (drafting-stage discipline) into delivery-stage standard | 2026-05-03 | (methodology — no BMD body revision; PRD-drafting discipline only) |
| DEC-075 | Allocation entity unification: single Allocation entity at launch for all sourcing-model × visibility combinations (no subtypes, no split tables); composes with DEC-076 (split realisation), DEC-077 (lifecycle orthogonality), DEC-079 (mutability), DEC-092 (commercial-terms shape) | 2026-05-04 | (Module A PRD scope; no BMD body revision) |
| DEC-076 | Split-allocation realisation: visibility-split producer commitments materialise as separate Allocation rows per visibility (sibling rows from one producer commitment); visibility enum **reduced to 2 values at launch** (`CLUB_ONLY / DISCOVERY_ONLY`); `BOTH` dropped at launch | 2026-05-04 | §7 + §9.6 + Glossary "Allocation Visibility" (2-value refresh; OPT-4 v0.6); Module A PRD owns row-multiplication mechanics |
| DEC-077 | Allocation lifecycle orthogonal to ProducerAgreement state transitions (no auto-cascade from `superseded` / `terminated` agreement onto child allocations; per-allocation actions handled case-by-case at agreement state change) | 2026-05-04 | (Module A PRD scope; no BMD body revision) |
| DEC-078 | Allocation pricing-field composition: `producer_price` (club) + `negotiated_cost` (Discovery + Direct Purchase) — **SUPERSEDED by DEC-092** (the two-field design collapses into the unified `commercial_terms = {shape, value}` structure orthogonal to sourcing model and counterparty type) | 2026-05-04 | (Module A PRD scope under DEC-092 reshape; superseded — not load-bearing) |
| DEC-079 | Allocation `qty` mutability generalised beyond Hero Package: `qty` mutable across all sourcing × visibility combinations (passive_v1 / passive_v2 / direct_purchase × CLUB_ONLY / DISCOVERY_ONLY); anti-orphan rule (cannot decrease below issued voucher count) applies uniformly; refines DEC-069 | 2026-05-04 | §3.7 (OPT-3 v0.6 generalisation sentence); §2.3 mid-year capacity sentence at v0.4 anchors on Hero Package |
| DEC-080 | Non-serialization opt-out extended to all allocations (club + Discovery + Direct Purchase); per-allocation choice (not visibility-gated, not per-producer); broadens DEC-052 scope; sub-pool partition mechanics (`qty_to_serialize` / `qty_non_serialized`) carry forward unchanged | 2026-05-04 | §6.1 (REQ-1 v0.6 broadening); §6.2 table row refreshed; §12.1 DEC-052 entry refreshed |
| DEC-081 | Direct Purchase Allocation FSM: DRAFT → ACTIVE on `SupplierPaymentCompleted` (Module D event), NOT on physical receipt; sellability gate decoupled from shipment gate (`InboundEventPhysicallyAccepted` gates Module C shipment only); refines DEC-063 | 2026-05-04 | §3.7 (OPT-1 v0.6 ACTIVE-on-payment sentence); Module A FSM detail in PRD scope |
| DEC-082 | Allocation counterparty: `producer_id` always populated; `supplier_id` optional, **common on Discovery** (Discovery+Supplier-not-Producer is the operational norm at launch, not a rare exception), admitted on Club (lower frequency); two-FK pattern; settlement / PO routes to `supplier_id` when populated, falls back to `producer_id` | 2026-05-04 | §3.1 (OPT-2 v0.6 Discovery-with-Supplier common-not-rare clarification); composes with DEC-067, DEC-020 |
| DEC-083 | Producer Portal ↔ Admin Panel parity for per-allocation operations (cross-cutting design principle; every allocation-level operation exposable from both surfaces; audit trail captures `actor_role`); covers visibility / qty / pricing / recall / sub-pool / opt-out / lifecycle / counterparty operations | 2026-05-04 | (Module A PRD scope; cross-cutting design principle; no BMD body revision) |
| DEC-084 | SupplierAgreement entity deferred at launch; supplier commercial terms live at allocation level (no parallel agreement entity to mirror Module K §4.6 ProducerAgreement); two-tier conceptual model: ProducerAgreement = umbrella (club-relationship); per-allocation commercial terms = specific (transaction-level) | 2026-05-04 | (Module D PRD scope; no BMD body revision) |
| DEC-085 | PurchaseOrder ownership 3-value enum: `PRODUCER / NEWCO / THIRD_PARTY` (Crurated v17 PO ownership pattern carries forward, with `CRURATED → NEWCO` rename for the NewCo entity); ownership derives from Allocation.sourcing_model | 2026-05-04 | (Module D PRD scope; no BMD body revision) |
| DEC-086 | PurchaseOrder timing three-way model: V1 PO at producer-settlement-cadence; V2 PO at sell-through-settlement cadence; Direct Purchase PO at PI creation (full-amount-at-purchase). Cross-module event chain `SupplierPaymentCompleted` (Module D) → `AllocationActivated` (Module A FSM trigger per DEC-081) | 2026-05-04 | (Module D PRD scope; cross-module event chain to Module A; no BMD body revision — §3.7 / §3.10 already anchor timing-by-sourcing-model framing) |
| DEC-087 | Module D owns SupplierProducerLink entity (per DEC-067 separation); PO-line validation gate at issuance — Discovery+Supplier-not-Producer (DEC-082 common pattern) requires the link to authorise Supplier for each constituent Producer's wine | 2026-05-04 | (Module D PRD scope; no BMD body revision) |
| DEC-088 | Inbound-receiving party = Supplier always (PO counterparty); Producer-as-Supplier collapses trivially; Discovery+Supplier-not-Producer is COMMON operational pattern (per DEC-082) — inbound routes through the Supplier counterparty | 2026-05-04 | (Module D PRD scope; no BMD body revision) |
| DEC-089 | INV1 / INV2 customer-facing only (per DEC-045); Supplier payment shape **domain-separated** — single financial commitment at PO issuance for Direct Purchase / Supplier-counterparty allocations; settles on Supplier's payment terms (informal Supplier metadata per DEC-084) | 2026-05-04 | (Module D PRD scope; no BMD body revision) |
| DEC-090 | Producer recall reverse-inbound: Module D event-recording at launch (`ReverseInboundEventRecorded` event per DEC-091 catalogue); manual operator capability admitted (Producer Portal trigger or Admin Panel entry per DEC-083); addresses v12-acknowledged recall gap | 2026-05-04 | (Module D PRD scope; cross-link §3.9 unsold-stock recall path) |
| DEC-091 | Module D event catalogue at launch: `ProcurementIntentCreated`, `PurchaseOrderIssued`, `SupplierPaymentCompleted`, `InboundEventPhysicallyAccepted`, `InboundEventCostFinalized`, `ConsignmentReceiptRecorded` (V2), `ReverseInboundEventRecorded`, `POIssuedUnderNonActiveAgreement` (audit; per DEC-094 Producer-counterparty Level 1 override only) | 2026-05-04 | (Module D PRD scope; no BMD body revision) |
| DEC-092 | Allocation `commercial_terms = {shape, value}` structure: `shape ∈ {fixed_per_unit, percent_of_selling_price}`, `value` = per-unit amount or percentage; **orthogonal to sourcing model and counterparty type**; 12.5% / 87.5% club mechanic = canonical `percent_of_selling_price` instance with `value` = 12.5%; passive consignment confirmed as NewCo's preferred sourcing model for Club + Discovery (cashflow-positive); direct purchase = FALLBACK; **supersedes DEC-078** | 2026-05-04 | §3.10 (REQ-2 v0.6 generalisation); App A Glossary "Sell-through" generalised + new "Commercial Terms" entry; §12.1 DEC-078 marked SUPERSEDED |
| DEC-093 | ProcurementIntent / PurchaseOrder / InboundEvent flow **uniform across sourcing models** (no separate Direct Purchase flow shape in data model); trigger + timing parameterised per sourcing model: PI trigger (V1/V2 = auto on voucher issuance; Direct Purchase = manual ops at allocation creation); PO timing (per DEC-086); Allocation FSM trigger (per DEC-081); refines DEC-063 | 2026-05-04 | (Module D PRD scope; refines DEC-063; no BMD body revision — §3.7 / §5.1 don't claim a separate Direct Purchase flow) |
| DEC-094 | PO issuance two-level gate: (Level 1) ProducerAgreement umbrella for Producer-counterparty POs — must be `active`; operator override surface fires `POIssuedUnderNonActiveAgreement` audit event; (Level 2) Allocation state — must be in valid sellable state for all PO counterparty types. Supplier-not-Producer-counterparty POs (DEC-082 admitted pattern) skip Level 1 (no SupplierAgreement entity per DEC-084) — Level 2 only | 2026-05-04 | (Module D PRD scope; gating discipline; no BMD body revision) |
| DEC-095 | Offer entity as separate first-class entity at NewCo (v17 §5.2 inheritance); Offer ↔ Allocation cardinality N:1 or N:M (multi-producer Discovery composite per DEC-097); Offer carries own FSM, pricing surface, granularity, eligibility filters, time-window, Layer 3 commercial_unbreakable | 2026-05-04 | (Module S PRD scope; no BMD body revision — entity boundary lives at PRD level) |
| DEC-096 | Hero Package = `Offer.is_hero_package` boolean flag (default `false`) with three Hero-Package-conditional concerns (single-purchase-per-Profile-per-club-year + Capacity Invariant + `MembershipFeePaid` event); Module 0 framing preserved (NOT a PIM Composite SKU attribute) | 2026-05-04 | App A Glossary "Hero Package" entry (REQ-6 v0.7); Module S PRD owns flag mechanics |
| DEC-097 | Multi-producer Discovery composite Offer publication = ONE composite Offer (single Offer row) referencing N constituent Allocations atomically; `composite_constituent_allocation_ids[]` (multi-FK for composites); customer-facing `P_d` lives on Offer; OC 5% × `P_d` computed once on composite headline | 2026-05-04 | (Module S PRD scope; no BMD body revision — composite binding pattern at PRD level; OPT-2 v0.7 skipped) |
| DEC-098 | Module S Offer publication 5-rule validation contract: (1) Allocation state ACTIVE; (2) visibility match strict; (3) serialization alignment with `non_serialized_offer_admitted` (DEC-080); (4) `commercial_terms.value` populated (DEC-092); (5) Layer 3 cannot downgrade Layer 2; `OfferPublicationValidationFailed` on failure | 2026-05-04 | (Module S PRD scope; no BMD body revision — validation contract at PRD level) |
| DEC-099 | Multi-Offer-per-Allocation pattern admitted; each Offer's voucher issuance decrements shared `Allocation.available_qty = qty - issued`; first-to-consume-last-unit wins; `AllocationCapacityExhausted` on overflow | 2026-05-04 | (Module S PRD scope; no BMD body revision) |
| DEC-100 | Offer pricing derives strictly from Allocation `commercial_terms` (DEC-092 shape × value); `Offer.promotional_price` is Offer-level overlay (Allocation untouched); producer-opt-in via `ProducerPromotionConsent` for club promotions per DEC-039 | 2026-05-04 | (Module S PRD scope; overlay pattern aligns with §4.12; no BMD body revision) |
| DEC-101 | Order FSM v17 §5.6 inheritance verbatim (12 states) with NewCo simplifications (B2B credit-terms branches DEFERRED per DEC-068; active-consignment / CruTrade branches DROPPED); **PENDING_PAYMENT IS the bank-transfer credit-terms state at NewCo launch**; no other B2C credit-terms flow at launch | 2026-05-04 | §4.7 (OPT-1 v0.7 PENDING_PAYMENT credit-terms framing); composes with DEC-049 / DEC-068 / DEC-102 / DEC-105 / DEC-107 |
| DEC-102 | NewCo Voucher state machine = 8 states: PENDING_PAYMENT → ISSUED → REDEMPTION_REQUESTED → SHIPPED → CONSUMED + VOIDED / EXPIRED / GIFTED; v17 ON_CRUTRADE DROPPED (no CruTrade marketplace per BMD §4.4); v17 RESOLVED + BottlingResolution N:M reissuance + BOUGHT_BACK DEFERRED | 2026-05-04 | §9.7 (OPT-6 v0.7 PENDING_PAYMENT pre-state explicit) + App A Glossary "Voucher" entry (REQ-6 v0.7) |
| DEC-103 | Voucher EXPIRED state IN at NewCo launch; trigger = scheduled job firing on bound `Allocation.expiry_date`; expiry_date optional (default null = no expiry); refund policy on EXPIRED reads DEC-025 matrix | 2026-05-04 | (Module S PRD scope; no BMD body revision) |
| DEC-104 | Voucher reissuance / substitution = manual operator capability at launch via NewCo Admin Panel; `VoucherSubstitutionExecuted` event records (original Voucher → substitute Bottle Reference / SKU + reason); passive-consignment-makes-rare framing per DEC-092; full automation deferred to Phase 2+ | 2026-05-04 | §3.13 (OPT-5 v0.7 substitution edge-case framing folded into REQ-3); composes with DEC-090 / DEC-117 |
| DEC-105 | Cart Hold timeout = **15 minutes default**, system-wide configurable by NewCo ops, NOT per-Offer (v17 §5.7 inheritance); bank-transfer extension to 7 calendar days per DEC-049 / DEC-101 / DEC-106 is the only payment-method-conditional override at launch | 2026-05-04 | App A Glossary "Cart Hold" entry refreshed (REQ-6 v0.7); Module S PRD owns value |
| DEC-106 | Cart Hold strict-timeout discipline locked: 15-minute timer counts down regardless of customer interaction; customer activity does NOT reset the timer; only DEC-049 bank-transfer payment-method selection extends the timer (to 7 calendar days) | 2026-05-04 | (Module S PRD scope; no BMD body revision) |
| DEC-107 | Module S customer-facing invoice events: `InvoiceINV1Issued` at order confirmation (post-payment-cleared per DEC-112) + `InvoiceINV2Issued` at shipment / fulfilment; Hero Package = one INV1 + N `VoucherIssued` events; ship-on-confirmation = distinct INV1 + INV2 fire simultaneously (no collapse) | 2026-05-04 | §8.13 (event-name surface in REQ-1 v0.7 §8.13 expansion to INV1 / INV2 / INV3); composes with DEC-045 |
| DEC-108 | 14-day cancellation window: timer-start = INV1 issuance (= post-payment-cleared per DEC-112); window applies **pre-shipment ONLY** (Voucher in PENDING_PAYMENT / ISSUED / REDEMPTION_REQUESTED); once Voucher SHIPPED, cancellation right is **WAIVED** (EU Distance Contracts Directive 2011/83/EU Article 16 carve-out for goods that deteriorate / expire rapidly) | 2026-05-04 | **§4.10 + §4.11** (REQ-2 v0.7 substantive revision); composes with DEC-025 / DEC-057 / DEC-079 / DEC-102 / DEC-109 / DEC-112 |
| DEC-109 | 1-voucher-per-bottle invariant locked: vouchers are bottle-granular at NewCo launch (one Voucher row per bottle, regardless of Offer granularity); partial refund per voucher; cancelling one voucher in a multi-voucher Order = void that voucher + refund per-bottle amount | 2026-05-04 | (Module S PRD scope; aligns with BMD §4.4 voucher framing; no BMD body revision) |
| DEC-110 | Stacking algebra: Module S inherits v17 7-step price-resolution chain + mutual-exclusivity matrix per v17 §5.14; OC 5% × `P_d` share computed on **headline `P_d`** (NOT post-stacking net price) per BMD §8.14 — preserves OC economic interest regardless of NewCo's discount discretion | 2026-05-04 | (Module S PRD scope; algebra at PRD level; no BMD body revision — §8.14 framing intact) |
| DEC-111 | Club Credit auto-applies at checkout-render time to eligible line(s) up to **capacity needed** (= min(`credit.balance`, sum of eligible line totals)); customer can **remove** via explicit UX action; multi-Profile customer = each credit auto-applies to its respective eligible lines (no cross-Club credit pooling) | 2026-05-04 | §4.12 (OPT-4 v0.7 auto-apply UX clarification) |
| DEC-112 | `DiscoveryRevenueShareAccrued` fires at **INV1 issuance** = **post-payment-cleared** (NOT post-payment-authorized; NOT at OrderPlaced); card payments fire OC share at OrderCompleted; bank-transfer payments fire on funds-cleared at Airwallex (PENDING_PAYMENT → ISSUED transition) | 2026-05-04 | §8.13 / §8.14 (post-payment-cleared timing flagged in REQ-1 v0.7 event-sequence prose); App A Glossary "Originating Club" entry refreshed (REQ-6 v0.7) |
| DEC-113 | Sanctions-screening enforcement gate fires **between OrderPlaced and PaymentAuthorization** (pre-payment); Module S reads `Customer.sanctions_status` per Module K v0.2 §9.3; non-`passed` blocks order + emits `OrderBlockedBySanctionsGate`; no card authorization fires for blocked Order | 2026-05-04 | (Module S PRD scope; refines v0.5 BMD §2.8 in-place; no BMD body revision) |
| DEC-114 | Hero Package three-gate eligibility check at order completion: (1) Profile state precondition (Approved Profile for the Hero Package's Club; Lapsed / Suspended / Cancelled blocks); (2) single-per-club-year (re-purchase blocked until next club year or prior-Hero-Package CONSUMED + year transition); (3) Capacity Invariant lookup (Active Profiles ≤ Allocation.qty per Module K v0.2 §13) | 2026-05-04 | (Module S PRD scope; aligns with BMD §2.3 / §9.1; no BMD body revision) |
| DEC-115 | Producer Portal ↔ Admin Panel parity (DEC-083) extends to Module S Offer-level operations: create / submit / publish / pause / close / promotional-pricing / Hero-Package-designate / Layer-3-set / granularity / time-window for **club Offers**; **Discovery Offers are NewCo-Admin-Panel-only at launch** (NewCo-curatorial commercial discretion per DEC-039); audit trail captures `actor_role: producer | newco_ops` | 2026-05-04 | (Module S PRD scope; OPT-3 v0.7 skipped — §7.4 already mentions Producer Portal scope at the right level for BMD prose) |
| DEC-116 | Gifting voucher transferability inherits v17 Module A §12 (7-day accept window; voucher locked PENDING_TRANSFER; no financial event; Allocation lineage preserved); NewCo recipient gates (registered + KYC `passed` + Offer-eligibility match); OC preservation per BMD §4.13 (giver's `originating_club_id` preserved as OC for gifted voucher's eventual sell-through); GIFTED state covers transfer-pending phase per DEC-102 | 2026-05-04 | (Module S PRD scope; mechanism aligns with BMD §4.13; no BMD body revision) |
| DEC-117 | Producer recall scope = **unsold sub-pool only** (`Allocation.qty - issued`); ISSUED Vouchers immutable post-INV1 and NOT subject to producer recall; refines DEC-090's voucher-void-on-recall scope to unsold-only; recall ≠ producer offboarding (offboarding = relationship lifecycle exit + commitment to honour outstanding ISSUED Vouchers per DEC-104) | 2026-05-04 | **§3.9 + §3.13** (REQ-3 v0.7 substantive revision); refines DEC-090 |
| DEC-118 | Storage fees mechanics at NewCo launch: Module E owns issuance (**module-ownership clause SUPERSEDED by DEC-119**; mechanics preserved); **NEW invoice type INV3** (third customer-facing invoice beyond INV1 / INV2); semi-annual cadence (end of June + end of December); €3 / bottle / year (€0.25 / month); partial-month rounding; **first 12 months FREE** from purchase; **mid-semester shipment carve-out** (storage charge for in-semester months rolls into bottle's INV2 line items rather than INV3); refines BMD §2.7 / §5.6 / §8.4 / §8.13 framing | 2026-05-04 | **§2.7 + §5.6 + §8.4 + §8.13 + §4.14 + Appendix A Glossary** (REQ-1 v0.7 substantive revision — largest single revision in v0.7; introduces NEW INV3 invoice type; ownership prose flipped at v0.8 per DEC-119) |
| DEC-119 | Storage-fee ownership flipped from Module E to **Module S** (supersedes DEC-118 ownership clause; mechanics preserved); Module S owns `StorageFeeAccrued` emission + INV3 issuance + per-bottle accrual + mid-semester INV2 roll-in (Module-S-internal logic; no cross-module query); Module E retains consumption + Xero routing + Airwallex charge execution per DEC-072 + DEC-014 boundary; storage-clock-start refined to **double anchor** = `max(INV1_date + 12 months, InboundEventPhysicallyAccepted_date)` (12-months-free-from-purchase AND bottle-at-warehouse condition); aligns customer-facing invoice ownership uniformly under Module S (INV1 + INV2 + INV3 all Module-S-emitted) | 2026-05-06 | **§2.7 + §5.6 + §8.4 + §8.13 + Appendix A Glossary** (REQ-1 v0.8 substantive revision — ownership prose flipped; mechanics preserved); refines DEC-118 (ownership clause superseded; mechanics carry forward) |
| DEC-120 | Recovery-scenario predecessor / successor chain shape — verbatim v17 §9 inheritance (symmetric link, chain depth unbounded); same NFC tag may carry multiple sequential NFTs over time without special-casing; working-hypothesis caveat (blockchain-expert review pending) | 2026-05-08 | (Module B PRD scope; no BMD body revision — BMD §6.11.1 + §6.11.3 prose already aligns) |
| DEC-121 | NFT mint timing — single mint per bottle (1:1 with `NFCTagApplied`); batched mints rejected at PRD layer to avoid tagged-but-not-yet-on-chain gap; working-hypothesis caveat | 2026-05-08 | (Module B PRD scope; no BMD body revision) |
| DEC-122 | NFT mint-payload composition — business-meaningful fields (catalog identity + allocation reference + NFC UID linkage + mint timestamp); no customer identity at mint per BMD §6.5 | 2026-05-08 | (Module B PRD scope; no BMD body revision — BMD v0.5 §6.5 in-place edit already reframed field list as illustrative) |
| DEC-123 | NFC tag application timing — Module B owns event-recording + SerializedBottle entity lifecycle; Logilize WMS executes physical workflow; Logilize as system of record for physical state, Module B for digital provenance + serialized-bottle commercial state | 2026-05-08 | (Module B PRD scope; no BMD body revision) |
| DEC-124 | NFC tag-write content — URL + bottle serial + on-chain reference (NFT-ID / chain-explorer hash) at launch; offline-resilient verification; **Paolo override A→B**; working-hypothesis caveat | 2026-05-08 | App A Glossary "NFC tag" entry refresh (OPT-1 v0.8); Module B PRD owns payload encoding |
| DEC-125 | Module B consumes Module D `InboundEventPhysicallyAccepted` + reads Module A sub-pool partition; Module B is read-only on Module A state; only `qty_to_serialize` portion enters Module B's NFC + NFT pipeline | 2026-05-08 | (Module B PRD scope; no BMD body revision) |
| DEC-126 | Module B as system of record for Bottle Page data (per-bottle state, allocation context, NFT reference, predecessor / successor chain); rendering surface (HTML / CSS / JS / image hosting / CDN) downstream tech per DEC-073 | 2026-05-08 | (Module B PRD scope; no BMD body revision) |
| DEC-127 | Six-locale Bottle Page launch — auto-detect on first scan + sticky cookie + browser `Accept-Language` + English fallback chain + manual switcher + per-attribute fallback (missing string falls back to English at attribute level, not page level) | 2026-05-08 | (Module B PRD scope; no BMD body revision — operationalises DEC-031 / DEC-064 at Bottle Page surface) |
| DEC-128 | Bottle Page provenance-trail anonymisation — chronological location waypoints with dates ("in producer cellar from … → in NewCo warehouse from dd/mm/yyyy → delivered to private cellar dd/mm/yyyy"); zero customer identifiers; **Paolo refinement** of analyst's "shipped to Customer" framing | 2026-05-08 | §6.8 anonymisation prose refresh (OPT-2 v0.8); refines DEC-024 + DEC-029 at Bottle Page rendering surface |
| DEC-129 | §6.11.1 Damaged tag in warehouse — Module B 5-event chain (`NFCTagDamagedInCustody` → ops authorisation → `NFCTagReapplied` → `NFTReissued` (predecessor link) → `NFTBurnedAsTagDamaged`); Bottle Lifecycle stays SERIALIZED → AVAILABLE | 2026-05-08 | (Module B PRD scope; no BMD body revision) |
| DEC-130 | §6.11.2 Damaged tag post-shipment — Module B event-recording role only (`BottlePostShipmentTagIssueReported` + `ProvenanceCertificateIssued`); no re-tagging per DEC-051; CS remedy mechanics flow Module S / Module E | 2026-05-08 | (Module B PRD scope; no BMD body revision) |
| DEC-131 | §6.11.3 NFT lost in NewCo wallet — Module B 2-event chain (`NFTLossInWalletDetected` + `NFTReissuedDueToWalletLoss` with predecessor link); lost NFT NOT burned (NewCo cannot reach to burn) — recorded as stale via on-chain attestation; working-hypothesis caveat | 2026-05-08 | (Module B PRD scope; no BMD body revision) |
| DEC-132 | §6.11.4 Bottle destroyed pre-shipment — Module B 2-event chain (`BottleDestroyedInCustody` + `BottleNFTBurnedAsDestroyed`); Module A debits pool; Module S substitutes per DEC-104 or refunds per §4.11; Bottle Lifecycle WRITTEN_OFF | 2026-05-08 | (Module B PRD scope; cross-module Module A + Module S coordination; no BMD body revision) |
| DEC-133 | Module B no-op on non-serialized stock — module-boundary discipline (no SerializedBottle, no NFC tag, no NFT, no Bottle Page, no recovery chain); the "non-serialized" badge on Module S Offer is Module S's concern | 2026-05-08 | (Module B PRD scope; no BMD body revision; composes with DEC-080 + DEC-052) |
| DEC-134 | NFT burn cross-module event chain — Module C dispatch → Module S `VoucherShipped` (carries shipped bottle's serial / NFT identity) → Module B consumes → emits `BottleNFTBurned`; Module B does NOT subscribe to Module C events directly | 2026-05-08 | (Module B PRD scope; aligns with BMD §6.7; no BMD body revision) |
| DEC-135 | NFT burn-transaction anonymisation — on-chain signals limited to timestamp + reason + NFT-ID / NFC UID linkage + opaque hash of Voucher.id (no customer-identifying data on-chain); operationalises DEC-024 + DEC-029 at burn-event layer | 2026-05-08 | (Module B PRD scope; no BMD body revision) |
| DEC-136 | Module B event catalogue at launch — emitted: `NFCTagApplied`, `NFTMinted`, `BottleSerialized`, `BottleNFTBurned` + recovery-chain variants (DEC-129..132); consumed: Module D `InboundEventPhysicallyAccepted` (DEC-125) + Module S `VoucherShipped` (DEC-134) + Module A allocation events; PRD finalisation may normalise variants under parameterised `BottleNFTBurned` with `reason` discriminator | 2026-05-08 | (Module B PRD scope; no BMD body revision) |
| DEC-137 | Late-binding selection algorithm — two-surface framing: (i) voucher-side FIFO by Voucher expiry (longest-held / earliest-expiry voucher gets the bottle); (ii) bottle-side Logilize warehouse-efficiency (most efficient physical pick wins) within allocation-pool constraint; producer-override deferred Phase 2+; **Paolo refinement** with open follow-up on producer-override | 2026-05-08 | §5.5 two-surface framing (OPT-5 v0.8); composes with DEC-060 + DEC-099 + DEC-076 |
| DEC-138 | Returns + replacement workflow at Module C — module-owned end-to-end (`PostShipmentIssueReported` → optional `ReturnReceiptRecorded` → `ReplacementShipmentIssued` → `ReplacementShipmentDelivered`); no cash refunds (replacements only); original Voucher commercial state preserved (no new Voucher / new INV2 per DEC-108) | 2026-05-08 | (Module C PRD scope; no BMD body revision — BMD §4.10 / §4.11 / §5.10 already aligned via DEC-108 v0.7 prose) |
| DEC-139 | Shipping Order entity 5-state machine + 2 sub-state flags + customer-initiated-only trigger — `draft → planned → picking → shipped → completed` + `cancelled` (terminal pre-shipped) + `returned` (post-shipment per DEC-108 + DEC-138) + `lost` (transit-loss); sub-flags `compliance_hold` (on `draft`) + `manual_review` (on `picking`); inherits v17 §C.4 baseline with NewCo simplifications (no B2B-credit-term branch; no active-consignment SO carve-out; no auto-SO on combined invoicing) | 2026-05-08 | (Module C PRD scope; no BMD body revision; composes with DEC-068 + DEC-017 + DEC-011 + DEC-113) |
| DEC-140 | Logilize WMS integration scope at launch — 5-stream contract: outbound shipment instruction; pick confirmation (binds bottle's serial / NFT for serialized; allocation + InboundBatch + qty for non-serialized); dispatch confirmation; delivery confirmation (best-effort tracking); storage-location tracking; inbound (NFC application + InboundEventPhysicallyAccepted) + stock state remain Module B + Module D scope per BMD §5.3 | 2026-05-08 | (Module C PRD scope; no BMD body revision — operationalises BMD §5.3) |
| DEC-141 | Logilize / NewCo ERP reconciliation contract — Logilize = physical bottle location + custody state + workflow execution + storage location; NewCo ERP = Allocation / Voucher / Order / Customer / Catalog / Bottle / NFT / Procurement / SO state; real-time event-driven reconciliation; conflict-resolution operator surface in NewCo Admin Panel ("Logilize discrepancy" queue, parallels Module D Discrepancy queue per DEC-091) | 2026-05-08 | (Module C PRD scope; no BMD body revision; composes with DEC-091) |
| DEC-142 | Late-binding event flow — `BottlePicked` cross-module 7-step chain: Module S `VoucherRedemptionRequested` → Module C SO `draft → planned` → reads Module A allocation context → sends pick request to Logilize → consumes pick-confirmation → fires `BottlePicked` → SO `planned → picking → shipped` → `ShipmentDispatched` → Module S consumes + transitions Voucher REDEMPTION_REQUESTED → SHIPPED + emits `VoucherShipped` (consumed by Module B for NFT burn per DEC-134) | 2026-05-08 | (Module C PRD scope; cross-module event chain; aligns with BMD §5.5; no BMD body revision) |
| DEC-143 | In-transit voucher display contract — for Direct Purchase Vouchers whose Allocation has not yet had `InboundEventPhysicallyAccepted` fire, Module C surfaces "in transit; ETA X" on Cellar + Voucher detail surfaces; Customer cannot redeem until physical receipt (Module C shipment gate at SO `draft → planned` per DEC-081); ETA from Module D in-transit metadata | 2026-05-08 | (Module C PRD scope; operationalises DEC-081 at Module C surface; no BMD body revision) |
| DEC-144 | Three shipping modes at launch — direct shipment (default) + customer pickup + events; sub-flag `is_gift` (gift shipments per DEC-116); SO `dispatch_mode` discriminator; mode-specific workflow variants (pickup = INV2 at handover; events = ship-to-event-venue) | 2026-05-08 | (Module C PRD scope; no BMD body revision — operationalises BMD §5.7 + §4.13 + DEC-116) |
| DEC-145 | Carrier selection + shipping-fee quote — automatic (operator-configurable rule set + carrier-API integration where available) + manual operator-entered (fee + selected carrier + transit estimate); same `ShippingFeeQuoted` event with `quote_origin` discriminator (`auto` / `manual`); used in white-glove cases per DEC-147; **Paolo refinement** | 2026-05-08 | §5.7 manual-quote-entry sentence (OPT-3 v0.8); composes with DEC-107 + DEC-147 |
| DEC-146 | Cross-module shipping-fee event flow + INV2 line-item composition — INV2 at NewCo launch includes bottle / Voucher amount (already on INV1, not re-billed) + shipping fee actual + destination VAT + excise + **storage fee accrued up to dispatch date** (mid-semester carve-out per DEC-118 + DEC-119); Module C does NOT issue invoices directly (INV2 is Module-S-owned per DEC-119); **Paolo confirmation reminder** on storage roll-in | 2026-05-08 | (Module C PRD scope; aligns with §8.4 + §8.13 v0.8 prose; no BMD body revision) |
| DEC-147 | Destination eligibility two-tier — automated path for pre-cleared destinations + **white-glove customer-service fallback** for non-eligible / complex destinations ("send shipping request" CTA → CS ticket → case-by-case approval with manual quote per DEC-145; if approved, SO proceeds; if denied, continued-storage or cancellation per DEC-108); ineligible destination does NOT terminate path (no hard block at launch); **Paolo refinement** | 2026-05-08 | **§5.8** (REQ-2 v0.8 substantive revision); composes with DEC-041 + DEC-145 + DEC-108 + DEC-115 + DEC-083 |
| DEC-148 | US-state alcohol rules — automated-vs-case-by-case principle, simple at launch (operator pre-clears easier states for automated path; harder states route via white-glove fallback per DEC-147); detail rule-matrix expansion Phase 2+; **Paolo refinement** | 2026-05-08 | §5.8 US-state simple-at-launch sentence (OPT-4 v0.8); composes with DEC-041 + DEC-145 + DEC-147 |
| DEC-149 | DDP/DAP shipping terms for non-EU — simple model at launch (operator picks per-destination default; admin-overridable on dispatch event; `incoterms = DDP` or `DAP`); edge cases route via white-glove fallback per DEC-147; country-by-country expansion Phase 2+; **Paolo refinement** | 2026-05-08 | §5.8 simple-at-launch sentence (OPT-4 v0.8 cross-reference); composes with DEC-056 + DEC-045 + DEC-147; BMD §10 already accommodates the simple-model + expansion disposition |
| DEC-150 | Excise + customs computation event flow at shipment — Module C reads destination + BR / Wine Variant + alcohol classification (Module 0 PIM) + excise rate matrix at SO `planned → picking`; emits `ExciseCalculated`; Logilize executes customs documentation per destination; Module S consumes for INV2 composition per DEC-107 | 2026-05-08 | (Module C PRD scope; aligns with BMD §5.4 + DEC-045; no BMD body revision) |
| DEC-151 | BMD §5.9 damages / breakage / transit-loss event ownership — Module-C-owned: `BottleBreakageInTransit`, `BottleLossInTransit`, `BottleWriteOff` (when Module-C-triggered), `InsuranceClaimOpened`, `InsuranceClaimResolved`; Module-B-owned (cross-link): `BottleBreakageInCustody` per DEC-132 §6.11.4 | 2026-05-08 | (Module C PRD scope; aligns with BMD §5.9; no BMD body revision; composes with DEC-025 + DEC-048 + DEC-138 + DEC-132 + DEC-072) |
| DEC-152 | Producer recall reverse logistics at Module C — manual operator capability at launch (operator-driven mirror of forward shipment workflow); `ReverseShipmentDispatched` recorded when stock leaves Vinlock back to producer; no automated reverse-logistics-carrier-integration at launch; full reverse-logistics mechanics deferred Phase 2+ per DEC-155 | 2026-05-08 | (Module C PRD scope; refines DEC-090 + DEC-117 at Module C side; no BMD body revision) |
| DEC-153 | Storage-location customer-facing granularity — warehouse-level at launch ("Stored at NewCo Vinlock cellar in France"); sub-warehouse granular detail (row / rack / cellar zone) tracked in Logilize but not exposed customer-facing; multi-warehouse-level granularity becomes valuable when multi-warehouse expansion lands per DEC-155 Q-OQ-16 | 2026-05-08 | (Module C PRD scope; aligns with BMD §5.2 + §5.3; no BMD body revision) |
| DEC-154 | Cellar render data composition — six-module read contract: Module S (Voucher state + storage-fee state per DEC-119) + Module C (physical state for in-flight Vouchers + storage-location summary + in-transit voucher state per DEC-143) + Module B (Bottle Page link for serialized stock per DEC-126) + Module 0 (BR + tasting notes — translatable per DEC-031 / DEC-064); Cellar UX layout downstream per DEC-073 + Paolo's deferral | 2026-05-08 | (Module C PRD scope; cross-module read contract; no BMD body revision) |
| DEC-155 | Wave 4 Q-OQ batch confirmation — 5 deferrals stand: Q-OQ-14 Crurated-tenant carve-out (Avalanche wallet operationally separate per DEC-001 + DEC-020 + App B.4); Q-OQ-15 smart contract audit + governance (tech-team / external-audit per DEC-073); Q-OQ-16 multi-warehouse expansion (single Vinlock-operated warehouse at launch per BMD §5.2 + §13.8); Q-OQ-17 drop-ship deferred (every voucher → physical-shipment goes through Vinlock per BMD §13.3); Q-OQ-18 producer recall full reverse-inbound mechanics (manual at launch per DEC-152; full mechanics Phase 2+) | 2026-05-08 | (Wave 4 Q-OQ batch; no BMD body revision — all five deferrals already explicitly out-of-scope in BMD v0.7 / §13) |
| DEC-156..DEC-184 | Wave 5 Module E elicitation 21-atom resolution — Module E event-recorder framing + AirwallexAdapter (cards / SEPA / saved-card / 3-stage failed-charge escalation per DEC-160) + Xero routing (real-time per-event sync; document-generation; multi-currency dual recording per DEC-169) + 5-section settlement statement (DEC-156 Paolo Option C) + OC routing (DEC-161 Paolo correction) + Section D info-disclosure constraint (DEC-162 Paolo refinement) + chargeback 5-step chain (DEC-168) + NonRevenueCost unified wrapper (DEC-167) + DEC-180..184 cross-cutting closure (sanctions/Hold uniformity, OC reversal symmetry, Returns + Replacement FSM, Allocation activation harmonisation across V1 / V2 / Direct Purchase) | 2026-05-08 | (Module E PRD v0.1 + cross-module closure scope; no BMD body revision — events-only framing per DEC-072 honoured throughout) |
| DEC-185 | **Stage 8 — Module B inventory-authority restoration scope.** Module B v0.2 restores v17 §B.1 inventory-authority framing in full. Module B = ERP-side inventory authority (ledger discipline, entity ownership for InboundBatch / StockPosition / Case / QuarantineRecord, ATP source, receiving physical-match authority, stocktake + adjustment authority, provenance immutability, two-layer no-overselling at physical level, committed-inventory protection); Logilize = physical-state execution arm. Inventory tracked across four orthogonal dimensions: ownership (2-value enum at NewCo launch — `PRODUCER` / `CRURATED`; `THIRD_PARTY` OUT per Q-CL-2 + DEC-068); custody; commercial status; allocation lineage. Two-layer no-overselling guard restored (Q-CL-5): Module A allocation-pool layer + Module B physical-inventory layer; both must pass at hold placement / voucher issuance. Committed-inventory protection restored (Q-CL-6); event-consumption N/A at launch (DEC-068). v0.1 NFT/provenance content (DEC-120..132 + DEC-134..136) preserved unchanged in v0.2 as digital-provenance sub-layer of Module B's broader ledger role | 2026-05-09 | **§5.3 (REQ-A v0.9 substantive revision) + §6 intro four-way reconciliation paragraph (REQ-D v0.9; new); composes with DEC-076 + DEC-068 + DEC-001 + DEC-099 + DEC-128 + DEC-153 + DEC-118 + DEC-119; foundational for DEC-186..DEC-196** |
| DEC-186 | **Stage 8 — Non-serialized inventory at InboundBatch level; supersedes DEC-133.** Module B v0.2 tracks NS inventory at InboundBatch level with v17 §B.5a counter set: `qty_planned_serialize` + `qty_actually_serialized` + `qty_non_serialized_committed` + `qty_non_serialized_reserved`. NS-pool ATP = `received_quantity − qty_planned_serialize − qty_non_serialized_committed − qty_non_serialized_reserved`. Serialization-plan-vs-actual divergence emits `BatchSerializationDiscrepancy`. **DEC-133 re-scoped** from "Module B no-op on NS" to "Module B no-op on NS digital-provenance only" — NS stock has full inventory-ledger discipline at InboundBatch level (counters, ATP, audit, adjustments); the five no-op clauses preserved on the digital-provenance axis (no SerializedBottle, NFC, NFT, Bottle Page, recovery chain) | 2026-05-09 | (Module B PRD v0.2 scope; no BMD body revision; composes with DEC-080 + DEC-052 + DEC-125 + DEC-185; **supersedes DEC-133** on inventory-ledger axis) |
| DEC-187 | **Stage 8 — ATP feed pattern Module B → Module A push.** Module B emits inventory events (`BottleStateChanged`, `InventoryAdjusted`, `OwnershipTransitioned`, `BottleQuarantined`, `BottleQuarantineResolved`, `StocktakeReconciled`, NS-pool counter mutations) on every state change; Module A maintains a cached ATP per allocation (sub-pool decomposition `atp_serialized` + `atp_non_serialized`); hold placement reads cache (real-time strongly consistent by construction). Display-ATP cache staleness ≤5s; transactional reads bounded latency. Pull and hybrid patterns rejected. Two-layer no-overselling guard at hold-placement: both Module A allocation-pool ATP and Module B physical-inventory ATP must pass | 2026-05-09 | (Module B PRD v0.2 + Module A PRD v0.2 scope; no BMD body revision; composes with DEC-099 + DEC-076 + DEC-185 + DEC-196) |
| DEC-188 | **Stage 8 — Logilize stream split: Module C 4 fulfillment + Module B 5 inventory-state; supersedes DEC-140 on inventory-state axis.** Module C retains 4 fulfillment streams (outbound pick instruction; pick confirmation; dispatch confirmation; delivery confirmation — DEC-140's first four streams verbatim). Module B owns 5 inventory-state streams (storage-location tracking — Stream 5 migrated from Module C; receiving + physical-match per DEC-194; stocktake instruction + variance per DEC-189; inventory-adjustment proposal-and-confirmation per DEC-190; QuarantineRecord resolution flow per DEC-191) | 2026-05-09 | **§6.3 + §11.3 (REQ-C v0.9 substantive revision); composes with DEC-141 + DEC-014 + DEC-185 + DEC-153; supersedes DEC-140** on inventory-state axis |
| DEC-189 | **Stage 8 — Stocktake authority + 4-state lifecycle at Module B.** Module B v0.2 owns Stocktake entity + 4-state FSM (`planned → in_progress → variance_review → reconciled`). Scope: warehouse / storage-location / Bottle Reference; target date; variance tolerance threshold (configurable per scope). Logilize executes physical count via DEC-188 stream; Module B compares counts to ledger and computes variances. Variance resolution emits `InventoryAdjusted` per DEC-190 with supervisor-resolution-decision discriminator. Cadence-policy is operator setting, not PRD content (per `feedback_prd_rr_approval`); single-supervisor-approval discipline applied uniformly | 2026-05-09 | (Module B PRD v0.2 scope; no BMD body revision; composes with DEC-185 + DEC-188 + DEC-190 + `feedback_prd_rr_approval`) |
| DEC-190 | **Stage 8 — Inventory-adjustment workflow + event catalogue.** Module B owns adjustment workflow at launch. Proposal flow: operator-initiated (or stocktake-variance-derived per DEC-189; or QuarantineRecord-resolution-derived per DEC-191) → supervisor approval → terminal-state event emission. Adjustment types: `damage` / `loss` / `consumption` (placeholder for Phase 2+) / `recount` / `transfer` / `found`. Events: `InventoryAdjusted` (with `adjustment_type` discriminator + scope: bottle id for serialized / batch id + qty for NS / case id for case-integrity adjustments per DEC-192); `InventoryShortfallDetected` (when adjustment reduces committed inventory below outstanding vouchers). Module E consumes for damage / loss / write-off financial-event recording per DEC-072. Committed-inventory protection guard enforced at proposal-validation (Q-CL-6) | 2026-05-09 | **§5.9 cross-link refresh (REQ-F v0.9); composes with DEC-072 + DEC-104 + DEC-138 + DEC-185 + DEC-189 + DEC-191 + `feedback_prd_rr_approval` + `feedback_bmd_prd_no_accounting`** |
| DEC-191 | **Stage 8 — QuarantineRecord entity + quarantine-before-trust principle.** Module B v0.2 owns QuarantineRecord entity. Module B never creates inventory records from unverified Logilize data; unknown entities reported by Logilize land in QuarantineRecord pending manual supervisor investigation. Resolution paths: associate with existing InboundBatch / create new inventory record / reject as invalid / escalate. Resolved QuarantineRecords are immutable post-resolution. Resolution-driven mutations may emit `InventoryAdjusted` per DEC-190 | 2026-05-09 | (Module B PRD v0.2 scope; no BMD body revision; composes with DEC-185 + DEC-188 + DEC-190 + `feedback_prd_rr_approval`) |
| DEC-192 | **Stage 8 — Case entity + 3-state integrity FSM; recorder-not-gatekeeper discipline.** Module B v0.2 owns Case entity with 3-state FSM (`intact → partially_broken → broken (terminal)`; monotonic non-decreasing). Module B is recorder, not gatekeeper — does NOT re-derive or gate the layered-breakability rule (`effective_unbreakable = producer_breakability OR commercial_unbreakable` per `project_layered_breakability` v13 Stage 2.5 closure); Module 0 BR-020 / Module A allocation-creation / Module S offer-creation + cart-add validation / Module A voucher issuance / Module C fulfillment planning own the gate at upstream layers | 2026-05-09 | (Module B PRD v0.2 scope; no BMD body revision; composes with DEC-185 + `project_layered_breakability` — Stage 2.5 not re-opened by Stage 8) |
| DEC-193 | **Stage 8 — ConsignmentPlacement deferred at NewCo launch.** v17 §B.11 ConsignmentPlacement entity (CRURATED-owned stock at consignee location; B2B Account `party_type = CUSTOMER`) is OUT at NewCo launch per DEC-068 (B2C-only). No ConsignmentPlacement entity, no `ConsignmentPlacementRecorded` event, no `ConsignmentSellThroughRecorded` event, no Module C §C.12 active-consignment sell-through workflow, no Module E SELL_THROUGH_SETTLEMENT financial event. Stage 2+ recovery: tri-module restoration when NewCo BMD scope expands to admit B2B-customer relationships | 2026-05-09 | (Module B PRD v0.2 scope; no BMD body revision; composes with DEC-068 + DEC-001 + DEC-185; defers v17 §B.11 + Module C §C.12 + Module E SELL_THROUGH_SETTLEMENT to Stage 2+) |
| DEC-194 | **Stage 8 — Receiving discrepancy authority restored: Module D = documents in order; Module B = physical match.** Two-stage check at NewCo launch: Module D = 3-gate inbound QC at PHYSICALLY_ACCEPTED (paperwork + provenance + physical-condition-on-arrival); Module B = downstream physical-match check on each InboundBatch (compares physically-counted bottles against qty in `InboundEventPhysicallyAccepted` payload). Variance emits `InboundBatchDiscrepancy` back to Module D, which reopens the InboundEvent into DISCREPANCY state; resolution flows via Module D's existing DiscrepancyResolution pattern per DEC-091. Cost-basis consequences if discrepancy resolution adjusts qty | 2026-05-09 | **§5.1 (REQ-E v0.9 substantive revision); composes with DEC-091 + DEC-185 + DEC-195 + DEC-188 + v13 Stage 2.3 split-inbound-acceptance lineage** |
| DEC-195 | **Stage 8 — InboundBatch entity ownership at Module B + cost-basis flow.** Module B v0.2 owns InboundBatch entity (logical container for goods arriving from a single source — PO line or consignment receipt) with v17 §B.2 attribute set: expected qty (from PO line), received qty (from Logilize physical match per DEC-194), serialization progress (`qty_planned_serialize` + `qty_actually_serialized` per DEC-186), ownership flag (per DEC-185 2-value enum at NewCo launch), cost basis flow (provisional at PHYSICALLY_ACCEPTED → finalized at COST_FINALIZED per v13 Stage 2.3), serialization-plan target. Module D triggers InboundBatch creation via `InboundEventPhysicallyAccepted`; Module D's downstream `InboundEventCostFinalized` flips cost-basis from provisional to finalized. Cost-basis referenced at dispatch-time per Module C late-binding chain per DEC-142 | 2026-05-09 | **§9.8 (REQ-B v0.9 substantive revision); composes with DEC-185 + DEC-186 + DEC-194 + DEC-188 + DEC-142 + v13 Stage 2.3 + 2.4 + 2.6 lineage** |
| DEC-196 | **Stage 8 — StockPosition aggregated view at 5-dimension intersection.** Module B v0.2 owns StockPosition view at canonical 5-dimension intersection: `(bottle_reference, warehouse, case_config, allocation, ownership)`. View reports `total_quantity`, `committed_quantity` (committed to vouchers — protected per Q-CL-6 + DEC-185), `available_quantity` (= `total − committed − reserved − quarantined − under_adjustment` per v17 §B.8 sub-pool ATP formula). Sub-pool decomposition: `available_serialized` + `available_non_serialized` per allocation. Sellable quantity feeds Module A + storefront ATP read path; shippable quantity feeds Module C late-binding selection per DEC-137 + DEC-188. 5-dimension aggregation preserved without simplification — dropping `case_config` would lose case-integrity-aware ATP and break mixed-case unbreakable dispatch per DEC-192 | 2026-05-09 | (Module B PRD v0.2 scope; no BMD body revision; composes with DEC-185 + DEC-186 + DEC-187 + DEC-192 + DEC-137) |

Any new decisions taken during ongoing review will be appended as DEC-197+ in the Decision Register.

### §12.2 Open Questions Still Deferred at Launch

Of the 32 open questions in v0.1, **24 resolved into DECs in v0.2** (via DEC-032..057) and drop out of the active register; **8 remain genuinely deferred at launch** and continue to live in **Appendix C**:

- Q-OQ-2 Bottle Page customer-identity exposure (locked anonymous per DEC-024; retained as a historical anchor).
- Q-OQ-5 Customer support tooling (deferred per §11.7; re-evaluated post-launch on volume).
- Q-OQ-6 Community features on Consumer Portal (deferred; post-launch product roadmap).
- Q-OQ-7 Producer-side communication features (deferred; post-launch enhancement).
- Q-OQ-8 Services / experiences revenue mechanics (placeholder; future BMD revision).
- Q-OQ-9 24-month producer agreement detail (Paolo to share template separately).
- Q-OQ-10 Persona profile for target customer (Paolo to share separately).
- Q-OQ-11 Death / inheritance / corporate dissolution policy (deferred per §13.9; legal review).

Each of the eight is confirmed-deferred under DEC-058.

**Resolved in v0.2 (no longer open)** — for cross-reference, the v0.1 open IDs that resolved this revision and the DEC each maps to: Q-OQ-1 → DEC-053; Q-OQ-3 → DEC-050; Q-OQ-4 → DEC-047; Q-OQ-12 → DEC-056; Q-OQ-13 → DEC-041; Q-OQ-14 → DEC-054; Q-OQ-15 → DEC-033; Q-OQ-16 → DEC-034; Q-OQ-17 → DEC-048; Q-OQ-18 → DEC-035; Q-OQ-19 → DEC-039; Q-OQ-20 → DEC-040; Q-OQ-21 → DEC-042 (settlement-cadence framework locked; net-30 working baseline survives); Q-OQ-22 → DEC-043; Q-OQ-23 → DEC-049; Q-OQ-24 → DEC-037; Q-OQ-25 → DEC-038; Q-OQ-26 → DEC-057; Q-OQ-27 → DEC-044; Q-OQ-28 → DEC-046; Q-OQ-29 → DEC-036; Q-OQ-30 → DEC-045 (resolved by elimination — no estimate-vs-actual reconciliation needed under INV1/INV2); Q-OQ-31 → DEC-052; Q-OQ-32 → DEC-051 (resolved by removal — re-tagging exception path itself is removed).

### §12.3 Drafting Decisions Resolution

All six drafting decisions raised in v0.1 (D-A1..D-A6) are resolved into DECs in v0.2. Section retained as historical anchor; no new drafting decisions made for v0.2.

| v0.1 drafting decision | Topic | Resolution in v0.2 |
|------------------------|-------|--------------------|
| **D-A1** (§3.6, Q-OQ-20) — 5% with no Originating Club | Discovery purchase by no-OC customers allowed; no 5% recipient; full Discovery gross to NewCo | **DEC-040** confirmed |
| **D-A2** (§3.13, Q-OQ-22) — Club Credit on producer offboarding | Convert to Discovery store credit at face, 12-month validity | **DEC-043** confirmed |
| **D-A3** (§6.11.2, Q-OQ-32) — Post-shipment NFC tag damage | **Substantively *changed*, not just confirmed**: v0.1's tag-damage cutoff approach with re-tagging exception path was *replaced* by case-by-case customer-service intervention. The cutoff question is moot under the new policy. | **DEC-051** (replaces v0.1 default) |
| **D-A4** (§6.11) — Four NFC/NFT recovery defaults | All four confirmed (with §6.11.2 amendment per DEC-051) | **DEC-055** confirmed |
| **D-A5** (§4.11, Q-OQ-27) — Store-credit goodwill premium | Admin-configurable per case (default 105%) | **DEC-044** confirmed |
| **D-A6** (§3.10, Q-OQ-21) — Producer settlement payment terms | Quarterly cadence + agreement-configurable payment terms locked under DEC-042; net-30 survives as the BMD's working baseline (each agreement may negotiate shorter or longer) | **DEC-042** confirmed |

Future drafting decisions (i.e., recommendations made by the agent during v0.3+ revisions) will follow the same pattern: flagged in §12.3 of the relevant draft, then either promoted to a DEC or revised down per Paolo's input.

---

## §13 Out of Scope (explicit)

> The explicit out-of-scope list: no B2B, no active consignment, no drop-ship, no liquid sales, no CruTrade P2P, no native mobile apps at launch, no AI / Copilot at launch, no multiple warehouses at launch, no inheritance handling, no customer support tooling at launch, no intragroup mechanics with Crurated, no data migration.

This section enumerates each item explicitly OUT of scope at launch, with a short rationale and a forward note (deferred / never / re-evaluate).

### §13.1 Active Consignment

**OUT** (DEC-011, L3). Active consignment is a B2B-flavored sourcing model where NewCo would consign goods to a third party for resale; incompatible with consumer-only positioning (§1.7) and rejected at launch.

- *Future*: re-evaluate only if NewCo's business model shifts to include B2B (which it does not at launch).

### §13.2 B2B / Wholesale

**OUT** (DEC-017, B13). NewCo is consumer-only at launch. No wholesale customers, no on-trade, no resale tier, no B2B credit terms.

- *Future*: not currently on the roadmap. Strategic reconsideration if customer-base shape changes.

### §13.3 Drop-Shipping

**OUT** (L5). Producer-direct shipment to customer (skipping the warehouse) is not in scope.

- *Future*: not on the roadmap.

### §13.4 Liquid Sales / Pre-Bottling

**OUT** (D6, L4). Sale of wine pre-bottling (en primeur, ex-cuvée arrangements) is not in scope.

- *Future*: candidate for a Phase 2+ addition; the Crurated v17 model handles liquid sales (see Crurated v17 §1.4 / §2.6) and could inform a future NewCo extension.

### §13.5 CruTrade-Style P2P Trading

**OUT** (DEC-008 envelope, L2). Customer-to-customer secondary-market trading via the NFT layer (CruTrade analog) is not in scope.

- *Future*: candidate Phase 2+ feature. Member-to-member gifting (§4.13) is the only customer-to-customer path at launch.

### §13.6 Native Mobile Apps

**OUT** (DEC-018, G3). Web + mobile-web only at launch. No native iOS / Android apps.

- *Future*: re-evaluated post-launch on mobile-engagement telemetry.

### §13.7 AI / Operator Copilot

**OUT** (DEC-021, L6). All AI capabilities — operator copilot, customer-facing assistant, anomaly / fraud detection beyond Airwallex baseline, content recommendation, etc. — are deferred at launch.

- *Future*: re-evaluated post-launch (Crurated v17 §14 follows a similar deferred posture).

### §13.8 Multiple Warehouses / Multi-Site Custody

**OUT** at launch (E3). Single Vinlock-operated warehouse in France.

- *Future*: Phase 2 candidate for capacity / regional expansion.

### §13.9 Death / Inheritance / Corporate Dissolution

**DEFERRED** (K8, Q-OQ-11). Operationally handled case-by-case until a policy is set.

- *Future*: policy required as customer base ages and base size grows.

### §13.10 Customer Support Tooling

**DEFERRED** (J8, Q-OQ-5). At launch, customer-support workload uses the Admin Panel + Consumer Portal contact-us form + email.

- *Future*: scoped post-launch on actual support volume.

### §13.11 Intragroup Mechanics with Crurated

**OUT** (DEC-001, DEC-020). NewCo and Crurated operate independently. Crurated may appear as one Discovery supplier on standard commercial terms (DEC-020), but no shared identity, no catalog cross-access, no parent-sub mechanics, no shared warehouse pool, no shared customer base.

- *Future*: ownership / commercial structure is undefined; intragroup mechanics may be reconsidered if and when ownership is clarified — but **not in this BMD**.

### §13.12 Data Migration

**OUT** / **N/A** (L7). NewCo is greenfield; no legacy customers to migrate from any prior system.

- *Future*: N/A.

---

## Appendix A — Glossary

> Defined business terms used in the BMD, with one-line definitions and the section where each is introduced.

Defined business terms used in this BMD. Each entry: the term, a one-line definition, and the section where the term is introduced.

| Term | Definition | First introduced |
|------|-----------|------------------|
| **Admin Panel** | Internal-facing front-end surface used by NewCo operations and support staff. NewCo-only at launch. | §7.2 |
| **Allocation** | A producer's commitment of a quantity of a Bottle Reference for sale on a specified surface (Club, Discovery, or both). For club allocations, the producer sets the customer-facing price; for Discovery allocations, NewCo and the producer negotiate an allocation cost `C` per unit and NewCo sets the customer-facing Discovery price `P_d` (DEC-032). The unit of supply on which Vouchers are minted and Cart Holds taken. | §4.5 |
| **Allocation Visibility** | The flag on an Allocation indicating where it surfaces — `CLUB_ONLY / DISCOVERY_ONLY` (2-value enum at launch per DEC-076; `BOTH` was the third value in early-draft framings under DEC-023 but is dropped at launch, with split commitments materialising as sibling Allocation rows per visibility). | §4.5 |
| **Avalanche** | The blockchain layer used by NewCo for NFT minting / burning. EVM-compatible. | §6.4 |
| **Bottle Page** | Public, anonymous web page reached by scanning a serialized bottle's NFC tag. Displays provenance; does not expose customer identity. | §6.8 |
| **Airwallex** | NewCo's payment provider at launch (DEC-014). Card and bank-transfer capture, multi-currency settlement, saved payment methods for auto-renewal and storage charges, 3-D Secure / SCA in EEA. Disputes and chargebacks managed via Airwallex tooling. | §8.9, §11.4 |
| **Bottle Reference** | The catalog-level identity of a wine, scoped to **two dimensions** at launch (Wine Variant + Format) per Module 0 PRD §3.3 — producer and vintage are absorbed up the catalog hierarchy at the Wine Master / Wine Variant level (i.e., a Bottle Reference does not stack producer + vintage as additional dimensions; those are inherited from the Wine Variant). The Bottle Reference is the unit against which late binding operates. | §4.4 |
| **Cart Hold** | Soft reservation against an Allocation for a fixed timeout window when a customer adds an offer to cart. Inherits Crurated v17 §5.7's hold-with-timeout model. **Timeout = 15 minutes default at NewCo launch, system-wide configurable, NOT per-Offer** (DEC-105). **Strict timer**: customer interaction does not reset the timer (DEC-106). The only payment-method-conditional override is **bank-transfer extension to 7 calendar days** (DEC-049) to cover SEPA / SWIFT clearing windows; selecting bank transfer at checkout extends the hold for the duration of the funds-clearing window. **Distinct from the Module K `Hold` entity** (which covers Customer / Profile blocking states such as KYC, payment, fraud, compliance, credit holds — not cart reservations); the two share the word "hold" but operate on different objects. | §4.6 |
| **Cellar** | The customer-level collection of Vouchers and stored Bottles held in NewCo / Vinlock custody. | §7.3 |
| **Club** | A producer's membership program inside NewCo. One Producer can have multiple Clubs (one per brand). | §1.1 |
| **Club Credit** | Profile-level credit issued when the Hero Package value comes in below the committed cost; spendable only on that club's offers. | §2.3 |
| **Club Page** | Producer-branded front-end page inside the Consumer Portal, accessible to club Members. | §1.6 |
| **Commercial Terms** | The unified `{shape, value}` structure on each Allocation that records the negotiated per-bottle financial relationship between NewCo and the producer / supplier (DEC-092). `shape ∈ {fixed_per_unit, percent_of_selling_price}`; `value` = a per-unit amount or a percentage. **Orthogonal to sourcing model and counterparty type**: any sourcing × visibility × counterparty combination admits either shape. The 12.5% / 87.5% club mechanic (DEC-010) is the canonical `percent_of_selling_price` instance with `value` = 12.5%; the per-allocation negotiated cost `C` for Discovery passive consignment (DEC-032 framing) is the canonical `fixed_per_unit` instance. Supersedes the prior two-field design (`producer_price` for club + `negotiated_cost` for Discovery, DEC-078). | §3.10, App A (this entry) |
| **Consumer Portal** | The customer-facing front-end surface (web + mobile-web) used by Members, Waiting-list, and Legacy customers. | §7.3 |
| **Customer** | A natural person with a NewCo account. Holds zero or more Profiles (one per Membership) and a Cellar. | §2.1 |
| **Customer Segment** | One of: Member, Waiting-list applicant, Legacy. Drives access rights to clubs, Discovery, and storage. | §2.1 |
| **Direct Purchase** | Exceptional third sourcing model alongside Passive Consignment V1 and V2 (DEC-063). NewCo pays the producer / supplier outright at purchase, takes title at purchase, holds inventory at Vinlock, and sells with full title transfer to the customer (Seller of Record unchanged). Used for strategic stock-up, one-off Discovery sourcing where the producer prefers a clean sale, or supplier relationships where outright purchase is the commercial norm. Bypasses sell-through settlement (producer paid at purchase, not at sell-through). | §3.7, §5.1 |
| **Discovery Tab** | NewCo's global cross-producer marketplace, accessible to all customer segments. NewCo controls Discovery pricing (`P_d`) and shares 5% × `P_d` with each buyer's Originating Club; the producer of the bottle is paid the per-allocation negotiated cost `C` (DEC-032). | §1.1, §4.5 |
| **Discovery Allocation Cost (`C`)** | The per-unit cost NewCo and the producer of a Discovery allocation negotiate for that allocation; paid to the producer per unit sold; not a formulaic percentage of the customer-facing price. Introduced in DEC-032. | §3.6 |
| **Discovery Price (`P_d`)** | The customer-facing price NewCo sets for a Discovery allocation; NewCo's gross margin per unit is `P_d − C`; 5% × `P_d` is paid to the buyer's Originating Club. Introduced in DEC-032. | §3.6 |
| **DPO** | Data Protection Officer (GDPR Article 37). Internal assignment at NewCo. | §2.9 |
| **Hero Package** | Producer-curated mixed-case **designation**, released once per club year; its price = annual membership cost; # available = the upper bound on the year's active members. The Hero Package is a Module S **Offer-level designation** realised as the **`Offer.is_hero_package` boolean flag** (default `false`) per DEC-096 — not a PIM Composite SKU attribute, not a separate Offer subtype. Three Hero-Package-conditional concerns attach to the flag (DEC-096 + DEC-114): (1) single-purchase-per-Profile-per-club-year enforced at order completion; (2) Capacity Invariant cross-check at order completion (Active Profiles ≤ Allocation.qty per Module K v0.2 §13); (3) `MembershipFeePaid` event emission on Hero Package purchase. **Capacity is mutable mid-year** (DEC-069 + DEC-079 generalisation): the producer can scale the Hero Package count up if demand exceeds the original commitment; capacity decreases below the count of currently-active members are not supported (would orphan members), but decreases above the active count are legal. **Price is asymmetric** (DEC-007): if the package value comes in below the committed membership cost, the difference accrues to the Member as Club Credit on that Club; if it comes in above, the Customer still pays only the committed cost (NewCo / producer absorb the upside). | §2.3 |
| **HubSpot** | NewCo's CRM and marketing-automation platform. | §11.5 |
| **KYC / AML / UIF / OFAC** | Know Your Customer / Anti-Money Laundering / Italian financial-intelligence unit (Unità di Informazione Finanziaria) / US Office of Foreign Assets Control. NewCo's compliance posture is light at consumer scale; the screening triple at onboarding is **EU + Italian UIF + OFAC** (DEC-030 amended by DEC-041 to add OFAC because the US is in the launch destination set). | §2.8, §10.7 |
| **Late Binding** | Practice of selecting the specific physical bottle assigned to a customer's Voucher at shipment time, not at sale time. | §4.4, §5.5 |
| **Legacy (segment)** | Ex-Member customer who still holds at least one unredeemed Voucher or stored Bottle. Discovery access only; no producer club access. | §2.1 |
| **Logilize** | NewCo's WMS (Warehouse Management System). Same provider as Crurated, separate integration. **System of record for physical-execution state on the workflow axis** — in-warehouse / in-transit / delivered / damaged / lost-in-custody location and movement at sub-warehouse granularity (row / rack / cellar zone — Logilize-internal); pick-pack-dispatch execution. The ERP-side inventory ledger lives in Module B per DEC-185 (Stage 8 restoration) — InboundBatch / StockPosition / Case / QuarantineRecord / Stocktake entity ownership; ATP source per allocation; receiving physical-match authority; stocktake variance computation; inventory-adjustment proposal-and-confirmation. Stream split (Stage 8 per DEC-188; supersedes DEC-140 on inventory-state axis): Module C owns 4 fulfillment streams; Module B owns 5 inventory-state streams. | §5.3, §11.3 |
| **Member (segment)** | Customer with at least one approved active membership; full access to that club's page, Discovery, and storage. | §2.1 |
| **Membership** | The relationship between a Customer (via a Profile) and a Club. Has a state machine: Applied → WaitingList → Approved → Active → Suspended/Lapsed/Cancelled. **Implementation note**: at Module K data-model level, Module K PRD v0.2 §3 collapses Membership and Profile to a single `Profile` entity (no separate Membership table); the BMD's Membership term remains as the business-level word for the Customer-Club relationship. The canonical event family is `Profile*` (`ProfileActivated`, `ProfileRenewed`, `ProfileSuspended`, `ProfileReactivated`, `ProfileExpired`) per Module K PRD §15. | §2.2 |
| **Mixed Package** | Producer-curated multi-product / multi-vintage selection sold as a single unit; the Hero Package is the canonical example (DEC-019 retained for clubs; DEC-061 amends to allow multi-producer composites on Discovery only). Constituents are tracked individually for cellar, voucher, and shipment-decomposition purposes; the package is the unit of sale. | §4.2, §4.3 |
| **MPV (Multi-Purpose Voucher) VAT** | EU VAT regime under which the Voucher's destination-country VAT events fire at redemption (shipment), not at sale. NewCo's VAT regime, inherited from Crurated v17. | §8.7 |
| **NewCo** | Working name for the new producer-club-aggregator venture being designed. Final brand name TBD. Separate venture from Crurated (DEC-001). | §1.1 |
| **NFC tag** | Near-Field-Communication chip applied to each serialized bottle by Vinlock under NewCo direction at warehouse receipt. At launch the tag's on-chip content includes the **Bottle Page URL + bottle serial + on-chain reference** (NFT-ID and / or Avalanche transaction hash) per DEC-124 — sufficient for offline / network-independent provenance verification. **Working hypothesis**: Paolo flagged the content shape for blockchain-expert revisit (validation-pending caveat across Wave 4 NFT/blockchain DECs — DEC-120 / DEC-121 / DEC-122 / DEC-124 / DEC-131); pre-launch tag-stock production lead-time may force a pre-validation lock. | §6.3 |
| **NFT** | Non-Fungible Token minted on Avalanche when the NFC tag is applied; acts as on-chain provenance / authentication artifact (not legal title at launch). Burned at shipment. | §6.5, §6.7, §6.10 |
| **Order** | Single transaction across one or more cart line items; can mix club and Discovery offers. | §4.7 |
| **Originating Club** | The specific **Club entity** (not Producer entity, per DEC-066) that first approves a Customer; locked at first approval via the one-shot `OriginatingClubLocked` event; persists at the Customer level for life. Drives the **5% × `P_d`** Discovery revenue share (DEC-032), settled to the Producer who operates the Originating Club. The link target is the Club so that multi-Club-per-Producer configurations remain disambiguable. **Emission timing**: `DiscoveryRevenueShareAccrued` fires at **INV1 issuance = post-payment-cleared** per DEC-112 (NOT at OrderPlaced; NOT at payment-authorized) — for card payments this is OrderCompleted; for bank-transfer payments this is funds-cleared at Airwallex (PENDING_PAYMENT → ISSUED transition). **Headline `P_d` rule**: the 5% is computed on the headline `P_d` (NOT post-stacking net price) per DEC-110 — preserves OC economic interest regardless of NewCo's discount discretion. **Persistence**: the share continues to accrue even after the Customer becomes Legacy w.r.t. that Club (i.e., after the first-approved Profile lapses or cancels). A Customer with no Originating Club may still purchase from Discovery (DEC-040); in that case no 5% recipient applies and the full Discovery margin accrues to NewCo. **Gifting preservation** (DEC-116): on a gifted voucher's eventual Discovery sell-through, the giver's locked Originating Club is the recipient of the share (the giving moment locked the OC reference per DEC-066). | §2.2, §8.14 |
| **Passive Consignment V1** | Sourcing model where stock stays at the producer's premises until needed for a customer order; NewCo never owns inventory pre-emptively. Used for very expensive / rare bottles. | §3.7, §5.1 |
| **Passive Consignment V2** | Sourcing model where stock is shipped to NewCo / Vinlock warehouse pre-emptively; sold from there. Default at launch. | §3.7, §5.1 |
| **Producer** | The entity that **makes** the wine — the wine identity ("who made it"). At NewCo, distinguished from Supplier (the commercial counterpart NewCo transacts with) — see Supplier (DEC-067). Most NewCo Producers are also their own Suppliers (1:1 SupplierProducerLink at launch); the separation handles edge cases (négociant arms, holding entities). At launch, only Producers operate Clubs; Suppliers operate Discovery allocations only. | §3.1 |
| **Producer Agreement** | The legal contract between NewCo and a Producer that records club terms, Hero Package design, allocation commitment, surface eligibility, settlement-cadence override, and producer discretion clauses (§3.3). Modelled as a first-class **`ProducerAgreement` entity** in Module K (DEC-070) with its own lifecycle (`draft → active → superseded → terminated`) and supersession chain. The agreement template itself is owned by Legal (Q-OQ-9); the BMD fixes the commercial substance the entity carries. | §3.3 |
| **Producer PO (Purchase Order)** | The internal financial obligation NewCo creates to the producer at sell-through (passive-consignment models only). **Commercially distinct** between surfaces (DEC-059): on **club** sales the PO is 87.5% × `P` (DEC-010); on **Discovery** sales the equivalent settlement is the per-allocation negotiated cost `C` per unit (DEC-032). **Direct-purchase allocations bypass the Producer PO at sell-through** (per §3.7 / DEC-063): the producer was paid in full at purchase, so direct-purchase batches do not generate sell-through-driven PO obligations. The data-model representation of the PO — single entity with a `purpose` flag vs two distinct entities — is a Module A / S PRD design call (DEC-059). | §3.6 |
| **Producer Portal** | Producer-side front-end surface used by producer staff to manage applications, allocations, and reporting. | §7.4 |
| **Profile** | A Customer's identity within a single Club. One Profile = one Membership = one Club. A Customer with multiple memberships has multiple Profiles. | §2.2 |
| **Refund-Cost Matrix** | Policy table allocating refund costs across Producer, Carrier, NewCo, and Customer (DEC-025). | §4.11 |
| **Seller of Record** | Legal status of NewCo on every customer-facing transaction; NewCo invoices the customer, owns customer-facing pricing, and holds compliance obligations. | §3.5 |
| **Sell-through** | The moment a unit of an Allocation is sold to a customer. For passive-consignment allocations (V1 / V2), sell-through triggers the `SellThroughRecorded` event and the per-bottle settlement obligation under the allocation's **commercial-terms shape** (DEC-092) — `fixed_per_unit` (settles at the negotiated per-unit value) or `percent_of_selling_price` (settles at value% × selling price; club default is the 12.5% / 87.5% split per DEC-010). The settlement obligation flows into the counterparty's settlement statement (§3.10). The mechanism is admissible across all sourcing × visibility combinations — the 12.5% / 87.5% is the club-default instance, not the only context. Direct-purchase allocations skip sell-through-driven settlement (the supplier was paid in full at PO issuance per DEC-063 / DEC-081 / §3.7) regardless of commercial-terms shape. | §3.10, §8.10 |
| **Settlement Statement** | NewCo-generated quarterly summary of producer's net commercial position, against which the producer issues an invoice. | §3.10, §8.10 |
| **Shipment** | The dispatch of one or more bottles from custody to a customer destination. Late binding selects specific bottles at this moment; NFTs are burned. | §5.5, §5.7 |
| **Store Credit** | Customer-level credit balance, applicable to any NewCo purchase. Issued for refunds, goodwill, customer-service interventions. | §4.12 |
| **Storage Fee** | The recurring per-bottle / per-year charge for custody after the first 12 free months. **Rate**: €3 / bottle / year = €0.25 / bottle / month (DEC-013, DEC-118). **Partial-month rule**: any partial month rounds up to a full month. **First 12 months FREE** from INV1 issuance; charges begin month 13. **Storage-clock-start double-anchor** (DEC-119): accrual begins at the later of `INV1 + 12 months` and the bound Allocation's `InboundEventPhysicallyAccepted` date — bottle must be at Vinlock for any part of an accruing month (collapses to `INV1 + 12 months` for V2 default; waits for physical arrival for V1 / Direct Purchase in-transit cases per DEC-081). **Issuance**: Module S owns storage-fee computation, INV3 issuance, and per-bottle accrual events under DEC-119 (supersedes DEC-118's Module-E-ownership clause; mechanics preserved). Issued semi-annually (end of June + end of December) via the dedicated **INV3** invoice type. Mid-semester shipments roll the in-semester storage charge into the bottle's **INV2** line items rather than the next INV3 cycle (Module-S-internal logic per DEC-119). Module E consumes Module S's invoice events, routes to Xero, and executes the Airwallex charge. | §2.7, §5.6, §8.4 |
| **Storage Service** | The recurring service offering NewCo provides for custody of customer-purchased bottles in the Vinlock-operated cellar (DEC-118 + Q-OQ-13 carve-out). **The only "service" operationalised at NewCo launch** — paid experiences and other services remain placeholders (§4.14). Storage is metered per bottle per month (after the first 12 free months and the bottle-at-warehouse condition per DEC-119) and billed via the dedicated INV3 invoice on a semi-annual cadence; INV3 is Module-S-emitted under DEC-119. | §2.7, §4.14, §5.6, §8.4 |
| **INV3** | Customer-facing service invoice for storage, introduced at NewCo launch under DEC-118. Issued by **Module S** (per DEC-119; supersedes DEC-118's Module-E-ownership clause; mechanics preserved) on a semi-annual cadence (end of June + end of December), aggregating the prior 6 months of `StorageFeeAccrued` events for each customer; Module E consumes the `InvoiceINV3Issued` event, routes it to Xero per DEC-072, and executes the Airwallex charge against the saved payment method. **Mid-semester carve-out**: when a bottle ships during a semester, its in-semester storage charge rolls into that bottle's INV2 line items rather than appearing on a separate INV3 — keeping all storage charges for a given bottle on exactly one customer-facing invoice (Module-S-internal logic per DEC-119). INV3 is net-new at NewCo: while Crurated's current ERP charges storage on an INV-3-equivalent line, the formal three-invoice typology (INV1 / INV2 / INV3) is locked at NewCo launch. | §2.7, §5.6, §8.4, §8.13 |
| **Two/Three-Invoice Mechanic (INV1 / INV2 / INV3)** | NewCo's customer-facing invoicing pattern at launch. **INV1** at checkout covers the bottle / Voucher amount only — no destination-VAT, no excise (DEC-045). **INV2** at shipment / redemption covers excise (pass-through per §8.6) + destination-country VAT (recognised under the MPV regime per §8.7) + shipping (DEC-045) + any unbilled storage months for the shipped bottle (mid-semester carve-out per DEC-118 + DEC-119). **INV3** at semester-end covers recurring storage services (DEC-118); calendar-cadence-driven, distinct from the order-event-driven INV1 / INV2. **All three customer-facing invoices are Module-S-emitted** under DEC-119; Module E consumes them, routes to Xero per DEC-072, and executes the Airwallex charge per DEC-014. The mechanic is the operational consequence of the MPV-Voucher model + storage-as-a-service launch carve-out and underpins the §8.13 event sequence; downstream accounting (Xero per DEC-028 / DEC-072) determines GL treatment from the events. | §4.7, §8.4, §8.6, §8.7, §8.13 |
| **Supplier** | Commercial counterpart that supplies wine to NewCo — the entity NewCo legally transacts with. May or may not be the wine's Producer; Suppliers can include négociants, distributors, or aggregators (e.g., Crurated as a Discovery supplier per DEC-020). Linked to one or more Producers via SupplierProducerLink (DEC-067). At launch, Suppliers operate Discovery allocations only; future flexibility may admit Supplier-operated Clubs. | §3.1 |
| **SDI (Sistema di Interscambio)** | The Italian e-invoicing system; NewCo connects via a third-party Xero-SDI connector. | §8.12, §11.6 |
| **Four-Way Reconciliation Discipline** | NewCo's launch ERP architecture rests on four orthogonal authorities each owning a distinct slice of bottle / inventory / commercial / financial state, reconciled through explicit cross-module event flows rather than silent overrides — Logilize (physical execution), Module B (ERP-side inventory ledger), Module S (commercial state), Module E (financial state). Restored at Stage 8 per DEC-185 + Module B v0.2 §2.4 (supersedes v0.8's three-way regime that concentrated both physical-execution and ERP-inventory-source-of-truth roles in Logilize). Reconciliation primitives: receiving (Module D = documents + Module B = physical match per DEC-194); stocktake (Module B Stocktake entity per DEC-189); adjustment (Module B `InventoryAdjusted` per DEC-190); QuarantineRecord gatekeeper (Module B "quarantine before trust" per DEC-191). Day-to-day load-bearing application = the **Two-Layer No-Overselling Guard** at hold-placement / voucher-issuance. | §6 (intro), §5.3, §11.3 |
| **InboundBatch** | Module-B-owned logical container for goods arriving from a single source (PO line or consignment receipt) per DEC-195. Attribute set: expected qty (from PO line), received qty (from Module B's physical-match check per DEC-194), serialization progress (`qty_planned_serialize` + `qty_actually_serialized` per DEC-186 NS counter set), ownership flag (per DEC-185 2-value enum at NewCo launch — `PRODUCER` / `CRURATED`), **cost-basis flow** (provisional at Module D `InboundEventPhysicallyAccepted` Phase 1 → finalized at `InboundEventCostFinalized` Phase 2 per v13 Stage 2.3 split-inbound lineage), serialization-plan target. The cost-basis is referenced at dispatch-time per Module C late-binding chain (DEC-142). For non-serialized inventory the InboundBatch is the load-bearing ledger entity (no SerializedBottle row); for serialized inventory it parents the SerializedBottle records derived from it. | §5.1, §9.8 |
| **InventoryAdjusted** | Module-B-emitted unified event for inventory adjustments per DEC-190, carrying an `adjustment_type` discriminator (`damage` / `loss` / `consumption` (Phase-2+ placeholder) / `recount` / `transfer` / `found`) + scope (bottle id for serialized / batch id + qty for non-serialized / case id for case-integrity adjustments per DEC-192). Proposal flow: operator-initiated (or stocktake-variance-derived per DEC-189; or QuarantineRecord-resolution-derived per DEC-191) → supervisor approval → terminal-state event emission. Module E consumes for damage / loss / write-off financial-event recording per DEC-072; Xero decides GL treatment per `feedback_bmd_prd_no_accounting`. | §5.9 |
| **InventoryShortfallDetected** | Module-B-emitted event when a proposed inventory adjustment would reduce committed inventory below outstanding vouchers per DEC-190 §13.4. The proposal is rejected; instead the event fires to Module A to drive the shortfall workflow (substitution per DEC-104 / replacement per DEC-138 / refund). Implements the committed-inventory protection guard (Q-CL-6 + DEC-185); the proposal cannot proceed until Module A `VoucherCancelled` first releases the commitment. | §5.9 |
| **Ownership Flag** | A 2-value enum at NewCo launch (`PRODUCER` / `CRURATED`) per DEC-185 + DEC-068 + DEC-001 (Q-CL-2 confirmation), tracked by Module B at the per-bottle (serialized) and per-InboundBatch (non-serialized) levels. **PRODUCER** = title held by the producer (passive consignment V1 + V2 pre-positioned + V1 in-transit pre-payment); **CRURATED** = title held by NewCo (Direct Purchase + post-supplier-payment passive consignment). The v17 third value `THIRD_PARTY` is OUT at NewCo launch — no agency intake (DEC-001), no third-party custody (DEC-185), no AgencyAgreement entity (Module B v0.2 §1.2 boundary). Ownership transitions are recorded by Module B emitting `OwnershipTransitioned`; Module E consumes for financial-event recording per DEC-072. The customer is never an `ownership_flag` value — the customer holds a Voucher (Module S off-chain record); customer-side title transfer follows the sale contract per BMD §9.8. | §9.8, §5.3 |
| **QuarantineRecord** | Module-B-owned entity per DEC-191 implementing the **quarantine-before-trust** principle: Module B never creates inventory records from unverified Logilize data; unknown entities reported by Logilize land in QuarantineRecord pending manual supervisor investigation. Resolution paths (each captured with supervisor identity + decision + reason + resulting mutation + timestamp): (1) associate with existing InboundBatch — serial / batch id mapping; (2) create new inventory record — supervisor confirms Logilize-emitted entity Module B did not anticipate; (3) reject as invalid — Logilize entity disregarded; provenance record kept; (4) escalate — queued for cross-functional review. Resolved QuarantineRecords are immutable post-resolution. Resolution-driven inventory state changes may emit `InventoryAdjusted` per DEC-190. | §5.3, §6, §9.8 |
| **StockPosition** | Module-B-owned aggregated view per DEC-196 at the canonical 5-dimension intersection — `(bottle_reference, warehouse, case_config, allocation, ownership)`. Reports `total_quantity`, `committed_quantity` (committed to vouchers — protected per Q-CL-6 + DEC-185), `available_quantity` (= `total − committed − reserved − quarantined − under_adjustment` per v17 §B.8 sub-pool ATP formula). Sub-pool decomposition: `available_serialized` + `available_non_serialized` per allocation (feeds DEC-187 ATP push to Module A). Sellable quantity feeds Module A + Module S storefront ATP read path under the lesser-of-(allocation-pool ATP, physical-inventory ATP) two-layer no-overselling guard; shippable quantity feeds Module C late-binding selection per DEC-137 + DEC-188. The 5-dimension aggregation is preserved without simplification — dropping `case_config` would lose case-integrity-aware ATP and break mixed-case unbreakable dispatch per DEC-192. | §5.3, §6, §9.8 |
| **Stocktake** | Module-B-owned entity per DEC-189 with a 4-state FSM (`planned → in_progress → variance_review → reconciled`). Scope at planning: warehouse / storage-location / Bottle Reference; target date; variance tolerance threshold (configurable per scope). Logilize executes the physical count via DEC-188 stocktake stream; Module B compares Logilize counts to the ledger and computes variances per scoped entity. Variance resolution emits `InventoryAdjusted` per DEC-190 with the supervisor's resolution discriminator (`damage` / `loss` / `recount` / `transfer` / `found`). **Stocktake-cadence policy is operator setting**, not BMD content (per `feedback_prd_rr_approval`); single-supervisor-approval discipline applied uniformly. Persona = Logistics / Operations Manager. | §5.3, §6, §9.8 |
| **Two-Layer No-Overselling Guard** | The day-to-day load-bearing application of the four-way reconciliation discipline, per Q-CL-5 + DEC-185 + DEC-187 + Module A v0.2 §7.1 + Module B v0.2 §10.5. At hold-placement / voucher-issuance time, two independent layers must each pass: **Layer 1 (Module A allocation-pool layer)** — `qty − issued ≥ 0` per DEC-099 (the supply-side commitment count); **Layer 2 (Module B physical-inventory layer)** — `physical_in_storage − reserved − quarantined − under_adjustment ≥ 0` per Module B v0.2 §10.5 + v17 §B.8 sub-pool ATP formula. Both layers are strongly consistent at the transactional boundary; failure of either rejects the placement. The two-layer discipline detects a class of failures that no single-layer guard would catch — Logilize-side count drift / batch confusion / serial mismatches that would let allocation-pool look healthy while physical inventory has shrunk underneath. Module S storefront display ATP reads the lesser of the two per Module S v0.2 §17.3 + Q-CL-5. | §6 (intro) |
| **Vinlock** | NewCo's third-party warehouse operator, France-based (same physical site as Crurated, separate contracts). | §5.2, §11.2 |
| **Voucher** | A customer's right to a specific Bottle Reference at a specific producer-set (or NewCo-set, for Discovery) price, redeemable for shipment. The unit of customer holding in the Cellar. **1-voucher-per-bottle invariant** (DEC-109): vouchers are bottle-granular at NewCo launch (one Voucher row per bottle, regardless of Offer granularity); a 12-bottle case purchase = 12 Vouchers; a multi-producer Discovery composite of N bottles = N Vouchers (one per constituent bottle per DEC-097). **Lifecycle = 8 states** (DEC-102): PENDING_PAYMENT (bank-transfer 7-day credit-terms pre-state per DEC-049 / DEC-101) → ISSUED → REDEMPTION_REQUESTED → SHIPPED → CONSUMED + VOIDED / EXPIRED / GIFTED. v17 ON_CRUTRADE state DROPPED at launch (no CruTrade marketplace per §4.4); v17 RESOLVED + BottlingResolution N:M reissuance + BOUGHT_BACK DEFERRED post-launch. EXPIRED is IN at launch with `Allocation.expiry_date` trigger (DEC-103); GIFTED covers transfer-pending phase (DEC-116). | §4.4, §9.7 |
| **Waiting-list applicant (segment)** | Customer who has applied to a Club but not yet been approved. No producer-page access; Discovery access only. | §2.1 |
| **Xero** | NewCo's accounting platform (with Italian SDI connector). | §8.12, §11.6 |

---

## Appendix B — Deltas vs Crurated v17

> Side-by-side reference for reviewers familiar with the Crurated ERP PRD v17: kept patterns, simplified patterns, removed scope, and net-new NewCo concepts, each with a brief reasoning. The BMD body is standalone — this appendix is the only place comparisons live.

This appendix is a side-by-side reference for reviewers familiar with the Crurated ERP PRD v17. The BMD body (§§0–13) is **standalone** — it does not depend on v17. This appendix collects the comparisons in one place so reviewers can quickly orient.

The Crurated v17 PRD is at [`../../01-PRD-current/Crurated_ERP_PRD_v17.md`](../../01-PRD-current/Crurated_ERP_PRD_v17.md) and is FROZEN. References are by section number only; v17 prose is never copied.

### B.1 Kept Patterns

Patterns retained from Crurated v17, with minor or no adaptation:

| Pattern | v17 reference | NewCo reference | Notes |
|---------|---------------|-----------------|-------|
| Voucher model with deferred fulfillment | v17 §1.4 / §2.4 | §4.4, §9.7 | Same conceptual shape; lifecycle simpler at NewCo (no ON_CRUTRADE). |
| Late binding (specific bottle assigned at shipment) | v17 §1.4 / §2.5 | §4.4, §5.5 | Same model. |
| Cart hold with timeout | v17 §2.3 | §4.6 | Same model; no "no-hold" alternate (Crurated's v11 simplification stands). |
| MPV (Multi-Purpose Voucher) VAT regime | v17 §0.7 | §8.7 | Same regime; deferred recognition until redemption. |
| Bottle Reference / Allocation / Voucher abstractions | v17 §1.4, §2.x | §4.x, §9.6, §9.7 | Same abstractions. |
| Two-identity stock (serialized + non-serialized) | v17 v13 Stage 2.4 | §6.1, §6.2 | NewCo retains both identities; serialization is default-on. |
| Producer ≠ Supplier separation | v17 v13 Stage 2.1 | §3.1 | Producer = wine identity; supplier = commercial counterpart. NewCo carries the same separation forward. |
| Split inbound acceptance (PHYSICALLY_ACCEPTED + COST_FINALIZED) | v17 v13 Stage 2.3 | §5.1 | 5-working-day cost-finalization SLA carried forward. |
| Storage as a service | v17 §2.x | §2.7, §5.6, §8.4 | NewCo uses different pricing (€3 / bottle / year) but same conceptual mechanic. |
| Layered breakability model (Wine Variant / Allocation / Offer) | v17 v13 Stage 2.5 | §4.2 | Producer + commercial breakability layers retained for case-level granularity decisions. |
| Composite SKU (multi-producer composite on Discovery) | v17 §1.4 | §4.3, DEC-061 | NewCo retains v17's multi-producer composite pattern **for the Discovery surface only**. Club mixed-cases stay single-producer (DEC-019 retained for clubs). |
| Multi-tier club model (data layer) | v17 (multi-tier club model) | §3.4, DEC-062 | NewCo's data model inherits v17's multi-tier shape under DEC-060; launch configures every club as single-tier. Multi-tier activation post-launch is configuration, not migration. |
| Customer-Profile model (one customer ↔ many profiles) | v17 §1.x | §2.2 | NewCo's Netflix-style profiles map to memberships, same as Crurated. |
| Damage / breakage / transit policy | v17 §2.x | §5.9 | Same handling pattern. |
| 14-day Italian / EU consumer-law withdrawal right (working baseline) | v17 §0.x | §4.10, §10.9 | Same baseline; full right assumed at launch with no carve-out reliance (DEC-057); Italian counsel review pre-launch is a non-blocking validation. |
| MPV-Voucher event sequence (INV1 at checkout / INV2 at redemption) | v17 §11 / §0.7 | §8.13 | NewCo emits the same INV1+INV2 event sequence; downstream accounting (Xero per DEC-028 / DEC-072) determines treatment per its own policy. |
| NFC + NFT serialization architecture (concept) | v17 §1.4 / §2.5 | §6.x | Same concept; different blockchain (Avalanche vs Crurated's chain). |

### B.2 Simplified Patterns

Patterns retained but simplified at NewCo:

| Pattern | v17 reference | NewCo simplification | Reason |
|---------|---------------|---------------------|--------|
| Voucher state machine | v17 §2.4 | No ON_CRUTRADE state (P2P trading not at launch) | CruTrade is OUT (DEC-008 envelope, L2). |
| Sourcing models | v17 §1.4 / §2.6 (allocation types × sourcing models 3×2) | Passive consignment V1 + V2 only; no active consignment | NewCo is consumer-only (DEC-017). |
| Customer segments | v17 (Members + B2B + Legacy + tiered memberships) | Three segments only: Member / Waiting-list / Legacy | No B2B (DEC-017); Waiting-list is new (see B.4). Note: tiered membership is a *launch configuration* simplification (single-tier per club), not a data-model removal — see B.1 row "Multi-tier club model (data layer)" + DEC-062. |
| Module shape | v17 8 modules (0/S/K/A/D/B/C/E) | Same 8 modules but simpler internals | Less business surface (no B2B / no active consignment / no liquid sales). |
| Cancellation policy | v17 (multi-jurisdiction) | Single jurisdiction (Italy) at launch | Simpler legal frame (DEC-015). |
| Consumer Portal scope | v17 (web + native intent) | Web + mobile-web only at launch | DEC-018. |
| AI / Operator Copilot | v17 §14 (deferred) | Deferred at launch (same posture) | DEC-021 — Crurated's deferral preserved. |

### B.3 Removed Scope

Items present in Crurated v17 that are explicitly removed at NewCo:

| Item | v17 reference | Reason removed |
|------|---------------|----------------|
| B2B / wholesale customers | v17 §0.x onwards | Consumer-only (DEC-017). |
| Active consignment | v17 §1.4 / §2.6 | No B2B counterpart for NewCo (DEC-011, DEC-017). |
| Active consignment invoicing (SELL_THROUGH_SETTLEMENT B2B) | v17 v13 Stage 2.6 | No active consignment. |
| Drop-shipping | v17 §2.x | Not at launch (L5). |
| Liquid voucher resolution + pre-bottling sales | v17 v13 Stage 2.2 | Out at launch (D6, L4). |
| CruTrade P2P integration | v17 (CruTrade integration model) | Out at launch (L2). |
| Multi-warehouse / multi-region logistics | v17 §2.x | Single warehouse at launch (E3). |
| Death / inheritance / corporate dissolution flow | v17 (had partial coverage) | Deferred at NewCo (K8, Q-OQ-11). |
| B2B credit terms | v17 §0.x | Consumer-only payment (DEC-017, D9). |
| Strategic-stocking PO overbuy sourcing | v17 (sourcing scenarios) | Not relevant under passive-consignment-only model. |
| Producer Club existing-ERP track | (separate Crurated track) | Superseded by NewCo greenfield (DEC-001). |

### B.4 New / Net-New for NewCo

Concepts not present (or not in this shape) in Crurated v17:

| Concept | NewCo reference | Why new at NewCo |
|---------|-----------------|------------------|
| **Hero Package = Membership Fee** | §2.3 | Structural primitive: annual membership cost = Hero Package price; # members = # Hero Packages; Club Credit when package value < cost. Unique to NewCo's producer-club model. |
| **Producer-Club Aggregator framing** | §1.1 | Crurated runs a direct fine-wine marketplace; NewCo's positioning is many producer-clubs + a Discovery layer on top. |
| **Three customer segments (Member, Waiting-list, Legacy)** | §2.1 | Waiting-list as a distinct customer segment with its own access rights (Discovery + storage but no producer-page access) is net-new at NewCo. Legacy concept exists at Crurated but with different mechanics. |
| **Discovery Tab as cross-producer marketplace** | §1.1, §4.5 | Crurated has its own marketplace; NewCo's Discovery is a deliberate cross-producer surface coexisting with producer-specific clubs. |
| **Allocation visibility flag (`CLUB_ONLY / DISCOVERY_ONLY`) with dual-listing pattern** | §4.5, DEC-023, DEC-076 | Same Bottle Reference can appear in club (lower price, members only) AND Discovery (higher price, all customers) at producer's discretion. Not modeled in Crurated. **Visibility enum is 2-value at launch** per DEC-076; dual-listing materialises as **sibling Allocation rows per visibility** (one CLUB_ONLY row + one DISCOVERY_ONLY row from a single producer commitment), not as a single `BOTH`-flagged row. The dual-listing concept is net-new at NewCo; the data realisation is row-multiplication. |
| **Originating Club + 5% Discovery revenue share** | §2.2, §8.14, DEC-008 / DEC-010 | Producer that first approved a customer earns 5% on every Discovery purchase by that customer for life. Net-new mechanic at NewCo. |
| **Asymmetric club-vs-Discovery commercial mechanic** | §3.6, DEC-010 (club), DEC-032 (Discovery) | Club sales: producer sets customer-facing price `P`; producer paid 87.5% × `P`; NewCo retains 12.5% × `P`. Discovery sales: NewCo and producer negotiate allocation cost `C` per allocation; NewCo sets customer-facing price `P_d`; producer paid `C`; NewCo gross margin `P_d − C`; 5% × `P_d` to buyer's Originating Club. The two surfaces use *structurally different* commercial mechanics — not just different percentage splits. |
| **Hybrid brand architecture (NewCo chrome + producer-branded club pages)** | §1.6 | Net-new framing; Crurated runs a single brand. |
| **Avalanche blockchain** | §6.4, DEC-014 | Crurated runs on a different chain; NewCo deliberately differs. |
| **Airwallex payments** | §8.9, DEC-014 | Crurated runs on Stripe; NewCo deliberately differs. |
| **Xero + Italian SDI connector** | §8.12, §11.6, DEC-028 | Differs from Crurated's accounting stack. |
| **Six-locale launch on Bottle Page + Consumer Portal** | §7.7, DEC-031 | EN + IT + FR + DE + JP + ZH; broader than Crurated's launch locale set. |
| **NFC tag application by Vinlock under NewCo direction** | §6.3 | Operationally different point of NFC application; NewCo does it at warehouse receipt. |
| **Hero-Package-driven dual-nature transaction** (membership state change + purchase order in one event) | §2.3 | Specific to the Hero Package = membership fee mechanic. |

---

This Appendix is the **only** place in the BMD where Crurated v17 is explicitly compared. The BMD body is standalone.

---

## Appendix C — Open Questions Register

> Open questions still deferred at launch in v0.2. Each entry: ID, question, source, current default if any, target resolution path. Items confirmed-deferred under DEC-058. Items that resolved into DECs in v0.2 (Q-OQ-1, 3, 4, 12, 13, 14, 15..32) have been removed from the active register; their resolutions are tabulated in §12.2.

The eight items below remain genuinely deferred. ID numbering is preserved (no renumbering) to keep cross-references in v0.1 and the Decision Register stable.

**Q-OQ-2 — Bottle Page customer-identity exposure**
- *Source*: F9.
- *Question*: Should the Bottle Page reveal customer identity in any form (e.g., "owned by John Doe" if the customer opts in)?
- *Default*: Anonymous public (DEC-024); confirmed-deferred (DEC-058).
- *Resolution path*: Locked anonymous; retained as historical anchor for any future re-evaluation.

**Q-OQ-5 — Customer support tooling**
- *Source*: J8.
- *Question*: Will NewCo deploy dedicated support tooling at launch (Zendesk / Intercom / custom)?
- *Default*: Deferred at launch; Admin Panel + email at launch (DEC-058).
- *Resolution path*: Re-evaluated post-launch on volume.

**Q-OQ-6 — Community features on Consumer Portal**
- *Source*: G5.
- *Question*: Should community features (forums, reviews, social, messaging) be at launch?
- *Default*: Deferred (DEC-058).
- *Resolution path*: Post-launch product roadmap.

**Q-OQ-7 — Producer-side communication features**
- *Source*: G7.
- *Question*: Producer-side messaging / communications to club members — at launch?
- *Default*: Deferred; minimal status notifications only (DEC-058).
- *Resolution path*: Post-launch enhancement.

**Q-OQ-8 — Services / experiences revenue mechanics**
- *Source*: D1, H1, H3.
- *Question*: Paid services / experiences (estate visits, masterclasses) — revenue model, seller of record, refund treatment?
- *Default*: Free experiences captured at launch (booking only); paid mechanics TBD (DEC-058).
- *Resolution path*: Operations + Finance; future BMD revision.

**Q-OQ-9 — 24-month producer agreement detail**
- *Source*: C3.
- *Question*: Full agreement template — terms, renewal, breach, IP, etc.
- *Default*: 24-month default term recorded; full template not in BMD scope (DEC-058).
- *Resolution path*: Paolo to share template separately; legal-counsel review.

**Q-OQ-10 — Persona profile for target customer**
- *Source*: B1.
- *Question*: Detailed customer persona (psychographics, behavioral markers, preferences).
- *Default*: Working description — male, 35–60, classical fine-wine collector (DEC-058).
- *Resolution path*: Paolo to upload persona document separately.

**Q-OQ-11 — Death / inheritance / corporate dissolution policy**
- *Source*: K8.
- *Question*: Customer death, inheritance handling, corporate dissolution — what happens to stored bottles, vouchers, club memberships?
- *Default*: Deferred at launch; case-by-case operational handling (DEC-058).
- *Resolution path*: Policy required as customer base ages; legal review.

---

The Open Questions Register is **alive** — items resolve to DECs (and disappear from this register) as Paolo signs off; new items get appended as drafting / review surfaces them. The 24 v0.1 opens that resolved in this revision are tabulated in §12.2 with their DEC mappings; their full content has moved to the Decision Register and is no longer maintained here.
