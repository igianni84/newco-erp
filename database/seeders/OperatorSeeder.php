<?php

namespace Database\Seeders;

use App\Modules\OperatorPanel\Models\Operator;
use Illuminate\Database\Seeder;
use RuntimeException;

class OperatorSeeder extends Seeder
{
    /**
     * Seed the bootstrap operator account from the OPERATOR_* environment
     * variables and grant it every authority-tier role (design D6).
     *
     * Runs AFTER RoleSeeder (DatabaseSeeder chains them in that order) so the
     * role assignment resolves against existing rows. Idempotent: re-running
     * updates the same operator (keyed on email) and attaches no duplicate
     * roles.
     *
     * Run explicitly via: php artisan db:seed --class=OperatorSeeder
     * (RoleSeeder must have run first).
     */
    public function run(): void
    {
        $name = $this->requireValue('operator.name', 'OPERATOR_NAME');
        $email = $this->requireValue('operator.email', 'OPERATOR_EMAIL');
        $password = $this->requireValue('operator.password', 'OPERATOR_PASSWORD');

        $operator = Operator::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $password],
        );

        // The single launch operator holds every seeded role so it is fully
        // functional. Role *possession* does not bypass the separation-of-duties
        // floor (deferred), which keys on distinct actor identity at the
        // transition, not role count (design D6). The role names resolve on the
        // `operator` guard (the only guard whose provider is the Operator model).
        $operator->assignRole(RoleSeeder::ROLES);
    }

    private function requireValue(string $configKey, string $envName): string
    {
        $value = config($configKey);

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException(
                "{$envName} must be set to seed the operator account (see .env.example).",
            );
        }

        return $value;
    }
}
