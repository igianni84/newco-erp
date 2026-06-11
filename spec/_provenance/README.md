# `_provenance/` — audit trail (DO NOT build from this)

> ⚠️ **Audit-only.** These are the *product-side decision record* — how and why the launch scope was cut. They are **not build inputs** and some carry **superseded framing**. Build from the v0.3-MVP PRDs in [`../02-prd/`](../02-prd/); use these only to answer "*why* is this manual-first / deferred?".

## What's here

| File(s) | What it is |
|---|---|
| `Module_{0,K,A,D,S,B,C,E}_CutSheet_v0.1.md` | The per-module triage record: every capability tagged KEEP / SIMPLIFY / DEFER / DROP, with the rationale and the forward-compatibility **seam** each deferral preserves. |
| `Phase_C_Reconciliation_v0.1.md` | The cross-module coherence gate — the proof the 8 trimmed scopes compose, and the four RECONCILEs (R1–R4). Its conclusions are already landed in the Architecture §8 and the PRDs. |
| `MVP_Restructure_Plan_v0.1.md`, `Dials_Worksheet_v0.1.md`, `Dials_Grounding_v1.1_reference.md` | The method: the cut principles (P1 defer-with-a-seam, P2 admin-first), the scope floor, and the 25 macro scope-dial decisions. |

## ⚠️ The supersession trap

The **cut-sheets predate the Phase C reconciliation** and carry the **"Module D emits `SupplierPaymentCompleted`"** framing. That was flipped: **Module E emits it** (RECONCILE R4); D and B consume it. Only the v0.3-MVP PRDs/Architecture carry the correct contract. **Never wire an event contract from a cut-sheet** — if a cut-sheet and a PRD conflict, the PRD wins.
