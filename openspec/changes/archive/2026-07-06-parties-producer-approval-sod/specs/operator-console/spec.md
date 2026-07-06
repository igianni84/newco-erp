## MODIFIED Requirements

### Requirement: Operator advances a Producer through its supply-side status lifecycle

The console SHALL surface the Producer's status-FSM transitions — **activate** (`ActivateProducer`, `draft → active`, recording `ProducerActivated`) and **retire** (`RetireProducer`, `active → retired`, recording `ProducerRetired`) — each invoking the corresponding domain Action and recording its verbatim event with `actor_role: newco_ops`. Activation SHALL be **gated on KYC cleared**: the domain rejects it when the Producer's `kyc_status` is `pending` or `rejected` (cleared = `verified`, `not_required`, **or** NULL), and the console SHALL surface the rejection, leaving the Producer in `draft` with no event recorded; the console SHALL NOT re-check the gate. Retirement SHALL **cascade in-domain** to sunset every Club the Producer operates that is currently `active` (each recording a `ClubSunset` causally linked to the `ProducerRetired` root), while Clubs already `sunset`/`closed` are left unchanged — the cascade is the Action's own behaviour, and the console SHALL NOT expose a separate cascade-retire affordance. The activate action SHALL present the **"second actor required"** affordance: Producer activation is subject to the domain **separation-of-duties floor** — an authenticated `newco_ops` operator, **distinct from the Producer's creator**, self-approval never allowed — enforced by the domain alongside the KYC-cleared gate. A same-actor (creator self-approval) or non-operator (`system`/unauthenticated) violation SHALL be rejected by the domain, surfaced to the operator as a notification, and leave the Producer, the audit log and the domain-event log unchanged; the console SHALL NOT reimplement the floor. The console SHALL expose **no** submit, reject, or reopen action — the Producer FSM is linear (`draft → active → retired`) with no review-governance step (the separation-of-duties floor is the spec-admissible 2-step Creator → Approver depth, with no distinct Reviewer leg). An out-of-state transition SHALL be surfaced as a notification without changing state.

#### Scenario: Activate a KYC-cleared draft Producer by a distinct operator

- **GIVEN** a KYC-cleared (`verified`, `not_required`, or NULL) `draft` Producer created by one operator
- **WHEN** a **distinct** authenticated operator activates it
- **THEN** the Producer becomes `active` and exactly one `ProducerActivated` event is recorded with `actor_role: newco_ops` and `actor_id` equal to the activating operator's id

#### Scenario: Self-approval is rejected and surfaced

- **GIVEN** a KYC-cleared `draft` Producer created by operator A
- **WHEN** operator A activates it
- **THEN** the domain rejects it on the separation-of-duties floor, the Producer stays `draft`, no `ProducerActivated` event is recorded, and the console shows a notification that a distinct actor is required

#### Scenario: Activation blocked by uncleared KYC is surfaced

- **WHEN** a distinct authenticated operator activates a `draft` Producer whose `kyc_status` is `pending` or `rejected`
- **THEN** the domain rejects the activation, the console surfaces the reason as a notification, the Producer stays `draft`, and no `ProducerActivated` event is recorded

#### Scenario: Retire an active Producer cascades Club sunset

- **GIVEN** an `active` Producer that operates two `active` Clubs and one already-`closed` Club
- **WHEN** an operator retires the Producer
- **THEN** the Producer becomes `retired` and a `ProducerRetired` event is recorded with `actor_role: newco_ops`
- **AND** the two `active` Clubs transition to `sunset`, each recording a `ClubSunset` carrying the `ProducerRetired` event's id as its causation, while the `closed` Club is unchanged

#### Scenario: The console exposes a cascade-retire-free surface with the second-actor affordance

- **WHEN** the Producer view surface is inspected
- **THEN** it exposes activate and retire but no separate cascade-retire action, no submit/reject/reopen action, and the activate action **presents** the "second actor required" affordance

#### Scenario: An out-of-state transition is surfaced without changing state

- **WHEN** an operator activates a Producer not in `draft`, or retires a Producer not in `active`
- **THEN** the domain raises an `IllegalProducerTransition`, the console surfaces it as a notification, and the Producer's `status` and the domain-event log are unchanged

_Source: openspec/specs/party-registry/spec.md (Producer Lifecycle; Supply-Side Lifecycle Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.4 (Producer FSM `draft → active → retired`; activation requires KYC cleared; the Producer content-approval workflow; §0 Q3 role-count admin-configurable, 2-step admitted, floor holds at any depth; retirement preserves Product Masters, blocks new activations), §10.2 (Producer offboarding cascade → Club sunset), §14.5 BR-K-Producer-2/4, §15.4 (`ProducerActivated`, `ProducerRetired`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-7 (activation gated on KYC cleared — positive `verified`/`not_required`, negative `pending`/`rejected`), §2 AC-K-J-10 (Creator → Reviewer → Approver against Producer content; **assert workflow with distinct actors at the configured depth**), AC-K-J-19, §5 AC-K-EVT-8, §6 AC-K-XM-2 (Module 0 consumes these events) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §5.2 (the multi-actor discipline — the "second actor required" affordance; distinct actors; self-approval never allowed; names Module K §4.4 Producer activation), §3.0 · decisions/2026-06-17-approval-separation-of-duties-role-gated.md (RESOLVED — retain the strict distinct-actor floor) · openspec/specs/operator-console/spec.md (Operator advances each catalog spine entity through the review-and-approval lifecycle — the "second actor required" + `ApprovalGovernance` surface pattern mirrored here) · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md (SoD surfaced, not reimplemented) · app/Modules/Parties/Actions/ActivateProducer.php, RetireProducer.php, SunsetClub.php · app/Modules/Parties/Exceptions/IllegalProducerTransition.php · decisions/2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse.md · MODIFIES the frozen *Operator advances a Producer through its supply-side status lifecycle* requirement (openspec/specs/operator-console/spec.md — which stated the activate action presents **no** "second actor required" affordance and Producer activation is not the catalog separation-of-duties governance)._
