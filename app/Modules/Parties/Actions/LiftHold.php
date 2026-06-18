<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\HoldStatus;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Events\CustomerHoldLifted;
use App\Modules\Parties\Exceptions\IllegalHoldLift;
use App\Modules\Parties\Models\Hold;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Lifts one `active` Hold via the operator path — the lifting counterpart of {@see PlaceHold} (parties-holds,
 * design L2/L3; party-registry — Requirements: Hold Lifecycle and Lift Discipline, Hold Events). It moves the
 * {@see Hold} `active → lifted`, records the lift actor/moment and the optional business `lift_reason`, and
 * records the verbatim {@see CustomerHoldLifted} event in the SAME transaction.
 *
 * This is the SOLE operator lift-writer of a `parties_holds` row (the model stays persistence-only — design L3).
 * It enforces the per-type lift discipline (DEC-160 § 4.8.1; AC-K-FSM-11; ADR
 * 2026-06-18-hold-lift-discipline-per-type) which lives on {@see HoldType::autoLiftable()}:
 *
 * - an **auto-managed** type (`kyc` / `payment` — `autoLiftable()`) is REJECTED here with
 *   {@see IllegalHoldLift::autoManaged()}: those lift only on their system clearing signal, never by hand (the
 *   `kyc` auto-lift is the separate within-module path `RecordKycVerified` drives — task 4.1; the `payment`
 *   auto-lift is a deferred Module-E seam);
 * - `admin` / `fraud` / `compliance` / `credit` lift freely through this path;
 * - a Hold that is not `active` (already `lifted`) is REJECTED with {@see IllegalHoldLift::notActive()} — a Hold
 *   lifts once.
 *
 * The status guard runs BEFORE the type guard, so an already-`lifted` Hold reports `notActive` regardless of type
 * (an already-resolved Hold is a more fundamental rejection than the type discipline).
 *
 * Like the other EVENTED Parties Actions (`RecordCustomerScreening`, `PlaceHold`) it injects the
 * {@see DomainEventRecorder} and resolves the acting principal from the {@see ActorContext} seam (`ActorRole::System`
 * until real principals wire in; an authenticated operator → `ActorRole::NewcoOps` — design L8). The actor is
 * resolved ONCE (after the guards, on the lift path only) and stamps BOTH the `lifted_actor_role`/`lifted_actor_id`
 * columns and the event envelope, so the Hold row and its event can never disagree on who lifted it; one
 * {@see CarbonImmutable::now()} likewise stamps the `lifted_at` column and is the single lift moment.
 *
 * From-state guarded and race-safe (design L3, mirroring `ActivateProducer`): inside ONE {@see DB::transaction} it
 * re-reads the row `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op under SQLite — the guards carry
 * correctness either way), runs the two guards, then writes `lifted` and records `CustomerHoldLifted` — the
 * recorder's open-transaction guard makes write + emit atomic, so a rejected lift records nothing and a rolled-back
 * lift leaves state and the event log unchanged. No causation/correlation is passed → the recorder makes this a
 * ROOT event (an operator lift is operator-initiated, never a cascade step in this slice). Lifting a Hold records
 * the Hold ONLY: it performs NO status transition on the scoped Customer/Account/Profile (the Hold→`suspended`
 * coupling is a deferred demand-side seam — proposal slice boundary).
 */
class LiftHold
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
    ) {}

    public function handle(int $holdId, ?string $reason = null): Hold
    {
        return DB::transaction(function () use ($holdId, $reason): Hold {
            // Transaction-locked re-read so two concurrent lift attempts serialize on PostgreSQL; the guards below
            // are the correctness guarantee (the lock is a no-op on SQLite).
            $hold = Hold::query()->whereKey($holdId)->lockForUpdate()->firstOrFail();

            // A Hold lifts once: an already-`lifted` Hold is rejected before the type discipline is consulted (the
            // status guard is the more fundamental rejection — an out-of-state lift is illegal whatever the type).
            if ($hold->status !== HoldStatus::Active) {
                throw IllegalHoldLift::notActive($hold->status);
            }

            // Per-type lift discipline (design L2): the operator path refuses an auto-managed type (`kyc`/`payment`)
            // — those lift only on their system clearing signal (the `kyc` auto-lift is RecordKycVerified's separate
            // within-module path; the `payment` auto-lift is a deferred Module-E seam).
            if ($hold->hold_type->autoLiftable()) {
                throw IllegalHoldLift::autoManaged($hold->hold_type);
            }

            // One actor resolution + one lift moment stamp both the Hold row and the event envelope, so the
            // lifted_actor_* / lifted_at columns and the CustomerHoldLifted envelope can never disagree (the seam
            // resolves lazily per call — design L8). Resolved on the lift path only (after the guards reject).
            $actorRole = $this->actor->role();
            $actorId = $this->actor->actorId();

            $hold->update([
                'status' => HoldStatus::Lifted,
                'lifted_actor_role' => $actorRole,
                'lifted_actor_id' => $actorId,
                'lifted_at' => CarbonImmutable::now(),
                'lift_reason' => $reason,
            ]);

            // Record CustomerHoldLifted in the SAME transaction (the recorder's open-transaction guard makes write +
            // emit atomic). The event class is the single source of truth for the name / entity type / PII-free
            // payload (which carries the now-updated `lift_reason`), so this Action stays thin and magic-string-free.
            $this->recorder->record(
                name: CustomerHoldLifted::NAME,
                module: Module::Parties->value,
                actorRole: $actorRole,
                actorId: $actorId,
                entityType: CustomerHoldLifted::ENTITY_TYPE,
                entityId: (string) $hold->id,
                payload: CustomerHoldLifted::payload($hold),
            );

            return $hold;
        });
    }
}
