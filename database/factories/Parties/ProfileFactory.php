<?php

namespace Database\Factories\Parties;

use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    /**
     * The model lives outside `App\Models`, so the factory declares it explicitly — Factory::modelName()
     * returns `$this->model` directly, and the model points back via its `newFactory()` override.
     *
     * @var class-string<Profile>
     */
    protected $model = Profile::class;

    /**
     * A Profile born `applied` (this slice transitions nothing) under a within-module Customer + Club, each
     * built by its own factory. The factory bypasses the CreateProfile action, so it records NO ProfileCreated
     * event and runs NO duplicate pre-check — a pure fixture. `tier` / `role` / `invited_by_customer_id` default
     * to null (single-tier/role at launch, no inviter); set them explicitly when a test needs them.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // within-module parents (both required FKs — § 4.2).
            'customer_id' => Customer::factory(),
            'club_id' => Club::factory(),
            'state' => ProfileState::Applied,
            'tier' => null,
            'role' => null,
            'invited_by_customer_id' => null,
            'version' => 1,
        ];
    }
}
