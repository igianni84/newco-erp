# NewCo ERP — Build Handoff (v0.3-MVP launch baseline)

- **Snapshot date**: 2026-06-09
- **Baseline**: NewCo ERP **v0.3-MVP** (launch scope; spec batch-ratified 2026-06-08)
- **Owner**: Paolo · **Audience**: the engineering team + any coding agent building the launch MVP
- **Status**: ratified specification snapshot — the build baseline.

---

> ## 🔴 This is the launch baseline. It SUPERSEDES v1.1.
> The earlier v1.1 spec (8 module PRDs at v0.2, Architecture v0.2) is **frozen and superseded** — it lives in [`../reference/v1.1/`](../reference/v1.1/) for audit only. **Build from the files in *this* folder.** Building from v1.1 implements the wrong, bigger system (see the master [`../README.md`](../README.md) for the four traps).
>
> **New here?** Read [`CHANGES_v1.1_to_v0.3-MVP.md`](CHANGES_v1.1_to_v0.3-MVP.md) first — the one-page summary of what changed from v1.1 (kept whole / simplified / deferred + the structural changes), then come back to the read order below.

---

## The single most important thing

1. **`SupplierPaymentCompleted` is emitted by Module E** (consumed by D and B). The frozen v1.1 files and the `_provenance/` cut-sheets say "D-emits" — that is superseded. **When any older doc conflicts with a v0.3-MVP PRD, the PRD wins.**
2. **Operations are manual-first at launch.** The integrations are all live (warehouse, payments, invoicing, stock); the *judgment/exception* layer (supplier settlement, INV3 dunning, returns routing, stock-mismatch reconciliation, complex-destination shipping quotes) is operator-run via the Admin Panel. Every manual-first record is exactly what the deferred engine will later consume — **no data debt**.

## Read order

1. **The manifest (entry document)** — [`05-release/MVP_Release_Index_v0.1.md`](05-release/MVP_Release_Index_v0.1.md): every launch artefact + a one-line scope each + the coherence assertion.
2. **The system view** — [`02-prd/Architecture_v0.3-MVP.md`](02-prd/Architecture_v0.3-MVP.md), especially **§8** (the composed floor, the trimmed event contract, the cross-PRD consistency notes).
3. **The build sequence** — [`05-release/Build_Workplan_v0.3-MVP.md`](05-release/Build_Workplan_v0.3-MVP.md): the 9 phases, dependency order, integrations, signoff gates. *(Sequencing only — it carries no dates/sizing; that is yours to determine.)*
4. **The module PRDs** — in build order: **Module 0 (PIM) + K (Parties)** → **A (Allocation) + D (Procurement)** → **S (Commerce)** → **B (Inventory) → C (Fulfilment)** → **E (Finance)**, with the **Admin Panel** PRD running across all. Each PRD is self-contained (DEC-074) — you can build a module from it without reading v1.1.
5. **Acceptance** — each module's [`03-acceptance/`](03-acceptance/) doc is its testable companion; read it alongside the PRD.

## What's in this folder

| Folder | Contents |
|---|---|
| [`02-prd/`](02-prd/) | the 9 v0.3-MVP module PRDs + `Architecture_v0.3-MVP.md` |
| [`03-acceptance/`](03-acceptance/) | the 9 v0.3-MVP acceptance docs (one per PRD) |
| [`04-decisions/`](04-decisions/) | `MVP_Decisions_Register` (the launch-delta index) + `decisions.md` (the frozen DEC-001..196 register) |
| [`04-roadmap/`](04-roadmap/) | `Post_Launch_Roadmap` — the deferred set (what is **not** in launch scope) |
| [`05-release/`](05-release/) | the Release Index (manifest) + the Build Workplan (sequence) |
| [`00-business-model/`](00-business-model/) | `NewCo_BusinessModel_v0.9.md` — the business-context anchor |
| [`_provenance/`](_provenance/) | **audit-only** — how the cut was decided (cut-sheets, Phase C, method). Do not build from it. |

## Resolving the references you'll hit

- **`BMD §X.Y`** → [`00-business-model/NewCo_BusinessModel_v0.9.md`](00-business-model/NewCo_BusinessModel_v0.9.md) (the one pinned business-model version).
- **`DEC-###`** → [`04-decisions/decisions.md`](04-decisions/decisions.md) (the frozen DEC-001..196 register). The PRDs restate each decision's substance inline (DEC-074) — the number is an anchor; open the register only to look one up.
- **The launch delta** ("what changed for the MVP, and why") → [`04-decisions/MVP_Decisions_Register_v0.1.md`](04-decisions/MVP_Decisions_Register_v0.1.md).

## Two load-bearing flags

- **The no-overselling build-sequencing flag.** Module B's floor artefacts (the Layer-2 ATP push, InboundBatch, StockPosition, per-sub-pool ATP) **must be integration-ready by the integrated launch** — Module A's Layer-1, Module S's storefront read, and Module C's no-oversell-at-pick all depend on B's side. It sits on the critical path; do not treat it as a post-launch follow-on. (Architecture §8.3; Build Workplan Phase 5.)
- **The D12 NFT/on-chain working hypothesis.** Per-bottle serialization (NFC tag + serial + ledger) is launch-ready; the **NFT mint/burn + on-chain layer is decoupled + feature-flagged**, its production unflag gated on an external blockchain-expert review (EXT-1). The **non-serialized path is the universal fallback** — every downstream degrades gracefully. Treat NFT-touching scope as feature-flag-isolatable.

## What this snapshot is NOT

- Not the **tech architecture** (stack, schema, API, hosting, event substrate, CI/CD) — your Phase-1 decision (DEC-073).
- Not **GL accounting policy** — Module E records; Xero decides treatment (DEC-072).
- Not **UX/UI design**, not **authority/RBAC tiers** (admin-configurable, downstream).
- Not a **schedule or estimate** — no target date is stated here; you size and date the build from this spec.

Questions / errata to Paolo.
