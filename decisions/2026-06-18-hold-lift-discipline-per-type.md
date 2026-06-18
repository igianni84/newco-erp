---
type: decision
status: active
date: 2026-06-18
---

## Decision: Hold-lift discipline is **per Hold type**, not uniform — `kyc` + `payment` auto-lift; `admin`/`fraud`/`compliance`/`credit` are operator-lift-only

A Hold's lift policy is a function of its `hold_type`, ratified verbatim by the spec (DEC-160 §4.8.1; AC-K-FSM-11):

- **Auto-liftable** — `kyc` (auto-lifts on KYC clear) and `payment` (auto-lifts on payment success). The lift is performed by the system on the resolving signal, never by an operator; an operator-initiated lift of an auto-managed Hold is **rejected**.
- **Operator-lift-only** — `admin`, `fraud`, `compliance`, `credit`. These are **never auto-lifted**; lifting requires an explicit operator action with an auditable actor + reason.

In `parties-holds` this is encoded as a `HoldType::autoLiftable(): bool` predicate (true for `Kyc`/`Payment` only — the enum's first predicate, mirroring `KycStatus::clears()`). The operator `LiftHold` action guards `!hold_type->autoLiftable()` and rejects `kyc`/`payment` with a localized `IllegalHoldLift`; the `kyc` auto-lift is performed within-module by `RecordKycVerified` (the only auto-lift trigger that exists at launch — see [[2026-06-17-producer-kyc-gate-not-required-clears]] for the KYC cleared-state semantics it keys off). The `payment` auto-lift trigger (and the placement triggers for `payment`/`fraud`/`compliance`/`credit`) are **Module E/S signals that do not exist yet** — documented seams; the discipline (the guard + the `autoLiftable` classification) ships now, the automatic triggers arrive with those modules.

## Context: why this came up

Root `CLAUDE.md` **Invariant #7** reads: *"Compliance gates … Holds are never auto-lifted."* Authoring `parties-holds` surfaced an apparent contradiction: the KYC↔Hold coupling (AC-K-FSM-3, AC-K-J-7) **auto-lifts** the `kyc` Hold on verification. Per the skill's "STOP and escalate if a task requires violating an invariant", this was investigated against the PRD before proceeding.

The investigation found **no real contradiction in the spec** — it is internally consistent and explicit. Three independent passages define a *per-type* discipline:

- **PRD §4.8** (the `kyc` type definition): *"`kyc`: placed automatically when KYC is required and not yet verified, **lifted automatically when verified**."*
- **PRD §4.8.1 / DEC-160** (the lift-discipline rule): *"Hold-lift discipline applies per type, not uniformly: **auto-lift permitted on `kyc`** (auto on KYC clear) **and `payment`** (auto on payment success); **explicit operator lift required on `admin`, `fraud`, `compliance`, and `credit`** Holds."*
- **AC-K-FSM-11** (the acceptance): *"`kyc` and `payment` Holds auto-lift on KYC clear / payment success; `admin`, `fraud`, `compliance`, `credit` require explicit operator lift. **Auto-lift on `admin`/`fraud`/`compliance`/`credit` is rejected.**"*

So `CLAUDE.md` Invariant #7 ("Holds are never auto-lifted") is **over-broad as written** relative to the PRD-of-record: the "never auto-lifted" rule is true for exactly four of the six types. The spec-faithful invariant is *"`admin`/`fraud`/`compliance`/`credit` Holds are never auto-lifted; `kyc` and `payment` are system-managed and auto-lift on their clearing signal."* This ADR records that reading so the slice is built correctly and the apparent contradiction is not re-litigated.

## Alternatives considered

- **Treat it as a real contradiction → STOP and escalate (no build).** Rejected — the spec is unambiguous; the only over-broad statement is the convenience phrasing in `CLAUDE.md`, not a genuine spec conflict. Escalation is still owed as a *flag* (the protected `CLAUDE.md` wording is the human's to adjust), but it does not block authoring.
- **Implement a uniform "never auto-lift" (honor Invariant #7 literally) and make KYC-verify require a manual Hold lift.** Rejected — directly contradicts PRD §4.8/§4.8.1/AC-K-FSM-3/J-7, which mandate the `kyc` auto-lift; it would also leave a verified Customer blocked until an operator manually cleared the system-placed Hold.
- **Encode the discipline as a per-type predicate + operator-lift guard (CHOSEN).** Builds the rule once, where it belongs (the type), and makes "operator-lift of an auto-managed Hold is rejected" a structural guard, not a convention.

## Trade-offs accepted

- **`CLAUDE.md` Invariant #7 stays worded as-is** — Giovanni decided 2026-06-18 (at the `parties-holds` gate) to keep the wording and let **this ADR stand as the authority** (the file is Protected — Claude does not edit it). A reader of Invariant #7 alone would over-generalize; this ADR is the standing pointer that corrects it. The reword in *References* below is recorded as the option, not an applied change.
- **The `payment` auto-lift and the non-`kyc` placement triggers ship as classification + guard only**, with no live trigger (Module E/S absent). This is the documented seam, not dead code: `autoLiftable()` is exactly the predicate those future triggers will key off, and `LiftHold`'s guard is already correct for them.
- The `kyc` Hold is the **one** auto-lift exercised end-to-end at launch (via `RecordKycVerified`); it is the single concrete instance proving the discipline.

## References

- Spec: `spec/02-prd/Module_K_PRD_v0.3-MVP.md` §4.8 (Hold entity; 6 types; per-type place/lift), §4.8.1 (DEC-160 lift discipline; KYC↔Hold cascade), §9.1 (KYC `pending` auto-places / `verified` auto-lifts the `kyc` Hold); `spec/04-decisions/decisions.md` DEC-160 (E6-07 per-type lift), DEC-168 (chargeback Hold no-auto-lift), DEC-181 (read-API uniformity).
- Acceptance: `spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md` AC-K-FSM-10 (Hold lifecycle), AC-K-FSM-11 (lift discipline), AC-K-FSM-3 / AC-K-J-7 (the `kyc` auto-lift half).
- Invariant clarified: root `CLAUDE.md` "Key Invariants" #7. **Recommended reword** (human-owned): *"Holds of type `admin`/`fraud`/`compliance`/`credit` are never auto-lifted (operator lift required); `kyc` and `payment` are system-managed and auto-lift on their clearing signal."*
- Related: [[2026-06-17-producer-kyc-gate-not-required-clears]] (KYC cleared-state semantics), [[2026-06-12-event-substrate-and-audit-store]] (PII-free events; transactional recording the Hold events use).
- Consumed by: the `parties-holds` change (`HoldType::autoLiftable()`, `LiftHold` guard, the `RecordKycVerified` auto-lift coupling).
