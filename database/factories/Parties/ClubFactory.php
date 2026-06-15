<?php

namespace Database\Factories\Parties;

use App\Modules\Parties\Enums\ClubRegistrationFlowType;
use App\Modules\Parties\Enums\ClubStatus;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Producer;
use App\Platform\Money\Currency;
use App\Platform\Money\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Club>
 */
class ClubFactory extends Factory
{
    /**
     * The model lives outside `App\Models`, so the factory declares it explicitly — Factory::modelName()
     * returns `$this->model` directly, and the model points back via its `newFactory()` override.
     *
     * @var class-string<Club>
     */
    protected $model = Club::class;

    /**
     * A Club born `active` (this slice transitions nothing), under a parent Producer built by the Producer
     * factory (a WITHIN-module reference). The factory bypasses the CreateClub action, so it records NO event
     * and runs NO missing-Producer pre-check — it is a pure fixture for standing up prerequisites cheaply
     * (ProducerAgreement and Profile lean on it for a Club).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // a within-module parent Producer (the non-nullable FK is structurally single-parent — BR-K-Club-1).
            'producer_id' => Producer::factory(),
            // `company()` is a typed `@method string` Faker provider — randomElement/unique return `mixed`.
            'display_name' => fake()->company().' Club',
            'status' => ClubStatus::Active,
            // the per-Club fee as Money (integer minor units + ISO 4217), never a float (invariant 6).
            'fee' => Money::of(25000, Currency::EUR),
            'registration_flow_type' => ClubRegistrationFlowType::OpenRegistration,
            'generates_credit' => true,
            'invite_only' => false,
            'version' => 1,
        ];
    }
}
