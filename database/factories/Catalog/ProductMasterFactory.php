<?php

namespace Database\Factories\Catalog;

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Models\ProductMaster;
use App\Platform\I18n\TranslatableText;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductMaster>
 */
class ProductMasterFactory extends Factory
{
    /**
     * The model lives outside `App\Models`, so the factory declares it explicitly — Factory::modelName()
     * returns `$this->model` directly, and the model points back via its `newFactory()` override.
     *
     * @var class-string<ProductMaster>
     */
    protected $model = ProductMaster::class;

    /**
     * The neutral CORE of a `WINE` Master, born `draft` (this slice transitions nothing). The wine
     * attribute set is attached 1:1 in {@see configure()} after the core row exists. The factory bypasses
     * the CreateProductMaster action, so it runs NO dedup and records NO event — it is a pure fixture for
     * standing up prerequisites cheaply (later tasks lean on it for a parent Master).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Built from `@method string` Faker providers (lastName) so the fixture stays typed — randomElement
        // / unique() return `mixed`. Variety is ample; the factory bypasses dedup, so a rare name repeat is
        // harmless. The dedup-sensitive tests pass explicit names rather than leaning on the factory.
        return [
            'name' => 'Château '.fake()->lastName(),
            'product_type' => ProductType::Wine,
            'producer_id' => fake()->numberBetween(1, 9_999),
            'lifecycle_state' => LifecycleState::Draft,
            'version' => 1,
        ];
    }

    /**
     * Attach the 1:1 `WINE` attribute set once the core row exists, unless the caller already supplied one.
     * The attribute row takes the FK from the just-created Master (via the within-module relation), so it
     * never builds a parent — no factory recursion.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (ProductMaster $master): void {
            if ($master->wineAttributes()->doesntExist()) {
                // Typed `@method string` Faker providers (city/country/sentence) — see definition().
                $master->wineAttributes()->create([
                    'appellation' => fake()->city(),
                    'region' => fake()->country(),
                    'winery_story' => TranslatableText::of(['en' => fake()->sentence()]),
                ]);
            }
        });
    }
}
