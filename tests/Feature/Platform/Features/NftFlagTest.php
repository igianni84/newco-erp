<?php

use App\Platform\Features\FeatureFlag;
use App\Platform\Features\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;

uses(RefreshDatabase::class);

// Task 3.2 — the EXT-1 NFT/on-chain feature flag + the reusable accessor (design D5;
// feature-flags delta scenarios "The NFT/on-chain flag is OFF by default", "The flag is
// the single named gate for on-chain surfaces", "A defined feature resolves through the
// accessor"). The flag is defined globally in AppServiceProvider::boot() via
// Features::define(). Non-vacuity: Pennant resolves false for BOTH a defined-and-off flag
// AND an undefined name (DatabaseDriver returns false for unknown features), so each "off"
// assertion is paired with a defined()/cases() membership check — otherwise a typo'd name
// would pass too. The NS-path-as-universal-fallback convention is documented in
// docs/feature-flags.md and doc-pinned by task 5.1 (no serialization workflow exists yet
// to gate, so the "serialization not gated" guard is a naming assertion here).

it('resolves the EXT-1 NFT/on-chain flag OFF by default', function () {
    expect(Feature::defined())->toContain(FeatureFlag::NftOnChain->value)  // genuinely a defined feature…
        ->and(Features::active(FeatureFlag::NftOnChain))->toBeFalse();      // …and off, not undefined-fallthrough
});

it('reports OFF with no operational override stored', function () {
    // The launch state: nothing stored in the features table for this flag, yet it reads
    // off — the resolver's launch default. (count() is evaluated before active() resolves
    // and stores, so the "no override" precondition is captured first.)
    expect(DB::table('features')->where('name', FeatureFlag::NftOnChain->value)->count())->toBe(0)
        ->and(Features::active(FeatureFlag::NftOnChain))->toBeFalse();
});

it('is the single named on-chain gate, and serialization is not gated', function () {
    // Exactly one flag — EXT-1 — and the per-bottle serialization workflow has no flag
    // name (it ships launch-ready, independent of the on-chain workstream).
    expect(FeatureFlag::cases())->toBe([FeatureFlag::NftOnChain])
        ->and(FeatureFlag::NftOnChain->value)->toBe('nft-on-chain')
        ->and(FeatureFlag::tryFrom('serialization'))->toBeNull();
});

it('rejects a flag name outside the defined set (fail-closed registry)', function (string $name) {
    // A name outside the registry cannot be turned into a FeatureFlag, so a call-site can
    // never resolve a magic-string flag.
    expect(FeatureFlag::tryFrom($name))->toBeNull();
})->with([
    'a near-miss on the canonical name' => 'on-chain',
    'the serialization workflow (launch-ready, not gated)' => 'serialization',
    'an unrelated undefined flag' => 'some-future-flag',
    'empty string' => '',
]);

it('throws when constructing a flag from an undefined name', function () {
    $undefined = 'not-a-flag';

    expect(fn () => FeatureFlag::from($undefined))->toThrow(ValueError::class);
});
