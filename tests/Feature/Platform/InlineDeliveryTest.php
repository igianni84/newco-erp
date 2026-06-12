<?php

use App\Platform\Events\ActorRole;
use App\Platform\Events\ConsumerRegistry;
use App\Platform\Events\DeliveryStatus;
use App\Platform\Events\DomainEvent;
use App\Platform\Events\DomainEventRecorder;
use App\Platform\Events\EventDelivery;
use App\Platform\Events\InlineDeliveryExecutor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Platform\FailingConsumer;
use Tests\Support\Platform\RecordingConsumer;

/**
 * Pins the InlineDeliveryExecutor and the recorder's post-commit wiring (foundations-domain-events-
 * audit, task 4.1; design D5). Covers the delta-spec Inline Delivery scenarios (inline happy path,
 * done-is-terminal, causal order within a transaction), the Per-Consumer Delivery Ledger R4 fan-out
 * isolation, and exactly-once-for-DB-effects on both the success and failure sides.
 *
 * Trait choice — DatabaseMigrations, NOT RefreshDatabase (same reasoning as the recorder tests, and
 * doubly load-bearing here): the executor's atomicity (handler write + status flip in one
 * transaction; rollback on failure) and the recorder's DB::afterCommit hook both need REAL commits
 * and rollbacks at transactionLevel 0, which only happen outside RefreshDatabase's wrapper
 * transaction. Verified in vendor: under the base DatabaseTransactionsManager an after-commit
 * callback fires when the level returns to 0 (a test's explicit DB::transaction() commit), exactly
 * as in production. (Under RefreshDatabase the testing manager would still fire the hook — at level
 * 1 — but the commit/rollback would not be observable, defeating the atomicity assertions; so the
 * D5 "afterCommit untestable" fallback is not needed on this installed framework.)
 */
uses(DatabaseMigrations::class);

// The consumer sink is PHP-process state (the trait resets the DB, not statics) — clear it before
// each test so invocation-count/order assertions never see a sibling test's deliveries.
beforeEach(fn () => RecordingConsumer::$handled = []);

/**
 * Records one synthetic demo event through the real recorder (which, post task 4.1, registers the
 * afterCommit delivery hook). `InlineDeliveryProbe` and friends are clearly-synthetic names —
 * verbatim spec event names are reserved for real module events (F2+). Prefixed to avoid colliding
 * with sibling test files' global Pest helpers (one shared namespace).
 */
function recordDeliveryEvent(string $name = 'InlineDeliveryProbe', string $entityId = '1', ?int $causationId = null): DomainEvent
{
    return app(DomainEventRecorder::class)->record(
        name: $name,
        module: 'platform',
        actorRole: ActorRole::System,
        actorId: null,
        entityType: 'delivery-demo',
        entityId: $entityId,
        payload: ['demo' => true],
        causationId: $causationId,
    );
}

/**
 * Seeds a committed domain event + one pending delivery WITHOUT the recorder, so no afterCommit hook
 * is registered — the exact state a crash between commit and inline execution leaves behind. A
 * directly-invoked executor must still deliver it (the path task 4.2's sweep reuses).
 */
function seedPendingDelivery(string $consumerClass): EventDelivery
{
    return DB::transaction(function () use ($consumerClass): EventDelivery {
        $event = DomainEvent::create([
            'event_id' => (string) Str::uuid7(),
            'name' => 'InlineSeededProbe',
            'schema_version' => 1,
            'module' => 'platform',
            'occurred_at' => CarbonImmutable::now('UTC'),
            'actor_role' => ActorRole::System,
            'actor_id' => null,
            'entity_type' => 'delivery-demo',
            'entity_id' => '1',
            'correlation_id' => (string) Str::uuid7(),
            'causation_id' => null,
            'payload' => [],
        ]);

        return EventDelivery::create([
            'domain_event_id' => $event->id,
            'consumer' => $consumerClass,
            'status' => DeliveryStatus::Pending,
            'attempts' => 0,
        ]);
    });
}

it('delivers inline after commit: the consumer runs and its delivery row reads done with attempts 1', function () {
    app(ConsumerRegistry::class)->register('InlineDeliveryProbe', RecordingConsumer::class);

    $event = DB::transaction(fn () => recordDeliveryEvent(name: 'InlineDeliveryProbe'));

    // The afterCommit hook fired on the real commit, so by now delivery has executed.
    $delivery = EventDelivery::query()->where('domain_event_id', $event->id)->sole();

    expect($delivery->status)->toBe(DeliveryStatus::Done)
        ->and($delivery->attempts)->toEqual(1)
        ->and(RecordingConsumer::$handled)->toBe([$event->id])                          // handler ran once
        // exactly-once for DB effects (success half): the handler's cache write and the `done` flip
        // committed together.
        ->and(DB::table('cache')->where('key', 'consumer:recording:'.$event->id)->count())->toBe(1);
});

it('isolates a failing consumer from its sibling (R4) and rolls back the failed handler\'s DB effect', function () {
    $registry = app(ConsumerRegistry::class);
    $registry->register('InlineFanout', FailingConsumer::class);     // registered first → delivered first
    $registry->register('InlineFanout', RecordingConsumer::class);

    $event = DB::transaction(fn () => recordDeliveryEvent(name: 'InlineFanout'));

    $failing = EventDelivery::query()->where('domain_event_id', $event->id)->where('consumer', FailingConsumer::class)->sole();
    $sibling = EventDelivery::query()->where('domain_event_id', $event->id)->where('consumer', RecordingConsumer::class)->sole();

    expect($failing->status)->toBe(DeliveryStatus::Pending)               // retryable (1 < max), not dead-lettered
        ->and($failing->attempts)->toEqual(1)
        ->and($failing->last_error)->toContain(FailingConsumer::FAILURE_MESSAGE)
        ->and($failing->available_at)->toBeInstanceOf(CarbonImmutable::class)         // backoff window set
        ->and($sibling->status)->toBe(DeliveryStatus::Done)                          // sibling unaffected
        ->and($sibling->attempts)->toEqual(1)
        ->and(RecordingConsumer::$handled)->toBe([$event->id])
        // exactly-once for DB effects (failure half): the failing handler's cache write rolled back
        // with its delivery transaction — no partial effect survives.
        ->and(DB::table('cache')->where('key', 'consumer:failing:'.$event->id)->count())->toBe(0)
        // the emitter's committed event is untouched by a consumer failure.
        ->and(DomainEvent::query()->whereKey($event->id)->exists())->toBeTrue();
});

it('executes a pending delivery when invoked directly, as after a crash where inline never ran', function () {
    $delivery = seedPendingDelivery(RecordingConsumer::class);

    expect($delivery->status)->toBe(DeliveryStatus::Pending);   // a directly-seeded row had no hook

    app(InlineDeliveryExecutor::class)->deliver([$delivery->domain_event_id]);

    expect(EventDelivery::query()->whereKey($delivery->id)->sole()->status)->toBe(DeliveryStatus::Done)
        ->and(RecordingConsumer::$handled)->toBe([$delivery->domain_event_id]);
});

it('never re-invokes a consumer whose delivery is already done (done is terminal)', function () {
    app(ConsumerRegistry::class)->register('InlineTerminal', RecordingConsumer::class);

    $event = DB::transaction(fn () => recordDeliveryEvent(name: 'InlineTerminal'));
    expect(EventDelivery::query()->where('domain_event_id', $event->id)->sole()->status)->toBe(DeliveryStatus::Done)
        ->and(RecordingConsumer::$handled)->toBe([$event->id]);

    // Re-running the executor over the now-`done` row must not call the handler again.
    app(InlineDeliveryExecutor::class)->deliver([$event->id]);

    expect(RecordingConsumer::$handled)->toBe([$event->id])     // still exactly one invocation
        ->and(EventDelivery::query()->where('domain_event_id', $event->id)->sole()->attempts)->toEqual(1);
});

it('delivers events recorded in one transaction to a consumer in their id (causal) order', function () {
    app(ConsumerRegistry::class)->register('InlineOrder', RecordingConsumer::class);

    $ids = DB::transaction(fn () => [
        recordDeliveryEvent(name: 'InlineOrder', entityId: '1')->id,
        recordDeliveryEvent(name: 'InlineOrder', entityId: '2')->id,
        recordDeliveryEvent(name: 'InlineOrder', entityId: '3')->id,
    ]);

    // Each record() registered its own afterCommit callback on the SAME transaction record; they fire
    // FIFO in record order, so the consumer sees the three events in ascending id (= causal) order.
    expect(RecordingConsumer::$handled)->toBe($ids)
        ->and($ids[0])->toBeLessThan($ids[1])
        ->and($ids[1])->toBeLessThan($ids[2]);
});

it('does not deliver when the recording transaction rolls back (the hook is discarded)', function () {
    app(ConsumerRegistry::class)->register('InlineRollback', RecordingConsumer::class);

    expect(fn () => DB::transaction(function () {
        recordDeliveryEvent(name: 'InlineRollback');
        throw new RuntimeException('force rollback');
    }))->toThrow(RuntimeException::class);

    // After-commit callbacks never run for a rolled-back transaction (Laravel discards them), so
    // nothing committed and nothing delivered.
    expect(RecordingConsumer::$handled)->toBe([])
        ->and(EventDelivery::count())->toBe(0)
        ->and(DomainEvent::count())->toBe(0);
});
