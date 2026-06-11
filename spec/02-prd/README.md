# `02-prd/` — the module PRDs + the system architecture

The launch specification. **Nine module PRDs + the Architecture.** Each PRD is **self-contained (DEC-074)** — every entity, event, and decision it relies on is restated inline, so you can build a module from its PRD (+ its acceptance doc) without reading the v1.1 history.

## Build order (data-dependency order)

| Phase | Module(s) | PRD |
|---|---|---|
| Foundations | **0 — PIM / Catalog** ∥ **K — Parties** | `Module_0_PRD_v0.3-MVP.md`, `Module_K_PRD_v0.3-MVP.md` |
| Commercial primitives | **A — Allocation** ∥ **D — Procurement / Inbound** | `Module_A_PRD_v0.3-MVP.md`, `Module_D_PRD_v0.3-MVP.md` |
| Commerce surface | **S — Sales / Cart / Checkout / Voucher / Invoicing** | `Module_S_PRD_v0.3-MVP.md` |
| Inventory + fulfilment | **B — Inventory Authority + Provenance** → **C — Fulfilment** *(B before C)* | `Module_B_PRD_v0.3-MVP.md`, `Module_C_PRD_v0.3-MVP.md` |
| Finance | **E — Financial recorder + payment + Xero routing** | `Module_E_PRD_v0.3-MVP.md` |
| Cross-cutting | **Admin Panel** — the operator surface over all 8 backends | `Admin_Panel_PRD_v0.3-MVP.md` |

## `Architecture_v0.3-MVP.md` — read it for the system view

The Architecture references the nine backends and re-specs none. Read **§8** first: the composed six-chain compliance/data-integrity floor, the **trimmed cross-module event contract** (emitter ↔ consumer), the **naming cascade**, and the cross-PRD consistency notes. **Where the Architecture or any other doc conflicts with a module PRD, the PRD wins** (the Architecture says so itself).

## Two things to internalise before coding

- **Naming cascade**: the identity spine is the generic **Product Reference** (`Product*`), not `Bottle Reference` / `Wine*`. Module 0 §18 is the source of truth; the cascade is uniform across all PRDs (with deliberate carve-outs for Module E's category-neutral names and Modules B/C's physical-unit names).
- **`SupplierPaymentCompleted` = Module E emits**; Module D and Module B consume it independently (RECONCILE R4). Any "D-emits" phrasing in older material is superseded.

## How to read a PRD

PRDs follow a consistent structure: a header (version/status/predecessor/scope), a methodology note (the DEC-072/073/074 boundaries), the entity + state-machine + event sections, the business rules, the deferred-set section (what's seamed → roadmap), and a §-anchored decision/audit trace at the end (provenance — not needed to build).
