<?php

use App\Modules\Catalog\Enums\ProducerProjectionStatus;
use App\Modules\Catalog\Models\ProducerState;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pins the Catalog-owned producer-state PROJECTION — the table, model and enum that back the codebase's
 * FIRST cross-module read model (catalog-lifecycle-approval, task 1.1; design D3/D4/D10; product-catalog —
 * Requirement: Producer-State Projection and Event Consumption). The {@see ProducerLifecycleProjector}
 * (task 1.2) is its sole writer and the *Producer Activation Gate* its sole reader; this test pins the
 * persistence floor underneath both: the schema is created on both engines, the model casts `status` to the
 * {@see ProducerProjectionStatus} enum, `producer_id` is UNIQUE (one row per producer — the upsert key,
 * enforced on BOTH engines), and the `status` value-set is held at the DB CHECK on PostgreSQL.
 *
 * Trait — DatabaseMigrations (the substrate/projection convention, design D11): no wrapper transaction, each
 * test re-migrates fresh, so the consumer's inline post-commit hook can fire at transactionLevel 0 in the
 * sibling tests. Here it also lets the raw-insert CHECK probe (savepoint-wrapped per testing-rule #5) abort a
 * PG (sub)transaction without poisoning the follow-up row-state query.
 */
uses(DatabaseMigrations::class);

it('creates the catalog_producer_states projection table with the expected columns on both engines', function () {
    expect(Schema::hasTable('catalog_producer_states'))->toBeTrue()
        ->and(Schema::hasColumns('catalog_producer_states', [
            'id', 'producer_id', 'status', 'last_event_id', 'created_at', 'updated_at',
        ]))->toBeTrue();
});

it('persists a projection row and casts status to the ProducerProjectionStatus enum, ids to integer', function () {
    $row = ProducerState::create([
        'producer_id' => 7,
        'status' => ProducerProjectionStatus::Active,
        'last_event_id' => 42,
    ]);

    // Re-fetch so the assertions exercise the read/hydration casts, not the in-memory create() values.
    $read = ProducerState::findOrFail($row->id);

    expect($read->status)->toBeInstanceOf(ProducerProjectionStatus::class)
        ->and($read->status)->toBe(ProducerProjectionStatus::Active)  // the enum cast
        ->and($read->producer_id)->toBe(7)                            // the integer cast
        ->and($read->last_event_id)->toBe(42);                        // the integer cast (the watermark)
});

it('rejects a duplicate producer_id — exactly one projection row per producer (the upsert key)', function () {
    ProducerState::create([
        'producer_id' => 7,
        'status' => ProducerProjectionStatus::Active,
        'last_event_id' => 10,
    ]);

    // A second row for the same producer violates the unique index — enforced on BOTH engines (SQLite and PG).
    expect(fn () => ProducerState::create([
        'producer_id' => 7,
        'status' => ProducerProjectionStatus::Retired,
        'last_event_id' => 11,
    ]))->toThrow(QueryException::class);

    // The rejected duplicate never landed — still exactly one row, still the original status.
    expect(ProducerState::query()->count())->toBe(1)
        ->and(ProducerState::query()->sole()->status)->toBe(ProducerProjectionStatus::Active);
});

it('enforces the status value-set at the PostgreSQL CHECK, while SQLite accepts the raw insert', function () {
    // A status-shaped literal deliberately ABSENT from ProducerProjectionStatus — guard the premise so a
    // future enum addition of this token could never let the test pass for the wrong reason.
    $outOfDomain = 'suspended';
    expect(array_map(fn (ProducerProjectionStatus $s): string => $s->value, ProducerProjectionStatus::cases()))
        ->not->toContain($outOfDomain);

    // A raw query-builder insert bypasses the ProducerProjectionStatus cast (the app-layer floor), so on PG
    // it is the DB CHECK, and only the CHECK, that stops the out-of-domain literal. Wrap in a transaction so
    // a PG constraint-abort stays isolated (testing-rule #5) and the row-state check after the throw is valid.
    $message = '';
    try {
        DB::transaction(fn () => DB::table('catalog_producer_states')->insert([
            'producer_id' => 99,
            'status' => $outOfDomain,
            'last_event_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    } catch (QueryException $e) {
        $message = $e->getMessage();
    }

    if (DB::getDriverName() === 'pgsql') {
        // The constraint-truth engine: catalog_producer_states_status_check rejects the value the builder
        // insert smuggled past the cast, and the row never lands.
        expect($message)->toContain('catalog_producer_states_status_check')
            ->and(DB::table('catalog_producer_states')->where('status', $outOfDomain)->exists())->toBeFalse();
    } else {
        // SQLite has no DB CHECK (added on pgsql only) — the raw insert is accepted. The value-set floor on
        // this lane is the enum cast, which a query-builder write does not run. A positive assertion (the row
        // lands), never a vacuous skip.
        expect($message)->toBe('')
            ->and(DB::table('catalog_producer_states')->where('status', $outOfDomain)->exists())->toBeTrue();
    }
});
