<?php

namespace App\Modules\Catalog\Consumers;

use App\Modules\Catalog\Enums\ProducerProjectionStatus;
use App\Modules\Catalog\Models\ProducerState;
use App\Platform\Events\Contracts\DomainEventConsumer;
use App\Platform\Events\DomainEvent;
use UnexpectedValueException;

/**
 * ProducerLifecycleProjector — the codebase's FIRST registered cross-module domain-event consumer
 * (catalog-lifecycle-approval, task 1.2; design D4; catalog-module-0-completeness-sweep, task 5.1,
 * design D7; product-catalog — Requirement: Producer-State Projection and Event Consumption). It maintains
 * the Catalog-owned producer-state projection ({@see ProducerState}), fed solely by the Module K
 * supply-side lifecycle events `ProducerCreated` / `ProducerActivated` / `ProducerRetired`.
 *
 * The projection is Catalog's SINGLE source of producer knowledge, read by two rules at different
 * granularities: the *Producer Activation Gate* asks "is producer X `active`?" (only `active` opens it),
 * and `CreateProductMaster` asks "does producer X exist?" (any row admits creation). `ProducerCreated` is
 * consumed precisely to answer the second question without standing up a SECOND knowledge source (a Parties
 * read contract) — design D7.
 *
 * BOUNDARY LAW (invariant 10): the only Catalog ↔ Parties coupling is the domain-event PAYLOAD. This
 * consumer imports NO `App\Modules\Parties\*` type — it keys off the bare event-name strings
 * ({@see PRODUCER_CREATED} / {@see PRODUCER_ACTIVATED} / {@see PRODUCER_RETIRED}, anchored here so the
 * provider registration and the handler's map share one source of truth) and reads ONLY
 * `$event->payload['producer_id']` — never the `name`/`region`/`country` that `ProducerCreated` also
 * carries (widening the read would make the projection's display columns event-shaped: a separate,
 * deliberate follow-up). Those names mirror Parties' `ProducerCreated::NAME` / `ProducerActivated::NAME` /
 * `ProducerRetired::NAME` verbatim (§ 15.4) — a documented event-contract coupling, never a code dependency.
 *
 * IDEMPOTENT + ORDER-TOLERANT (the substrate's at-least-once, possibly out-of-order delivery, design D4):
 * the projection carries a per-producer `last_event_id` WATERMARK, and the handler applies an event only
 * when its `id` STRICTLY advances that watermark (latest-wins). A replay (a double delivery, equal id)
 * and a stale event arriving after a newer one (a lower id) are therefore both no-ops, so the projection
 * never regresses and converges on the latest applied producer state. That watermark is exactly what lets
 * the third event join with NO new mechanism: a `ProducerCreated` REDELIVERED after the producer's
 * `ProducerActivated` was applied carries a LOWER id, so it can never downgrade an `active` row back to
 * `registered`.
 *
 * DB-WORK-ONLY (the inline-consumer contract, design D4/D5): the handler performs database work only and
 * opens NO transaction of its own — its projection write and the delivery-status flip share the
 * `InlineDeliveryExecutor`'s single transaction (exactly-once for DB effects). Consuming `ProducerRetired`
 * writes ONLY the projection — it NEVER transitions a `ProductMaster` (block-new, never cascade-retire);
 * consuming `ProducerActivated` NEVER auto-activates a Master (no auto-replay — the Master's
 * `reviewed → active` stays operator-initiated); consuming `ProducerCreated` ONLY inserts the `registered`
 * row (it creates no Catalog entity, and it does NOT open the activation gate).
 *
 * Registered in `CatalogServiceProvider::boot()` (inline mode — the launch substrate; no queue ADR gate).
 */
class ProducerLifecycleProjector implements DomainEventConsumer
{
    /** Verbatim Module K § 15.4 event name — projects the producer `registered` (KNOWN to Catalog; Master creation admitted). */
    public const PRODUCER_CREATED = 'ProducerCreated';

    /** Verbatim Module K § 15.4 event name — projects the producer `active` (Master activation ENABLED). */
    public const PRODUCER_ACTIVATED = 'ProducerActivated';

    /** Verbatim Module K § 15.4 event name — projects the producer `retired` (new Master activation BLOCKED). */
    public const PRODUCER_RETIRED = 'ProducerRetired';

    public function handle(DomainEvent $event): void
    {
        $status = match ($event->name) {
            self::PRODUCER_CREATED => ProducerProjectionStatus::Registered,
            self::PRODUCER_ACTIVATED => ProducerProjectionStatus::Active,
            self::PRODUCER_RETIRED => ProducerProjectionStatus::Retired,
            default => null,
        };

        // Defensive totality: the registry only routes the three names above to this consumer, so a
        // non-matching event is unreachable in production — but mapping anything else to a no-op keeps the
        // handler side-effect-free rather than projecting a bogus state if it were ever mis-registered.
        if ($status === null) {
            return;
        }

        // The supply-side event contract carries the producer BY ID as an integer (the jsonb store
        // round-trips it as one); the payload is typed `mixed`, so narrow explicitly rather than
        // blind-casting. A non-integer is a malformed/forged envelope — fail loudly so the delivery
        // retries and dead-letters with a clear error, never silently coercing a bogus id into the gate's
        // projection.
        $producerId = $event->payload['producer_id'] ?? null;

        if (! is_int($producerId)) {
            throw new UnexpectedValueException(
                "ProducerLifecycleProjector: event {$event->id} ({$event->name}) carries a non-integer producer_id payload."
            );
        }

        // Lock the existing projection row for the duration of the executor's transaction so two
        // concurrent deliveries for the same producer (inline vs. sweep) serialize on PostgreSQL — a no-op
        // on single-writer SQLite, where the watermark assert below carries correctness (design D2/D4).
        $state = ProducerState::query()
            ->where('producer_id', $producerId)
            ->lockForUpdate()
            ->first();

        if ($state === null) {
            // First event seen for this producer — seed the projection (regardless of which event type it
            // is: normally `ProducerCreated`, but a producer created/activated before this projection
            // deployed re-converges on whichever of its events lands here first). A
            // concurrent insert race is caught by the unique `producer_id` index — the loser's delivery
            // rolls back and the sweep re-applies it, re-reading this row and falling to the watermark
            // branch below, so convergence is preserved.
            ProducerState::create([
                'producer_id' => $producerId,
                'status' => $status,
                'last_event_id' => $event->id,
            ]);

            return;
        }

        // Watermark — latest-wins: apply only when this event STRICTLY advances the last applied id. A
        // replay (equal id) or a stale event (lower id) is a no-op, so the watermark never regresses.
        if ($event->id <= $state->last_event_id) {
            return;
        }

        $state->update([
            'status' => $status,
            'last_event_id' => $event->id,
        ]);
    }
}
