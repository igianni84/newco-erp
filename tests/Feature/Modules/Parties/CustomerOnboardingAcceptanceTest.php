<?php

use App\Modules\Parties\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * Pins the additive onboarding-acceptance schema (parties-membership-activation task 1.1; design L1;
 * party-registry — Requirement: Customer Onboarding Activation). It proves the migration adds the three
 * acceptance-timestamp columns (`email_verified_at`, `tc_accepted_at`, `privacy_accepted_at`) as **nullable with
 * no default** — born `NULL`, the un-accepted birth state (DEC-071/DEC-073) — and that the `Customer` casts expose
 * them as typed `immutable_datetime`s that round-trip. These are the gate inputs the later `ActivateCustomer`
 * composite gate reads (§ 4.1); no setter ships in this slice, so the columns are operator/test-set.
 *
 * RefreshDatabase migrates the additive migration; the round-trip re-fetches so the assertions exercise the
 * hydration casts, not the in-memory write values. The columns are plain `timestamptz` — no PG-only CHECK — so the
 * value is read back through the `immutable_datetime` cast (testing-rule #4: never byte-compare a raw timestamptz,
 * which renders a `+00` zone suffix on PG; read through the cast and compare Carbon instances).
 */
uses(RefreshDatabase::class);

it('creates a Customer with the three onboarding-acceptance columns NULL — un-accepted by birth', function () {
    $read = Customer::findOrFail(Customer::factory()->create()->id);

    expect($read->email_verified_at)->toBeNull()
        ->and($read->tc_accepted_at)->toBeNull()
        ->and($read->privacy_accepted_at)->toBeNull();
});

it('round-trips the three acceptance timestamps as immutable_datetime casts', function () {
    // Fixed moments (no microseconds) so the round-trip holds identically on SQLite and PG17.
    $customer = Customer::factory()->create([
        'email_verified_at' => CarbonImmutable::parse('2026-06-18 09:30:00'),
        'tc_accepted_at' => CarbonImmutable::parse('2026-06-18 09:31:00'),
        'privacy_accepted_at' => CarbonImmutable::parse('2026-06-18 09:32:00'),
    ]);

    // Re-fetch so the assertions exercise the hydration casts, not the in-memory write values.
    $read = Customer::findOrFail($customer->id);

    // Typed by the cast, then a value round-trip via `->format()` (testing-rule #4: read back THROUGH the
    // immutable_datetime cast and format — never byte-compare a raw timestamptz, which renders a `+00` zone
    // suffix on PG). The null-safe `?->` keeps static analysis honest about the `CarbonImmutable|null` property;
    // the instanceof assertions above already prove the values are in fact non-null.
    expect($read->email_verified_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($read->tc_accepted_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($read->privacy_accepted_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($read->email_verified_at?->format('Y-m-d H:i:s'))->toBe('2026-06-18 09:30:00')
        ->and($read->tc_accepted_at?->format('Y-m-d H:i:s'))->toBe('2026-06-18 09:31:00')
        ->and($read->privacy_accepted_at?->format('Y-m-d H:i:s'))->toBe('2026-06-18 09:32:00');
});

it('adds the three acceptance columns to parties_customers on the running engine (incl. PG17)', function () {
    // Schema::hasColumn runs against whatever engine the suite is on — so on the cross-engine PG17 run this
    // positively proves the columns landed on PostgreSQL 17, not only SQLite.
    expect(Schema::hasColumn('parties_customers', 'email_verified_at'))->toBeTrue()
        ->and(Schema::hasColumn('parties_customers', 'tc_accepted_at'))->toBeTrue()
        ->and(Schema::hasColumn('parties_customers', 'privacy_accepted_at'))->toBeTrue();
});
