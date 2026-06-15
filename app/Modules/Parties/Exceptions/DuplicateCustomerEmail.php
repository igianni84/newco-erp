<?php

namespace App\Modules\Parties\Exceptions;

use RuntimeException;

/**
 * Raised when a Customer creation uses an email already held by another Customer (parties-core, design D5;
 * party-registry — Requirement: Customer Identity). A Customer's email is GLOBALLY UNIQUE (§ 4.1,
 * BR-K-Identity-1); the `unique` index on `parties_customers.email` is the true structural guard (it would
 * reject the insert with an integrity error), and the `CreateCustomer` in-transaction pre-check throws this
 * first to surface a clean, operator-facing reason ahead of the raw violation (the same belt-and-braces pattern
 * as the {@see MissingClubProducer} / {@see MissingAgreementProducer} guards).
 *
 * The reason is localized through Laravel's translator (CLAUDE.md invariant 12 — no hardcoded user-facing
 * strings). Unlike the sibling guards — which echo the producer-id (an operator reference, NOT PII) — this
 * reason DELIBERATELY does NOT interpolate the email: an email is personal data (GDPR), and an exception
 * message can reach logs. The localized copy names the rule (a globally-unique email), which is fully
 * actionable for the operator who supplied the value; the PII-free discipline that governs the `CustomerCreated`
 * payload thus extends to the rejection copy. The `forEmail()` factory keeps the call-site self-documenting (it
 * names what is duplicated) without exposing the value. `(string)` coerces the translator return (typed `mixed`
 * by Larastan) to the RuntimeException message contract.
 */
class DuplicateCustomerEmail extends RuntimeException
{
    public static function forEmail(string $email): self
    {
        return new self((string) __('parties.customer.duplicate_email'));
    }
}
