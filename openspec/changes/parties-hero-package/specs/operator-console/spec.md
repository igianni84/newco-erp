## MODIFIED Requirements

### Requirement: Operator creates a Profile through the console

The console SHALL let an operator create a **Profile** — Module K's per-Club membership entry — through a manual create surface that collects a target **Customer** and a target **Club** (both selected from within-module reads, no cross-module import beyond `{Models}`), invoking `CreateProfile($customerId, $clubId)` and returning the created model (never `$model->save()`). The surface SHALL construct **no** `Parties\Enums` operand enum and stay within the `{Models, Actions}` import surface. A created Profile SHALL be born **`Applied`** — **or `WaitingList` when the target Club is at its Hero-Package capacity** (per *Profile — Multi-Profile Membership*) — and SHALL record exactly one `ProfileCreated` domain event, plus a `WaitingListJoined` event when born waitlisted, each tagged module `parties` and carrying the `actor_role: newco_ops` audit envelope. The create surface SHALL expose **no** `state` field, SHALL **not** collect a `tier`, `role`, or inviter, and SHALL **not** expose or collect a capacity: the birth state is decided by the domain, never by the operator. A duplicate non-terminal Profile for the same Customer–Club pair SHALL be rejected (`DuplicateProfileForClub`) and surfaced — `waiting_list` is **non-terminal** and so blocks a second live Profile. A create targeting a Club that is **not `active`** (`sunset` or `closed`) SHALL be rejected (`ClubNotAcceptingMemberships`) and surfaced on the form, with no Profile and no event created, **whether or not that Club is at capacity**; the Club picker SHOULD present `active` Clubs.

#### Scenario: Valid input creates an applied Profile and records ProfileCreated

- **WHEN** an operator submits a valid Customer + an `active` Club with a free Hero-Package seat (or no configured capacity) through the create surface
- **THEN** `CreateProfile` is invoked and a Profile exists in `Applied` for that Customer and Club, with `auto_renew` inherited from the Club default
- **AND** exactly one `ProfileCreated` event is recorded with `actor_role: newco_ops`, `actor_id` equal to the operator's id, and entity type `Profile`

#### Scenario: A Club at capacity creates the Profile on the waiting list

- **WHEN** an operator submits a valid Customer + an `active` Club that is at its Hero-Package capacity
- **THEN** `CreateProfile` is invoked and the Profile exists in `WaitingList` (not `Applied`), and both a `ProfileCreated` and a `WaitingListJoined` event are recorded with `actor_role: newco_ops`

#### Scenario: A duplicate non-terminal Profile is rejected and surfaced

- **GIVEN** a Customer with a non-terminal Profile in a Club (including one in `waiting_list`)
- **WHEN** an operator submits a second Profile for the same Customer–Club pair
- **THEN** the domain raises a `DuplicateProfileForClub`, the console surfaces it on the form, and no second Profile and no event are created

#### Scenario: A sunset or closed Club is rejected and surfaced

- **WHEN** an operator submits a Profile targeting a Club in `sunset` or `closed`, at capacity or not
- **THEN** the domain raises a `ClubNotAcceptingMemberships`, the console surfaces it on the form, and no Profile, no `ProfileCreated` and no `WaitingListJoined` event are created

#### Scenario: The create surface exposes the membership operands and no lifecycle or capacity field

- **WHEN** the Profile create surface is inspected
- **THEN** it exposes a Customer select and a Club select, exposes no `state`/`tier`/`role` field and no capacity field, and the birth state (`Applied` or `WaitingList`) is decided by the domain

_Source: decisions/2026-07-09-hero-package-capacity-seat-set-and-waitinglist.md (§6 — birth-in-`WaitingList` at application, canon §7.1 step 6) · openspec/specs/party-registry/spec.md (*Profile — Multi-Profile Membership*; *WaitingList Placement, Conversion and Decline*; *Profile Auto-Renewal Preference*) · canon `c-mless/documentation` @ `360df0b` Module_K_PRD §7.1 step 6 (`:399`) · frozen spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.2.1, §4.3, §7.1:392 · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-2, **AC-K-FSM-6 / §4.4 AC-K-BR-Club-3** · app/Modules/Parties/Actions/CreateProfile.php · app/Modules/Parties/Exceptions/DuplicateProfileForClub.php · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md · MODIFIES the *Operator creates a Profile through the console* requirement (which asserted a created Profile is born `Applied`, full stop)._

### Requirement: Operator approves or declines a Profile membership through the console

The console SHALL surface the Profile membership-approval verbs — **approve** (`ApproveProfile`) and **decline** (`DeclineProfile`) — on the Profile view, each invoking the corresponding domain Action and never an Eloquent write. These are the **one retained producer write** at launch (membership approve/decline, the L-PP / K-Q4 producer write); exercised through the operator console they carry `actor_role: newco_ops` (DEC-083 / DEC-115 admin-parity).

Each verb SHALL be **form-less** and **visibility-gated to the `{Applied, WaitingList}` from-state set** — the exact complement of the widened domain from-state guard — so that **waitlist conversion and waitlist decline are reachable through the console**, and the from-state rejection (`IllegalProfileTransition`) stays unreachable for every other state.

**Approve** SHALL record **no** Profile-named approval event. Its outcome depends on the Club's Hero-Package capacity, evaluated by the domain (the console SHALL re-check **no** gate of its own):

- **A seat is free (or the Club is uncapped)** → the Profile becomes **`Active`** in one atomic operation (`Approved` is a transient pass-through, never durably rested-in — canon MVP-DEC-016), recording exactly one `ProfileActivated` event; and on the Customer's **first-ever** approval into any Club that reaches `Active`, exactly one additional `OriginatingClubLocked` event, setting the Customer's `originating_club_id` (the one-shot lock, idempotent thereafter).
- **The Club is at capacity and the Profile is `Applied`** → the Profile becomes **`WaitingList`**, recording exactly one `WaitingListJoined` event, with **no** charge, **no** `ProfileActivated` and **no** `OriginatingClubLocked`.
- **The Club is at capacity and the Profile is already `WaitingList`** → the domain rejects with a localized `IllegalProfileTransition` naming the capacity reason, surfaced as a danger notification, leaving the Profile unchanged.

Because a single `approve` click therefore has **two distinct successful outcomes**, the console SHALL **derive its success notification from the resulting Profile state** rather than emitting a fixed title: an approval that reaches `Active` SHALL surface the *approved* copy, and one that lands in `WaitingList` SHALL surface distinct *waitlisted* copy. The console SHALL NOT report a capacity-diverted approval as an approval. All such copy SHALL be localized in EN and IT.

**Decline** SHALL set `state = Rejected` from either `Applied` or `WaitingList` and SHALL record **no** domain event (the `state = rejected` write is the audit record).

#### Scenario: Approve an applied Profile with a free seat, activating it and locking the Originating Club

- **GIVEN** a Customer whose `originating_club_id` is unset, with an `Applied` Profile in a Club with a free seat
- **WHEN** an operator approves the Profile
- **THEN** the Profile becomes `Active` (never resting in `Approved`), the Customer's `originating_club_id` is set to that Profile's Club, and exactly one `OriginatingClubLocked` and one `ProfileActivated` event are recorded with `actor_role: newco_ops`
- **WHEN** the operator later approves a second Club's `Applied` Profile for the same Customer
- **THEN** that Profile becomes `Active` and **no** further `OriginatingClubLocked` event is recorded (the lock is one-shot)

#### Scenario: Approving into a Club at capacity waitlists the Profile and says so

- **GIVEN** an `Applied` Profile in a Club at exactly its Hero-Package capacity
- **WHEN** an operator approves it
- **THEN** the Profile becomes `WaitingList`, exactly one `WaitingListJoined` event is recorded with `actor_role: newco_ops`, no `ProfileActivated` and no `OriginatingClubLocked` are recorded
- **AND** the console surfaces the *waitlisted* notification copy — **not** the *approved* copy

#### Scenario: Approve converts a waitlisted Profile once a seat frees

- **GIVEN** a Profile in `WaitingList` whose Club now has a free seat
- **WHEN** an operator views the Profile and approves it
- **THEN** the `approve` verb is visible (it is not hidden on `waiting_list`), the Profile becomes `Active`, exactly one `ProfileActivated` event is recorded, and the console surfaces the *approved* notification copy

#### Scenario: Approving a waitlisted Profile while the Club is still at capacity is rejected and surfaced

- **GIVEN** a Profile in `WaitingList` whose Club is still at exactly its capacity
- **WHEN** an operator drives `approve` (visible from `waiting_list`)
- **THEN** the domain raises an `IllegalProfileTransition` naming the capacity reason, the console surfaces a danger notification carrying that message, the Profile stays `WaitingList`, and no event is recorded

#### Scenario: Decline an applied or waitlisted Profile is terminal and event-silent

- **GIVEN** a Profile in `Applied`, or a Profile in `WaitingList`
- **WHEN** an operator declines it
- **THEN** the Profile becomes `Rejected` and no domain event is recorded (the `state = rejected` write is the audit record)

#### Scenario: Approve and decline are offered only from Applied or WaitingList

- **WHEN** a Profile in neither `Applied` nor `WaitingList` is viewed
- **THEN** neither approve nor decline is offered, and an out-of-state approve/decline driven against the domain is rejected (`IllegalProfileTransition`) with state and the event log unchanged

_Source: decisions/2026-07-09-hero-package-capacity-seat-set-and-waitinglist.md (§7 — `ApproveProfile`'s from-state widens to `{applied, waiting_list}`, as does `DeclineProfile`'s; at parity it **transitions**, it does not throw) · openspec/specs/party-registry/spec.md (*Profile Membership Approval*; *WaitingList Placement, Conversion and Decline*; *Hero Package Capacity Invariant*) · canon `c-mless/documentation` @ `360df0b` Module_K_PRD §4.2.1:186, §13.5:655 (conversion is producer-discretionary and manual) · canon Module_K_Acceptance **AC-K-J-13**:92 · frozen spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.2.1, §3.1 (the one retained producer write), §6 / §6.1 · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md §2 + §3.K (operator-driven parity, DEC-083) · app/Modules/Parties/Actions/{ApproveProfile,DeclineProfile}.php · app/Modules/Parties/Events/{OriginatingClubLocked,WaitingListJoined}.php · app/Modules/OperatorPanel/Filament/Console/Concerns/SurfacesDomainActions.php (the fixed-success-title helper this requirement forces to become outcome-aware) · decisions/2026-06-19-operator-console-read-binding-write-through-actions.md (design L4 — the console re-checks no gate; it catches `RuntimeException` by base type) · CLAUDE.md invariant 12 (i18n) · MODIFIES the *Operator approves or declines a Profile membership through the console* requirement, which (a) gated both verbs to `Applied` **only**, making waitlist conversion unreachable, (b) declared *"The console SHALL author **no** `Applied → WaitingList` transition — that edge has no writer at launch"*, and (c) carried an **RM-03 residual**: it described approve as `Applied → Approved` and asserted the Profile *"becomes `Approved`"*, whereas RM-03 made `Approved` transient and the Profile reaches `Active`._

### Requirement: Operator advances a Profile through its lifecycle

The console SHALL surface the Profile's post-approval lifecycle transitions on the Profile view, each invoking its domain Action and never an Eloquent write, each **form-less** and **visibility-gated to its from-state**: **suspend** (`SuspendProfile`, `Active → Suspended`, recording `ProfileSuspended`), **reactivate** (`ReactivateProfile`, `Suspended → Active`, recording `ProfileReactivated`), **lapse** (`LapseProfile`, `Active → Lapsed`, recording `ProfileExpired`), **renew** (`RenewProfile`, `Lapsed → Active` within the 30-day grace, recording `ProfileRenewed`), **cancel** (`CancelProfile`, `Active | Lapsed → Cancelled`, **audit-only — no event**) and **deactivate** (`DeactivateProfile`, `Active → Inactive`, recording `ProfileInactive`). Each recorded event carries the `actor_role: newco_ops` audit envelope. The console SHALL surface **no** `activate` verb for a Profile: `Approved → Active` is driven inside the atomic `ApproveProfile` transaction and `Approved` is never a durable resting state, so there is no record for such a verb to act on.

**`reactivate` SHALL NEVER be capacity-blocked.** A `Suspended` Profile keeps its Hero-Package seat, so restoration is admitted even when the Club is at exactly its capacity — a temporary Hold must never evict a member. The console SHALL surface no capacity affordance on this verb.

**`renew` SHALL be capacity-gated by the domain.** `Lapsed → Active` re-consumes a seat, so a renew into a Club at capacity SHALL be rejected by the domain with a localized `IllegalProfileTransition` naming the capacity reason, surfaced as a danger notification, leaving the Profile `Lapsed` with its `lapsed_at` intact. The Profile SHALL **not** be moved to `WaitingList` by a renew.

**Suspension SHALL be state-preserving** — it changes only `state`; vouchers, orders, allocation reservations and Club Credit are untouched (the Club-Credit freeze is enforced at the redemption site). Each from-state-gated rejection (`IllegalProfileTransition`) SHALL be unreachable through the surface (the verb is hidden off its from-state) and SHALL be rejected by the domain when driven directly, leaving state and the event log unchanged. **`renew` is the sole verb with domain sub-gates that no visibility predicate can express**: visible from `Lapsed`, it is rejected and surfaced as a danger notification, without state change, when attempted past the 30-day grace **or** into a Club at capacity. `Cancelled` and `Inactive` are **terminal soft-delete** states — the row is never hard-deleted and stays queryable.

#### Scenario: Suspend then restore an active Profile, state-preserving

- **GIVEN** an `Active` Profile with an active voucher and an active Club Credit
- **WHEN** an operator suspends it
- **THEN** the Profile becomes `Suspended`, exactly one `ProfileSuspended` event is recorded, and the voucher and Club Credit are unchanged (suspension changes only `state`)
- **WHEN** the operator reactivates it
- **THEN** the Profile becomes `Active` and exactly one `ProfileReactivated` event is recorded

#### Scenario: Restoration is admitted at exact capacity

- **GIVEN** a `Suspended` Profile in a Club at exactly its Hero-Package capacity
- **WHEN** an operator reactivates it
- **THEN** the Profile becomes `Active`, exactly one `ProfileReactivated` event is recorded, and no capacity rejection is raised or surfaced

#### Scenario: Lapse, then renew within the 30-day grace with a free seat

- **GIVEN** an `Active` Profile in a Club with (after the lapse) a free seat
- **WHEN** an operator lapses it
- **THEN** the Profile becomes `Lapsed` and exactly one `ProfileExpired` event is recorded
- **WHEN** the operator renews it within 30 days of lapse
- **THEN** the Profile becomes `Active` and exactly one `ProfileRenewed` event is recorded

#### Scenario: A renew into a Club at capacity is rejected and surfaced

- **GIVEN** a Profile that lapsed within the last 30 days, whose Club has since reached exactly its capacity
- **WHEN** an operator drives `renew` (visible from `Lapsed`)
- **THEN** the domain raises an `IllegalProfileTransition` naming the capacity reason, the console surfaces a danger notification, and the Profile stays `Lapsed` with `lapsed_at` intact, not moved to `WaitingList`, with no event recorded

#### Scenario: A renew past the 30-day grace is rejected and surfaced

- **GIVEN** a Profile that lapsed more than 30 days ago
- **WHEN** an operator drives `renew` (visible from `Lapsed`)
- **THEN** the domain raises an `IllegalProfileTransition`, the console surfaces a danger notification, and the Profile stays `Lapsed` with no event recorded

#### Scenario: Cancel is terminal, audit-only, and the row is preserved

- **GIVEN** an `Active` (or `Lapsed`) Profile
- **WHEN** an operator cancels it
- **THEN** the Profile becomes `Cancelled`, no domain event is recorded (audit-only), and the row remains queryable (soft-delete, never hard-deleted)

#### Scenario: Deactivate records ProfileInactive

- **GIVEN** an `Active` Profile
- **WHEN** an operator deactivates it
- **THEN** the Profile becomes `Inactive` and exactly one `ProfileInactive` event is recorded

#### Scenario: Each lifecycle verb is offered only from its from-state, and no activate verb exists

- **WHEN** a Profile is viewed in any given state
- **THEN** only the verbs valid from that state are offered (suspend/lapse/deactivate from `Active`; reactivate from `Suspended`; renew from `Lapsed`; cancel from `Active` or `Lapsed`), **no** `activate` verb is offered in any state, and an out-of-state transition driven against the domain is rejected (`IllegalProfileTransition`) with state and the event log unchanged

_Source: decisions/2026-07-09-hero-package-capacity-seat-set-and-waitinglist.md (§2 — `Suspended → Active` never capacity-re-checked; §11 — `RenewProfile` **is** cap-gated, and is not the grandfathered rollover) · openspec/specs/party-registry/spec.md (*Profile Lapse and Grace Renewal*; *Profile Suspension and Restoration*; *Profile Activation*; *Hero Package Capacity Invariant*) · canon `c-mless/documentation` @ `360df0b` Module_K_PRD §13.1:625/:627/:629, §10.1:532 · canon Module_K_Acceptance **AC-K-FSM-2a**:114 · frozen spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.2.1, §13, §10.1 · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md §3 AC-K-FSM-2, AC-K-FSM-2a, AC-K-FSM-12 (30-day grace, DEC-034), AC-K-FSM-13 · app/Modules/Parties/Actions/{SuspendProfile,ReactivateProfile,LapseProfile,RenewProfile,CancelProfile,DeactivateProfile}.php · app/Modules/OperatorPanel/Filament/Resources/Parties/ProfileResource/Pages/ViewProfile.php (`getHeaderActions()` — which has never carried an `activate` verb) · decisions/2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse.md · MODIFIES the *Operator advances a Profile through its lifecycle* requirement, which (a) declared *"**Activation SHALL ship uncapped**"* and *"the console drives `ActivateProfile` without a capacity check and surfaces no cap"*, (b) applied **no capacity gate** to `renew` and made the 30-day grace its *"sole exception"*, and (c) carried an **RM-03 residual** — it enumerated an **`activate` (`ActivateProfile`, `Approved → Active`)** console verb, with a *"Activate an approved Profile, uncapped"* scenario, that `ViewProfile::getHeaderActions()` has never surfaced and `OperatorPanel` never references._
