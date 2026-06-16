<?php

use App\Modules\Catalog\Consumers\ProducerLifecycleProjector;
use App\Modules\Catalog\Enums\ProducerProjectionStatus;
use App\Modules\Catalog\Models\ProducerState;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Module;
use App\Platform\Events\ActorRole;
use App\Platform\Events\ConsumerRegistry;
use App\Platform\Events\DeliveryStatus;
use App\Platform\Events\DomainEvent;
use App\Platform\Events\DomainEventRecorder;
use App\Platform\Events\EventDelivery;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

/**
 * Pins the ProducerLifecycleProjector — the codebase's FIRST registered cross-module domain-event
 * consumer (catalog-lifecycle-approval, task 1.2; design D4; product-catalog — Requirement:
 * Producer-State Projection and Event Consumption). It consumes the Module K supply-side events
 * `ProducerActivated` / `ProducerRetired` into the Catalog-owned producer-state projection
 * ({@see ProducerState}) that the *Producer Activation Gate* (task 3.2) will read.
 *
 * Trait — DatabaseMigrations, NOT RefreshDatabase (design D11; the InlineDeliveryTest template): the
 * recorder's DB::afterCommit inline hook and the executor's commit fire only at transactionLevel 0,
 * which RefreshDatabase's wrapping transaction suppresses. Each test records its event inside a real
 * DB::transaction so the inline delivery runs on commit, exactly as in production.
 *
 * Boundary note: the test simulates Module K's emission with bare strings + a `{producer_id, status}`
 * payload (module `parties`, entity_type `Producer`) — it never imports a Parties event class, mirroring
 * the consumer's own payload-only coupling (invariant 10; the source `ModuleBoundariesTest` stays green).
 */
uses(DatabaseMigrations::class);

/**
 * Record a supply-side producer event through the real recorder, exactly as Module K's ActivateProducer /
 * RetireProducer does (module `parties`, entity_type `Producer`, payload `{producer_id, status}`). The
 * recorder requires an open transaction, so callers wrap this in DB::transaction(); on commit the inline
 * post-commit hook fans the event out to the registered ProducerLifecycleProjector. Prefixed to avoid
 * colliding with sibling test files' global Pest helpers (one shared namespace).
 */
function recordProducerLifecycleEvent(string $name, int $producerId, string $status): DomainEvent
{
    return app(DomainEventRecorder::class)->record(
        name: $name,
        module: Module::Parties->value,
        actorRole: ActorRole::System,
        actorId: null,
        entityType: 'Producer',
        entityId: (string) $producerId,
        payload: ['producer_id' => $producerId, 'status' => $status],
    );
}

it('registers the projector for both producer lifecycle events on the shared registry', function () {
    // CatalogServiceProvider::boot() registered the first real module consumer — without it the events
    // would fan out zero deliveries and the gate would block every Master forever (design D4 risk note).
    $registry = app(ConsumerRegistry::class);

    expect($registry->consumersFor(ProducerLifecycleProjector::PRODUCER_ACTIVATED))
        ->toContain(ProducerLifecycleProjector::class)
        ->and($registry->consumersFor(ProducerLifecycleProjector::PRODUCER_RETIRED))
        ->toContain(ProducerLifecycleProjector::class);
});

it('projects a producer active on a delivered ProducerActivated and marks the delivery done', function () {
    $event = DB::transaction(fn () => recordProducerLifecycleEvent('ProducerActivated', 7, 'active'));

    // The inline post-commit hook fired (the consumer is registered in boot()): the delivery is done…
    $delivery = EventDelivery::query()
        ->where('domain_event_id', $event->id)
        ->where('consumer', ProducerLifecycleProjector::class)
        ->sole();

    // …and the projection row was written for producer 7 with the applied event as its watermark.
    $state = ProducerState::query()->where('producer_id', 7)->sole();

    expect($delivery->status)->toBe(DeliveryStatus::Done)
        ->and($delivery->attempts)->toEqual(1)
        ->and($state->status)->toBe(ProducerProjectionStatus::Active)
        ->and($state->last_event_id)->toBe($event->id)
        // consuming the event writes ONLY the projection — no Product Master is created or touched.
        ->and(ProductMaster::query()->count())->toBe(0);
});

it('projects retired on a later ProducerRetired, advances the watermark, and keeps exactly one row', function () {
    DB::transaction(fn () => recordProducerLifecycleEvent('ProducerActivated', 7, 'active'));
    $retired = DB::transaction(fn () => recordProducerLifecycleEvent('ProducerRetired', 7, 'retired'));

    $state = ProducerState::query()->where('producer_id', 7)->sole();

    expect($state->status)->toBe(ProducerProjectionStatus::Retired)
        ->and($state->last_event_id)->toBe($retired->id)        // watermark advanced to the newer event
        ->and(ProducerState::query()->count())->toBe(1)         // upsert on producer_id — still one row
        ->and(ProductMaster::query()->count())->toBe(0);        // block-new, never cascade-retire a Master
});

it('is idempotent: re-handling a delivered event leaves exactly one row and never re-advances', function () {
    $event = DB::transaction(fn () => recordProducerLifecycleEvent('ProducerActivated', 7, 'active'));

    // Replay the SAME event straight through the handler (a double delivery). The watermark guard (equal
    // id is not a strict advance) makes it a no-op — no duplicate row, no spurious state change.
    app(ProducerLifecycleProjector::class)->handle($event);

    $state = ProducerState::query()->where('producer_id', 7)->sole();

    expect(ProducerState::query()->count())->toBe(1)
        ->and($state->status)->toBe(ProducerProjectionStatus::Active)
        ->and($state->last_event_id)->toBe($event->id);
});

it('is order-tolerant: a stale event below the watermark never regresses the projection', function () {
    $activated = DB::transaction(fn () => recordProducerLifecycleEvent('ProducerActivated', 7, 'active'));
    $retired = DB::transaction(fn () => recordProducerLifecycleEvent('ProducerRetired', 7, 'retired'));

    // The stale ProducerActivated (a LOWER id than the current watermark) arrives late — re-handle it
    // directly: the ledger's `done` row would never re-invoke it, so we drive the watermark guard itself.
    app(ProducerLifecycleProjector::class)->handle($activated);

    $state = ProducerState::query()->where('producer_id', 7)->sole();

    expect($state->status)->toBe(ProducerProjectionStatus::Retired)   // watermark held — no regression to active
        ->and($state->last_event_id)->toBe($retired->id);
});

it('seeds a fresh projection row when the first event seen for a producer is a retirement', function () {
    // A producer activated before this projection deployed: its first delivered event is the retirement.
    // The consumer seeds the row (no prior watermark) rather than dropping the event (design Migration Plan).
    $retired = DB::transaction(fn () => recordProducerLifecycleEvent('ProducerRetired', 9, 'retired'));

    $state = ProducerState::query()->where('producer_id', 9)->sole();

    expect($state->status)->toBe(ProducerProjectionStatus::Retired)
        ->and($state->last_event_id)->toBe($retired->id)
        ->and(ProductMaster::query()->count())->toBe(0);
});
