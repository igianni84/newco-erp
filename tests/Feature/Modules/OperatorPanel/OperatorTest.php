<?php

// Task 2.2 (design D1/D2/D3) — the Operator is the operator login principal: an Authenticatable +
// FilamentUser that implements both Filament MFA contracts and exposes spatie's HasRoles, built alongside
// the bootstrap User (removed at task 6.1). These assertions pin that contract surface, the always-true
// panel-access rule, and the encrypted-cast round-trip for the opt-in 2FA columns (stored as ciphertext at
// rest, handed back decrypted). The cross-engine close (task 6.3) re-runs the suite on PostgreSQL 17.

use App\Modules\OperatorPanel\Models\Operator;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Traits\HasRoles;

uses(RefreshDatabase::class);

it('builds a persistable operator via the factory', function () {
    $operator = Operator::factory()->create();

    expect($operator->exists)->toBeTrue()
        ->and(Operator::query()->whereKey($operator->getKey())->exists())->toBeTrue();
});

it('is an Authenticatable and a FilamentUser that can access the admin panel', function () {
    $operator = Operator::factory()->create();

    expect($operator)->toBeInstanceOf(Authenticatable::class)
        ->and($operator)->toBeInstanceOf(FilamentUser::class)
        ->and($operator->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

it('implements both Filament MFA contracts and exposes spatie HasRoles', function () {
    $operator = Operator::factory()->create();

    expect($operator)->toBeInstanceOf(HasAppAuthentication::class)
        ->and($operator)->toBeInstanceOf(HasAppAuthenticationRecovery::class)
        ->and(class_uses_recursive($operator))->toContain(HasRoles::class);
});

it('hashes the password through the hashed cast', function () {
    $operator = Operator::factory()->create(['password' => 'plain-text-secret']);

    expect($operator->password)->not->toBe('plain-text-secret')
        ->and(Hash::check('plain-text-secret', $operator->password))->toBeTrue();
});

it('creates an un-enrolled operator (no 2FA secret or recovery codes) by default', function () {
    $operator = Operator::factory()->create();

    expect($operator->getAppAuthenticationSecret())->toBeNull()
        ->and($operator->getAppAuthenticationRecoveryCodes())->toBeNull();
});

it('reports the operator email as the 2FA holder name', function () {
    $operator = Operator::factory()->create(['email' => 'holder@newco.test']);

    expect($operator->getAppAuthenticationHolderName())->toBe('holder@newco.test');
});

it('round-trips the 2FA app-authentication secret through the encrypted cast', function () {
    $operator = Operator::factory()->create();

    // saveAppAuthenticationSecret() persists internally; reload to prove the cast decrypts on read.
    $operator->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $operator->refresh();

    expect($operator->getAppAuthenticationSecret())->toBe('JBSWY3DPEHPK3PXP');

    // at rest the column holds ciphertext, never the raw secret.
    $raw = DB::table('operators')->where('id', $operator->getKey())->value('app_authentication_secret');
    expect($raw)->not->toBeNull()
        ->and($raw)->not->toBe('JBSWY3DPEHPK3PXP');
});

it('round-trips encrypted recovery codes as an array through the contract accessor', function () {
    $operator = Operator::factory()->create();

    $operator->saveAppAuthenticationRecoveryCodes(['code-1', 'code-2', 'code-3']);
    $operator->refresh();

    expect($operator->getAppAuthenticationRecoveryCodes())->toBe(['code-1', 'code-2', 'code-3']);

    // at rest the column holds ciphertext, not the plain codes.
    $raw = DB::table('operators')->where('id', $operator->getKey())->value('app_authentication_recovery_codes');
    expect($raw)->not->toBeNull()
        ->and($raw)->not->toContain('code-1');
});
