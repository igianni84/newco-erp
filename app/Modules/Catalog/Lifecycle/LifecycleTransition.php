<?php

namespace App\Modules\Catalog\Lifecycle;

use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Module;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Events\ActorContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * The shared lifecycle-transition mechanism — ONE place that drives the uniform spine FSM
 * `draft → reviewed → active → retired` (+ the `retired → reviewed` reopen) for all seven Module 0 spine
 * entities (design D1/D2; product-catalog — Requirement: Product Lifecycle State Machine).
 *
 * Per design D1 the seven entities share the IDENTICAL FSM, from-state guard and audit shape — they differ
 * only in their activation gate and the event they record (later tasks) — so the generic mechanics live
 * here once instead of ~35 near-identical Action classes. Per-entity thin Actions
 * (`SubmitProductMasterForReview` etc.) call {@see transition()} with the model, the
 * {@see LifecycleTransitionType} and the canonical entity label. This mechanism is the SOLE writer of
 * `lifecycle_state` (the immutability discipline the spine rests on); the Models stay persistence-only.
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
 * Every transition is audited (CLAUDE.md invariant 8): an `audit_records` row in the SAME transaction as
 * the state write (the recorder's open-transaction guard makes write + audit atomic), with the before/after
 * `{lifecycle_state}` snapshot and the acting principal resolved from the {@see ActorContext} seam. The
 * `draft → reviewed` and `retired → reviewed` checkpoints are audit-ONLY (no domain event, Module 0 PRD
 * § 14.2). Two seams layer onto this mechanism in later tasks: a domain event recorded on Activate/Retire,
 * and the approval-governance + per-entity activation gate evaluated before the write.
 */
class LifecycleTransition
{
    /**
     * The authorization basis stamped on every lifecycle audit row: the step was performed under the
     * catalog-lifecycle management authority. The acting principal is recorded separately as
     * `actor_role`/`actor_id`.
     */
    private const AUTHORIZATION_BASIS = 'catalog-lifecycle';

    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly ActorContext $actor,
    ) {}

    /**
     * Drive $model through $type, recording the step in the audit trail; returns the transitioned model
     * (re-read under the row lock, reflecting the to-state).
     *
     * @template TModel of Model&HasLifecycleState
     *
     * @param  TModel  $model  the spine entity to transition (this mechanism is its sole `lifecycle_state` writer)
     * @param  string  $entity  the canonical entity-type label (e.g. `ProductMaster`) for the audit record + the rejection
     * @return TModel
     */
    public function transition(Model&HasLifecycleState $model, LifecycleTransitionType $type, string $entity): Model&HasLifecycleState
    {
        return DB::transaction(function () use ($model, $type, $entity) {
            // Acquire the row's FOR UPDATE lock for this transaction (a real lock on PostgreSQL that
            // serialises concurrent transitions; a no-op under SQLite's single writer), then refresh the
            // authoritative state onto $model. The from-state assert reads THIS locked row, never the
            // caller's (possibly stale) in-memory snapshot — so a transition decided on a stale read is
            // rejected.
            $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();
            $model->refresh();

            $from = $model->lifecycleState();

            if ($from !== $type->from()) {
                throw $type->rejection($from, $entity);
            }

            $to = $type->to();
            $model->update(['lifecycle_state' => $to]);

            // One audit row per step, in the same transaction as the write (invariant 8). The before/after
            // snapshot is the lifecycle_state edge; the actor is the ActorContext principal.
            $this->auditRecorder->record(
                action: $this->auditAction($model, $type),
                module: Module::Catalog->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: $entity,
                entityId: $this->entityId($model),
                before: ['lifecycle_state' => $from->value],
                after: ['lifecycle_state' => $to->value],
                authorizationBasis: self::AUTHORIZATION_BASIS,
            );

            return $model;
        });
    }

    /**
     * The audit action `catalog.<entity>.<verb>` — e.g. `catalog.product_master.submitted`. The entity
     * segment is derived from the model's own table (`catalog_product_masters` → `product_master`), the
     * canonical snake-case identifier, so it never drifts and reads cleanly even for the SKU acronyms
     * (`catalog_sellable_skus` → `sellable_sku`).
     */
    private function auditAction(Model $model, LifecycleTransitionType $type): string
    {
        $segment = Str::singular(Str::after($model->getTable(), 'catalog_'));

        return "catalog.{$segment}.{$type->auditVerb()}";
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
