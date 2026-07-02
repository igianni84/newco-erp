<?php

namespace App\Modules\Parties\Models;

use App\Modules\Parties\Enums\ComplianceReviewReason;
use App\Modules\Parties\Enums\ThresholdKind;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\Parties\ComplianceReviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ComplianceReview — one Compliance review-queue work-item, raised when a Customer crosses the enhanced-KYC AML
 * threshold (change parties-enhanced-kyc-threshold, design D6; party-registry — Requirement: Compliance Review
 * Queue). It belongs to EXACTLY ONE Customer via a WITHIN-module FK to `parties_customers`, so the cross-module
 * ban (invariant 10) does not apply: the {@see customer()} relation is a within-module `belongsTo` (mirrors
 * {@see Address} / {@see ClubCredit}). No inverse `hasMany` is added to {@see Customer} — the read surfaces query
 * the queue directly by `customer_id` + `resolved_at IS NULL` (task 6.1), so the relation stays one-directional.
 *
 * The tripping amount follows the money floor (invariant 6 — integer minor units + an ISO 4217 code, NEVER a
 * float): `tripped_amount_minor` (`bigInteger` on disk, cast to `integer` so it reads back a PHP int on BOTH
 * engines — an uncast bigint reads back a numeric STRING on PostgreSQL) + `tripped_currency` (a fixed-width
 * 3-char code, EUR at launch). These are held as TWO raw scalars — NOT a MoneyCast `Money` — deliberately: the
 * currency column is named `tripped_currency` (not the MoneyCast `{key}_currency` convention), and the
 * `CustomerEnhancedKycReviewRequired` event (task 2.3) re-assembles the amount on demand via
 * `Money::of($review->tripped_amount_minor, Currency::of($review->tripped_currency))`. `resolved_at` is nullable
 * (NULL = open); open-vs-resolved is boolean-derivable (`resolved_at IS NOT NULL`), NOT an FSM (§ 9.1 — the
 * `anonymised_at` flag precedent). `reason` / `threshold_kind` cast to their backed enums.
 *
 * Persistence-only by design (design D6): the `CreateComplianceReview` Action (task 4.1) is the SOLE writer — it
 * assembles the attributes internally inside the detection workflow's `DB::transaction`, so `$guarded = []`
 * carries no mass-assignment-from-request risk (mirrors the sibling {@see Address} / {@see ClubCredit} models).
 * The writer is audit-only: the review row records NO domain event of its own — the detection Action records the
 * lone `CustomerEnhancedKycReviewRequired` (design D5). There is no resolve/close write surface this change
 * (deferred — § 9.1, enhanced-KYC is handled operationally); the operator console (task 6.1) reads only.
 *
 * @property int $id
 * @property int $customer_id
 * @property ComplianceReviewReason $reason
 * @property ThresholdKind $threshold_kind
 * @property int $tripped_amount_minor
 * @property string $tripped_currency
 * @property CarbonImmutable|null $resolved_at
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Customer $customer
 */
class ComplianceReview extends Model
{
    /** @use HasFactory<ComplianceReviewFactory> */
    use HasFactory;

    protected $table = 'parties_compliance_reviews';

    /**
     * The CreateComplianceReview action is the only writer; it assembles the attributes internally, so there is
     * no mass-assignment from request input to guard (mirrors the sibling models).
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The Customer the review concerns — a WITHIN-module `belongsTo` (both entities are Module K, so the
     * cross-module relation ban does not apply). The reference is required (a non-nullable FK).
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * The factory lives outside the `Database\Factories\` convention (it is namespaced per module under
     * `Database\Factories\Parties\`), so the model names it explicitly — and the explicit return type lets
     * static analysis infer the factory's model for `ComplianceReview::factory()->create()`.
     */
    protected static function newFactory(): ComplianceReviewFactory
    {
        return ComplianceReviewFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => ComplianceReviewReason::class,
            'threshold_kind' => ThresholdKind::class,
            // bigint → PHP int on both engines (an uncast bigint reads back a numeric STRING on PostgreSQL —
            // knowledge/testing/rules.md #6). The paired `tripped_currency` stays a raw string (no MoneyCast —
            // the event re-assembles the Money from the two scalars, task 2.3).
            'tripped_amount_minor' => 'integer',
            'resolved_at' => 'immutable_datetime',
        ];
    }
}
