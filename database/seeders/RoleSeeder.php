<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * The operator authority-tier role names (Admin_Panel §1.4 / §9.2).
     *
     * These are the RBAC *mechanism* only: seeded as bare roles with NO
     * permissions and NO role→capability map. The authority-tier policy that
     * grants capabilities (and the separation-of-duties floor) is deferred to
     * later changes — see design D4.
     *
     * Public so OperatorSeeder grants the bootstrap operator exactly the roles
     * seeded here (design D6) — a single source of truth for the role set.
     */
    public const ROLES = ['Creator', 'Reviewer', 'Approver'];

    /**
     * Seed the operator roles on the `operator` guard, idempotently.
     */
    public function run(): void
    {
        // Spatie caches roles/permissions; reset it before seeding so a role
        // created here is immediately resolvable downstream — DatabaseSeeder
        // runs OperatorSeeder straight after to assign these roles (design D6).
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES as $name) {
            // firstOrCreate keys on (name, guard_name); the guard is set
            // explicitly so seeding is correct regardless of the app default
            // guard. Re-running matches the existing row — no duplicates.
            Role::firstOrCreate([
                'name' => $name,
                'guard_name' => 'operator',
            ]);
        }
    }
}
