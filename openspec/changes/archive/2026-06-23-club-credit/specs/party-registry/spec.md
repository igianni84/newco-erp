## ADDED Requirements

### Requirement: Club Credit Entity and One-Active-Per-Profile Invariant

The system SHALL persist **Club Credit** as a Module K entity (`parties_club_credits`) — a per-Profile **prepayment instrument**, entirely distinct from the Voucher. Each Club Credit SHALL reference exactly one **Profile** (a within-module reference) and SHALL carry: an `amount` and a `remaining` balance, each an integer count of minor units plus an ISO 4217 currency code (a `Money`, never a float — invariant 6); a validity window (`valid_from`, `valid_to`); and a `state` ∈ {`active`, `redeemed`, `forfeited`}. The FSM SHALL be `active → redeemed | forfeited`: a Club Credit is created `active`; `redeemed` and `forfeited` are reached only through the writer Actions; and `redeemed → active` is reachable only via `RestoreClubCredit` (a downstream order-cancellation effect, not a Club Credit primitive). The `amount` and `remaining` currencies SHALL be equal and SHALL be **immutable** across the credit's lifetime (set once at issuance).

At most **one `active` Club Credit per Profile** SHALL exist at any moment. This invariant SHALL be enforced **structurally** by a partial unique index on `(profile_id)` scoped to `state = 'active'` — so a `redeemed` or `forfeited` credit frees the slot and the next issuance inserts cleanly, while a second concurrent `active` insert for the same Profile is rejected at the database level.

#### Scenario: A Club Credit carries amount, remaining, validity and an FSM state

- **WHEN** a Club Credit is created
- **THEN** it references exactly one Profile, carries `amount` and `remaining` as `Money` (integer minor units + ISO 4217 currency), a `valid_from`/`valid_to` window, and `state = active`, with `amount` and `remaining` sharing one immutable currency

#### Scenario: At most one active Club Credit per Profile (structural)

- **GIVEN** a Profile that already holds an `active` Club Credit
- **WHEN** a second `active` Club Credit is inserted for that Profile
- **THEN** the partial unique index rejects it (the one-active invariant holds at the database level)

#### Scenario: A terminal credit frees the slot

- **GIVEN** a Profile whose only Club Credit is `redeemed` or `forfeited`
- **WHEN** a new `active` Club Credit is inserted for that Profile
- **THEN** it inserts cleanly (the partial index covers only `active` rows)

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md §11 (Club Credit — per-Profile prepayment instrument; fields: Profile reference, amount + currency, status lifecycle `active → redeemed | forfeited` with `redeemed → active` only on order cancellation, validity window, remaining balance; "only one active Club Credit per Profile at any moment"; currency immutable across lifetime) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-17 (Club Credit FSM + one-active-per-Profile invariant preserved across all paths) · spec/04-decisions/decisions.md DEC-007 (hero-package fee → club credit) · CONTEXT.md (Club Credit — a monetary credit entity attached to a membership, distinct from Voucher) · CLAUDE.md invariant 6 (money = integer minor units + currency) · openspec/specs/money/spec.md (Money Value Object) · openspec/specs/party-registry/spec.md (Profile — Multi-Profile Membership — the within-module parent)._

### Requirement: Club Credit Issuance

A Club Credit SHALL be issued by an explicit within-module `IssueClubCredit` Action — the sole creator of a Club Credit row — running inside one `DB::transaction`. Issuance SHALL be gated on the Profile's Club having `generates_credit = true`; an issuance for a Profile whose Club has `generates_credit = false` SHALL be rejected with a localized exception and create no row. The issued credit's `amount` SHALL equal the Club's `fee` **verbatim** (both minor units and currency) — at launch the welcome-window proportional scaling (K.18) is deferred, so **full fee → full credit**; `remaining` SHALL be initialized equal to `amount`; `valid_from` SHALL be the issuance moment and `valid_to` SHALL default to **31 December of the issuance year**; and the credit SHALL be created in `state = active`. A Club with `generates_credit = true` but **no `fee`** cannot define an amount; such an issuance SHALL be rejected (no zero/undefined credit).

In production the issuance trigger is Module E's `MembershipFeePaid` event, gated on payment-provider-confirmed payment success (§11.1); Module E does not exist, so the **`MembershipFeePaid` listener is a deferred Module-E seam** — `IssueClubCredit` is the within-module writer, invoked by the operator/seam path now and directly in tests, and **no** Module-E event contract is fabricated. Issuance SHALL NOT be blocked by Holds (the entitlement is recorded once the fee is paid — only redemption is Hold-gated, §11.2). The operator manual-issuance path (K.19) SHALL NOT be built at launch (launch goodwill routes through the Module S `REFUND_COMPENSATION` coupon); the `IssueClubCredit` writer is itself the retained K.19 seam.

#### Scenario: Issue a full-fee credit on a credit-generating Club

- **GIVEN** a Profile whose Club has `generates_credit = true` and a non-null `fee`, and no `active` Club Credit
- **WHEN** `IssueClubCredit` is invoked for that Profile
- **THEN** a Club Credit is created `active` with `amount` equal to the Club's `fee` (minor units + currency), `remaining` equal to `amount`, `valid_from` the issuance moment and `valid_to` 31 December of the issuance year

#### Scenario: Issuance is refused when the Club does not generate credit

- **WHEN** `IssueClubCredit` is invoked for a Profile whose Club has `generates_credit = false`
- **THEN** the issuance is rejected and no Club Credit row is created

#### Scenario: Issuance is refused when the Club has no fee

- **WHEN** `IssueClubCredit` is invoked for a Profile whose Club has `generates_credit = true` but `fee` is null
- **THEN** the issuance is rejected (no amount can be defined) and no Club Credit row is created

#### Scenario: The fee-paid trigger is a deferred Module-E seam

- **WHEN** the Parties code surface is inspected
- **THEN** `IssueClubCredit` exists as the within-module writer of issuance, and there is **no** `MembershipFeePaid` listener and no fabricated Module-E event class in this change

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md §11.1 (issuance — auto-generated when Module K consumes Module E's `MembershipFeePaid` and the Profile's Club has `generates_credit = true`; gated on payment-provider-confirmed success; K.18 welcome-window scaling DEFERRED — launch full-fee → full-credit, the `policy × (fee_paid/full_fee)` hook retained in Module K; K.19 operator manual issuance DEFERRED with retained seam), §11 (validity window default 31 Dec of issuance year; amount/currency set at issuance), §11.2 (issuance not Hold-gated; only redemption is) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-J-16 (auto-issuance on `generates_credit = true`), AC-K-MVP-3 (K.18 deferred-with-seam — launch full-fee → full-credit), AC-K-MVP-4 / AC-K-J-16a (K.19 operator manual issuance deferred; goodwill via REFUND_COMPENSATION), AC-K-J-17 (welcome-window scaling criterion retained for restore) · spec/04-decisions/decisions.md DEC-007 (fee → club credit) · openspec/changes/archive/2026-06-19-parties-membership-activation (design L5 — the `MembershipFeePaid` deferred-Module-E-seam precedent) · decisions/2026-06-12-event-substrate-and-audit-store.md._

### Requirement: Club Credit Redemption and Carry-Forward

A Club Credit SHALL be redeemed by an explicit within-module `ApplyClubCredit` Action — the sole writer of `remaining` and of the `active → redeemed` transition — running inside one `DB::transaction` against a transaction-locked re-read. Given a redeemed amount (a `Money`), the Action SHALL, before any write: reject unless the credit is `active`; reject unless the redeemed amount's currency equals the credit currency; and reject unless the redeemed amount does not exceed `remaining` (no negative balance — a package exceeding the credit applies the full `remaining` and the difference is paid in cash, a Module S concern). It SHALL then set `remaining = remaining − redeemed`: if the new `remaining` is **zero**, the credit SHALL transition to `redeemed`; if the new `remaining` is **positive**, the credit SHALL stay `active` and the balance **carries forward** for future purchases (**K.17**).

Redemption SHALL be **frozen while the owning Profile is suspended**: `ApplyClubCredit` SHALL reject when the Profile's `state = Suspended` (no redemption during suspension — AC-K-FSM-2a; the credit becomes mutable again once the Profile is restored). The data Module K exposes for eligibility is the credit's `active` state, its `remaining`, its currency, and its issuing Club (via `profile.club_id`); the **checkout decision** — Offer matching (`credit.profile.club_id ∈ offer.club_ids`), currency-match at price resolution, the coupon **mutual-exclusion** (one coupon XOR one Club Credit per checkout), auto-apply, and the Hold-gated price resolution — is a **Module S** concern and SHALL NOT be built in this change.

#### Scenario: Partial redemption carries the balance forward (K.17)

- **GIVEN** an `active` Club Credit with `remaining` of 25000 minor units (EUR)
- **WHEN** `ApplyClubCredit` redeems 9000 minor units (EUR)
- **THEN** `remaining` becomes 16000 minor units and the credit stays `active` (the balance carries forward to future purchases)

#### Scenario: Full redemption transitions to redeemed

- **GIVEN** an `active` Club Credit with `remaining` of 16000 minor units (EUR)
- **WHEN** `ApplyClubCredit` redeems 16000 minor units (EUR)
- **THEN** `remaining` becomes zero and the credit transitions to `redeemed`

#### Scenario: Over-application and currency mismatch are rejected

- **WHEN** `ApplyClubCredit` redeems more than `remaining`, or redeems an amount whose currency differs from the credit currency
- **THEN** the Action is rejected with a localized exception and the credit's `remaining` and `state` are unchanged

#### Scenario: Redemption is frozen while the Profile is suspended

- **GIVEN** an `active` Club Credit whose owning Profile is `Suspended`
- **WHEN** `ApplyClubCredit` is invoked
- **THEN** it is rejected (the credit is frozen during suspension); after the Profile is restored to `Active`, the same redemption succeeds

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md §11.2 (redemption — against the issuing Club only, `credit.profile.club_id ∈ offer.club_ids`, currency-match required; mechanics live in Module S; Module K provides the eligibility data; redemption Hold-gated, issuance not), §11 (remaining balance / K.17 partial-redemption carry-forward — "carries forward for future purchases until forfeiture"; full redemption is the norm, the Customer pays any difference), §11.5 (commercial-coupon mutual-exclusion at checkout — Module S enforcement), §10.1 (Club Credit frozen, no accrual/redemption while the Profile is suspended; mutable again on restore) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-J-18 (redemption against the issuing Club; partial redemption leaves a `remaining_balance` that carries forward; cross-club rejected), AC-K-FSM-2a (Profile suspension freezes Club Credit — no accrual, no redemption) · spec/04-decisions/decisions.md DEC-110 (price-resolution stacking; promo + club credit mutually exclusive — Module S), DEC-111 (club-credit auto-apply at checkout — Module S) · openspec/specs/party-registry/spec.md (Profile Suspension and Restoration — the `Suspended` state this freeze reads)._

### Requirement: Club Credit Forfeiture and Restoration

A Club Credit SHALL be forfeited by an explicit within-module `ForfeitClubCredit` Action — the sole writer of the `active → forfeited` transition — running inside one `DB::transaction` against a transaction-locked re-read; it SHALL reject unless the credit is `active`, and `forfeited` SHALL be **terminal** (at most one forfeiture per credit lifetime). A Club Credit SHALL be restored by an explicit within-module `RestoreClubCredit` Action — the sole writer of the `redeemed → active` transition — which SHALL reject unless the credit is `redeemed` **and** the Profile holds no other `active` Club Credit (the one-active invariant is respected, not violated).

The **forfeiture triggers** (§11.3) are documented cross-module / scheduler seams **not** wired by this change: year-end lapse past `valid_to` (a **scheduler** seam — mirrors the `LapseProfile` validity-period seam); renewal-triggered replacement (**forfeit-before-issue**, sequenced within the Module-E renewal-time `MembershipFeePaid` consumption); Profile cancellation (a within-module follow-on cascade); and Club closure, on which the residual balance is converted to Discovery store credit at face value with 12-month validity (**DEC-043**) — an operation **owned by Module S**, with Module K's role ending at the upstream cancellation/closure signal (**AC-K-XM-23**). The **forfeit-before-issue ordering** is nonetheless exercised at launch: because the one-active invariant makes `IssueClubCredit` reject when an `active` credit exists, re-issuance requires `ForfeitClubCredit` then `IssueClubCredit`. The **order-cancellation-window** trigger for `RestoreClubCredit` is likewise a Module-S seam; the writer ships and is tested directly.

#### Scenario: Forfeit an active credit (terminal)

- **WHEN** `ForfeitClubCredit` is invoked on an `active` credit
- **THEN** its `state` becomes `forfeited`; any subsequent forfeiture, apply or restore on it is rejected (terminal)

#### Scenario: Forfeit-before-issue ordering holds via the one-active invariant

- **GIVEN** a Profile with an `active` Club Credit
- **WHEN** `IssueClubCredit` is invoked again for that Profile
- **THEN** it is rejected (one-active); and after `ForfeitClubCredit` forfeits the existing credit, `IssueClubCredit` then succeeds — the forfeit-before-issue ordering the renewal listener will perform

#### Scenario: Restore a redeemed credit, one-active-respecting

- **GIVEN** a `redeemed` Club Credit whose Profile holds no other `active` credit
- **WHEN** `RestoreClubCredit` is invoked
- **THEN** the credit returns to `active` with its `remaining` restored
- **WHEN** instead the Profile already holds another `active` credit
- **THEN** `RestoreClubCredit` is rejected (the one-active invariant is preserved)

#### Scenario: Club-closure conversion is not owned by Module K

- **WHEN** the Parties code surface is inspected
- **THEN** there is **no** Club-Credit-to-store-credit conversion logic in Module K (the DEC-043 conversion is a Module S concern); Module K ships only the `ForfeitClubCredit` writer and ends at the upstream signal

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md §11.3 (forfeiture triggers: year-end lapse past `valid_to` via a daily job; renewal-triggered replacement — forfeit-before-issue, sequenced within the renewal-time `MembershipFeePaid` consumption; Profile cancellation; Club closure → DEC-043 conversion owned by Module S; at most one forfeiture per lifetime — terminal), §11 (status lifecycle — `redeemed → active` only on order cancellation within the cancellation window, a downstream effect) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-17 (forfeiture paths + one-active invariant preserved across all paths; club-closure forfeiture = DEC-043 conversion trigger owned by Module S), AC-K-XM-23 (Module K does NOT execute the conversion math; its role ends at the upstream signal) · spec/04-decisions/decisions.md DEC-043 (Club Credit → Discovery store credit at face, 12-month validity, on producer offboarding) · openspec/specs/party-registry/spec.md (Profile Lapse and Grace Renewal — the `LapseProfile` scheduler-seam precedent)._

### Requirement: Club Credit State Recording Is Module-E-Owned

Module K SHALL NOT emit any Club Credit lifecycle domain event. The events `ClubCreditIssued`, `ClubCreditApplied`, `ClubCreditRestored` and `ClubCreditForfeited` — and the upstream `MembershipFeePaid` — are **Module E's** events (§11.4 / §15.8); Module K consumes them and records the resulting state on its own Club Credit entity. Because Module E does not exist (Phase 6), this change SHALL build the within-module writer Actions as **audit-only** state writers — they `update()` the credit `state`/`remaining` and record **no** domain event — and SHALL fabricate **no** `MembershipFeePaid` or `ClubCredit*` event class. This mirrors the audit-only-write precedent (a KYC transition records `kyc_status` with no KYC event; the Account family writes `status` with no event). When Module E lands, its `MembershipFeePaid` listener and its `ClubCredit*` consumers SHALL invoke these same within-module writers; the entity-state authority (Module K) and the financial-event authority (Module E) compose without rework.

#### Scenario: No Club Credit domain event is emitted by Module K

- **WHEN** any Club Credit writer (`IssueClubCredit` / `ApplyClubCredit` / `ForfeitClubCredit` / `RestoreClubCredit`) runs
- **THEN** the credit's state is updated and **no** `domain_events` row named `ClubCreditIssued` / `ClubCreditApplied` / `ClubCreditRestored` / `ClubCreditForfeited` (or `MembershipFeePaid`) is recorded by Module K

#### Scenario: No Module-E event class is fabricated

- **WHEN** the Parties code surface is inspected
- **THEN** no `MembershipFeePaid`, `ClubCreditIssued`, `ClubCreditApplied`, `ClubCreditRestored` or `ClubCreditForfeited` event class exists under `app/Modules/Parties` (the §11.4 ownership boundary; the invent-no-event discipline)

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md §11.4 (Module K does NOT emit Club Credit lifecycle events; Module E emits `ClubCreditIssued` / `ClubCreditApplied` / `ClubCreditRestored` / `ClubCreditForfeited`; Module K consumes and records the resulting state), §15.8 (events Module K consumes, recorded by Module E — `MembershipFeePaid` + the four `ClubCredit*`), §14 BR-K-Contract-2 (Module K records state; Module E records the financial events; Xero decides GL treatment) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-J-16 / AC-K-FSM-17 (Module K records the Club Credit state) · openspec/changes/archive/2026-06-19-parties-membership-activation (design L2 — the audit-only-write precedent: a status transition that records no domain event; L5 — no Module-E contract fabricated) · decisions/2026-06-12-event-substrate-and-audit-store.md (the `domain_events` outbox — what is and is not recorded) · decisions/2026-06-11-modular-monolith-architecture.md (events are the inter-module API; the R-reconciliations of event ownership) · CLAUDE.md invariant 4 (financial immutability — Module E is the financial event recorder)._

## MODIFIED Requirements

### Requirement: Birth States Recorded, Lifecycle Transitions Deferred

Every Parties entity that carries a lifecycle state SHALL define its full state domain and SHALL be created in its birth state: Customer `pending`, Account `active`, Producer `draft`, Club `active`, ProducerAgreement `draft`, Profile `Applied` (Supplier carries no lifecycle state). The **supply-side** lifecycle — Producer, ProducerAgreement and Club — SHALL implement its state transitions and emit its lifecycle events, as governed by the Requirements *Producer Lifecycle*, *ProducerAgreement Lifecycle*, *Club Lifecycle* and *Supply-Side Lifecycle Events*. The **Customer and Producer compliance-screening lifecycles** — the KYC FSM and the Customer sanctions FSM, **each separate from the Customer/Producer status FSM** — SHALL be implemented as governed by the Requirements *Customer KYC Lifecycle*, *Customer Sanctions Screening Lifecycle*, *Producer KYC Lifecycle* and *Sanctions Screening Events*; their fields are added additively (nullable — DEC-071). The **demand-side** status lifecycle is now **fully implemented** across activation and suspension. Activation (the Requirements *Customer Onboarding Activation*, *Profile Membership Approval*, *Profile Activation* and *Demand-Side Activation Events*): Customer `pending → active`, Profile `Applied → Approved | Rejected` and `Approved → Active`, and the Originating-Club one-shot lock — emitting `CustomerActivated` / `ProfileActivated` / `OriginatingClubLocked` (approval and decline audit-only — § 15.2 names no `ProfileApproved` / `ProfileRejected`). Suspension and the remaining status edges (the Requirements *Profile Suspension and Restoration*, *Profile Lapse and Grace Renewal*, *Profile Cancellation and Deactivation*, *Customer Suspension and Closure*, *Account Status Lifecycle*, *Hold-Driven Status Coupling* and *Demand-Side Status Events*): Profile `Active → Suspended | Lapsed | Cancelled | Inactive` and `Lapsed → Active` grace, Customer `active → suspended | closed` (suspension cascading to the Customer's Profiles), and Account `active → suspended → closed` — emitting `CustomerSuspended` / `CustomerReactivated` / `CustomerClosed` and `ProfileSuspended` / `ProfileReactivated` / `ProfileExpired` / `ProfileRenewed` / `ProfileInactive` (Account transitions and Profile cancellation are **audit-only** — § 15 names no Account event and the § 15.2 family names no `ProfileCancelled`). The **Hold→`suspended` status coupling** is now implemented (the *Hold-Driven Status Coupling* requirement): placing a Hold drives every covered scope in its suspendable from-state to `suspended`, and lifting a Hold restores a covered scope **iff no other active Hold** still covers it (ADR 2026-06-19); the unified Hold registry and the `kyc` Hold compliance coupling (auto-place on KYC `pending`, auto-lift on `verified`) remain as governed by the Requirements *Hold Registry*, *Hold Lifecycle and Lift Discipline*, *Hold Events* and *Hold and Sanctions Read-API*. The **Club Credit** instrument is now implemented as a Module K entity (the Requirements *Club Credit Entity and One-Active-Per-Profile Invariant*, *Club Credit Issuance*, *Club Credit Redemption and Carry-Forward*, *Club Credit Forfeiture and Restoration* and *Club Credit State Recording Is Module-E-Owned*): a per-Profile prepayment instrument created `active` on issuance, with the FSM `active → redeemed | forfeited` driven by the within-module writers `IssueClubCredit` / `ApplyClubCredit` (K.17 carry-forward) / `ForfeitClubCredit` / `RestoreClubCredit`, the structural one-active-per-Profile invariant, and the freeze-while-suspended guarantee (the deferred `club-credit` seam the suspension slice named, now closed). These writers are **audit-only** — § 11.4 makes the `ClubCreditIssued` / `ClubCreditApplied` / `ClubCreditRestored` / `ClubCreditForfeited` lifecycle events (and the `MembershipFeePaid` trigger) **Module E's**, so Module K records state and emits no Club Credit event. The Club Credit **cross-module triggers** remain deferred seams: the Module-E `MembershipFeePaid` listener + `ClubCredit*` consumers (Phase 6), the Module-S checkout redemption and the Club-closure → Discovery store-credit conversion (DEC-043), the year-end-lapse scheduler, and the Profile-cancellation → forfeit cascade. Only three demand-side **status** seams SHALL remain deferred: the **Hero Package Capacity Invariant** (approval and activation ship **uncapped** — the Module-A seam), the `Applied → WaitingList` path (and its `WaitingListJoined` event), and **Customer-segment derivation** (and its `CustomerSegmentChanged` event) — until the follow-on changes (`parties-hero-package`, `parties-customer-segments`) implement them. `ActivateAccount` SHALL NOT exist (the Account is born `active`; its only `→ active` edge is the restore `ReactivateAccount`).

#### Scenario: Each entity is born in its birth state

- **WHEN** a Customer, Account, Producer, Club, ProducerAgreement or Profile is created
- **THEN** its state is, respectively, `pending`, `active`, `draft`, `active`, `draft`, `Applied`

#### Scenario: The demand-side status transitions and the Hold coupling exist; only the capacity, WaitingList and segment seams remain

- **WHEN** the Parties code surface is inspected
- **THEN** Producer, ProducerAgreement and Club expose lifecycle-transition operations and record their lifecycle events; the Customer/Producer KYC and Customer sanctions screening FSMs expose their transitions; the unified Hold registry exposes place/lift with the `kyc` Hold auto-placed on KYC `pending` and auto-lifted on `verified`; the demand-side **activation** transitions exist (Customer `pending → active` via `ActivateCustomer`, Profile `Applied → Approved | Rejected` via `ApproveProfile` / `DeclineProfile` and `Approved → Active` via `ActivateProfile`, with the Originating-Club one-shot lock); AND the demand-side **status** transitions exist — Profile `Active → Suspended | Lapsed | Cancelled | Inactive` and `Lapsed → Active` grace, Customer `active → suspended | closed` (cascading to Profiles), Account `active → suspended → closed` — recording `CustomerSuspended` / `CustomerReactivated` / `CustomerClosed` / `ProfileSuspended` / `ProfileReactivated` / `ProfileExpired` / `ProfileRenewed` / `ProfileInactive` (Account transitions and Profile cancellation audit-only)
- **AND** placing a Hold drives the covered scope (in its suspendable from-state) to `suspended` and lifting the last covering Hold restores it
- **AND** the Hero Package Capacity Invariant (approval and activation stay uncapped), the `Applied → WaitingList` path, and Customer-segment derivation do **not** exist; no `WaitingListJoined` / `CustomerSegmentChanged` event is recordable; and no `ActivateAccount` Action exists

#### Scenario: The Club Credit entity and its within-module FSM exist, audit-only

- **WHEN** the Parties code surface is inspected
- **THEN** the Club Credit entity exists with the writers `IssueClubCredit` / `ApplyClubCredit` / `ForfeitClubCredit` / `RestoreClubCredit` driving `active → redeemed | forfeited` under the one-active-per-Profile invariant, redemption frozen while the Profile is suspended
- **AND** Module K records Club Credit state with **no** `ClubCredit*` domain event of its own and **no** fabricated `MembershipFeePaid` / `ClubCredit*` event class (the § 11.4 ownership boundary)
- **AND** the Club Credit cross-module triggers — the Module-E `MembershipFeePaid` listener and `ClubCredit*` consumers, the Module-S checkout redemption and DEC-043 closure conversion, the year-end scheduler, and the Profile-cancellation cascade — do **not** exist (deferred seams)

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.1 / § 4.2.1 / § 4.3 / § 4.7 (per-entity state machines + birth states; the demand-side status FSMs now implemented) · § 4.8 / § 4.8.1 (the unified Hold registry + the `kyc` compliance coupling) · § 10.1 (Hold→suspension coupling — now implemented; Club Credit frozen while suspended) · § 9.1 / § 9.2 (KYC and sanctions screening FSMs) · § 11 / § 11.1–11.5 (Club Credit entity + lifecycle — now implemented; events Module-E-owned per § 11.4) · § 13 (Hero Package Capacity Invariant — deferred Module-A seam) · § 5 (Customer segments — deferred) · § 15 (lifecycle event families; no Account event; no `ProfileCancelled`; § 15.8 Module-E-owned Club Credit events) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-1 (Customer FSM + events), AC-K-FSM-2 / AC-K-FSM-2a (Profile FSM + suspension state-preservation + Club-Credit freeze), AC-K-FSM-9 (Account FSM; Holds drive `active → suspended`), AC-K-FSM-12 (lapsed grace), AC-K-FSM-13 (terminal soft-delete), AC-K-FSM-17 (Club Credit FSM + one-active invariant), AC-K-EVT-1 / AC-K-EVT-5 (the status events), AC-K-J-13 / AC-K-XM-18 (Hero Package capacity reads Module A `qty` — deferred) · decisions/2026-06-19-hold-status-coupling.md (the Hold→status coupling) · spec/05-release/Build_Workplan_v0.3-MVP.md § Phase 2 (the demand-side membership lifecycle + the Club-Credit entity/auto-issuance/one-active core) · openspec/changes/club-credit/proposal.md (the Club Credit entity + within-module FSM implemented here; its cross-module triggers deferred) · openspec/changes/parties-membership-suspension/proposal.md (the suspension subset; the `club-credit` freeze seam this change closes)._
