<?php

// Task 5.1 (design D4) — RoleSeeder seeds the operator authority-tier roles
// (Creator / Reviewer / Approver) as BARE roles on the `operator` guard: the
// RBAC *mechanism*, with no permissions and no role→capability map (the policy
// is deferred). The seeder must be idempotent — DatabaseSeeder (task 5.2) chains
// it before OperatorSeeder, and it may run repeatedly across environments.

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

it('seeds Creator, Reviewer and Approver as roles on the operator guard', function () {
    seed(RoleSeeder::class);

    $names = Role::query()
        ->where('guard_name', 'operator')
        ->pluck('name')
        ->sort()
        ->values()
        ->all();

    expect($names)->toBe(['Approver', 'Creator', 'Reviewer']);
});

it('seeds each role with zero permissions — the mechanism, not the policy', function () {
    seed(RoleSeeder::class);

    $roles = Role::query()->where('guard_name', 'operator')->get();

    expect($roles)->toHaveCount(3);

    foreach ($roles as $role) {
        expect($role->permissions)->toHaveCount(0);
    }
});

it('is idempotent — a second run creates no duplicate roles', function () {
    seed(RoleSeeder::class);
    seed(RoleSeeder::class);

    expect(Role::query()->count())->toBe(3);
});
