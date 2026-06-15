<?php

namespace Database\Factories\Parties;

use App\Modules\Parties\Enums\ProducerAgreementStatus;
use App\Modules\Parties\Models\Producer;
use App\Modules\Parties\Models\ProducerAgreement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProducerAgreement>
 */
class ProducerAgreementFactory extends Factory
{
    /**
     * The model lives outside `App\Models`, so the factory declares it explicitly — Factory::modelName()
     * returns `$this->model` directly, and the model points back via its `newFactory()` override.
     *
     * @var class-string<ProducerAgreement>
     */
    protected $model = ProducerAgreement::class;

    /**
     * An agreement born `draft` (this slice transitions nothing) under a parent Producer built by the Producer
     * factory (a WITHIN-module reference). `club_id` defaults to null — a Producer-wide agreement; narrow it
     * explicitly (`->for(Club::factory(), 'club')` or a `club_id` override) for a Club-scoped fixture. The
     * factory bypasses the CreateProducerAgreement action, so it records NO event and runs NO missing-Producer
     * pre-check — a pure fixture. Term dates / settlement cadence are fixed literals (no Faker typing risk).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // a within-module parent Producer (the non-nullable FK is structurally single-parent — § 4.6).
            'producer_id' => Producer::factory(),
            // Producer-wide by default; the optional Club narrowing is set explicitly when wanted.
            'club_id' => null,
            'status' => ProducerAgreementStatus::Draft,
            // fixed agreement-term literals — the `immutable_date` cast parses them to CarbonImmutable.
            'term_start' => '2026-01-01',
            'term_end' => '2026-12-31',
            // the D19 settlement-cadence seam (a free string at launch; a neutral fixture value).
            'settlement_cadence' => 'monthly',
            'version' => 1,
        ];
    }
}
