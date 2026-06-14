<?php

namespace Database\Factories\Catalog;

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\SellableSku;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SellableSku>
 */
class SellableSkuFactory extends Factory
{
    /**
     * The model lives outside `App\Models`, so the factory declares it explicitly — Factory::modelName()
     * returns `$this->model` directly, and the model points back via its `newFactory()` override.
     *
     * @var class-string<SellableSku>
     */
    protected $model = SellableSku::class;

    /**
     * A Sellable SKU (Intrinsic) born `draft` (this slice transitions nothing) over a within-module Product
     * Reference + Case Configuration, each built by its own factory. Both are nested within-module references —
     * recursion-free, because neither the PR nor the Case Configuration factory builds a Sellable SKU. The
     * factory bypasses the CreateSellableSku action, so it records NO event — a pure fixture for standing up
     * prerequisites cheaply.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_reference_id' => ProductReference::factory(),
            'case_configuration_id' => CaseConfiguration::factory(),
            'commercial_name' => fake()->sentence(3),
            'marketing_copy' => fake()->paragraph(),
            'lifecycle_state' => LifecycleState::Draft,
            'version' => 1,
        ];
    }
}
