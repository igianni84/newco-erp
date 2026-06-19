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
 * Approves a Profile membership `applied → approved` and, on the Customer's FIRST-EVER approval across any Club,
 * locks the Originating-Club link — all atomically (parties-membership-activation, design L2/L3/L4/L7/L8;
 * party-registry — Requirements: Profile Membership Approval, Demand-Side Activation Events).
 *
 * THE ONE RETAINED PRODUCER WRITE (Build Workplan § Phase 2 — L-PP / K-Q4): the operator/console-invocable Action
 * that approves a membership application (the producer-facing HTTP portal is deferred — admin-parity, DEC-083).
 * Approval is reachable only from `applied` (§ 4.2.1); this Action is the SOLE writer of `Profile.state` for the
 * transition.
 *
 * AUDIT-ONLY (design L2): § 15.2 names NO `ProfileApproved` event, so — exactly as {@see RecordKycVerified} writes
 * `kyc_status` and records no KYC event (the audit trail is the record) — approval records NO Profile event. The
 * `state = approved` write IS the audit record. The ONLY domain event the approve path may record is the
 * conditional {@see OriginatingClubLocked}, and only on the first-ever approval.
 *
 * ORIGINATING-CLUB ONE-SHOT LOCK (design L3; § 6.1 / AC-K-J-4): after the `approved` write, re-read the Customer
 * `->lockForUpdate()`; if the Originating-Club FK is currently NULL, set it to THIS Profile's `club_id` and record
 * a root {@see OriginatingClubLocked} in the same transaction. The NULL-gate makes the lock IDEMPOTENT (a later
 * Club's approval finds it set → no write, no event) and IMMUTABLE (no other Action writes the column — there is no
 * `LockOriginatingClub` / `SetOriginatingClub`; the lock is an in-tx side-effect, not a standalone Action — design
 * L3). It MAY stay unset indefinitely for Discovery-only Customers (DEC-040). Writing the Customer's FK from a
 * Profile-transition Action locks two rows in one transaction — the within-module cross-entity pattern of
 * {@see RetireProducer} (Producer + Club) and {@see RecordKycVerified} (Customer + Hold). The lock event is a ROOT
 * event: the approval records no Profile event to be its parent.
 *
 * HERO PACKAGE CAPACITY GATE — DEFERRED MODULE-A SEAM (design L7; § 13 / AC-K-J-13 / AC-K-XM-18): § 13 mandates the
 * membership no-oversell ceiling "at every membership approval", but § 13.2 puts the cap on Module A's Hero-Package
 * Allocation `qty` — and Module A is unbuilt, so the gate cannot read it without inventing A's contract. Approval
 * therefore ships UNCAPPED, exactly as {@see ActivateProducer} shipped before its KYC gate and {@see CloseClub}
 * ships without its all-members-gone gate. The cap (and the `Applied → WaitingList` capacity-exceeded path) lands
 * with `parties-hero-package` after Module A.
 *
 * From-state guarded and race-safe (design L4, mirroring `ActivateProducer`): inside ONE {@see DB::transaction} it
 * re-reads the Profile `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op under SQLite — the from-state
 * assert carries correctness either way), asserts `state === applied`, then writes `approved`. A call on a Profile
 * not in `applied` throws {@see IllegalProfileTransition::cannotApprove()} BEFORE any write, and the transaction
 * rolls back leaving the Profile, the Customer and the event log unchanged. `version` is NOT bumped (parties-core
 * identity-revision semantics). The Models stay persistence-only; this Action is the sole writer of the transition
 * and of the conditional Originating-Club link. The actor is resolved from the {@see ActorContext} seam (System
 * until real principals wire in).
 */
class ApproveProfile
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
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

            return $profile;
        });
    }
}
