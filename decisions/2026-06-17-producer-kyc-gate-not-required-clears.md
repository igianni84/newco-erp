---
type: decision
status: active
date: 2026-06-17
---

## Decision: producer-KYC gate is cleared by `not_required` (equivalent to `verified`); implemented in the deferred parties-compliance slice, not retrofitted

The producer-side KYC gate is satisfied when a Producer is `active` **and** its KYC is **cleared** — `verified` **or** `not_required`; it blocks **only** while KYC is `pending` or `rejected`. An operator setting a Producer's KYC to `not_required` (i.e. "deselecting" KYC) therefore lets the Producer activate and be used downstream exactly as if verified. This is the launch behaviour for every gate that reads "Producer `active` and KYC-cleared": the Producer's own `draft → active`, Product Master activation (Module 0), Allocation creation (Module A), and PO-line creation (Module D).

This is **implemented in the future `parties-compliance` change** (the KYC four-state lifecycle + fields), **not retrofitted** into the already-shipped `parties-producer-lifecycle`, which deliberately deferred the gate.

## Context: why this came up

Giovanni relayed Paolo's 2026-06-16 call note: *"il KYC è un attributo che l'operatore può banalmente deselezionare su un producer per far sì che non sia necessario."* Verified against the spec — this is **already ratified upstream**, so it is not an erratum:

- Upstream commit **`fb9a5e2`** (paoloalfieri) — *"Clarify producer-KYC gate: not_required clears the gate like verified (Modules 0/K/A/D)"* — now in `spec/` @ `4f48277` (pulled via `scripts/sync-spec.sh`). Module K §4.4: *"KYC is **cleared** (non-blocking) when it is `verified` or `not_required`, and **blocking** when it is `pending` or `rejected` … `not_required` and `verified` are equivalent at every gate."* Module 0 §5.4 / §13.4 BR-Producer-1 and BR-Lifecycle-3 reworded from "KYC-verified" to "KYC-cleared (`verified` or `not_required`)". `ProducerActivated` fires on `draft → active` when KYC is cleared.
- Our build had **already deferred** the producer KYC gate: `openspec/specs/party-registry/spec.md` states the §4.4 KYC precondition is a *"deferred seam … implement the transition WITHOUT enforcing a KYC gate; `parties-compliance` SHALL tighten it"* (DEC-071 — KYC fields nullable, added additively). So there is **no contradiction with shipped code** — `parties-producer-lifecycle` correctly enforces no KYC gate today.

KYC-revocation symmetry is unchanged: revoking a verified Producer's KYC blocks only *new* Product Master activations; existing `active` Masters remain.

## Alternatives considered

- **Retrofit the gate into `parties-producer-lifecycle` now.** Rejected — the gate was deliberately deferred; there is no launch consumer yet, and the KYC fields/FSM are owned by `parties-compliance`.
- **Model `not_required` as a blocking/incomplete state.** Rejected — contradicts the ratified spec; would over-gate activation and break the operator-waive flow Paolo described.
- **Implement per the clarified spec in `parties-compliance` (CHOSEN).** Build the gate once, correctly: `cleared = verified ∨ not_required`; operator can set `not_required` to waive; `pending`/`rejected` block.

## Trade-offs accepted

- The producer KYC gate stays **unenforced until `parties-compliance` ships** — already an accepted, documented seam, not new debt.
- This ADR is a **design note anchored to a now-ratified spec**, not a contested architectural choice; recorded so the deferred slice is built with the cleared-state semantics from day one.

## References

- Upstream commit `fb9a5e2`; now in `spec/02-prd/Module_K_PRD_v0.3-MVP.md` §4.4, §13.4 (BR-K-Producer-2), §15.4 (`ProducerActivated`); `spec/02-prd/Module_0_PRD_v0.3-MVP.md` §5.4, §13.4 (BR-Producer-1), §13.2 (BR-Lifecycle-3); Module A / Module D PRDs (allocation / PO-line gates).
- `openspec/specs/party-registry/spec.md` — producer activation "no KYC gate in this slice" deferral; DEC-071, DEC-074.
- Spec sync: [2026-06-17-spec-synced-from-documentation-repo.md](2026-06-17-spec-synced-from-documentation-repo.md).
- To be consumed by the future `parties-compliance` change (producer + customer KYC FSM, sanctions, Holds).
