<?php

// Task 2.1 (design D1/D3) — the `operators` table is the operator login principal, built ALONGSIDE the
// bootstrap `users` table (cutover discipline D1: `users` is removed at cleanup task 6.1, not here). This
// guards that the new migration stands up `operators` with its columns — including the two Filament MFA
// columns (`app_authentication_secret` / `app_authentication_recovery_codes`, opt-in 2FA, design D3) — on a
// fresh database, while leaving the transient `users`, `password_reset_tokens` and `sessions` tables intact.
// SQLite here; the cross-engine close re-runs the suite on PostgreSQL 17.

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

it('leaves the transient users table and the retained auth tables intact (cutover discipline D1)', function () {
    // `users` is still present this iteration — it is removed only at cleanup task 6.1.
    expect(Schema::hasTable('users'))->toBeTrue()
        // the operator password broker reuses the generic password_reset_tokens; the session guard reuses sessions.
        ->and(Schema::hasTable('password_reset_tokens'))->toBeTrue()
        ->and(Schema::hasTable('sessions'))->toBeTrue();
});
