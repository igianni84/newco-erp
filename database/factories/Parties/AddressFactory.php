<?php

namespace Database\Factories\Parties;

use App\Modules\Parties\Models\Address;
use App\Modules\Parties\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    /**
     * The model lives outside `App\Models`, so the factory declares it explicitly — Factory::modelName()
     * returns `$this->model` directly, and the model points back via its `newFactory()` override.
     *
     * @var class-string<Address>
     */
    protected $model = Address::class;

    /**
     * A billing Address on a within-module parent Customer (built by its own factory). The factory bypasses the
     * CreateCustomerAddress action, so it runs no country-code boundary validation and records nothing — a pure
     * fixture standing up an Address cheaply. The optional company-billing fields (`company_name` / `vat_id`,
     * DEC-068) are NULL by default (a natural-person billing address); override them (or use {@see forCompany()})
     * for the company-billing case. `country_code` is a valid ISO 3166-1 alpha-2 code (two uppercase letters).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // a within-module parent Customer (the non-nullable FK — design D4).
            'customer_id' => Customer::factory(),
            // personal address fields — line2/region optional (NULL by default). The Faker providers return
            // `mixed`, so coerce to string (the house `(string) fake()` idiom, PHPStan-max clean).
            'line1' => (string) fake()->streetAddress(),
            'line2' => null,
            'locality' => (string) fake()->city(),
            'region' => null,
            'postal_code' => (string) fake()->postcode(),
            // ISO 3166-1 alpha-2 — two uppercase letters; strtoupper guarantees the canonical form regardless of
            // any Faker-locale quirk (the column is a fixed-width code like the ISO 4217 currency codes).
            'country_code' => strtoupper((string) fake()->countryCode()),
            // the optional company-billing affordance (DEC-068 / AC-K-XM-25): NULL for a natural-person address.
            'company_name' => null,
            'vat_id' => null,
        ];
    }

    /**
     * The company-billing variant (DEC-068): an individual collector transacting through their own company for
     * fiscal reasons — the Customer stays the natural person; the company data lives here on the Address.
     */
    public function forCompany(): self
    {
        return $this->state(fn (array $attributes): array => [
            'company_name' => (string) fake()->company(),
            'vat_id' => strtoupper((string) fake()->bothify('??#########')),
        ]);
    }
}
