<?php

namespace Database\Factories\Parties;

use App\Modules\Parties\Enums\PartyType;
use App\Modules\Parties\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * The model lives outside `App\Models`, so the factory declares it explicitly — Factory::modelName()
     * returns `$this->model` directly, and the model points back via its `newFactory()` override.
     *
     * @var class-string<Supplier>
     */
    protected $model = Supplier::class;

    /**
     * A minimal Supplier fixed to the `supplier` marker. The factory bypasses the CreateSupplier action, but
     * the action records no event either, so the two persist an identical minimal Supplier — the factory is a
     * pure fixture for standing up a Supplier cheaply (the integration close, task 6.2, leans on it).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // `company()` is a typed `@method string` Faker provider (the catalog/Producer factories use only
        // annotated providers to stay PHPStan-max clean — randomElement / unique() return `mixed`).
        return [
            'legal_name' => fake()->company(),
            'party_type' => PartyType::Supplier,
        ];
    }
}
