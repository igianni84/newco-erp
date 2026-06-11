<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('runs the test suite on an in-memory sqlite database', function () {
    expect(config('database.default'))->toBe('sqlite')
        ->and(config('database.connections.sqlite.database'))->toBe(':memory:');
});

it('migrates the full schema on the test database', function () {
    expect(Schema::hasTable('users'))->toBeTrue()
        ->and(Schema::hasTable('cache'))->toBeTrue()
        ->and(Schema::hasTable('jobs'))->toBeTrue();
});
