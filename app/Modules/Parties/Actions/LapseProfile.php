<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\ProfileExpired;
use App\Modules\Parties\Exceptions\IllegalProfileTransition;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Transitions a Profile membership `Active → Lapsed`, stamps the grace-window anchor `lapsed_at`, and records its
 * {@see ProfileExpired} event atomically (parties-membership-suspension, design L3/L4/L5/L9/L10/L11; party-registry —
 * Requirements: Profile Lapse and Grace Renewal, Demand-Side Status Events).
 *
 * NAMING TRAP (design L3): the STATE is `Lapsed`, the EVENT is {@see ProfileExpired}. The § 15.2 family names **no**
 * `ProfileLapsed` — never coin one. The inverse within-grace restore is {@see RenewProfile} (`lapsed → active`,
 * recording `ProfileRenewed` — NOT `ProfileReactivated`, which is the `suspended → active` edge only).
 *
 * VALIDITY-PERIOD TRIGGER — DEFERRED SCHEDULER/MODULE-E SEAM (design L5; § 4.2.1 / § 15.2): in production the
 * `active → lapsed` transition is driven by "the membership validity period passes without a successful renewal" — a
 * time-based trigger owned by a scheduler/Module-E that does not exist yet. No scheduler and no Module-E event
 * contract are fabricated (zero-invention); `LapseProfile` ships as the within-module writer of the transition,
 * invoked by the operator path now and directly in tests — exactly as {@see ActivateProfile} ships the transition
 * with its upstream fee-paid signal as a documented seam.
 *
 * STATE-PRESERVING (design L9; § 10.1 / AC-K-FSM-2a): the lapse writes ONLY `Profile.state` and the additive
 * `lapsed_at` anchor — it does NOT cancel vouchers, pending orders or allocation reservations, nor mutate any Club
 * Credit balance. Vouchers/orders/reservations live in Module S/B/E and are unbuilt; the Club Credit entity is built
 * (Module K, `club-credit`), but lapse mutates no credit — the year-end forfeiture past `valid_to` is a deferred
 * scheduler seam owned by `ForfeitClubCredit`, never inline here. Nothing destructive happens — only this one row
 * changes.
 *
 * From-state guarded and race-safe (design L4, mirroring {@see SuspendProfile}): inside ONE {@see DB::transaction} it
 * re-reads the Profile `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op under SQLite — the from-state
 * assert carries correctness either way), asserts `state === active`, then writes `lapsed` + stamps `lapsed_at =
 * CarbonImmutable::now()` (the 30-day grace anchor {@see RenewProfile} reads — DEC-034) and records the event. A call
 * on a Profile not in `active` throws {@see IllegalProfileTransition::cannotLapse()} BEFORE any write, and the
 * transaction rolls back leaving the Profile (state and `lapsed_at`) and the event log unchanged. The payload
 * reflects the POST-transition `state`. `version` is NOT bumped (parties-core identity-revision semantics; the
 * immutable domain event is the audit record of the transition). The Model stays persistence-only; this Action is the
 * sole state writer. `ProfileExpired` is a ROOT event — the lapse has no parent transition, so no causation/correlation
 * is threaded. The actor is resolved from the {@see ActorContext} seam (System until real principals wire in).
 */
class LapseProfile
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
    ) {}

    public function handle(int $profileId): Profile
    {
        return DB::transaction(function () use ($profileId): Profile {
            // Transaction-locked re-read so two concurrent attempts serialize on PostgreSQL; the from-state assert
            // below is the correctness guarantee (the lock is a no-op on SQLite).
            $profile = Profile::query()->whereKey($profileId)->lockForUpdate()->firstOrFail();

            // Lapse is reachable only from `active` (§ 4.2.1); every other state rejects.
            if ($profile->state !== ProfileState::Active) {
                throw IllegalProfileTransition::cannotLapse($profile->state);
            }

            // State-preserving (design L9): write ONLY `state` + the additive grace anchor. `lapsed_at` is the
            // moment the grace window opens — RenewProfile reads it for the 30-day boundary (DEC-034).
            $profile->update([
                'state' => ProfileState::Lapsed,
                'lapsed_at' => CarbonImmutable::now(),
            ]);

            // No causation/correlation passed → the recorder makes this a root event (its `correlation_id` defaults
            // to its own `event_id`): a lapse records no parent event. The event class is the single source of truth
            // for the name / entity type / PII-free payload (and is `ProfileExpired`, never `ProfileLapsed` — L3).
            $this->recorder->record(
                name: ProfileExpired::NAME,
                module: Module::Parties->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: ProfileExpired::ENTITY_TYPE,
                entityId: (string) $profile->id,
                payload: ProfileExpired::payload($profile),
            );

            return $profile;
        });
    }
}
