<?php

namespace App\Modules\Catalog\Lifecycle;

use App\Modules\Catalog\Enums\LifecycleState;

/**
 * Marks a product-catalog spine entity as lifecycle-bearing — it can report its current `lifecycle_state`
 * (design D1; product-catalog — Requirement: Product Lifecycle State Machine).
 *
 * The shared {@see LifecycleTransition} mechanism is the SOLE writer of `lifecycle_state` across all seven
 * spine entities (Product Master, Product Variant, Product Reference, Format, Case Configuration, Sellable
 * SKU, Composite SKU). It is generic over "a Model that has a `lifecycle_state`": this interface gives that
 * genericity a typed contract, so the mechanism reads the state through an interface method (not a concrete
 * model) and stays ONE shared place rather than seven bespoke copies.
 *
 * The `lifecycleState()` getter is a pure READ accessor over the cast attribute — NOT a transition method: the
 * models stay persistence-only (the mechanism is the only writer, design D1). Every spine model already
 * carries the `lifecycle_state` enum-cast column (shipped from the spine), so an entity opts in by
 * implementing this one-line getter. The typed method (rather than a magic `@property`) keeps the
 * mechanism's `$model->lifecycleState()` read statically resolvable through the `Model & HasLifecycleState`
 * intersection.
 */
interface HasLifecycleState
{
    /** The entity's current lifecycle state (its `lifecycle_state` cast attribute). */
    public function lifecycleState(): LifecycleState;
}
