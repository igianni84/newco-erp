# `04-decisions/` — the two decision registers

There are **two** files here, and they are complementary — not duplicates. Use them like a front door and the reference shelf behind it.

| File | What it is | When you use it |
|---|---|---|
| **`MVP_Decisions_Register_v0.1.md`** | A **thin index of what the launch re-scoping changed** — the governing decisions, the 25 scope dials, the 8 module cut-sheet verdicts, the Phase-C reconciliations (R1–R4), and a small net-new `MVP-DEC-###` stream. Each row points to the authoritative doc. It *bridges* to the frozen register (flags a `DEC-###` as kept / superseded-at-launch / deferred); it never restates or extends it. | **The front door.** "What did the MVP change for this module, and where is the authority?" Start here. |
| **`decisions.md`** | The **frozen full product-decision register, `DEC-001..196`** — the canonical text of every decision carried from v1.1. Frozen; never extended by the MVP exercise. | **The lookup.** "I hit a bare `DEC-185` in a PRD or acceptance doc — what does it actually say?" Open it to resolve a specific number. |

## How to use them together

1. Building a module → read its **PRD** (self-contained) and, when you want the *why* behind a scope/contract decision, the **MVP register** row that points to it.
2. Hit an unfamiliar `DEC-###` token → resolve it in **`decisions.md`**.

> The PRDs restate each decision's substance inline (DEC-074), so in practice you rarely need to open `decisions.md` to build — it's there so every `DEC-###` anchor resolves. The MVP register is the map of the launch delta; the frozen register is the source of truth for the underlying decisions.

**Note on range:** the frozen register runs `DEC-001..196` (the Stage-8 inventory-authority block `DEC-185..196` is load-bearing for the launch — e.g. `DEC-185` ownership, `DEC-187/188` inventory contracts).
