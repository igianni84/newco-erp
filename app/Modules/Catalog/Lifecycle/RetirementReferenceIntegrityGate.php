<?php

namespace App\Modules\Catalog\Lifecycle;

use App\Modules\Catalog\Actions\RetireProductMasterCascade;
use App\Modules\Catalog\Exceptions\RetirementReferenceIntegrityViolation;

/**
 * The retirement reference-integrity gate — the WITHIN-MODULE precondition on a SINGLE-entity `active → retired`
 * retire (catalog-lifecycle-approval, design D8; product-catalog — Requirement: Retirement Cascade and Reference
 * Integrity; Module 0 PRD § 4.6, BR-Lifecycle-5). An entity SHALL NOT be retired out from under an `active`
 * terminal sellable object that still references it: a Product Reference referenced by an `active` Sellable /
 * Composite SKU, or a Case Configuration referenced by an `active` Sellable SKU. The per-entity Retire action
 * (`RetireProductReference` / `RetireCaseConfiguration`) passes a closure that loads the entity's `active`
 * referencers and calls {@see assertNoActiveReferencers()} to the shared {@see LifecycleTransition} mechanism,
 * which evaluates it AFTER the approval-governance operator floor and BEFORE the state write, inside the
 * transition's transaction; a breach throws {@see RetirementReferenceIntegrityViolation} and the whole
 * transition rolls back, so the entity stays `active` and records no `*Retired`.
 *
 * This is the retirement-side sibling of {@see ActivationCascadeGate}: that gate (a child's parent must be
 * `active` to ACTIVATE) reads a sibling spine entity's `lifecycle_state` directly; this gate (a referenced
 * entity must have no `active` SKU referencer to RETIRE) reads the within-module SKU rows that point at it.
 * Both reads are WITHIN Module 0 (no projection, no cross-module read; invariant 10 untouched). The scope is
 * the TERMINAL SELLABLE EDGE only (Option B, `decisions/2026-06-16-catalog-retirement-reference-integrity-scope.md`):
 * a hierarchy parent (a Master with `active` Variants, a Variant with `active` PRs) is NOT guarded — it
 * preserves its children, so only the two referenced-entity Actions wire this gate.
 *
 * The check is a pure decision over the already-loaded referencer set — the per-entity Action owns the
 * within-module read (it knows the relationship: a Sellable SKU's `product_reference_id` /
 * `case_configuration_id`, a Composite SKU's constituent junction) and feeds the open-reference tokens here.
 * The read is intentionally lock-free (a read-time gate, mirroring the activation cascade): the guard blocks a
 * retire that would orphan an `active` SKU; it never cascade-retires those SKUs (that is the operator-driven
 * {@see RetireProductMasterCascade}).
 */
class RetirementReferenceIntegrityGate
{
    /**
     * Assert that no `active` terminal sellable object still references the entity being retired, else reject
     * the retire and surface the open references.
     *
     * @param  array<int, string>  $openReferences  the entity's `active` referencers as entity-type + id tokens (e.g. `['SellableSku#5']`) — empty ⇒ clear to retire
     * @param  string  $entity  the entity-type label being retired (e.g. `ProductReference`) for the rejection copy
     *
     * @throws RetirementReferenceIntegrityViolation when one or more `active` references remain
     */
    public function assertNoActiveReferencers(array $openReferences, string $entity): void
    {
        if ($openReferences !== []) {
            throw RetirementReferenceIntegrityViolation::blockedByActiveReferences($entity, $openReferences);
        }
    }
}
