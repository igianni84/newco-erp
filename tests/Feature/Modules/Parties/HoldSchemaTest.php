<?php

use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldStatus;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Models\Hold;
use App\Platform\Events\ActorRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pins the additive `parties_holds` schema (parties-holds task 1.2; design L1; party-registry — Requirement:
 * Hold Registry). It proves the migration stands up the unified Hold table with the typed casts (`hold_type` /
 * `scope_type` / `status` as the three Hold enums, `placed_actor_role` / `lifted_actor_role` as ActorRole,
 * `lifted_at` immutable), the polymorphic `(scope_type, scope_id, status)` read-API index, and that the three
 * value-set columns carry the PostgreSQL-only `CHECK (col IN (...))` (an out-of-enum token rejected on PG;
 * accepted by SQLite, where the cast is the floor and a query-builder write bypasses it).
 *
 * RefreshDatabase migrates the table. The CHECK is PG-only (the create-table idiom), so the value-set test
 * asserts BOTH halves of the documented asymmetry, never skipping the off-lane: pgsql rejects the raw insert
 * (a QueryException naming the constraint); SQLite ACCEPTS it (a positive assertion, never a vacuous skip). The
 * forbidden insert is wrapped in DB::transaction (a SAVEPOINT under the wrapper, testing-rule #5) so PG's
 * transaction-abort stays isolated and the follow-up reads remain valid — inlined locally rather than via a
 * cross-file helper, which a filtered single-file run would not load.
 */
uses(RefreshDatabase::class);

it('round-trips a Hold row with the typed enum + actor casts', function () {
    $hold = Hold::factory()->create([
        'hold_type' => HoldType::Kyc,
        'scope_type' => HoldScope::Profile,
        'status' => HoldStatus::Active,
        'placed_actor_role' => ActorRole::NewcoOps,
    ]);

    // Re-fetch so the assertions exercise the hydration casts, not the in-memory write values.
    $read = Hold::findOrFail($hold->id);

    expect($read->hold_type)->toBe(HoldType::Kyc)
        ->and($read->scope_type)->toBe(HoldScope::Profile)
        ->and($read->status)->toBe(HoldStatus::Active)
        ->and($read->placed_actor_role)->toBe(ActorRole::NewcoOps)
        ->and($read->scope_id)->toBeInt()
        ->and($read->reason)->toBeNull()
        ->and($read->lift_reason)->toBeNull()
        ->and($read->lifted_actor_role)->toBeNull()
        ->and($read->lifted_actor_id)->toBeNull()
        ->and($read->lifted_at)->toBeNull();
});

it('round-trips the lift columns as typed values once a Hold is lifted', function () {
    $hold = Hold::factory()->create();

    $hold->status = HoldStatus::Lifted;
    $hold->lift_reason = 'cleared on review';
    $hold->lifted_actor_role = ActorRole::NewcoOps;
    $hold->lifted_actor_id = 42;
    $hold->lifted_at = CarbonImmutable::now();
    $hold->save();

    $read = Hold::findOrFail($hold->id);

    expect($read->status)->toBe(HoldStatus::Lifted)
        ->and($read->lift_reason)->toBe('cleared on review')
        ->and($read->lifted_actor_role)->toBe(ActorRole::NewcoOps)
        ->and($read->lifted_actor_id)->toBe(42)
        ->and($read->lifted_at)->toBeInstanceOf(CarbonImmutable::class);
});

it('creates the (scope_type, scope_id, status) composite read-API index', function () {
    // Match by column tuple (robust to engine name differences) — the index exists on BOTH engines and covers
    // exactly the read-API "active Holds for this scope" lookup, in leftmost-prefix order.
    $hasComposite = collect(Schema::getIndexes('parties_holds'))
        ->contains(fn (array $index): bool => $index['columns'] === ['scope_type', 'scope_id', 'status']);

    expect($hasComposite)->toBeTrue();
});

it('enforces the Hold value-set at the PostgreSQL CHECK while SQLite accepts the raw write', function (string $column, string $constraint, string $validToken) {
    // A token deliberately ABSENT from the column's enum — so the test can never pass for the wrong reason.
    $bogus = 'definitely_not_a_valid_token';

    // A fully valid baseline row (every value-set column a valid token, the actor recorded `system`); the one
    // column under test is overridden so ONLY that column's CHECK can reject the write.
    $base = [
        'hold_type' => HoldType::Admin->value,
        'scope_type' => HoldScope::Customer->value,
        'scope_id' => 1,
        'status' => HoldStatus::Active->value,
        'placed_actor_role' => ActorRole::System->value,
    ];

    // Capture the constraint violation, savepoint-wrapped (testing-rule #5) so PG's transaction-abort stays
    // isolated and the row-count read after the throw is valid regardless of the surrounding transaction.
    $violation = '';
    try {
        DB::transaction(fn () => DB::table('parties_holds')->insert(array_merge($base, [$column => $bogus])));
    } catch (QueryException $e) {
        $violation = $e->getMessage();
    }

    if (DB::getDriverName() === 'pgsql') {
        // The truth engine: the non-nullable CHECK rejects the out-of-enum token by its declared name; the row
        // never landed, proving the rejected write did not partially apply.
        expect($violation)->toContain($constraint)
            ->and(DB::table('parties_holds')->where($column, $bogus)->count())->toBe(0);
    } else {
        // SQLite has no DB CHECK (PG-only) — the raw write bypasses the cast and is accepted (non-vacuous).
        expect($violation)->toBe('')
            ->and(DB::table('parties_holds')->where($column, $bogus)->count())->toBe(1);
    }

    // A fully valid row inserts on BOTH engines (the valid token for the column under test).
    DB::table('parties_holds')->insert(array_merge($base, [$column => $validToken]));
    expect(DB::table('parties_holds')->where($column, $validToken)->count())->toBe(1);
})->with([
    'hold_type' => ['hold_type', 'parties_holds_hold_type_check', 'kyc'],
    'scope_type' => ['scope_type', 'parties_holds_scope_type_check', 'profile'],
    'status' => ['status', 'parties_holds_status_check', 'lifted'],
]);
