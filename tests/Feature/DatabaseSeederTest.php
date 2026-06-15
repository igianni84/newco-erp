<?php

// Task 5.2 (design D6) — DatabaseSeeder runs RoleSeeder THEN OperatorSeeder
// (so the bootstrap operator's role assignment resolves against existing rows)
// and no longer seeds the placeholder "Test User" factory account.

use App\Modules\OperatorPanel\Models\Operator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'operator.name' => 'NewCo Operator',
        'operator.email' => 'operator@newco.test',
        'operator.password' => 'secret-operator-password',
    ]);
});

it('seeds roles before the operator so the bootstrap operator holds every role', function () {
    seed(DatabaseSeeder::class);

    $operator = Operator::query()->where('email', 'operator@newco.test')->firstOrFail();

    // The assignment only resolves if RoleSeeder ran first — proves the ordering.
    expect($operator->getRoleNames()->sort()->values()->all())
        ->toBe(['Approver', 'Creator', 'Reviewer']);
});

it('no longer seeds the bootstrap Test User account', function () {
    seed(DatabaseSeeder::class);

    // The dropped factory line created test@example.com on the (transient) users
    // table; DatabaseSeeder now provisions only the operator. (users is removed
    // wholesale at task 6.1, which retires this assertion.)
    assertDatabaseCount('users', 0);
    assertDatabaseMissing('operators', ['email' => 'test@example.com']);
});
