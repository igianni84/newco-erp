<?php

// Task 1.1 (design D4) — spatie/laravel-permission is the operator RBAC *mechanism*.
// This guards that the published migration stands up the five permission tables on a
// fresh database (SQLite here; the cross-engine close re-runs the suite on PostgreSQL 17),
// guard-aware via `guard_name`, with the teams feature OFF — operator-scoping is achieved
// by seeding roles with an explicit guard, never a team column.

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('migrates the five spatie permission tables on a fresh database', function () {
    expect(Schema::hasTable('roles'))->toBeTrue()
        ->and(Schema::hasTable('permissions'))->toBeTrue()
        ->and(Schema::hasTable('model_has_roles'))->toBeTrue()
        ->and(Schema::hasTable('model_has_permissions'))->toBeTrue()
        ->and(Schema::hasTable('role_has_permissions'))->toBeTrue();
});

it('carries the guard-aware columns each table needs (operator-scoping keys on guard_name)', function () {
    expect(Schema::hasColumns('roles', ['id', 'name', 'guard_name']))->toBeTrue()
        ->and(Schema::hasColumns('permissions', ['id', 'name', 'guard_name']))->toBeTrue()
        ->and(Schema::hasColumns('model_has_roles', ['role_id', 'model_type', 'model_id']))->toBeTrue()
        ->and(Schema::hasColumns('model_has_permissions', ['permission_id', 'model_type', 'model_id']))->toBeTrue()
        ->and(Schema::hasColumns('role_has_permissions', ['permission_id', 'role_id']))->toBeTrue();
});

it('has the teams feature disabled — no team_foreign_key column on roles (design D4)', function () {
    expect(config('permission.teams'))->toBeFalse()
        ->and(Schema::hasColumn('roles', 'team_id'))->toBeFalse();
});

it('uses spatie model defaults (config left at the package Role/Permission models)', function () {
    expect(config('permission.models.role'))->toBe(Role::class)
        ->and(config('permission.models.permission'))->toBe(Permission::class);
});
