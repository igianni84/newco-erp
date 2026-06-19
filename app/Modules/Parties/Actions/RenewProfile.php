<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\ProfileRenewed;
use App\Modules\Parties\Exceptions\IllegalProfileTransition;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Transitions a Profile membership `Lapsed → Active` within the 30-day grace window, clears the `lapsed_at` anchor,
 * and records its {@see ProfileRenewed} event atomically (parties-membership-suspension, design L3/L4/L5/L9/L10/L11;
 * party-registry — Requirements: Profile Lapse and Grace Renewal, Demand-Side Status Events). It is the inverse of
 * {@see LapseProfile}.
 *
 * NAMING TRAP (design L3): the grace restore (`lapsed → active`) records {@see ProfileRenewed}, NOT
 * `ProfileReactivated` (which is the `suspended → active` edge only — {@see ReactivateProfile}). Never conflate the
 * lapse-renewal cycle with suspension restore.
 *
 * THE 30-DAY GRACE IS ENFORCED IN CODE (design L5; DEC-034; § 4.2.1): renewal is permitted ONLY when
 * `state === lapsed` AND the current moment is within 30 days of `lapsed_at` (the anchor {@see LapseProfile} stamps).
 * Past the grace window the call is rejected — in production the deferred scheduler instead transitions the Profile
 * `lapsed → cancelled` (the `CancelProfile` Action — task 2.3). Both a wrong from-state AND a past-grace lapsed
 * Profile throw {@see IllegalProfileTransition::cannotRenew()}; the boundary is INCLUSIVE (renewal exactly 30 days
 * after `lapsed_at` still succeeds).
 *
 * RENEWAL-PAYMENT TRIGGER — DEFERRED MODULE-E SEAM (design L5; § 4.2.1 / § 15.2 / § 15.8): in production the
 * `lapsed → active` transition is driven by a renewal payment (Module E's `MembershipFeePaid` signal extending
 * validity). Module E does not exist, so the listener that would invoke this Action on that signal is a documented
 * Module-E seam — NO Module-E event contract is fabricated (zero-invention). `RenewProfile` ships as the within-module
 * writer, invoked directly now.
 *
 * STATE-PRESERVING inverse of lapse (design L9): write ONLY `state` + clear the `lapsed_at` anchor. From-state guarded
 * and race-safe (design L4, mirroring {@see LapseProfile}): inside ONE {@see DB::transaction} it re-reads the Profile
 * `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op under SQLite — the from-state + grace assert carries
 * correctness either way), asserts the grace guard, then writes `active` + clears `lapsed_at` and records the event.
 * A disallowed call throws BEFORE any write, and the transaction rolls back leaving the Profile (state and `lapsed_at`)
 * and the event log unchanged. The payload reflects the POST-transition `state`. `version` is NOT bumped (parties-core
 * identity-revision semantics; the immutable domain event is the audit record of the transition). The Model stays
 * persistence-only; this Action is the sole state writer. `ProfileRenewed` is a ROOT event — a directly-invoked
 * renewal records no parent in its transaction, so no causation/correlation is threaded. The actor is resolved from
 * the {@see ActorContext} seam (System until real principals wire in).
 */
class RenewProfile
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
    ) {}

    public function handle(int $profileId): Profile
    {
        return DB::transaction(function () use ($profileId): Profile {
            // Transaction-locked re-read so two concurrent attempts serialize on PostgreSQL; the from-state + grace
            // assert below is the correctness guarantee (the lock is a no-op on SQLite).
            $profile = Profile::query()->whereKey($profileId)->lockForUpdate()->firstOrFail();

            // Renewal is reachable only from `lapsed` AND only within the 30-day grace window of `lapsed_at`
            // (DEC-034 — the boundary is inclusive). A wrong from-state OR a past-grace lapsed Profile both reject;
            // past grace the deferred scheduler instead cancels the Profile (`CancelProfile`, task 2.3).
            $graceDeadline = $profile->lapsed_at?->addDays(30);
            if (
                $profile->state !== ProfileState::Lapsed
                || $graceDeadline === null
                || CarbonImmutable::now()->greaterThan($graceDeadline)
            ) {
                throw IllegalProfileTransition::cannotRenew($profile->state);
            }

            // State-preserving inverse of lapse (design L9): write ONLY `state` and clear the grace anchor.
            $profile->update([
                'state' => ProfileState::Active,
                'lapsed_at' => null,
            ]);

            // No causation/correlation passed → the recorder makes this a root event (its `correlation_id` defaults
            // to its own `event_id`): a directly-invoked renewal records no parent event. The event class is the
            // single source of truth for the name / entity type / PII-free payload (`ProfileRenewed`, not
            // `ProfileReactivated` — L3).
            $this->recorder->record(
                name: ProfileRenewed::NAME,
                module: Module::Parties->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: ProfileRenewed::ENTITY_TYPE,
                entityId: (string) $profile->id,
                payload: ProfileRenewed::payload($profile),
            );

            return $profile;
        });
    }
}
