<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Contracts\HeroPackageCapacityReader;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\OriginatingClubLocked;
use App\Modules\Parties\Events\WaitingListJoined;
use App\Modules\Parties\Exceptions\IllegalProfileTransition;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use App\Modules\Parties\Support\ClubSeatOccupancy;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Approves a Profile membership and, in ONE transaction, drives it straight through to `active` — the atomic
 * **approve = charge = activation** collapse (canon MVP-DEC-016; parties-membership-charge-on-approval, design
 * Decisions #1/#2; parties-hero-package, design D3/D4/D8; party-registry — Requirements: Profile Membership
 * Approval, Profile Activation, Demand-Side Activation Events, Hero Package Membership Capacity Is Enforced at
 * Every Seat-Consuming Transition, WaitingList Placement, Conversion and Decline). On an `applied` — or, equally,
 * a `waiting_list` — Profile whose Club has a free seat it writes `approved`, performs the conditional
 * Originating-Club one-shot lock, then invokes the within-module {@see ActivateProfile} writer to reach `active`
 * — all inside the SAME {@see DB::transaction}. `Approved` is a TRANSIENT pass-through, NEVER a durable resting
 * state (§ 4.2.1 / AC-K-FSM-2); the operation records the conditional {@see OriginatingClubLocked} (first-ever
 * approval only) and the `ProfileActivated` from the internal activation.
 *
 * A `waiting_list → active` CONVERSION IS NOT A DISTINCT ACTION (§ 13.5; design D10): it is this same atomic
 * instant, under the same capacity gate and the same Originating-Club one-shot rule, which is why the from-state
 * set is `{applied, waiting_list}` and not `{applied}`. It is also the ONLY exit from the waitlist that reaches
 * `active` — nothing promotes a Profile automatically, on any trigger (design D5). The other exit is the terminal,
 * event-silent {@see DeclineProfile}.
 *
 * THE CHARGE IS A NO-OP MODULE-S SEAM TODAY (proposal — What Changes; design Non-Goals; canon MVP-DEC-016): approve
 * = charge = activation is the correct SHAPE, but Module S/E are single-file stubs — no mandate, no pull-capable
 * instrument, no invoice entity — so there is no charge to run. The K-internal atomic activate-on-approval is
 * UNCONDITIONAL now and DELEGATES to the Module-S `MembershipFeePaid` listener when Module S lands (**Module S
 * emits; Module E records; Module K consumes** — DEC-173; the same signal {@see ActivateProfile} documents as its
 * future trigger, firing INV1, no INV0 — DEC-157). The CHARGE-FAIL CONTRACT — a charge that fails at approval leaves
 * the Profile in `applied` (no `active`, no seat, no {@see OriginatingClubLocked}, re-attemptable) — is specified as
 * that Module-S target, not built here (no dead payment code — Simplicity First). NO `MembershipFeePaid` event class
 * is fabricated: Module K only *consumes* it (zero-invention).
 *
 * THE ONE RETAINED PRODUCER WRITE (Build Workplan § Phase 2 — L-PP / K-Q4): the operator/console-invocable Action
 * that approves a membership application (the producer-facing HTTP portal is deferred — admin-parity, DEC-083).
 * "Approve" is the producer's L-PP action; activation is its automatic consequence (design Decisions #4). Approval
 * is reachable from `applied` and from `waiting_list` (§ 4.2.1 / § 13.5); this Action is the SOLE writer of the
 * `applied | waiting_list → approved` transition, the SOLE writer of the `applied → waiting_list` capacity divert,
 * and DELEGATES the `approved → active` write to {@see ActivateProfile}.
 *
 * THE APPROVE WRITE IS AUDIT-ONLY (design L2): § 15.2 names NO `ProfileApproved` event, so — exactly as
 * {@see RecordKycVerified} writes `kyc_status` and records no KYC event (the audit trail is the record) — the
 * `approved` write itself records NO Profile event; the `state = approved` write IS its audit record. Across a
 * SUCCESSFUL approval the events recorded are therefore the conditional {@see OriginatingClubLocked} (the
 * first-ever-approval lock, below) and the `ProfileActivated` the internal {@see ActivateProfile} records on the
 * `approved → active` step; across a CAPACITY-DIVERTED approval the only event recorded is {@see WaitingListJoined}.
 *
 * ORIGINATING-CLUB ONE-SHOT LOCK (design L3; § 6.1 / AC-K-J-4): after the `approved` write, re-read the Customer
 * `->lockForUpdate()`; if the Originating-Club FK is currently NULL, set it to THIS Profile's `club_id` and record
 * a root {@see OriginatingClubLocked} in the same transaction. The NULL-gate makes the lock IDEMPOTENT (a later
 * Club's approval finds it set → no write, no event) and IMMUTABLE (no other Action writes the column — there is no
 * `LockOriginatingClub` / `SetOriginatingClub`; the lock is an in-tx side-effect, not a standalone Action — design
 * L3). It MAY stay unset indefinitely for Discovery-only Customers (DEC-040). Writing the Customer's FK from a
 * Profile-transition Action locks two rows in one transaction — the within-module cross-entity pattern of
 * {@see RetireProducer} (Producer + Club) and {@see RecordKycVerified} (Customer + Hold). The lock is a ROOT event
 * and precedes the activation (so the recorded order is OriginatingClubLocked → `ProfileActivated`). It fires ONLY
 * on an approval that reaches `active`: a capacity-diverted approval takes no charge and locks no Originating Club.
 *
 * THE HERO-PACKAGE CAPACITY GATE, AND THE ROW IT SERIALISES ON (parties-hero-package design D3/D8; § 13 /
 * AC-K-J-13 / AC-K-XM-18; canon MVP-DEC-017). Approval is the seat-CONSUMING instant — `active` and `suspended`
 * are the seat-occupying states, and neither `applied` nor `waiting_list` holds a seat — so this Action is the sole
 * enforcement point of the membership no-oversell invariant (CLAUDE.md invariant 1). Locking the Profile row does
 * NOT serialise it: two concurrent approvals of DIFFERENT Profiles in the SAME Club lock different rows, both read
 * `49/50`, both pass, and the Club ends with 51 seats occupied against a capacity of 50. So, inside the transaction
 * and BEFORE any write, {@see ClubSeatOccupancy::lockAndCountOccupiedSeats()} takes the `parties_clubs` row lock and
 * only THEN counts — same-Club approvals queue, different Clubs stay parallel. The capacity itself is never stored
 * in Module K (`AC-K-XM-20`): it is read through Module K's own {@see HeroPackageCapacityReader} port, whose launch
 * adapter is config-backed. An UNSET capacity means UNCAPPED — the shipped production posture — so the gate passes
 * unconditionally and every pre-existing caller behaves exactly as before (a dark launch).
 *
 * AT PARITY THIS ACTION TRANSITIONS; IT THROWS ONLY WHERE NO TRANSITION EXISTS (design D8). An `applied` Profile
 * approved into a full Club is NOT an illegal transition — canon has it LAND in `waiting_list` (AC-K-J-13): the
 * state is written, exactly one {@see WaitingListJoined} is recorded, and NO charge, NO Originating-Club lock and NO
 * `ProfileActivated` follow. A Profile ALREADY in `waiting_list` whose Club is STILL full has no edge left to take,
 * so it raises {@see IllegalProfileTransition::clubAtCapacity()} — naming the capacity and the occupancy the gate
 * just decided on — writing no state and recording no second `WaitingListJoined`. A silent idempotent no-op would be
 * indistinguishable from a defect to the operator who clicked the button.
 *
 * From-state guarded and race-safe (design L4, mirroring `ActivateProducer`): inside ONE {@see DB::transaction} it
 * re-reads the Profile `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op under SQLite), asserts the
 * from-state is `applied` or `waiting_list`, takes the Club-row lock and evaluates the capacity gate, then writes
 * `approved`, locks the Originating Club and drives through {@see ActivateProfile} to `active`. THE FROM-STATE GUARD
 * PRECEDES THE GATE, and the order is load-bearing: a call on a Profile in any other state throws
 * {@see IllegalProfileTransition::cannotApprove()} BEFORE any write and before any Club lock — it must never be
 * diverted onto the waitlist merely because its Club happens to be full, and a doomed call must not serialise a
 * Club. The transaction rolls back leaving the Profile, the Customer and the event log unchanged; the internal
 * activation shares this transaction, so were it to fail the whole approval rolls back (the Profile stays `applied`
 * — the charge-fail shape). `version` is NOT bumped (parties-core identity-revision semantics). The Models stay
 * persistence-only; this Action is the sole writer of its transitions and of the conditional Originating-Club link.
 * The actor is resolved from the {@see ActorContext} seam (System until real principals wire in).
 */
class ApproveProfile
{
    /**
     * The seat ledger AND the capacity port are both injected — unlike {@see CreateProfile}, which routes a birth
     * state on the ledger's boolean alone and so needs no capacity number. This Action builds the operator-facing
     * rejection, which names the capacity, so it reads the port directly for that one number. The ledger still owns
     * the rule (`null` ⇒ uncapped; `>=`, not `>`); it is never re-spelled here.
     */
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
        private readonly ActivateProfile $activateProfile,
        private readonly ClubSeatOccupancy $seats,
        private readonly HeroPackageCapacityReader $capacity,
    ) {}

    public function handle(int $profileId): Profile
    {
        return DB::transaction(function () use ($profileId): Profile {
            // Transaction-locked re-read so two concurrent approvals serialize on PostgreSQL; the from-state
            // assert below is the correctness guarantee (the lock is a no-op on SQLite).
            $profile = Profile::query()->whereKey($profileId)->lockForUpdate()->firstOrFail();

            // Approval is reachable from `applied` and from `waiting_list` — the conversion is the same atomic
            // instant, not a distinct Action (§ 4.2.1 / § 13.5). Every other state rejects. THIS GUARD PRECEDES THE
            // CAPACITY GATE BELOW: an `active` or `lapsed` Profile in a full Club must throw `cannotApprove`, never
            // be diverted onto the waitlist, and a doomed call must take no Club-row lock.
            if (! in_array($profile->state, [ProfileState::Applied, ProfileState::WaitingList], true)) {
                throw IllegalProfileTransition::cannotApprove($profile->state);
            }

            // THE SEAT GATE (design D3). Approval newly consumes a seat, so the occupancy is counted under the
            // `parties_clubs` row lock — acquired STRICTLY FIRST, inside this transaction. That ordering is the fix:
            // the Profile-row lock above serialises nothing here, because two concurrent approvals in one Club lock
            // two different Profile rows and would both observe the same last free seat.
            $occupiedSeats = $this->seats->lockAndCountOccupiedSeats($profile->club_id);
            $capacity = $this->capacity->forClub($profile->club_id);

            // `wouldOversell()` owns the rule — including "a `null` capacity is UNCAPPED, never an oversell" — so it
            // is not re-spelled here. The `!== null` conjunct is redundant to that rule and load-bearing to the
            // TYPES: `clubAtCapacity()` takes an `int` capacity precisely because an uncapped Club can never reach
            // it, so the capped branch must be established before the rejection can be built. PHPStan holds this
            // Action to the proof the ledger already makes.
            if ($capacity !== null && $this->seats->wouldOversell($profile->club_id, $occupiedSeats)) {
                // A Profile already ON the waitlist has no edge left to take (design D8): no state is written, and
                // no second WaitingListJoined is recorded. The reason names the capacity and the occupancy this gate
                // decided on — the numbers are handed to the factory, never counted a second time.
                if ($profile->state === ProfileState::WaitingList) {
                    throw IllegalProfileTransition::clubAtCapacity($profile->state, $capacity, $occupiedSeats);
                }

                // An `applied` Profile DOES have an edge: canon has it LAND in `waiting_list` (AC-K-J-13), so this is
                // a transition, not a rejection. No charge, no Originating-Club lock, no ProfileActivated — the
                // approval consumed no seat. The event is recorded in this same transaction as the state write, and
                // reads the post-write state off the row (design D7: `WaitingListJoined` fires at BOTH entry points,
                // this divert and CreateProfile's birth). Nothing will promote it off the waitlist automatically
                // (design D5); only a later approve, once a seat is free, converts it.
                $profile->update(['state' => ProfileState::WaitingList]);

                $this->recorder->record(
                    name: WaitingListJoined::NAME,
                    module: Module::Parties->value,
                    actorRole: $this->actor->role(),
                    actorId: $this->actor->actorId(),
                    entityType: WaitingListJoined::ENTITY_TYPE,
                    entityId: (string) $profile->id,
                    payload: WaitingListJoined::payload($profile),
                );

                return $profile;
            }

            $profile->update(['state' => ProfileState::Approved]);

            // The Originating-Club one-shot lock (design L3): on the Customer's FIRST-EVER approval, set the link
            // to THIS approving Club and record the lock — in the same transaction. The Customer is re-read under
            // its own row lock; the NULL-gate makes the lock idempotent (a later Club's approval finds it set → no
            // write, no event), and no other Action writes the column, so it is immutable thereafter.
            $customer = Customer::query()->whereKey($profile->customer_id)->lockForUpdate()->firstOrFail();

            if ($customer->originating_club_id === null) {
                $customer->update(['originating_club_id' => $profile->club_id]);

                // OriginatingClubLocked is a ROOT event (no causation/correlation passed → the recorder defaults
                // `correlation_id` to its own `event_id`): the approval records no Profile event to parent it. The
                // event class is the single source of truth for the name / entity type / PII-free payload — the
                // locking `club_id` is THIS Profile's Club (design L3).
                $this->recorder->record(
                    name: OriginatingClubLocked::NAME,
                    module: Module::Parties->value,
                    actorRole: $this->actor->role(),
                    actorId: $this->actor->actorId(),
                    entityType: OriginatingClubLocked::ENTITY_TYPE,
                    entityId: (string) $customer->id,
                    payload: OriginatingClubLocked::payload($customer, $profile),
                );
            }

            // Atomic approve = charge = activation (canon MVP-DEC-016): drive the TRANSIENT `approved` straight
            // through to `active` via the within-module ActivateProfile writer, INSIDE this same transaction — the
            // charge is the no-op Module-S seam today (it delegates to the Module-S `MembershipFeePaid` listener when
            // Module S lands — DEC-173). ActivateProfile re-reads the now-`approved` Profile under its own savepoint,
            // records `ProfileActivated`, and returns it `active`; any failure here rolls the whole approval back
            // (the Profile stays `applied` — the charge-fail shape).
            return $this->activateProfile->handle($profile->id);
        });
    }
}
