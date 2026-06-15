<?php

namespace Database\Factories\Parties;

use App\Modules\Parties\Enums\ProducerStatus;
use App\Modules\Parties\Models\Producer;
use App\Platform\I18n\TranslatableText;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producer>
 */
class ProducerFactory extends Factory
{
    /**
     * The model lives outside `App\Models`, so the factory declares it explicitly — Factory::modelName()
     * returns `$this->model` directly, and the model points back via its `newFactory()` override.
     *
     * @var class-string<Producer>
     */
    protected $model = Producer::class;

    /**
     * A Producer born `draft` (this slice transitions nothing). The factory bypasses the CreateProducer
     * action, so it records NO event — it is a pure fixture for standing up prerequisites cheaply (Club and
     * ProducerAgreement lean on it for an operating Producer).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Built from `@method string` Faker providers so the fixture stays typed — randomElement / unique()
        // return `mixed`. There is no Producer uniqueness rule in this slice, so a rare repeat is harmless.
        return [
            'name' => 'Château '.fake()->lastName(),
            'region' => fake()->city(),
            'appellation' => fake()->word(),
            'country' => fake()->country(),
            'description' => TranslatableText::of(['en' => fake()->sentence()]),
            'website' => fake()->url(),
            'status' => ProducerStatus::Draft,
            'version' => 1,
        ];
    }
}
