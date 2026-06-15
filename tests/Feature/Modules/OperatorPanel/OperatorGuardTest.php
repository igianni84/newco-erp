<?php

// Task 2.3 (design D2) — the `operator` session guard wiring in config/auth.php. These assertions pin the
// guard/provider/password-broker config, prove Auth::guard('operator') resolves the Operator provider and
// authenticates an Operator, and report a guest when none is. The application-default-guard end state (the
// bootstrap `web`/`users` shell removed at task 6.1) is pinned by AuthDefaultsTest. The cross-engine close
// (task 6.3) re-runs the suite on PostgreSQL 17.

use App\Modules\OperatorPanel\Models\Operator;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Auth\SessionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('configures the operator guard as a session guard backed by the operators provider', function () {
    expect(config('auth.guards.operator.driver'))->toBe('session')
        ->and(config('auth.guards.operator.provider'))->toBe('operators');
});

it('configures the operators provider as eloquent resolving the Operator model', function () {
    expect(config('auth.providers.operators.driver'))->toBe('eloquent')
        ->and(config('auth.providers.operators.model'))->toBe(Operator::class);
});

it('configures the operators password-reset broker on the shared tokens table', function () {
    expect(config('auth.passwords.operators.provider'))->toBe('operators')
        ->and(config('auth.passwords.operators.table'))->toBe('password_reset_tokens')
        ->and(config('auth.passwords.operators.expire'))->toBe(60)
        ->and(config('auth.passwords.operators.throttle'))->toBe(60);
});

it('builds the operator guard at runtime as a session guard with an eloquent provider', function () {
    // Runtime proof the config translates to real objects (driver=session → SessionGuard,
    // provider=operators driver=eloquent → EloquentUserProvider). That the provider resolves the Operator
    // MODEL is proven declaratively above and functionally below (an authenticated user() is an Operator).
    expect(Auth::guard('operator'))->toBeInstanceOf(SessionGuard::class)
        ->and(Auth::createUserProvider('operators'))->toBeInstanceOf(EloquentUserProvider::class);
});

it('authenticates an Operator on the operator guard', function () {
    $operator = Operator::factory()->create();

    actingAs($operator, 'operator');

    expect(Auth::guard('operator')->check())->toBeTrue()
        ->and(Auth::guard('operator')->id())->toBe($operator->getKey())
        ->and(Auth::guard('operator')->user())->toBeInstanceOf(Operator::class);
});

it('reports a guest on the operator guard when no operator is authenticated', function () {
    expect(Auth::guard('operator')->check())->toBeFalse()
        ->and(Auth::guard('operator')->guest())->toBeTrue()
        ->and(Auth::guard('operator')->user())->toBeNull();
});
