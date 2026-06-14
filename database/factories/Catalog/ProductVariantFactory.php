<?php

namespace Database\Factories\Catalog;

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Catalog\Models\ProductVariant;
use App\Platform\I18n\TranslatableText;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * The model lives outside `App\Models`, so the factory declares it explicitly — Factory::modelName()
     * returns `$this->model` directly, and the model points back via its `newFactory()` override.
     *
     * @var class-string<ProductVariant>
     */
    protected $model = ProductVariant::class;

    /**
     * The neutral CORE of a `WINE` Variant, born `draft` (this slice transitions nothing), under a parent
     * Master built by the Master factory (a WITHIN-module reference). The wine attribute set is attached 1:1
     * in {@see configure()} after the core row exists. The factory bypasses the CreateProductVariant action,
     * so it records NO event — it is a pure fixture for standing up prerequisites cheaply (3.3 leans on it for
     * a parent Variant).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // a within-module parent Master (the single FK is structurally single-parent — BR-Identity-2).
            'product_master_id' => ProductMaster::factory(),
            // a TYPE-NEUTRAL identifier — a release label, here a year-like token cast to string (the WINE
            // vintage meaning lives in the attribute set). numberBetween is `@method int`, so the cast is typed.
            'variant_identifier' => (string) fake()->numberBetween(1990, 2025),
            'lifecycle_state' => LifecycleState::Draft,
            'version' => 1,
        ];
    }

    /**
     * Attach the 1:1 `WINE` attribute set once the core row exists, unless the caller already supplied one.
     * The attribute row takes the FK from the just-created Variant (via the within-module relation), so it
     * never builds a parent — no factory recursion.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (ProductVariant $variant): void {
            if ($variant->wineAttributes()->doesntExist()) {
                // Typed `@method` Faker providers (numberBetween: int, sentence: string) — see definition().
                $variant->wineAttributes()->create([
                    'vintage_year' => fake()->numberBetween(1990, 2025),
                    'non_vintage' => false,
                    'tasting_notes' => TranslatableText::of(['en' => fake()->sentence()]),
                ]);
            }
        });
    }
}
