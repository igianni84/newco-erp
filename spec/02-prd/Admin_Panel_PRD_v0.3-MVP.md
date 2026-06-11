# NewCo ERP — Admin Panel (Operator-Surface / Workflow Product-Spec) PRD — v0.3-MVP

- **Version**: v0.3-MVP (Phase D re-baseline — **the 9th, thin Admin-Panel PRD**; the operator-surface / workflow product-spec layer over the 8 module backends). **The first time the Admin-Panel surface is specced — there is NO frozen v1.1 predecessor and NO cut-sheet** (§8).
- **Date**: 2026-06-08
- **Status**: **DRAFT — awaiting batch ratification (Paolo).** Held in `mvp/`; nothing promoted to `handoff/` until Phase E. **Net-new but thin** — a *composition + consolidation* of operator surfaces the 8 v0.3-MVP PRDs already expose, plus the genuinely-net-new cross-module consoles the manual-first defers created. **It re-specs no backend and takes no new scope cuts** (the surface contract is settled — Phase C item L, ratified Q1).
- **Owner**: Paolo (product + business — decides). Claude recommends.
- **Companion specs (the 8 module backends this surface operates — referenced, NOT duplicated)**: [`Module_0_PRD_v0.3-MVP.md`](Module_0_PRD_v0.3-MVP.md) · [`Module_K_PRD_v0.3-MVP.md`](Module_K_PRD_v0.3-MVP.md) · [`Module_A_PRD_v0.3-MVP.md`](Module_A_PRD_v0.3-MVP.md) · [`Module_D_PRD_v0.3-MVP.md`](Module_D_PRD_v0.3-MVP.md) · [`Module_S_PRD_v0.3-MVP.md`](Module_S_PRD_v0.3-MVP.md) · [`Module_B_PRD_v0.3-MVP.md`](Module_B_PRD_v0.3-MVP.md) · [`Module_C_PRD_v0.3-MVP.md`](Module_C_PRD_v0.3-MVP.md) · [`Module_E_PRD_v0.3-MVP.md`](Module_E_PRD_v0.3-MVP.md). **Each module PRD owns its entities / events / FSMs; this PRD names the *operator surface* over them.** The companion acceptance contract is [`../03-acceptance/Admin_Panel_Acceptance_v0.3-MVP.md`](../03-acceptance/Admin_Panel_Acceptance_v0.3-MVP.md).
- **Predecessor**: **NONE.** The Admin Panel had no v1.1 PRD — v1.1 treated it as an implicit **DEC-083 admin-parity mirror** and never specced it (§8). The closest prior artefact is the **read-only design-side reference** `greenfield/12-admin-panel/` (a Stage-8 operator-task / IA / journey / design-token exploration of the *full* target surface) — used here for **operator-task vocabulary only, mapped down to the MVP slice**; it is **not a predecessor and not scope to import** (§8, §6).
- **Authoritative scope brief**: master [`../00-method/Phase_D_Kickoff_Prompt.md`](../00-method/Phase_D_Kickoff_Prompt.md) **§6.D** (the four content blocks (a)/(b)/(c)/(d)) + **§3.P2** (Admin-first, self-serve-later) + **§6.E** (the floor). The spine: [`../01-triage/Phase_C_Reconciliation_v0.1.md`](../01-triage/Phase_C_Reconciliation_v0.1.md) **item L** (RESOLVED at ratification Q1 — the thin 9th PRD; the full surface → roadmap).
- **Methodology DECs binding this document**: **DEC-073** (product-spec layer — name the operator *capability* + the workflow contract, **NOT** the interface; **no UX/layout, no screens/forms/navigation/components/IA/design-tokens** — those are tech-implementation, deferred); **DEC-074** (self-contained — anchors restated inline; cite Phase C item L + master §6.D + the 8 module PRDs); **DEC-083** (admin-parity is a *backend* contract — the operator path is already functionally complete; only producer-facing write *UIs* are deferred, no backend capability cut); **DEC-072** (no accounting positions — Module E records, Xero decides GL); **DEC-141** (the shared Logilize discrepancy queue B+C); **D24** (Admin Panel KEEP — *more* load-bearing in a manual-first MVP); **L-PP / K-Q4** (exactly ONE producer write platform-wide); **D23** (Producer Portal read + full 7-section reporting KEPT); `feedback_prd_rr_approval` (the operator authority-tier / RBAC policy is admin-configurable + downstream — **not specced at the PRD layer**, §1.4).
- **What this document is NOT** (the hard boundaries — §1.2): **NOT a UX/layout spec** (DEC-073 — no screen layouts, form fields, navigation trees, component specs, IA wireframes, design tokens); **NOT a re-spec of the 8 module backends** (reference the module PRDs; do not restate their entities / events / FSMs); **NOT new scope** (the surface contract is settled — Phase C item L; flag genuine gaps, don't cut or invent); **NOT the full Admin-Panel surface** (that is a roadmap deliverable, master §5 #12 — write the thin MVP slice + the §6 seam).

---

## §0 MVP scope at a glance

> **Verdict: the Admin Panel is the launch's load-bearing operational surface — and this PRD is the THIN operator-surface / workflow product-spec that names *what operators can do* across the launch.** It is a **composition + a consolidation**, not a new backend: (a) it **inventories** the operator capabilities each of the 8 modules already exposes at launch (referencing the module PRDs — block (a), §3); (b) it **specs the genuinely net-new cross-module operator consoles** the manual-first defers created, that belong to no single module (block (b), §4 — **the substantive net-new content**); (c) it states the **producer-write boundary** crisply (exactly ONE producer write platform-wide; D23 read KEPT; consumer storefront exempt — block (c), §2); and (d) it names the **"full target surface" seam** so the MVP slice is explicitly a clean subset (block (d), §6). **Take no new scope cuts** — the surface contract is settled (Phase C item L ratified Q1). Seven facts converge:

1. **The manual-first MVP routed every producer/back-office write through the Admin Panel (P2) — and the D19/D4/D16/D14/D3/D15 defers made it *more* load-bearing, not less (D24).** v1.1 assumed a thin admin-parity mirror; the launch MVP makes the Admin Panel carry: Module E's operator-run settlement runs + manual INV3 dunning + bank-transfer reconciliation + FX-variance + Xero exception (D19/D4); Module B's manual stocktake / quarantine / discrepancy / adjustment (D16); Module C's manual returns/replacement + white-glove quotes + manual recall (D14/D3/D15); Module D's manual procurement / discrepancy ops. **The defers *increase* operator load → the operator surfaces are the floor the manual-first created** (Phase C item L; §4).

2. **Exactly ONE producer write platform-wide: Module K membership approve/decline (L-PP / K-Q4).** Every other producer/back-office write across A / D / S / B / C / E is **operator-driven via the Admin Panel** (zero producer writes in each — A §3.3, D §3.6, S §15, B §0.8, C §0.9, E §1.4). **No backend capability is cut** — DEC-083/115 admin-parity is a *backend* contract; only the producer-facing write *UIs* are deferred, built back post-launch on the same backend (P1 seam). **State the boundary crisply** (§2).

3. **Producer Portal read + full 7-section reporting (D23) is KEPT** (reads A / S / E / K — sell-through metrics, settlement projections, PO status, financial dashboards). **The consumer storefront / cellar / Bottle Page are EXEMPT** (self-serve KEPT — master §3): browse / buy / cart / checkout / cellar / cancellation (S), the cellar render + in-transit display (C), the Bottle Page (B). (§2.)

4. **The two substantive net-new consoles (§4 — the real content of this PRD):** **(i) the finance-ops console (E)** — the most load-bearing: operator-run settlement runs (compose the 5-section statement from the recorded settlement-input events + run Xero AP, D19); manual INV3 dunning / K-Hold placement (D4 manual-first); bank-transfer reconciliation (operator-fallback); FX-variance review; Xero exception / sync-failed retry queue; chargeback dispute-evidence (D21 auto-ingestion + operator step-4); the admin-configurable thresholds. **(ii) the shared Logilize discrepancy queue (B+C, DEC-141)** — the unified discrepancy surface both inventory (B) and fulfilment (C) feed. The other consoles (white-glove quote flow C/D3; returns/recall C/D14/D15; stocktake/quarantine/adjustment B/D16; procurement/discrepancy D) are the manual-first operator surfaces the defers created (§4.3–§4.6).

5. **The recording is whole at launch — the consoles COMPOSE from the recorded events; the engines are the roadmap.** The finance-ops console composes the settlement statement from events Module E records at launch (the D19 seam — Module E §4.7); the inventory consoles book through the kept integrity-core entities/events (the D16 seam — Module B §0.2); the returns console runs the kept DEC-184 FSM (the D14 seam — Module C §10.2). **The consoles surface the *manual-first* posture the defers created — they do not re-open the defers** (the defers are months-out automation, not floor; §4).

6. **This is the product-spec layer, NOT a UX/layout spec (DEC-073).** This PRD names the operator *capability* (what an operator can do + the workflow contract + the audit envelope), not the *interface* (no screens, forms, navigation, components, IA, design tokens). The `greenfield/12-admin-panel/` IA / journey / design-token / component-library work is a **read-only design-side reference for vocabulary**, not scope to import (§6, §8). The operator authority-tier / RBAC policy is **admin-configurable + downstream** (`feedback_prd_rr_approval`) — not specced here (§1.4).

7. **Net-new but THIN — honest calibration (master §8).** This is a *composition + consolidation* of operator surfaces the 8 PRDs already expose, plus the net-new cross-module consoles the defers created. **The finance-ops console (E) + the Logilize discrepancy queue (B+C) are the substantive net-new content; the rest is inventory + reference.** It does **not** manufacture a heavy backend spec, does **not** duplicate the modules, does **not** import the full target surface. The surface is *more* load-bearing because of the defers (D24) — said plainly — and the seam to the full surface is named (§6).

**The four content blocks (master §6.D) → this PRD's structure:**

| Block | Content | Where |
|---|---|---|
| **(a)** | The per-module operator-capability inventory (reference the module PRDs; don't re-spec) | **§3** |
| **(b)** | The net-new consolidated cross-module operator consoles (the substantive net-new content) | **§4** |
| **(c)** | The producer-write boundary (one write = K membership) + D23 read KEPT + consumer exempt | **§2** (stated early — it is the framing contract) |
| **(d)** | The "full target surface" seam (the north-star — short) | **§6** |

**The floor this surface holds in composition (master §6.E — the operator surfaces compose the floor the manual-first defers created):** the operator surfaces are *how* the manual-first floor chains are exercised at launch — the finance-ops console exercises tax-correct-invoicing recording + dual-record FX + sanctions-at-charge (E); the white-glove flow keeps INV2 tax-correctness even in the manual path (C — excise runs even in white-glove); the compliance-ops surfaces exercise KYC/sanctions/OFAC/Hold + GDPR (K); the stocktake/quarantine/adjustment surfaces exercise committed-inventory protection + quarantine-before-trust (B). **The Admin Panel does not own the floor — it is the operator surface through which the floor's manual-first arms are run; the floor is owned + verified in the module PRDs.** (§4, §5.)

---

## §1 What the Admin-Panel PRD is in the MVP

### §1.1 Purpose + altitude

The Admin Panel is **NewCo's single back-office operational surface** — the operator's home for every producer-facing and back-office write at launch (P2), plus the consolidated cross-module consoles the manual-first defers created. This PRD specs it at the **product-spec / workflow altitude**: for each operator capability, *what an operator can do*, *the workflow contract* (the sequence of operator actions + the events they drive + the gates they honour), and *the audit envelope* (`actor_role`). It is the **operator-surface layer over the 8 module backends** — the backends are specced in the module PRDs; this PRD does not restate them.

**Why a 9th PRD (Phase C item L, ratified Q1).** Every cut-sheet forwarded "final reconciliation in the Admin-Panel / Producer-Portal triage" to Phase C. The reconciled finding: the cross-module surface *contract* is consistent (one producer write platform-wide; all else operator-driven; consumer storefront exempt; D23 read KEPT; no backend cut) — **but the manual-first defers made the Admin-Panel surface materially more load-bearing than v1.1 assumed, and it had never been triaged as its own artefact.** Resolution: a thin 9th MVP Admin-Panel PRD (this doc); the *full* surface → roadmap (§6). **The litmus for this PRD:** a reader sees, in one place, **every operator capability the launch ships** (each linked to its owning module PRD) **+ the net-new cross-module consoles' workflow contracts** + the producer-write boundary + the seam to the full surface.

### §1.2 Module boundary — what this PRD does NOT do (the hard boundaries)

- **Does NOT write a UX/layout spec (DEC-073).** No screen layouts, form fields, navigation trees, component specs, IA wireframes, design tokens, page templates. It names the operator *capability* + the workflow contract, not the interface. *(The `greenfield/12-admin-panel/` IA-model / canonical-journeys / component-library / design-token work is a read-only reference for vocabulary — §6, §8 — not scope.)*
- **Does NOT re-spec the 8 module backends.** It references the module PRDs' L-PP / operator-surface / producer-write-treatment sections; it does not restate their entities, events, or FSMs. Where a workflow contract is named here, the authoritative spec is the owning module PRD (cited inline per DEC-074).
- **Does NOT add new scope.** The surface contract is settled (Phase C item L). No operator capability beyond what the 8 v0.3-MVP PRDs expose at launch; genuine gaps are flagged for Paolo (§9), not cut or invented.
- **Does NOT write the full Admin-Panel surface.** That is a roadmap deliverable (master §5 #12). This PRD writes the thin MVP slice + the §6 seam.
- **Does NOT own the floor or take accounting positions.** The floor chains are owned + verified in the module PRDs (master §6.E); this surface is *how* their manual-first arms are run (§0). Module E records financial events; Xero decides GL (DEC-072).

### §1.3 The audit envelope (`actor_role`) — the one cross-cutting discipline this surface owns

Every operator action through the Admin Panel carries the standard audit envelope (DEC-083): **`actor_role` + actor identity + timestamp + the action + the entity reference**, recorded on the event the action drives. At launch every back-office write carries **`actor_role: newco_ops`** (the operator acting on a producer's or the business's behalf); the **one** producer write (membership approve/decline) carries **`actor_role: producer`** when exercised from the Producer Portal, or `newco_ops` when an operator runs it on the producer's behalf (DEC-115 parity). **This `actor_role` discipline is the audit/retention floor's operator-surface arm** (Phase C floor chain 6 — composed with K's GDPR + E's 10-yr retention). Discovery Offer operations are always `actor_role: newco_ops` (Admin-Panel-only; Module S §15.2). The literal envelope schema is tech (DEC-073); the PRD commitment is that **every operator action is attributable + audited**.

### §1.4 Personas + authority tiers — role-agnostic at the PRD layer (downstream per `feedback_prd_rr_approval`)

The Admin Panel serves the operator personas the 8 modules name — Catalog Operator/Reviewer/Lead (0), Customer-Care / Onboarding / Compliance / Producer-Onboarding Operators (K), Allocation Operator (A/S), Discovery Curator (S), Procurement Operator (D), Logistics/Operations Manager + Operations Operator + Wallet Operator (B), Fulfilment / Customer-Care Operator (C), Finance Manager / Analyst / Operations (E). **At the PRD layer the surface is role-agnostic: every capability is a back-office operator action; the authority-tier / RBAC / persona-gating policy (which role may run which capability, the approval-tier enum) is admin-configurable + downstream (`feedback_prd_rr_approval`) — decided after the prototype lands, when launch staffing is concrete.** This matches the modules' own posture (Mod0-Q2 / K-Q3 — the 3-step approval *role-count* is admin-configurable, no spec change). **The one PRD-level discipline preserved regardless of role configuration: the spec-mandated multi-actor patterns** (§5.2) — self-approval is never allowed where the spec requires distinct actors.

---

## §2 The producer-write boundary + the producer-portal read + the consumer exemption (block (c))

> **This is the framing contract for the whole surface — stated first because every capability in §3–§4 sits on one side of it.** The boundary is settled (Phase C item L, ratified Q1; L-PP / K-Q4); **no backend capability is cut** — only producer-facing write *UIs* are deferred (P1 seam: built post-launch on the same DEC-083/115 backend).

### §2.1 Exactly ONE producer write platform-wide

**The single retained producer write across the entire platform is Module K membership approve/decline** (incl. waitlist approval — Module K §3.1 / §4.2.1 / §13.5). A minimal producer approve/decline surface ships at launch; the richer "waitlist review" UX is post-launch Producer-Portal scope. **Every other producer/back-office write is operator-driven via the Admin Panel** (`actor_role: newco_ops`):

| Module | Producer/back-office writes (all operator-driven via Admin Panel at launch) | Producer writes retained | Owning PRD |
|---|---|---|---|
| **0 (PIM)** | Catalog entity create/review/approve/retire; Format / Case-Config governance; bulk import; enrichment-metadata update | **0** | Module 0 §2, §4.2, §5 |
| **K (Parties)** | Producer-initiated invitation; Hero-Package designation; capacity adjustment; ProducerAgreement drafting; Club config; producer record + KYC | **1 — membership approve/decline (the one, L-PP)** | Module K §3.1 |
| **A (Allocation)** | Allocation creation / activation / all mid-life mutations / recall / close / retire | **0** | Module A §3.3 |
| **D (Procurement)** | PI creation; PO lifecycle; issuance-gate override; cost-finalization; producer-initiated recall; SupplierProducerLink | **0** | Module D §3.6 |
| **S (Sales)** | Club-Offer authoring (create/publish/FSM/Hero/Layer-3/promo overlay); Discovery curation (Admin-Panel-only) | **0** | Module S §15 |
| **B (Bottle/Stock)** | Stocktake; quarantine; adjustment; recall recording; destruction recording; NFC re-tag authorisation | **0** | Module B §0.8 |
| **C (Fulfilment)** | SO supervision; pick/pack/dispatch; discrepancy; white-glove quote; returns/replacement; recall reverse-logistics; carrier/excise config | **0** | Module C §0.9 |
| **E (Finance)** | Settlement composition; INV3 dunning; bank-transfer reconciliation; FX-variance; Xero exception; chargeback dispute-evidence; thresholds | **0** | Module E §1.4 |
| **Platform total** | | **1** | — |

**Why no backend capability is cut.** DEC-083 admin-parity (extended to Offer-level ops by DEC-115) is a *backend contract* — every operation is exposable from both the Admin Panel and the Producer Portal at the backend level, carrying `actor_role: producer | newco_ops`. At launch the Admin-Panel side is exercised (`newco_ops`); the producer-facing write UI is deferred and **builds post-launch on the same backend** (P1 seam). The operator path is already functionally complete — this is the cleanest L-PP application of the triage.

### §2.2 The Producer Portal read + full reporting (D23) — KEPT

The **Producer Portal read + full 7-section self-serve reporting is KEPT at launch** (D23 — core to the producer promise; real-time). It reads across A / S / E / K: sell-through metrics per allocation (A/S), settlement projections (A/D/E), PO status — units shipped / in-transit / received (D), financial dashboards (E), membership + club state (K). **The Producer Portal is read-only at launch except for the one write (membership approve/decline).** The reporting *backend* is owned by the producer-reporting contract (D23); this surface notes that the read is KEPT whole — the deferral is producer *write UIs*, never producer *reads*.

### §2.3 The consumer storefront / cellar / Bottle Page — EXEMPT (self-serve KEPT)

**The consumer-facing surfaces are EXEMPT from L-PP (master §3 — self-serve KEPT):** the consumer storefront (browse / buy / cart / checkout / cellar / 14-day pre-shipment cancellation — Module S), the cellar render + in-transit voucher display (Module C — the customer's private authenticated space), the Bottle Page (Module B — the one customer-facing read, zero customer identifiers per DEC-024). **These are not Admin-Panel surfaces** — they are consumer self-serve, KEPT whole. *(Customer-Care operators act on consumer state from the Admin-Panel side — voucher substitution, supervisor-override refund, pre-shipment cancellation on the customer's behalf — but the consumer's own self-serve surfaces are exempt; §3.S, §4.)*

---

## §3 The per-module operator-capability inventory (block (a))

> **Reference, not re-spec.** For each module, the operator capabilities it exposes at launch — drawn from each PRD's L-PP / operator-surface / producer-write-treatment sections — **pointing to the module PRD** (the backend is specced there; this surface names the *operator capability* over it). The litmus: a reader sees, in one place, every operator capability the launch ships, each linked to its owning module PRD. **Cross-module capabilities (composed surfaces) carry a §4/§5 pointer.** Frequency/shape annotations are mapped down from the `greenfield/12-admin-panel/` operator-task inventory (the full-target reference) to the MVP slice.

### §3.0 Module 0 (PIM) — the catalog console

The catalog operator surface runs the pluggable-adapter / manual-baseline creation workflow + the uniform 4-state lifecycle (`draft → reviewed → active → retired`) with the 3-step Creator → Reviewer → Approver approval on every entity. **Zero producer writes.** → Module 0 §2 (personas), §4 (lifecycle governance), §5 (creation/enrichment).

| Operator capability | Workflow contract (the operator surface) | Composed? | Owning PRD |
|---|---|---|---|
| Create a catalog entity | Via the Product-Type enrichment adapter (`WINE` = LWIN/Liv-ex) **or the manual baseline path** (the launch default — manual-first enrichment, Mod0-Q3); validation + dedup either way | — | §5.1–§5.3 |
| Review + approve an entity | 3-step Creator → Reviewer → Approver (**role-count admin-configurable, Mod0-Q2**; self-approval never allowed); `*Activated` on `reviewed → active` | YES (Producer-active gate spans K → §5.2) | §4.2, §4.3 |
| Retire / re-activate an entity | Operator-driven cascade retirement (retirement-blocked-by-active-references; existing refs run to completion); 3-step lifecycle | YES (cascade spans A/S/B) | §4.5–§4.7 |
| Run a bulk import | Operator-run; partial-failure error log triages by upstream entity (Producer / Format) — basic bulk at launch (D9) | — | §6 (bulk import) |
| Govern Format / Case Configuration | Operator administers reference data through the standard approval workflow | — | §3.5, §3.6 |
| Update enrichment metadata | Operator updates critic scores / tasting notes / market data (observational; off the critical path — Mod0-Q3) | — | §5 |

### §3.K Module K (Parties) — the onboarding + compliance-ops console

The registry's operator surface runs onboarding flows, KYC, sanctions screening, the unified Hold mechanism, suspension/offboarding cascades, and GDPR. **The one platform-wide producer write lives here (membership approve/decline).** → Module K §2 (personas), §3.1 (producer-write treatment), §9 (KYC/sanctions), §4.8 (Hold), §8.2 / §12 (GDPR).

| Operator capability | Workflow contract (the operator surface) | Composed? | Owning PRD |
|---|---|---|---|
| **Membership approve/decline** | **The ONE producer write (L-PP / K-Q4)** — exercisable from the Producer Portal (`actor_role: producer`) or operator-run (`newco_ops`); incl. waitlist approval | — | §3.1, §4.2.1, §13.5 |
| Onboard / edit a Customer | Direct registration / Club-link / producer-initiated invitation (the invitation is operator-driven — K.31); address + payment-method-reference management on Account | — | §7, §4.1, §4.7 |
| Manage a KYC verification | The four-state KYC lifecycle; enhanced-KYC threshold review (**compliance floor**) | — | §9.1 |
| Review a sanctions-screening match | The four-state sanctions lifecycle; the order-completion-gate review queue (the gate fires at Module S, DEC-113) | YES (gate at S → §5) | §9.2, §9.3 |
| Place / lift a Customer Hold | The unified Hold entity (scope: Customer / Account / Profile); **the cross-cutting compliance action** — composes with DEC-181 uniformity at every transaction-initiation surface; **manual K-Hold placement is the D4 INV3-dunning arm (§4.2) + the D21 chargeback arm (automated trigger)** | YES (gates S/C/E → §4.2, §5) | §4.8, §10 |
| Run suspension / producer-offboarding | The suspension model; `ClubSunset` cascade (Profiles + Club-Credit conversion + voucher integrity) | YES (cascades to S/B/C/E) | §10 |
| Handle a GDPR data-subject request | Access / portability / **right-to-erasure** approval (soft-delete + anonymisation; **GDPR floor**, floor chain 6) | YES (anonymisation × active-Hold; HubSpot sync) | §8.2, §12 |
| Producer onboarding | Create/edit Producer; manage Producer KYC; draft + activate ProducerAgreement (the D19 settlement-cadence seam); Club config; Producer-activation approval (3-step, Catalog Lead) | YES (Producer-active gate → 0; agreement → D/E) | §4.4, §4.6, §4.3 |
| Operator-driven producer writes | Invitation send; Hero-Package designation; capacity adjustment (all operator-driven, producer UI deferred) | — | §3.1 |
| Marketing-consent review | Consent-state review for audit; right-to-object; HubSpot coordination | — | §8.1 |

### §3.A Module A (Allocation) — the allocation-ops console

Every allocation operation is a producer/back-office write → operator-driven via the Admin Panel; **zero producer writes, no backend cut** (DEC-083 parity is a backend contract). → Module A §3.3.

| Operator capability | Workflow contract (the operator surface) | Composed? | Owning PRD |
|---|---|---|---|
| Create an allocation | On a producer's behalf; PR-active + Producer-active/KYC gates; SupplierProducerLink read; two-FK lineage | YES (gates span K/0) | §3.3, §5.3.1, §4.1 |
| Publish an allocation (DRAFT → ACTIVE) | **Operator-publish post-PO-commit, uniform** (DEC-183 — the R1 framing; `SupplierPaymentCompleted` is financial-event-only, no FSM-activation role) | YES (post-PO-commit → D) | §3.3, §5.3.2 |
| Mid-life mutations | Visibility (CLUB ↔ DISCOVERY) / qty / commercial_terms / counterparty / sub-pool / opt-out; anti-orphan rules; Hero capacity invariant; waitlist conversion | YES (cut across K/S/D/E) | §3.3, §5.3.3–§5.3.8 |
| Trigger a producer recall | Operator-driven (already admitted operator-side, DEC-090); five-module cascade (A → D → C → B → S) | YES → §4.4 | §3.3, §5.3.7 |
| Close / retire an allocation | Operator-driven | — | §3.3, §5.3.9–§5.3.10 |

### §3.D Module D (Procurement) — the procurement-ops console

The procurement spine (PI → PO → InboundEvent → discrepancy) is operator-driven; **zero producer writes.** The manual procurement/discrepancy surfaces are the net-new console at §4.6. → Module D §3.6.

| Operator capability | Workflow contract (the operator surface) | Composed? | Owning PRD |
|---|---|---|---|
| Issue a PO | DRAFT → ISSUED → … → CLOSED; the two-level issuance gate (reads ProducerAgreement + Allocation state) | YES (gate spans K/A) → §4.6 | §3.6, §6 |
| Override the PO Level-1 gate | `POIssuedUnderNonActiveAgreement` — operator override + audit event for compliance review | YES (audit → K) → §4.6 | §3.6 |
| Accept inbound goods (3-gate QC) | **Module D = documents-in-order** (paperwork / agreement / PO-line conformance; pass fires `InboundEventPhysicallyAccepted`); **Module B = physical match** (DEC-194 split) | YES (split with B) → §4.5/§4.6 | §3.6 (and Module B §11) |
| Finalise inbound landed cost | The 5-WD cost-finalization; provisional → finalized flip (flows to B InboundBatch + E `COGSAdjustmentRecorded`) | YES (B/E) → §4.6 | §3.6 |
| Resolve an inbound discrepancy | The DISCREPANCY state + the **6-path resolution enum** (Accept Shortage / Return+Reorder / Return for Credit / Adjustment / Supplier Replacement / Write-Off); **manual-first round-trip (N1 — operator opens + records within the 5-WD window)** | YES (B feed-back) → §4.5/§4.6 | §3.6 (and Module B §11.2) |
| Record a producer-initiated reverse inbound | Operator-driven mirror (DEC-090/152); event-record-only at launch | YES → §4.4 | §3.6, §9 |
| Link a Supplier to a Producer | SupplierProducerLink creation (back-office, no auto-link) | — | §3.6, §10 |

### §3.S Module S (Sales) — the offer-authoring + customer-care console (the largest operator surface)

The commerce operator surface is the customer-care daily home + the offer-authoring surface. **Zero producer writes** (identical to A/D); the consumer storefront is exempt (§2.3). → Module S §2 (personas), §15 (Offer parity), §12 (cancellation/refund), §11.5 (substitution).

| Operator capability | Workflow contract (the operator surface) | Composed? | Owning PRD |
|---|---|---|---|
| Author a Club Offer | Create / submit / publish (the 5-rule validation) / pause / close / promo-overlay (producer opt-in, DEC-039) / Hero designation / Layer-3 / granularity / time-window / eligibility — **Admin-Panel-driven at launch** (producer write UI deferred — L-PP) | YES (bound Allocation FSM → A) | §15.1, §4.2, §7 |
| Curate a Discovery Offer | **Admin-Panel-only** (no producer write exists — DEC-115 carve-out); set `P_d`, granularity, eligibility, promo (NewCo discretion); **single-producer at launch** (multi-producer composite deferred — D7) | YES (constituents → A/0) | §15.2, §6 |
| Review a sanctions/Hold match at order completion | **THE consumer-side enforcement point** (S §10.1, DEC-113) — review Customers in `under_review` for the order-completion gate | YES (reads K) → §5 | §10.1 |
| **Customer Care: refund a customer order** | Records the refund + cause; offers store-credit-105% by judgment via the REFUND_COMPENSATION coupon (**D6 manual-first decisioning**; the legal floor + the mechanism KEPT) | **YES — the canonical composed surface (K+S+C+E) → §5.1** | §12.5, §12.1 |
| Customer Care: supervisor-override post-delivery refund | The rare exceptional refund (`SupervisorOverridePostDeliveryRefund`; supervisor identity + reason + amount) — **multi-actor: initiator ≠ supervisor** | YES (S+C+E) → §4.4 | §12.3 |
| Customer Care: manual voucher substitution | The DEC-104 substitution (passive-consignment-rare); coordinates customer notification | YES (S+B+A) | §11.5 |
| Customer Care: pre-shipment cancellation | The 14-day pre-shipment cancellation surface (legal floor KEPT) | — | §12.1 |
| Issue the INV3 storage-invoice cycle | Semi-annual INV3 issuance; the failed-charge escalation is **manual-first at launch (D4)** — the finance-ops dunning arm | YES (E/K) → §4.2 | §14 (and Module E §3.3) |
| Investigate a stuck voucher | Voucher state × shipping state × inventory join (lookup + exception) | YES (S+C+B) → §5 | §11 |

### §3.B Module B (Bottle/Stock) — the inventory-integrity console

Module B is back-office / warehouse-ops; **no producer writes, no consumer self-serve writes** (the one customer-facing surface is the read-only Bottle Page — exempt). The manual stocktake/quarantine/adjustment surfaces + the Logilize discrepancy queue B-side are the net-new consoles at §4.1/§4.5. → Module B §0.8, §11–§15, §20.1.

| Operator capability | Workflow contract (the operator surface) | Composed? | Owning PRD |
|---|---|---|---|
| Plan + run a stocktake | Supervisor-planned count campaign; **operator-scheduled manual counts + manual variance review (D16 manual-first)**; book variances through the adjustment path; `StocktakeReconciled` | YES (A/E) → §4.5 | §12 |
| Approve an inventory adjustment | Operator proposal → single-supervisor approval (**multi-actor: proposer ≠ supervisor**); **committed-inventory protection is FLOOR** (reject if it breaches outstanding vouchers → `InventoryShortfallDetected` to A) | YES (A/E) → §4.5 | §13 |
| Triage a QuarantineRecord | Quarantine-before-trust gate; supervisor resolves via 4 paths (associate / create-new / reject / escalate); **automated cascades manual-first (D16)**; resolved records immutable | YES (D/C/A) → §4.1/§4.5 | §14 |
| Handle a receiving physical-match discrepancy | The DEC-194 physical-match check → `InboundBatchDiscrepancy` to D; **manual-first round-trip (N1 — identical with Module D)** | YES (D) → §4.1/§4.6 | §11.2 |
| Authorise an NFC re-tag / record destruction | NFC re-tag authorisation (custody damage — rides the D12 decouple); destruction recording (`damage` adjustment → E financial event) | YES (E) | §17, §13 |

### §3.C Module C (Fulfilment) — the fulfilment-ops console

Module C is fulfilment ops + two consumer reads (cellar render + in-transit display — exempt); **zero producer writes.** The white-glove quote flow, the returns/recall consoles, and the Logilize discrepancy queue C-side are the net-new consoles at §4.1/§4.3/§4.4. → Module C §0.9, §4, §6, §7, §10, §12.

| Operator capability | Workflow contract (the operator surface) | Composed? | Owning PRD |
|---|---|---|---|
| Supervise the SO + pick/pack/dispatch | The 5-state SO FSM; the 4 Logilize fulfilment streams (pick / dispatch / delivery + customs-doc); pickup-handover recorded via Admin Panel (sanctions/Hold re-read at handover, DEC-181) | YES (S/B) | §2, §4, §5 |
| Resolve a pick-time discrepancy | Logilize `manual_review`: serial / quantity / batch mismatch + breakage-at-pick; `DiscrepancyResolutionRecorded` clears it | **YES → §4.1 (Logilize queue C-side)** | §4.4 |
| Approve a white-glove destination shipment | The Tier-2 manual fallback (complex / high-excise / US-state destinations); manual carrier quote; **OFAC + INV2-excise FLOOR even in the manual path** | **YES → §4.3** | §7, §6.1 |
| Enter a manual shipping-fee quote | The manual carrier-quote path (`quote_origin = manual`) — the D3 white-glove enabler | — → §4.3 | §6.1 |
| Adjudicate a returns/replacement claim | The DEC-184 FSM (REPORTED → INVESTIGATED → APPROVED → REPLACEMENT_ISSUED → CLOSED + REJECTED/WITHDRAWN), **operator-run end-to-end (D14 manual-first)**; original-voucher-preserved + no-cash-refund discipline | **YES → §4.4** | §10 |
| Coordinate a recall reverse-shipment | Operator-driven mirror (D15 minimal/manual); `ReverseShipmentDispatched`; unsold-only (ISSUED immune) | **YES → §4.4** | §12 |
| Configure carrier / excise / DDP-DAP rule matrices | Operator-managed rate/eligibility matrices (the D3 manual-first floor; expansion deferred) | — | §6.1, §7, §8 |

### §3.E Module E (Finance) — the finance-ops console (the most load-bearing — detailed at §4.2)

Module E is back-office finance; **zero producer writes, zero consumer self-serve writes — the cleanest L-PP (there is no write UI to defer, so no backend capability is cut).** The D4/D19 defers *increase* operator load → the finance surfaces are **more** load-bearing at launch (D24). The full console is the net-new content at **§4.2**. → Module E §1.4 (personas), §3.2 / §3.3 / §4 / §6 / §7.

| Operator capability | Workflow contract (the operator surface) | Composed? | Owning PRD |
|---|---|---|---|
| Run a settlement composition + Xero AP | **D19 manual-first** — compose the 5-section statement from the recorded settlement-input events + run Xero AP manually (the engine deferred; **the recording is the seam**) | YES (A/S/K) → §4.2 | §4.4, §4.7 |
| Run manual INV3 dunning / place K-Hold | **D4 manual-first** — drive the retry / `StoragePaymentFailed` / K-Hold-placement / Suspension chain manually on the first storage cycle (months out) | YES (K/S) → §4.2 | §3.3 |
| Reconcile a bank transfer manually | The DEC-159 operator-fallback (webhook fails / no auto-match) → operator-confirmed match → `BankTransferFundsCleared` | YES (S) → §4.2 | §3.2 |
| Review an FX variance / Xero sync failure | FX-variance review; the Xero sync-failed retry queue + reversal-ordering escalation (Finance Manager) | — → §4.2 | §7.1, §7.2 |
| Handle a chargeback dispute (step 4) | **D21 KEPT — auto-ingestion + auto-Hold automated**; the operator submits dispute evidence per the 7-BD SLA (step 4, operator by spec) | YES (K/S) → §4.2 | §6.1 |
| Configure finance thresholds | Admin-configurable: dunning cadences, retry windows, FX buffer %, refund-compensation premiums, Hold-lift authority | — → §4.2 | §1.4, §7.1 |

---

## §4 The net-new consolidated cross-module operator consoles (block (b)) — the substantive net-new content

> **This is the real net-new content of this PRD — the consoles the manual-first defers created, that belong to no single module.** Each composes the operator surfaces of two-or-more modules into one triage/workflow surface. **The two substantive ones are §4.2 (the finance-ops console, E) and §4.1 (the shared Logilize discrepancy queue, B+C);** the rest (§4.3–§4.6) are the manual-first operator surfaces the defers created. **The recording / integrity-core is whole at launch — the consoles compose from the recorded events; the engines + automation are the roadmap (§6).** These consoles **reference** the owning module backends — their downstream behaviour (the FSM transitions, the event emissions, the financial-event recording) is specced + verified in the owning module PRD + acceptance doc; **this surface specs the Admin-Panel-side workflow contract only.**

### §4.1 The shared Logilize discrepancy queue (B + C — DEC-141) — a substantive net-new console

**The unified discrepancy surface both inventory (B) and fulfilment (C) feed** (DEC-141 — a named B↔C contract). When a Logilize event contradicts NewCo's commercial state OR NewCo's inventory ledger, the discrepancy appears in **one NewCo Admin Panel "Logilize discrepancy" queue**, shared across both modules.

**The console's workflow contract (the Admin-Panel-side):**
- **The C-side feeds it** (fulfilment-side discrepancies — Module C §4.3/§4.4): pick discrepancies — serial mismatch / quantity mismatch / batch mismatch / breakage-at-pick — raised on the late-binding pick when Logilize's Stream-2 pick-confirmation contradicts the Allocation pool or commercial state; the SO's `manual_review` sub-state flag signals an in-progress C-side discrepancy.
- **The B-side feeds it** (inventory-state-side discrepancies — Module B §15.3): QuarantineRecord resolution, `InboundBatchDiscrepancy` flow-back, stocktake variance review — the manual-first D16 workflows land here.
- **The operator triages both kinds in one surface;** resolution events are recorded **in the appropriate module per the B/C boundary** (bottle-state → C streams; inventory-state → B streams). On a C-side resolution, `DiscrepancyResolutionRecorded` fires (resolution type + operator identity) and `manual_review` clears; on a B-side resolution, the QuarantineRecord / adjustment / stocktake path records in B.
- **Reconciliation is real-time event-driven** (no batch jobs at launch).

**Why it is net-new + load-bearing.** It belongs to no single module — it is the *shared* triage surface the manual-first inventory + fulfilment ops both need (DEC-141; the D16 manual-first workflows land here — Module B §0.2). The R3 contract context: Module C owns 4 fulfilment streams; Module B owns 5 inventory-state streams (B1 storage-location migrated from C + B2–B5 net-new) — the queue spans both stream families. **The Admin-Panel PRD specs the shared-queue surface; the reconciliation algorithm + the queue UX are tech (DEC-073); the downstream resolution events are owned + verified in Module B §15.3 + Module C §4.3/§4.4.**

### §4.2 The finance-ops console (E) — the most load-bearing net-new console

**The finance-ops console is the single most load-bearing net-new operator surface of the launch** — the D4/D19 defers routed the settlement + dunning load through it, and the recording-is-the-seam discipline means the console *composes from events Module E records at launch* (the engine is the roadmap). It carries six operator sub-surfaces (all `actor_role: newco_ops`; Module E §1.4 personas — Finance Manager / Analyst / Operations):

**(a) Operator-run settlement runs (D19 — the headline defer; Module E §4.4 / §4.7).** At launch the operator **composes the first producer settlement statement(s) manually from the recorded settlement-input events + runs Xero AP manually.** The five-section statement (A per-Club sell-through / B Discovery sell-through aggregate-only / C refunds+clawbacks netted / D OC shares aggregate-only / E Direct-Purchase informational-idle) is composed by the operator from the events Module E records in real time at launch: the E-emitted `SupplierPaymentCompleted` (R4), D's `InboundEventCostFinalized`, S's `DiscoveryRevenueShareAccrued` (the OC accrual) + cause-tagged refunds + reversals, C's NonRevenueCost triggers, B's `InventoryAdjusted` + cost-basis. **The recording is whole at launch (Module E §4.7) — the console composes from it; the settlement *engine* (the quarterly runs, the statement FSM, the OC 5% aggregation, the clawback netting, the Xero AP routing) is the roadmap.** The first quarterly close lands months post-launch (the defer is safe — Phase C item E confirms the capture whole). The Section-D info-disclosure constraint (DEC-180 — aggregate-only, no per-purchase-buyer detail) is preserved on the recorded accrual payload. **No accounting position (DEC-072) — Xero decides GL.**

**(b) Manual INV3 dunning / K-Hold placement (D4 — manual-first; Module E §3.3).** The first INV3 storage-billing cycle lands **months post-launch** (storage accrues only after the 12-month-free + bottle-at-warehouse double anchor; INV3 fires semi-annually end-Jun/end-Dec), so there is no dunning cycle to automate for months. At launch **the operator drives the staged chain manually:** monitor the failed INV3 charge → operator-triggered saved-card re-charge + reminder (Stage 1) → operator places the K-Hold `STORAGE_PAYMENT_FAILED` manually (or operator-triggers `StoragePaymentFailed`) (Stage 2) → operator drives the K Suspension if the Hold persists past grace (Stage 3). **The `StoragePaymentFailed` → K-Hold → Profile-Suspension event chain + the admin-configurable staged thresholds + the multi-cycle rules are the seam (Module E §3.3); re-enabling the automated orchestration is purely additive.** N2: the storage-payment Hold trigger is manual-first; Module K's Hold registry is trigger-agnostic. **The sanctions/Hold re-read at charge (DEC-181) + the Hold-no-auto-lift discipline are FLOOR — never deferred** (the compliance gate at charge is floor even though the auto-escalation defers).

**(c) Bank-transfer reconciliation (operator-fallback; Module E §3.2).** For edge cases (webhook fails, transfer reaches Airwallex without auto-matching), the console exposes the **manual-reconciliation surface** (Finance Analyst); on operator-confirmed match, Module E emits `BankTransferFundsCleared` exactly as in the happy path. **Already operator-driven by spec — no cut.**

**(d) FX-variance review (Module E §7.2).** The Finance Analyst reviews the `FXVarianceRecorded` gap (Airwallex actual-capture FX vs the EOD-Rome snapshot rate). The dual-record FX machinery (D18 — every event in customer-currency + EUR; per-leg rate-lock; refund-at-original-rate) is **FLOOR — not a candidate**; the operator surface is the variance review, not a recomputation.

**(e) Xero exception management (Module E §7.1).** The **sync-failed retry queue** — each financial event runs a per-event sync FSM (`pending → syncing → synced`; `syncing → sync_failed` with configurable retry); persistent failures auto-escalate to a Finance Manager review queue; the reversal-ordering invariant (reversals against `sync_failed` source invoices queue until the source syncs) escalates stuck reversals. **Post-sync immutability is FLOOR** (corrections flow through credit notes — the single-payment-path discipline). The retry parameters are admin-configurable.

**(f) Chargeback dispute-evidence handling (D21 KEPT — Paolo override; Module E §6.1).** The chargeback chain is **automated from day 1** (auto-ingestion of `dispute.created`/`dispute.resolved`; auto-record + `CustomerChargebackFlagged` → K `CHARGEBACK_REVIEW` Hold). **The one operator step is step 4 — submit dispute evidence per the DEC-047 7-BD SLA** (operator by spec; the launch KPI is chargeback rate < 2%). N2: the chargeback Hold trigger is automated (the webhook), composing with the manual-first storage-payment trigger on K's trigger-agnostic registry. *(D21 KEPT is a Paolo override — payment automation is floor; see [[keep-payment-automation]].)*

**Plus the admin-configurable thresholds** (Finance Manager — Module E §1.4): dunning cadences, retry windows, FX buffer percentage, refund-compensation premiums (default 105%), Hold-lift authority. **The console specs the operator surface; the settlement-run code / dunning-orchestration internals / Airwallex+Xero API contracts are tech (DEC-073); the financial-event recording + GL boundary are owned + verified in Module E (DEC-072).**

### §4.3 The white-glove quote flow (C — D3)

**The manual operator quote for complex / high-excise / US-state destinations** — the Tier-2 fallback in the D3 geography hybrid (Module C §6.1 / §7.1). The hybrid *is* v1.1's design (KEEP it); at launch the Tier-1 automated pre-cleared list is narrowed to low-friction destinations (EU/UK/CH + whatever Paolo confirms), so complex destinations route via this already-built white-glove flow.

**The console's workflow contract:**
- A non-eligible destination offers a **"send shipping request" CTA** (not a hard block) → a Customer Care ticket → case-by-case operator review → **if approved**, a manual carrier quote (`quote_origin = manual`; the SO proceeds; the approval recorded for audit) → **if denied**, the Customer chooses continued storage or pre-shipment cancellation (DEC-108).
- **INV2 tax-correctness is FLOOR even in the manual path** — the excise computation (`ExciseCalculated`) runs even in the white-glove flow (Module C §8.2); OFAC screening applies at all destinations regardless of tier (Module C §7.2). **The floor cannot be cut by the manual routing.**
- **Seam (P1):** the manual flow records the same shipment / payment / excise data a future automated engine consumes; the Tier-1 list is operator-expandable post-launch (the automated US-state / excise / DDP-DAP engines are the roadmap).

**The Admin-Panel PRD specs the operator quote-and-approve surface; the carrier-API contracts + the quote UX are tech (DEC-073); the INV2 composition + excise flow are owned + verified in Module C §6/§8 + Module S §10.7.**

### §4.4 The returns/replacement + recall consoles (C — D14/D15)

**Two operator-run consoles the manual-first defers created** (Module C §10 / §12):

**(a) The returns/replacement console (D14 — manual-first; the FSM + discipline KEPT).** At launch operators run the DEC-184 Returns/Replacement FSM **end-to-end via the Admin Panel** — `REPORTED → INVESTIGATED → APPROVED → REPLACEMENT_ISSUED → CLOSED` with `REJECTED` / `WITHDRAWN` off-ramps (the supervisor-override approval governed by `feedback_prd_rr_approval`). **The FSM *automation* (auto-transitions / auto-routing / auto-notification) is deferred — the FSM + the 4-event chain ARE the seam.** The discipline is KEPT whole: original-voucher-preserved (INV-C-08 — no new Voucher, no new INV2); no-cash-refund (INV-C-07 — replacements only; cash refund = S supervisor-override). The supervisor-override-refund closure path (APPROVED → CLOSED with `closure_path = supervisor_override_refund`) is **multi-actor: initiator ≠ supervisor** (§5.2).

**(b) The recall console (D15 — minimal/manual).** Operator-driven reverse-logistics mirror of the forward shipment (already lean): when Module D records `ReverseInboundEventRecorded`, NewCo logistics coordinates the physical return outside the system; the operator initiates the reverse-shipment via the Admin Panel → `ReverseShipmentDispatched`. **Unsold-only (INV-C-06 — ISSUED Vouchers immune; committed-customer-holdings protected).** Full reverse-inbound mechanics (automated reverse-carrier API, three-gate reverse QC) are already-deferred — the manual posture is the launch floor; the automation is additive.

**The Admin-Panel PRD specs the operator FSM-driving + reverse-shipment-initiation surfaces; the FSM transitions + event chains are owned + verified in Module C §10/§12; the NonRevenueCost + OC-reversal financial events are the Module E seam (D19).**

### §4.5 The manual stocktake / quarantine / adjustment surfaces (B — D16)

**The operator-driven inventory-integrity surfaces the D16 manual-first SIMPLIFY created** (Module B §12/§13/§14/§15.3). **The integrity core is FLOOR — KEPT; only the automated round-trips defer.**

- **Stocktake (Module B §12):** operator-scheduled manual counts + manual variance review; book above-tolerance variances through the adjustment path or a QuarantineRecord or escalation to the Logilize queue (§4.1); `StocktakeReconciled` on resolution. The tolerance-driven auto-reconciliation engine + cadence automation are deferred; the entity + variance-computation contract are the seam.
- **Inventory adjustment (Module B §13):** operator proposal (scope / type / qty-delta / reason) → **single-supervisor approval (multi-actor: proposer ≠ supervisor, §5.2)** → `InventoryAdjusted` (ATP push to A; E records the financial event). **Committed-inventory protection is FLOOR — NOT a D16 candidate:** the pre-validation rejects any negative-delta adjustment on committed inventory that would breach outstanding vouchers → `InventoryShortfallDetected` to Module A (the proposal cannot proceed until A's `VoucherCancelled` releases the commitment).
- **QuarantineRecord (Module B §14):** the quarantine-before-trust gate — supervisor resolves via 4 paths (associate-with-existing / create-new [explicit sign-off, no auto-create] / reject-as-invalid / escalate-to-the-Logilize-queue); resolved records immutable. **The automated cross-module cascades on resolution are manual-first (D16) — the operator records the cost-basis / financial-event / ATP follow-ups manually.**
- **The receiving physical-match round-trip (Module B §11.2 / N1):** the operator opens the `InboundBatchDiscrepancy` + records the resolution path manually within the 5-WD window — **identically to Module D's manual-first depth** (§4.6).

**The Admin-Panel PRD specs the operator proposal / approval / triage surfaces; the integrity-core entities / events / FSMs + the committed-inventory-protection floor are owned + verified in Module B §11–§14.**

### §4.6 The manual procurement / discrepancy surfaces (D)

**The operator-driven procurement + receiving workflows** (Module D §3.6 / §5 / §6 / §7 / §13). The procurement spine is operator-driven end-to-end:
- **PI / PO lifecycle:** PI creation (V1/V2 system-auto-fired; the Direct-Purchase operator-initiated PI is **deferred** — item I); PO lifecycle DRAFT → ISSUED → … → CLOSED; the **issuance-gate override** (`POIssuedUnderNonActiveAgreement` — operator override + audit); **cost-finalization** (the 5-WD landed-cost finalization).
- **The manual receiving-discrepancy handling (N1 — manual-first; Module D §3.6 / §13.3):** the operator opens the discrepancy + records the resolution path (the **6-path enum**) manually within the 5-WD window — landed **identically to Module B's manual-first depth** (§4.5; the D↔B interlocks read consistently per N1). The DISCREPANCY state + the 6-path resolution enum + the event consumers are the kept seam; only the automated round-trips defer.
- **Producer-initiated recall → operator-driven** (event-record-only; §4.4).

**The Admin-Panel PRD specs the operator PI/PO/discrepancy surfaces; the procurement entities / events / FSMs + the DEC-194 receiving split are owned + verified in Module D §5–§13 (and Module B §11 for the physical-match half).**

---

## §5 The composed-surface model + the multi-actor discipline (supporting)

> **The consoles in §4 + the cross-module capabilities flagged "composed" in §3 are *composed surfaces*** — single operator surfaces that span two-or-more modules. This section names the composed-surface model + the one PRD-level discipline the surface owns regardless of role configuration (the multi-actor patterns). It is a *contract-level* statement (which operations compose on one surface), not a UX spec (DEC-073).

### §5.1 The canonical composed surface — "refund a customer's order" (K + S + C + E)

The canonical four-module composed surface (the worked example from the operator-task reference, mapped down to the MVP slice): a Customer-Care operator, in one surface, reads the cancellation eligibility (S §12.1 — 14-day pre-shipment window), the voucher state (S §11) + shipping state (C §2), and the customer Hold/sanctions state (K §4.8), then drives the refund — records the cause + offers store-credit-105% by judgment (**D6 manual-first**, S §12.5) → Module S emits `RefundRequested` → **Module E executes the Airwallex refund + records `RefundExecuted`** (FX-correct, credit-note discipline — E §5.1) → the producer-fault clawback netting **defers with the settlement engine** (D19). **Each module owns + verifies its half** (S the cause/coupon, E the execution/recording, K the Hold, C the shipping state); the Admin-Panel PRD names that they **compose on one operator surface**. The other composed surfaces (the allocation cluster A; the procurement cluster D+B; the inventory cluster B; the fulfilment cluster C; the finance cluster E; the onboarding/compliance cluster K) are named in §3's "composed?" column + detailed in §4. *(The full composed-surface enumeration — ~20 distinct cross-module surfaces — is the design-side `greenfield/12-admin-panel/operator_cross_module_workflows_v0.1.md` reference, mapped down to the MVP slice; §6.)*

### §5.2 The multi-actor discipline (the one PRD-level policy the surface owns)

Three operator patterns are **spec-mandated multi-actor** — the spec text itself requires two-or-more distinct actors, independent of which roles they hold. The Admin Panel must surface a **"second actor required" affordance** at the relevant gate (the affordance is a capability; the UX is tech, DEC-073):
- **The 3-step Creator → Reviewer → Approver lifecycle** (Module 0 §4.2 entity activation + Module K §4.4 Producer activation) — the three actors must be three different people; **self-approval is never allowed** (the role-count is admin-configurable — Mod0-Q2 / K-Q3 — but the separation-of-duties floor stands).
- **The supervisor-override pattern** (Module S §12.3 post-shipment refund; Module C §10.2 supervisor-override-refund closure) — initiator ≠ authoriser.
- **The single-supervisor-approval pattern** (Module B §13 inventory adjustment) — proposer ≠ supervisor.

**This is the one discipline the Admin-Panel surface owns at the PRD layer** — every other authority-tier / RBAC / persona-gating decision is admin-configurable + downstream (`feedback_prd_rr_approval`, §1.4). The audit envelope (`actor_role` + identity + timestamp, §1.3) records every actor on every step that runs.

---

## §6 The "full target surface" seam (block (d)) — the north-star (short)

> **The MVP Admin-Panel surface is explicitly a clean SUBSET of the full target surface (P1).** This PRD writes the thin MVP slice (what the 8 v0.3-MVP PRDs expose at launch + the net-new manual-first consoles); the **full Admin-Panel surface is a roadmap deliverable** (master §5 #12). **Do NOT write the full surface here — name the seam.**

**The full target surface accretes along three additive axes as the deferred scope restores:**

1. **The restored automation consoles** — as each manual-first defer's automation lands, the corresponding console gains its automated arm (purely additive — the manual-first surface is the seam): the **settlement engine** (E — the quarterly runs + statement FSM + OC 5% aggregation + clawback netting + Xero AP, replacing the §4.2(a) operator-run composition, D19); the **dunning orchestration** (E — the 3-stage auto-escalation replacing the §4.2(b) manual chain, D4); the **Stage-8 inventory automation** (B — the tolerance-driven auto-reconciliation + the automated quarantine/discrepancy cascades replacing the §4.5 manual round-trips, D16); the **Returns/Replacement FSM automation** (C — the auto-transitions/routing/notification replacing the §4.4 manual FSM, D14); the **D3 automation engines** (C — the automated US-state / excise / DDP-DAP engines replacing the §4.3 white-glove manual quote).

2. **The producer-portal write-UIs** — the producer-facing write UIs that build back on the same DEC-083/115 backend (the operator path is already complete — §2.1): allocation ops (A), procurement ops (D), Club-Offer authoring (S), the richer waitlist-review UX (K), the producer recall UI (A/D). At launch these are operator-driven via the Admin Panel; post-launch they restore as producer self-serve (the consumer storefront is already self-serve — exempt).

3. **The cross-cutting platform layer** — the **UX / IA / design-system layer** (screen layouts, navigation, component library, design tokens, page templates — all DEC-073 tech-implementation, deferred) + the **RBAC / authority-tier / persona-gating model** (admin-configurable + downstream per `feedback_prd_rr_approval`, §1.4). **The `greenfield/12-admin-panel/` exploration is the design-side north-star for this layer** (the IA model, the canonical journeys, the component primitives, the design-token framework, the 57-task / ~20-composed-surface operator inventory) — a **read-only reference**, mapped down to the MVP slice here, accreting in the roadmap as the full surface.

**The seam is real on both sides (P1).** Every manual-first console (§4) records the same data its future automated engine consumes (the recording / integrity-core is whole at launch — §0); every operator-driven write sits on the same backend its future producer UI will use (DEC-083/115 parity is a backend contract — §2.1). **The full Admin-Panel surface is a forward target, not a backward predecessor** (§8) — it is largely *derivable* (the full operator surface = the 8 full PRDs' operations + the deferred-automation roadmap + the deferred producer-portal write-UIs; a composition, not a new source of truth). **Point: the full surface lives in the roadmap** (`04-roadmap/`, master §5 #12) — do not write it now.

---

## §7 Naming note (the lightest of the nine — the Admin Panel owns no category names)

The Admin Panel **reads** catalog identity but **owns no category names** — it is the operator surface over the modules, and it surfaces **each module's own names**. Per Module 0 v0.3-MVP §18 (the source-of-truth names), where this surface references catalog identity it reads `Product Reference (PR)` (wine-display alias **Bottle Reference**) and `Product Master / Variant` (wine-display alias `Wine Master / Variant`); where it surfaces a module's own names it carries them unchanged — Module E's category-neutral finance names (`Invoice*`, `Payment*`, `Settlement*`, `Chargeback*`, …), and Modules B and C's physical-unit / wine-display names (`SerializedBottle`, `InboundBatch`, `Case`, `StockPosition`, "Bottle Page"; `Shipping Order`, `BottlePicked`, `ShipmentDispatched`, …) per the Module 0 guardrail-8 carve-out. **"Bottle Reference" is retained everywhere as a wine-display alias; payload semantics identical; zero behaviour change** (Phase C item A). This is the **lightest naming touch of the nine PRDs** — the Admin Panel is a presentation/operation layer; it inherits names, it does not mint them.

---

## §8 MVP re-baseline note — the first time the surface is specced (NEW; no predecessor)

> **⚠️ This is the FIRST PRD with NO frozen v1.1 predecessor and NO cut-sheet.** Every other v0.3-MVP PRD is stripped *from* a full v1.1 predecessor (greenfield v0.2). The Admin Panel had **no full predecessor** — v1.1 treated it as an implicit **DEC-083 admin-parity mirror** and never specced it. **This PRD is the *first* time the surface is specced — not a stripped-down version of anything.**

**Why a 9th PRD now (Phase C item L, ratified Q1).** The manual-first MVP made the Admin Panel the **load-bearing operational surface of the launch** (D24 — *more* load-bearing because of the D19/D4/D16/D14/D3/D15 defers, not less); the cross-module surface had never been triaged as its own artefact (each cut-sheet only forwarded it to Phase C). Resolution: a thin 9th MVP Admin-Panel PRD (this doc) at the product-spec layer (not a UX spec — DEC-073; not a duplication of the 8 module backends).

**Why the "full version" is a roadmap item, not a backward predecessor (Phase C item L, Paolo's follow-up).** The **"full version" is a forward target, not a backward predecessor:** writing a standalone full Admin-Panel PRD now would cut against the Lean re-scoping (and is largely *derivable* — §6); and v1.1 cannot be retrofitted (greenfield is frozen — plan R4). So the **full Admin-Panel surface lives in the roadmap** (`04-roadmap/`) as the buildout target — accreting as the deferred automation + producer-write-UIs restore — exactly where every other module's deferred scope lives. Symmetric in *intent* (every module: an MVP scope + a roadmap of deferred scope); it simply has no frozen full predecessor because there never was one. **The Phase-D Architecture records the Admin Panel as a first-class cross-cutting surface** (correcting v1.1's implicit treatment — the next artefact, #10).

**What this PRD is built from (DEC-074 — self-contained; there is no v1.1 predecessor to cite — said plainly):**
- **The authoritative scope brief:** master `Phase_D_Kickoff_Prompt.md` **§6.D** (the four content blocks).
- **The spine:** Phase C `Phase_C_Reconciliation_v0.1.md` **item L** (the surface contract + the ratified Q1 decision).
- **The settled input:** the **8 module PRDs' L-PP / operator-surface / producer-write-treatment sections** (referenced per capability throughout §3–§4 — not duplicated).
- **The read-only design-side reference (NOT a predecessor, NOT scope to import):** `greenfield/12-admin-panel/` (the Stage-8 operator-task inventory [57 tasks / ~20 composed surfaces], the IA model, the canonical journeys, the component library + design tokens) — used for **operator-task vocabulary only, mapped down to the MVP slice** (the operator surfaces the 8 v0.3-MVP PRDs actually expose at launch); its scope (the full target surface) is **not imported** — it is the §6 north-star.

**No new scope cuts taken** (the surface contract is settled — Phase C item L). **No backend re-spec** (reference the module PRDs). **No UX/layout** (DEC-073). The genuine drifts surfaced are flagged for Paolo (§9), not resolved unilaterally.

---

## §9 Flags for Paolo + Cross-references

### §9.1 Flags for Paolo (genuine items surfaced — not cut/invented; for the digest)

1. **The Module E PRD cross-references a "§5.11 L-PP" section that does not exist** (Module E §0 / §1.4 cite "§5.11" as the L-PP locus, but Module E's section list runs §1.4 personas → §3.2/§3.3 → §4 → §6 → §7 with no §5.11). The L-PP content *is present* — distributed across Module E §1.4 (personas) + the operator surfaces in §3.2/§3.3/§4.4/§6.1/§7.1/§7.5 — so this is a **stale internal cross-reference in a sibling that is itself DRAFTED (awaiting batch ratification), not a content gap.** This PRD reads Module E's finance-ops surface from §1.4 + those sections (the files win). **Flagged for a one-line Module E §-anchor fix at batch ratification** (forward-consistency, no behaviour change) — not blocking.
2. **The authority-tier / RBAC model is intentionally unspecced at the PRD layer** (admin-configurable + downstream per `feedback_prd_rr_approval`, §1.4) — consistent with Mod0-Q2 / K-Q3 (the 3-step approval role-count is admin-configurable). **This is a known deferral, not a gap** — flagged so Paolo confirms the surface stays role-agnostic at launch (the multi-actor separation-of-duties floor, §5.2, stands regardless). The full RBAC model is the §6 seam (the `greenfield/12-admin-panel/archived/operator_role_model_v0.1.md` is the design-side reference).
3. **No new operator capability beyond the 8 PRDs' exposed surfaces** — the inventory (§3) is a faithful mapping-down of what the 8 v0.3-MVP PRDs expose; if Paolo sees an operator surface the launch needs that no module exposes, that is a genuine gap to surface (none found in this pass — the 8 PRDs' operator surfaces compose the launch operational floor).

### §9.2 Cross-references

- **Authoritative scope brief:** master [`../00-method/Phase_D_Kickoff_Prompt.md`](../00-method/Phase_D_Kickoff_Prompt.md) §6.D (the four content blocks) + §3.P2 + §6.E.
- **The spine:** [`../01-triage/Phase_C_Reconciliation_v0.1.md`](../01-triage/Phase_C_Reconciliation_v0.1.md) item L (RESOLVED Q1) + §3 items G–K (the floor/seam items the consoles surface) + §6 (the floor chains).
- **The 8 module backends (referenced, not duplicated):** [Module 0](Module_0_PRD_v0.3-MVP.md) §2/§4/§5 · [Module K](Module_K_PRD_v0.3-MVP.md) §2/§3.1/§9/§4.8/§8.2/§12 · [Module A](Module_A_PRD_v0.3-MVP.md) §3.3 · [Module D](Module_D_PRD_v0.3-MVP.md) §3.6/§5–§13 · [Module S](Module_S_PRD_v0.3-MVP.md) §2/§15/§12 · [Module B](Module_B_PRD_v0.3-MVP.md) §0.8/§11–§15/§20.1 · [Module C](Module_C_PRD_v0.3-MVP.md) §0.9/§4/§6/§7/§10/§12 · [Module E](Module_E_PRD_v0.3-MVP.md) §1.4/§3.2/§3.3/§4/§6/§7.
- **Companion acceptance:** [`../03-acceptance/Admin_Panel_Acceptance_v0.3-MVP.md`](../03-acceptance/Admin_Panel_Acceptance_v0.3-MVP.md).
- **Source-of-truth names:** [Module 0 §18](Module_0_PRD_v0.3-MVP.md) (the naming cascade — the Admin Panel reads, owns no category names — §7).
- **Decisions index:** [`../04-decisions/MVP_Decisions_Register_v0.1.md`](../04-decisions/MVP_Decisions_Register_v0.1.md) (D24 Admin Panel; L-PP; D23; the four RECONCILEs).
- **The full target surface → roadmap:** `04-roadmap/Post_Launch_Roadmap_v0.1.md` (master §5 #12 — the buildout target, §6) + the design-side reference `greenfield/12-admin-panel/` (read-only — vocabulary, not scope).
- **Next artefact (#10):** the v0.3-MVP Architecture — records the Admin Panel as a first-class cross-cutting surface.

---

*End of Admin-Panel PRD v0.3-MVP — **DRAFT, awaiting batch ratification (Paolo).** The thin 9th PRD: (a) the per-module operator-capability inventory (§3 — reference the module PRDs, don't re-spec); (b) the net-new cross-module operator consoles (§4 — the finance-ops console E + the shared Logilize discrepancy queue B+C are the substantive net-new content; plus white-glove C/D3, returns/recall C/D14-D15, stocktake/quarantine/adjustment B/D16, procurement/discrepancy D); (c) the producer-write boundary (§2 — exactly ONE producer write = K membership approve/decline; D23 read KEPT; consumer storefront exempt); (d) the "full target surface" seam (§6 — the restored automation consoles + producer-portal write-UIs + the UX/RBAC platform layer → roadmap). Product-spec layer only (DEC-073 — no UX); references the backends (no re-spec); no new scope cuts (the surface contract is settled — Phase C item L). Net-new but THIN — a composition + consolidation, more load-bearing in a manual-first MVP (D24). Recommend; Paolo decides. Nothing handed off until Phase E. With this PRD drafted, the 9 PRDs are complete — the re-baseline turns to the Architecture (#10), the roadmap (#12), and the release index (#13).*
