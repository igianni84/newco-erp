<?php

use App\Models\User;
use Database\Seeders\OperatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'operator.name' => 'NewCo Operator',
        'operator.email' => 'operator@newco.test',
        'operator.password' => 'secret-operator-password',
    ]);
});

it('seeds the operator account from the OPERATOR env contract', function () {
    seed(OperatorSeeder::class);

    $operator = User::query()->where('email', 'operator@newco.test')->firstOrFail();

    expect($operator->name)->toBe('NewCo Operator')
        ->and(Hash::check('secret-operator-password', $operator->password))->toBeTrue();
});

it('updates the existing operator instead of duplicating it', function () {
    seed(OperatorSeeder::class);

    config(['operator.name' => 'Renamed Operator']);
    seed(OperatorSeeder::class);

    assertDatabaseCount('users', 1);

    expect(User::query()->where('email', 'operator@newco.test')->firstOrFail()->name)
        ->toBe('Renamed Operator');
});

it('refuses to seed when OPERATOR_PASSWORD is missing', function () {
    config(['operator.password' => '']);

    expect(fn () => seed(OperatorSeeder::class))
        ->toThrow(RuntimeException::class, 'OPERATOR_PASSWORD');

    assertDatabaseCount('users', 0);
});
