<?php

// Task 5.2 (design D6) — OperatorSeeder seeds the bootstrap operator as an
// Operator (cut over from the bootstrap User) from the OPERATOR_* env contract
// and grants it every authority-tier role. RoleSeeder is the documented
// precondition (DatabaseSeeder chains it first) so the role assignment resolves.

use App\Modules\OperatorPanel\Models\Operator;
use Database\Seeders\OperatorSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'operator.name' => 'NewCo Operator',
        'operator.email' => 'operator@newco.test',
        'operator.password' => 'secret-operator-password',
    ]);

    // OperatorSeeder grants the bootstrap operator the seeded roles, so the
    // roles must exist first — exactly the order DatabaseSeeder guarantees (D6).
    seed(RoleSeeder::class);
});

it('seeds the operator account from the OPERATOR env contract', function () {
    seed(OperatorSeeder::class);

    $operator = Operator::query()->where('email', 'operator@newco.test')->firstOrFail();

    expect($operator->name)->toBe('NewCo Operator')
        ->and(Hash::check('secret-operator-password', $operator->password))->toBeTrue();
});

it('grants the bootstrap operator every authority-tier role', function () {
    seed(OperatorSeeder::class);

    $operator = Operator::query()->where('email', 'operator@newco.test')->firstOrFail();

    expect($operator->getRoleNames()->sort()->values()->all())
        ->toBe(['Approver', 'Creator', 'Reviewer'])
        ->and($operator->roles->pluck('guard_name')->unique()->all())
        ->toBe(['operator']);
});

it('seeds an operator that authenticates on the operator guard', function () {
    seed(OperatorSeeder::class);

    $operator = Operator::query()->where('email', 'operator@newco.test')->firstOrFail();

    actingAs($operator, 'operator');

    expect(Auth::guard('operator')->check())->toBeTrue()
        ->and(Auth::guard('operator')->id())->toEqual($operator->getKey());
});

it('updates the existing operator instead of duplicating it, with no duplicate roles', function () {
    seed(OperatorSeeder::class);

    config(['operator.name' => 'Renamed Operator']);
    seed(OperatorSeeder::class);

    assertDatabaseCount('operators', 1);
    // Three role grants for the one operator — re-seeding attaches no duplicates.
    assertDatabaseCount('model_has_roles', 3);

    expect(Operator::query()->where('email', 'operator@newco.test')->firstOrFail()->name)
        ->toBe('Renamed Operator');
});

it('refuses to seed when OPERATOR_PASSWORD is missing', function () {
    config(['operator.password' => '']);

    expect(fn () => seed(OperatorSeeder::class))
        ->toThrow(RuntimeException::class, 'OPERATOR_PASSWORD');

    assertDatabaseCount('operators', 0);
});
