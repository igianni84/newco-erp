---
type: decision
status: active
date: 2026-06-17
---

> **Correction (2026-07-06 · RM-08).** The decision below is unchanged; the resolution note's "already correct" / "already built" clauses conflated two separate changes and are corrected in place — **not** superseded, because the separation-of-duties decision itself did not change. The **Catalog** SoD floor (`catalog-lifecycle-approval`) was indeed already built and correct (self-approval rejected; distinct actors pass at role_count 2 and 3). But **Producer activation** shipped **single-operator**: `parties-producer-lifecycle` explicitly deferred the multi-role Creator→Approver workflow (AC-K-J-10) and shipped `ActivateProducer` as a lone operator Action via the `ActorContext` seam (the KYC gate came later, in `parties-compliance`) — it carried **no distinct-actor floor and accepted the `system`/null actor** (grep-confirmed: zero SoD terms in the `parties-producer-lifecycle` archive). The "already correct for **Parties**" claim becomes true only with **RM-08** (`parties-producer-approval-sod`, `docs/validation/Remediation_Tracker.md`), which adds the operator-principal + distinct-actor floor to `ActivateProducer`, mirroring Catalog's guard minus the reviewer leg. Two overstated clauses are reworded below — the RESOLVED "Net effect" headline and the Context "built in …" reference; the **Decision, Alternatives and Trade-offs are untouched**.

> **RESOLVED 2026-06-17 — proposal withdrawn; the written spec stands.** After this erratum, Paolo confirmed on Slack: keep the separation-of-duties floor **as written — two distinct people; self-approval never allowed** (*"vale il documento sempre"*). His 2026-06-16 call note ("same person may create + approve") was a **misremembering** across the ~10k-page spec, not a change; he is realigning the verbal exception he had given Laurent. **Net effect: no spec change (the written floor stands); no code change in Catalog — `catalog-lifecycle-approval` was already correct. Producer activation, however, shipped single-operator and needed RM-08 to gain the same distinct-actor floor (see the Correction note above).** The analysis below is retained as the record of *why we did not auto-implement against the written spec* — the resolution vindicated that restraint. Status stays `active`: the in-force decision is now simply "the strict-SoD floor is retained."

## Decision: capture Paolo's "same actor may create + approve if role-qualified" direction; reconcile via spec erratum + a dedicated change — do NOT retrofit shipped code yet

Paolo's 2026-06-16 call note (relayed by Giovanni): on the Creator → Reviewer → Approver lifecycle, **the same person MAY perform more than one step — including create *and* approve — provided they hold the role(s) that authorise each step.** This makes separation-of-duties **role-gated (does the actor hold the step's role?)** rather than **identity-gated (must the actors be distinct people?)**.

This **contradicts the current spec and shipped code** (see Context). Because separation-of-duties is a compliance-flavoured control, a verbal call note is **not** sufficient to flip it. The decision is therefore about *handling*:

1. **Record the intended end-state** (below) but **change no shipped code now**.
2. **Send an erratum to Paolo** (he owns the spec) to ratify the relaxation in the docs — `docs/spec-errata/2026-06-17-approval-sod-same-actor.md`.
3. **Once ratified**, implement via a **dedicated openspec change** across Module 0 (catalog) and Module K (producer), re-testing the affected acceptance.
4. Until (2)+(3) land, **shipped code keeps strict separation-of-duties** (status quo).

**Intended end-state (to confirm with Paolo, then build):** model distinct-actor enforcement as an **admin-configurable toggle** (e.g. `approval.require_distinct_actors`, default **ON**) rather than deleting the floor — when relaxed, each step still requires the actor to **hold that step's role**, and **every step stays audited** (actor + timestamp + decision). This keeps the seam and the audit envelope, and matches the spec's existing posture that approval governance is admin-configurable.

## Context: why this came up

The current spec (now in `spec/` @ `4f48277`) holds distinct-actor as the **one** non-configurable approval floor:

- `spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md` §5.2: *"Three operator patterns are spec-mandated multi-actor — the spec text itself requires two-or-more distinct actors, **independent of which roles they hold** … self-approval is never allowed."* (§4.3 table; §1.4.)
- `spec/02-prd/Module_0_PRD_v0.3-MVP.md` §4.1 (Reviewer/Approver *"cannot be the same person as the Creator"*), §4.2 (Q2 role-**count** configurable, *"separation-of-duties floor … self-approval is never allowed … holds at any configured depth"*), §13.2 BR-Lifecycle-1.
- `spec/02-prd/Module_K_PRD_v0.3-MVP.md` §2/§4.4 (Q3, same floor for Producer content).

So today the **role-count** is configurable (3-step → 2-step), but the **distinct-person** requirement is held independent of roles — the exact opposite of Paolo's note.

Shipped, tested code enforces it:
- `openspec/changes/archive/2026-06-16-catalog-lifecycle-approval` (tasks.md): *"self-approval (creator approves own Master …) is rejected on the SoD floor … distinct actors pass at both role_count 2 and 3."*
- The separation-of-duties floor was registered as module logic by [2026-06-15-identity-auth.md](2026-06-15-identity-auth.md) and built in `operator-auth-foundation` / `catalog-lifecycle-approval` — **not** in `parties-producer-lifecycle`, which deferred the multi-role activation workflow (AC-K-J-10) and shipped single-operator Producer activation; the Parties floor is added by **RM-08** (see the Correction note at top).

## Alternatives considered

- **Implement the relaxation in code now.** Rejected — it contradicts the written, ratified spec; reversing a compliance control on a verbal note (no spec change, no audit/compliance sign-off) violates "spec wins over assumptions / mai assumere". The shipped tests assert the *opposite*, so a silent flip would also be wrong-against-acceptance.
- **Treat the note as a misunderstanding / do nothing.** Rejected — Giovanni reported it as a definite clarification from the spec owner; it must be reconciled, not dropped.
- **Capture + erratum to Paolo + dedicated change (CHOSEN).** Spec owner ratifies, then we implement cleanly with new acceptance.

## Trade-offs accepted

- A **known divergence** between Paolo's verbal direction and shipped code persists until the erratum is ratified and the change lands. Flagged here and in the erratum; shipped behaviour (strict SoD) is the safe default meanwhile.
- If Paolo confirms full removal rather than a configurable toggle, this ADR's "intended end-state" is revised before implementation (the toggle is a recommendation, not a commitment).

## Open questions for Paolo (in the erratum)

1. Confirm the relaxation: same actor may create **and** approve when role-qualified?
2. **Full removal** of distinct-actor, or an **admin-configurable toggle** (default ON), relaxable for the small launch team?
3. Applies to **both** Module 0 (catalog entities) **and** Module K (Producer content)? Also the other §5.2 multi-actor patterns (supervisor-override, single-supervisor-approval)?
4. Audit unchanged — each step still recorded with actor + timestamp + decision? (assumed yes)
5. Any compliance/audit constraint on relaxing separation-of-duties we must preserve?

## References

- `docs/spec-errata/2026-06-17-approval-sod-same-actor.md` — the erratum to Paolo.
- Spec (current floor): `spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md` §5.2/§4.3/§1.4; `spec/02-prd/Module_0_PRD_v0.3-MVP.md` §4.1/§4.2/§13.2; `spec/02-prd/Module_K_PRD_v0.3-MVP.md` §2/§4.4; `spec/04-decisions/MVP_Decisions_Register_v0.1.md` MVP-DEC-007; `feedback_prd_rr_approval`.
- Shipped enforcement: `openspec/changes/archive/2026-06-16-catalog-lifecycle-approval`; `openspec/changes/archive/2026-06-16-parties-producer-lifecycle`; [2026-06-15-identity-auth.md](2026-06-15-identity-auth.md).
- Spec sync: [2026-06-17-spec-synced-from-documentation-repo.md](2026-06-17-spec-synced-from-documentation-repo.md).
