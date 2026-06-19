<?php

use App\Modules\Parties\Models\Profile;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * Pins the additive demand-side lifecycle schema on `parties_profiles` (parties-membership-suspension task 1.1;
 * design L1; party-registry — Requirements: Profile Lapse and Grace Renewal, Profile Cancellation and
 * Deactivation). It proves the migration adds the two columns — `lapsed_at` (the 30-day-grace anchor, DEC-034) and
 * `cancellation_reason` (the optional Producer-initiated reason CancelProfile records) — as **nullable with no
 * default** (born `NULL`, DEC-071), and that the `Profile` casts expose `lapsed_at` as a typed `immutable_datetime`
 * that round-trips while `cancellation_reason` stays a plain string. The transition Actions (tasks 2.x) are the sole
 * writers; no setter ships in this task, so the columns are operator/test-set.
 *
 * RefreshDatabase migrates the additive migration; the round-trip re-fetches so the assertions exercise the
 * hydration casts, not the in-memory write values. `lapsed_at` is a plain `timestamptz` — no PG-only CHECK — so the
 * value is read back through the `immutable_datetime` cast (testing-rule #4: never byte-compare a raw timestamptz,
 * which renders a `+00` zone suffix on PG; read through the cast and compare formatted Carbon instances).
 */
uses(RefreshDatabase::class);

it('creates a Profile with both lifecycle columns NULL — unset by birth', function () {
    $read = Profile::findOrFail(Profile::factory()->create()->id);

    expect($read->lapsed_at)->toBeNull()
        ->and($read->cancellation_reason)->toBeNull();
});

it('round-trips lapsed_at as an immutable_datetime cast and cancellation_reason as a string', function () {
    // A fixed moment (no microseconds) so the round-trip holds identically on SQLite and PG17.
    $profile = Profile::factory()->create([
        'lapsed_at' => CarbonImmutable::parse('2026-06-19 14:00:00'),
        'cancellation_reason' => 'producer_offboarding',
    ]);

    // Re-fetch so the assertions exercise the hydration casts, not the in-memory write values.
    $read = Profile::findOrFail($profile->id);

    // `lapsed_at` is typed by the cast, then a value round-trip via `->format()` (testing-rule #4: read back
    // THROUGH the immutable_datetime cast and format — never byte-compare a raw timestamptz, which renders a `+00`
    // zone suffix on PG). The null-safe `?->` keeps static analysis honest about the `CarbonImmutable|null`
    // property; the instanceof assertion already proves the value is in fact non-null. `cancellation_reason` is a
    // plain (uncast) nullable string.
    expect($read->lapsed_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($read->lapsed_at?->format('Y-m-d H:i:s'))->toBe('2026-06-19 14:00:00')
        ->and($read->cancellation_reason)->toBe('producer_offboarding');
});

it('adds both lifecycle columns to parties_profiles on the running engine (incl. PG17)', function () {
    // Schema::hasColumn runs against whatever engine the suite is on — so on the cross-engine PG17 run this
    // positively proves the columns landed on PostgreSQL 17, not only SQLite.
    expect(Schema::hasColumn('parties_profiles', 'lapsed_at'))->toBeTrue()
        ->and(Schema::hasColumn('parties_profiles', 'cancellation_reason'))->toBeTrue();
});
