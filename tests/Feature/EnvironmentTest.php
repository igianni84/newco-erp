<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('runs the test suite on the configured database engine', function () {
    $engine = config('database.default');

    // Default lane: in-memory SQLite. The CI pgsql lane overrides DB_CONNECTION (see
    // .github/workflows/ci.yml), so the SQLite specifics are pinned only when SQLite is the active
    // engine; either way we prove the connection is live.
    expect(['sqlite', 'pgsql'])->toContain($engine);

    if ($engine === 'sqlite') {
        expect(config('database.connections.sqlite.database'))->toBe(':memory:');
    }

    expect(DB::connection()->getPdo())->not->toBeNull();
});

it('migrates the full schema on the test database', function () {
    expect(Schema::hasTable('users'))->toBeTrue()
        ->and(Schema::hasTable('cache'))->toBeTrue()
        ->and(Schema::hasTable('jobs'))->toBeTrue();
});
