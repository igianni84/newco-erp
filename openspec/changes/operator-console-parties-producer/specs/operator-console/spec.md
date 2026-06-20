## ADDED Requirements

### Requirement: Operator creates a Producer through the console

The console SHALL let an operator create a **Producer** — the standalone winery-identity registry (not a Party subtype) — through a manual create surface that collects the Producer's identity attributes (a **name**, a **region** and a **country**, each required; an optional **appellation**, an optional translatable **description**, and an optional **website**), invoking `CreateProducer` and returning the created model (never `$model->save()`). A created Producer SHALL be persisted in `draft` and SHALL record exactly one `ProducerCreated` domain event, tagged module `parties` with a **PII-free** payload, carrying the `actor_role: newco_ops` audit envelope. The Producer ships **no** create-time uniqueness guard (two Producers with the same name both succeed), and the create surface SHALL expose **no** status or KYC field — both `status` (born `draft`) and `kyc_status` (born unset) are lifecycle-managed, never set at creation. Creating a Producer SHALL NOT create a Supplier as a side effect.

#### Scenario: Valid input creates a draft Producer and records ProducerCreated

- **WHEN** an operator submits a valid Producer (name, region, country) through the create surface
- **THEN** `CreateProducer` is invoked, a Producer exists in `draft` with its identity attributes persisted and its `kyc_status` unset
- **AND** exactly one `ProducerCreated` event is recorded with `actor_role: newco_ops`, `actor_id` equal to the operator's id, entity type `Producer`, and a payload containing no name/email/phone personal data of any party
- **AND** no Supplier row is created

#### Scenario: The create surface exposes the identity attributes and no lifecycle field

- **WHEN** the Producer create surface is inspected
- **THEN** it exposes name, region, country, appellation, description and website fields, and exposes no `status` and no `kyc_status` field

#### Scenario: Producer creation ships no uniqueness guard

- **WHEN** an operator creates two Producers with the same name
- **THEN** both are created in `draft`, each recording its own `ProducerCreated` event (no duplicate-identity rejection)

_Source: openspec/specs/party-registry/spec.md (Producer Registry; Spine Creation Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.4 (Producer — standalone winery registry; born `draft`; identity attributes; translatable description), §14.5 BR-K-Producer-1/3 (standalone; no auto-cross-create), §15.4 (`ProducerCreated`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-7 (birth half), §5 AC-K-EVT-8 · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.0 · app/Modules/Parties/Actions/CreateProducer.php · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md · decisions/2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse.md._

### Requirement: Operator advances a Producer through its supply-side status lifecycle

The console SHALL surface the Producer's status-FSM transitions — **activate** (`ActivateProducer`, `draft → active`, recording `ProducerActivated`) and **retire** (`RetireProducer`, `active → retired`, recording `ProducerRetired`) — each invoking the corresponding domain Action and recording its verbatim event with `actor_role: newco_ops`. Activation SHALL be **gated on KYC cleared**: the domain rejects it when the Producer's `kyc_status` is `pending` or `rejected` (cleared = `verified`, `not_required`, **or** NULL), and the console SHALL surface the rejection, leaving the Producer in `draft` with no event recorded; the console SHALL NOT re-check the gate. Retirement SHALL **cascade in-domain** to sunset every Club the Producer operates that is currently `active` (each recording a `ClubSunset` causally linked to the `ProducerRetired` root), while Clubs already `sunset`/`closed` are left unchanged — the cascade is the Action's own behaviour, and the console SHALL NOT expose a separate cascade-retire affordance. The activate action SHALL present **no** "second actor required" affordance — Producer activation is a single-operator, KYC-gated transition, not the catalog separation-of-duties governance. The console SHALL expose **no** submit, reject, or reopen action — the Producer FSM is linear (`draft → active → retired`) with no review-governance step. An out-of-state transition SHALL be surfaced as a notification without changing state.

#### Scenario: Activate a KYC-cleared draft Producer

- **WHEN** an operator activates a `draft` Producer whose `kyc_status` is cleared (`verified`, `not_required`, or NULL)
- **THEN** the Producer becomes `active` and exactly one `ProducerActivated` event is recorded with `actor_role: newco_ops` and `actor_id` equal to the operator's id

#### Scenario: Activation blocked by uncleared KYC is surfaced

- **WHEN** an operator activates a `draft` Producer whose `kyc_status` is `pending` or `rejected`
- **THEN** the domain rejects the activation, the console surfaces the reason as a notification, the Producer stays `draft`, and no `ProducerActivated` event is recorded

#### Scenario: Retire an active Producer cascades Club sunset

- **GIVEN** an `active` Producer that operates two `active` Clubs and one already-`closed` Club
- **WHEN** an operator retires the Producer
- **THEN** the Producer becomes `retired` and a `ProducerRetired` event is recorded with `actor_role: newco_ops`
- **AND** the two `active` Clubs transition to `sunset`, each recording a `ClubSunset` carrying the `ProducerRetired` event's id as its causation, while the `closed` Club is unchanged

#### Scenario: The console exposes neither a cascade-retire affordance nor the catalog governance verbs

- **WHEN** the Producer view surface is inspected
- **THEN** it exposes activate and retire but no separate cascade-retire action, no submit/reject/reopen action, and the activate action presents no "second actor required" affordance

#### Scenario: An out-of-state transition is surfaced without changing state

- **WHEN** an operator activates a Producer not in `draft`, or retires a Producer not in `active`
- **THEN** the domain raises an `IllegalProducerTransition`, the console surfaces it as a notification, and the Producer's `status` and the domain-event log are unchanged

_Source: openspec/specs/party-registry/spec.md (Producer Lifecycle; Supply-Side Lifecycle Events) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.4 (Producer FSM `draft → active → retired`; activation requires KYC cleared; retirement preserves Product Masters, blocks new activations), §10.2 (Producer offboarding cascade → Club sunset), §14.5 BR-K-Producer-2/4, §15.4 (`ProducerActivated`, `ProducerRetired`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-7 (activation gated on KYC cleared — positive `verified`/`not_required`, negative `pending`/`rejected`), §2 AC-K-J-10/AC-K-J-19, §5 AC-K-EVT-8, §6 AC-K-XM-2 (Module 0 consumes these events) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §3.0/§5.2 · app/Modules/Parties/Actions/ActivateProducer.php, RetireProducer.php, SunsetClub.php · app/Modules/Parties/Exceptions/IllegalProducerTransition.php · decisions/2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse.md._

### Requirement: Operator manages a Producer's KYC through the console

The console SHALL surface the Producer's provenance-KYC FSM (`not_required → pending → verified | rejected`, plus the operator waive to `not_required`) as four operator actions, each invoking the corresponding domain Action: **require** (`RequireProducerKyc`, `not_required`|NULL → `pending`), **waive** (`WaiveProducerKyc`, any outstanding state → `not_required`), **verify** (`RecordProducerKycVerified`, `pending → verified`), and **reject** (`RecordProducerKycRejected`, `pending → rejected`). These KYC transitions are **audit-only** — the PRD event catalog names no KYC event, so each records the `actor_role: newco_ops` audit envelope but **no** domain event — and the KYC FSM is **separate** from the Producer status FSM: a KYC transition SHALL NOT move the Producer's `status`. None of these actions SHALL place or lift a Hold (Producer KYC carries no Hold coupling). The console SHALL display the Producer's current `kyc_status` so that a KYC-blocked activation is explainable. An illegal KYC transition SHALL be surfaced as a notification without changing state.

#### Scenario: Require KYC moves not_required/NULL to pending, audit-only

- **WHEN** an operator requires KYC on a Producer whose `kyc_status` is `not_required` or NULL
- **THEN** `kyc_status` becomes `pending`, the Producer's `status` is unchanged, the transition is recorded with the `actor_role: newco_ops` audit envelope, and no domain event is recorded

#### Scenario: Verify and reject resolve a pending Producer, audit-only

- **WHEN** an operator verifies a Producer in KYC `pending`
- **THEN** `kyc_status` becomes `verified` (a cleared state), no domain event is recorded, and the Producer's `status` is unchanged
- **WHEN** an operator rejects a Producer in KYC `pending`
- **THEN** `kyc_status` becomes `rejected` (a blocking state), no domain event is recorded, and no Hold is placed

#### Scenario: Waive clears the gate from any outstanding state

- **WHEN** an operator waives KYC on a Producer whose `kyc_status` is `pending`, `rejected`, `verified`, or NULL
- **THEN** `kyc_status` becomes `not_required` (a cleared state), audit-only, and the Producer's `status` is unchanged

#### Scenario: KYC management unblocks a KYC-gated activation end-to-end

- **GIVEN** a `draft` Producer whose `kyc_status` is `pending`
- **WHEN** an operator activates it
- **THEN** the activation is rejected and surfaced, the Producer stays `draft`, and no `ProducerActivated` event is recorded
- **WHEN** the operator then verifies the Producer (KYC `pending → verified`) and activates it
- **THEN** the Producer becomes `active` and `ProducerActivated` is recorded

#### Scenario: An illegal KYC transition is surfaced without changing state

- **WHEN** an operator verifies or rejects a Producer not in KYC `pending`, or waives a Producer already `not_required`
- **THEN** the domain raises an `IllegalKycTransition`, the console surfaces it as a notification, and `kyc_status` is unchanged

_Source: openspec/specs/party-registry/spec.md (Producer KYC Lifecycle; Producer Lifecycle — the KYC-cleared activation gate) · spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.4 (Producer provenance-KYC four-state; `not_required`/`verified` cleared; separate from the status FSM), §9.1 (KYC lifecycle), §14.5 BR-K-Producer-2 · spec/04-decisions/decisions.md DEC-071 (KYC fields additive nullable) · decisions/2026-06-17-producer-kyc-gate-not-required-clears.md (waive → `not_required` clears the gate) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-7 · app/Modules/Parties/Actions/RequireProducerKyc.php, WaiveProducerKyc.php, RecordProducerKycVerified.php, RecordProducerKycRejected.php · app/Modules/Parties/Exceptions/IllegalKycTransition.php · decisions/2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse.md._
