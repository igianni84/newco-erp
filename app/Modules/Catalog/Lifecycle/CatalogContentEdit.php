<?php

namespace App\Modules\Catalog\Lifecycle;

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalContentEdit;
use App\Modules\Module;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Events\ActorContext;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The shared CONTENT-EDIT mechanism — ONE place that performs an in-place, re-versioning edit of a Module 0
 * spine entity's content (catalog-module-0-completeness-sweep, design D1/D2/D3; product-catalog — Requirement:
 * In-Place Versioned Identity Edits; Module 0 PRD BR-Audit-1, DEC-073).
 *
 * An edit is NOT a lifecycle transition — it changes content, not `lifecycle_state` — so it lives BESIDE
 * {@see LifecycleTransition} rather than inside it (design D3): folding an edit into a transition FSM would
 * muddy a state machine with non-transitions. The two mechanisms are deliberate mirrors: the same
 * transaction + locked re-read, the same operator floor, the same audit envelope (shared verbatim through
 * {@see CatalogAuditEnvelope}). Per-entity thin Actions (`UpdateProductMasterIdentity`,
 * `UpdateCompositeSkuComposition`, …) call {@see edit()} with the model, the canonical entity label, their
 * audit VERB and an `$apply` closure carrying their field semantics; the mechanism owns everything else.
 *
 * This class makes Module 0 a TWO-writer audit trail (it ends the era in which `LifecycleTransition` was the
 * sole catalog audit writer) — which is exactly why {@see ApprovalGovernance}'s review-freshness condition is
 * derived from a FILTERED verb set (design D4/D5) rather than from the trail's raw latest action: an
 * `enrichment_updated` or `whitelist_updated` row written here must not be able to clear a pending rejection.
 * The D5 collision discipline governs the `$verb` this mechanism is called with: no verb may END with one of
 * the four review-freshness suffixes (`submitted` / `resubmitted` / `rejected` / `identity_updated`) unless it
 * is MEANT to participate in review freshness. `identity_updated` participates deliberately: review-governed
 * content changed, so the entity is review-stale until it is explicitly re-submitted (the DEC-019 re-arm leg).
 *
 * Re-versioning is IN-PLACE (design D1): the same row keeps its primary key and every downstream reference,
 * `version` increments by exactly one in the SAME `UPDATE` as the field writes, and the append-only,
 * 10-year audit trail is what keeps every old version retrievable — "deprecated, never deleted" (BR-Audit-1
 * asks for exactly that: the old version retrievable, the new version active, one audit row carrying before
 * AND after). No separate version rows; no schema churn.
 *
 * Race-safe state guard (mirroring design D2): inside ONE {@see DB::transaction} it re-reads the target row
 * `->lockForUpdate()` (a real row lock on PostgreSQL that serialises concurrent edits; a harmless no-op under
 * SQLite's single writer — the state assert carries correctness either way), asserts the LOCKED row is in an
 * editable state ({@see EDITABLE_STATES}: `draft`, `reviewed`, `active`; a `retired` entity must be reopened
 * first), then enforces the operator-principal floor, applies the change and records ONE audit row. The state
 * is read from the locked re-read, never the caller's (possibly stale) in-memory instance, so an edit decided
 * on a stale snapshot is rejected. Both rejections — {@see IllegalContentEdit} and
 * {@see ApprovalGovernanceViolation} — roll the transaction back BEFORE `$apply` is ever invoked, so a
 * rejected edit leaves the row, its `version`, the audit trail and the event log unchanged (nothing is
 * recorded, and the Action's own re-checks never run).
 *
 * An edit records NO domain event (design D2): the catalog event surface stays closed at the 21 lifecycle
 * events plus `EnrichmentDataUpdated` — and that one event belongs to the enrichment Action's own semantics,
 * recorded from inside its `$apply` closure (which runs in this transaction), never by this mechanism. The
 * audit row IS the edit's record: `catalog.<segment>.<verb>`, before/after carrying only the CHANGED fields
 * plus the `version` on both sides (design R9 — minimal snapshots, mirroring the transition rows' shape, so
 * the PII/redaction posture is unchanged).
 */
class CatalogContentEdit
{
    /**
     * The authorization basis stamped on every content-edit audit row: the write was performed under the
     * catalog content-edit authority (distinct from `LifecycleTransition`'s `catalog-lifecycle` basis — one
     * moves the FSM, the other changes content). The acting principal is recorded separately as
     * `actor_role`/`actor_id`.
     */
    private const AUTHORIZATION_BASIS = 'catalog-content-edit';

    /**
     * The states whose content may be edited (design D2). `draft` edits freely; a `reviewed` or `active`
     * entity may also be corrected — the FSM has no `active → reviewed` edge, so an `active` entity STAYS
     * `active` and the review-freshness derivation (not this guard) is what re-arms its review. Only a
     * `retired` entity is refused: its remedy is the `retired → reviewed` reopen.
     *
     * @var list<LifecycleState>
     */
    private const EDITABLE_STATES = [
        LifecycleState::Draft,
        LifecycleState::Reviewed,
        LifecycleState::Active,
    ];

    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly ActorContext $actor,
        private readonly ApprovalGovernance $governance,
    ) {}

    /**
     * Apply an in-place, re-versioning content edit to $model under $verb, recording ONE audit row; returns
     * the edited model (re-read under the row lock, reflecting the new content and `version`).
     *
     * @template TModel of Model&HasLifecycleState
     *
     * @param  TModel  $model  the spine entity to edit (carrying the `version` integer column every spine table has)
     * @param  string  $entity  the canonical entity-type label (e.g. `ProductMaster`) for the audit record + the rejections
     * @param  string  $verb  the audit action's verb segment (`identity_updated`, `enrichment_updated`, `whitelist_updated`) — governed by the D5 collision discipline
     * @param  (Closure(TModel): array{attributes: array<string, mixed>, before: array<string, mixed>, after: array<string, mixed>})  $apply  the Action's field semantics, invoked inside this transaction AFTER both guards pass, against the LOCKED model — the place for its own re-checks (identity dedup, N ≥ 2, constituent state) and for any related-row writes (per-type attribute sets, join tables). It returns the entity's OWN changed columns (`attributes`, merged with the `version` increment into a single UPDATE) plus the `before`/`after` snapshots of the changed fields for the audit row.
     * @return TModel
     *
     * @throws IllegalContentEdit when the locked row is `retired` (content is editable only in draft/reviewed/active)
     * @throws ApprovalGovernanceViolation when there is no authenticated operator principal
     */
    public function edit(
        Model&HasLifecycleState $model,
        string $entity,
        string $verb,
        Closure $apply,
    ): Model&HasLifecycleState {
        return DB::transaction(function () use ($model, $entity, $verb, $apply) {
            $this->lockAndRefresh($model);

            $state = $model->lifecycleState();

            if (! in_array($state, self::EDITABLE_STATES, true)) {
                throw IllegalContentEdit::cannotEdit($state, $entity);
            }

            // The operator-principal floor (design D2, reusing the governance guard's floor): a catalog content
            // edit is an inherently human operator decision — a `system`/null actor cannot perform one. Checked
            // BEFORE $apply, so a floor breach records nothing and runs none of the Action's re-checks.
            $this->governance->requireOperator($entity);

            $versionBefore = $this->versionOf($model, $entity);
            $versionAfter = $versionBefore + 1;

            // The Action's field semantics, against the LOCKED row: its own re-checks may reject here (rolling
            // the transaction back, unchanged), and its related-row writes join this same transaction.
            $change = $apply($model);

            // The entity's own changed columns AND the version increment in ONE UPDATE (design D1) — the row
            // can never be observed carrying new content at the old version.
            $model->update([...$change['attributes'], 'version' => $versionAfter]);

            $this->auditRecorder->record(
                action: CatalogAuditEnvelope::action($model, $verb),
                module: Module::Catalog->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: $entity,
                entityId: CatalogAuditEnvelope::entityId($model),
                before: [...$change['before'], 'version' => $versionBefore],
                after: [...$change['after'], 'version' => $versionAfter],
                authorizationBasis: self::AUTHORIZATION_BASIS,
            );

            return $model;
        });
    }

    /**
     * Acquire the row's FOR UPDATE lock for this transaction (a real lock on PostgreSQL that serialises
     * concurrent edits; a no-op under SQLite's single writer), then refresh the authoritative locked state onto
     * $model — so the state guard, the `version` read and the Action's re-checks all see DB truth, never the
     * caller's stale snapshot.
     */
    private function lockAndRefresh(Model&HasLifecycleState $model): void
    {
        $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();
        $model->refresh();
    }

    /**
     * The locked row's current `version`. Every Module 0 spine table carries the column (default 1, cast to
     * `integer` on every spine model), so a non-int read means this mechanism was handed a model that is not a
     * versioned spine entity — a structural bug, not a domain rejection.
     */
    private function versionOf(Model $model, string $entity): int
    {
        $version = $model->getAttribute('version');

        if (! is_int($version)) {
            throw new LogicException("A content-editable catalog entity must carry an integer `version` attribute ({$entity}).");
        }

        return $version;
    }
}
