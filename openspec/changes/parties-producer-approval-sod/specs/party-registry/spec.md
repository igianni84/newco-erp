## MODIFIED Requirements

### Requirement: Producer Lifecycle

The Producer SHALL transition through its state machine `draft → active → retired` (one operating direction; the FSM is linear) via explicit operator Actions that are the sole writers of `Producer.status`, each recording its lifecycle event in the same database transaction as the state write.

A Producer in `draft` SHALL transition to `active` on an `ActivateProducer` operation, recording **`ProducerActivated`**. Activation SHALL enforce the **KYC-cleared gate** (§ 4.4; BR-K-Producer-2): the Producer's `kyc_status` SHALL be **cleared** — `verified`, `not_required`, **or NULL** (a Producer never touched by KYC) — and the activation SHALL be **rejected** while `kyc_status` is `pending` or `rejected`, leaving the Producer in `draft` and recording no event. NULL is treated as cleared so the additive KYC field (DEC-071) does not break the activation of Producers created before this change; an operator may explicitly set `not_required` to **waive** KYC (ADR `2026-06-17-producer-kyc-gate-not-required-clears.md`). This closes the deferred seam the previously-shipped slice left ungated; `ProducerActivated` therefore fires on `draft → active` only when KYC is cleared (§ 15.4).

`ActivateProducer` SHALL additionally enforce the **separation-of-duties floor** (Admin Panel PRD § 5.2 — the multi-actor discipline naming Module K § 4.4 Producer activation; AC-K-J-10; the resolved distinct-actor floor of `decisions/2026-06-17-approval-separation-of-duties-role-gated.md`). The activating actor SHALL be an **authenticated operator principal** — `actor_role = newco_ops` with a non-null `actor_id` — so a `system`/null actor SHALL be **rejected** with a localized separation-of-duties violation, leaving the Producer in `draft` and recording no event. The activating actor SHALL be a **distinct actor from the Producer's creator** — the `actor_id` recorded on the Producer's `ProducerCreated` event, recovered as the **earliest** append-only domain event for the Producer; an activation whose actor equals the creator (self-approval) SHALL be **rejected** on the separation-of-duties floor, leaving the Producer in `draft` and recording no event. A Producer with **no recoverable creator actor** (e.g. a system-seeded row whose creation recorded a null actor) imposes no creator-distinctness constraint but still requires the operator-principal floor. This is the spec-admissible **2-step Creator → Approver** depth (Module K § 4.4 / § 0 Q3, role-count admin-configurable, the floor holding at any depth); the distinct **Reviewer** leg — a `reviewed` review-governance state — is not part of the linear Producer FSM and is out of this change. Both gates SHALL hold: a violation of **either** the KYC-cleared gate or the separation-of-duties floor leaves the Producer in `draft` with no `ProducerActivated` event recorded.

A Producer in `active` SHALL transition to `retired` on a `RetireProducer` operation, recording **`ProducerRetired`**, and SHALL **cascade**: every Club the Producer operates that is currently in `active` SHALL transition to `sunset` (recording its own `ClubSunset`, per the Club Lifecycle requirement) within the same transaction. Clubs already in `sunset` or `closed` SHALL be left unchanged (the cascade is idempotent over already-transitioned Clubs). The **Profile leg** of the § 10.2 offboarding cascade (per-Profile cancellation and the Module-S Club-Credit conversion signal) SHALL NOT be performed by this change — it is deferred with Profile lifecycle.

Every transition SHALL be **from-state guarded**: an `ActivateProducer` on a Producer not in `draft`, or a `RetireProducer` on a Producer not in `active`, SHALL be rejected with a localized `IllegalProducerTransition` and SHALL leave all state and the event log unchanged. The guard SHALL be evaluated against a transaction-locked re-read of the row so concurrent transition attempts cannot both succeed.

#### Scenario: Activate a draft Producer

- **GIVEN** a Producer in `draft` whose `kyc_status` is cleared (`verified`, `not_required`, or NULL), created by one operator
- **WHEN** a **distinct** authenticated `newco_ops` operator invokes `ActivateProducer`
- **THEN** the Producer's status becomes `active` and a `ProducerActivated` event is recorded in the same transaction (module `parties`, entityType `Producer`, PII-free payload)

#### Scenario: Self-approval by the Producer's creator is rejected

- **GIVEN** a Producer in `draft` with KYC cleared, created by operator A
- **WHEN** operator A invokes `ActivateProducer` on that Producer
- **THEN** the activation is rejected on the separation-of-duties floor, the Producer stays `draft`, and no `ProducerActivated` event is recorded

#### Scenario: A system or unauthenticated actor cannot activate

- **WHEN** `ActivateProducer` is invoked in a `system`/unauthenticated context (no `newco_ops` operator principal) on a `draft` Producer with KYC cleared
- **THEN** the activation is rejected — the distinct-actor floor cannot be satisfied without an operator principal — the Producer stays `draft`, and no `ProducerActivated` event is recorded

#### Scenario: Activation requires KYC cleared

- **WHEN** a distinct authenticated operator invokes `ActivateProducer` on a `draft` Producer whose `kyc_status` is `verified`, `not_required`, or NULL
- **THEN** the activation succeeds and `ProducerActivated` is recorded
- **WHEN** a distinct authenticated operator invokes `ActivateProducer` on a `draft` Producer whose `kyc_status` is `pending` or `rejected`
- **THEN** the activation is rejected, the Producer stays `draft`, and no `ProducerActivated` event is recorded

#### Scenario: Retire an active Producer cascades Club sunset

- **GIVEN** a Producer in `active` that operates two Clubs in `active` and one Club already in `closed`
- **WHEN** `RetireProducer` is invoked
- **THEN** the Producer's status becomes `retired` and a `ProducerRetired` event is recorded
- **AND** the two `active` Clubs transition to `sunset`, each recording a `ClubSunset` caused by the retirement, while the `closed` Club is left unchanged

#### Scenario: Illegal Producer transitions are rejected

- **WHEN** `ActivateProducer` is invoked on a Producer not in `draft`, or `RetireProducer` on a Producer not in `active`
- **THEN** an `IllegalProducerTransition` is raised, the Producer's status is unchanged, and no `ProducerActivated` / `ProducerRetired` (and no cascade `ClubSunset`) event is recorded

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.4 (Producer FSM `draft → active → retired`; **activation requires KYC cleared — `verified` or `not_required`**; the Producer content-approval workflow; retirement preserves Product Masters, blocks new activations) · § 0 Q3 / § 2 (producer-content approval role-count admin-configurable — 2-step Creator → Approver admitted, the separation-of-duties floor holds at any depth) · § 10.2 (Producer offboarding cascade → Club sunset) · § 14.5 BR-K-Producer-2/4 · § 15.4 (`ProducerActivated`, `ProducerRetired`) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md § 5.2 (the multi-actor discipline — distinct actors; self-approval never allowed; names Module K § 4.4 Producer activation), § 1.3 (the `actor_role` audit envelope) · spec/04-decisions/decisions.md DEC-071 (KYC/sanctions fields nullable, added in compliance) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 3 AC-K-FSM-7 (activation gated on KYC cleared — positive `verified`/`not_required`, negative `pending`/`rejected`), § 2 AC-K-J-10 (Creator → Reviewer → Approver against Producer content; **assert workflow with distinct actors at the configured depth**), AC-K-J-19, § 5 AC-K-EVT-8, § 6 AC-K-XM-2 (Module 0 consumes these events to gate Product Master activation) · decisions/2026-06-17-approval-separation-of-duties-role-gated.md (RESOLVED — retain the strict distinct-actor floor: two distinct people, self-approval never allowed) · decisions/2026-06-17-producer-kyc-gate-not-required-clears.md (the KYC gate; NULL treated as cleared) · decisions/2026-06-15-identity-auth.md (authenticated operator → `newco_ops` + `Operator.id` via the `ActorContext` seam) · decisions/2026-06-12-event-substrate-and-audit-store.md (transactional, PII-free recording; the append-only event store the creator is read from) · openspec/specs/product-catalog/spec.md (Approval Governance — the mirrored operator-principal + distinct-actor floor at the 2-step depth) · CLAUDE.md invariant 8 (audit envelope; actor on every operator action) · MODIFIES the frozen *Producer Lifecycle* requirement (openspec/specs/party-registry/spec.md — which gated `ActivateProducer` on from-state + KYC only, with no distinct-actor / operator-principal floor)._
