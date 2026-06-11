<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class OperatorSeeder extends Seeder
{
    /**
     * Seed the operator account from the OPERATOR_* environment variables.
     *
     * Run explicitly via: php artisan db:seed --class=OperatorSeeder
     */
    public function run(): void
    {
        $name = $this->requireValue('operator.name', 'OPERATOR_NAME');
        $email = $this->requireValue('operator.email', 'OPERATOR_EMAIL');
        $password = $this->requireValue('operator.password', 'OPERATOR_PASSWORD');

        User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $password],
        );
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
