<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Exceptions\IllegalProfileTransition;
use App\Modules\Parties\Models\Profile;
use Illuminate\Support\Facades\DB;

/**
 * Declines a Profile membership `applied → rejected` atomically (parties-membership-activation, design L2/L4;
 * party-registry — Requirement: Profile Membership Approval).
 *
 * THE DECLINE LEG OF THE ONE RETAINED PRODUCER WRITE (Build Workplan § Phase 2 — L-PP / K-Q4): the
 * operator/console-invocable Action that declines a membership application. Decline is reachable only from
 * `applied` (§ 4.2.1) and `rejected` is TERMINAL-for-this-application: the partial unique index on
 * `parties_profiles` excludes `rejected`, so a later re-application via {@see CreateProfile} inserts a fresh
 * `applied` Profile on the same (Customer, Club) pair with no index conflict (rejected Profiles are not reused —
 * § 4.2.1). This Action is the SOLE writer of `Profile.state` for the transition.
 *
 * AUDIT-ONLY — EVENT-SILENT (design L2): § 15.2 names NO `ProfileRejected` event, so — exactly as
 * {@see RecordKycRejected} writes `kyc_status` and records nothing (the audit trail is the column itself) — decline
 * records NO domain event at all. No {@see DomainEventRecorder} is touched (no recorder/actor dependency): the
 * `state = rejected` write IS the audit record. The approve path's lone event — the conditional
 * {@see OriginatingClubLocked} — has no decline analogue (a decline locks no Originating Club).
 *
 * From-state guarded and race-safe (design L4, mirroring `RecordKycRejected`): inside ONE {@see DB::transaction} it
 * re-reads the row `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op under SQLite — the from-state assert
 * carries correctness either way), asserts `state === applied`, then writes `rejected`. A call on a Profile not in
 * `applied` throws {@see IllegalProfileTransition::cannotReject()} BEFORE any write, and the transaction rolls back
 * leaving the row unchanged. `version` is NOT bumped (parties-core identity-revision semantics). The Model stays
 * persistence-only; this Action is the only state writer (design L4).
 */
class DeclineProfile
{
    public function handle(int $profileId): Profile
    {
        return DB::transaction(function () use ($profileId): Profile {
            // Transaction-locked re-read so two concurrent declines serialize on PostgreSQL; the from-state
            // assert below is the correctness guarantee (the lock is a no-op on SQLite).
            $profile = Profile::query()->whereKey($profileId)->lockForUpdate()->firstOrFail();

            // Decline is reachable only from `applied` (§ 4.2.1); every other state rejects.
            if ($profile->state !== ProfileState::Applied) {
                throw IllegalProfileTransition::cannotReject($profile->state);
            }

            $profile->update(['state' => ProfileState::Rejected]);

            return $profile;
        });
    }
}
