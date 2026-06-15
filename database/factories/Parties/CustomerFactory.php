<?php

namespace Database\Factories\Parties;

use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\PartyType;
use App\Modules\Parties\Models\Customer;
use App\Platform\I18n\SupportedLocale;
use App\Platform\Money\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * The model lives outside `App\Models`, so the factory declares it explicitly — Factory::modelName()
     * returns `$this->model` directly, and the model points back via its `newFactory()` override.
     *
     * @var class-string<Customer>
     */
    protected $model = Customer::class;

    /**
     * A Customer born `pending` carrying the `customer` marker (this slice transitions nothing). The factory
     * bypasses the CreateCustomer action, so it records NO CustomerCreated event, runs NO duplicate-email
     * pre-check, and co-provisions NO Account — it is a pure fixture standing up a bare Customer cheaply (the
     * integration close, task 6.2, leans on it; a test wanting the co-provisioned Account drives the action).
     * `originating_club_id` is `null` (born unset — design D6).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // unique() guarantees no email collision across fixtures (the column is globally unique). The proxy
            // returns `mixed`, so coerce to string — the house `(string) __()` idiom (PHPStan-max clean).
            'email' => (string) fake()->unique()->safeEmail(),
            // `name`/`phoneNumber` are typed `@method string` Faker providers (randomElement/unique return mixed).
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            // a fixed DOB literal — the `immutable_date` cast parses it to CarbonImmutable (no Faker typing risk).
            'date_of_birth' => '1990-01-01',
            'party_type' => PartyType::Customer,
            // ISO 4217 / locale PREFERENCE strings (design D9) — the typed-anchor backing values, stored as plain
            // strings (the column is not a cast/enum column).
            'preferred_currency' => Currency::EUR->value,
            'preferred_locale' => SupportedLocale::En->value,
            'status' => CustomerStatus::Pending,
            // born unset — this change provides no mutation surface for the Originating Club (design D6).
            'originating_club_id' => null,
            'version' => 1,
        ];
    }
}
