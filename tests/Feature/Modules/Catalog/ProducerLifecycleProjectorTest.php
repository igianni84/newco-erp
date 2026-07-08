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
 * consumer (catalog-lifecycle-approval, task 1.2; design D4; catalog-module-0-completeness-sweep, task 5.1,
 * design D7; product-catalog — Requirement: Producer-State Projection and Event Consumption). It consumes
 * the Module K supply-side events `ProducerCreated` / `ProducerActivated` / `ProducerRetired` into the
 * Catalog-owned producer-state projection ({@see ProducerState}) that the *Producer Activation Gate*
 * (task 3.2) reads and that `CreateProductMaster` consults for producer EXISTENCE (task 5.2).
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

it('registers the projector for all three producer lifecycle events on the shared registry', function () {
    // CatalogServiceProvider::boot() registered the first real module consumer — without it the events
    // would fan out zero deliveries, the gate would block every Master forever (design D4 risk note) and
    // the creation-existence guard would reject every producer (sweep design D7).
    $registry = app(ConsumerRegistry::class);

    expect($registry->consumersFor(ProducerLifecycleProjector::PRODUCER_CREATED))
        ->toContain(ProducerLifecycleProjector::class)
        ->and($registry->consumersFor(ProducerLifecycleProjector::PRODUCER_ACTIVATED))
        ->toContain(ProducerLifecycleProjector::class)
        ->and($registry->consumersFor(ProducerLifecycleProjector::PRODUCER_RETIRED))
        ->toContain(ProducerLifecycleProjector::class);
});

it('projects a producer registered on a delivered ProducerCreated and marks the delivery done', function () {
    // The delta's "ProducerCreated makes a producer known for Master creation" scenario, projection half:
    // the producer becomes KNOWN to Catalog (existence, task 5.2) WITHOUT the gate opening (asserted below).
    $event = DB::transaction(fn () => recordProducerLifecycleEvent('ProducerCreated', 7, 'draft'));

    $delivery = EventDelivery::query()
        ->where('domain_event_id', $event->id)
        ->where('consumer', ProducerLifecycleProjector::class)
        ->sole();

    $state = ProducerState::query()->where('producer_id', 7)->sole();

    expect($delivery->status)->toBe(DeliveryStatus::Done)
        ->and($delivery->attempts)->toEqual(1)
        ->and($state->status)->toBe(ProducerProjectionStatus::Registered)
        ->and($state->last_event_id)->toBe($event->id)
        // `registered` is EXISTENCE, never activeness: the gate reads `active` and nothing else.
        ->and($state->status)->not->toBe(ProducerProjectionStatus::Active)
        // consuming the event writes ONLY the projection — no Product Master is created or touched.
        ->and(ProductMaster::query()->count())->toBe(0);
});

it('advances a registered producer to active on the ProducerActivated that follows', function () {
    // The real Module K lineage: created → activated. One row, latest-wins, no second knowledge source.
    DB::transaction(fn () => recordProducerLifecycleEvent('ProducerCreated', 7, 'draft'));
    $activated = DB::transaction(fn () => recordProducerLifecycleEvent('ProducerActivated', 7, 'active'));

    $state = ProducerState::query()->where('producer_id', 7)->sole();

    expect($state->status)->toBe(ProducerProjectionStatus::Active)
        ->and($state->last_event_id)->toBe($activated->id)   // watermark advanced past the creation event
        ->and(ProducerState::query()->count())->toBe(1)      // upsert on producer_id — still one row
        ->and(ProductMaster::query()->count())->toBe(0);     // no auto-activation of any Master
});

it('never downgrades an active producer when a stale ProducerCreated is redelivered after activation', function () {
    // The delta's out-of-order clause, verbatim: "a stale `ProducerCreated` (re)delivered after a
    // `ProducerActivated` SHALL NOT downgrade an `active` row to `registered`". The creation event carries a
    // LOWER id than the watermark, so the existing latest-wins guard — not a new mechanism — rejects it.
    $created = DB::transaction(fn () => recordProducerLifecycleEvent('ProducerCreated', 7, 'draft'));
    $activated = DB::transaction(fn () => recordProducerLifecycleEvent('ProducerActivated', 7, 'active'));

    // Re-handle the creation event directly: its ledger row is `done`, so only the watermark guard can be
    // driven here (the same idiom the ProducerActivated stale test below uses).
    app(ProducerLifecycleProjector::class)->handle($created);

    $state = ProducerState::query()->where('producer_id', 7)->sole();

    expect($state->status)->toBe(ProducerProjectionStatus::Active)   // no downgrade to `registered`
        ->and($state->last_event_id)->toBe($activated->id)           // watermark unregressed
        ->and($created->id)->toBeLessThan($activated->id);           // non-vacuity: the stale event IS older
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
