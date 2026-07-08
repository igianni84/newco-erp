<?php

namespace App\Modules\Parties\Exceptions;

use App\Modules\Parties\Enums\ClubRegistrationFlowType;
use App\Modules\Parties\Models\Club;
use RuntimeException;

/**
 * Raised when a {@see Club} is persisted with a `registration_flow_type` that is NOT selectable at launch —
 * today exactly `open_registration` (change parties-module-k-br-guards, task 4.3; design D6; party-registry —
 * Requirement: Club Registration Flow and Onboarding Channel; BR-K-Club-6 / canon MVP-DEC-022 — ADR
 * 2026-07-07-adopt-mvp-dec-022-club-membership-governance). `open_registration` (auto-join without approval)
 * is CARRIED LATENT in {@see ClubRegistrationFlowType} but must never reach the database, because it would
 * contradict the mandatory producer-approval write (DEC-069 — approval = charge = activation is mandatory for
 * every flow; no value auto-approves). The three launch-selectable channels are `application_with_approval`
 * (the default), `invitation_only`, and `link_onboarding`.
 *
 * Enforced by the {@see Club} model's `saving` guard so the invariant holds on EVERY write path (the CreateClub
 * action, the factory, the seeder, and any future update writer) — the spec's "a Club create or update
 * attempts to set open_registration → rejected". Unlike a business-rule guard on entity state (e.g.
 * {@see ClubNotAcceptingMemberships}), this is a value-domain reject: the operand is outside the
 * launch-selectable subset. The reason is localized through Laravel's translator (CLAUDE.md invariant 12); like
 * {@see InvalidSettlementCadence} — and unlike {@see DuplicateCustomerEmail}, which omits the PII email — a
 * registration-flow token is NOT personal data, so the offending value IS interpolated to make the rejection
 * self-documenting. `(string)` coerces the translator return (typed `mixed` by Larastan) to the message contract.
 */
class ClubRegistrationFlowNotSelectable extends RuntimeException
{
    public static function forFlow(string $flow): self
    {
        return new self((string) __('parties.club.registration_flow_not_selectable', ['flow' => $flow]));
    }
}
