<?php

namespace Database\Factories\Parties;

use App\Modules\Parties\Enums\ComplianceReviewReason;
use App\Modules\Parties\Enums\ThresholdKind;
use App\Modules\Parties\Models\ComplianceReview;
use App\Modules\Parties\Models\Customer;
use App\Platform\Money\Currency;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplianceReview>
 */
class ComplianceReviewFactory extends Factory
{
    /**
     * The model lives outside `App\Models`, so the factory declares it explicitly — Factory::modelName()
     * returns `$this->model` directly, and the model points back via its `newFactory()` override.
     *
     * @var class-string<ComplianceReview>
     */
    protected $model = ComplianceReview::class;

    /**
     * An OPEN enhanced-KYC review on a within-module parent Customer (built by its own factory): a single-
     * transaction breach at the €10,000 floor (`1_000_000` minor EUR — DEC-035; invariant 6). The factory
     * bypasses the CreateComplianceReview action, so it records nothing and runs no detection — a pure fixture
     * standing up a review row cheaply (the detection workflow drives the real Action, task 4.2). `resolved_at`
     * is NULL (open — the born state; use {@see resolved()} for a resolved fixture). The enum fields are set as
     * enum instances (the sibling HoldFactory/ClubCreditFactory convention); the casts serialize them on write.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // a within-module parent Customer (the non-nullable FK — design D6).
            'customer_id' => Customer::factory(),
            // the sole reason this change (design D6) — the enhanced-KYC threshold breach.
            'reason' => ComplianceReviewReason::EnhancedKycThreshold,
            // the single-transaction signal (the default breach); override to CumulativeAnnual for the €50k path.
            'threshold_kind' => ThresholdKind::SingleTransaction,
            // the tripping amount as integer minor units + an ISO 4217 code (invariant 6) — €10,000, the
            // single-transaction floor. Two raw scalars (NOT a MoneyCast Money — the event re-assembles it).
            'tripped_amount_minor' => 1_000_000,
            'tripped_currency' => Currency::EUR->value,
            // NULL = open (design D6); the resolved() state stamps it for a resolved fixture.
            'resolved_at' => null,
        ];
    }

    /**
     * A resolved review — `resolved_at` stamped (NULL = open ⇒ non-NULL = resolved, design D6; no FSM). The
     * resolve action is deferred this change, so this state is the fixture the read surface (task 6.1) and the
     * closing integration test (task 7.1) use to stand up a closed queue entry.
     */
    public function resolved(): self
    {
        return $this->state(fn (array $attributes): array => [
            'resolved_at' => CarbonImmutable::now(),
        ]);
    }
}
