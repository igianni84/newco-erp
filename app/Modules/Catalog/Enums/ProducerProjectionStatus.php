<?php

namespace App\Modules\Catalog\Enums;

/**
 * The status domain of the Catalog-owned producer-state projection
 * (catalog-lifecycle-approval, design D3/D4; catalog-module-0-completeness-sweep,
 * design D7; product-catalog — Requirement: Producer-State Projection and Event
 * Consumption).
 *
 * This is NOT Module K's producer lifecycle and NOT a cross-module contract: it
 * is a Catalog-LOCAL read model fed by the `ProducerLifecycleProjector` consumer
 * as it consumes the supply-side events `ProducerCreated`/`ProducerActivated`/
 * `ProducerRetired` (the only Catalog ↔ Parties coupling — the event payload,
 * never a Module K query, invariant 10). Two Catalog rules read it: the *Producer
 * Activation Gate* ("may a Product Master go `reviewed → active` under this
 * producer?") and `CreateProductMaster`'s existence guard ("is this producer
 * KNOWN to Catalog at all?").
 *
 * Three cases — one per consumed event — and the two readers ask at DIFFERENT
 * granularities:
 *   - `registered` — the producer EXISTS (a `ProducerCreated` was consumed).
 *     Product Master creation is admitted against it; activation is NOT gated open.
 *   - `active` — Master activation against this producer is ENABLED.
 *   - `retired` — new Master activation BLOCKED; existing actives preserved
 *     (block-new, never cascade-retire).
 *
 * So **existence ≠ activeness**: ONLY `Active` opens the gate, while ANY row at
 * all admits creation. A producer with NO row is unknown to Catalog — creation is
 * rejected, and the gate stays fail-closed.
 *
 * The full four-state producer/spine lifecycle ({@see LifecycleState}) is still
 * deliberately NOT mirrored here: `reviewed` has no Catalog consequence, so no
 * event ever carries a producer into this read model under that name.
 *
 * Ordered by producer-lifecycle progression (`registered → active → retired`),
 * mirroring {@see LifecycleState}'s house style and the verbatim status domain of
 * the *Producer-State Projection and Event Consumption* Requirement. Nothing reads
 * `cases()` by ORDER — only the Postgres CHECK derives from the SET.
 *
 * Mirrors the house enum style ({@see LifecycleState}, {@see ProductType},
 * `Module`); stored as a `status` string column on the projection with a
 * driver-guarded Postgres CHECK derived from `cases()` (the
 * `domain_events.actor_role` / spine `lifecycle_state` idiom) plus this cast.
 *
 * - case name    = the projected status in PascalCase (Catalog vocabulary)
 * - backing value = the persisted token (the column value)
 */
enum ProducerProjectionStatus: string
{
    case Registered = 'registered';
    case Active = 'active';
    case Retired = 'retired';
}
