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
 * The gate is not the only door onto the invariant. Activation is ONE way a child could come to reference a
 * non-`active` parent; replacing an `active` Composite SKU's constituent set is another — and the activation
 * gate never runs again on an already-`active` entity. So the same violated rule is raised from the EDIT path
 * too ({@see constituentNotActiveOnCompositionEdit()}, thrown by `UpdateCompositeSkuComposition`: the cascade
 * condition re-asserted at edit time, catalog-module-0-completeness-sweep design D2; product-catalog —
 * Requirement: In-Place Versioned Identity Edits). One exception class, one invariant — "a child never
 * references a non-`active` parent" — with one reason per SURFACE: a caller catching this type learns the
 * cascade was breached, whichever door it was breached through, while the operator reads copy naming the action
 * they actually attempted. The edit-path rejection fires inside the content-edit mechanism's transaction, so the
 * composition, the `version`, the audit trail and the event log all roll back unchanged.
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

    /**
     * The cascade condition re-asserted at edit time: an `active` Composite SKU's REPLACEMENT constituent set
     * contains a `$parent` that is not `active` (or that does not resolve — fail-closed, exactly as the gate
     * treats a null parent). Distinct copy from {@see parentNotActive()} because the operator pressed *save
     * composition*, not *activate*: the rule is the same, the surface is not.
     */
    public static function constituentNotActiveOnCompositionEdit(string $entity, string $parent): self
    {
        return new self((string) __('catalog.gate.parent_not_active_on_composition_edit', [
            'entity' => $entity,
            'parent' => $parent,
        ]));
    }
}
