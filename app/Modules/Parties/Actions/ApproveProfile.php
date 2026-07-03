<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\OriginatingClubLocked;
use App\Modules\Parties\Exceptions\IllegalProfileTransition;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Approves a Profile membership and, in ONE transaction, drives it straight through to `active` — the atomic
 * **approve = charge = activation** collapse (canon MVP-DEC-016; parties-membership-charge-on-approval, design
 * Decisions #1/#2; party-registry — Requirements: Profile Membership Approval, Profile Activation, Demand-Side
 * Activation Events). On an `applied` Profile it writes `approved`, performs the conditional Originating-Club
 * one-shot lock, then invokes the within-module {@see ActivateProfile} writer to reach `active` — all inside the
 * SAME {@see DB::transaction}. `Approved` is a TRANSIENT pass-through, NEVER a durable resting state (§ 4.2.1 /
 * AC-K-FSM-2); the operation records the conditional {@see OriginatingClubLocked} (first-ever approval only) and the
 * `ProfileActivated` from the internal activation. {@see DeclineProfile} (`applied → rejected`, terminal,
 * event-silent) is unchanged.
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
 * is reachable only from `applied` (§ 4.2.1); this Action is the SOLE writer of the `applied → approved` transition
 * and DELEGATES the `approved → active` write to {@see ActivateProfile}.
 *
 * THE APPROVE WRITE IS AUDIT-ONLY (design L2): § 15.2 names NO `ProfileApproved` event, so — exactly as
 * {@see RecordKycVerified} writes `kyc_status` and records no KYC event (the audit trail is the record) — the
 * `approved` write itself records NO Profile event; the `state = approved` write IS its audit record. The events the
 * atomic operation records are the conditional {@see OriginatingClubLocked} (the first-ever-approval lock, below) and
 * the `ProfileActivated` the internal {@see ActivateProfile} records on the `approved → active` step.
 *
 * ORIGINATING-CLUB ONE-SHOT LOCK (design L3; § 6.1 / AC-K-J-4): after the `approved` write, re-read the Customer
 * `->lockForUpdate()`; if the Originating-Club FK is currently NULL, set it to THIS Profile's `club_id` and record
 * a root {@see OriginatingClubLocked} in the same transaction. The NULL-gate makes the lock IDEMPOTENT (a later
 * Club's approval finds it set → no write, no event) and IMMUTABLE (no other Action writes the column — there is no
 * `LockOriginatingClub` / `SetOriginatingClub`; the lock is an in-tx side-effect, not a standalone Action — design
 * L3). It MAY stay unset indefinitely for Discovery-only Customers (DEC-040). Writing the Customer's FK from a
 * Profile-transition Action locks two rows in one transaction — the within-module cross-entity pattern of
 * {@see RetireProducer} (Producer + Club) and {@see RecordKycVerified} (Customer + Hold). The lock is a ROOT event
 * and precedes the activation (so the recorded order is OriginatingClubLocked → `ProfileActivated`).
 *
 * HERO PACKAGE CAPACITY GATE — DEFERRED MODULE-A SEAM (design L7; § 13 / AC-K-J-13 / AC-K-XM-18): § 13 mandates the
 * membership no-oversell ceiling "at every membership approval", but § 13.2 puts the cap on Module A's Hero-Package
 * Allocation `qty` — and Module A is unbuilt, so the gate cannot read it without inventing A's contract. Approval
 * therefore ships UNCAPPED (the seat gate is MVP-DEC-017 / RM-05, after Module A), exactly as {@see ActivateProducer}
 * shipped before its KYC gate and {@see CloseClub} ships without its all-members-gone gate. The cap (and the
 * `Applied → WaitingList` capacity-exceeded path) lands with `parties-hero-package` after Module A.
 *
 * From-state guarded and race-safe (design L4, mirroring `ActivateProducer`): inside ONE {@see DB::transaction} it
 * re-reads the Profile `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op under SQLite — the from-state
 * assert carries correctness either way), asserts `state === applied`, writes `approved`, locks the Originating Club,
 * then drives through {@see ActivateProfile} to `active`. A call on a Profile not in `applied` throws
 * {@see IllegalProfileTransition::cannotApprove()} BEFORE any write, and the transaction rolls back leaving the
 * Profile, the Customer and the event log unchanged; the internal activation shares this transaction, so were it to
 * fail the whole approval rolls back (the Profile stays `applied` — the charge-fail shape). `version` is NOT bumped
 * (parties-core identity-revision semantics). The Models stay persistence-only; this Action is the sole writer of the
 * `applied → approved` transition and of the conditional Originating-Club link. The actor is resolved from the
 * {@see ActorContext} seam (System until real principals wire in).
 */
class ApproveProfile
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
        private readonly ActivateProfile $activateProfile,
    ) {}

    public function handle(int $profileId): Profile
    {
        return DB::transaction(function () use ($profileId): Profile {
            // Transaction-locked re-read so two concurrent approvals serialize on PostgreSQL; the from-state
            // assert below is the correctness guarantee (the lock is a no-op on SQLite).
            $profile = Profile::query()->whereKey($profileId)->lockForUpdate()->firstOrFail();

            // Approval is reachable only from `applied` (§ 4.2.1); every other state rejects.
            if ($profile->state !== ProfileState::Applied) {
                throw IllegalProfileTransition::cannotApprove($profile->state);
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
