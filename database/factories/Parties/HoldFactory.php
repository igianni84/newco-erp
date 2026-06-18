<?php

namespace Database\Factories\Parties;

use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldStatus;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Models\Hold;
use App\Platform\Events\ActorRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hold>
 */
class HoldFactory extends Factory
{
    /**
     * The model lives outside `App\Models`, so the factory declares it explicitly — Factory::modelName()
     * returns `$this->model` directly, and the model points back via its `newFactory()` override.
     *
     * @var class-string<Hold>
     */
    protected $model = Hold::class;

    /**
     * An `active` `admin` Hold on a Customer scope — a placeable operator Hold born in its `active` state. The
     * factory bypasses the PlaceHold action, so it records NO CustomerHoldPlaced event and runs no actor
     * resolution — it is a pure fixture standing up a bare Hold row cheaply (the lifecycle / read-API tests
     * drive the real Actions). `scope_id` is a plain within-module reference (no FK — design L1); the actor is
     * recorded `system` (the unattended default), the lift columns NULL (un-lifted).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hold_type' => HoldType::Admin,
            'scope_type' => HoldScope::Customer,
            // a plain within-module scope reference — no DB FK (design L1); tests needing a real scope override.
            'scope_id' => fake()->numberBetween(1, 1000),
            'status' => HoldStatus::Active,
            'reason' => null,
            'placed_actor_role' => ActorRole::System,
            'placed_actor_id' => null,
            'lift_reason' => null,
            'lifted_actor_role' => null,
            'lifted_actor_id' => null,
            'lifted_at' => null,
        ];
    }
}
