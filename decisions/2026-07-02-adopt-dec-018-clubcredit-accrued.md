---
type: decision
status: active
date: 2026-07-02
---

## Decision: adopt canon **DEC-018** locally — the Club Credit issuance domain event is **`ClubCreditAccrued`** (was `ClubCreditIssued`); application-event emission moves to Module S (deferred seam)

The Module-E financial event that records Club Credit creation is renamed **`ClubCreditIssued` → `ClubCreditAccrued`**. Precise scope:

- **The EVENT seam name only.** No `ClubCreditIssued` event class exists — all four Club Credit lifecycle events (`Accrued`/`Applied`/`Restored`/`Forfeited`) are documented Module-E **seams** that Module K *consumes*; Module K emits none and fabricates no such class (`ClubCreditEventOwnershipTest`). So the rename touches only docblocks + the test's forbidden-name list. **Zero behaviour change:** Module K emits none either way, and `ClubCreditAccrued` is asserted ABSENT as a Parties event class exactly as `ClubCreditIssued` was.
- **Application-event emitter moves E → S.** Per DEC-018 the *application* event (`ClubCreditApplied` — credit redeemed against a purchase, a Module-S commerce action) is **Module-S-emitted**; the accrual (`ClubCreditAccrued`) stays Module-E. Module S is an empty stub, so this is a **deferred seam** — noted in the docblocks so ownership is not misstated; the real re-home lands when Module S ships. Module K is unaffected (consumes + emits none regardless of the E/S emitter).
- **Module K's within-module writer vocabulary is UNCHANGED.** DEC-018 renamed the *event*, not Module K's writer: the Action `IssueClubCredit`, the `ClubCreditIssuanceTest` file, and the domain concept "issuance" stay. The event is Module-E financial vocabulary (an *accrual*); the Action is Module-K's within-module writer (it *issues* the credit entity) — different layers, correctly named differently.
- **Out of scope:** `MembershipFeePaid` ownership. Canon DEC-016 also moves it E → S, but that is **RM-03** (membership charge-on-approval, needs its own ADR); left as the frozen-spec Module-E signal here, corrected when RM-03 lands.

## Context: why this came up

This **reverses a naming decision our own frozen `spec/` made and documents internally-consistently.** `spec/02-prd/Module_K_PRD_v0.3-MVP.md` §15.8 + §11.4 make the four Club Credit events Module-E-emitted and name the issuance event `ClubCreditIssued`; `spec/03-acceptance/Module_E_Acceptance_v0.3-MVP.md` `AC-E-EVT-21` states it verbatim and **annotates "(was `ClubCreditAccrued`)" citing DEC-166** — i.e. our snapshot's DEC-166 renamed Accrued → Issued to align Module E acceptance with Module K §15.8. The shipped code matches the frozen spec.

Newer canon **MVP-DEC-018** renamed it **back** (Issued → Accrued) and split application-event emission to Module S. Same **escalation-asymmetry** as DEC-008/RM-04: the canon corrections DEC-008..023 never flowed into our frozen snapshot (which stops at DEC-007 — team-memory `spec-divergence-from-cmless-documentation`). The 2026-07-01 Module K validation code-confirmed the divergence and the reviewer **re-verified this probe against canon source** (`docs/validation/Module_K_Verdict_v0.3-MVP.md` — line 153: DEC-018, severity 🟡 Low rename/seam; line 5: the ClubCredit-event-name probe re-verified against source). Tracked as **RM-10** in `docs/validation/Remediation_Tracker.md`. Because invariant #11 forbids editing `spec/**`, the resulting code↔frozen-spec divergence is recorded **here**, exactly as RM-04 recorded DEC-008. This mini-ADR precedes the rename.

## Alternatives considered

- **Keep `ClubCreditIssued` (frozen-spec DEC-166).** Rejected — behind canon; DEC-018 is the newer decision, re-verified against source; leaves `AC-K-EVT-16`/`AC-K-J-16` naming misaligned with what Paolo will walk.
- **Rename the event AND Module K's action/concept** (`IssueClubCredit` → `AccrueClubCredit`, "issuance" → "accrual", rename the test file). Rejected — DEC-018 renamed the *event*, not Module K's within-module writer. Over-renaming invents beyond canon and churns green code with no spec basis; the E-accrual-event vs K-issue-writer layering is intentional.
- **Fully re-home the application event to Module S now.** Rejected — Module S is an empty stub; a real re-home has nowhere to land. Seam-noted and deferred, mirroring the existing Module-E consumer seams (`PartiesServiceProvider::boot()` empty).
- **Skip the mini-ADR** (the tracker's §3 row says "ADR? —"). Rejected — unlike RM-04 (where frozen §4.8 self-contradicted), here the frozen spec is **internally consistent** on `ClubCreditIssued`. Adopting DEC-018 knowingly diverges from a coherent spec; invariant #11 bans fixing the spec, so the divergence needs a recorded trace. RM-04 set the precedent: a canon adoption → a mini-ADR.

## Trade-offs accepted

- **The application-event Module-S emitter stays a deferred, unexercised seam** until Module S — documented, not dead code (mirrors every other Module-E/S consumer seam in Parties).
- **A cosmetic split** between the Module-E event name (`ClubCreditAccrued`) and Module K's Action/concept (`IssueClubCredit`/"issuance") — accepted; it reflects the real layering (Module K consumes financial events and emits none).
- **`MembershipFeePaid` ownership is left as the frozen-spec Module-E signal** pending RM-03, so a few docblocks still state Module-E ownership for that one upstream signal — corrected when RM-03 adopts DEC-016. Deliberately not folded in (scope discipline; RM-03 is an L item with its own ADR).

## References

- Spec: `spec/02-prd/Module_K_PRD_v0.3-MVP.md` §11.4 + §15.8 (Club Credit events Module-E-emitted, Module K consumes + records state); `spec/03-acceptance/Module_E_Acceptance_v0.3-MVP.md` `AC-E-EVT-21` (`ClubCreditIssued`, "was `ClubCreditAccrued`", DEC-166 + §15.8); `spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md` `AC-K-EVT-16`, `AC-K-J-16`.
- Canon: **MVP-DEC-018** (c-mless/documentation register; not in our frozen snapshot, which stops at DEC-007) — issuance event → `ClubCreditAccrued`; application events Module-S-emitted.
- Validation: `docs/validation/Module_K_Verdict_v0.3-MVP.md` (line 153 — DEC-018 canon overlay, 🟡 Low; line 5 — probe re-verified against source); `docs/validation/Remediation_Tracker.md` **RM-10**.
- Related: [[2026-07-01-adopt-dec-008-hold-types-8]] (the sibling canon adoption; same escalation-asymmetry); RM-03 (DEC-016 — `MembershipFeePaid` ownership, separate); team-memory `spec-divergence-from-cmless-documentation`.
- Consumed by: RM-10 (docblock + `ClubCreditEventOwnershipTest` rename `ClubCreditIssued` → `ClubCreditAccrued`).
