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
 * The shared CONTENT-EDIT mechanism — ONE place that performs an in-place write to a Module 0 spine entity's
 * content (catalog-module-0-completeness-sweep, design D1/D2/D3/D6/D11; product-catalog — Requirements: In-Place
 * Versioned Identity Edits, Layer-1 Case-Configuration Whitelist, Enrichment Data Update; Module 0 PRD
 * BR-Audit-1, DEC-073).
 *
 * An edit is NOT a lifecycle transition — it changes content, not `lifecycle_state` — so it lives BESIDE
 * {@see LifecycleTransition} rather than inside it (design D3): folding an edit into a transition FSM would
 * muddy a state machine with non-transitions. The two mechanisms are deliberate mirrors: the same
 * transaction + locked re-read, the same operator floor, the same audit envelope (shared verbatim through
 * {@see CatalogAuditEnvelope}). Per-entity thin Actions (`UpdateProductMasterIdentity`,
 * `UpdateCompositeSkuComposition`, `SetVariantCaseWhitelist`, …) call one of the two entry points with the
 * model, the canonical entity label, their audit VERB and an `$apply` closure carrying their field semantics;
 * the mechanism owns everything else.
 *
 * TWO entry points, over ONE set of guards — the difference is `version`, and `version` means *identity*:
 *
 *   {@see edit()} — a RE-VERSIONING edit of review-governed identity content (`identity_updated`). The entity's
 *   `version` increments by exactly one; the audit row carries it on both sides. What the reviewer approved has
 *   changed, so the entity becomes review-stale (see below).
 *
 *   {@see maintain()} — an audit-only MAINTENANCE write of data that is NOT the entity's identity: the Layer-1
 *   case-configuration whitelist (a statement about how this product *could* be packaged — design D6) and the
 *   observational enrichment prose (design D11). `version` is untouched: nothing an approver signed off on has
 *   moved, so re-stamping the identity version would be a lie to every downstream reader that watches it. The
 *   state guard, the operator floor, the lock and the audit row are IDENTICAL — a maintenance write is no less
 *   an operator decision, and no less auditable, than an identity edit.
 *
 * The split is therefore semantic, not a convenience flag: a caller that must ask "should this bump `version`?"
 * is really asking "is this the entity's identity?", and the two verbs answer it at the call site.
 *
 * This class makes Module 0 a TWO-writer audit trail (it ends the era in which `LifecycleTransition` was the
 * sole catalog audit writer) — which is exactly why {@see ApprovalGovernance}'s review-freshness condition is
 * derived from a FILTERED verb set (design D4/D5) rather than from the trail's raw latest action: an
 * `enrichment_updated` or `whitelist_updated` row written here must not be able to clear a pending rejection.
 * The D5 collision discipline governs the `$verb` this mechanism is called with: no verb may END with one of
 * the four review-freshness suffixes (`submitted` / `resubmitted` / `rejected` / `identity_updated`) unless it
 * is MEANT to participate in review freshness. `identity_updated` participates deliberately: review-governed
 * content changed, so the entity is review-stale until it is explicitly re-submitted (the DEC-019 re-arm leg).
 * A {@see maintain()} verb never may — which is the same statement as "it does not touch `version`", read from
 * the governance side: the whitelist and the enrichment prose are not what the reviewer approved, so a
 * reviewed-then-maintained entity still activates without a re-submit. That discipline is a GUARD, not a
 * comment: {@see assertVerbIsNotReviewGoverned()} refuses such a verb at the call, reading the one list
 * {@see ApprovalGovernance::REVIEW_FRESHNESS_VERBS} the derivation itself reads.
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
 *
 * A NO-OP is the closure's call, never the mechanism's: `$apply` may report `null` for *nothing to record*,
 * and then this mechanism writes nothing at all — no `UPDATE`, no `version` increment, no audit row (the
 * guards, the lock and the closure's own re-checks all ran; they simply found no change to record). Only the
 * enrichment Action uses it, because only `EnrichmentDataUpdated`'s contract makes idempotence observable
 * (design D11: an update that changes nothing records no event). An identity edit always records: an operator
 * re-affirming reviewed content IS the audited fact, and the `version` increment is what re-arms its review.
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
     * Apply an in-place, RE-VERSIONING content edit to $model under $verb, recording ONE audit row; returns
     * the edited model (re-read under the row lock, reflecting the new content and `version`).
     *
     * For review-governed IDENTITY content only: `version` increments by exactly one and the audit row carries
     * it on both sides. Data that is not the entity's identity goes through {@see maintain()} instead.
     *
     * @template TModel of Model&HasLifecycleState
     *
     * @param  TModel  $model  the spine entity to edit (carrying the `version` integer column every spine table has)
     * @param  string  $entity  the canonical entity-type label (e.g. `ProductMaster`) for the audit record + the rejections
     * @param  string  $verb  the audit action's verb segment (`identity_updated`) — governed by the D5 collision discipline
     * @param  (Closure(TModel): (array{attributes: array<string, mixed>, before: array<string, mixed>, after: array<string, mixed>}|null))  $apply  the Action's field semantics — see {@see perform()}
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
        return $this->perform($model, $entity, $verb, $apply, reVersion: true);
    }

    /**
     * Apply an in-place, audit-only MAINTENANCE write to $model under $verb, recording ONE audit row; returns
     * the maintained model (re-read under the row lock).
     *
     * The non-versioning sibling of {@see edit()}, under the same transaction, the same `lockForUpdate` re-read,
     * the same `draft`/`reviewed`/`active` state guard and the same operator-principal floor. It exists for the
     * writes that are NOT the entity's identity — the Layer-1 whitelist (design D6) and the enrichment prose
     * (design D11) — where an identity `version` bump would misinform every downstream reader that watches it,
     * and where the D5 verb discipline correspondingly forbids a review-freshness-relevant suffix (a
     * reviewed-then-maintained entity still activates without a re-submit).
     *
     * `version` appears in neither the UPDATE nor the audit snapshots. When the `$apply` closure reports no own
     * changed columns — a whitelist lives entirely in its pivot table — the entity's row is not written at all
     * (not even its `updated_at`): the audit row IS the record of the maintenance write. When it reports `null`
     * — nothing changed at all — not even that row is written (design D11's idempotent enrichment no-op).
     *
     * @template TModel of Model&HasLifecycleState
     *
     * @param  TModel  $model  the spine entity whose attached data is maintained
     * @param  string  $entity  the canonical entity-type label (e.g. `ProductVariant`) for the audit record + the rejections
     * @param  string  $verb  the audit action's verb segment (`whitelist_updated`, `enrichment_updated`) — MUST NOT end with a review-freshness suffix; enforced by {@see assertVerbIsNotReviewGoverned()} (design D5)
     * @param  (Closure(TModel): (array{attributes: array<string, mixed>, before: array<string, mixed>, after: array<string, mixed>}|null))  $apply  the Action's field semantics — see {@see perform()}
     * @return TModel
     *
     * @throws LogicException when $verb is a review-freshness-governed verb (a call-site bug, not a domain rejection)
     * @throws IllegalContentEdit when the locked row is `retired`
     * @throws ApprovalGovernanceViolation when there is no authenticated operator principal
     */
    public function maintain(
        Model&HasLifecycleState $model,
        string $entity,
        string $verb,
        Closure $apply,
    ): Model&HasLifecycleState {
        self::assertVerbIsNotReviewGoverned($verb);

        return $this->perform($model, $entity, $verb, $apply, reVersion: false);
    }

    /**
     * The D5 collision discipline, ENFORCED rather than merely documented: a maintenance write must not be
     * recorded under a verb the review-freshness derivation reads.
     *
     * {@see ApprovalGovernance} derives review-freshness from the audit actions ending `.submitted`,
     * `.resubmitted`, `.rejected` or `.identity_updated`. An action is `catalog.<segment>.<verb>`, so it ends
     * with `.<governed verb>` exactly when the VERB is that governed verb (verbs are single dotless segments;
     * the `str_ends_with` leg below keeps the rule exact even if one ever were not). Letting a `maintain()`
     * call through under such a verb would make an observational write the entity's freshest
     * review-freshness-relevant action — clearing a pending rejection with no re-submit, which is precisely the
     * S1 hole this mechanism's arrival made possible and the filtered derivation closed.
     *
     * A {@see LogicException} (not a domain rejection): no operator input can reach this, only a programming
     * mistake at a call site — and the mistake must surface at the call, not as a green suite hiding a
     * governance bypass. {@see edit()} is exempt by construction: `identity_updated` is governed ON PURPOSE.
     */
    private static function assertVerbIsNotReviewGoverned(string $verb): void
    {
        foreach (ApprovalGovernance::REVIEW_FRESHNESS_VERBS as $governed) {
            if ($verb === $governed || str_ends_with($verb, '.'.$governed)) {
                throw new LogicException(
                    "A maintenance write may not use the review-freshness-governed verb `{$verb}` (design D5): "
                    .'it would let an observational edit clear a pending rejection. Use `edit()` if the write '
                    .'really is a re-versioning identity change.'
                );
            }
        }
    }

    /**
     * The guards, the lock, the write and the audit row — shared verbatim by both entry points, which differ
     * only in $reVersion (private, so the boolean is never a caller's concern: {@see edit()} and
     * {@see maintain()} name the semantic choice at the call site).
     *
     * @template TModel of Model&HasLifecycleState
     *
     * @param  TModel  $model
     * @param  (Closure(TModel): (array{attributes: array<string, mixed>, before: array<string, mixed>, after: array<string, mixed>}|null))  $apply  the Action's field semantics, invoked inside this transaction AFTER both guards pass, against the LOCKED model — the place for its own re-checks (identity dedup, N ≥ 2, constituent state, reference existence) and for any related-row writes (per-type attribute sets, join tables, pivots). It returns the entity's OWN changed columns (`attributes`, merged with any `version` increment into a single UPDATE) plus the `before`/`after` snapshots of the changed fields for the audit row — or `null` to report that there was nothing to record at all.
     * @param  bool  $reVersion  whether this write re-versions the entity's identity (design D1) or merely maintains attached data
     * @return TModel
     *
     * @throws IllegalContentEdit
     * @throws ApprovalGovernanceViolation
     */
    private function perform(
        Model&HasLifecycleState $model,
        string $entity,
        string $verb,
        Closure $apply,
        bool $reVersion,
    ): Model&HasLifecycleState {
        return DB::transaction(function () use ($model, $entity, $verb, $apply, $reVersion) {
            $this->lockAndRefresh($model);

            $state = $model->lifecycleState();

            if (! in_array($state, self::EDITABLE_STATES, true)) {
                throw IllegalContentEdit::cannotEdit($state, $entity);
            }

            // The operator-principal floor (design D2, reusing the governance guard's floor): a catalog content
            // write is an inherently human operator decision — a `system`/null actor cannot perform one. Checked
            // BEFORE $apply, so a floor breach records nothing and runs none of the Action's re-checks.
            $this->governance->requireOperator($entity);

            // Also before $apply: a model handed to `edit()` without an integer `version` is a structural bug,
            // and a structural bug must not first run the Action's semantics. A maintenance write never reads it.
            $versionBefore = $reVersion ? $this->versionOf($model, $entity) : null;

            // The Action's field semantics, against the LOCKED row: its own re-checks may reject here (rolling
            // the transaction back, unchanged), and its related-row writes join this same transaction.
            $change = $apply($model);

            // `null` = nothing to record: the closure found the incoming content identical to the stored content
            // and wrote nothing. The write is a NO-OP — no UPDATE, no `version` increment, no audit row — and the
            // caller receives the (locked, re-read) model exactly as it stands. Idempotence is a per-Action
            // contract, declared by returning `null`; every other closure always records (design D11).
            if ($change === null) {
                return $model;
            }

            $attributes = $change['attributes'];
            $before = $change['before'];
            $after = $change['after'];

            if ($versionBefore !== null) {
                $attributes['version'] = $versionBefore + 1;
                $before['version'] = $versionBefore;
                $after['version'] = $versionBefore + 1;
            }

            // The entity's own changed columns AND the version increment in ONE UPDATE (design D1) — the row can
            // never be observed carrying new content at the old version. A maintenance write whose content lives
            // wholly outside the row (a pivot) changes no column, and then the row is left entirely alone.
            if ($attributes !== []) {
                $model->update($attributes);
            }

            $this->auditRecorder->record(
                action: CatalogAuditEnvelope::action($model, $verb),
                module: Module::Catalog->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: $entity,
                entityId: CatalogAuditEnvelope::entityId($model),
                before: $before,
                after: $after,
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
