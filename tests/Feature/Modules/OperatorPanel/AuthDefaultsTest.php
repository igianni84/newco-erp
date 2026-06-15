<?php

// Task 6.1 (design D1/D2) — the cutover end state: the bootstrap `User` model and the generic `users` shell
// are gone and the `Operator` principal is the SOLE authenticatable. These assertions pin that the
// application default guard/broker are repointed to the operator, that the operator guard/provider/broker
// resolve correctly, and that the database carries `operators` (not `users`) while the shared `sessions` +
// `password_reset_tokens` tables are retained. The cross-engine close (task 6.3) re-runs on PostgreSQL 17.
//
// FRAMEWORK NOTE — why this does NOT assert `auth.guards.web` / `auth.providers.users` are null: Laravel's
// LoadConfiguration deep-merges the framework's base config/auth.php UNDER the app's for the mergeable keys
// `guards`/`providers`/`passwords` (Bootstrap\LoadConfiguration::mergeableOptions). `array_merge(base, app)`
// can add keys but never remove base ones, so the framework's `web` guard + `users` provider/broker linger
// as INERT defaults regardless of this file. They are unused: the default guard is `operator`, no code reads
// `web` (grep-verified), and `Operator` is the only authenticatable. The orphaned-principal proof is the
// empty old-model/old-factory reference sweep + the dropped `users` table below — not a config-key absence.

use App\Modules\OperatorPanel\Models\Operator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('repoints the application default guard and password broker to the operator', function () {
    expect(config('auth.defaults.guard'))->toBe('operator')
        ->and(config('auth.defaults.passwords'))->toBe('operators');
});

it('wires the operator guard, provider and password broker as the operator end state', function () {
    expect(config('auth.guards.operator.driver'))->toBe('session')
        ->and(config('auth.guards.operator.provider'))->toBe('operators')
        ->and(config('auth.providers.operators.driver'))->toBe('eloquent')
        ->and(config('auth.providers.operators.model'))->toBe(Operator::class)
        ->and(config('auth.passwords.operators.provider'))->toBe('operators');
});

it('authenticates an Operator on the application default guard', function () {
    // No explicit guard → resolves config('auth.defaults.guard') === 'operator'. Functional proof that the
    // default guard is the operator guard backed by the Operator model (stronger than config introspection).
    $operator = Operator::factory()->create();

    actingAs($operator);

    expect(Auth::check())->toBeTrue()
        ->and(Auth::user())->toBeInstanceOf(Operator::class)
        ->and(Auth::id())->toBe($operator->getKey());
});

it('migrates the operators principal table and retains the shared auth tables, with no users table', function () {
    expect(Schema::hasTable('operators'))->toBeTrue()
        ->and(Schema::hasTable('users'))->toBeFalse()
        ->and(Schema::hasTable('sessions'))->toBeTrue()
        ->and(Schema::hasTable('password_reset_tokens'))->toBeTrue();
});
