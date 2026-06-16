<?php

namespace App\Modules\Catalog\Exceptions;

use RuntimeException;

/**
 * Raised when a child spine entity's `reviewed → active` transition is blocked by the activation cascade —
 * a parent (or a constituent it composes) that it requires is not `active` (catalog-lifecycle-approval,
 * design D7; product-catalog — Requirement: Activation Cascade; Module 0 PRD § 4.4, BR-Lifecycle-3). A child
 * SHALL NOT reach `active` while a parent it depends on is not `active`: a Product Variant requires its
 * Product Master; a Product Reference its Product Variant AND its Format; a Sellable SKU its Product Reference
 * AND its Case Configuration; a Composite SKU every constituent Product Reference. The gate is a HARD gate,
 * rejected at the workflow level — the transition's transaction rolls back, so the child stays `reviewed` and
 * no `*Activated` event (nor its audit row) is recorded, and parent-before-child emission ordering falls out
 * for free (a child can never reach `active` before its parent).
 *
 * This is the WITHIN-CATALOG sibling of {@see ProducerActivationGateViolation}: that gate reads a
 * cross-module read model (the producer-state projection) for the one cross-module parent (a Master's
 * Producer); this gate reads a sibling spine entity's `lifecycle_state` WITHIN Module 0 (no projection — the
 * parent's own row is the truth, design D7). One parameterized exception serves every child entity (the
 * cascade rule is uniform; the entity and its blocking parent are the parameters), the faithful analogue of
 * the single {@see IllegalLifecycleTransition} over a uniform FSM.
 *
 * The copy (the `gate` group of `lang/en/catalog.php`; CLAUDE.md invariant 12 — no hardcoded user-facing
 * strings) names only the violated rule, the `:entity` type label and the `:parent` it is waiting on — never
 * any party or personal data (PII-free; invariant 10). `(string)` coerces the translator return (typed
 * `mixed` by Larastan) to the RuntimeException message contract.
 */
class ActivationCascadeViolation extends RuntimeException
{
    public static function parentNotActive(string $entity, string $parent): self
    {
        return new self((string) __('catalog.gate.parent_not_active', ['entity' => $entity, 'parent' => $parent]));
    }
}
