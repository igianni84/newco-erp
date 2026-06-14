<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\FormatCreated;
use App\Modules\Catalog\Models\Format;
use App\Modules\Module;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Format and records its {@see FormatCreated} event atomically (catalog-product-spine,
 * design D8; product-catalog — Requirement: Format / Spine Creation Events).
 *
 * This is the FIRST of the seven `Create*` actions and sets the spine creation pattern every sibling
 * repeats: inside ONE {@see DB::transaction}, insert the row(s) then call the platform
 * {@see DomainEventRecorder} with the verbatim event name, `Module::Catalog->value`, the actor resolved
 * from the {@see ActorContext} seam (System by default until the auth ADR wires real principals in),
 * the entity type and stringified id, and a PII-free payload. The recorder's own transaction guard
 * makes the atomicity enforced, not advised — emitting and the write commit or roll back together (no
 * dual-write). The model stays persistence-only; this action is the seam the deferred lifecycle/approval
 * change extends (it adds transitions here, never on the model).
 */
class CreateFormat
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
    ) {}

    public function handle(string $name, string $sizeLabel, int $volumeMl): Format
    {
        return DB::transaction(function () use ($name, $sizeLabel, $volumeMl): Format {
            $format = Format::create([
                'name' => $name,
                'size_label' => $sizeLabel,
                'volume_ml' => $volumeMl,
                'lifecycle_state' => LifecycleState::Draft,
            ]);

            $this->recorder->record(
                name: FormatCreated::NAME,
                module: Module::Catalog->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: FormatCreated::ENTITY_TYPE,
                entityId: (string) $format->id,
                payload: FormatCreated::payload($format),
            );

            return $format;
        });
    }
}
