<?php

namespace App\Modules\Catalog\Lifecycle;

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;

/**
 * The four legal lifecycle transitions — each a (from → to) edge of the uniform spine FSM
 * `draft → reviewed → active → retired` plus the `retired → reviewed` reopen (design D1/D2; Module 0 PRD
 * § 4.1; product-catalog — Requirement: Product Lifecycle State Machine).
 *
 * This enum is the single source of truth for the transition MAP the shared {@see LifecycleTransition}
 * mechanism drives: the from/to states, the past-tense audit verb, and the localized rejection factory on
 * {@see IllegalLifecycleTransition}. The map is identical for all seven spine entities (an entity differs
 * only in its activation gate and the event it records — later tasks), so it lives here once and every
 * per-entity Action references a case rather than re-encoding the edges.
 *
 * Recording semantics (Module 0 PRD § 14.2, wired by later tasks): Activate records the entity's
 * `*Activated` event and Retire its `*Retired` event; Submit and Reopen are audit-ONLY checkpoints
 * (event-silent — there is no `*Reviewed` event). This enum carries only the FSM map; the event wiring and
 * the approval-governance / activation-gate guards land with the activation/retirement Actions.
 */
enum LifecycleTransitionType
{
    case Submit;   // draft → reviewed   (audit-only checkpoint)

    case Activate; // reviewed → active  (records *Activated)

    case Retire;   // active → retired   (records *Retired)

    case Reopen;   // retired → reviewed (audit-only checkpoint)

    /** The required from-state: a transition invoked on an entity not in this state is rejected. */
    public function from(): LifecycleState
    {
        return match ($this) {
            self::Submit => LifecycleState::Draft,
            self::Activate => LifecycleState::Reviewed,
            self::Retire => LifecycleState::Active,
            self::Reopen => LifecycleState::Retired,
        };
    }

    /** The to-state written on a successful transition. */
    public function to(): LifecycleState
    {
        return match ($this) {
            self::Submit => LifecycleState::Reviewed,
            self::Activate => LifecycleState::Active,
            self::Retire => LifecycleState::Retired,
            self::Reopen => LifecycleState::Reviewed,
        };
    }

    /** The past-tense verb segment of the audit action (`catalog.<entity>.<verb>`). */
    public function auditVerb(): string
    {
        return match ($this) {
            self::Submit => 'submitted',
            self::Activate => 'activated',
            self::Retire => 'retired',
            self::Reopen => 'reopened',
        };
    }

    /** The localized rejection for an out-of-state call of this transition (design D2). */
    public function rejection(LifecycleState $from, string $entity): IllegalLifecycleTransition
    {
        return match ($this) {
            self::Submit => IllegalLifecycleTransition::cannotSubmit($from, $entity),
            self::Activate => IllegalLifecycleTransition::cannotActivate($from, $entity),
            self::Retire => IllegalLifecycleTransition::cannotRetire($from, $entity),
            self::Reopen => IllegalLifecycleTransition::cannotReopen($from, $entity),
        };
    }
}
