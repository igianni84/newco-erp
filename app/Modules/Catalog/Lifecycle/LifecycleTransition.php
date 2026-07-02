<?php

namespace App\Modules\Catalog\Lifecycle;

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Module;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * The shared lifecycle-transition mechanism — ONE place that drives the uniform spine FSM
 * `draft → reviewed → active → retired` (+ the `retired → reviewed` reopen) for all seven Module 0 spine
 * entities (design D1/D2; product-catalog — Requirement: Product Lifecycle State Machine).
 *
 * Per design D1 the seven entities share the IDENTICAL FSM, from-state guard, governance and audit shape —
 * they differ only in their activation gate and the event they record (later tasks) — so the generic
 * mechanics live here once instead of ~35 near-identical Action classes. Per-entity thin Actions
 * (`SubmitProductMasterForReview`, `RejectProductMasterReview` etc.) call {@see transition()} / {@see reject()}
 * with the model, the {@see LifecycleTransitionType} and the canonical entity label. This mechanism is the
 * SOLE writer of `lifecycle_state` (the immutability discipline the spine rests on); the Models stay
 * persistence-only.
 *
 * Race-safe from-state guard (design D2): inside ONE {@see DB::transaction} it re-reads the target row
 * `->lockForUpdate()` (a real row lock on PostgreSQL that serialises concurrent attempts; a harmless no-op
 * under SQLite's single writer — the from-state assert carries correctness either way), asserts the LOCKED
 * row is in the transition's required from-state, then writes the to-state and records ONE audit row. The
 * from-state is read from the locked re-read, never the caller's (possibly stale) in-memory instance, so a
 * transition decided on a stale snapshot is rejected. An out-of-state call throws
 * {@see IllegalLifecycleTransition} and the transaction rolls back, leaving the row, the audit log and the
 * event log unchanged.
 *
 * Approval governance (design D5; catalog-lifecycle-approval task 2.3): every COMMERCIAL-IMPACT step (the
 * {@see LifecycleTransitionType::requiresApprovalGovernance()} set — activate / retire / reopen) passes the
 * {@see ApprovalGovernance} guard AFTER the from-state assert and BEFORE the write — the operator-principal
 * floor always, plus the Creator → Reviewer → Approver separation-of-duties distinctness at the approval
 * step. A governance breach throws {@see ApprovalGovernanceViolation} and the transaction rolls back, so the
 * rejected step records nothing. The guard reads its lineage (the `*Created` event actor, the submit audit
 * actor) inside this transaction, so the decision and the audit row share one consistent snapshot. The
 * reviewed → reviewed rejection decision ({@see reject()}, § 4.3) carries only the operator-principal floor.
 *
 * Every step is audited (CLAUDE.md invariant 8): an `audit_records` row in the SAME transaction as the state
 * write (the recorder's open-transaction guard makes write + audit atomic), with the before/after
 * `{lifecycle_state}` snapshot and the acting principal resolved from the {@see ActorContext} seam. The
 * `draft → reviewed` and `retired → reviewed` checkpoints are audit-ONLY (no domain event, Module 0 PRD
 * § 14.2).
 *
 * Two per-transition seams the activation/retirement Actions plug into {@see transition()} (design D6/D7/D9;
 * catalog-lifecycle-approval task 3.2, extended per-entity by 4.x/5.x):
 *   - the optional `$gate` closure — a transition precondition evaluated AFTER the governance guard and
 *     BEFORE the write; it throws a localized exception to reject, so a gated-out transition records nothing.
 *     Two uses: an ACTIVATION gate (the Producer gate for a Product Master via {@see ProducerActivationGate};
 *     the parent-active cascade for a child) and the RETIREMENT reference-integrity guard (design D8 — reject
 *     a single-entity retire of a Product Reference / Case Configuration still referenced by an `active` SKU).
 *     Submit/reopen, the standalone activations and the guard-free operator-driven cascade retire pass null;
 *   - the optional `$event` closure — the transition's verbatim `*Activated`/`*Retired` domain-event intent
 *     (name + PII-free payload), recorded through the platform {@see DomainEventRecorder} AFTER the state
 *     write, inside this same transaction (§14.1 / invariant 4 — the transactional outbox), so its payload
 *     reflects the to-state. The audit-only checkpoints pass null.
 * The mechanism owns the event envelope (module `catalog`, the {@see ActorContext} principal, the entity
 * type/id resolved once) so the envelope can never drift between the audit row and the event, and the Action
 * stays thin and magic-string-free (it supplies only its entity-specific gate + event class).
 */
class LifecycleTransition
{
    /**
     * The authorization basis stamped on every lifecycle audit row: the step was performed under the
     * catalog-lifecycle management authority. The acting principal is recorded separately as
     * `actor_role`/`actor_id`.
     */
    private const AUTHORIZATION_BASIS = 'catalog-lifecycle';

    /** The review-rejection verb (the audit action segment) and the `decision` recorded in the after-snapshot (§ 4.3). */
    private const DECISION_REJECTED = 'rejected';

    /** The review re-submit verb (the audit action segment) and the `decision` in the after-snapshot (§ 4.3; RM-06 — the twin of {@see reject()} that re-arms review after a rejection). */
    private const DECISION_RESUBMITTED = 'resubmitted';

    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly ActorContext $actor,
        private readonly ApprovalGovernance $governance,
        private readonly DomainEventRecorder $eventRecorder,
    ) {}

    /**
     * Drive $model through $type, recording the step in the audit trail (and, on Activate/Retire, the
     * supplied domain event); returns the transitioned model (re-read under the row lock, reflecting the
     * to-state).
     *
     * @template TModel of Model&HasLifecycleState
     *
     * @param  TModel  $model  the spine entity to transition (this mechanism is its sole `lifecycle_state` writer)
     * @param  string  $entity  the canonical entity-type label (e.g. `ProductMaster`) for the audit/event record + the rejection
     * @param  (Closure(TModel): void)|null  $gate  a transition precondition (design D6/D7/D8) evaluated after the governance guard, before the write — it throws a localized exception to reject. Two uses: an activation gate (the Producer gate for a Master; the parent-active cascade for a child) and the retirement reference-integrity guard (a PR / Case Configuration referenced by an `active` SKU). Null for transitions with no gate (submit/reopen, standalone activations, the guard-free cascade retire).
     * @param  (Closure(TModel): array{name: string, payload: array<string, mixed>})|null  $event  the transition's domain-event intent (design D9), recorded AFTER the state write inside this transaction so the payload reflects the to-state. Null for the audit-only checkpoints (submit/reopen).
     * @return TModel
     *
     * @throws IllegalLifecycleTransition when the locked row is not in the transition's required from-state
     * @throws ApprovalGovernanceViolation when a commercial-impact step fails the approval governance
     */
    public function transition(
        Model&HasLifecycleState $model,
        LifecycleTransitionType $type,
        string $entity,
        ?Closure $gate = null,
        ?Closure $event = null,
    ): Model&HasLifecycleState {
        return DB::transaction(function () use ($model, $type, $entity, $gate, $event) {
            $this->lockAndRefresh($model);
            $entityId = $this->entityId($model);

            $from = $model->lifecycleState();

            if ($from !== $type->from()) {
                throw $type->rejection($from, $entity);
            }

            // Approval governance on every commercial-impact step (design D5): the operator-principal floor
            // always, plus the separation-of-duties distinctness at the approval step. Evaluated AFTER the
            // from-state assert and BEFORE the write, inside this transaction, so a rejected step records
            // nothing and the guard reads a snapshot consistent with the locked row.
            if ($type->requiresApprovalGovernance()) {
                $this->governance->guard($type, $entity, $entityId);
            }

            // Activation precondition gate (design D6/D7): the Producer gate for a Master, the parent-active
            // cascade for a child. Evaluated after governance and before the write, so a gated-out activation
            // records neither audit nor event. Only activation Actions pass a gate; the others pass null.
            if ($gate !== null) {
                $gate($model);
            }

            $to = $type->to();
            $model->update(['lifecycle_state' => $to]);

            $this->recordAudit(
                $model,
                $entityId,
                $type->auditVerb(),
                $entity,
                ['lifecycle_state' => $from->value],
                ['lifecycle_state' => $to->value],
            );

            // Domain event (design D9; § 14.1 / invariant 4 — the transactional outbox), recorded AFTER the
            // state write inside this same transaction so the payload reflects the to-state. Activate records
            // the entity's *Activated, Retire its *Retired; the audit-only checkpoints pass null.
            if ($event !== null) {
                $intent = $event($model);

                $this->eventRecorder->record(
                    name: $intent['name'],
                    module: Module::Catalog->value,
                    actorRole: $this->actor->role(),
                    actorId: $this->actor->actorId(),
                    entityType: $entity,
                    entityId: $entityId,
                    payload: $intent['payload'],
                );
            }

            return $model;
        });
    }

    /**
     * Record a review REJECTION (§ 4.3): a `reviewed → reviewed` governance DECISION that changes no state.
     * The entity stays in `reviewed`, one `audit_records` row captures the actor, the `decision: rejected`
     * and the operator's `$notes`, and NO domain event is recorded — the Creator edits in place (there is no
     * revert to `draft`) and the append-only trail preserves the full rejection history. From-state guarded
     * (only a `reviewed` entity may be rejected, else {@see IllegalLifecycleTransition}) and operator-floored
     * (a `system`/null actor cannot reject — a reviewer/approver decision is inherently human).
     *
     * @template TModel of Model&HasLifecycleState
     *
     * @param  TModel  $model
     * @param  string  $entity  the canonical entity-type label (e.g. `ProductMaster`)
     * @param  string  $notes  the operator's rejection notes, recorded in the audit after-snapshot
     * @return TModel
     *
     * @throws IllegalLifecycleTransition when the locked entity is not in `reviewed`
     * @throws ApprovalGovernanceViolation when there is no authenticated operator principal
     */
    public function reject(Model&HasLifecycleState $model, string $entity, string $notes): Model&HasLifecycleState
    {
        return DB::transaction(function () use ($model, $entity, $notes) {
            $this->lockAndRefresh($model);
            $entityId = $this->entityId($model);

            $state = $model->lifecycleState();

            if ($state !== LifecycleState::Reviewed) {
                throw IllegalLifecycleTransition::cannotReject($state, $entity);
            }

            $this->governance->requireOperator($entity);

            $this->recordAudit(
                $model,
                $entityId,
                self::DECISION_REJECTED,
                $entity,
                ['lifecycle_state' => $state->value],
                ['lifecycle_state' => $state->value, 'decision' => self::DECISION_REJECTED, 'notes' => $notes],
            );

            return $model;
        });
    }

    /**
     * Record a review RE-SUBMIT (§ 4.3; RM-06 / canon MVP-DEC-019): a `reviewed → reviewed` governance
     * DECISION that changes no state — the twin of {@see reject()} that RE-ARMS the approval flow after a
     * rejection. The entity stays in `reviewed`, one `audit_records` row captures the actor and the
     * `decision: resubmitted`, and NO domain event is recorded. Because "rejection-pending" is DERIVED from
     * the entity's latest governance audit action (design D3/D5 — no schema flag), the re-submit is the
     * freshest action and so clears the review-freshness activation block-gate (task 2.2) without any revert
     * to `draft`; the append-only trail preserves the full reject → re-submit history. From-state guarded
     * (only a `reviewed` entity may be re-submitted, else {@see IllegalLifecycleTransition::cannotResubmit()})
     * and operator-floored (a `system`/null actor cannot re-submit — a Creator's re-submission is inherently
     * human). No `$notes` argument: the "what changed" history is RM-14's re-versioning concern (design D2),
     * not a free-text note here.
     *
     * @template TModel of Model&HasLifecycleState
     *
     * @param  TModel  $model
     * @param  string  $entity  the canonical entity-type label (e.g. `ProductMaster`)
     * @return TModel
     *
     * @throws IllegalLifecycleTransition when the locked entity is not in `reviewed`
     * @throws ApprovalGovernanceViolation when there is no authenticated operator principal
     */
    public function resubmit(Model&HasLifecycleState $model, string $entity): Model&HasLifecycleState
    {
        return DB::transaction(function () use ($model, $entity) {
            $this->lockAndRefresh($model);
            $entityId = $this->entityId($model);

            $state = $model->lifecycleState();

            if ($state !== LifecycleState::Reviewed) {
                throw IllegalLifecycleTransition::cannotResubmit($state, $entity);
            }

            $this->governance->requireOperator($entity);

            $this->recordAudit(
                $model,
                $entityId,
                self::DECISION_RESUBMITTED,
                $entity,
                ['lifecycle_state' => $state->value],
                ['lifecycle_state' => $state->value, 'decision' => self::DECISION_RESUBMITTED],
            );

            return $model;
        });
    }

    /**
     * Acquire the row's FOR UPDATE lock for this transaction (a real lock on PostgreSQL that serialises
     * concurrent transitions; a no-op under SQLite's single writer), then refresh the authoritative locked
     * state onto $model — so the from-state assert reads DB truth, never the caller's stale snapshot.
     */
    private function lockAndRefresh(Model&HasLifecycleState $model): void
    {
        $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();
        $model->refresh();
    }

    /**
     * Record ONE audit row for a lifecycle step in the current transaction (invariant 8). The action is
     * `catalog.<entity>.<verb>` — the entity segment derived from the model's own table
     * (`catalog_product_masters` → `product_master`), the canonical snake-case identifier, so it never drifts
     * and reads cleanly even for the SKU acronyms (`catalog_sellable_skus` → `sellable_sku`). The actor is
     * the {@see ActorContext} principal; the basis is the catalog-lifecycle authority.
     *
     * @param  array<string, mixed>  $before  the pre-step snapshot
     * @param  array<string, mixed>  $after  the post-step snapshot (a rejection adds `decision` + `notes`)
     */
    private function recordAudit(Model $model, string $entityId, string $verb, string $entity, array $before, array $after): void
    {
        $segment = Str::singular(Str::after($model->getTable(), 'catalog_'));

        $this->auditRecorder->record(
            action: "catalog.{$segment}.{$verb}",
            module: Module::Catalog->value,
            actorRole: $this->actor->role(),
            actorId: $this->actor->actorId(),
            entityType: $entity,
            entityId: $entityId,
            before: $before,
            after: $after,
            authorizationBasis: self::AUTHORIZATION_BASIS,
        );
    }

    /** The model's primary key as the audit envelope's string `entity_id`. */
    private function entityId(Model $model): string
    {
        $key = $model->getKey();

        // Every lifecycle spine entity keys on an auto-increment integer; a non-scalar key is a structural bug.
        if (! is_int($key) && ! is_string($key)) {
            throw new LogicException('A lifecycle entity must have a scalar primary key.');
        }

        return (string) $key;
    }
}
