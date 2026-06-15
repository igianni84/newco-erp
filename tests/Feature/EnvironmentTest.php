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
    // `operators` is the operator login principal (operator-auth-foundation 6.1 replaced the bootstrap
    // `users` table). cache + jobs are the retained framework tables.
    expect(Schema::hasTable('operators'))->toBeTrue()
        ->and(Schema::hasTable('cache'))->toBeTrue()
        ->and(Schema::hasTable('jobs'))->toBeTrue();
});

it('pins the PostgreSQL session timezone to UTC', function () {
    // Config-level pin (runs on BOTH lanes): the pgsql connection declares UTC, so the
    // Postgres connector issues `SET TIME ZONE 'UTC'` on connect
    // (PostgresConnector::configureTimezone). Dropping the key fails this loudly.
    // substrate-hardening C6 / design D6.
    expect(config('database.connections.pgsql.timezone'))->toBe('UTC');

    // Behavioural proof on the production-faithful lane: when pgsql is the live engine the
    // session actually reports UTC. SQLite has no session-timezone knob, so it is unaffected
    // (the config pin above still runs there).
    if (DB::getDriverName() === 'pgsql') {
        expect(DB::scalar('show time zone'))->toBe('UTC');
    }
});
