<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\CaseConfigurationCreated;
use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\Module;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Case Configuration and records its {@see CaseConfigurationCreated} event atomically
 * (catalog-product-spine, design D8; product-catalog — Requirement: Case Configuration / Spine Creation
 * Events).
 *
 * Follows the spine creation pattern set by the CreateFormat action: inside ONE {@see DB::transaction},
 * insert the row then call the platform {@see DomainEventRecorder} with the verbatim event name,
 * `Module::Catalog->value`, the actor resolved from the {@see ActorContext} seam (System by default until
 * the auth ADR wires real principals in), the entity type and stringified id, and a PII-free payload. The
 * recorder's own transaction guard makes the atomicity enforced, not advised — emitting and the write
 * commit or roll back together (no dual-write). The model stays persistence-only; this action is the seam
 * the deferred lifecycle/approval change extends (it adds transitions here, never on the model).
 *
 * No breakability parameter exists — breakability is decided downstream (Module A/S), never captured on
 * the Case Configuration (BR-RefData-2).
 */
class CreateCaseConfiguration
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
    ) {}

    public function handle(string $name, int $unitsPerCase, string $packagingType): CaseConfiguration
    {
        return DB::transaction(function () use ($name, $unitsPerCase, $packagingType): CaseConfiguration {
            $caseConfiguration = CaseConfiguration::create([
                'name' => $name,
                'units_per_case' => $unitsPerCase,
                'packaging_type' => $packagingType,
                'lifecycle_state' => LifecycleState::Draft,
            ]);

            $this->recorder->record(
                name: CaseConfigurationCreated::NAME,
                module: Module::Catalog->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: CaseConfigurationCreated::ENTITY_TYPE,
                entityId: (string) $caseConfiguration->id,
                payload: CaseConfigurationCreated::payload($caseConfiguration),
            );

            return $caseConfiguration;
        });
    }
}
