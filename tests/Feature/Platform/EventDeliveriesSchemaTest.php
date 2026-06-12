<?php

use App\Platform\Events\ActorRole;
use App\Platform\Events\DeliveryStatus;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Insert a minimal-but-valid `domain_events` row (the FK parent every delivery needs) and return
 * its bigint id. Only the NOT-NULL-without-default envelope columns are set; schema_version
 * defaults to 1, actor_id/causation_id stay null.
 */
function seedDomainEvent(): int
{
    return DB::table('domain_events')->insertGetId([
        'event_id' => (string) Str::uuid(),
        'name' => 'PlatformDemoRecorded',
        'module' => 'platform',
        'occurred_at' => now(),
        'actor_role' => ActorRole::System->value,
        'entity_type' => 'demo',
        'entity_id' => '1',
        'correlation_id' => (string) Str::uuid(),
        'payload' => json_encode([]),
    ]);
}

/**
 * A complete, DB-layer-valid `event_deliveries` row for the given parent event id. status,
 * attempts, available_at and last_error all have defaults or are nullable, so the minimal row is
 * (domain_event_id, consumer) + the nullable timestamps; overrides drop/change one field per test.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function eventDeliveryRow(int $domainEventId, array $overrides = []): array
{
    return array_merge([
        'domain_event_id' => $domainEventId,
        'consumer' => 'App\\Modules\\Procurement\\Listeners\\RecordSupplierPayment',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);
}

it('creates event_deliveries with the full ledger columns', function () {
    expect(Schema::hasColumns('event_deliveries', [
        'id', 'domain_event_id', 'consumer', 'status', 'attempts',
        'available_at', 'last_error', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('has the unique (domain_event_id, consumer) index', function () {
    expect(Schema::hasIndex('event_deliveries', ['domain_event_id', 'consumer'], 'unique'))->toBeTrue();
});

it('has the partial pending index, asserted by name (portable across engines)', function () {
    // Schema::getIndexes() surfaces an index's name + columns on BOTH SQLite and PostgreSQL but NOT
    // its partial predicate, so the portable proof of existence is the index NAME (hasIndex matches
    // $value['name'] === $index). The predicate `WHERE status = 'pending'` lives in the migration's
    // raw DDL and runs identically on both engines — an invalid predicate would abort the migration
    // and error every test in this file, so a green suite also proves the partial DDL is valid SQL
    // on whichever engine is running (the pgsql lane, task 5.2, exercises the PostgreSQL side).
    expect(Schema::hasIndex('event_deliveries', 'event_deliveries_pending_index'))->toBeTrue();
});

it('defaults a fresh delivery row to status=pending and attempts=0', function () {
    $id = seedDomainEvent();
    DB::table('event_deliveries')->insert(eventDeliveryRow($id, ['consumer' => 'fresh']));

    $row = DB::table('event_deliveries')->where('consumer', 'fresh');
    expect($row->value('status'))->toEqual(DeliveryStatus::Pending->value)
        ->and($row->value('attempts'))->toEqual(0);
});

it('accepts a fully-formed delivery row', function () {
    $id = seedDomainEvent();
    DB::table('event_deliveries')->insert(eventDeliveryRow($id, [
        'consumer' => 'App\\Modules\\Inventory\\Listeners\\RecordSupplierPayment',
        'status' => DeliveryStatus::Done->value,
        'attempts' => 1,
        'available_at' => now(),
        'last_error' => null,
    ]));

    expect(DB::table('event_deliveries')->where('domain_event_id', $id)->value('status'))
        ->toEqual(DeliveryStatus::Done->value);
});

it('rejects a duplicate (domain_event_id, consumer) pair at the DB layer', function () {
    $id = seedDomainEvent();
    $consumer = 'App\\Modules\\Procurement\\Listeners\\RecordSupplierPayment';
    DB::table('event_deliveries')->insert(eventDeliveryRow($id, ['consumer' => $consumer]));

    // The same (event, consumer) again violates the unique pair — the fan-out must never create a
    // second row for a consumer already enrolled on an event (retries mutate the existing row).
    DB::table('event_deliveries')->insert(eventDeliveryRow($id, ['consumer' => $consumer]));
})->throws(QueryException::class);

it('allows the same consumer on different events and different consumers on one event', function () {
    // Proves the unique is on the PAIR, not on either column alone (non-vacuity for the constraint).
    $event1 = seedDomainEvent();
    $event2 = seedDomainEvent();

    DB::table('event_deliveries')->insert([
        eventDeliveryRow($event1, ['consumer' => 'A']),
        eventDeliveryRow($event1, ['consumer' => 'B']), // different consumer, same event — allowed
        eventDeliveryRow($event2, ['consumer' => 'A']), // same consumer, different event — allowed
    ]);

    expect(DB::table('event_deliveries')->count())->toEqual(3);
});

it('rejects a delivery whose domain_event_id has no parent event (FK)', function () {
    // 999999 is not a domain_events.id — the FK to domain_events.id rejects the orphan. SQLite
    // enforces FKs in this app (foreign_key_constraints on), so this throws on both engines.
    DB::table('event_deliveries')->insert(eventDeliveryRow(999999, ['consumer' => 'orphan']));
})->throws(QueryException::class);

it('rejects an insert missing consumer at the DB layer (NOT NULL floor)', function () {
    $id = seedDomainEvent();
    $row = eventDeliveryRow($id);
    unset($row['consumer']);

    DB::table('event_deliveries')->insert($row);
})->throws(QueryException::class);
