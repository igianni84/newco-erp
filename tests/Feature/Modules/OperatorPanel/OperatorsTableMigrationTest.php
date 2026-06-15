<?php

// Task 2.1 (design D1/D3) — the `operators` table is the operator login principal. This guards that the
// migration stands up `operators` with its columns — including the two Filament MFA columns
// (`app_authentication_secret` / `app_authentication_recovery_codes`, opt-in 2FA, design D3) — on a fresh
// database, while the retained `password_reset_tokens` and `sessions` tables remain (the bootstrap `users`
// table was removed at cleanup task 6.1). SQLite here; the cross-engine close re-runs the suite on PostgreSQL 17.

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates the operators table on a fresh database', function () {
    expect(Schema::hasTable('operators'))->toBeTrue();
});

it('carries the operator principal columns', function () {
    expect(Schema::hasColumns('operators', [
        'id',
        'name',
        'email',
        'email_verified_at',
        'password',
        'remember_token',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

it('carries the Filament opt-in 2FA columns (names per the MFA concern traits, design D3)', function () {
    expect(Schema::hasColumn('operators', 'app_authentication_secret'))->toBeTrue()
        ->and(Schema::hasColumn('operators', 'app_authentication_recovery_codes'))->toBeTrue();
});

it('retains the shared auth tables and drops the bootstrap users table (cutover complete, task 6.1)', function () {
    // The operator password broker reuses the generic password_reset_tokens; the session guard reuses
    // sessions — both retained. The bootstrap `users` table was removed wholesale at cleanup task 6.1.
    expect(Schema::hasTable('password_reset_tokens'))->toBeTrue()
        ->and(Schema::hasTable('sessions'))->toBeTrue()
        ->and(Schema::hasTable('users'))->toBeFalse();
});
