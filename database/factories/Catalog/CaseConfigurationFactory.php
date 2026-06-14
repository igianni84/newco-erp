<?php

namespace Database\Factories\Catalog;

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Models\CaseConfiguration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaseConfiguration>
 */
class CaseConfigurationFactory extends Factory
{
    /**
     * The model lives outside `App\Models`, so the factory declares it explicitly — Factory::modelName()
     * returns `$this->model` directly (verified in vendor), and the model points back via its
     * `newFactory()` override, so `CaseConfiguration::factory()` resolves this class.
     *
     * @var class-string<CaseConfiguration>
     */
    protected $model = CaseConfiguration::class;

    /**
     * A coherent packaging configuration, born `draft` (this slice transitions nothing). Each tuple keeps
     * name / units_per_case / packaging_type consistent so factory-made fixtures read like real cases
     * (loose, OWC, carton). No breakability attribute — that decision lives downstream (BR-RefData-2).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var array{name: string, units_per_case: int, packaging_type: string} $configuration */
        $configuration = fake()->randomElement([
            ['name' => 'Loose', 'units_per_case' => 1, 'packaging_type' => 'loose'],
            ['name' => 'Original Wooden Case (6)', 'units_per_case' => 6, 'packaging_type' => 'owc'],
            ['name' => 'Original Wooden Case (12)', 'units_per_case' => 12, 'packaging_type' => 'owc'],
            ['name' => 'Carton (6)', 'units_per_case' => 6, 'packaging_type' => 'carton'],
            ['name' => 'Carton (12)', 'units_per_case' => 12, 'packaging_type' => 'carton'],
        ]);

        return [
            ...$configuration,
            'lifecycle_state' => LifecycleState::Draft,
            'version' => 1,
        ];
    }
}
