<?php

namespace App\Modules\Parties\Exceptions;

use RuntimeException;

/**
 * Raised when Producer activation fails the separation-of-duties floor on ActivateProducer (change
 * parties-producer-approval-sod, design D1/D4; party-registry — Requirement: Producer Lifecycle; Module K
 * PRD § 4.4 / AC-K-J-10; Admin Panel PRD § 5.2). ProducerApprovalGovernance (task 1.3) guards the
 * `draft → active` transition at the spec-admissible 2-step Creator → Approver depth; a violation is thrown
 * before the write, so the Producer's `status`, the audit trail and the event log are left unchanged.
 *
 * Two failure modes, each with a named factory resolving localized copy (CLAUDE.md invariant 12 — no
 * hardcoded user-facing strings) from the `approval` group of `lang/en/parties.php`:
 *   - {@see requiresOperatorPrincipal()} — activation was attempted with no authenticated operator principal
 *     (`actor_role` ≠ `newco_ops`, or a null `actor_id`): a `system`/null actor cannot satisfy the
 *     distinct-actor floor, so it is refused — this closes the verdict's "System actor accepted" hole.
 *   - {@see creatorMayNotApprove()} — the separation-of-duties floor was breached: the operator activating
 *     the Producer is the one who created it (the `ProducerCreated` actor read from `domain_events`).
 *
 * Mirrors Catalog's ApprovalGovernanceViolation MINUS the reviewer leg — the Producer FSM is linear
 * (`draft → active → retired`, no `reviewed` state), so there is no reviewer source and hence no
 * reviewer-self-approval / insufficient-separation factory. No `Catalog\*` symbol is imported (CLAUDE.md
 * invariant 10 — module boundary); this is a Parties-local copy of the same shape.
 *
 * The copy names only the violated RULE and the `:entity` type label (never PII) — the acting principal
 * lives on the event/audit row (`actor_role`/`actor_id`), the system of record for who performed each step.
 * `(string)` coerces the translator return (typed `mixed` by Larastan) to the RuntimeException message
 * contract, exactly as the sibling {@see IllegalProducerTransition} guards do.
 */
class SeparationOfDutiesViolation extends RuntimeException
{
    public static function requiresOperatorPrincipal(string $entity): self
    {
        return self::build('requires_operator_principal', $entity);
    }

    public static function creatorMayNotApprove(string $entity): self
    {
        return self::build('creator_may_not_approve', $entity);
    }

    private static function build(string $key, string $entity): self
    {
        return new self((string) __("parties.approval.{$key}", ['entity' => $entity]));
    }
}
