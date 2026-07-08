<?php

namespace App\Modules\Catalog\Lifecycle;

use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Module;
use App\Platform\Audit\AuditRecord;
use App\Platform\Events\ActorContext;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Database\Eloquent\Builder;

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
 * The review-freshness block-gate (RM-06 / canon MVP-DEC-019; § 4.3, and its edit leg from
 * catalog-module-0-completeness-sweep design D4): at the approval step the guard also refuses activation while
 * the entity is REVIEW-STALE ({@see assertReviewIsFresh}) — its content has changed, or been refused, since the
 * last review decision. Like the creator/reviewer lineage this is DERIVED from the audit trail (no schema flag;
 * design D3), and it is checked BEFORE the distinctness floor: a review-stale entity is not activatable by ANY
 * operator until it is re-submitted. Retire and reopen are not activation and so are never blocked by it.
 *
 * The derivation is VERB-FILTERED, not a raw latest-action read: the catalog audit trail now carries
 * non-governance rows too (identity edits, enrichment updates, whitelist maintenance — this class is no longer
 * reading a trail written solely by {@see LifecycleTransition}). Only the four REVIEW-FRESHNESS-RELEVANT verbs
 * participate ({@see REVIEW_FRESHNESS_VERBS}: `submitted`, `resubmitted`, `rejected`, `identity_updated`); among
 * those the LATEST wins, and it leaves the entity review-stale iff it is a `rejected` (an un-remediated
 * rejection) or an `identity_updated` (review-governed content edited but not re-reviewed). Every other catalog
 * audit row neither sets nor clears the condition — a post-rejection enrichment or whitelist row can never
 * unblock activation without a re-submit. `submitted` MUST stay in the relevant set: it is what clears a
 * `draft`-stage identity edit, which would otherwise block the entity forever.
 *
 * Verb-collision discipline (design D5): no future catalog audit verb may END with one of the four relevant
 * suffixes unless it is meant to participate in review-freshness. (`resubmitted` deliberately does not end with
 * `.submitted` — the `.` in the suffix separates the segment, so {@see reviewerOf}'s `LIKE '%.submitted'` and
 * this filter both discriminate the two.)
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

    /** The submit verb — clears a `draft`-stage edit; the freshness set's only never-stale entry besides `resubmitted`. */
    private const VERB_SUBMITTED = 'submitted';

    /** The explicit re-submit verb — the Creator re-arming review after a rejection or an edit. */
    private const VERB_RESUBMITTED = 'resubmitted';

    /** The rejection verb — leaves the entity review-stale until re-submitted. */
    private const VERB_REJECTED = 'rejected';

    /** The identity-edit verb — review-governed content changed, so it too leaves the entity review-stale. */
    private const VERB_IDENTITY_UPDATED = 'identity_updated';

    /**
     * The REVIEW-FRESHNESS-RELEVANT audit verbs (design D4/D5): the only `catalog.<segment>.<verb>` rows the
     * review-freshness condition is derived from. Any other catalog audit row (`activated`, `retired`,
     * `reopened`, `enrichment_updated`, `whitelist_updated`, …) neither sets nor clears the condition.
     *
     * @var list<string>
     */
    private const REVIEW_FRESHNESS_VERBS = [
        self::VERB_SUBMITTED,
        self::VERB_RESUBMITTED,
        self::VERB_REJECTED,
        self::VERB_IDENTITY_UPDATED,
    ];

    public function __construct(private readonly ActorContext $actor) {}

    /**
     * Enforce the approval governance for a commercial-impact transition: the operator-principal floor
     * always, plus — at the approval step (`reviewed → active`) — the review-freshness block-gate (a pending
     * rejection blocks activation) and the separation-of-duties distinctness.
     *
     * @param  string  $entity  the canonical entity-type label (e.g. `ProductMaster`) — matches the audit / event `entity_type`
     * @param  string  $entityId  the entity's stringified primary key (the audit / event `entity_id`)
     *
     * @throws ApprovalGovernanceViolation when the operator-principal floor is breached, a review-stale entity blocks activation, or the distinctness floor is breached
     */
    public function guard(LifecycleTransitionType $type, string $entity, string $entityId): void
    {
        $approver = $this->operatorPrincipalOrFail($entity);

        // The review-freshness block-gate and the separation-of-duties distinctness are BOTH the APPROVAL
        // step's floors (the Creator → Reviewer → Approver lineage culminates in `reviewed → active`);
        // retire/reopen carry only the operator floor. The block-gate runs FIRST: a review-stale entity (an
        // un-remediated rejection, or review-governed content edited since the last review) is not in an
        // activatable review-state at all — NO operator may approve it until it is re-submitted — so it
        // precedes the who-may-approve distinctness check (and, in the mechanism, the per-entity activation
        // gate).
        if ($type === LifecycleTransitionType::Activate) {
            $this->assertReviewIsFresh($entity, $entityId);
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

    /**
     * The review-freshness block-gate (RM-06 / canon MVP-DEC-019; design D1/D3; the edit leg from
     * catalog-module-0-completeness-sweep design D4; product-catalog — Requirement: Approval Governance):
     * refuse activation while the entity is REVIEW-STALE. The condition is DERIVED from the audit trail
     * (never a persisted schema flag; design D3, reaffirming catalog-lifecycle-approval D5): among the
     * entity's REVIEW-FRESHNESS-RELEVANT catalog audit rows the LATEST wins, and it blocks iff it is a
     * `.rejected` (an un-remediated rejection) or a `.identity_updated` (review-governed content edited since
     * the last review decision). The two causes carry DISTINCT localized reasons — the operator's remedy is the
     * same re-submit, but "you were rejected" and "you edited it" are different facts.
     *
     * A `.resubmitted` becomes the freshest relevant action and so clears BOTH causes; a `.submitted` clears a
     * `draft`-stage edit; an entity with no relevant action at all (a factory/seed-built entity) is vacuously
     * fresh. Rows outside the relevant set — `.activated`, `.retired`, `.reopened`, `.enrichment_updated`,
     * `.whitelist_updated` — are INVISIBLE here, so they can neither block nor unblock.
     *
     * @throws ApprovalGovernanceViolation when the entity is review-stale (un-remediated rejection, or an un-re-reviewed identity edit)
     */
    private function assertReviewIsFresh(string $entity, string $entityId): void
    {
        $latest = $this->latestReviewFreshnessAction($entity, $entityId);

        if ($latest === null) {
            return;
        }

        if (str_ends_with($latest, '.'.self::VERB_REJECTED)) {
            throw ApprovalGovernanceViolation::activationBlockedByPendingRejection($entity);
        }

        if (str_ends_with($latest, '.'.self::VERB_IDENTITY_UPDATED)) {
            throw ApprovalGovernanceViolation::activationBlockedByUnreviewedEdit($entity);
        }
    }

    /**
     * The entity's LATEST review-freshness-relevant catalog audit action, or null when it has none.
     *
     * The SQL `LIKE` prefilter narrows the scan to the four relevant suffixes, but it is only an
     * OVER-approximation: `_` is a single-character wildcard in `LIKE` on both engines, so `%.identity_updated`
     * would also match a hypothetical `.identityXupdated`. The PHP {@see endsWithReviewFreshnessVerb()} pass over
     * the (newest-first) candidates is therefore the AUTHORITATIVE filter — it returns the first EXACT match, so
     * the predicate is exact regardless of what verbs the trail grows. Escaping the underscore instead was
     * rejected: `ESCAPE` semantics differ between SQLite and PostgreSQL, and this predicate must be engine-neutral.
     */
    private function latestReviewFreshnessAction(string $entity, string $entityId): ?string
    {
        $candidates = AuditRecord::query()
            ->where('module', Module::Catalog->value)
            ->where('entity_type', $entity)
            ->where('entity_id', $entityId)
            ->where(function (Builder $relevant): void {
                foreach (self::REVIEW_FRESHNESS_VERBS as $verb) {
                    $relevant->orWhere('action', 'like', '%.'.$verb);
                }
            })
            ->orderByDesc('id')
            ->pluck('action');

        foreach ($candidates as $action) {
            if (is_string($action) && self::endsWithReviewFreshnessVerb($action)) {
                return $action;
            }
        }

        return null;
    }

    /** Does this audit action end in one of the four review-freshness-relevant verbs (the exact, engine-free test)? */
    private static function endsWithReviewFreshnessVerb(string $action): bool
    {
        foreach (self::REVIEW_FRESHNESS_VERBS as $verb) {
            if (str_ends_with($action, '.'.$verb)) {
                return true;
            }
        }

        return false;
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
