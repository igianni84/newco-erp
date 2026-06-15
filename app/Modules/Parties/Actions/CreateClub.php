<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\ClubRegistrationFlowType;
use App\Modules\Parties\Enums\ClubStatus;
use App\Modules\Parties\Events\ClubCreated;
use App\Modules\Parties\Exceptions\MissingClubProducer;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Producer;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use App\Platform\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Club in `active` for an existing operating Producer and records its {@see ClubCreated} event
 * atomically (parties-core, design D3/D4/D7/D9; party-registry — Requirement: Club, Spine Creation Events).
 *
 * One guard makes the launch invariant enforced, not advised:
 *   - REQUIRED PRODUCER (BR-K-Club-1): a Club is associated with EXACTLY ONE operating Producer. Inside the
 *     transaction a presence check rejects a `producer_id` that matches no Producer with a localized
 *     {@see MissingClubProducer} reason. The within-module FK on `parties_clubs.producer_id` is the true
 *     structural guard; the pre-check surfaces a clean operator reason ahead of the raw integrity error.
 *
 * The operating-Producer link is set once here and is immutable thereafter (BR-K-Club-2) — this action
 * exposes no reassignment operation. The per-Club `fee` is a {@see Money} (integer minor units + ISO 4217,
 * never a float — invariant 6); `registration_flow_type` is the per-Club classifier set explicitly at
 * creation (no birth default). Then, in ONE {@see DB::transaction}: insert the Club (born `active`) and
 * record the PII-free event via the platform {@see DomainEventRecorder} (the actor resolved from the
 * {@see ActorContext} seam — System until real principals wire in). The recorder's own transaction guard
 * makes write + emit atomic. The model stays persistence-only; this action is the seam the deferred lifecycle
 * change extends.
 */
class CreateClub
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
    ) {}

    public function handle(
        string $displayName,
        int $producerId,
        ClubRegistrationFlowType $registrationFlowType,
        ?Money $fee = null,
        bool $generatesCredit = true,
        bool $inviteOnly = false,
    ): Club {
        return DB::transaction(function () use (
            $displayName,
            $producerId,
            $registrationFlowType,
            $fee,
            $generatesCredit,
            $inviteOnly,
        ): Club {
            // BR-K-Club-1: a Club requires an EXISTING operating Producer. Reject a missing/non-existent
            // reference with a clean localized reason (the FK is the structural backstop).
            if (! Producer::query()->whereKey($producerId)->exists()) {
                throw MissingClubProducer::forId($producerId);
            }

            $club = Club::create([
                'display_name' => $displayName,
                'producer_id' => $producerId,
                'status' => ClubStatus::Active,
                'fee' => $fee,
                'registration_flow_type' => $registrationFlowType,
                'generates_credit' => $generatesCredit,
                'invite_only' => $inviteOnly,
            ]);

            $this->recorder->record(
                name: ClubCreated::NAME,
                module: Module::Parties->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: ClubCreated::ENTITY_TYPE,
                entityId: (string) $club->id,
                payload: ClubCreated::payload($club),
            );

            return $club;
        });
    }
}
