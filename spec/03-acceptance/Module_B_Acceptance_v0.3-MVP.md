# NewCo ERP — Module B (Inventory Authority + Digital Provenance) Acceptance Criteria — v0.3-MVP

- **Version**: v0.3-MVP (Phase D re-baseline — the launch-MVP acceptance contract for Module B; re-cut from the v0.1 DRAFT)
- **Date**: 2026-06-08
- **Status**: **DRAFT — awaiting batch ratification (Paolo).** Held in `mvp/`; nothing promoted to `handoff/` until Phase E. The acceptance delta is **bounded and unusually clean — because the D12 decouple boundary is already drawn (the EXT-1 gate, now *re-scoped*) and the D16 integrity core is untouched.** Module B is **KEPT WHOLE on its inventory-integrity floor + D12 DECOUPLE (on-chain layer) + D16 SIMPLIFY (Stage-8 workflow automation → manual-first) + R4 consumer + N1/N3 + naming cascade**: this doc (a) **re-scopes the EXT-1 feature-flag gate** so it gates only the **NFT/on-chain** criteria — the physical-tagging / serial / `SerializedBottle`-ledger criteria are **launch-floor, NOT gated** (§0.1) + adds `nft_reference`-nullable / back-fill criteria; (b) **annotates the D16 Stage-8 automation criteria manual-first** (the integrity-core criteria stand UNCHANGED, §0.2); (c) **aligns the `SupplierPaymentCompleted` criteria to the E-emits / B-consumes contract (R4 — the criterion that had Module B deriving CRURATED *at sell-through* now verifies Module B *consuming the E-emitted event*, §0.3)**; (d) applies the **naming cascade** to the catalog-identity criteria; (e) re-anchors to the v0.3-MVP PRD; (f) adds a small **§6.12 MVP re-baseline** section. **No criterion in launch scope is removed; all floor criteria stand unchanged.**
- **Owner**: Paolo (product sign-off authority)
- **Companion spec**: [`../02-prd/Module_B_PRD_v0.3-MVP.md`](../02-prd/Module_B_PRD_v0.3-MVP.md) — the source of truth this document validates against. The PRD says *what to build*; this document says *what passes*. Together they are the dev-team's complete brief for the launch-MVP Module B.
- **Predecessor (re-cut from)**: [`../../reference/v1.1/01-prd/Module_B_Acceptance_v0.1.md`](../../reference/v1.1/01-prd/Module_B_Acceptance_v0.1.md) — the v1.1 acceptance template (**DRAFT 2026-05-15; 170 criteria; 89.4% AUTO / 8.8% MIXED / 1.8% HUMAN; ~29–33 NFT/NFC criteria EXT-1-gated; Packet verdict EDITS_NEEDED — NOT yet Paolo-validated**, like Module D + Module S). `greenfield/` is frozen (plan R4); this is a derivative under `mvp/`. **The MVP re-cut + the original validation + the Packet's EDITS_NEEDED reconciliation land together** at Paolo's batch ratification.
- **Audience** (three concurrent uses): **Paolo** at module-delivery sign-off (verdict report + spot-checks + a focused four-way-reconciliation review); **dev team** during build (the definition of done, read alongside the PRD from day one); **AI coding agents** during code generation (AUTO criteria as fitness functions in the build loop).
- **Purpose**: the demonstrable behaviours that, taken together, constitute "Module B is delivered as specified per v0.3-MVP." Each criterion is traceable to a PRD anchor (BR-B-* / event / FSM transition / DEC / §) and tagged AUTO / MIXED / HUMAN.
- **Methodology DECs binding this document**: DEC-072 (no-accounting-policy claims — Module B records inventory/cost-basis business-signal events; Module E records the financial event; Xero decides GL), DEC-073 (product-spec layer; criteria are business-behaviour, not tech-implementation — incl. the WMS/Logilize integration mechanics, the ATP-cache push mechanics + staleness/latency SLAs, the NFC/NFT minting + on-chain encoding, the Bottle Page render surface), DEC-074 (self-contained; anchors restated inline), **Phase C R4 (`SupplierPaymentCompleted` is E-emitted / B-consumed) + N1 (D16 manual-first, identical with Module D) + N3 (the CRURATED-vs-NEWCO two-ledger clarity)**, `feedback_prd_rr_approval` (operator approval-tier policy admin-configurable — out of scope).
- **The re-scoped EXT-1 gate (load-bearing — §0.1)**: every **NFT/blockchain/on-chain** criterion is **gated behind a feature flag pending EXT-1 (blockchain-expert) review** — but **re-scoped at ratification (D12 / Q1)** so the gate covers **only the on-chain layer.** The **physical-tagging / serial / `SerializedBottle`-ledger criteria are launch-floor (un-gated)** — `NFCTagApplied` (AC-B-EVT-1) + NFC application (AC-B-XM-47) move out of the gated set. **The inventory-ledger criteria are NOT gated.** This **exactly resolves AMB-B-5** (Stage-8-net-new vs preserved-from-v0.1 EXT-1 gate scope) + the Packet's count reconciliation (§0.4).
- **What this document is NOT**: engineering Definition of Done (coverage thresholds, performance budgets beyond the §22.1 metrics, retry/idempotency mechanics, schema design, the wallet operational architecture, Avalanche RPC + gas + smart-contract code, the Logilize integration mechanics, the NFC tag-write encoding, the Bottle Page render surface); UI / UX acceptance (Admin Panel form layouts, the Bottle Page render, the Cellar render UX); operational R&R / approval-tier *policy* (admin-configurable); Phase 2+ deferrals (multi-warehouse, ConsignmentPlacement, AGENCY, THIRD_PARTY ownership_flag); cross-module behaviours owned by other modules.

---

## §0 What changed from v0.1 (the re-cut delta)

Module B is **KEPT WHOLE on its inventory-integrity floor**, with **D12 (DECOUPLE the on-chain layer) + D16 (SIMPLIFY the Stage-8 workflow automation → manual-first)** as the two cuts, plus the **R4 consumer flip + N1/N3 + naming cascade**. So this acceptance re-cut is **bounded and additive — no launch-scope criterion is removed:**

1. **The EXT-1 gate is RE-SCOPED — the one substantive D12 acceptance delta (§0.1):** the ~29–33 NFT/NFC/wallet/on-chain criteria stay **authored + CI-wired behind the flag** (enabled when the on-chain workstream + EXT-1 review land), **but the gate is re-scoped** so the **physical-tagging / serial / `SerializedBottle`-ledger criteria move to the launch-floor (un-gated) set** — **AC-B-EVT-1** (`NFCTagApplied`), **AC-B-XM-47** (NFC application execution boundary), and the SerializedBottle-ledger-creation criteria (**AC-B-J-3**, **AC-B-FSM-1..6**). The gated set becomes the **authoritative decouple manifest** (NFT mint/burn/wallet/on-chain/recovery + the on-chain-reference tag content). **`nft_reference`-nullable + back-fill criteria are added** (AC-B-MVP-1 / AC-B-MVP-2) for the serialized-minus-NFT launch posture. **The inventory-ledger criteria remain not-gated, UNCHANGED.**
2. **D16 Stage-8 automation criteria annotated MANUAL-FIRST (N1 — §0.2):** the Stocktake auto-reconciliation/cadence portions (**AC-B-J-14**, **AC-B-FSM-16..18**, **AC-B-BR-Reconcile-2**), the QuarantineRecord automated-cascade portions (**AC-B-J-17**, **AC-B-EVT-27**, **AC-B-XM-17**), and the automated reciprocal-round-trip with Module D (**AC-B-J-2** discrepancy-round-trip, **AC-B-EVT-20**) are re-scoped to **manual-first operator handling** (the automated-round-trip arms annotated deferred-with-feature; verified when the automation lands). **Identical to Module D's manual-first depth (N1).** **The integrity-core criteria stand UNCHANGED — FLOOR** (§0.2).
3. **`SupplierPaymentCompleted` criteria aligned to E-emits / B-consumes (R4 — ⚠️ the trap — §0.3):** the criterion that had Module B deriving CRURATED *at sell-through* now verifies Module B **consuming the E-emitted `SupplierPaymentCompleted`** to flip the inventory `ownership_flag` PRODUCER → CRURATED: **AC-B-J-21** (re-anchored to the E-emitted trigger), **AC-B-EVT-17** (`OwnershipTransitioned` triggered by the E-emitted event), **AC-B-EVT-39** (consume Module E's `SupplierPaymentCompleted` — the v1.1 "Phase C cascade anticipated" note now settled as E-emits), **AC-B-BR-Ledger-5** (Module E emits / Module B consumes — **Module B does NOT emit or derive it**). **N3** (the CRURATED inventory ledger distinct from Module D's NEWCO PO-level title ledger) annotated. Naming/contract only.
4. **Naming cascade applied to the catalog-identity criteria** (Phase C item A): `Bottle Reference → Product Reference`, `Wine Master/Variant → Product Master/Variant` in **AC-B-J-3** (SerializedBottle creation), **AC-B-FSM-19/20** (StockPosition `(PR, …)` intersection), **AC-B-XM-13/29** (Bottle Page + Module 0 reads), and the `BottleReferenceActivated/Retired → ProductReferenceActivated/Retired` consumer note. **Module B's own physical-unit entity names** (`SerializedBottle`, `InboundBatch`, `Case`, `StockPosition`, "Bottle Page", "bottle-days-in-storage") are **retained as wine-display naming** (the §18 carve-out). Wine-display aliases ("Bottle Reference / BR") retained. **Behaviour is identical.** See AC-B-MVP-3.
5. **Re-anchored to the v0.3-MVP PRD.** PRD §-numbers now refer to [`../02-prd/Module_B_PRD_v0.3-MVP.md`](../02-prd/Module_B_PRD_v0.3-MVP.md). **Module B had no structural entity insertion (cut-heavy but a decouple + an automation simplify, not a re-model), so the body §-anchors (§1–§22) are unchanged from v1.1** — every existing AC anchor remains valid. Only §0 was prepended; §N adds the MVP re-baseline trace.
6. **New section §6.12 — MVP re-baseline criteria** (6 criteria, AC-B-MVP-1..6), verifying the `nft_reference`-nullable / back-fill posture, the re-scoped EXT-1 gate manifest, the naming cascade, the D16 manual-first seam, the R4 E-emits/B-consumes contract, and the floor parity.
7. **Floor criteria re-affirmed UNCHANGED:** the two-layer no-oversell guard + per-sub-pool ATP (**AC-B-J-18**, **AC-B-BR-NoOversell-1/2/3**, **AC-B-XM-22/23**), the B→A push (**AC-B-EVT inventory-ledger rows**, **AC-B-XM-5/6/7/8**), committed-inventory protection + `InventoryShortfallDetected` (**AC-B-J-16**, **AC-B-BR-Commit-1**), InboundBatch + cost-basis (**AC-B-J-1**, **AC-B-FSM-7..10**, **AC-B-BR-Reconcile-4**), StockPosition 5-dimension (**AC-B-FSM-19..21**), the quarantine-before-trust gate (**AC-B-J-17**, **AC-B-BR-Quarantine-1/2**), provenance immutability (**AC-B-BR-Provenance rows**), the DEC-194 split (**AC-B-BR-Reconcile-1**), the Bottle Page zero-customer-identifiers (**AC-B-BR-Anonymisation-2/3**) all stand as-is. **Nothing in launch scope removed.**

### §0.1 The D12 decouple posture (the re-scoped EXT-1 gate — the decouple manifest)

Per the cut-sheet Q1 (ratified, refined: **DECOUPLE the NFT/on-chain layer only; the per-bottle serialization workflow stays launch-ready**) + Phase C item J (the NS path is the universal fallback; every downstream degrades gracefully). **The EXT-1 feature-flag gate IS the decouple boundary — and it is re-scoped so it gates only the on-chain layer:**

- **Launch-floor (UN-GATED) — the serialization workflow is launch-ready:** **AC-B-EVT-1** (`NFCTagApplied`), **AC-B-XM-47** (NFC application — Module B records, Logilize executes), **AC-B-J-3** (SerializedBottle creation + inherited lineage/ownership), **AC-B-FSM-1..6** (the SerializedBottle ledger lifecycle), and the serial-capture / ledger-record criteria. These move **out** of the v0.1 gated set into the launch-floor set (the WH tag + serial + ledger is a warehouse-floor operation that cannot be deferred). **`nft_reference = NULL` at launch (nullable + back-fillable)** — AC-B-MVP-1.
- **GATED behind EXT-1 (the decouple manifest — back-filled when the on-chain workstream + the blockchain-expert review land):** the NFT-mint/burn/wallet/on-chain/recovery + the on-chain-reference tag content — **AC-B-J-4** (NFT mint 1:1), **AC-B-J-5** (the *on-chain-reference* component of the tag content — the serial + Bottle Page URL components are launch-ready, AC-B-MVP-2), **AC-B-J-6** (NFT burn at shipment — the SerializedBottle transition + serial late-binding are launch-ready; the burn back-fills), **AC-B-J-7** (on-chain burn anonymisation), **AC-B-J-10/J-12** (§17.1/§17.3 on-chain recovery), **AC-B-J-11** (§17.2 certificate — rides the decouple), **AC-B-EVT-2** (`NFTMinted`), **AC-B-EVT-4** (`BottleNFTBurned`), **AC-B-EVT-6..15** (recovery + NFT events), **AC-B-EVT-33** (consume `VoucherShipped` → burn — the NS path un-gated), **AC-B-XM-9** (NFT burn chain), **AC-B-XM-16** (pre/post-burn variants), **AC-B-XM-42** (wallet architecture), **AC-B-XM-48** (Avalanche on-chain execution), **AC-B-XM-49** (NFC tag-write protocol — the on-chain-reference encoding), **AC-B-BR-Anonymisation-1** (no PII on-chain).
- **Split criteria (the inventory-ledger side launch-floor; the on-chain side gated):** **AC-B-J-13** (§17.4 destruction — the `written_off` lifecycle + `InventoryAdjusted` damage + Module A pool-debit are **launch-floor / KEPT**; the `BottleNFTBurnedAsDestroyed` on-chain burn is gated), **AC-B-EVT-3** (`BottleSerialized` — the SerializedBottle-creation side launch-floor; the NFT-correlation side gated), **AC-B-EVT-14** (`BottleDestroyedInCustody` — the destruction-event recording is launch-floor; only the NFT-burn portion gated).
- **The NS universal fallback (UN-GATED — the floor does not depend on the on-chain layer):** **AC-B-EVT-5** (`BottleShippedAsNonSerialized`), **AC-B-XM-10** (NS no-op + the informational event). These carry every shipment when the on-chain workstream slips.

This **exactly resolves AMB-B-5** (the re-scoped gated-list IS the authoritative decouple manifest). **Two Paolo-track action items (time-sensitive — §0.4):** schedule the EXT-1 review now; confirm the DEC-124 tag-content back-fill design.

### §0.2 The D16 manual-first posture (the Stage-8 workflow automation; N1 — identical with Module D)

Per the cut-sheet Q2 (ratified: **KEEP the integrity core (FLOOR); SIMPLIFY the Stage-8 workflow automation → manual-first**) + Phase C item H (B decided the depth in lockstep with Module D's KEEP-pending-B-review, now discharged). **The integrity-core criteria stand UNCHANGED — FLOOR; only the automated round-trips are annotated manual-first:**

- **The integrity-core criteria stand UNCHANGED — FLOOR (not candidates):** the two-layer no-oversell guard (**AC-B-BR-NoOversell-1/2/3**, **AC-B-J-18**), committed-inventory protection + `InventoryShortfallDetected` (**AC-B-J-16**, **AC-B-BR-Commit-1**), the quarantine-before-trust gate (**AC-B-J-17** gate portion, **AC-B-BR-Quarantine-1/2**), cost-basis flow (**AC-B-BR-Reconcile-4**), the DEC-194 two-stage receiving split (**AC-B-BR-Reconcile-1**, **AC-B-J-2** physical-match portion).
- **The automated round-trips annotated MANUAL-FIRST (deferred-with-feature; verified when the automation lands; identical to Module D §13.3/§13.4):** the Stocktake **tolerance-driven auto-reconciliation + cadence automation** (**AC-B-J-14**, **AC-B-FSM-16..18**, **AC-B-BR-Reconcile-2** — at launch the operator schedules manual counts + manual variance review; the FSM + the variance-computation contract + `StocktakeReconciled` stand); the QuarantineRecord **automated cross-module cascades on resolution** (**AC-B-J-17** cascade portion, **AC-B-EVT-27** cascade portion, **AC-B-XM-17** — at launch the operator records the follow-up manually; the gate + the 4 paths + immutability stand); the **automated reciprocal round-trip with Module D** (**AC-B-J-2** discrepancy-round-trip portion, **AC-B-EVT-20** — at launch the operator opens the discrepancy + records the resolution within the 5-WD window; the `InboundBatchDiscrepancy` event + the DISCREPANCY state + Module D's 6-path enum stand). See AC-B-MVP-4.

### §0.3 The `SupplierPaymentCompleted` posture (R4 + N3 — ⚠️ the trap)

The single highest-risk reconciliation. The v1.1 acceptance (AC-B-J-21, AC-B-EVT-17/39, AC-B-BR-Ledger-5) had Module B flipping `ownership_flag` PRODUCER → CRURATED *at sell-through*, with the trigger loosely sourced ("Module E triggers via `SupplierPaymentCompleted`"; the v1.1 §19.2 carried a "Phase C cascade question"). **Phase C R4 settles it as E-emits:**

- **R4 (Phase C) — E-emits / B-consumes.** **Module E emits `SupplierPaymentCompleted`** on payment clearing (the payment executor — three-actor split DEC-119; symmetric with the customer-side `AirwallexChargeExecuted`). **Module B consumes it** to flip the inventory `ownership_flag` PRODUCER → CRURATED. **This corrects the cut-sheets' "D-emits" reading + the v1.1's loose "at sell-through" framing.** **AC-B-J-21** is re-anchored to the E-emitted trigger (B consuming, not deriving); **AC-B-EVT-39** verifies B consuming Module E's `SupplierPaymentCompleted` (the "Phase C cascade anticipated" note settled as E-emits); **AC-B-BR-Ledger-5** verifies Module B records + executes but does not decide — **and does NOT emit or derive the event** (assert no `SupplierPaymentCompleted` emission path in Module B). **AC-B-MVP-5** verifies the E-emits / B-consumes contract.
- **N3 — two distinct ledgers, same party.** The inventory `ownership_flag` `CRURATED` ledger (Module B, keyed to `SupplierPaymentCompleted`) is **distinct** from Module D's PO-level `NEWCO` title ledger (keyed to `VoucherIssued` — Module D item F). Same real-world party; two signals at two moments. Annotated on AC-B-J-21 / AC-B-EVT-17. *(Module E's emission side is verified in Module E acceptance; Module D's independent PO-title consume is verified in Module D acceptance.)*
- **Direct-Purchase no-op:** for `direct_purchase` the InboundBatch is `CRURATED` from creation → no PRODUCER → CRURATED transition; **not-exercised-at-launch** (Direct Purchase deferred — Phase C item I).

### §0.4 The re-scoped gate count reconciliation + the acceptance-authoring backlog (AMB-B-1..5)

Module B's acceptance is DRAFT (not Paolo-validated, like Module D + Module S; Packet verdict EDITS_NEEDED); the MVP re-cut + the original validation + the Packet's Q2 count reconciliation (29/33/28) land together. **The re-scoped gated-list (the on-chain manifest, §0.1) becomes the authoritative count** — `NFCTagApplied` (AC-B-EVT-1) + NFC application (AC-B-XM-47) + the SerializedBottle-ledger criteria move to the launch-floor set, so the gated count shrinks to the genuine on-chain set. The **AMB-B-1..5** PRD ambiguities (the `BottleSerialized` composite-event optionality, the `InboundBatchStateChanged` enum coverage, the `consumption`/`transfer` placeholders, the supervisor-role binding, the Stage-8-net-new vs preserved-from-v0.1 EXT-1 gate scope) are an **acceptance-authoring backlog** deferred to a future editorial pass — orthogonal to MVP scope (§7). **AMB-B-5 is exactly resolved by the re-scoped EXT-1 gate (§0.1).** **The two Paolo-track action items (time-sensitive, §0.1):** (1) schedule the EXT-1 blockchain-expert review now (or it becomes the launch critical path); (2) confirm the DEC-124 tag-content back-fill design (serial + Bottle Page URL at launch; the on-chain reference back-fillable — pre-launch NFC tag-stock procurement lead-time makes it time-sensitive).

---

## §1 How to use this document

### §1.1 Verification tags + the EXT-1 gate flag

- **AUTO** — an AI agent or automated harness reads the criterion + spec anchor + running system (event stream, entity state, API responses, audit trail, on-chain state where applicable) and produces a PASS/FAIL verdict with evidence. Paolo reviews the verdict batch.
- **MIXED** — AI prepares the evidence (six-locale Bottle-Page renderings, the audit-trail trace for a recovery scenario, the StockPosition reconciliation report for a stocktake variance review); Paolo confirms a judgment call.
- **HUMAN** — Paolo executes personally (a single end-to-end demo session + a focused four-way-reconciliation review).
- **The re-scoped EXT-1 gate flag (§0.1):** NFT/on-chain criteria carry **"AUTO — gated behind feature flag pending EXT-1"** (held in CI behind the flag until the EXT-1 review + the v0.3+ on-chain-workstream revisions land); the **physical-tagging / serial / `SerializedBottle`-ledger criteria are launch-floor (NOT gated)**; the **inventory-ledger criteria are NOT gated.**

**Distribution for Module B v0.3-MVP: ~176 total criteria** — the v0.1 170 (89.4% AUTO / 8.8% MIXED / 1.8% HUMAN) **+ 6 MVP re-baseline criteria (AC-B-MVP-1..6; 4 AUTO / 2 MIXED).** The re-scoped gate moves ~3–5 criteria (`NFCTagApplied`, NFC application, SerializedBottle-ledger creation) from gated → launch-floor; the D16 Stage-8 automation criteria carry an inline "manual-first at launch; automated round-trip deferred" note; the `SupplierPaymentCompleted` criteria carry an inline "E-emits / B-consumes (R4)" note. Paolo's hands-on load: the MIXED items + 1 end-to-end demo session + the focused four-way-reconciliation review.

### §1.2 Build-time usage + §1.3 Sign-off cadence

Consulted from day one, not only at handover: the dev reads the PRD + this doc together; AUTO criteria wire into CI as scaffolding lands (the AUTO PASS rate is a continuous completion signal); AI coding agents treat AUTO criteria as fitness functions; MIXED/HUMAN items are scheduled. **NFT-touching criteria stay EXT-1-GATED in CI until the review + on-chain workstream land; once the gate is lifted they are enabled.** Each criterion lands OPEN → DEMOED → ACCEPTED; Module B is **delivered** when every §2–§6 launch-scope criterion is ACCEPTED (NFT-touching criteria need the EXT-1 gate lifted before they leave OPEN). Sign-off log at §8.

### §1.4 Anchors + §1.5 Format conventions (carried; the v0.3-MVP additions)

PRD §-numbers refer to [`../02-prd/Module_B_PRD_v0.3-MVP.md`](../02-prd/Module_B_PRD_v0.3-MVP.md). BR-B-* refers to its §18. Event names refer to its §19. SerializedBottle FSM → §4.2; InboundBatch FSM → §3.4; Case FSM → §7.1; Stocktake FSM → §12.2; QuarantineRecord lifecycle → §14.1. **(Body §-anchors are unchanged from v1.1 — §0 item 5.)** Conventions: (1) §4 BR statements are verbatim from PRD §18 *(for v0.3-MVP the verbatim statements carry the naming cascade on the catalog-identity rules + the BR-B-Ledger-5 R4 reconciliation)*; (2) §4 BR→AC pointer rows preserve traceability; (3) §6 cross-module criteria verify the Module-B-side surface only; (4) AUTO criteria dependent on consumer modules carry "verified when X lands" notes; **(5) (NEW, v0.3-MVP) MVP re-baseline criteria live in §6.12 (AC-B-MVP-*); NFT/on-chain criteria carry the re-scoped EXT-1 gate flag; D16 automation criteria carry an inline "manual-first at launch; automated round-trip deferred" note; the `SupplierPaymentCompleted` criteria carry an inline "E-emits / B-consumes (R4)" note; the launch-ready serialization criteria carry an inline "launch-floor (un-gated) under the re-scoped EXT-1 gate" note.**

---

## §2 Canonical journeys — end-to-end inventory + provenance flows

Eleven journey buckets: receiving + InboundBatch creation; SerializedBottle assignment + NFC *(launch-ready)* + 1:1 NFT mint *(decoupled)*; NFT burn at shipment *(decoupled; NS fallback)*; Bottle Page render with 6-locale fallback; the four §17 recovery scenarios *(on-chain decoupled; §17.4 ledger side kept)*; stocktake *(auto-reconciliation manual-first)*; inventory adjustment with the Q-CL-6 short-circuit *(FLOOR)*; quarantine-before-trust *(gate FLOOR; cascades manual-first)*; ATP push to Module A *(FLOOR)*; NS four-counter discipline; the Paolo demo.

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-B-J-1** | *(FLOOR)* On Module D `InboundEventPhysicallyAccepted`, Module B creates an InboundBatch with source-path discriminator, source allocation, expected qty, `ownership_flag` from payload, `cost_basis_provisional = true`, `qty_planned_serialize` from the source allocation's `qty_to_serialize`, NS counters = 0, lifecycle = `expected`. | §3.1 + §11.3; DEC-195 | AUTO — emit `InboundEventPhysicallyAccepted` fixture; assert every field + state = `expected` |
| **AC-B-J-2** | *(integrity core FLOOR; the automated round-trip manual-first, N1)* Module B's physical-match check compares Logilize counts vs expected; on match → `expected → received` + ATP push; on variance → `InboundBatchDiscrepancy` to Module D + `partially_received`/`discrepancy`. **At launch the operator opens the discrepancy + records the resolution within the 5-WD window (manual-first — Module D re-opens the InboundEvent on the operator action; the automated round-trip is the deferred seam).** | §11.1 + §11.2; DEC-194 | AUTO — three scenarios (match / short / over); assert event emission + FSM transition + ATP push; assert the DEC-194 split (B = physical match) stands; the automated round-trip arm deferred-with-feature |
| **AC-B-J-3** | *(launch-floor / un-gated under the re-scoped EXT-1 gate; naming cascade)* For the `qty_to_serialize` portion, Logilize applies the NFC tag; Module B consumes the Logilize event + emits `NFCTagApplied` (serial, **Product Reference**, source Allocation, InboundBatch ref, custody, actor, timestamp); the **SerializedBottle record is created** inheriting the InboundBatch's allocation lineage + ownership flag, with **`nft_reference = NULL`** at launch (D12 — back-fillable). | §6.1 + §6.2 + §6.4; DEC-123 | AUTO — drive the serialization flow; assert event shape + SerializedBottle creation + inherited lineage/ownership + `nft_reference` nullable. **Launch-floor — NOT gated** (the WH tag + serial + ledger is launch-ready) |
| **AC-B-J-4** | *(EXT-1-gated — decoupled, D12)* Each `NFCTagApplied` emits exactly one `NFTMinted` (1:1 cardinality); the mint payload references catalog identity (**Product Reference** + **Product Variant**), source Allocation, NFC-UID linkage (serial), mint timestamp; zero PII. | §9.1 + §9.2 + §9.4; DEC-121 + DEC-122 + DEC-029 | AUTO — gated behind EXT-1; assert 1:1 cardinality + payload + no-PII; **back-filled when the on-chain workstream lands** |
| **AC-B-J-5** | *(split — the serial + URL launch-ready; the on-chain reference EXT-1-gated, D12)* NFC tag-write content carries Bottle Page URL + bottle serial *(launch-ready — online verification works)* + on-chain reference *(EXT-1-gated — offline verification rides the decoupled workstream; back-fillable per DEC-124)*. | §6.3; DEC-124 | AUTO — assert serial + URL present at launch (un-gated); the on-chain-reference component gated behind EXT-1 (AC-B-MVP-2 verifies the back-fill design) |
| **AC-B-J-6** | *(split — the SerializedBottle transition + serial late-binding launch-ready; the NFT burn EXT-1-gated, D12; NS fallback)* At shipment: Module C `ShipmentDispatched` → Module S `VoucherShipped` (carries serial / NFT identity) → Module B records the `reserved_for_picking → shipped` transition *(launch-ready)*; **the `BottleNFTBurned` (`reason = shipment_dispatch`) is EXT-1-gated (decoupled — back-fills); for NS / not-yet-minted stock the informational `BottleShippedAsNonSerialized` fires (the universal fallback)**. Module B does NOT subscribe to Module C directly. | §9.5 + §9.6 + §9.8; DEC-134 | AUTO — drive the chain; assert Module B's subscription is Module S only; the SerializedBottle transition + the NS fallback un-gated; the NFT-burn surface gated behind EXT-1 |
| **AC-B-J-7** | *(EXT-1-gated — decoupled, D12)* On-chain burn transaction carries timestamp + reason + NFT-ID + NFC-UID linkage + an opaque anonymised reference (one-way hash of Voucher.id); the literal Voucher.id never crosses to the chain; no Customer.id / Profile.id / shipment recipient / address on-chain. | §9.6; DEC-135 + DEC-024 + DEC-029 | MIXED — gated behind EXT-1; AI inspects on-chain burn payloads + assembles the no-PII audit; Paolo verifies anonymisation discipline holistically |
| **AC-B-J-8** | *(KEPT — the Bottle Page is launch-ready)* Bottle Page renders across all six launch locales (EN/IT/FR/DE/JP/ZH) with cookie-preference > Accept-Language > English fallback + per-attribute fallback (a missing string falls back to EN for that string only). | §16.3; DEC-127 + DEC-031 + DEC-064 | MIXED — AI fetches six-locale renderings (one fully translated + one partial-JP); Paolo confirms readability + per-attribute fallback. **Renders non-NFT content at launch** |
| **AC-B-J-9** | *(KEPT — compliance-adjacent)* Bottle Page provenance trail renders the "in producer cellar … → in NewCo warehouse … → delivered to private cellar …" framing; the data feed contains zero customer identifiers (no Customer.id / Profile.id / Voucher.id / shipment recipient / address); customer-as-anonymous-destination throughout. | §16.4 + §16.6; DEC-128 + DEC-024 | MIXED — AI assembles the data feed for a shipped bottle; Paolo verifies the anonymisation framing + absence of customer-identifying data |
| **AC-B-J-10** | *(EXT-1-gated — decoupled on-chain recovery, D12)* Recovery §17.1 (damaged tag in warehouse): the 5-event on-chain chain (`NFCTagDamagedInCustody` → ops authorisation → `NFCTagReapplied` → `NFTReissued` → `NFTBurnedAsTagDamaged` with the symmetric predecessor/successor chain); SerializedBottle stays `in_storage`; no Module A/S/C cascade. **The physical re-tag + serial re-capture are launch-ready; the on-chain re-mint/burn back-fill.** | §17.1; DEC-129 + DEC-120 | MIXED — gated behind EXT-1; AI drives the scenario + assembles the audit trail; Paolo verifies trace completeness + absence of lifecycle regression |
| **AC-B-J-11** | *(EXT-1-gated — rides the decouple, D12)* Recovery §17.2 (damaged tag post-shipment): `BottlePostShipmentTagIssueReported` + `ProvenanceCertificateIssued` (a non-NFT signed certificate attesting pre-burn provenance); no `NFCTagReapplied`; no re-mint (burn-finality). The Customer Care remedy records in Module S / Module E, NOT Module B. | §17.2; DEC-130 + DEC-051 | MIXED — gated behind EXT-1 (the certificate attests on-chain provenance that back-fills); AI drives the scenario + the certificate sample; Paolo verifies certificate content + the Customer Care boundary |
| **AC-B-J-12** | *(EXT-1-gated — decoupled on-chain recovery, D12)* Recovery §17.3 (NFT lost in wallet): `NFTLossInWalletDetected` + `NFTReissuedDueToWalletLoss` (same NFC tag; `predecessor_status = lost_in_wallet`); the lost NFT is NOT burned (dangling-token, recorded stale); no `NFCTagReapplied`; SerializedBottle lifecycle unaffected. | §17.3; DEC-131 + DEC-120 | MIXED — gated behind EXT-1; AI drives the scenario + the dangling-token trace; Paolo verifies dangling-token correctness + scan-time graceful behaviour |
| **AC-B-J-13** | *(split — the inventory-ledger write-off side launch-floor / KEPT; the NFT-burn portion EXT-1-gated, D12)* Recovery §17.4 (bottle destroyed pre-shipment): `BottleDestroyedInCustody` → **`written_off` (terminal) + `InventoryAdjusted` (`adjustment_type = damage`) + Module A `AllocationPoolDebitedDueToLoss` (launch-floor / KEPT)**; the `BottleNFTBurnedAsDestroyed` on-chain burn is EXT-1-gated. Module S consumes for `VoucherSubstitutionExecuted` only if a bound Voucher pre-existed (rare edge). | §17.4; DEC-132 + DEC-136 + DEC-104 | AUTO (ledger side, un-gated) + gated (the NFT burn) — drive in both modes; assert the write-off lifecycle + the InventoryAdjusted + the pool-debit at launch; the on-chain burn gated behind EXT-1 |
| **AC-B-J-14** | *(integrity discipline FLOOR; the auto-reconciliation + cadence automation manual-first, D16/N1)* Stocktake: supervisor creates a Stocktake with scope + target date + tolerance; FSM `planned → in_progress → variance_review → reconciled`; **at launch the operator schedules manual counts + manual variance review (the tolerance-driven auto-reconciliation + cadence automation deferred); the variance-computation contract + `StocktakeReconciled` stand**; above-tolerance variances resolve via §13 adjustment or §14 QuarantineRecord. | §12.1 + §12.2 + §12.4 + §12.5 + §12.6; DEC-189 | MIXED — AI drives an end-to-end stocktake with mixed variances + the supervisor-workflow trace; Paolo verifies workflow ergonomics + reconciliation summary; the auto-reconcile/cadence arm deferred-with-feature |
| **AC-B-J-15** | *(FLOOR — already manual)* Inventory adjustment: operator proposes (scope + type + qty delta + reason); pre-validation runs the Q-CL-6 committed-inventory protection; on admissible → single-supervisor approval → `InventoryAdjusted` to Module A (ATP push) + Module E (financial event); on rejection → audit-trail entry, no state mutation. | §13.1 + §13.3; DEC-190 | AUTO — three scenarios (admissible approval / admissible rejection / Q-CL-6 short-circuit); assert event emission + state mutation + ATP push |
| **AC-B-J-16** | *(FLOOR — committed-inventory protection; NOT a D16 candidate)* Q-CL-6 short-circuit: a proposed adjustment that would reduce committed inventory below outstanding vouchers is REJECTED at pre-validation; `InventoryShortfallDetected` emits to Module A; the proposal cannot proceed until Module A `VoucherCancelled` releases the commitment first. | §13.4 + §13.5; DEC-190 + DEC-099 | AUTO — set up committed allocation; attempt over-committing adjustment; assert rejection + `InventoryShortfallDetected` + payload; emit `VoucherCancelled`; retry; assert PASS |
| **AC-B-J-17** | *(the gate FLOOR; the automated resolution cascades manual-first, D16/N1)* Quarantine-before-trust: Logilize reports an unmatched entity; a QuarantineRecord is created (`open`); the supervisor resolves via one of four paths (associate / create-new / reject / escalate); resolved records are immutable. **At launch the cross-module cascades on resolution are operator-triggered manual follow-ups (the gate + the 4 paths + immutability stand; the automated cascades deferred-with-feature).** | §14.1 + §14.2 + §14.3; DEC-191 | MIXED — AI drives the 5 triggers + 4 resolution paths + the supervisor-decision trace; Paolo verifies decision-path coherence + audit-trail immutability + the four-way reconciliation discipline; the automated-cascade arm deferred-with-feature |
| **AC-B-J-18** | *(FLOOR — the two-layer guard + the B→A push + the lesser-of read)* ATP push to Module A: every inventory state change (§10.2 list) sources an ATP delta; Module A's cache reflects it strongly-consistently; Module S storefront read returns the lesser of (Module A allocation-pool ATP) and (Module B physical-inventory ATP) per sub-pool. | §10.2 + §10.4 + §10.5; DEC-187 | AUTO — drive each of the 6 event categories; assert the ATP delta + cache update; verify the lesser-of read. *(Composes with Module A's operation-level over-issuance rejection — no `AllocationCapacityExhausted` event)* |
| **AC-B-J-19** | *(FLOOR — doubly load-bearing: Layer 2 for NS + the D12 decouple seam)* NS InboundBatch four-counter discipline: `qty_planned_serialize` / `qty_actually_serialized` / `qty_non_serialized_committed` / `qty_non_serialized_reserved` maintained per batch; the NS ATP formula evaluates correctly at every state change. | §5.1 + §5.2; DEC-186 + DEC-185 | AUTO — drive NS commit + reserve + adjustment cycles; assert counter values + ATP-formula output after each transition |
| **AC-B-J-20** | *(KEPT)* NS → serialized conversion: triggered only by Module A `AllocationSerializationPlanChanged` upward; Module B updates `qty_planned_serialize` + emits the Logilize instruction delta; each new SerializedBottle inherits the batch's allocation lineage; the reverse (serialized → NS) is NOT admitted. | §3.3 + §5.4; DEC-186 + Module A BR-A-Mutability-5 | AUTO — drive plan-increase; assert counter update + Logilize-instruction emission + SerializedBottle creation with inherited lineage; attempt plan-decrease below `qty_actually_serialized`; assert `AllocationSerializationPlanInfeasible` |
| **AC-B-J-21** | *(R4 — E-emits / B-consumes; N3; the v1.1 "at sell-through" framing superseded)* Ownership-flag transition: Module D sets the initial flag at InboundBatch creation; **on the E-emitted `SupplierPaymentCompleted` (Module B consumes it — does NOT emit/derive it)**, Module B records the `PRODUCER → CRURATED` transition as an immutable provenance event; custody history + lineage preserved. **The inventory `ownership_flag` `CRURATED` ledger keys to `SupplierPaymentCompleted` — distinct from Module D's PO-level `NEWCO` title ledger (keyed to `VoucherIssued`) — N3.** | §2.2 + §18.2; BR-B-Ledger-5 + DEC-185 + R4 | AUTO — drive the V2 passive-consignment lifecycle with the **E-emitted** `SupplierPaymentCompleted` trigger; assert `OwnershipTransitioned` + provenance immutability + lineage continuity; **assert Module B has no `SupplierPaymentCompleted` emission path** |
| **AC-B-J-22** | *(HUMAN demo)* End-to-end Paolo demo: PO receiving with physical-match pass + discrepancy (manual-first); NFC + SerializedBottle creation *(launch-ready)*; shipment + NS fallback *(NFT burn EXT-1-flagged)*; Bottle Page render in 3 locales; §17.4 destruction (ledger write-off) live; stocktake with mixed variances (manual-first); inventory adjustment with the Q-CL-6 short-circuit; Logilize quarantine + supervisor resolution; the two-layer no-overselling guard rejection at hold placement. | §1–§17 (full surface) | HUMAN — single session, ~90–120 min, with dev + ops; NFT segments flagged EXT-1-pending; Paolo signs off against this doc |
| **AC-B-J-23** | *(HUMAN — the four-way reconciliation review)* Four-way reconciliation spot-check: Paolo reviews the four primitives (Logilize physical + Module B ledger + Module S commercial + Module E financial) under receiving (DEC-194), stocktake (DEC-189), adjustment (DEC-190); the quarantine-before-trust gate (DEC-191) confirmed as the architectural payoff. **The integrity discipline is FLOOR; only the automation is manual-first (D16).** | §2.4 + §22.1; DEC-185 + DEC-189 + DEC-190 + DEC-191 + DEC-194 | HUMAN — focused 30-min review post-demo; Paolo confirms the four-way discipline holds, the quarantine gate is load-bearing, and the <5% KPI is plausible |
| **AC-B-J-24** | *(EXT-1 gate posture sign-off — re-scoped, D12)* Working-hypothesis sign-off: Paolo confirms every NFT/on-chain criterion stays **EXT-1-GATED** until the blockchain-expert review + the on-chain-workstream revisions land into v0.3+; **the re-scoped gate covers only the on-chain layer — the physical-tagging / serial / ledger criteria are launch-floor (un-gated).** | §0.1 of this doc + §0.1 of PRD; DEC-120 + DEC-121 + DEC-122 + DEC-124 + DEC-131 | HUMAN — Paolo confirms the re-scoped EXT-1 gate posture + the launch-ready serialization boundary |

---

## §3 State machine round-trips — entity FSMs

Module B owns five primary FSMs + the StockPosition view. *(The SerializedBottle FSM is launch-floor / un-gated — the ledger lifecycle is launch-ready; only `nft_reference` decouples.)*

### §3.1 SerializedBottle lifecycle FSM (§4.2) — **launch-floor / un-gated**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-B-FSM-1** | *(launch-floor)* `in_storage → reserved_for_picking → shipped → delivered` on the standard fulfillment path; `BottleStateChanged` emits at each transition (prior + new state + reason + actor + timestamp). | §4.2; DEC-185 | AUTO |
| **AC-B-FSM-2** | *(launch-floor; the §17.4 write-off side kept)* `in_storage → damaged` / `in_storage → lost` / `in_storage → written_off`; `damaged`/`written_off` terminal; `lost → in_storage` only on supervisor approval. | §4.2; DEC-191 | AUTO — drive each transition + assert terminal immobility + the supervisor gate |
| **AC-B-FSM-3** | *(launch-floor)* `reserved_for_picking → in_storage` (cancellation) + `reserved_for_picking → damaged`; cancellation reverses commercial-status + pushes the ATP delta. | §4.2 + §10.2; DEC-187 | AUTO |
| **AC-B-FSM-4** | *(launch-floor)* `shipped → returned` / `delivered → returned`; returned → `in_storage` (restocked) or `damaged`. | §4.2 | AUTO |
| **AC-B-FSM-5** | *(launch-floor)* `consumed` preserved as terminal but N/A at launch (no events); the placeholder reactivates Phase 2+; `adjustment_type = consumption` preserved as placeholder. | §4.2; DEC-068 | AUTO — verify the state is declared but no transition path is exercised at launch |
| **AC-B-FSM-6** | *(launch-floor; FLOOR — provenance immutability)* Every SerializedBottle transition is an immutable provenance event (timestamp, actor, reason); records cannot be edited/deleted; corrections go via a new event. | §4.2 + §18.1; BR-B-Provenance-1 | MIXED — AI drives a transition + edit-attempt; Paolo confirms append-only discipline |

### §3.2 InboundBatch lifecycle FSM (§3.4) — **FLOOR**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-B-FSM-7** | *(FLOOR)* `expected → received` on physical-match pass (received = expected); ATP push; goods enter the salable pool. | §3.4 + §11.3; DEC-195 | AUTO |
| **AC-B-FSM-8** | *(FLOOR; the automated round-trip manual-first, N1)* `expected → partially_received` (short) / `expected → discrepancy` (over or identity mismatch) / `partially_received → discrepancy` / `discrepancy → received` (DiscrepancyResolution closure). | §3.4 + §11.2; DEC-194 | AUTO — drive each transition; assert FSM + event emission; the discrepancy re-open is manual-first at launch |
| **AC-B-FSM-9** | *(FLOOR)* `closed` from `received`/`partially_received` when net inventory zero + no in-flight state; records persist for provenance. | §3.4 + §18.1; BR-B-Provenance-1 | AUTO |
| **AC-B-FSM-10** | *(FLOOR)* InboundBatch identity attributes (source path, source allocation, ownership flag, expected qty) immutable from creation; mutable fields limited to counters, cost-basis flag, lifecycle state. | §3.1; BR-B-Ledger-2 | AUTO — attempt edit of each immutable attribute; assert rejection; verify mutable counters update |

### §3.3 Case integrity FSM (§7.1)

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-B-FSM-11** | `intact → partially_broken → broken`; `intact → broken` single-step admitted. | §7.1; DEC-192 | AUTO — drive each transition + verify member-bottle accounting |
| **AC-B-FSM-12** | Monotonic non-decreasing — `broken` terminal; cannot reconstitute; released bottles retain lineage. | §7.1 + §18.2; BR-B-Ledger-4 | AUTO — attempt `broken → *`; assert rejection; verify lineage preservation |
| **AC-B-FSM-13** | `CaseIntegrityChanged` emits at each transition (prior + new state + reason + member-bottle deltas). | §7.1 + §19.1 | AUTO |

### §3.4 QuarantineRecord lifecycle (§14.1) — **gate FLOOR; cascades manual-first**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-B-FSM-14** | *(FLOOR)* `open → under_investigation → resolved`; on resolution the path discriminator (associate / create-new / reject / escalate) is recorded. | §14.1 + §14.2; DEC-191 | AUTO — drive each path; assert FSM + path-recording fidelity |
| **AC-B-FSM-15** | *(FLOOR)* Resolved records immutable — no edit-after-resolution; a correction creates a NEW record (or a §13 adjustment); the original stays as the audit anchor. | §14.3 + §18.1; BR-B-Provenance-4 | AUTO — attempt edit; assert rejection; verify the correction links to the original |

### §3.5 Stocktake 4-state FSM (§12.2) — **discipline FLOOR; auto-reconciliation manual-first (D16)**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-B-FSM-16** | *(discipline FLOOR; auto-reconciliation manual-first, D16)* `planned → in_progress → variance_review → reconciled`; **at launch the transitions are operator-driven (the tolerance-driven auto-reconciliation + cadence automation deferred; the FSM + variance computation stand).** | §12.2; DEC-189 | AUTO — drive the FSM; the auto-reconcile/cadence arm deferred-with-feature |
| **AC-B-FSM-17** | Monotonic; a stocktake whose review surfaces unrecognised entities stays in `variance_review` until the QuarantineRecord resolutions + adjustments complete. | §12.2 + §14 | AUTO |
| **AC-B-FSM-18** | On `variance_review → reconciled`, `StocktakeReconciled` emits (scope, resolution summary, supervisor, timestamp). | §12.6 | AUTO |

### §3.6 StockPosition aggregated view (§8) — **FLOOR; not a primary FSM; naming cascade**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-B-FSM-19** | *(FLOOR + naming cascade)* StockPosition is a 5-dimension view at `(Product Reference, Warehouse, Case Configuration, Allocation, Ownership)`; no lifecycle state; recomputed on every §10.2 event that mutates a contributing entity. | §8.1; DEC-196 | AUTO — drive each contributing event type; assert the cell reflects the delta. *(Naming cascade: `Bottle Reference → Product Reference`)* |
| **AC-B-FSM-20** | *(FLOOR)* Three headline quantities: `total_quantity` (serialized count + NS residual), `committed_quantity`, `available_quantity` (`total − committed − reserved − quarantined − under_adjustment`). | §8.1; DEC-196 | AUTO — exercise each quantity path across mixed serialized + NS |
| **AC-B-FSM-21** | *(FLOOR)* Sub-pool decomposition per allocation: `available_serialized` + `available_non_serialized`; both feed the §10.5 two-layer guard. | §10.6; DEC-196 + DEC-186 | AUTO |

---

## §4 Business rule enforcement (BRs from PRD §18)

One criterion per BR (verbatim inline per DEC-074; naming cascade where they reference PR/Wine; the R4 reconciliation on BR-B-Ledger-5).

### §4.1 Provenance chain discipline (BR-B-Provenance-1..4)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-B-BR-Provenance-1** | Every state / custody / ownership / commercial-status change on a SerializedBottle is an immutable provenance event; records cannot be edited/deleted; the internal chain extends beyond the customer-facing trail *(the on-chain portions decoupled — D12)*. | AUTO — drive each chain category + assert emission + immutability (cross-refs AC-B-FSM-6/13/15) |
| **AC-B-BR-Provenance-2** | NS adjustments recorded as quantity deltas on the InboundBatch (scope discriminator `per-batch`; provenance at the batch level). | AUTO — drive an NS adjustment; assert scope + counter update + batch-level provenance |
| **AC-B-BR-Provenance-3** | The chain is append-only — a correction is a NEW event; the historical event stays intact. | AUTO — drive a correction; assert original preserved + new corrective event linked |
| **AC-B-BR-Provenance-4** | Resolved QuarantineRecords, reconciled Stocktakes, approved InventoryAdjustments are immutable. | AUTO — covered by AC-B-FSM-15 + analogous tests |

### §4.2 Ledger-authority invariants (BR-B-Ledger-1..5)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-B-BR-Ledger-1** | Every `SerializedBottle.serial` globally unique; cannot be duplicated/reused. | AUTO — attempt duplicate-serial ingestion; assert rejection |
| **AC-B-BR-Ledger-2** | Allocation-lineage UUID immutable on every InboundBatch / SerializedBottle / Case / StockPosition cell; never interchangeable across allocations. | AUTO — covered by AC-B-FSM-10 + parallel tests; assert cross-allocation fungibility rejected at hold placement |
| **AC-B-BR-Ledger-3** | `qty_actually_serialized` irreversible; serialized → NS NOT admitted. | AUTO — covered by AC-B-J-20 |
| **AC-B-BR-Ledger-4** | The Case FSM monotonic; `broken` terminal; released bottles retain lineage. | AUTO — covered by AC-B-FSM-12 |
| **AC-B-BR-Ledger-5** | *(R4 — E-emits / B-consumes; the trap)* Module B records + executes the `PRODUCER → CRURATED` transition but does not decide it — Module D sets the initial flag at InboundBatch creation; **Module E emits `SupplierPaymentCompleted`** (the payment executor; Module B consumes it — **does NOT emit/derive it**); Module B preserves custody history + lineage. *(N3: the `CRURATED` inventory ledger keys to `SupplierPaymentCompleted`; distinct from Module D's `NEWCO` PO-title ledger, keyed to `VoucherIssued`.)* | AUTO — covered by AC-B-J-21; **additionally assert Module B has no `SupplierPaymentCompleted` emission path** (the E-emits contract) |

### §4.3 No-overselling guards (BR-B-NoOversell-1..3) — **FLOOR**

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-B-BR-NoOversell-1** | *(FLOOR)* Both layers pass at hold placement / voucher issuance: Module A allocation-pool (`qty − issued ≥ 0`) + Module B physical (`physical_in_storage − reserved − quarantined − under_adjustment ≥ 0`); either failure rejects. | AUTO — drive 4 scenarios (both pass / A fail / B fail / both fail). *(Composes with Module A's operation-level over-issuance rejection — no `AllocationCapacityExhausted` event)* |
| **AC-B-BR-NoOversell-2** | *(FLOOR)* The guard composes per sub-pool (SERIALIZED rejected if `atp_serialized` < qty; NON_SERIALIZED rejected if `atp_non_serialized` < qty); cross-sub-pool fungibility NOT admitted. **Intact even when the serialized sub-pool is dormant (the D12 decouple).** | AUTO — drive SERIALIZED-offer hold with serialized ATP short / NS plenty; assert rejection; verify no cross-sub-pool substitution |
| **AC-B-BR-NoOversell-3** | *(FLOOR)* Every §10.2 event sources an ATP push delta to Module A; strongly consistent at the transactional boundary *(mechanics tech, DEC-073)*. | AUTO — covered by AC-B-J-18 |

### §4.4 Committed-inventory protection (BR-B-Commit-1..2) — **FLOOR**

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-B-BR-Commit-1** | *(FLOOR — NOT a D16 candidate)* Committed inventory cannot be diverted for adjustments without first releasing the commitment via Module A `VoucherCancelled`; rejection emits `InventoryShortfallDetected`. | AUTO — covered by AC-B-J-16 |
| **AC-B-BR-Commit-2** | Event-consumption diversion requires the same release-first discipline; N/A at launch (no events); reactivates Phase 2+. | AUTO — assert `consumption` preserved as placeholder; no path activates it at launch |

### §4.5 Quarantine-before-trust (BR-B-Quarantine-1..2) — **gate FLOOR**

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-B-BR-Quarantine-1** | *(FLOOR — the gate)* Module B never creates inventory records from unverified Logilize data; unknown entities quarantine; composes through every Logilize stream. | AUTO — covered by AC-B-J-17; inspect each of the 5 streams; assert each routes unmatched entities to QuarantineRecord |
| **AC-B-BR-Quarantine-2** | *(gate FLOOR; the automated cascade manual-first, D16)* Each resolution captures supervisor identity, decision path, reason, resulting mutation; immutable. | AUTO — drive resolution; verify all fields; attempt edit; assert rejection. The automated cross-module cascade is manual-first at launch (the gate stands) |

### §4.6 Recorder-not-gatekeeper on layered breakability (BR-B-Breakability-1..2)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-B-BR-Breakability-1** | Module B records + executes case-breaking; does NOT re-derive or gate the `Layer 2 OR Layer 3` effective rule (composed upstream). | AUTO — assert no `effective_unbreakable` computation in Module B; verify the rule is read from upstream |
| **AC-B-BR-Breakability-2** | When `effective_unbreakable = true`, the case is dispatched whole with one voucher per bottle; voucher count = bottle count; overage structurally impossible. | AUTO — drive an effective-unbreakable case through dispatch; assert one-voucher-per-bottle |

### §4.7 Storage-location + bottle-days exposure (BR-B-Storage-1..2)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-B-BR-Storage-1** | Warehouse-level storage location per SerializedBottle (serialized) + per InboundBatch (NS) for Cellar + Bottle Page; sub-warehouse detail Logilize-internal (already-deferred). *(Stream B1 — the R3 migration target.)* | AUTO — assert warehouse-granularity only; sub-warehouse fields absent from Module B's surface |
| **AC-B-BR-Storage-2** | Bottle-days-in-storage per allocation lineage exposed as data; storage-fee pricing + customer-level billing live in Module S + Module E; Module B does NOT hold pricing or know the voucher's customer. | AUTO — query the bottle-days path; assert data returned + absence of pricing/customer fields |

### §4.8 Receiving + adjustment + stocktake reconciliation discipline (BR-B-Reconcile-1..4)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-B-BR-Reconcile-1** | *(integrity core FLOOR)* DEC-194 — Module D = documents in order; Module B = physical match; `InboundBatchDiscrepancy` flows back to Module D. | AUTO — covered by AC-B-J-2 |
| **AC-B-BR-Reconcile-2** | *(discipline FLOOR; auto-reconciliation manual-first, D16)* Variance = `logilize_count − ledger_count`; within-tolerance auto-reconcile is **manual-first at launch** (operator variance review); above-tolerance → `variance_review`. | AUTO — covered by AC-B-J-14; the auto-reconcile arm deferred-with-feature |
| **AC-B-BR-Reconcile-3** | Single-supervisor-approval at the PRD layer; NewCo Operations sets the role/authority policy downstream. | AUTO — verify no multi-tier approval imposed at the PRD layer; the supervisor gate present + parameterised |
| **AC-B-BR-Reconcile-4** | *(FLOOR-adjacent)* Provisional cost basis at PHYSICALLY_ACCEPTED; finalized at COST_FINALIZED; goods sellable at the physical-match transition; cost finalization independent of availability. | AUTO — drive a two-phase cost-basis lifecycle; assert (a) sellable at provisional, (b) update on `InboundEventCostFinalized`, (c) dispatch-moment read fidelity |

### §4.9 Anonymisation discipline (BR-B-Anonymisation-1..3)

| AC ID | BR statement (verbatim) | Verification |
|---|---|---|
| **AC-B-BR-Anonymisation-1** | *(EXT-1-gated — on-chain, D12)* No Customer.id / Profile.id / Voucher.id / shipment recipient / address ever crosses to the chain; only opaque references. | AUTO — gated behind EXT-1; inspect on-chain mint + burn payloads; assert absence of all PII categories. **The invariant stands when the on-chain workstream lands** |
| **AC-B-BR-Anonymisation-2** | *(KEPT — compliance-adjacent)* The Bottle Page provenance trail uses "delivered to private cellar"; zero customer identifiers in the data feed. | MIXED — AI assembles a sample of feeds (pre/post-burn + the recovery scenarios); Paolo verifies the framing + no breadcrumbs |
| **AC-B-BR-Anonymisation-3** | *(KEPT)* The Bottle Page is public, anonymous, not a personalisation surface; no logged-in-Customer code path. | AUTO — inspect the data contract; assert no Customer-session/auth-header consumption; same data for anonymous + logged-in |

---

## §5 Domain event emission and consumption

Module B emits ~27 events, consumes ~10, observes ~4. *(Naming cascade on PR-referencing payloads; the inventory-ledger events FLOOR; the NFT/on-chain events EXT-1-gated; `NFCTagApplied` + `BottleShippedAsNonSerialized` launch-floor.)*

### §5.1 Serialization + digital-provenance lifecycle events

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-B-EVT-1** | *(launch-floor / un-gated under the re-scoped gate — moved OUT of the v0.1 gated set)* `NFCTagApplied` fires on Vinlock + Logilize physical tag application; carries serial, **Product Reference**, source Allocation, InboundBatch ref, actor, timestamp. | §6.4 + §19.1; DEC-123 | AUTO — assert emission + payload. **Launch-floor — NOT gated** (the serialization workflow is launch-ready); the 1:1 `NFTMinted` trigger is decoupled |
| **AC-B-EVT-2** | *(EXT-1-gated — decoupled, D12)* `NFTMinted` fires after `NFCTagApplied` (1:1); carries NFT identifier, serial, catalog reference (**PR + Product Variant**), source Allocation, mint timestamp, wallet reference. | §9.4 + §19.1; DEC-121 + DEC-122 | AUTO — gated behind EXT-1 |
| **AC-B-EVT-3** | *(split — the SerializedBottle-creation side launch-floor; the NFT-correlation side EXT-1-gated)* `BottleSerialized` composite marks SerializedBottle creation (correlating `NFCTagApplied` + `NFTMinted`). | §19.1 | AUTO — the SerializedBottle-creation assertion un-gated; the NFT-correlation gated behind EXT-1 |
| **AC-B-EVT-4** | *(EXT-1-gated — decoupled, D12)* `BottleNFTBurned` parameterised `reason ∈ {shipment_dispatch, tag_damaged_in_custody, bottle_destroyed_in_custody}`; standard burn from `VoucherShipped`; recovery burns from §17.1/§17.4. | §9.6 + §9.7 + §19.1; DEC-134 + DEC-129 + DEC-132 | AUTO — gated behind EXT-1; drive each `reason` |
| **AC-B-EVT-5** | *(launch-floor / un-gated — the NS universal fallback)* `BottleShippedAsNonSerialized` fires for NS bottles at shipment (informational; payload mirrors with `null` NFT fields; no state change). | §5.6 + §9.5 + §19.1; DEC-133 + DEC-186 | AUTO — drive NS shipment; assert emission + null-field discipline + no state change. **Carries every shipment when the on-chain workstream slips** |

### §5.2 Recovery-scenario variants — **EXT-1-gated (decoupled on-chain recovery, D12); the §17.4 ledger side kept**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-B-EVT-6..9** | *(EXT-1-gated)* §17.1 chain: `NFCTagDamagedInCustody`, `NFCTagReapplied` (predecessor ref), `NFTReissued` (`predecessor_nft_id`, `reason` discriminator), `NFTBurnedAsTagDamaged` (`successor_nft_id`). | §17.1.2 + §19.1; DEC-129 + DEC-120 | AUTO — gated behind EXT-1 *(the physical re-tag launch-ready; the on-chain chain back-fills)* |
| **AC-B-EVT-10/11** | *(EXT-1-gated — rides the decouple)* §17.2: `BottlePostShipmentTagIssueReported`, `ProvenanceCertificateIssued` (Module B owns certificate generation). | §17.2.2 + §19.1; DEC-130 | AUTO — gated behind EXT-1 |
| **AC-B-EVT-12/13** | *(EXT-1-gated)* §17.3: `NFTLossInWalletDetected`, `NFTReissuedDueToWalletLoss` (same NFC tag; `predecessor_status = lost_in_wallet`; verify NO `NFCTagReapplied`). | §17.3.2 + §19.1; DEC-131 + DEC-120 | AUTO — gated behind EXT-1 |
| **AC-B-EVT-14** | *(split — the destruction-event recording launch-floor; the NFT-burn portion EXT-1-gated)* `BottleDestroyedInCustody` fires on §17.4 destruction (operator-initiated; serial, source Allocation, cause, actor, timestamp). | §17.4.2 + §19.1; DEC-132 | AUTO — the destruction-event recording un-gated (the write-off ledger side, AC-B-J-13); the on-chain burn gated |
| **AC-B-EVT-15** | *(EXT-1-gated — decoupled)* `BottleNFTBurnedAsDestroyed` (`reason = bottle_destroyed_in_custody` + write-off cross-reference). | §17.4.2 + §19.1; DEC-132 | AUTO — gated behind EXT-1 |

### §5.3 Stage-8 inventory-ledger events — **FLOOR (KEPT, un-gated)**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-B-EVT-16** | *(FLOOR)* `BottleStateChanged` per SerializedBottle lifecycle transition; sources the §10.2 ATP push; carries ref, prior + new state, reason, actor, timestamp. | §4.2 + §10.2 + §19.1; DEC-187 | AUTO |
| **AC-B-EVT-17** | *(R4 — E-emits / B-consumes; N3)* `OwnershipTransitioned` fires on `PRODUCER → CRURATED`; **triggered by the E-emitted `SupplierPaymentCompleted`** (Module B consumes — does NOT emit it); recorded for provenance. *(The `CRURATED` ledger keys to `SupplierPaymentCompleted`, distinct from Module D's `NEWCO` PO-title ledger — N3.)* | §2.2 + §19.1; BR-B-Ledger-5 + DEC-185 + R4 | AUTO — covered by AC-B-J-21 |
| **AC-B-EVT-18** | *(FLOOR)* `InboundBatchCreated` on InboundBatch creation (source path, source allocation, expected qty, ownership flag, cost-basis = provisional, serialization plan). | §3.1 + §11.3 + §19.1; DEC-195 | AUTO — covered by AC-B-J-1 |
| **AC-B-EVT-19** | *(FLOOR)* `InboundBatchStateChanged` on lifecycle transitions; sources ATP push on availability-affecting transitions. | §3.4 + §19.1; DEC-195 + DEC-187 | AUTO |
| **AC-B-EVT-20** | *(FLOOR; the automated round-trip manual-first, N1)* `InboundBatchDiscrepancy` on physical-count variance; consumed by Module D; carries InboundBatch + upstream InboundEvent refs, variance type, variance qty, Logilize report, actor, timestamp. **At launch the operator drives the round-trip (manual-first); the event + the DISCREPANCY state stand.** | §11.2 + §19.1; DEC-194 | AUTO — drive each variance type; verify payload + Module D consumption; the automated round-trip deferred-with-feature |
| **AC-B-EVT-21** | `BatchSerializationDiscrepancy` on plan-vs-actual divergence; resolution paths (Module A plan update / new Logilize instruction / escalate). | §3.5 + §19.1 | AUTO — drive each cause; assert emission + resolution-path tracking |
| **AC-B-EVT-22** | *(FLOOR)* `InventoryAdjusted` on supervisor approval; parameterised `adjustment_type` + scope discriminator; consumed by Module A (ATP push) + Module E (financial event). | §13.3 + §19.1; DEC-190 + DEC-072 | AUTO — drive each type; verify payload + scope + downstream consumption |
| **AC-B-EVT-23** | *(FLOOR)* `InventoryShortfallDetected` on the Q-CL-6 rejection; consumed by Module A; carries affected allocation, proposed scope/type/qty, the negative shortfall delta, actor, timestamp. | §13.4 + §19.1; DEC-190 | AUTO — covered by AC-B-J-16 |
| **AC-B-EVT-24** | *(discipline FLOOR; auto-reconciliation manual-first, D16)* `StocktakeReconciled` at the terminal state (scope, resolution summary, supervisor, timestamp). | §12.6 + §19.1; DEC-189 | AUTO — covered by AC-B-FSM-18 + AC-B-J-14 |
| **AC-B-EVT-25** | `StocktakePlanned` / `StocktakeStarted` on lifecycle transitions (audit anchors). | §12.2 + §19.1; DEC-189 | AUTO |
| **AC-B-EVT-26** | *(gate FLOOR)* `BottleQuarantined` on entry; sources ATP push (excluded while quarantined). | §14 + §10.2 + §19.1; DEC-191 + DEC-187 | AUTO |
| **AC-B-EVT-27** | *(gate FLOOR; the automated cascade manual-first, D16)* `BottleQuarantineResolved` on exit; sources ATP push (restored on associate/create-new; permanently excluded on reject). **The automated cross-module cascade on resolution is manual-first at launch.** | §14.2 + §10.2 + §19.1; DEC-191 + DEC-187 | AUTO — drive each resolution path; assert ATP behaviour; the automated-cascade arm deferred-with-feature |
| **AC-B-EVT-28** | `CaseIntegrityChanged` on Case FSM transition (ref, prior + new state, member-bottle deltas, actor, timestamp). | §7.1 + §19.1; DEC-192 | AUTO — covered by AC-B-FSM-13 |

### §5.4 N/A at launch — deferred events

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-B-EVT-29** | `ConsignmentPlacementRecorded` / `ConsignmentSellThroughRecorded` OUT at launch (already-deferred); reactivate when B2B-customer scope expands. | §19.1 N/A + §21; DEC-193 + DEC-068 | AUTO — assert neither appears in the launch event catalogue |

### §5.5 Consumed events

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-B-EVT-30** | *(FLOOR)* Consume Module D `InboundEventPhysicallyAccepted` — the InboundBatch creation trigger. | §19.2 + §11.3; DEC-195 | AUTO — covered by AC-B-J-1 |
| **AC-B-EVT-31** | *(FLOOR)* Consume Module D `InboundEventCostFinalized` — the cost-basis flip provisional → finalized. | §19.2 + §3.2; DEC-195 | AUTO — drive Phase 2; verify the flag flip + landed-cost update |
| **AC-B-EVT-32** | Consume Module D `ConsignmentReceiptRecorded` (V2) — composes into a single InboundBatch. | §19.2 + §3.1; DEC-011 | AUTO — drive V2 intake; assert single InboundBatch per co-fired pair |
| **AC-B-EVT-33** | *(split — the NFT burn EXT-1-gated; the NS path un-gated; D12)* Consume Module S `VoucherShipped` — the NFT-burn trigger for serialized stock *(EXT-1-gated — back-fills)*; for NS the informational `BottleShippedAsNonSerialized` fires *(un-gated — the universal fallback)*. Module B does NOT subscribe to Module C directly. | §19.2 + §9.5; DEC-134 | AUTO — covered by AC-B-J-6; assert the Module S subscription + Module C-event absence; the burn arm gated, the NS arm un-gated |
| **AC-B-EVT-34/35** | Consume Module A `AllocationCreated` (serialization-pipeline pacing) + `AllocationActivated` (gating + sellability coherence). | §19.2; DEC-187 | AUTO |
| **AC-B-EVT-36** | Consume Module A `AllocationSerializationPlanChanged` (from `AllocationSubPoolRebalanced` + `AllocationNonSerializedOptOutChanged`) — update `qty_planned_serialize`. | §19.2 + §3.3 + §5.4; DEC-186 | AUTO — covered by AC-B-J-20 |
| **AC-B-EVT-37** | Consume Module A `AllocationCapacityIncreased` / `AllocationCapacityDecreased` (ATP recalc at the StockPosition aggregation). | §19.2; DEC-196 + DEC-187 | AUTO |
| **AC-B-EVT-38** | *(FLOOR)* Consume Module A `VoucherCancelled` — releases the committed-inventory commitment (allows a previously-blocked §13 adjustment). | §19.2 + §13.5; DEC-099 + DEC-190 | AUTO — covered by AC-B-J-16 (release path) |
| **AC-B-EVT-39** | *(R4 — ⚠️ the trap; E-emits / B-consumes; the v1.1 "Phase C cascade anticipated" now settled)* Consume **Module E** `SupplierPaymentCompleted` (E-emitted — the payment executor) → trigger `OwnershipTransitioned` (`PRODUCER → CRURATED`). **Module B does NOT emit or derive this event.** | §19.2 + §2.2; BR-B-Ledger-5 + R4 | AUTO — covered by AC-B-J-21; **assert the source is Module E (E-emits) and Module B has no emission path** |
| **AC-B-EVT-40** | Consume Logilize integration events sourcing the 5 inventory-state streams (B1–B5); mechanics tech (DEC-073). | §19.2 + §15.1; DEC-188 + DEC-123 | AUTO — drive each stream's fixtures; assert correct per-stream behaviour |

### §5.6 Observed-but-not-Module-B-owned + §5.7 emission semantics

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-B-EVT-41/42/43** | Module B does NOT emit `AllocationPoolDebitedDueToLoss` (Module A — §17.4; the ledger write-off side observed), `CustomerServiceRemedyApplied` (Module S/Care — §17.2), `VoucherSubstitutionExecuted` (Module S — §17.4 edge); observes only. | §19.3 | AUTO — inspect the emitted-event registry; assert each absent; verify Module B observes the cascades |
| **AC-B-EVT-44** | Module B does NOT subscribe to Module C events directly (`BottlePicked`/`ShipmentDispatched`/`BottleDelivered`); observes via the Module S `VoucherShipped` chain. | §19.3 + §9.5; DEC-134 | AUTO — inspect the subscription registry; assert Module C events absent; `VoucherShipped` present |
| **AC-B-EVT-45/46** | Cascading events within a recovery sequence / workflow are emitted in causal order (within-transaction); every event carries the standard audit envelope (event id, source entity ref, timestamp, actor). | §19.4 + §19 intro | AUTO — instrument the event stream; assert in-transaction ordering + envelope completeness |

---

## §6 Cross-module contracts + boundary respect

Module B is downstream of Module 0 / K / A / D / E, sibling cross-link with Module S + Module C. Boundary discipline is enforced by what Module B does NOT do. *(The named B↔C contracts — StockPosition, serialized-bottle identity, Stream B1 / the shared discrepancy queue, the Bottle Page link / Cellar data-source switch, the NFT-burn chain — are surfaced here for the Module C session.)*

### §6.1 InboundBatch creation gated by Module D (DEC-194 + DEC-195) — **FLOOR**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-B-XM-1** | *(FLOOR)* InboundBatch created ONLY on Module D `InboundEventPhysicallyAccepted`; no autonomous creation. | §3.1 + §11.3; DEC-195 | AUTO — assert single entry point |
| **AC-B-XM-2** | *(FLOOR; the automated round-trip manual-first, N1)* The receiving-discrepancy authority split (D = documents; B = physical match); `InboundBatchDiscrepancy` flows back, re-opening the InboundEvent into DISCREPANCY without retroactively invalidating live batches. | §11.1 + §11.2; DEC-194 | AUTO — covered by AC-B-J-2 + AC-B-EVT-20; assert non-retroactivity; the round-trip manual-first |
| **AC-B-XM-3** | *(FLOOR)* Cost basis provisional at `InboundEventPhysicallyAccepted` / finalized at `InboundEventCostFinalized`; goods available at the physical-match transition; finalization independent of availability. | §3.2 + §11.3; DEC-195 | AUTO — drive a two-phase lifecycle with sales between the phases |
| **AC-B-XM-4** | Cost-basis referenced at dispatch; Module C's late-binding dispatch reads InboundBatch cost basis + propagates to Module S `VoucherShipped` + Module E settlement; Module B records the attribute (DEC-072 — Module E + Xero decide GL). | §3.2 + §20.2; DEC-072 + DEC-142 | MIXED — AI assembles a cost-basis propagation trace (provisional-at-dispatch + finalized-after-dispatch); Paolo confirms fidelity + the GL boundary |

### §6.2 ATP push to Module A (DEC-187) — **FLOOR**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-B-XM-5** | *(FLOOR)* The ATP feed is Module B → Module A push; Module A maintains a cached ATP per allocation; hold placement reads the cache. | §10.2; DEC-187 | AUTO — covered by AC-B-J-18 |
| **AC-B-XM-6** | *(FLOOR)* The feed is sourced by six event categories (`BottleStateChanged`, `InventoryAdjusted`, `OwnershipTransitioned`, `BottleQuarantined`/`Resolved`, `StocktakeReconciled`, NS-counter mutations). | §10.2; DEC-187 | AUTO — drive each category; assert the ATP delta arrives |
| **AC-B-XM-7** | Module A's cache rebuilds from Module B's StockPosition on cold start / outage recovery / operator-tick; strongly consistent; hold placement rejects on stale-beyond-tolerance. *(The cadence + tolerance are tech, DEC-073 — Module A's contract.)* | §10.2; DEC-187 + DEC-196 | AUTO — drive a cache-rebuild; assert StockPosition is the source; verify rejection on stale-cache |
| **AC-B-XM-8** | *(FLOOR — a Module S ratified contract, Q4)* Module S reads the LESSER of (Module A allocation-pool ATP) and (Module B physical ATP) per sub-pool; the lesser is the available-to-sell quantity; a missing layer returns conservatively. | §10.4 + §10.5; DEC-187 + Q-CL-5 | AUTO — set up each limiting layer; assert Module S returns the lesser |

### §6.3 NFT burn coordination with Module S (DEC-134) — **EXT-1-gated (the burn); the NS path un-gated; a named B↔C contract**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-B-XM-9** | *(EXT-1-gated — decoupled, D12)* The NFT burn fires through Module C `ShipmentDispatched` → Module S `VoucherShipped` (carries serial / NFT identity) → Module B `BottleNFTBurned` (`reason = shipment_dispatch`). | §9.5; DEC-134 | AUTO — gated behind EXT-1; covered by AC-B-J-6 |
| **AC-B-XM-10** | *(launch-floor / un-gated — the NS universal fallback)* For NS stock, Module B is no-op on the NFT-burn surface for `VoucherShipped`; reads the bound Allocation `serialization_type`; if `NON_SERIALIZED`, no burn + the informational `BottleShippedAsNonSerialized` fires. | §9.5 + §5.6; DEC-133 + DEC-186 | AUTO — drive NS shipment; assert no burn + informational event. **Un-gated** |
| **AC-B-XM-11** | *(launch-floor — the subscription boundary)* Module B subscribes to Module S, not Module C, for the dispatch chain (Module S owns the customer-facing shipment-state authority). | §9.5; DEC-134 | AUTO — inspect the subscription contract; verify Module C absent + Module S `VoucherShipped` is the entry point |

### §6.4 Bottle Page data-source contract (DEC-126 + DEC-127 + DEC-128) — **KEPT; the NFT/chain-link content decoupled; a named B↔C contract**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-B-XM-12** | *(KEPT)* Module B is system of record for Bottle Page data (per-bottle state; allocation context; NFT reference *(nullable — decoupled)*; chain *(decoupled)*); the render surface is tech (DEC-073). | §16.1; DEC-126 | AUTO — assert the data layer present + render surface absent |
| **AC-B-XM-13** | *(KEPT + naming cascade)* The data feed reads four sources: Module 0 (**Product Reference** + **Product Variant** + Product-Master prose + i18n content) + Module A (Allocation lineage) + Module B (SerializedBottle / NFT / chain) + Module K (Producer description); Module B reads + joins, does not replicate. | §16.2 + §20.2; DEC-126 + DEC-127 | AUTO — verify each source wired + no upstream replication. *(Naming cascade)* |
| **AC-B-XM-14** | *(KEPT)* The 6-locale fallback chain (cookie > Accept-Language > English) + per-attribute fallback. | §16.3; DEC-127 + DEC-031 + DEC-064 + DEC-027 | MIXED — covered by AC-B-J-8 |
| **AC-B-XM-15** | *(KEPT)* The customer-facing trail is the public slice of the internal provenance chain; internal-only events (ownership transitions, stocktake, adjustments, quarantine) stay off the Bottle Page. | §16.4; DEC-128 + DEC-185 | MIXED — AI generates a render for a bottle with rich internal history; Paolo verifies internal-only events filtered out |
| **AC-B-XM-16** | *(EXT-1-gated — decoupled, D12)* Pre-burn vs post-burn Bottle Page variants (derived from lifecycle + NFT-reference status). | §16.5; DEC-126 + BMD §6.8 | AUTO — gated behind EXT-1; at launch (`nft_reference = NULL`) the non-NFT content renders; the variant distinction back-fills |

### §6.5 QuarantineRecord cross-link with Module D (DEC-191) — **integrity interlock FLOOR; the cascade manual-first (N1)**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-B-XM-17** | *(integrity interlock FLOOR; the automated cost-basis reconciliation manual-first, N1)* When a QuarantineRecord resolution associates the entity with an existing InboundBatch + affects qty, **at launch the operator records the follow-up cost-basis reconciliation manually** (the observation + conditional follow-up stand; the automated round-trip is the deferred seam — identical to Module D §13.4). | §14.4; DEC-191 + DEC-195 | AUTO — drive resolution-via-association; assert the follow-up only on the qty-affecting path; the automated round-trip deferred-with-feature |
| **AC-B-XM-18** | *(integrity FLOOR)* Quarantine-trigger inconsistency at receiving: entities land in a QuarantineRecord + an `InboundBatchDiscrepancy` is also emitted to Module D for cross-reference. | §11.2 + §14.1; DEC-191 + DEC-194 | AUTO — drive an unmatchable Logilize event at receiving; assert both |

### §6.6 `InventoryAdjusted` ingestion by Module E (DEC-190 + DEC-072)

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-B-XM-19** | `InventoryAdjusted` (`damage`/`loss`/`written_off`) consumed by Module E for the financial event; Module B records the operational event; Module E records the financial event; Xero decides GL. | §13.3 + §20.2; DEC-190 + DEC-072 | AUTO — drive each type; assert Module E consumption + that Module B computes no GL |
| **AC-B-XM-20** | `InventoryAdjusted` (`recount`/`found`) sources ATP push to Module A; Module B emits unconditionally; consumers dispatch on event type. | §13.3; DEC-187 + DEC-190 | AUTO — drive recount + found; assert ATP push |
| **AC-B-XM-21** | The `adjustment_type` enum covers six values; `consumption` + `transfer` are N/A at launch (placeholders; carry — do not re-cut). | §13.2; DEC-190 + DEC-068 + DEC-155 | AUTO — inspect the enum; verify the six values + placeholder behaviour |

### §6.7 Two-layer no-overselling guard (Q-CL-5) — **FLOOR**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-B-XM-22** | *(FLOOR)* Both layers pass at hold placement / voucher issuance (Module A `qty − issued ≥ 0` + Module B physical); either failure rejects; evaluated at the transactional boundary. | §10.5 + §18.3; BR-B-NoOversell-1 + Q-CL-5 | AUTO — covered by AC-B-BR-NoOversell-1. *(Module A over-issuance = operation-level rejection, no event)* |
| **AC-B-XM-23** | *(FLOOR)* The guard composes per sub-pool; cross-sub-pool fungibility NOT admitted. | §10.5 + §18.3; BR-B-NoOversell-2 | AUTO — covered by AC-B-BR-NoOversell-2 |

### §6.8 Logilize stream split (DEC-188) — **R3; the named B↔C contracts**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-B-XM-24** | *(a named B↔C contract — Stream B1 = the R3 migration target)* Module B owns 5 inventory-state streams (B1 storage-location migrated from C; B2 receiving + physical-match; B3 stocktake; B4 adjustment; B5 quarantine). | §15.1; DEC-188 | AUTO — assert 5 named streams + correct concern per stream |
| **AC-B-XM-25** | *(R3)* Module C retains 4 fulfilment streams (C1–C4); the bottle-state vs inventory-state boundary preserved. | §15.2; DEC-188 | AUTO — assert 4 fulfilment streams + no overlap with B's 5 |
| **AC-B-XM-26** | *(a named B↔C contract — the Cellar data-source switch)* The Cellar render data source switches from Logilize-direct (v0.1) to Module B-summary (v0.3); Module C reads the warehouse-level summary from Module B. | §15.2 + §20.2; DEC-188 + DEC-154 | MIXED — AI drives a Cellar query + the data-source-switch trace; Paolo verifies user-facing continuity |
| **AC-B-XM-27** | *(a named B↔C contract — the shared discrepancy queue; the manual-first operator surface)* The shared Admin-Panel "Logilize discrepancy" queue is triaged across B (inventory-state) + C (fulfilment); B-side resolutions per §14 + §11.2 + §12.5. | §15.3; DEC-141 + DEC-188 | AUTO — drive a mixed queue; verify routing to the correct module's resolution surface |
| **AC-B-XM-28** | *(gate FLOOR)* Every Logilize stream applies quarantine-before-trust; unmatched entities → QuarantineRecord. | §15.4 + §14.5; DEC-191 + BR-B-Quarantine-1 | AUTO — covered by AC-B-J-17 + AC-B-BR-Quarantine-1 |

### §6.9 Module 0 (PIM) read contracts — **naming cascade**

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-B-XM-29** | *(naming cascade)* Module B reads **Product Reference** identity at SerializedBottle creation; **Product Master / Product Variant** prose for the Bottle Page; the Case Configuration whitelist (Layer 1); translatable strings. | §20.2 | AUTO — verify each read path wired; verify Module B does NOT replicate/mutate any Module 0 entity. *(Naming cascade; wine-display alias Bottle Reference / Wine Master)* |
| **AC-B-XM-30** | Module B reads `case_config` on the Case entity for catalog-level identity; does NOT read or enforce `effective_unbreakable` (Layer 1/0 + Layer 2/A + Layer 3/S compose it). | §7.2 + §7.3; BR-B-Breakability-1 | AUTO — covered by AC-B-BR-Breakability-1 |

### §6.10 Module K read contract

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-B-XM-31** | Module B reads the Producer customer-facing description (Bottle Page producer profile; translatable; consent-gated); B2B `party_type` stays out of the inventory side. | §20.2 + §16.2; DEC-031 + DEC-064 + DEC-027 + DEC-193 | AUTO — verify the Producer-description read present; B2B party_type absent |

### §6.11 Boundary statements — Module B does NOT carry (AC-B-XM-32..52)

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-B-XM-32..41** | Module B holds NO commercial pricing / Offer / Cart / Checkout / Voucher state (S); NO Allocation entity / FSM (A — read-only on sub-pool numerics); NO Procurement / PO / Supplier (D); NO Settlement / financial events / GL (E + Xero); NO Customer identity / Profile / Hold / sanctions / KYC (K — no PII); NO Producer / PIM entities (0 — reads); does NOT execute layered-breakability authorisation, fulfilment, the Bottle Page render, or the Logilize integration mechanics. | §1.3 + §20.2; DEC-073/024/029/072/125/126 | AUTO — schema + surface inspection per boundary; assert each absence / read-only |
| **AC-B-XM-42** | *(EXT-1-gated — decoupled, D12)* Module B does NOT author the wallet operational architecture (cold-storage / multi-signature / key-rotation — tech). | §1.3 + §9.3; DEC-073 + DEC-155 | AUTO — gated behind EXT-1; assert posture documented + operational mechanics absent |
| **AC-B-XM-43** | Module B does NOT expose sub-warehouse storage-location detail (Logilize-internal); warehouse-level summary only. | §1.3 + §15.1; DEC-153 + Q-CL-3 | AUTO — covered by AC-B-BR-Storage-1 |
| **AC-B-XM-44** | Module B does NOT recognise `THIRD_PARTY` as an `ownership_flag` value at launch (2-value enum); v17's 3-value reactivates post-launch. | §1.2 + §2.2 + §21; DEC-185 + Q-CL-2 | AUTO — inspect the enum; assert 2 values; no `THIRD_PARTY` constant |
| **AC-B-XM-45/46** | Module B does NOT recognise `ConsignmentPlacement` entity/events or `sourcing_model = AGENCY` at launch (already-deferred). | §1.2 + §21; DEC-193 + DEC-068 + DEC-001 | AUTO — covered by AC-B-EVT-29 + schema inspection |
| **AC-B-XM-47** | *(launch-floor / un-gated under the re-scoped gate — moved OUT of the v0.1 gated set)* Module B does NOT execute the physical NFC tag application workflow — Logilize WMS executes; **Module B records the digital event (the recording side is launch-ready).** | §1.2 + §6.1; DEC-123 | AUTO — assert the recording-only role + Logilize as the executing system. **Launch-floor — NOT gated** |
| **AC-B-XM-48** | *(EXT-1-gated — decoupled, D12)* Module B does NOT execute Avalanche on-chain transaction execution (smart-contract code, RPC, gas, encoding, wallet architecture) — tech. | §1.2 + §21; DEC-073 + DEC-014 | AUTO — gated behind EXT-1; assert business-meaningful payload defined + on-chain execution absent |
| **AC-B-XM-49** | *(split — tag application launch-ready; the on-chain-reference encoding EXT-1-gated, D12)* Module B does NOT author the NFC tag-write protocol (on-chip encoding, write-protection mode, URL pattern); names the business-meaningful content shape (serial + URL launch-ready; the on-chain reference back-fillable). | §1.2 + §21; DEC-124 | AUTO — the serial + URL content shape un-gated; the on-chain-reference encoding gated behind EXT-1 |
| **AC-B-XM-50** | Module B does NOT execute Customer Service remedy mechanics (§17.2); records `BottlePostShipmentTagIssueReported` + `ProvenanceCertificateIssued` only (the remedy is Module S/E). | §1.2 + §17.2.4; DEC-130 | AUTO — covered by AC-B-EVT-42 + AC-B-J-11 |
| **AC-B-XM-51** | Module B does NOT hold storage-fee pricing or customer-level billing; exposes bottle-days-in-storage as data (Module S computes the fee; Module E records). | §1.2 + §18.7; BR-B-Storage-2 + DEC-119 | AUTO — covered by AC-B-BR-Storage-2 |
| **AC-B-XM-52** | Module B does NOT execute the Cellar render UX (Module C scope); answers data queries from the Cellar (the named B↔C contract). | §21; DEC-154 | AUTO — assert data queries answered + no Cellar UX rendered |

### §6.12 MVP re-baseline criteria (NEW — AC-B-MVP-1..6)

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-B-MVP-1** | *(D12 — the serialized-minus-NFT launch posture)* At launch every `SerializedBottle` is created with **`nft_reference = NULL`** (nullable + back-fillable); the per-bottle ledger record (serial, lineage, ownership, lifecycle, the `atp_serialized` contribution) is fully functional without the mint; the NFT back-fills when the on-chain workstream lands — no rebuild. | PRD §0.1 + §4.1; D12 / Phase C item J | AUTO — create a SerializedBottle at launch; assert `nft_reference` nullable + the ledger record functional; simulate back-fill; assert no record rebuild |
| **AC-B-MVP-2** | *(D12 — the DEC-124 tag-content back-fill design; Paolo-track action item 2)* NFC tags apply at launch with **serial + Bottle Page URL** (online verification works); the **on-chain reference is back-fillable** (offline verification rides the decoupled workstream); the "bake the on-chain reference onto the chip" design is NOT a hard prerequisite for applying tags. | PRD §6.3; DEC-124 | MIXED — AI assembles the tag-content design trace (serial + URL at launch; the on-chain ref back-fillable); Paolo confirms the back-fill design supports the launch posture *(the tag-stock procurement lead-time is the time-sensitive Paolo-track item)* |
| **AC-B-MVP-3** | *(Phase C item A — naming cascade)* The catalog-identity criteria carry `Bottle Reference → Product Reference`, `Wine Master/Variant → Product Master/Variant`, and the `BottleReferenceActivated/Retired → ProductReferenceActivated/Retired` consumer note; Module B's own physical-unit names (`SerializedBottle`, `InboundBatch`, `Case`, `StockPosition`, "Bottle Page") are retained (wine-display naming). Behaviour identical. | PRD §0.8 + §N.4; Phase C item A; Module 0 §18 | AUTO — assert the renamed catalog-identity reads + the retained physical-unit names; verify payload semantics unchanged |
| **AC-B-MVP-4** | *(D16 / N1 — the manual-first seam)* The Stage-8 workflow automation (Stocktake auto-reconciliation + cadence; the QuarantineRecord automated cascades; the automated reciprocal round-trips with Module D) is **manual-first at launch**; the integrity-core entities + events + the DEC-194 split + the DISCREPANCY state + the 6-path enum + the Stocktake/QuarantineRecord entities are KEPT as the seam (the automation is additive when it lands). **Identical to Module D's manual-first depth.** | PRD §0.2 + §11 + §12 + §14; Phase C item H / N1 | AUTO — assert the integrity-core criteria pass at launch; assert the automated-round-trip arms are deferred-with-feature (present-but-not-automated); cross-check parity with Module D AC-D-MVP-4 |
| **AC-B-MVP-5** | *(R4 — ⚠️ the trap; E-emits / B-consumes)* `SupplierPaymentCompleted` is **emitted by Module E** (the payment executor) and **consumed by Module B** to flip the inventory `ownership_flag` PRODUCER → CRURATED; **Module B does NOT emit or derive it** (the cut-sheets' "D-emits" + the v1.1 "at sell-through" framing superseded). The `CRURATED` inventory ledger keys to `SupplierPaymentCompleted` — distinct from Module D's `NEWCO` PO-title ledger (keyed to `VoucherIssued`) — N3. | PRD §0.3 + §2.2 + §19.2; Phase C §5-R4 / §5-N3 | AUTO — drive the E-emitted `SupplierPaymentCompleted` → assert B's `OwnershipTransitioned`; **assert Module B has no `SupplierPaymentCompleted` emission/derivation path**; verify the two-ledger distinction (B `CRURATED` vs D `NEWCO`) |
| **AC-B-MVP-6** | *(the floor parity — Phase C item G/M)* The inventory-integrity floor composes whole at the integrated launch: the two-layer no-oversell guard Layer 2 + the B→A push + the storefront lesser-of read + committed-inventory protection + InboundBatch + StockPosition + the four-way reconciliation discipline; **the floor does not depend on the decoupled NFT workstream (Layer 2 = the NS sub-pool ATP if the on-chain layer slips)**; the build-sequencing (B phase 5; A/D=3, S=4, C=5 depend on B) is integration-ready by the integrated launch (a Phase-E flag, not a cut). | PRD §0.5 + §10; Phase C item G/M | MIXED — AI assembles the floor-composition trace (per sub-pool, with the serialized sub-pool dormant); Paolo confirms the floor whole at the integrated launch + the build-sequencing flag for the Phase-E re-estimate |

---

## §7 Out of scope for this acceptance pass

Excluded (per the methodology DECs): the engineering Definition of Done (coverage thresholds, performance budgets beyond §22.1, retry/idempotency, schema design, the wallet operational architecture, Avalanche RPC + gas + smart-contract code, the Logilize integration mechanics, the NFC tag-write encoding, the Bottle Page render surface); UI / UX acceptance (Admin Panel layouts, the Bottle Page render, the Cellar render UX); operational R&R / approval-tier policy (admin-configurable); non-functional concerns not anchored to a BR/DEC; **Phase 2+ deferrals** (multi-warehouse + the `transfer` placeholder; ConsignmentPlacement; THIRD_PARTY ownership_flag; AGENCY sourcing; the `consumption` placeholder; richer Bottle Page media; post-shipment re-tagging); cross-module behaviours owned by other modules (Module A allocation FSM, Module D PO/InboundEvent FSM + the `SupplierPaymentCompleted` **emission** which is now **Module E's** — R4, Module S Voucher FSM, Module C fulfilment + the 4 Logilize streams, Module E financial-event recording + Xero). **The re-scoped EXT-1 gate (D12, §0.1):** every NFT-touching criterion is gated behind a feature flag pending the blockchain-expert review + the on-chain-workstream revisions into v0.3+ of the PRD; **the physical-tagging / serial / `SerializedBottle`-ledger criteria are launch-floor (un-gated)**; the inventory-ledger criteria are not gated. **The NFT working-hypothesis cluster (DEC-120/121/122/124/131) is carried verbatim — do not re-cut.** **PRD ambiguities (AMB-B-1..5)** are an acceptance-authoring backlog (AMB-B-5 is resolved by the re-scoped gate, §0.4).

---

## §8 Sign-off log

### §8.1 Format-validation milestones (template-level)

| Milestone | Date | Notes |
|---|---|---|
| v0.1 authored (parallel agent) | 2026-05-15 | Initial draft against the Module 0 template; 170 criteria; 89.4% AUTO; ~29–33 NFT/NFC criteria EXT-1-gated; Packet verdict EDITS_NEEDED. |
| **v0.3-MVP re-cut (this session)** | 2026-06-08 | Phase D re-baseline: the EXT-1 gate **re-scoped** (the physical-tagging/serial/ledger criteria → launch-floor; the on-chain manifest gated) + `nft_reference`-nullable/back-fill criteria added (D12); the D16 Stage-8 automation criteria annotated manual-first (N1, identical with Module D); the `SupplierPaymentCompleted` criteria aligned to E-emits / B-consumes (R4) + N3; the naming cascade applied; §6.12 MVP re-baseline criteria added (AC-B-MVP-1..6). **No launch-scope criterion removed; all floor criteria unchanged. DRAFT — the MVP re-cut + the original validation + the Packet's EDITS_NEEDED reconciliation land together at batch ratification.** |

### §8.2 Per-AC delivery sign-off

Maintained at first delivery review (OPEN / DEMOED / ACCEPTED per criterion; NFT-touching criteria carry an EXT-1-status column — EXT-1-GATED until the review + on-chain workstream land; EXT-1-LIFTED once flipped). Placeholder rows omitted in this draft.

---

## §9 Cross-references

- **Companion PRD** — [`../02-prd/Module_B_PRD_v0.3-MVP.md`](../02-prd/Module_B_PRD_v0.3-MVP.md).
- **v1.1 predecessor (frozen, re-cut from)** — [`../../reference/v1.1/01-prd/Module_B_Acceptance_v0.1.md`](../../reference/v1.1/01-prd/Module_B_Acceptance_v0.1.md) + its companion [`../../reference/v1.1/01-prd/Module_B_PRD_v0.2.md`](../../reference/v1.1/01-prd/Module_B_PRD_v0.2.md). `greenfield/` is frozen (plan R4).
- **Ratified cut-sheet** — [`../01-triage/Module_B_CutSheet_v0.1.md`](../01-triage/Module_B_CutSheet_v0.1.md) §5 (the acceptance-criteria delta). **⚠️ Any "D emits `SupplierPaymentCompleted`" reading is superseded by Phase C R4 (E-emits — §0.3).**
- **Phase C reconciliation** — [`../01-triage/Phase_C_Reconciliation_v0.1.md`](../01-triage/Phase_C_Reconciliation_v0.1.md) (R4 E-emits; N1 D16 manual-first identical with Module D; N3 two-ledger; item G/M floor; item J the NFT/on-chain DECOUPLE; item I Direct Purchase deferred).
- **Sibling acceptance docs (the cross-module-contract counterparts)** — Module D (the N1 manual-first depth this matches; the E-emitted `SupplierPaymentCompleted` D consumes for the PO title), Module A (the two-layer guard / `VoucherCancelled` / `InventoryShortfallDetected` consumer side; the operation-level over-issuance rejection — no `AllocationCapacityExhausted` event), Module S (the lesser-of read; `VoucherIssued`/`VoucherShipped`; `BottleShippedAsNonSerialized`), Module E (the `SupplierPaymentCompleted` **emission** side — R4), Module C (the named B↔C contracts — next session).
- **MVP decisions register** — [`../04-decisions/MVP_Decisions_Register_v0.1.md`](../04-decisions/MVP_Decisions_Register_v0.1.md).
- **Blockchain-expert review queue (Paolo-track)** — the EXT-1 review (action item 1) + the DEC-124 tag-content back-fill design (action item 2) — time-sensitive; feed the feasibility into the Phase-E re-estimate.

---

*End of Module B Acceptance Criteria v0.3-MVP — Phase D re-baseline. The acceptance delta is bounded and clean: the **EXT-1 gate is re-scoped** so it gates only the **NFT/on-chain layer** (the physical-tagging / serial / `SerializedBottle`-ledger criteria are launch-floor — D12; `nft_reference`-nullable + back-fill criteria added; AMB-B-5 resolved); the **D16 Stage-8 automation criteria are manual-first** (the integrity-core criteria stand UNCHANGED — FLOOR; identical to Module D — N1); the **`SupplierPaymentCompleted` criteria are aligned to E-emits / B-consumes** (R4 — Module B consumes, does NOT emit; the v1.1 "at sell-through" framing superseded; N3 two-ledger clarity); the **naming cascade** is applied (B keeps its physical-unit names); §6.12 adds six MVP re-baseline criteria (AC-B-MVP-1..6). **No criterion in launch scope is removed; all floor criteria stand unchanged. DRAFT — awaiting batch ratification (Paolo);** the MVP re-cut + the original validation + the Packet's EDITS_NEEDED reconciliation land together. Held in `mvp/`; nothing promoted to `handoff/` until Phase E.*
