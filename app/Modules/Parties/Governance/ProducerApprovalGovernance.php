<?php

namespace App\Modules\Parties\Governance;

use App\Modules\Parties\Exceptions\SeparationOfDutiesViolation;
use App\Platform\Events\ActorContext;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;

/**
 * The separation-of-duties floor on Producer activation (change parties-producer-approval-sod, design
 * D1–D4/D6; party-registry — Requirement: Producer Lifecycle; Module K PRD § 4.4 / AC-K-J-10; Admin Panel
 * PRD § 5.2). `ActivateProducer` calls {@see guard()} inside its
 * transaction, after the locked from-state assert and BEFORE the KYC gate and the write (design D6 order:
 * from-state → operator-principal → distinct-actor → KYC → write); a violation throws
 * {@see SeparationOfDutiesViolation} before any write, so the Producer's `status` and the event log are left
 * unchanged.
 *
 * The floor is the spec-admissible 2-step Creator → Approver depth (design D1) — the exact path Catalog's
 * `ApprovalGovernance` reduces to at `role_count < 3`. It has two legs:
 *   - Operator-principal (design D4; CLAUDE.md invariant 8): activation requires an authenticated
 *     `newco_ops` operator with a non-null `actor_id` — a `system`/null actor cannot satisfy a distinct-actor
 *     floor and is rejected (`requiresOperatorPrincipal`), closing the verdict's "System actor accepted"
 *     hole. Checked FIRST, so a system actor is rejected on this leg even when a creator lineage exists.
 *   - Distinct-actor (design D1/D3): the approver SHALL differ from the Producer's CREATOR — the actor on the
 *     earliest `domain_events` row for the entity (its `ProducerCreated` event). A null creator (a
 *     system/seed-created Producer, or a `factory()->create()` row that records no event) is VACUOUS —
 *     distinctness cannot be breached against an absent actor — so such a Producer stays activatable by any
 *     single operator principal.
 *
 * Mirrors Catalog's guard MINUS the reviewer leg and the review-freshness block-gate: the Producer FSM is
 * linear (`draft → active → retired`, no `reviewed` state and no `.submitted` audit action), so there is no
 * reviewer source in Parties and no rejection-pending state to gate. Only the creator is persisted and
 * recoverable, hence only the creator-distinctness check.
 *
 * Boundary-clean (CLAUDE.md invariant 10): this guard reads ONLY the platform substrate — the `domain_events`
 * log via {@see DomainEvent} and the {@see ActorContext} seam — and its own module's
 * {@see SeparationOfDutiesViolation}. No `App\Modules\Catalog\*` (or any other module's) symbol is imported;
 * this is a deliberate, minimal Parties-local copy of the same shape (design D2 — platform extraction is a
 * future option, not a launch need). The guard takes the entity-type label + key as values (the caller
 * passes `'Producer'`), never touching the Producer model, so it carries no key-narrowing of its own.
 */
class ProducerApprovalGovernance
{
    public function __construct(private readonly ActorContext $actor) {}

    /**
     * Enforce the 2-step separation-of-duties floor for a Producer activation: the operator-principal floor
     * always, then the distinct-actor check (the approver differs from the creator; a null creator is
     * vacuous).
     *
     * @param  string  $entityType  the canonical entity-type label (`Producer`) — matches the event `entity_type`
     * @param  int|string  $entityId  the Producer's primary key (matched against the event `entity_id`, a string)
     *
     * @throws SeparationOfDutiesViolation when there is no authenticated operator principal, or the approver is the creator
     */
    public function guard(string $entityType, int|string $entityId): void
    {
        $approver = $this->operatorPrincipalOrFail($entityType);

        $creator = $this->creatorOf($entityType, (string) $entityId);

        if ($creator !== null && $approver === $creator) {
            throw SeparationOfDutiesViolation::creatorMayNotApprove($entityType);
        }
    }

    /**
     * Resolve the acting operator's id, or reject when there is no authenticated operator principal
     * (`actor_role` ≠ `newco_ops`, or a null `actor_id`). Direct mirror of Catalog's
     * `operatorPrincipalOrFail()` (design D4).
     *
     * @throws SeparationOfDutiesViolation
     */
    private function operatorPrincipalOrFail(string $entity): int
    {
        $actorId = $this->actor->actorId();

        if ($this->actor->role() !== ActorRole::NewcoOps || $actorId === null) {
            throw SeparationOfDutiesViolation::requiresOperatorPrincipal($entity);
        }

        return $actorId;
    }

    /**
     * The creator's `actor_id` — read from the entity's FIRST `domain_events` row (its `ProducerCreated`
     * event; an entity has no event before its creation). Null when the Producer was created with no recorded
     * actor (system/seed) or by a path that records no creation event (a test factory): a vacuous creator.
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
     * Normalise a raw `actor_id` column read to `?int`. The column is an uncast bigint, so PDO returns it as
     * an `int` on SQLite but as a numeric STRING on PostgreSQL — both must compare `===` against the
     * {@see ActorContext} int id, so coerce numeric reads to int and map everything else (null / absent) to
     * null. A private copy (not a Catalog import — invariant 10).
     */
    private function normalizeActorId(mixed $raw): ?int
    {
        return is_numeric($raw) ? (int) $raw : null;
    }
}
