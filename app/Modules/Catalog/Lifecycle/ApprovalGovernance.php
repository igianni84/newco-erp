<?php

namespace App\Modules\Catalog\Lifecycle;

use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Module;
use App\Platform\Audit\AuditRecord;
use App\Platform\Events\ActorContext;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;

/**
 * The Creator → Reviewer → Approver approval-governance guard layered onto the shared
 * {@see LifecycleTransition} mechanism (catalog-lifecycle-approval, design D5; product-catalog —
 * Requirement: Approval Governance; Module 0 PRD § 4.2). The mechanism calls {@see guard()} on every
 * commercial-impact transition (the {@see LifecycleTransitionType::requiresApprovalGovernance()} set —
 * activate / retire / reopen) BEFORE it writes the state, and {@see requireOperator()} for the
 * reviewed → reviewed rejection decision; a violation throws {@see ApprovalGovernanceViolation} and the
 * mechanism's transaction rolls back, so nothing is recorded.
 *
 * The floor (CLAUDE.md invariant 8; § 4.2): every commercial-impact step requires an authenticated
 * operator principal (`actor_role = newco_ops` with a non-null `actor_id`) — a `system`/null actor cannot
 * satisfy the distinct-actor floor and is rejected, because approval is an inherently human decision. The
 * separation-of-duties distinctness applies to the APPROVAL step (`reviewed → active`): the approver SHALL
 * differ from the creator, and — in the three-step configuration — from the reviewer, and the creator and
 * reviewer SHALL themselves be distinct (three distinct operators). Retire and reopen carry only the
 * operator-principal floor (their distinctness is not part of the activation lineage).
 *
 * The audit trail is the SYSTEM OF RECORD for which actor performed each step (design D5 — no per-entity
 * governance columns): the **creator** is read from the entity's first `domain_events` row (its `*Created`
 * event — an entity has no event before its creation), and the **reviewer** from the latest
 * `draft → reviewed` submit `audit_records` row. A null on either (a system/seed-created entity, or no
 * submit yet) is VACUOUS — distinctness cannot be breached against an absent actor — which keeps a
 * system-seeded entity approvable by a single distinct operator. The acting principal (the approver) is the
 * {@see ActorContext} `actor_id`, guaranteed non-null by the operator-principal floor.
 *
 * The **role count** is operational configuration (`config('catalog.approval.role_count')` ∈ {2, 3},
 * default three — `config/catalog.php`); a non-numeric value normalises back to the three-step default. The
 * knob only widens or narrows the distinctness SET (two-step Creator → Approver checks only approver ≠
 * creator), never relaxes the floor.
 *
 * Boundary-clean (invariant 10): this guard reads ONLY the platform substrate (`domain_events` /
 * `audit_records`) and the `ActorContext` seam — no `App\Modules\Parties\*` (or any other module) type. It
 * takes the entity-type label + the stringified key as values (resolved once by the mechanism), so it never
 * touches the model and carries no key-narrowing of its own.
 */
class ApprovalGovernance
{
    /** The three-step Creator → Reviewer → Approver default when the config knob is absent or non-numeric. */
    private const DEFAULT_ROLE_COUNT = 3;

    public function __construct(private readonly ActorContext $actor) {}

    /**
     * Enforce the approval governance for a commercial-impact transition: the operator-principal floor
     * always, plus — at the approval step (`reviewed → active`) — the separation-of-duties distinctness.
     *
     * @param  string  $entity  the canonical entity-type label (e.g. `ProductMaster`) — matches the audit / event `entity_type`
     * @param  string  $entityId  the entity's stringified primary key (the audit / event `entity_id`)
     *
     * @throws ApprovalGovernanceViolation when the operator-principal floor or the distinctness floor is breached
     */
    public function guard(LifecycleTransitionType $type, string $entity, string $entityId): void
    {
        $approver = $this->operatorPrincipalOrFail($entity);

        // The separation-of-duties distinctness is the APPROVAL step's floor (the Creator → Reviewer →
        // Approver lineage culminates in `reviewed → active`); retire/reopen carry only the operator floor.
        if ($type === LifecycleTransitionType::Activate) {
            $this->assertSeparationOfDuties($entity, $entityId, $approver);
        }
    }

    /**
     * Enforce the operator-principal floor for the reviewed → reviewed rejection decision (§ 4.3): a
     * reviewer or approver — an authenticated operator — rejects; a `system`/null actor cannot.
     *
     * @throws ApprovalGovernanceViolation when there is no authenticated operator principal
     */
    public function requireOperator(string $entity): void
    {
        $this->operatorPrincipalOrFail($entity);
    }

    /**
     * Resolve the acting operator's id, or reject when there is no authenticated operator principal
     * (`actor_role` ≠ `newco_ops`, or a null `actor_id`).
     *
     * @throws ApprovalGovernanceViolation
     */
    private function operatorPrincipalOrFail(string $entity): int
    {
        $actorId = $this->actor->actorId();

        if ($this->actor->role() !== ActorRole::NewcoOps || $actorId === null) {
            throw ApprovalGovernanceViolation::requiresOperatorPrincipal($entity);
        }

        return $actorId;
    }

    /**
     * The separation-of-duties distinctness at the approval step: the approver differs from the creator
     * always, and — at three-step depth — from the reviewer, with the creator and reviewer themselves
     * distinct. Null prior actors are vacuous (an absent actor cannot be matched), so a system/seed-created
     * entity remains approvable by a single distinct operator.
     *
     * @throws ApprovalGovernanceViolation
     */
    private function assertSeparationOfDuties(string $entity, string $entityId, int $approver): void
    {
        $creator = $this->creatorOf($entity, $entityId);

        if ($creator !== null && $approver === $creator) {
            throw ApprovalGovernanceViolation::creatorMayNotApprove($entity);
        }

        // Two-step Creator → Approver: the reviewer role collapses, so only approver ≠ creator is required.
        if ($this->roleCount() < 3) {
            return;
        }

        $reviewer = $this->reviewerOf($entity, $entityId);

        if ($reviewer !== null && $approver === $reviewer) {
            throw ApprovalGovernanceViolation::reviewerMayNotApprove($entity);
        }

        // Three distinct operators are required; if the creator also submitted (reviewer == creator) the
        // three-step floor is unreachable regardless of who approves, so the approval is refused.
        if ($creator !== null && $reviewer !== null && $creator === $reviewer) {
            throw ApprovalGovernanceViolation::insufficientSeparation($entity);
        }
    }

    /** The configured number of distinct approval roles (∈ {2, 3}); a non-numeric value is the three-step default. */
    private function roleCount(): int
    {
        $configured = config('catalog.approval.role_count', self::DEFAULT_ROLE_COUNT);

        return is_numeric($configured) ? (int) $configured : self::DEFAULT_ROLE_COUNT;
    }

    /**
     * The creator's `actor_id` — read from the entity's FIRST `domain_events` row (its `*Created` event; an
     * entity has no event before its creation). Null when the entity was created with no recorded actor
     * (system/seed) or by a path that records no creation event (a test factory): a vacuous creator.
     */
    private function creatorOf(string $entity, string $entityId): ?int
    {
        return $this->normalizeActorId(
            DomainEvent::query()
                ->where('entity_type', $entity)
                ->where('entity_id', $entityId)
                ->orderBy('id')
                ->value('actor_id'),
        );
    }

    /**
     * The reviewer's `actor_id` — read from the latest `draft → reviewed` submit `audit_records` row (the
     * submit verb, recorded by the shared mechanism). Null when no submit has been recorded: a vacuous
     * reviewer.
     */
    private function reviewerOf(string $entity, string $entityId): ?int
    {
        return $this->normalizeActorId(
            AuditRecord::query()
                ->where('module', Module::Catalog->value)
                ->where('entity_type', $entity)
                ->where('entity_id', $entityId)
                ->where('action', 'like', '%.submitted')
                ->orderByDesc('id')
                ->value('actor_id'),
        );
    }

    /**
     * Normalise a raw `actor_id` column read to `?int`. The column is an uncast bigint, so PDO returns it as
     * an `int` on SQLite but as a numeric STRING on PostgreSQL — both must compare `===` against the
     * `ActorContext` int id, so coerce numeric reads to int and map everything else (null / absent) to null.
     */
    private function normalizeActorId(mixed $raw): ?int
    {
        return is_numeric($raw) ? (int) $raw : null;
    }
}
