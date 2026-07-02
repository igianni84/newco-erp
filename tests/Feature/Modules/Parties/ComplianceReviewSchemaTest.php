<?php

use App\Modules\Parties\Enums\ComplianceReviewReason;
use App\Modules\Parties\Enums\ThresholdKind;
use App\Modules\Parties\Models\Customer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Task 1.1 (change parties-enhanced-kyc-threshold, design D6) — the `parties_compliance_reviews` migration
 * stands up the within-module Compliance review-queue at the RAW DB layer (the model + factory land in task
 * 2.2): the column set, the within-module FK to `parties_customers` (its column typed to match the bigint PK),
 * and the PostgreSQL-only value-set CHECKs on `reason` / `threshold_kind`. Both value-set columns are NOT NULL,
 * so the CHECK is a plain `IN (...)` — unlike the additive-nullable compliance columns' `IS NULL OR IN (...)`.
 *
 * The CHECK is PG-only (the create-table idiom), so the value-set test asserts BOTH halves of the documented
 * asymmetry, never skipping the off-lane: pgsql rejects the raw write (a QueryException naming the constraint);
 * SQLite ACCEPTS it (no portable CHECK — the enum cast is the floor there, and a query-builder write bypasses
 * the cast), a positive assertion, never a vacuous skip. The forbidden write is wrapped in DB::transaction (a
 * SAVEPOINT under RefreshDatabase's wrapper, testing-rule #5) so PG's transaction-abort stays isolated and the
 * follow-up reads remain valid. SQLite here; the cross-engine close re-runs the suite on PostgreSQL 17
 * (tests-pgsql lane).
 */

/**
 * A complete, DB-layer-valid `parties_compliance_reviews` row for the given Customer. Every column is NOT NULL
 * except `resolved_at` (NULL = open); overrides drop/change one field per test.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function complianceReviewRow(int $customerId, array $overrides = []): array
{
    return array_merge([
        'customer_id' => $customerId,
        'reason' => ComplianceReviewReason::EnhancedKycThreshold->value,
        'threshold_kind' => ThresholdKind::SingleTransaction->value,
        'tripped_amount_minor' => 1_000_000,
        'tripped_currency' => 'EUR',
        'resolved_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);
}

it('creates parties_compliance_reviews with the full entity columns', function () {
    expect(Schema::hasColumns('parties_compliance_reviews', [
        'id', 'customer_id', 'reason', 'threshold_kind',
        'tripped_amount_minor', 'tripped_currency', 'resolved_at',
        'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('accepts a fully-formed open review row and round-trips the amount', function () {
    $customerId = Customer::factory()->create()->id;

    DB::table('parties_compliance_reviews')->insert(complianceReviewRow($customerId));

    // Read each column with `->value()` (the ClubCreditSchemaTest idiom) — avoids a nullable stdClass and reads
    // the scalar directly. `->toEqual` not `->toBe` on the amount: PostgreSQL returns bigint as a numeric string.
    $review = DB::table('parties_compliance_reviews')->where('customer_id', $customerId);

    expect($review->value('reason'))->toBe(ComplianceReviewReason::EnhancedKycThreshold->value)
        ->and($review->value('threshold_kind'))->toBe(ThresholdKind::SingleTransaction->value)
        ->and($review->value('tripped_amount_minor'))->toEqual(1_000_000)
        ->and($review->value('tripped_currency'))->toBe('EUR')
        ->and($review->value('resolved_at'))->toBeNull();
});

it('types customer_id to match parties_customers.id — a valid within-module FK', function () {
    // The FK target `parties_customers.id` is a bigint identity; the FK column must be the same type or the
    // constraint could not be created (a type mismatch aborts the migration and reds every test in this file).
    // Schema::getColumnType surfaces the type portably on both engines (bigint on PG, integer on SQLite).
    expect(Schema::getColumnType('parties_compliance_reviews', 'customer_id'))
        ->toBe(Schema::getColumnType('parties_customers', 'id'));
});

it('rejects a review whose customer_id has no parent Customer (FK)', function () {
    // 999999 is not a parties_customers.id — the within-module FK rejects the orphan. SQLite enforces FKs in
    // this app (foreign_key_constraints on), so this throws on both engines.
    DB::table('parties_compliance_reviews')->insert(complianceReviewRow(999999));
})->throws(QueryException::class);

it('enforces the value-set at the PostgreSQL CHECK while SQLite accepts the raw write', function (string $column, string $constraint) {
    $customerId = Customer::factory()->create()->id;

    // A token deliberately ABSENT from the column's enum — so the test can never pass for the wrong reason.
    $bogus = 'definitely_not_a_valid_token';

    // Capture the constraint violation, savepoint-wrapped (testing-rule #5) so PG's transaction-abort stays
    // isolated and the count read after the throw is valid regardless of the surrounding transaction.
    $violation = '';
    try {
        DB::transaction(fn () => DB::table('parties_compliance_reviews')
            ->insert(complianceReviewRow($customerId, [$column => $bogus])));
    } catch (QueryException $e) {
        $violation = $e->getMessage();
    }

    if (DB::getDriverName() === 'pgsql') {
        // The truth engine: the CHECK rejects the out-of-enum token by its declared name; no row was written
        // (the rejected insert did not partially apply).
        expect($violation)->toContain($constraint)
            ->and(DB::table('parties_compliance_reviews')->where('customer_id', $customerId)->count())->toBe(0);
    } else {
        // SQLite has no DB CHECK (PG-only) — the raw insert bypasses the cast and is accepted (non-vacuous).
        expect($violation)->toBe('')
            ->and(DB::table('parties_compliance_reviews')->where('customer_id', $customerId)->value($column))
            ->toBe($bogus);
    }
})->with([
    'reason' => ['reason', 'parties_compliance_reviews_reason_check'],
    'threshold_kind' => ['threshold_kind', 'parties_compliance_reviews_threshold_kind_check'],
]);
