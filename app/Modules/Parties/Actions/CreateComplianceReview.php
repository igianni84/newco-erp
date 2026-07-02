<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Parties\Enums\ComplianceReviewReason;
use App\Modules\Parties\Enums\ThresholdKind;
use App\Modules\Parties\Models\ComplianceReview;
use App\Platform\Money\Money;

/**
 * Creates one {@see ComplianceReview} row on the Compliance review-queue — the SOLE writer of a review-queue
 * work-item (change parties-enhanced-kyc-threshold, design D6; party-registry — Requirement: Compliance Review
 * Queue).
 *
 * A THIN creation action, mirroring the sibling {@see CreateCustomerAddress}: it records NO domain event and opens
 * NO transaction of its own. The lone escalation event `CustomerEnhancedKycReviewRequired` is recorded by the
 * detection action `EvaluateEnhancedKycThreshold` (task 4.2, design D5), which calls this action INSIDE its
 * `DB::transaction` — so this single Eloquent insert simply joins that outer transaction (atomic with the flag-set,
 * the event, and the `under_review` re-screen). Called standalone the insert is atomic on its own; either way it
 * needs no transaction here. It is named `Create*` deliberately so the exhaustive non-`Create*` Action allow-list
 * (`SupplyLifecycleChainTest`) filters it out — raising a review-queue item is not a lifecycle transition.
 *
 * The tripping amount arrives as a {@see Money} and is split into the row's two scalars — `tripped_amount_minor`
 * (integer minor units) + `tripped_currency` (the ISO 4217 code) — honouring the money floor (invariant 6: integer
 * minor units + a currency code, never a float). This is the exact inverse of the event's re-assembly
 * `Money::of($review->tripped_amount_minor, Currency::of($review->tripped_currency))` (task 2.3): the amount is one
 * value at the boundary, two scalars at rest. `reason` is passed in (the enum is extensible — `EnhancedKycThreshold`
 * is its sole case today) rather than hard-coded, keeping the writer reason-agnostic for future review kinds.
 * `resolved_at` is left unset — the row is born open (NULL = open, design D6; there is no resolve write this change).
 */
class CreateComplianceReview
{
    public function handle(
        int $customerId,
        ComplianceReviewReason $reason,
        ThresholdKind $thresholdKind,
        Money $trippedAmount,
    ): ComplianceReview {
        return ComplianceReview::create([
            'customer_id' => $customerId,
            'reason' => $reason,
            'threshold_kind' => $thresholdKind,
            // Money → the row's two scalars (invariant 6): the event re-assembles the Money from these two.
            'tripped_amount_minor' => $trippedAmount->minorUnits,
            'tripped_currency' => $trippedAmount->currency->value,
            // `resolved_at` is left unset — the row is born open (NULL = open, design D6).
        ]);
    }
}
