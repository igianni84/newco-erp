<?php

use App\Modules\Parties\Models\Customer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Task 1.2 (change parties-anonymisation; design D4 + Migration Plan; party-registry — Requirements: Customer
 * Anonymisation (Right-to-Erasure), Customer Address) — the two additive migrations that stand up the erasure
 * schema: `anonymised_at` on `parties_customers` (the GDPR erasure timestamp, orthogonal to the § 4.1 status FSM)
 * and the `parties_addresses` table (the Customer's billing Address, DEC-068). These guards prove the schema at
 * the RAW DB layer (the Address model + factory land in task 2.1; the AnonymiseCustomer overwrite in task 3.2):
 * columns present, the nullable/NOT-NULL floors, and the owning within-module FK. SQLite here; the cross-engine
 * close (task 7.1) re-runs the suite on PostgreSQL 17 (tests-pgsql lane).
 */

/**
 * A complete, DB-layer-valid `parties_addresses` row for the given owning customer id. The optional
 * company-billing + secondary fields default present so the minimal row is a full billing address; overrides
 * drop/change one field per test.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function addressRow(int $customerId, array $overrides = []): array
{
    return array_merge([
        'customer_id' => $customerId,
        'line1' => '10 Downing Street',
        'line2' => 'Flat 2',
        'locality' => 'London',
        'region' => 'Greater London',
        'postal_code' => 'SW1A 2AA',
        'country_code' => 'GB',
        'company_name' => 'Acme Wine Holdings Ltd',
        'vat_id' => 'GB123456789',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);
}

it('adds a nullable anonymised_at to parties_customers — born NULL, additive (DEC-071)', function () {
    expect(Schema::hasColumn('parties_customers', 'anonymised_at'))->toBeTrue();

    // A Customer is born un-anonymised: the additive column is NULL at creation (no backfill, no default).
    $id = Customer::factory()->create()->id;
    expect(DB::table('parties_customers')->where('id', $id)->value('anonymised_at'))->toBeNull();
});

it('accepts a timestamp write into anonymised_at (Postgres-truthful timestamptz, SQLite-compatible)', function () {
    // Proves the column stores a moment on both engines — AnonymiseCustomer (task 3.2) stamps it. No cast is
    // exercised here (the model gains it in 3.2); this is the raw-column floor.
    $id = Customer::factory()->create()->id;

    DB::table('parties_customers')->where('id', $id)->update(['anonymised_at' => now()]);

    expect(DB::table('parties_customers')->where('id', $id)->value('anonymised_at'))->not->toBeNull();
});

it('creates parties_addresses with the full entity columns', function () {
    expect(Schema::hasColumns('parties_addresses', [
        'id', 'customer_id',
        'line1', 'line2', 'locality', 'region', 'postal_code', 'country_code',
        'company_name', 'vat_id',
        'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('accepts a fully-formed billing address row scoped to a Customer', function () {
    $customerId = Customer::factory()->create()->id;

    DB::table('parties_addresses')->insert(addressRow($customerId));

    expect(DB::table('parties_addresses')->where('customer_id', $customerId)->count())->toBe(1)
        ->and(DB::table('parties_addresses')->where('customer_id', $customerId)->value('country_code'))->toBe('GB');
});

it('accepts an address with the optional fields NULL (line2 / region / company_name / vat_id)', function () {
    $customerId = Customer::factory()->create()->id;

    DB::table('parties_addresses')->insert(addressRow($customerId, [
        'line2' => null,
        'region' => null,
        'company_name' => null,
        'vat_id' => null,
    ]));

    // Read each column via ->value() (the sibling ClubCreditSchemaTest idiom) rather than ->first()->prop —
    // ->first() is stdClass|null, so a property access would be unsafe on the null branch (PHPStan).
    $address = DB::table('parties_addresses')->where('customer_id', $customerId);

    expect($address->value('line2'))->toBeNull()
        ->and($address->value('region'))->toBeNull()
        ->and($address->value('company_name'))->toBeNull()
        ->and($address->value('vat_id'))->toBeNull();
});

it('rejects an address whose customer_id has no parent Customer (the owning FK)', function () {
    // 999999 is not a parties_customers.id — the within-module FK rejects the orphan. SQLite enforces FKs in this
    // app (foreign_key_constraints on), so this throws on both engines; a mismatched FK column type would not
    // bind the constraint, so a green rejection also proves customer_id matches parties_customers.id (bigint).
    DB::table('parties_addresses')->insert(addressRow(999999));
})->throws(QueryException::class);

it('rejects an insert omitting a required address field (NOT NULL floor)', function (string $column) {
    // line1 / locality / postal_code / country_code carry no default — omitting one is a NOT-NULL violation, never
    // a silent born-state default (the optional fields are proven nullable by the sibling test above).
    $customerId = Customer::factory()->create()->id;
    $row = addressRow($customerId);
    unset($row[$column]);

    expect(fn () => DB::table('parties_addresses')->insert($row))->toThrow(QueryException::class);
})->with(['line1', 'locality', 'postal_code', 'country_code']);
