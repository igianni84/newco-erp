<?php

use App\Platform\Events\ActorRole;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * A complete, DB-layer-valid domain_events row (every NOT NULL column without a default
 * present). Overrides let each test drop or change exactly one field to prove a constraint.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function domainEventRow(array $overrides = []): array
{
    return array_merge([
        'event_id' => (string) Str::uuid(),
        'name' => 'PlatformDemoRecorded',
        'module' => 'platform',
        'occurred_at' => now(),
        'actor_role' => ActorRole::System->value,
        'entity_type' => 'demo',
        'entity_id' => '1',
        'correlation_id' => (string) Str::uuid(),
        'payload' => json_encode(['fx_rate' => '1.0842']),
    ], $overrides);
}

it('creates domain_events with the full ADR envelope columns', function () {
    expect(Schema::hasColumns('domain_events', [
        'id', 'event_id', 'name', 'schema_version', 'module', 'occurred_at',
        'actor_role', 'actor_id', 'entity_type', 'entity_id',
        'correlation_id', 'causation_id', 'payload',
    ]))->toBeTrue();
});

it('enforces a unique index on event_id', function () {
    expect(Schema::hasIndex('domain_events', ['event_id'], 'unique'))->toBeTrue();
});

it('has the two launch composite indexes, id-suffixed for causal-order reads', function () {
    expect(Schema::hasIndex('domain_events', ['entity_type', 'entity_id', 'id']))->toBeTrue()
        ->and(Schema::hasIndex('domain_events', ['name', 'id']))->toBeTrue();
});

it('accepts a fully-formed event row and defaults schema_version to 1', function () {
    $eventId = (string) Str::uuid();
    DB::table('domain_events')->insert(domainEventRow(['event_id' => $eventId]));

    // The row coming back (and schema_version defaulting to 1) proves the happy-path insert:
    // had it thrown, the test would error before this assertion; had no row landed, the value
    // would be null and `toEqual(1)` would fail. A real UUID is used because event_id is a native
    // `uuid` column on PostgreSQL (strict), only a loose varchar on SQLite.
    expect(DB::table('domain_events')->where('event_id', $eventId)->value('schema_version'))
        ->toEqual(1);
});

it('links a causal chain through the self-referencing causation_id FK', function () {
    $rootEventId = (string) Str::uuid();
    DB::table('domain_events')->insert(domainEventRow(['event_id' => $rootEventId]));
    $rootId = DB::table('domain_events')->where('event_id', $rootEventId)->value('id');

    $causedEventId = (string) Str::uuid();
    DB::table('domain_events')->insert(domainEventRow([
        'event_id' => $causedEventId,
        'causation_id' => $rootId,
    ]));

    expect(DB::table('domain_events')->where('event_id', $causedEventId)->value('causation_id'))
        ->toEqual($rootId);
});

it('rejects an insert missing actor_role at the DB layer (invariant-8 NOT NULL floor)', function () {
    $row = domainEventRow();
    unset($row['actor_role']);

    DB::table('domain_events')->insert($row);
})->throws(QueryException::class);

it('rejects a duplicate event_id at the DB layer', function () {
    $eventId = (string) Str::uuid();
    DB::table('domain_events')->insert(domainEventRow(['event_id' => $eventId]));
    DB::table('domain_events')->insert(domainEventRow(['event_id' => $eventId]));
})->throws(QueryException::class);
