<?php

namespace Database\Factories\Catalog;

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Models\Format;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductReference>
 */
class ProductReferenceFactory extends Factory
{
    /**
     * The model lives outside `App\Models`, so the factory declares it explicitly — Factory::modelName()
     * returns `$this->model` directly, and the model points back via its `newFactory()` override.
     *
     * @var class-string<ProductReference>
     */
    protected $model = ProductReference::class;

    /**
     * A Product Reference born `draft` (this slice transitions nothing) over a within-module Variant + Format,
     * each built by its own factory. Both are nested within-module references — recursion-free, because neither
     * the Variant nor the Format factory builds a Product Reference. The factory bypasses the
     * CreateProductReference action, so it records NO event — it is a pure fixture for standing up
     * prerequisites cheaply (task 4.1's Sellable SKU leans on it for a parent PR).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'format_id' => Format::factory(),
            'lifecycle_state' => LifecycleState::Draft,
            'version' => 1,
        ];
    }
}
