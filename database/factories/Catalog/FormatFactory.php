<?php

namespace Database\Factories\Catalog;

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Models\Format;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Format>
 */
class FormatFactory extends Factory
{
    /**
     * The model lives outside `App\Models`, so the factory declares it explicitly — Factory::modelName()
     * returns `$this->model` directly (verified in vendor), and the model points back via its
     * `protected static $factory`, so `Format::factory()` resolves this class.
     *
     * @var class-string<Format>
     */
    protected $model = Format::class;

    /**
     * A coherent WINE bottle-size Format, born `draft` (this slice transitions nothing). Each tuple keeps
     * name / size_label / volume_ml consistent so factory-made fixtures read like real bottle sizes.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var array{name: string, size_label: string, volume_ml: int} $format */
        $format = fake()->randomElement([
            ['name' => 'Half Bottle', 'size_label' => '375ml', 'volume_ml' => 375],
            ['name' => 'Bottle', 'size_label' => '750ml', 'volume_ml' => 750],
            ['name' => 'Magnum', 'size_label' => '1.5L', 'volume_ml' => 1500],
            ['name' => 'Double Magnum', 'size_label' => '3L', 'volume_ml' => 3000],
            ['name' => 'Imperial', 'size_label' => '6L', 'volume_ml' => 6000],
        ]);

        return [
            ...$format,
            'lifecycle_state' => LifecycleState::Draft,
            'version' => 1,
        ];
    }
}
