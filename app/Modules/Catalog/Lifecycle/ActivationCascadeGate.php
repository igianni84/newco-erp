<?php

namespace App\Modules\Catalog\Lifecycle;

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Exceptions\ActivationCascadeViolation;

/**
 * The activation-cascade gate — the WITHIN-MODULE precondition on a CHILD spine entity's `reviewed → active`
 * transition (catalog-lifecycle-approval, design D7; product-catalog — Requirement: Activation Cascade;
 * Module 0 PRD § 4.4, BR-Lifecycle-3). A child cannot reach `active` while a parent it depends on is not
 * `active`. The per-entity Activate action (e.g. `ActivateProductVariant`, task 4.3) passes a closure that
 * loads the child's parent(s) and calls {@see assertParentActive()} once per parent to the shared
 * {@see LifecycleTransition} mechanism, which evaluates it AFTER the approval-governance guard and BEFORE the
 * state write, inside the transition's transaction; a breach throws {@see ActivationCascadeViolation} and the
 * whole transition rolls back, so the child stays `reviewed` and records no `*Activated`.
 *
 * This is the within-catalog sibling of {@see ProducerActivationGate}: that gate reads the cross-module
 * producer-state projection for a Master's Producer; this gate reads a SIBLING spine entity's
 * `lifecycle_state` directly — the parent's own row is the truth WITHIN Module 0 (no projection, no
 * cross-module read; invariant 10 is untouched because the parent is the same module's entity, design D7).
 *
 * The check is a pure decision over the already-loaded parent — the per-entity Action owns the within-module
 * read (it knows the relationship: a Variant's `product_master_id`, a Reference's `product_variant_id` +
 * `format_id`, a Composite SKU's constituent set) and feeds each loaded parent here. A child with multiple
 * parents (a Product Reference, a Sellable SKU) or N constituents (a Composite SKU) calls this once per
 * parent, so the SAME primitive serves the whole hierarchy. Fail-closed (the spec's hard gate): a null parent
 * — a missing/unresolved reference — is treated exactly like a non-`active` one and the gate rejects. The
 * read is intentionally lock-free (a read-time gate, design D7): the cascade blocks NEW activation; it never
 * cascade-retires an in-flight one.
 *
 * The proven parent is RETURNED, not merely asserted — so a caller that also needs to read it (a Sellable
 * SKU's activation resolves its Product Reference's (Variant, Format) pair for the sibling
 * {@see CaseConfigurationWhitelistGate}) reads it from a value the gate has already narrowed, rather than
 * re-querying it or null-checking what the throw made unreachable.
 */
class ActivationCascadeGate
{
    /**
     * Assert that a parent the activating child depends on is `active`, else reject the activation — and hand
     * the proven parent back.
     *
     * The return value carries the fail-closed contract into the type system: past this call the parent is
     * non-null (the throw said so), so a caller that must READ the parent afterwards — `ActivateSellableSku`
     * resolves its Product Reference's `(product_variant_id, format_id)` pair for the whitelist gate — needs no
     * null check that the throw already made unreachable. Most callers pass a parent that is non-null by
     * construction (a relation-loaded constituent) and simply ignore the return; the generic keeps the
     * concrete parent type for those who don't.
     *
     * @template TParent of HasLifecycleState
     *
     * @param  TParent|null  $parent  the loaded parent spine entity (null when the reference does not resolve — fail-closed)
     * @param  string  $entity  the activating child's canonical entity-type label (e.g. `ProductVariant`) for the rejection copy
     * @param  string  $parentLabel  the parent's canonical entity-type label (e.g. `ProductMaster`) — the entity it is waiting on
     * @return TParent the same parent, now proven `active`
     *
     * @throws ActivationCascadeViolation when the parent is absent or not `active`
     */
    public function assertParentActive(?HasLifecycleState $parent, string $entity, string $parentLabel): HasLifecycleState
    {
        if ($parent === null || $parent->lifecycleState() !== LifecycleState::Active) {
            throw ActivationCascadeViolation::parentNotActive($entity, $parentLabel);
        }

        return $parent;
    }
}
