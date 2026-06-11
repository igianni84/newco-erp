# `05-release/` — the manifest + the build sequence

Two coupled documents: one indexes the launch spec, the other sequences its build.

| File | What it is | Use it for |
|---|---|---|
| **`MVP_Release_Index_v0.1.md`** | The **launch manifest** — every artefact composing the launch spec (the 9 PRDs + 9 acceptance + Architecture + roadmap + registers + workplan), each with a path + a one-line scope descriptor, plus the headline coherence assertion and the one-paragraph "what the launch IS". | **The entry document / table of contents.** Open this first to see the whole launch slice and where each piece lives. |
| **`Build_Workplan_v0.3-MVP.md`** | The **9-phase build sequence** — per phase: scope, dependency order, the integrations wired, the frontend/Admin-Panel track, the tests, and the signoff gates. | Sequencing the build: what to build in what order, what gates each phase. |

## Important: the workplan is sequence, not schedule

The Build Workplan deliberately carries **no person-days, no team size, and no calendar dates** — and it makes **no tech-stack decisions** (those are your Phase-1 call, DEC-073). It names *what* gets built *in what order* and *against what signoff gate*. **Sizing and dating the build is yours to determine** from this spec.

## The one sequencing constraint to honour

The workplan carries the **no-overselling build-sequencing flag**: Module B's floor artefacts (Layer-2 ATP push, InboundBatch, StockPosition, per-sub-pool ATP) must be **integration-ready by the integrated launch**, because Module A (Phase 3), Module S (Phase 4), and Module C (Phase 5) all depend on B's side. It is on the critical path — not a post-launch follow-on. (Build Workplan Phase 5; Architecture §8.3.)
