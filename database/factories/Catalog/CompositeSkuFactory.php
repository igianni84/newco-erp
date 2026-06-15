<?php

namespace Database\Factories\Catalog;

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Models\CompositeSku;
use App\Modules\Catalog\Models\ProductReference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompositeSku>
 */
class CompositeSkuFactory extends Factory
{
    /**
     * The model lives outside `App\Models`, so the factory declares it explicitly — Factory::modelName()
     * returns `$this->model` directly, and the model points back via its `newFactory()` override.
     *
     * @var class-string<CompositeSku>
     */
    protected $model = CompositeSku::class;

    /**
     * The parent of a Composite SKU born `draft` (this slice transitions nothing). The bundle's constituents
     * are attached 1:1 in {@see configure()} after the parent row exists — a Composite SKU is meaningless
     * without its N ≥ 2 constituents, so the fixture stands up a valid two-constituent bundle by default. The
     * factory bypasses the CreateCompositeSku action, so it runs NO N ≥ 2 guard and records NO event — a pure
     * fixture for standing up prerequisites cheaply.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lifecycle_state' => LifecycleState::Draft,
            'version' => 1,
        ];
    }

    /**
     * Attach two ordered constituent Product References once the parent row exists, unless the caller already
     * supplied constituents. Each PR is built by its own within-module factory (recursion-free — the PR factory
     * never builds a Composite SKU), and takes its position from insertion order.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (CompositeSku $compositeSku): void {
            if ($compositeSku->constituents()->doesntExist()) {
                $compositeSku->constituents()->attach([
                    ProductReference::factory()->create()->id => ['position' => 1],
                    ProductReference::factory()->create()->id => ['position' => 2],
                ]);
            }
        });
    }
}
