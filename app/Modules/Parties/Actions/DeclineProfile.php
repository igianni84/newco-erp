<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Exceptions\IllegalProfileTransition;
use App\Modules\Parties\Models\Profile;
use Illuminate\Support\Facades\DB;

/**
 * Declines a Profile membership `applied → rejected` — or, equally, `waiting_list → rejected` — atomically
 * (parties-membership-activation, design L2/L4; parties-hero-package, design D8; party-registry — Requirements:
 * Profile Membership Approval, WaitingList Placement, Conversion and Decline).
 *
 * THE DECLINE LEG OF THE ONE RETAINED PRODUCER WRITE (Build Workplan § Phase 2 — L-PP / K-Q4): the
 * operator/console-invocable Action that declines a membership application. Decline is reachable from `applied` and
 * from `waiting_list` (§ 4.2.1:186 — the waitlist's two exits are `Approved` and `Rejected`), and `rejected` is
 * TERMINAL-for-this-application: the partial unique index on `parties_profiles` excludes `rejected`, so a later
 * re-application via {@see CreateProfile} inserts a fresh Profile on the same (Customer, Club) pair with no index
 * conflict (rejected Profiles are not reused — § 4.2.1). That re-application is itself capacity-routed, so a
 * declined waitlister who re-applies to a still-full Club is born back onto the waitlist. This Action is the SOLE
 * writer of `Profile.state` for the transition.
 *
 * IT TAKES NO CLUB-ROW LOCK AND READS NO CAPACITY (parties-hero-package, design D8). Neither `applied` nor
 * `waiting_list` is a seat-occupying state ({@see ClubSeatOccupancy}: the seat set is `Active` + `Suspended`), so a
 * decline neither frees a seat nor consumes one and can never oversell a Club. It therefore keeps its bare
 * constructor-less shape: no capacity port, no seat ledger, and none of the serialisation {@see ApproveProfile} must
 * pay for. Declining the last waitlister of a full Club leaves that Club exactly as full as it was.
 *
 * AUDIT-ONLY — EVENT-SILENT (design L2): § 15.2 names NO `ProfileRejected` event, so — exactly as
 * {@see RecordKycRejected} writes `kyc_status` and records nothing (the audit trail is the column itself) — decline
 * records NO domain event at all, from either from-state. No {@see DomainEventRecorder} is touched (no
 * recorder/actor dependency): the `state = rejected` write IS the audit record. The approve path's lone event — the
 * conditional {@see OriginatingClubLocked} — has no decline analogue (a decline locks no Originating Club), and
 * leaving the waitlist records no counterpart to the {@see WaitingListJoined} that entered it.
 *
 * From-state guarded and race-safe (design L4, mirroring `RecordKycRejected`): inside ONE {@see DB::transaction} it
 * re-reads the row `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op under SQLite — the from-state assert
 * carries correctness either way), asserts the from-state is `applied` or `waiting_list`, then writes `rejected`. A
 * call on a Profile in any other state throws {@see IllegalProfileTransition::cannotReject()} BEFORE any write, and
 * the transaction rolls back leaving the row unchanged. `version` is NOT bumped (parties-core identity-revision
 * semantics). The Model stays persistence-only; this Action is the only state writer (design L4).
 */
class DeclineProfile
{
    public function handle(int $profileId): Profile
    {
        return DB::transaction(function () use ($profileId): Profile {
            // Transaction-locked re-read so two concurrent declines serialize on PostgreSQL; the from-state
            // assert below is the correctness guarantee (the lock is a no-op on SQLite).
            $profile = Profile::query()->whereKey($profileId)->lockForUpdate()->firstOrFail();

            // Decline is reachable from `applied` and from `waiting_list` — declining a waitlisted applicant is the
            // waitlist's other exit, not a distinct Action (§ 4.2.1:186). Every other state rejects. No capacity is
            // read and no Club row is locked: neither from-state holds a seat, so a decline cannot oversell.
            if (! in_array($profile->state, [ProfileState::Applied, ProfileState::WaitingList], true)) {
                throw IllegalProfileTransition::cannotReject($profile->state);
            }

            $profile->update(['state' => ProfileState::Rejected]);

            return $profile;
        });
    }
}
