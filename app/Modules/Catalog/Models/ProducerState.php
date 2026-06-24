<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\ProducerProjectionStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * ProducerState — the Catalog-owned producer-state PROJECTION row (catalog-lifecycle-approval, design
 * D3/D4; product-catalog — Requirement: Producer-State Projection and Event Consumption). One row per
 * producer, the codebase's first cross-module read model.
 *
 * The gate needs "is producer X `active`?" but invariant 10 forbids querying Module K. This model is the
 * Catalog-LOCAL read surface fed by the `ProducerLifecycleProjector` consumer
 * (task 1.2, the sole writer) as it consumes `ProducerActivated`/`ProducerRetired`; the *Producer Activation Gate*
 * reads `status` off it. The consumer upserts on `producer_id`, applying an event only when its `id`
 * advances `last_event_id` (the watermark — latest-wins, design D4).
 *
 * The `producer_id` is a PLAIN id into Module K — never an Eloquent relation (the boundary law, invariant
 * 10; mirrors {@see ProductMaster::$producer_id}). `last_event_id` is the applied `domain_events.id`, a
 * plain id (no relation into the platform event store). Persistence-only by design: the projector is the
 * only writer, so `$guarded = []` carries no mass-assignment-from-request risk (it is never bound to a
 * request — it is written by the inline consumer inside the delivery executor's transaction).
 *
 * @property int $id
 * @property int $producer_id
 * @property ?string $producer_name
 * @property ?string $region
 * @property ?string $country
 * @property ProducerProjectionStatus $status
 * @property int $last_event_id
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class ProducerState extends Model
{
    protected $table = 'catalog_producer_states';

    /**
     * The projector consumer is the only writer; it builds the row internally from the consumed event, so
     * there is no mass-assignment from request input to guard (mirrors the spine models' persistence-only
     * stance).
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProducerProjectionStatus::class,
            'producer_id' => 'integer',
            'last_event_id' => 'integer',
        ];
    }
}
