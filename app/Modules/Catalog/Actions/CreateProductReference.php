<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\ProductReferenceCreated;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Module;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Product Reference — the atomic product key (a Product Variant + a Format) — and records its
 * {@see ProductReferenceCreated} event atomically (catalog-product-spine, design D5/D8; product-catalog —
 * Requirement: Product Reference, Spine Creation Events).
 *
 * Like the Variant's creation seam and unlike the Master's, this action has NO application-layer dedup and NO
 * type guard. The PR's two-dimension identity (BR-Identity-3 — the `(variant, format)` pair is unique) is
 * enforced STRUCTURALLY by the database unique index, not by an in-action check: a duplicate pair surfaces as
 * a {@see UniqueConstraintViolationException} from the insert (the Master's identity key
 * spans two tables and so must be checked in the action; the PR's is a single-table tuple the DB can own). The
 * single-Variant / single-Format invariant is enforced by the two scalar foreign keys.
 *
 * In ONE {@see DB::transaction}: insert the row (`draft`) and record the PII-free event via the platform
 * {@see DomainEventRecorder} (the actor resolved from the {@see ActorContext} seam — System until the auth ADR
 * wires real principals in). The recorder's own transaction guard makes write + emit atomic, and — since the
 * unique violation aborts the insert before the emit — a rejected duplicate records NO event (the savepoint
 * the transaction opens isolates the failure on PostgreSQL). The model stays persistence-only; this action is
 * the seam the deferred lifecycle/approval change extends.
 */
class CreateProductReference
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
    ) {}

    public function handle(int $productVariantId, int $formatId): ProductReference
    {
        return DB::transaction(function () use ($productVariantId, $formatId): ProductReference {
            $reference = ProductReference::create([
                'product_variant_id' => $productVariantId,
                'format_id' => $formatId,
                'lifecycle_state' => LifecycleState::Draft,
            ]);

            $this->recorder->record(
                name: ProductReferenceCreated::NAME,
                module: Module::Catalog->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: ProductReferenceCreated::ENTITY_TYPE,
                entityId: (string) $reference->id,
                payload: ProductReferenceCreated::payload($reference),
            );

            return $reference;
        });
    }
}
