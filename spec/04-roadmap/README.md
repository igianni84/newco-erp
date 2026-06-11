# `04-roadmap/` — the deferred set (NOT launch scope)

`Post_Launch_Roadmap_v0.1.md` is the register of everything **deliberately deferred** out of the launch MVP. **You do not build anything in here for launch.** It exists so that (a) you can see what is intentionally out, and (b) every deferral names the **seam** — the entity / event / state-machine / record kept live at launch — so the post-launch build is *additive, not a rewrite*.

## Why it matters while you build the launch

The launch PRDs keep the **integrity cores, FSMs, events, and entities** that the deferred automation will later drive; only the *automation* defers. When a PRD says a capability is "manual-first" or "decoupled" and points here, that tells you: **build the seam now, don't build the engine.** Keeping the seam exactly as specified is what makes "no data debt" true — the manual-first records are what the future engine reads.

## What's deferred (shape)

- **Four coordinated multi-module restorations**: gifting (S+K+C); Discovery multi-producer composites (S+A+0); the NFT/on-chain layer (B + downstream, gated on EXT-1); the **supplier-settlement engine** (E+D+S+A — the single most-depended-on restore).
- **Single-module manual-first automations**: INV3 dunning (E), returns/replacement automation (C), Stage-8 inventory-workflow automation (B+D), geography engines (C), late-binding optimisation (C), cellar richness (C), refund-cost-matrix (S).
- **Direct Purchase** (idle enum/FSM seam across A/D/B/E/S).
- **The full Admin-Panel surface** (the thin MVP slice ships now; the rest accretes here).
- **The v1.1 already-deferred set**, carried verbatim (B2B / active consignment / multi-warehouse / extra geographies / etc.).

> Two **near-term** items live here as flags even though they're post-launch in build terms: schedule the **EXT-1 blockchain-expert review** early, and confirm the **DEC-124 NFC tag-stock procurement lead-time** — both have long lead-times and feed the build's external-dependency planning.
