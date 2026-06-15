<?php

// Task 3.1 (design D2/D3) — the /admin panel is cut over to the `operator` session guard with
// login + password reset + opt-in TOTP 2FA (recovery codes), and NO self-registration / email
// verification. These assertions pin that surface: the panel authenticates an `Operator` (not the
// bootstrap `User`, removed at 6.1) through the real Filament Login page against the operator guard,
// guests are bounced to the panel login, and the registration route is absent. The OperatorSeeder is
// still `User`-based until 5.2, so the login tests build the principal with the factory, not the seeder.

use App\Modules\OperatorPanel\Models\Operator;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('redirects unauthenticated visitors to the panel login', function () {
    get('/admin')->assertRedirect('/admin/login');
});

it('authenticates an operator on the operator guard through the panel login', function () {
    Operator::factory()->create(['email' => 'operator@newco.test']);

    Livewire::test(Login::class)
        ->fillForm([
            'email' => 'operator@newco.test',
            'password' => 'password',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect('/admin');

    assertAuthenticated('operator');

    get('/admin')->assertOk();
});

it('lets an authenticated operator reach the dashboard', function () {
    actingAs(Operator::factory()->create(), 'operator');

    get('/admin')->assertOk();
});

it('rejects invalid credentials at the panel login', function () {
    Operator::factory()->create(['email' => 'operator@newco.test']);

    Livewire::test(Login::class)
        ->fillForm([
            'email' => 'operator@newco.test',
            'password' => 'not-the-password',
        ])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    assertGuest('operator');
});

it('authenticates the panel against the operator guard', function () {
    expect(Filament::getPanel('admin')->getAuthGuard())->toBe('operator');
});

it('exposes login and password-reset routes but no registration route', function () {
    expect(Route::has('filament.admin.auth.login'))->toBeTrue()
        ->and(Route::has('filament.admin.auth.password-reset.request'))->toBeTrue()
        ->and(Route::has('filament.admin.auth.register'))->toBeFalse()
        ->and(Filament::getPanel('admin')->hasRegistration())->toBeFalse();
});

it('offers opt-in app-based multi-factor authentication with recovery codes', function () {
    $panel = Filament::getPanel('admin');

    expect($panel->hasMultiFactorAuthentication())->toBeTrue()
        ->and($panel->isMultiFactorAuthenticationRequired())->toBeFalse();

    $appProvider = $panel->getMultiFactorAuthenticationProviders()['app'] ?? null;

    // getMultiFactorAuthenticationProviders() is typed to the MultiFactorAuthenticationProvider
    // contract, which declares no isRecoverable(); a real instanceof narrows it to the concrete
    // AppAuthentication for the recovery-codes proof (phpstan-max forbids assert()/@var narrowing).
    expect($appProvider)->toBeInstanceOf(AppAuthentication::class)
        ->and($appProvider instanceof AppAuthentication && $appProvider->isRecoverable())->toBeTrue();
});
