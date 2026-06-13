<?php

use App\Modules\Module;
use App\Platform\Events\ActorRole;
use App\Platform\Events\ConsumerRegistry;
use App\Platform\Events\DeliveryStatus;
use App\Platform\Events\DomainEvent;
use App\Platform\Events\DomainEventRecorder;
use App\Platform\Events\EventDelivery;
use App\Platform\Events\NotInTransactionException;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Platform\InertConsumerA;
use Tests\Support\Platform\InertConsumerB;

/**
 * Pins the DomainEventRecorder (foundations-domain-events-audit, task 3.4; design D3) — the single
 * write path for `domain_events` and its in-transaction delivery fan-out. Covers the delta-spec
 * scenarios under Transactional Event Recording (atomic commit / rollback / outside-transaction
 * refused) and Domain Event Envelope (envelope read-back, UUIDv7 event_id, FX decimal-string
 * survival, monotonic intra-transaction ids, provenance query, root-event correlation default), plus
 * the recorder's slice of Per-Consumer Delivery Ledger (one pending row per registered consumer).
 * Delivery EXECUTION is task 4.1's concern and is not exercised here.
 *
 * Trait choice — DatabaseMigrations, NOT RefreshDatabase (same reasoning as AuditRecorderTest, and
 * here it is doubly load-bearing): the recorder's guard checks `DB::transactionLevel() === 0`, which
 * RefreshDatabase's wrapper transaction (level ≥ 1) makes untestable; and the atomic-commit /
 * rollback scenarios need a REAL commit and a REAL rollback to be observable, which only happens
 * outside that wrapper. migrate:fresh is DDL, which the append-only immutability triggers don't guard
 * (unlike DatabaseTruncation's per-table DELETE), and it leaves each test at transactionLevel 0 — so
 * the write tests wrap the recorder in explicit DB::transaction() blocks and the outside-transaction
 * case trips the guard for real.
 */
uses(DatabaseMigrations::class);

// One test mocks the clock; guarantee no test-now leaks into a sibling even if an assertion fails
// mid-test (Carbon and CarbonImmutable share the global mock).
afterEach(fn () => CarbonImmutable::setTestNow());

/**
 * Records the canonical demo event, overriding only the field a test exercises (named arguments).
 * A fully-typed helper — not an `array<string,mixed>` spread — so each argument keeps its static type
 * at the call site. Does NOT open a transaction: callers wrap it in DB::transaction() (write tests)
 * or call it bare (the guard test). Prefixed to avoid colliding with sibling test files' global Pest
 * helpers (one shared namespace). `PlatformDemoRecorded` is a clearly-synthetic name — verbatim spec
 * event names are reserved for real module events (F2+).
 *
 * @param  array<string, mixed>  $payload
 */
function recordTestEvent(
    string $name = 'PlatformDemoRecorded',
    string $module = 'platform',
    ActorRole $actorRole = ActorRole::System,
    ?int $actorId = null,
    string $entityType = 'demo',
    string $entityId = '1',
    array $payload = ['amount_minor' => 12000, 'currency' => 'EUR', 'fx_rate' => '1.0842'],
    ?string $correlationId = null,
    ?int $causationId = null,
): DomainEvent {
    return app(DomainEventRecorder::class)->record(
        name: $name,
        module: $module,
        actorRole: $actorRole,
        actorId: $actorId,
        entityType: $entityType,
        entityId: $entityId,
        payload: $payload,
        correlationId: $correlationId,
        causationId: $causationId,
    );
}

/**
 * Registers two DISTINCT, NAMED fake consumers for an event name and returns their FQCNs in
 * registration order, so a test can assert the recorder fanned out exactly those two rows in that
 * order. The consumers are named classes (InertConsumerA/B), NOT anonymous `new class` doubles: an
 * anonymous-class FQCN carries a NUL byte that PostgreSQL truncates, collapsing the two onto one
 * stored `consumer` string and tripping a false unique(domain_event_id, consumer) collision — see
 * InertConsumerA's docblock. Named classes are also the production-faithful shape (real module
 * consumers are always named). The registry is the container singleton AppServiceProvider binds, so
 * this is the same instance the recorder resolves.
 *
 * @return list<string>
 */
function registerTwoFakeConsumers(string $eventName): array
{
    $registry = app(ConsumerRegistry::class);
    $registry->register($eventName, InertConsumerA::class);
    $registry->register($eventName, InertConsumerB::class);

    return [InertConsumerA::class, InertConsumerB::class];
}

it('records a domain event and reads it back complete with its full envelope', function () {
    $correlationId = (string) Str::uuid7();

    $event = DB::transaction(fn () => recordTestEvent(
        name: 'PlatformDemoRecorded',
        module: Module::Commerce->value,   // a module emitter passes its registry value (a string)
        actorRole: ActorRole::NewcoOps,
        actorId: 7,
        entityType: 'voucher',
        entityId: '42',
        correlationId: $correlationId,
    ));

    // Re-fetch a fresh instance so the assertions exercise the read/hydration casts, not the values
    // still in memory from create().
    $read = DomainEvent::findOrFail($event->id);

    expect($read->event_id)->toBeString()
        ->and(Str::isUuid($read->event_id))->toBeTrue()        // a unique UUIDv7 public identity
        ->and($read->event_id[14])->toBe('7')                  // UUIDv7 version nibble (Str::uuid7, not v4)
        ->and($read->name)->toBe('PlatformDemoRecorded')
        ->and($read->schema_version)->toBe(1)                  // default 1 (integer cast)
        ->and($read->module)->toBe('commerce')                 // stored as the string the emitter passed
        ->and($read->actor_role)->toBe(ActorRole::NewcoOps)    // enum cast round-trip
        ->and($read->actor_id)->toEqual(7)                     // uncast bigint; loose compare spans engines
        ->and($read->entity_type)->toBe('voucher')
        ->and($read->entity_id)->toBe('42')
        ->and($read->correlation_id)->toBe($correlationId)     // caller-passed value preserved
        ->and($read->causation_id)->toBeNull()                 // root event has no cause
        // payload asserted by key (not a whole-array ->toBe): PostgreSQL jsonb does not preserve key
        // order, so an order-sensitive array compare is non-portable; the values are what matter.
        ->and($read->payload['amount_minor'])->toBe(12000)
        ->and($read->payload['currency'])->toBe('EUR')
        ->and($read->payload['fx_rate'])->toBe('1.0842')
        ->and($read->occurred_at)->toBeInstanceOf(CarbonImmutable::class);
});

it('commits the state change, the event and one pending delivery per consumer atomically', function () {
    expect(DB::transactionLevel())->toBe(0);

    $consumers = registerTwoFakeConsumers('PlatformDemoRecorded');

    DB::transaction(function () {
        // A state change in the SAME transaction (design D9: a cache-table write — a platform table,
        // idempotent, no domain pollution) proves the recorder's writes ride the caller's transaction.
        DB::table('cache')->insert(['key' => 'demo:atomic', 'value' => 'committed', 'expiration' => 9999999999]);
        recordTestEvent(name: 'PlatformDemoRecorded');
    });

    // Co-persistence (atomicity) is the claim here: the state change, the event and one delivery row
    // per consumer all committed together. The delivery STATUS is deliberately not asserted — task
    // 4.1's afterCommit hook now runs these deliveries post-commit, so the fan-out's pending state is
    // pinned inside the recording transaction by the dedicated fan-out test below, not here.
    expect(DB::table('cache')->where('key', 'demo:atomic')->count())->toBe(1)  // the state change
        ->and(DomainEvent::count())->toBe(1)                                    // the event
        ->and(EventDelivery::count())->toBe(count($consumers));                 // one delivery row per consumer, co-persisted
});

it('discards the state change, the event and its deliveries together on rollback', function () {
    expect(DB::transactionLevel())->toBe(0);

    registerTwoFakeConsumers('PlatformDemoRecorded');

    expect(fn () => DB::transaction(function () {
        DB::table('cache')->insert(['key' => 'demo:rollback', 'value' => 'discarded', 'expiration' => 9999999999]);
        recordTestEvent(name: 'PlatformDemoRecorded');
        throw new RuntimeException('force rollback');
    }))->toThrow(RuntimeException::class, 'force rollback');

    // No dual-write: a rolled-back transaction leaves nothing behind in any of the three.
    expect(DB::table('cache')->where('key', 'demo:rollback')->count())->toBe(0)
        ->and(DomainEvent::count())->toBe(0)
        ->and(EventDelivery::count())->toBe(0);
});

it('refuses to record outside a database transaction and writes nothing', function () {
    // Precondition (non-vacuity): DatabaseMigrations leaves us un-wrapped, so the guard can fire. If a
    // future change wrapped tests in a transaction this assertion fails loudly rather than letting the
    // throw-test pass vacuously.
    expect(DB::transactionLevel())->toBe(0);

    expect(fn () => recordTestEvent())->toThrow(NotInTransactionException::class);

    // The guard fires before any write — neither the event nor any delivery row leaks.
    expect(DomainEvent::count())->toBe(0)
        ->and(EventDelivery::count())->toBe(0);
});

it('preserves an FX rate as the exact decimal string it was given, never a float', function () {
    $event = DB::transaction(fn () => recordTestEvent(
        payload: ['amount_minor' => 12000, 'currency' => 'EUR', 'fx_rate' => '1.0842'],
    ));

    $payload = DomainEvent::findOrFail($event->id)->payload;

    // Invariants 5/6: FX rate survives as the exact decimal string (a float round-trip would corrupt
    // it); minor-units money stays an integer.
    expect($payload['fx_rate'])->toBeString()->toBe('1.0842')
        ->and($payload['amount_minor'])->toBe(12000);
});

it('assigns strictly increasing ids and a distinct event_id to events recorded in one transaction', function () {
    $events = DB::transaction(fn () => [
        recordTestEvent(entityId: '1'),
        recordTestEvent(entityId: '2'),
        recordTestEvent(entityId: '3'),
    ]);

    $ids = array_map(fn (DomainEvent $e) => $e->id, $events);
    $eventIds = array_map(fn (DomainEvent $e) => $e->event_id, $events);

    // id (the monotonic bigint PK) encodes intra-transaction causal/emission order (A § 12.4); each
    // event also gets its own fresh UUIDv7.
    expect($ids[0])->toBeLessThan($ids[1])
        ->and($ids[1])->toBeLessThan($ids[2])
        ->and(array_unique($eventIds))->toHaveCount(3);
});

it('returns an entity history by entity_type and entity_id in id order', function () {
    // Provenance spans transactions: record three events for one entity across separate transactions.
    foreach (['a', 'b', 'c'] as $marker) {
        DB::transaction(fn () => recordTestEvent(entityType: 'voucher', entityId: '99', payload: ['marker' => $marker]));
    }
    // A different entity, to prove the query actually filters.
    DB::transaction(fn () => recordTestEvent(entityType: 'voucher', entityId: '1000'));

    $history = DomainEvent::query()
        ->where('entity_type', 'voucher')
        ->where('entity_id', '99')
        ->orderBy('id')
        ->get();

    $markers = $history->map(fn (DomainEvent $e) => $e->payload['marker'])->all();

    expect($history)->toHaveCount(3)                 // exactly that entity's events, not the '1000' one
        ->and($markers)->toBe(['a', 'b', 'c']);      // returned in recorded (id) order
});

it('defaults correlation_id to the event\'s own event_id for a root event', function () {
    // recordTestEvent() omits correlationId, so the recorder defaults it to the event's OWN event_id
    // (a root event is its own correlation root — design D3), NOT an independent fresh UUID like the
    // audit recorder does.
    $event = DB::transaction(fn () => recordTestEvent());

    $read = DomainEvent::findOrFail($event->id);

    expect($read->correlation_id)->toBe($read->event_id)
        ->and(Str::isUuid($read->correlation_id))->toBeTrue();
});

it('fans out one pending delivery row per registered consumer, and none when no consumer is registered', function () {
    [$first, $second] = registerTwoFakeConsumers('PlatformDemoRecorded');

    // Assert INSIDE the recording transaction — before commit, before task 4.1's afterCommit hook
    // runs the deliveries — so this pins the recorder's in-transaction fan-out contract (the pending
    // rows it enqueues), immune to post-commit delivery flipping them to `done`.
    DB::transaction(function () use ($first, $second) {
        $event = recordTestEvent(name: 'PlatformDemoRecorded');

        $deliveries = EventDelivery::query()->where('domain_event_id', $event->id)->orderBy('id')->get();

        expect($deliveries)->toHaveCount(2)
            ->and($deliveries->pluck('consumer')->all())->toBe([$first, $second])  // FQCNs, in registration order
            ->and($deliveries->pluck('status')->all())->toBe([DeliveryStatus::Pending, DeliveryStatus::Pending])
            ->and($deliveries->pluck('attempts')->all())->toEqual([0, 0]);         // freshly enqueued
    });

    // An event whose name has no registered consumer gets zero delivery rows (no fan-out).
    $orphan = DB::transaction(fn () => recordTestEvent(name: 'NobodyListens'));
    expect(EventDelivery::query()->where('domain_event_id', $orphan->id)->count())->toBe(0);
});

it('threads a caller-supplied causation_id from the causing event', function () {
    [$rootId, $causedId] = DB::transaction(function () {
        $root = recordTestEvent(entityId: '1');
        $caused = recordTestEvent(entityId: '2', causationId: $root->id);

        return [$root->id, $caused->id];
    });

    expect(DomainEvent::findOrFail($rootId)->causation_id)->toBeNull()            // root: no cause
        ->and(DomainEvent::findOrFail($causedId)->causation_id)->toEqual($rootId); // caused: points at the cause
});

it('stamps occurred_at as the application-set UTC clock', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 6, 12, 9, 30, 0, 'UTC'));

    $event = DB::transaction(fn () => recordTestEvent());

    // The recorder sets occurred_at to CarbonImmutable::now('UTC'); the model casts it back to a
    // CarbonImmutable, so the round-trip proves the clock is application-set (time-travel-testable)
    // and in UTC, not a DB default.
    expect(DomainEvent::findOrFail($event->id)->occurred_at->format('Y-m-d H:i:s'))->toBe('2026-06-12 09:30:00');
});
