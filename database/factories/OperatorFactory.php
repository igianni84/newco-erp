<?php

namespace Database\Factories;

use App\Modules\OperatorPanel\Models\Operator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Operator>
 */
class OperatorFactory extends Factory
{
    /**
     * The model lives outside `App\Models` (it is `App\Modules\OperatorPanel\Models\Operator`), so the
     * factory names it explicitly — `Factory::modelName()` returns `$this->model` directly, and the model
     * points back via its `newFactory()` override, so `Operator::factory()` resolves this class.
     *
     * @var class-string<Operator>
     */
    protected $model = Operator::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * A persistable, un-enrolled operator: no 2FA secret or recovery codes (both nullable — 2FA is
     * per-operator opt-in, design D3) and no roles (assigned by the seeders, tasks 5.1/5.2). The password is
     * hashed once and reused (mirrors the framework user factory it replaces); the `password => 'hashed'`
     * cast leaves an already-hashed value untouched.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }
}
