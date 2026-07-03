<?php

namespace App\Modules\Parties\Events;

use App\Modules\Parties\Models\ComplianceReview;
use App\Modules\Parties\Models\Customer;
use App\Platform\Money\Currency;
use App\Platform\Money\Money;

/**
 * `CustomerEnhancedKycReviewRequired` — recorded when a Customer crosses the enhanced-KYC AML threshold (€10k on a
 * single transaction OR €50k rolling-trailing-12-month cumulative, independent OR — design D3/D8) and is escalated
 * to the Compliance review-queue (parties-enhanced-kyc-threshold, design D5; party-registry — Requirement:
 * Compliance Review Queue). The PII-free signal a future Compliance dashboard / Module-E consumer keys on — the
 * Parties slice of the ~120-event inter-module API (CLAUDE.md: events + contracts are the only cross-module
 * coupling).
 *
 * Recorded by exactly one writer — the forthcoming `EvaluateEnhancedKycThreshold` action (task 4.2) — inside the
 * SAME transaction as the `enhanced_kyc_flag` / `enhanced_kyc_at` stamp, the `CreateComplianceReview` write, and the
 * `RecordCustomerScreening(under_review, aml_threshold)` re-screen, so the escalation and its signal commit or roll
 * back together. The workflow is latched on `enhanced_kyc_flag` (design D1), so it fires AT MOST ONCE per Customer —
 * a re-scan of a still-above-threshold Customer records no second event.
 *
 * DELIBERATE ADDITION over the frozen event-free catalogue: the frozen § 15.6 names no enhanced-KYC event, yet the
 * RM-02 scope calls for one (design D5). It is the audit anchor for a compliance-floor state change in the 10-year
 * store, and it stays a durable record of the AML-threshold origin even after the Customer's
 * `screening_trigger_source` column later reverts to `compliance_ad_hoc` at operator resolution (design D2). This
 * mirrors {@see CustomerAnonymised} (a PII-free event added over the frozen spec's event-free anonymisation).
 *
 * The class is the single source of truth for the event's three contract facets, so the action stays thin and
 * free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Customer;
 *   - {@see payload()} — the PII-free escalation payload.
 */
final class CustomerEnhancedKycReviewRequired
{
    /** The event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'CustomerEnhancedKycReviewRequired';

    /** The envelope `entity_type` for a Customer. */
    public const ENTITY_TYPE = 'Customer';

    /**
     * The escalation payload — STRICT PII-free (decisions/2026-06-12-event-substrate-and-audit-store.md; the 10-year
     * event store holds no personal data). The Customer carries email/name/phone/date_of_birth on the module table
     * (where GDPR erasure operates); this payload DELIBERATELY OMITS all four. It carries only the `customer_id`, the
     * moment the flag was raised (`enhanced_kyc_at`, the ISO-8601 render of the persisted Customer column — a SINGLE
     * source of truth, so the event's moment equals the row's), the `threshold_kind` that tripped (its enum value),
     * and the tripping `amount` re-assembled from the review's two money scalars through {@see Money::toPayload()} to
     * the envelope shape `{minor_units, currency}` (integer minor units + ISO 4217 code, never a float — invariant 6,
     * DEC-169). A consumer needing personal data reads it through a published read contract, never a widened payload.
     *
     * @return array<string, mixed>
     */
    public static function payload(Customer $customer, ComplianceReview $review): array
    {
        return [
            'customer_id' => $customer->id,
            'enhanced_kyc_at' => $customer->enhanced_kyc_at?->toIso8601String(),
            'threshold_kind' => $review->threshold_kind->value,
            'amount' => Money::of($review->tripped_amount_minor, Currency::of($review->tripped_currency))->toPayload(),
        ];
    }
}
