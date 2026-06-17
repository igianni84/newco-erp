<?php

use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Enums\SanctionsStatus;
use App\Modules\Parties\Enums\ScreeningTriggerSource;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Producer;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Pins the additive compliance schema (parties-compliance task 1.2; design L1; party-registry — Requirements:
 * Customer KYC Lifecycle, Customer Sanctions Screening Lifecycle, Producer KYC Lifecycle). It proves the
 * migration adds the KYC/sanctions columns as **nullable with no default** (a Customer/Producer is creatable
 * un-screened — DEC-071), that the `Customer`/`Producer` casts expose the typed enums + booleans + immutable
 * datetimes, and that the three Customer value-set columns + the Producer `kyc_status` carry the PostgreSQL-only
 * `CHECK (col IS NULL OR col IN (...))` (NULL always admitted; an out-of-enum token rejected).
 *
 * RefreshDatabase migrates the additive migration. The CHECK is PG-only (the create-table idiom), so the value-set
 * test asserts BOTH halves of the documented asymmetry, never skipping the off-lane: pgsql rejects the raw write
 * (a QueryException naming the constraint); SQLite ACCEPTS it (no portable CHECK — the cast is the floor there, and
 * a query-builder write bypasses the cast), a positive assertion, never a vacuous skip. The forbidden write is
 * wrapped in DB::transaction (a SAVEPOINT under the wrapper, testing-rule #5) so PG's transaction-abort stays
 * isolated and the follow-up reads remain valid — inlined locally rather than via the cross-file
 * captureConstraintViolation helper, which a filtered single-file run would not load.
 */
uses(RefreshDatabase::class);

it('creates a Customer with every compliance column NULL — un-screened by birth (DEC-071)', function () {
    $read = Customer::findOrFail(Customer::factory()->create()->id);

    expect($read->kyc_status)->toBeNull()
        ->and($read->kyc_required)->toBeNull()
        ->and($read->enhanced_kyc_flag)->toBeNull()
        ->and($read->enhanced_kyc_at)->toBeNull()
        ->and($read->sanctions_status)->toBeNull()
        ->and($read->last_screening_at)->toBeNull()
        ->and($read->next_rescreen_at)->toBeNull()
        ->and($read->screening_trigger_source)->toBeNull();
});

it('creates a Producer with a NULL kyc_status — a never-screened Producer (cleared at the gate)', function () {
    expect(Producer::findOrFail(Producer::factory()->create()->id)->kyc_status)->toBeNull();
});

it('round-trips the Customer compliance casts as typed values', function () {
    $customer = Customer::factory()->create();

    $customer->kyc_status = KycStatus::Pending;
    $customer->kyc_required = true;
    $customer->enhanced_kyc_flag = true;
    $customer->enhanced_kyc_at = CarbonImmutable::now();
    $customer->sanctions_status = SanctionsStatus::Passed;
    $customer->last_screening_at = CarbonImmutable::now();
    $customer->next_rescreen_at = CarbonImmutable::now()->addMonths(12);
    $customer->screening_trigger_source = ScreeningTriggerSource::Onboarding;
    $customer->save();

    // Re-fetch so the assertions exercise the hydration casts, not the in-memory write values.
    $read = Customer::findOrFail($customer->id);

    expect($read->kyc_status)->toBe(KycStatus::Pending)
        ->and($read->kyc_required)->toBeTrue()
        ->and($read->enhanced_kyc_flag)->toBeTrue()
        ->and($read->enhanced_kyc_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($read->sanctions_status)->toBe(SanctionsStatus::Passed)
        ->and($read->last_screening_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($read->next_rescreen_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($read->screening_trigger_source)->toBe(ScreeningTriggerSource::Onboarding);
});

it('round-trips the Producer kyc_status cast as a typed value', function () {
    $producer = Producer::factory()->create();
    $producer->kyc_status = KycStatus::Verified;
    $producer->save();

    expect(Producer::findOrFail($producer->id)->kyc_status)->toBe(KycStatus::Verified);
});

it('enforces the compliance value-set at the PostgreSQL CHECK while SQLite accepts the raw write', function (string $table, string $column, string $constraint, string $validToken) {
    // A row to update — the value-set columns are NULL at creation (the un-screened birth state).
    $id = $table === 'parties_customers'
        ? Customer::factory()->create()->id
        : Producer::factory()->create()->id;

    // A token deliberately ABSENT from the column's enum — so the test can never pass for the wrong reason.
    $bogus = 'definitely_not_a_valid_token';

    // Capture the constraint violation, savepoint-wrapped (testing-rule #5) so PG's transaction-abort stays
    // isolated and the row-state read after the throw is valid regardless of the surrounding transaction.
    $violation = '';
    try {
        DB::transaction(fn () => DB::table($table)->where('id', $id)->update([$column => $bogus]));
    } catch (QueryException $e) {
        $violation = $e->getMessage();
    }

    if (DB::getDriverName() === 'pgsql') {
        // The truth engine: the nullable CHECK rejects the out-of-enum token by its declared name; the row is
        // untouched (still NULL from creation), proving the rejected write did not partially apply.
        expect($violation)->toContain($constraint)
            ->and(DB::table($table)->where('id', $id)->value($column))->toBeNull();
    } else {
        // SQLite has no DB CHECK (PG-only) — the raw write bypasses the cast and is accepted (non-vacuous).
        expect($violation)->toBe('')
            ->and(DB::table($table)->where('id', $id)->value($column))->toBe($bogus);
    }

    // The CHECK is `col IS NULL OR col IN (...)`: NULL and any valid token are accepted on BOTH engines.
    DB::table($table)->where('id', $id)->update([$column => null]);
    expect(DB::table($table)->where('id', $id)->value($column))->toBeNull();

    DB::table($table)->where('id', $id)->update([$column => $validToken]);
    expect(DB::table($table)->where('id', $id)->value($column))->toBe($validToken);
})->with([
    'customer kyc_status' => ['parties_customers', 'kyc_status', 'parties_customers_kyc_status_check', 'verified'],
    'customer sanctions_status' => ['parties_customers', 'sanctions_status', 'parties_customers_sanctions_status_check', 'passed'],
    'customer screening_trigger_source' => ['parties_customers', 'screening_trigger_source', 'parties_customers_screening_trigger_source_check', 'onboarding'],
    'producer kyc_status' => ['parties_producers', 'kyc_status', 'parties_producers_kyc_status_check', 'not_required'],
]);
