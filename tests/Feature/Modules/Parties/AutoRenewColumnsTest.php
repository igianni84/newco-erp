<?php

use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Profile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * Pins the additive auto-renewal preference schema (parties-module-k-br-guards task 2.2; Profile-5 / canon
 * MVP-DEC-022 sub-7 / AC-K-BR-Profile-5; ADR 2026-07-07-adopt-mvp-dec-022-club-membership-governance; design
 * D8). Migration 2026_07_07_000002 adds two additive NOT-NULL boolean columns — `parties_clubs.auto_renew_default`
 * (the Club-level default a Profile inherits at creation) and `parties_profiles.auto_renew` (the per-Profile
 * preference) — each with a `true` DB default: the value floor an additive NOT-NULL column needs (SQLite `ALTER
 * TABLE ADD COLUMN` requires one) so every existing insert path that omits the column stays valid until task 4.2
 * wires CreateProfile's explicit inheritance. This task ships the SCHEMA + casts only; the inheritance BEHAVIOUR
 * is task 4.2.
 *
 * RefreshDatabase migrates the additive migration on whatever engine the suite runs (so on the cross-engine PG17
 * run these positively prove the columns landed on PostgreSQL 17, not only SQLite); each round-trip re-fetches so
 * the assertions exercise the hydration `boolean` cast, not the in-memory write value.
 */
uses(RefreshDatabase::class);

it('adds auto_renew_default to parties_clubs, defaulting true and cast to bool', function () {
    // ClubFactory omits auto_renew_default, so the row takes the DB default — proving the NOT-NULL floor keeps a
    // column-omitting insert valid (the reason the additive column carries a default).
    $read = Club::findOrFail(Club::factory()->create()->id);

    expect($read->auto_renew_default)->toBeBool()
        ->and($read->auto_renew_default)->toBeTrue();
});

it('adds auto_renew to parties_profiles, defaulting true and cast to bool', function () {
    // ProfileFactory (like CreateProfile until task 4.2) omits auto_renew, so the row takes the DB default.
    $read = Profile::findOrFail(Profile::factory()->create()->id);

    expect($read->auto_renew)->toBeBool()
        ->and($read->auto_renew)->toBeTrue();
});

it('round-trips an explicit false through the boolean cast on both columns', function () {
    // An explicit `false` write proves each column is a genuine stored boolean, not a constant-true default read.
    $profile = Profile::factory()->create(['auto_renew' => false]);
    $club = Club::factory()->create(['auto_renew_default' => false]);

    // Re-fetch so the assertions exercise the hydration cast, not the in-memory write values.
    expect(Profile::findOrFail($profile->id)->auto_renew)->toBeFalse()
        ->and(Club::findOrFail($club->id)->auto_renew_default)->toBeFalse();
});

it('adds both auto-renew columns to their tables on the running engine (incl. PG17)', function () {
    // Schema::hasColumn runs against whatever engine the suite is on — proving up() landed the columns.
    expect(Schema::hasColumn('parties_clubs', 'auto_renew_default'))->toBeTrue()
        ->and(Schema::hasColumn('parties_profiles', 'auto_renew'))->toBeTrue();
});
