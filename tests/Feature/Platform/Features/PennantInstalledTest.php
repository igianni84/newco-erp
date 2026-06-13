<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Pennant\Feature;
use Laravel\Pennant\FeatureManager;

uses(RefreshDatabase::class);

// Task 3.1 — proves the Laravel Pennant feature-flag infrastructure is installed
// (design D5; feature-flags delta scenario "The feature-flag infrastructure is
// installed"). RefreshDatabase runs every migration on the in-memory SQLite test DB,
// so hasTable proves the published `features` migration applied cleanly; this same
// file re-runs on the PostgreSQL 17 CI lane, which is how "exists on both engines"
// is exercised. The database-backed store (the published config default) is the
// persistence the operator flip-surface (a later change) writes to — no flag is
// defined or resolved here; that is task 3.2 (the accessor + EXT-1 flag).

it('creates the Pennant features backing table', function () {
    expect(Schema::hasTable('features'))->toBeTrue()
        ->and(Schema::hasColumns('features', ['name', 'scope', 'value']))->toBeTrue();
});

it('resolves the feature-flag service through the container', function () {
    $manager = app(FeatureManager::class);

    expect(app()->bound(FeatureManager::class))->toBeTrue()
        ->and($manager)->toBeInstanceOf(FeatureManager::class)
        ->and(app(FeatureManager::class))->toBe($manager)            // container-managed singleton
        ->and(Feature::getFacadeRoot())->toBe($manager);            // the Feature facade is backed by it
});

it('defaults to the database-backed store on the features table', function () {
    // config/ is outside PHPStan's paths, so this pin is the published config's only
    // static guard (mirrors I18nConfigTest): the table-backed store is the launch
    // default, persisting flag values to the `features` table proven above.
    expect(config('pennant.default'))->toBe('database')
        ->and(config('pennant.stores.database.table'))->toBe('features');
});
