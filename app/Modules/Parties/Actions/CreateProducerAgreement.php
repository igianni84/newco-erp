<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\ProducerAgreementStatus;
use App\Modules\Parties\Events\ProducerAgreementCreated;
use App\Modules\Parties\Exceptions\MissingAgreementProducer;
use App\Modules\Parties\Models\Producer;
use App\Modules\Parties\Models\ProducerAgreement;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Creates a ProducerAgreement in `draft` for an existing Producer (optionally narrowed to one Club) and records
 * its {@see ProducerAgreementCreated} event atomically (parties-core, design D3/D4/D7; party-registry —
 * Requirement: ProducerAgreement, Spine Creation Events).
 *
 * One guard makes the launch invariant enforced, not advised:
 *   - REQUIRED PRODUCER (§ 4.6): a ProducerAgreement references EXACTLY ONE Producer. Inside the transaction a
 *     presence check rejects a `producer_id` that matches no Producer with a localized
 *     {@see MissingAgreementProducer} reason. The within-module FK on `parties_producer_agreements.producer_id`
 *     is the true structural guard; the pre-check surfaces a clean operator reason ahead of the raw integrity
 *     error.
 *
 * The Club narrowing is OPTIONAL — a null `clubId` is a Producer-wide agreement; a value scopes it to that one
 * Club (the FK is the structural backstop for a non-existent Club). The "at most one ACTIVE agreement per
 * Producer scope" rule (BR-K-Agreement-1) is an ACTIVATION-time invariant and is therefore NOT enforced here:
 * draft agreements are created freely (design D2, the spec's creation-only scope). Then, in ONE
 * {@see DB::transaction}: insert the agreement (born `draft`) and record the PII-free event via the platform
 * {@see DomainEventRecorder} (the actor resolved from the {@see ActorContext} seam — System until real
 * principals wire in). The recorder's own transaction guard makes write + emit atomic. The model stays
 * persistence-only; this action is the seam the deferred lifecycle change extends.
 */
class CreateProducerAgreement
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
    ) {}

    public function handle(
        int $producerId,
        ?int $clubId = null,
        ?CarbonInterface $termStart = null,
        ?CarbonInterface $termEnd = null,
        ?string $settlementCadence = null,
    ): ProducerAgreement {
        return DB::transaction(function () use (
            $producerId,
            $clubId,
            $termStart,
            $termEnd,
            $settlementCadence,
        ): ProducerAgreement {
            // § 4.6: a ProducerAgreement requires an EXISTING Producer. Reject a missing/non-existent reference
            // with a clean localized reason (the FK is the structural backstop). The single-active-per-scope
            // rule is an activation-time invariant — NOT checked here; drafts create freely.
            if (! Producer::query()->whereKey($producerId)->exists()) {
                throw MissingAgreementProducer::forId($producerId);
            }

            $agreement = ProducerAgreement::create([
                'producer_id' => $producerId,
                'club_id' => $clubId,
                'status' => ProducerAgreementStatus::Draft,
                'term_start' => $termStart,
                'term_end' => $termEnd,
                'settlement_cadence' => $settlementCadence,
            ]);

            $this->recorder->record(
                name: ProducerAgreementCreated::NAME,
                module: Module::Parties->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: ProducerAgreementCreated::ENTITY_TYPE,
                entityId: (string) $agreement->id,
                payload: ProducerAgreementCreated::payload($agreement),
            );

            return $agreement;
        });
    }
}
