## MODIFIED Requirements

### Requirement: Approval Governance

Every commercial-impact transition (`reviewed → active`, `active → retired`, `retired → reviewed`) SHALL pass a **Creator → Reviewer → Approver** approval workflow. The actors that perform the configured steps SHALL be **distinct people — self-approval SHALL never be allowed** (the separation-of-duties floor). The **number of distinct approval roles SHALL be operational configuration** (`feedback_prd_rr_approval`): the full three-step Creator → Reviewer → Approver SHALL be the default, and a lighter two-step Creator → Approver MAY be configured; the separation-of-duties floor — each configured step performed by a distinct actor, no self-approval, every step audited — SHALL hold at any configured depth. Because these are operator decisions, a governance transition SHALL require an authenticated operator principal (`actor_role = newco_ops` with a non-null `actor_id`); a `system`/null actor cannot satisfy the distinct-actor floor and SHALL be rejected.

Each governance step SHALL be recorded in the **append-only audit trail** with the acting `actor_role` and `actor_id` (resolved from the `ActorContext` seam), the action, a before/after snapshot, and the decision; the audit trail SHALL be the system of record for which actor performed each step, and **both** the distinct-actor guard **and** the review-freshness (rejection-pending) condition SHALL be evaluated against it. No per-entity governance status column SHALL be added — the rejection-pending condition is a **derived** read of the entity's latest governance action, never persisted state.

Rejection, re-submission, and the review-freshness block-gate (`§ 4.3`): a Reviewer or Approver MAY **reject** an entity in `reviewed`; the entity SHALL **stay in `reviewed`** with the rejection recorded in the audit trail (actor, notes, decision), and there SHALL be **no** revert-to-`draft` step. A rejection leaves the entity **rejection-pending** — a condition DERIVED from the audit trail as "the entity's latest governance action is a rejection." While an entity is rejection-pending its activation (`reviewed → active`) SHALL be **blocked** with a localized exception, leaving it in `reviewed` and recording no `*Activated` event: the approval flow SHALL restart from review, never complete from a still-rejected state. The Creator SHALL edit the entity **in place** and then perform an explicit **`re-submit`** operation — a `reviewed → reviewed`, **audit-only** governance decision (recorded as `resubmitted`; **no** domain event), the twin of `reject`, requiring an authenticated operator principal and from-state guarded on `reviewed` — which **re-arms review** by clearing the rejection-pending condition (the latest governance action becomes the re-submission, no longer a rejection); a distinct approver MAY then activate. The full rejection history (every round's notes, actor identities and timestamps) SHALL be preserved as part of the entity's permanent append-only audit record.

#### Scenario: Self-approval is rejected

- **WHEN** the operator who created (or, in the three-step configuration, reviewed) an entity attempts to perform the approval step (`reviewed → active`) on it
- **THEN** the transition is rejected on the separation-of-duties floor, the entity stays in `reviewed`, and no `*Activated` event is recorded

#### Scenario: Distinct actors satisfy the floor at the configured depth

- **WHEN** the configured role count is three and three distinct operators perform create, review and approve in turn (or, under a two-step configuration, two distinct operators perform create and approve)
- **THEN** the entity reaches `active`, and each step is recorded in the audit trail with its distinct acting `actor_id`

#### Scenario: A non-operator context cannot perform a governance step

- **WHEN** a governance transition is attempted in a `system`/unauthenticated context (no operator `actor_id`)
- **THEN** it is rejected — the distinct-actor floor cannot be satisfied without an operator principal

#### Scenario: Rejection keeps the entity in reviewed and preserves history

- **WHEN** a Reviewer or Approver rejects an entity in `reviewed` with notes
- **THEN** the entity stays in `reviewed`, the rejection (actor, notes, decision, timestamp) is recorded in the append-only audit trail, and after the Creator edits in place and re-submits, the approval flow restarts with the full rejection history preserved

#### Scenario: A pending rejection blocks activation until re-submit

- **WHEN** an entity in `reviewed` has been rejected (its latest governance action is a rejection) and a distinct approver attempts to activate it (`reviewed → active`)
- **THEN** the activation is blocked with a localized exception, the entity stays in `reviewed`, and no `*Activated` event is recorded

#### Scenario: Re-submit re-arms review and clears the rejection-pending condition

- **WHEN** the Creator performs the explicit `re-submit` operation on a rejection-pending entity in `reviewed`
- **THEN** the entity stays in `reviewed`, an audit record (`resubmitted`, with acting `actor_id` and a before/after snapshot) is written and **no** domain event is recorded, the rejection-pending condition is cleared (the latest governance action is now the re-submission), and a distinct approver may then activate the entity to `active`

#### Scenario: Two rejection rounds each block until re-submit and preserve full history

- **WHEN** an entity is rejected, re-submitted, rejected again, re-submitted again, then activated by a distinct approver
- **THEN** activation is blocked after each rejection until the following re-submit, the entity stays in `reviewed` throughout the rounds, both rejection rows (with their notes and actors) and both re-submission rows are preserved in the append-only audit trail, and the final activation succeeds and records exactly one `*Activated` event

#### Scenario: Re-submit is operator-floored and from-state guarded

- **WHEN** a `re-submit` is attempted in a `system`/unauthenticated context, or on an entity not in `reviewed` (e.g. a `draft` or `active` entity)
- **THEN** it is rejected — respectively on the operator-principal floor, or with a localized `IllegalLifecycleTransition` naming the state — and no audit row is written and no state changes

_Source: spec/02-prd/Module_0_PRD_v0.3-MVP.md § 4.2 (Creator → Reviewer → Approver; distinct people; self-approval never allowed; steps recorded in the audit trail with actor identity, timestamp, decision; the `draft → reviewed` checkpoint is audit-only; Q2 role-count is operational configuration, the floor holds at any depth) · § 4.3 (rejection stays in `reviewed`, edited in place, no revert to `draft`, re-submission restarts the flow, history preserved) · § 13.2 BR-Lifecycle-1 (multi-step approval; distinct people; no self-approval), BR-Lifecycle-6 (rejection handling — rejection flag + notes, edit in place, re-submit, flow restarts from review, history preserved) · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md § 2 AC-0-J-7 (2-rejection-round scenario; state stays `reviewed`, audit trail contains every round), § 3 AC-0-FSM-8 (review audited, no event), § 4.2 AC-0-BR-Lifecycle-6 · decisions/2026-07-02-adopt-dec-019-review-freshness-resubmit.md (canon MVP-DEC-019 local adoption: explicit re-submit operation + the activation block-gate on an un-remediated rejection, derived from the audit trail; the edit-re-arms leg deferred to RM-14) · decisions/2026-06-15-identity-auth.md (operator authenticated → `newco_ops` + `Operator.id` via ActorContext) · openspec/specs/event-substrate/spec.md (Audit Records — append-only; before/after; authorization basis) · CLAUDE.md invariant 8 (audit envelope; actor_role on every operator action; append-only)._
