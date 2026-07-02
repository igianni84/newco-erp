# Progress — parties-anonymisation

## Codebase Patterns
(consolidated reusable patterns — read first each iteration)

- **Anonymisation Hold-precedence gate (tasks 3.2 / 4.1) = `compliance`-only, count-independent.** The gate blocks iff an active `compliance` Hold covers the Customer; **no other type blocks**. Key on `compliance` **only** — do NOT enumerate the whole Hold set, so the gate is immune to the RM-04 6→8 Hold-count debt. Read coverage via `PartyComplianceStatusReader` (contract) / `DatabaseComplianceStatusReader` — **never** touch the `Hold` Eloquent model directly (no-model-leak boundary). Canon MVP-DEC-015 (ADR `2026-07-02-adopt-dec-015-…`).
- **There is NO `sanctions` Hold type.** `HoldType` = 8 cases (`admin|kyc|payment|fraud|compliance|credit|chargeback_review|storage_payment_failed`), none `sanctions`. Sanctions is a **separate** `sanctions_status` FSM (`SanctionsStatus`: `pending|passed|failed|under_review`) on the Customer — do NOT wire it into the anonymisation gate. A sanctioned-customer retention case is a `compliance` Hold placed by Compliance.
- **Frozen-spec anonymisation precedence is stated 3 disagreeing ways** — `DEC-027` (compliance non-blocking) vs `§8.2` / `AC-K-J-9a` (compliance blocks). Canon collapses to compliance-only; the ADR is the reconciliation. Cite the ADR, not the raw spec, when implementing the gate.

---

## [2026-07-02 15:32] — 1.1 Mini-ADR: adopt canon MVP-DEC-015
- Wrote `decisions/2026-07-02-adopt-dec-015-anonymisation-hold-block-set.md` (mirrors the DEC-018 ADR shape) recording all four required points: (a) `compliance`-only block-set + resolution of the compound frozen-spec contradiction; (b) sanctions-retention via a `compliance` Hold (no `sanctions` Hold type — sanctions is the `sanctions_status` FSM); (c) J-9b minimal/synchronous/in-memory export scope (D5); (d) PII-free `CustomerAnonymised` event over frozen §15.1's event-free anonymisation (D3). Added the newest-first `decisions/INDEX.md` row.
- **Verification-first, not design-trusting:** an Explore subagent quoted the three frozen-spec precedence statements against source — richer than the design summarized. The contradiction is **two-layered**, both folded into the ADR: (1) DEC-027 (`compliance` NON-blocking; non-block list `{payment,fraud,kyc,compliance,admin}`) vs §8.2 (`compliance` blocks; non-block `{…,credit}`) vs AC-K-J-9a (blocks `{sanctions-OFAC,compliance}`) — they disagree on `compliance` AND `credit`, and DEC-027 even cites §8.2 as its anchor; (2) all three name a "sanctions Hold" that is not in the §4.8 six-type enum — sanctions is the separate `sanctions_status` FSM. Canon MVP-DEC-015 = `compliance`-only resolves both. Code reality confirmed: `HoldType` = 8 cases, none `sanctions`; `SanctionsStatus` separate; `PartyComplianceStatusReader`/`DatabaseComplianceStatusReader` present.
- Accurate anchors pinned before writing (no invented line numbers): `Module_K_Verdict_v0.3-MVP.md:152` = the authoritative DEC-015 canon-overlay row ("only `compliance` blocks; no separate `sanctions` Hold", 🔴 floor); `:34` = AC-K-J-9a "See Canon Overlay (DEC-015)"; DEC-027 `decisions.md:198`; §8.2 `Module_K_PRD:454`; §4.8 `:301`; §9.2 `:479`; §12 `:599`; §15.1 `:750`.
- Files changed: `decisions/2026-07-02-adopt-dec-015-anonymisation-hold-block-set.md` (new), `decisions/INDEX.md` (+1 row), `openspec/changes/parties-anonymisation/{tasks.md,progress.md}`. Date = 2026-07-02 → filename matches; delta-spec `_Source:_` citations already point at it (no rename/citation edit needed).
- Quality loop: green — `openspec validate parties-anonymisation --strict` valid; INDEX grep = 1; Pint `--test` passed. Doc-only, zero PHP touched → PHPStan/Pest baseline (1807/1807) unaffected; full-suite + PG17 close is task 7.1.
- **Learnings for future iterations:**
  - The gate is `compliance`-only and **count-independent** — see the Codebase Patterns block at top. Cite the ADR (not the raw, self-contradictory spec) when building tasks 3.2 (gate) and 4.1 (precedence matrix).
  - `sanctions_status` is NOT a Hold type; never wire it into the anonymisation gate.
---
