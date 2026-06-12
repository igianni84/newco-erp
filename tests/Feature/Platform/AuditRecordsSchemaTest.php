<?php

use App\Platform\Events\ActorRole;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * A complete, DB-layer-valid audit_records row (every NOT NULL column without a default
 * present). Overrides let each test drop or change exactly one field to prove a constraint.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function auditRecordRow(array $overrides = []): array
{
    return array_merge([
        'occurred_at' => now(),
        'module' => 'platform',
        'actor_role' => ActorRole::NewcoOps->value,
        'entity_type' => 'voucher',
        'entity_id' => '1',
        'correlation_id' => (string) Str::uuid(),
        'action' => 'voucher.cancel',
        'before' => json_encode(['status' => 'active']),
        'after' => json_encode(['status' => 'cancelled']),
        'authorization_basis' => 'operator_console',
    ], $overrides);
}

it('creates audit_records with the envelope-core + audit columns', function () {
    expect(Schema::hasColumns('audit_records', [
        'id', 'occurred_at', 'module', 'actor_role', 'actor_id',
        'entity_type', 'entity_id', 'correlation_id',
        'action', 'before', 'after', 'authorization_basis',
    ]))->toBeTrue();
});

it('does NOT carry the event-log-only columns (audit shares the envelope core only)', function () {
    expect(Schema::hasColumns('audit_records', ['event_id']))->toBeFalse()
        ->and(Schema::hasColumns('audit_records', ['name']))->toBeFalse()
        ->and(Schema::hasColumns('audit_records', ['schema_version']))->toBeFalse()
        ->and(Schema::hasColumns('audit_records', ['causation_id']))->toBeFalse();
});

it('has the entity-history composite index, id-suffixed for append-order reads', function () {
    expect(Schema::hasIndex('audit_records', ['entity_type', 'entity_id', 'id']))->toBeTrue();
});

it('accepts a fully-formed audit row', function () {
    DB::table('audit_records')->insert(auditRecordRow(['entity_id' => 'happy']));

    // The row coming back proves the happy-path insert: had it thrown, the test would error
    // before this assertion; had no row landed, the value would be null and toEqual would fail.
    expect(DB::table('audit_records')->where('entity_id', 'happy')->value('action'))
        ->toEqual('voucher.cancel');
});

it('allows before/after to be null (creation has no before; redaction nulls both — design D7)', function () {
    DB::table('audit_records')->insert(auditRecordRow([
        'entity_id' => 'redacted',
        'before' => null,
        'after' => null,
    ]));

    expect(DB::table('audit_records')->where('entity_id', 'redacted')->exists())->toBeTrue();
});

it('rejects an insert missing actor_role at the DB layer (invariant-8 NOT NULL floor)', function () {
    $row = auditRecordRow();
    unset($row['actor_role']);

    DB::table('audit_records')->insert($row);
})->throws(QueryException::class);

it('rejects an insert missing authorization_basis at the DB layer', function () {
    $row = auditRecordRow();
    unset($row['authorization_basis']);

    DB::table('audit_records')->insert($row);
})->throws(QueryException::class);

it('rejects an insert missing action at the DB layer', function () {
    $row = auditRecordRow();
    unset($row['action']);

    DB::table('audit_records')->insert($row);
})->throws(QueryException::class);
