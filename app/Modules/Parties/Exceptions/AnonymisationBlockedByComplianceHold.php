<?php

namespace App\Modules\Parties\Exceptions;

use App\Modules\Parties\Contracts\PartyComplianceStatusReader;
use App\Modules\Parties\Enums\HoldType;
use RuntimeException;

/**
 * Raised when `AnonymiseCustomer` is invoked on a Customer covered by an active `compliance` Hold — the
 * regulatory-retention Hold-precedence gate (parties-anonymisation, design D2; party-registry — Requirement:
 * Anonymisation Hold Precedence; canon MVP-DEC-015 — ADR
 * decisions/2026-07-02-adopt-dec-015-anonymisation-hold-block-set.md; Module K PRD § 8.2 / AC-K-J-9a; invariant 7
 * — compliance/Hold gates every transaction-initiation surface and Holds are never auto-lifted).
 *
 * Canon MVP-DEC-015: ONLY an active `compliance` Hold blocks anonymisation — none of the other seven
 * {@see HoldType} cases does, and there is NO separate `sanctions` Hold type (sanctions
 * live in the `sanctions_status` FSM; a sanctioned Customer whose identifiable data must be retained is gated by
 * Compliance PLACING a `compliance` Hold). The gate reads coverage through the within-module
 * {@see PartyComplianceStatusReader} (never the `Hold` model — the no-model-leak
 * boundary law), and on a block leaves the Customer entirely un-anonymised (no PII overwrite, no `anonymised_at`,
 * no Address overwrite, no event); anonymisation proceeds only once the `compliance` Hold is lifted.
 *
 * The reason is localized through Laravel's translator (invariant 12 — no hardcoded user-facing strings): the
 * English baseline is `parties.anonymisation.blocked_by_compliance_hold`. {@see forCustomer()} interpolates the
 * Customer id — an operator-facing reference (a digit), NOT PII (the sibling {@see IllegalCustomerTransition}
 * `:state` / {@see DuplicateCustomerEmail} PII-omitting discipline): the copy names the rule and interpolates no
 * name/email/phone/date-of-birth, so it stays log-safe. `(string)` coerces the translator return (typed `mixed`
 * by Larastan) to the RuntimeException message contract.
 */
class AnonymisationBlockedByComplianceHold extends RuntimeException
{
    public static function forCustomer(int $customerId): self
    {
        return new self((string) __('parties.anonymisation.blocked_by_compliance_hold', [
            'customer' => $customerId,
        ]));
    }
}
