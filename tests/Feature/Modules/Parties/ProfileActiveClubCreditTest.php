<?php

use App\Modules\Parties\Enums\ClubCreditState;
use App\Modules\Parties\Models\ClubCredit;
use App\Modules\Parties\Models\Profile;
use App\Platform\Money\Currency;
use App\Platform\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins {@see Profile::activeClubCredit()} (change club-credit task 1.4; design L1) — the at-most-one `active` Club
 * Credit on a Profile, a WITHIN-module `hasOne` SCOPED to `state = 'active'` (both entities Module K, so the
 * cross-module relation ban does not apply; it is the inverse of {@see ClubCredit::profile()}). The structural
 * one-active invariant is the partial unique index `(profile_id) WHERE state = 'active'`, so the relation resolves
 * to a SINGLE credit or `null`. These tests prove: it returns the `active` credit when one exists; it returns
 * `null` for a Profile with no credit, or whose only credit is `redeemed` or `forfeited`; and — the state-scope
 * discriminator — it returns ONLY the `active` credit when a terminal credit also exists for the same Profile
 * (so the scope filters on `state`, not merely "the first credit").
 */
uses(RefreshDatabase::class);

it('resolves the active Club Credit on the Profile (the at-most-one active credit)', function () {
    $profile = Profile::factory()->create();
    $credit = ClubCredit::factory()->create(['profile_id' => $profile->id]);   // active by factory default

    // Re-fetch so the relation lazy-loads from disk, exercising the read path (not an in-memory artifact).
    $active = Profile::findOrFail($profile->id)->activeClubCredit;

    expect($active)->not->toBeNull()
        ->and($active?->is($credit))->toBeTrue()                 // the very credit persisted for this Profile
        ->and($active?->state)->toBe(ClubCreditState::Active);
});

it('returns null when the Profile has no Club Credit at all', function () {
    $profile = Profile::factory()->create();

    expect(Profile::findOrFail($profile->id)->activeClubCredit)->toBeNull();
});

it('returns null when the Profile\'s only credit is redeemed', function () {
    $profile = Profile::factory()->create();
    // A fully-redeemed credit (remaining drained to 0) leaves the `WHERE state = 'active'` predicate, so the
    // scoped relation must not resolve it.
    ClubCredit::factory()->create([
        'profile_id' => $profile->id,
        'state' => ClubCreditState::Redeemed,
        'remaining' => Money::of(0, Currency::EUR),
    ]);

    expect(Profile::findOrFail($profile->id)->activeClubCredit)->toBeNull();
});

it('returns null when the Profile\'s only credit is forfeited', function () {
    $profile = Profile::factory()->create();
    // Forfeiture loses the remaining balance and takes the credit out of `active` — outside the relation scope.
    ClubCredit::factory()->create([
        'profile_id' => $profile->id,
        'state' => ClubCreditState::Forfeited,
    ]);

    expect(Profile::findOrFail($profile->id)->activeClubCredit)->toBeNull();
});

it('resolves only the active credit when a terminal credit also exists for the Profile', function () {
    $profile = Profile::factory()->create();

    // A prior credit that was fully redeemed frees the one-active slot (it is outside the partial index's
    // predicate), so a fresh active credit inserts cleanly alongside it — both belong to the same Profile.
    $redeemed = ClubCredit::factory()->create([
        'profile_id' => $profile->id,
        'state' => ClubCreditState::Redeemed,
        'remaining' => Money::of(0, Currency::EUR),
    ]);
    $active = ClubCredit::factory()->create(['profile_id' => $profile->id]);   // active by factory default

    $resolved = Profile::findOrFail($profile->id)->activeClubCredit;

    // The scope filters on `state` — it returns the active credit, NOT the redeemed one (proving it is not just
    // "the first/any credit for the Profile").
    expect($resolved)->not->toBeNull()
        ->and($resolved?->is($active))->toBeTrue()
        ->and($resolved?->is($redeemed))->toBeFalse()
        ->and($resolved?->state)->toBe(ClubCreditState::Active);
});
