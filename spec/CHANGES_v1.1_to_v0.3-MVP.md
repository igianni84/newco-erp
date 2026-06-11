# What changed: v1.1 → v0.3-MVP launch baseline

- **Purpose**: the one-page orientation to *how the launch MVP differs from the full v1.1 spec* — for a developer or coding agent landing cold. Read this, then build from the PRDs in [`02-prd/`](02-prd/).
- **What it is / isn't**: a plain-language **summary**. On any conflict, the **module PRDs win** (they are the authoritative, self-contained spec). The structured per-capability source is the [MVP Decisions Register §3](04-decisions/MVP_Decisions_Register_v0.1.md); the full deferred list is the [Post-Launch Roadmap](04-roadmap/Post_Launch_Roadmap_v0.1.md); the frozen v1.1 spec for side-by-side diffing is in [`../reference/v1.1/`](../reference/v1.1/).

---

## TL;DR

**The launch MVP is a clean, coherent *subset* of the v1.1 system — not a different system.** It keeps the **entire core business loop** and the **whole compliance / data-integrity floor** at full fidelity, and **defers the heavy back-office *automation* and the *breadth*** (extra sales models, markets) that aren't needed to prove the model. Everything deferred keeps a **seam** (the entity / event / state-machine / record stays live at launch) so it can be built back **additively**, not as a rewrite. Operations are **manual-first**: the systems and integrations are all live; the *judgment/exception* layer is operator-run via the Admin Panel, and every manual-first record is exactly what the future engine will consume (no data debt).

---

## 1. Structural changes — get these right before coding

These are not scope cuts; they are **shape changes** to the spec that a builder must internalise. (The first one is the single most common trap.)

1. **`SupplierPaymentCompleted` is emitted by Module E** (Module D and Module B *consume* it). The v1.1/v0.2 spec and the archived cut-sheets say *Module D emits it* — that is **backwards** and superseded (RECONCILE **R4**). Wiring the ownership/settlement cascade off the old material reverses it.
2. **Wine → Product generalisation (the naming cascade).** The identity spine is the generic **Product Reference** (`Product Master` / `Product Variant` / `Product Reference`), not `Bottle Reference` / `Wine Master`. At launch `Product Type` has one value, `WINE`. **Module 0 §18 is the source of truth.** Carve-outs: Module E keeps category-neutral names; Modules B/C keep physical-unit names.
3. **The Admin Panel is a first-class operational surface**, not the implicit "admin mirror" v1.1 assumed. Because operations are manual-first, the operator consoles (finance-ops, the shared Logilize discrepancy queue, returns/recall, stocktake/quarantine, white-glove quoting, procurement/discrepancy) are **a real build target** — specced in a net-new 9th PRD.
4. **NFT / on-chain is decoupled + feature-flagged** (dial D12). Per-bottle **serialization is launch-ready** (NFC tag + serial + ledger); the **NFT mint/burn + on-chain layer is built but flagged off in production**, its unflag gated on an external blockchain-expert review (EXT-1). The **non-serialized (NS) path is the universal fallback** — every downstream degrades gracefully. *Decouple ≠ defer: the serialization value proposition is preserved.*
5. **The other three reconciliations** (all naming/contract-consistency, no behaviour change): **R1** — `SupplierPaymentCompleted` is financial-event-only in Module D (no Direct-Purchase activation role; activation is uniform operator-publish). **R2** — storage is Module-S-internal (one D→S read; no bidirectional S↔E). **R3** — Module C's Logilize contract is 4 fulfilment streams (storage-location migrated to Module B's stream).

---

## 2. Kept WHOLE — no compromise at launch

- **The core loop** — producer onboarded → allocation published → member registers/KYC'd → browses storefront → buys Voucher → pays → bottle received into inventory → shipped → in Cellar → supplier settled.
- **The club / membership spine** (full — it's the core value proposition).
- **Producer self-serve reporting** (full — all 7 sections, real-time).
- **The compliance + data-integrity floor**, none cut: KYC / sanctions / OFAC / unified Hold (enforced uniformly at every transaction-initiation surface); tax-correct invoicing (INV1 bottle / INV2 shipment+excise / INV3 storage); **dual-record FX** (customer-currency + EUR, per-leg rate-lock, FX-correct refunds); the **two-layer no-overselling guard**; committed-inventory protection; audit + 10-year retention.
- **Customer payment automation** — Airwallex cards/SEPA/saved-card/multi-currency + refunds + **chargeback automation** (kept by explicit decision; payment automation is floor, not a defer).
- **Per-bottle serialization** — NFC tag + serial + SerializedBottle ledger from day one.
- **Six locales, five currencies.**
- **Catalog (Module 0) and Parties (Module K)** — kept in full; Allocation (A) kept in full; Procurement (D) kept heavy.

---

## 3. Simplified — lighter form at launch (full version deferred; seam kept)

Each of these keeps its integrity core / FSM / events live; only the *automation or richness* is trimmed, and the operator runs it by hand at launch.

| Area | Launch form | Deferred (→ roadmap) |
|---|---|---|
| **Geography / shipping (D3)** | Automate low-friction destinations; **manual white-glove quote** for complex (US / high-excise); OFAC retained (floor) | Automated US-state / excise / DDP-DAP engines |
| **Refunds (D6)** | 14-day cancellation (legal floor) + basic refund, operator-decided | Automated refund-cost-matrix + producer-fault clawback netting |
| **Late-binding pick (D13)** | FIFO by voucher expiry + manual tiebreak | Warehouse-efficiency optimisation |
| **Returns / replacements (D14)** | Manual-first (FSM + event chain kept) | Auto-transitions / routing / notification |
| **Producer recall (D15)** | Minimal / manual reverse logistics | Recall automation |
| **Stocktake / quarantine / adjustments (D16)** | Integrity core = floor; workflows manual-first | Stage-8 inventory-workflow automation |
| **Cellar render (D17)** | Basic composition (member's full collection) | ETA-precision / shelf-location / richest aggregation |
| **Supplier settlement (D19)** | **Operator-run** statement composition | The settlement *engine* (composite-OC, clawback netting, OC-5% compute, partial-PO) |
| **INV3 dunning (D4)** | Manual operator retry → Hold → suspension (chain kept) | Automated saved-card 3-stage escalation |

---

## 4. Deferred — not built at launch (lives in the roadmap, with its seam)

Not deleted — each sits in the [Post-Launch Roadmap](04-roadmap/Post_Launch_Roadmap_v0.1.md) with its forward-compat seam, re-introduction trigger, and dependencies. **Do not build these for launch; do build the seam each one names.**

- **Four coordinated multi-module restorations**: **gifting** (S+K+C); **Discovery multi-producer composites** (S+A+0); the **NFT on-chain layer** (B + downstream; gated on EXT-1); the **supplier-settlement engine** (E+D+S+A — *the single most depended-on restore*).
- **Single-module automations** (the manual-first items in §3 — their engines).
- **Direct Purchase** — confirmed no launch deal; the `direct_purchase` enum/FSM idles across A/D/B/E/S as a seam.
- **The full Admin-Panel surface** — the thin operator slice ships now; the rest (restored automation consoles, producer-portal write-UIs, the UX/IA/RBAC platform layer) accretes later.
- **The v1.1 already-deferred set, carried verbatim** — B2B / wholesale / active consignment / drop-ship / liquid sales / P2P / multi-warehouse / US-state + DDP-DAP expansion / Italian SDI connector / smart-contract audit / AI-copilot / native mobile, etc.

> **Coherence guarantee:** the trim was reconciled end-to-end (Phase C) — **no kept capability silently depends on a deferred one.** The deferred multi-module sets defer *and* restore as coordinated groups.

---

## 5. Per-module summary

| Module | Verdict | Headline change for the MVP |
|---|---|---|
| **0 — PIM / Catalog** | KEEP-in-full **+ generalise** | Wine→Product spine; **source of the naming cascade**; manual-first enrichment (LWIN pluggable) |
| **K — Parties** | KEEP-in-full | Compliance floor + club spine intact; exactly **one** producer self-serve write (membership approve/decline) |
| **A — Allocation** | KEEP-in-full | Supply primitive + Layer-1 no-oversell + `VoucherCancelled` release; Direct-Purchase enum/FSM idle (seam) |
| **D — Procurement / Inbound** | KEEP-heavy | Direct Purchase deferred; **R1**; *consumes* the E-emitted `SupplierPaymentCompleted` |
| **S — Commerce** | Cut-heavy | Gifting / multi-producer composites / refund-matrix / club-credit peripherals deferred-or-simplified; **R2**; 7-state Voucher; INV1/INV2/INV3 |
| **B — Inventory + Provenance** | Cut-heavy | **D12 on-chain decoupled** (serialization launch-ready); **D16** Stage-8 automation → manual-first (integrity core = floor); **R4** consume |
| **C — Fulfilment** | Cut-heavy | D3 geography-hybrid / D13 FIFO pick / D14 returns manual / D17 basic cellar / D15 recall minimal; **R3**; in-transit redemption-block floor |
| **E — Finance** | Cut-heavy | **D19** settlement engine deferred (operator-run); **D4** INV3 dunning deferred; **chargeback automation kept**; **dual-record FX = floor**; **R4** emit |
| **Admin Panel** | **NET-NEW (thin)** | The operator surface over the 8 backends — net-new finance-ops console + shared Logilize discrepancy queue; no v1.1 predecessor |

---

## 6. What this means for the build

- **Build from `handoff/`** — the v0.3-MVP PRDs are self-contained and authoritative. `reference/v1.1/` is frozen context for understanding *why*, **never** a build source (it carries the pre-trim scope and the superseded seams).
- **Build the seam, not the deferred engine.** When a PRD says "manual-first" or "decoupled," ship the entity/event/FSM exactly as specified and the operator console — not the automation. That's what keeps the post-launch build additive and the manual-first records debt-free.
- **Dig deeper**: per-capability dispositions → [MVP Decisions Register §3](04-decisions/MVP_Decisions_Register_v0.1.md); the deferred set with seams → [Post-Launch Roadmap](04-roadmap/Post_Launch_Roadmap_v0.1.md); the per-module rationale → [`_provenance/`](_provenance/) cut-sheets; the system-level composition → [Architecture §8](02-prd/Architecture_v0.3-MVP.md).
