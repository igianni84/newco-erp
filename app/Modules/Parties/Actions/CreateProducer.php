<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\ProducerStatus;
use App\Modules\Parties\Events\ProducerCreated;
use App\Modules\Parties\Models\Producer;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use App\Platform\I18n\TranslatableText;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Producer in `draft` and records its {@see ProducerCreated} event atomically (parties-core,
 * design D2/D7/D10; party-registry — Requirement: Producer Registry, Spine Creation Events).
 *
 * The Producer is the root of the supply-side registry, so creation is unconditional — there is no
 * uniqueness rule in this slice (the spec names none; BR-K-Producer-1/3 are "standalone" + "no auto
 * cross-create", not a dedup), and NO Supplier is created as a side effect (design D10 — the Supplier↔
 * Producer link is Module D's `SupplierProducerLink`, never modelled here).
 *
 * In ONE {@see DB::transaction}: insert the Producer (born `draft`) and record the PII-free event via the
 * platform {@see DomainEventRecorder} (the actor resolved from the {@see ActorContext} seam — System until
 * real principals wire in). The recorder's own transaction guard makes write + emit atomic. The model stays
 * persistence-only; this action is the seam the deferred lifecycle change extends.
 */
class CreateProducer
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
    ) {}

    public function handle(
        string $name,
        string $region,
        string $country,
        ?string $appellation = null,
        ?TranslatableText $description = null,
        ?string $website = null,
    ): Producer {
        return DB::transaction(function () use ($name, $region, $country, $appellation, $description, $website): Producer {
            $producer = Producer::create([
                'name' => $name,
                'region' => $region,
                'country' => $country,
                'appellation' => $appellation,
                'description' => $description,
                'website' => $website,
                'status' => ProducerStatus::Draft,
            ]);

            $this->recorder->record(
                name: ProducerCreated::NAME,
                module: Module::Parties->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: ProducerCreated::ENTITY_TYPE,
                entityId: (string) $producer->id,
                payload: ProducerCreated::payload($producer),
            );

            return $producer;
        });
    }
}
