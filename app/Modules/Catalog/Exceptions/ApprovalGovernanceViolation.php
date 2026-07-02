<?php

namespace App\Modules\Catalog\Exceptions;

use RuntimeException;

/**
 * Raised when a commercial-impact lifecycle transition fails the approval governance guard (the
 * Creator → Reviewer → Approver separation-of-duties floor OR the review-freshness block-gate)
 * (catalog-lifecycle-approval, design D5; catalog-review-freshness-resubmit RM-06, design D1;
 * product-catalog — Requirement: Approval Governance; Module 0 PRD § 4.2/4.3). The transition is rejected at
 * the workflow level and its transaction rolls back, so the entity's `lifecycle_state`, the audit trail and
 * the event log are left unchanged.
 *
 * Failure modes, each with a named factory resolving localized copy (CLAUDE.md invariant 12 — no hardcoded
 * user-facing strings) from the `approval` group of `lang/en/catalog.php` — EXCEPT the block-gate, whose
 * reason lives in the `lifecycle` group (see its factory docblock):
 *   - {@see requiresOperatorPrincipal()} — the step was attempted with no authenticated operator principal
 *     (`actor_role` ≠ `newco_ops`, or a null `actor_id`): a `system`/null actor cannot satisfy the
 *     distinct-actor floor, so approval — an inherently human decision — is refused.
 *   - the separation-of-duties floor was breached at the approval step (`reviewed → active`):
 *     {@see creatorMayNotApprove()} / {@see reviewerMayNotApprove()} (self-approval by the creator or, in the
 *     three-step configuration, the reviewer) and {@see insufficientSeparation()} (three-step depth but the
 *     creator and reviewer were the same operator, so three distinct actors are unreachable).
 *   - {@see activationBlockedByPendingRejection()} — the review-freshness block-gate: `activate` is refused
 *     while the entity's latest governance decision is an un-remediated rejection (re-submit re-arms review).
 *
 * The copy names only the violated RULE and the `:entity` type label (never PII) — the acting principal lives
 * on the audit row (`actor_role`/`actor_id`), which is the system of record for who performed each step
 * (design D5). One parameterized exception serves all seven spine entities, mirroring
 * {@see IllegalLifecycleTransition} — the governance is uniform, so the entity name is a factory parameter.
 * `(string)` coerces the translator return (typed `mixed` by Larastan) to the RuntimeException message contract.
 */
class ApprovalGovernanceViolation extends RuntimeException
{
    public static function requiresOperatorPrincipal(string $entity): self
    {
        return self::build('requires_operator', $entity);
    }

    public static function creatorMayNotApprove(string $entity): self
    {
        return self::build('self_approval_creator', $entity);
    }

    public static function reviewerMayNotApprove(string $entity): self
    {
        return self::build('self_approval_reviewer', $entity);
    }

    public static function insufficientSeparation(string $entity): self
    {
        return self::build('insufficient_separation', $entity);
    }

    /**
     * The review-freshness block-gate (RM-06 / canon MVP-DEC-019): `activate` (`reviewed → active`) is refused
     * while the entity's latest governance decision is an un-remediated rejection — the Creator must re-submit
     * for review first. Thrown from this class (not a dedicated exception) so it surfaces through the console
     * kit's `surfaceLifecycleOutcome` path like the SoD floor, but its reason lives in the `lifecycle` group of
     * `lang/en/catalog.php` (not `approval`), because the rule it names is a lifecycle-flow / review-freshness
     * rule rather than a separation-of-duties one — hence it bypasses {@see build()}'s `approval` prefix. :entity
     * is the entity-type name (never PII); the offending state is always `reviewed` and the acting principal
     * lives on the audit row.
     */
    public static function activationBlockedByPendingRejection(string $entity): self
    {
        return new self((string) __('catalog.lifecycle.activation_blocked_by_pending_rejection', ['entity' => $entity]));
    }

    private static function build(string $key, string $entity): self
    {
        return new self((string) __("catalog.approval.{$key}", ['entity' => $entity]));
    }
}
