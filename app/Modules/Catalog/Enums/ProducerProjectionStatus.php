<?php

namespace App\Modules\Catalog\Enums;

/**
 * The status domain of the Catalog-owned producer-state projection
 * (catalog-lifecycle-approval, design D3/D4; product-catalog — Requirement:
 * Producer-State Projection and Event Consumption).
 *
 * This is NOT Module K's producer lifecycle and NOT a cross-module contract: it
 * is a Catalog-LOCAL read model fed by the `ProducerLifecycleProjector` consumer
 * (task 1.2) as it consumes the supply-side events `ProducerActivated`/`ProducerRetired`
 * (the only Catalog ↔ Parties coupling — the event payload, never a Module K
 * query, invariant 10). The *Producer Activation Gate* reads exactly this status
 * off the `catalog_producer_states` projection to decide whether a Product Master
 * may transition `reviewed → active`.
 *
 * Two cases only — the projection cares about exactly the two gate-relevant
 * producer states the consumer is fed: `active` (Master activation ENABLED
 * against this producer) and `retired` (new Master activation BLOCKED; existing
 * actives preserved — block-new, never cascade-retire). The full four-state
 * producer/spine lifecycle ({@see LifecycleState}) is deliberately NOT mirrored
 * here: a producer is only ever projected from a `ProducerActivated`/`Retired`
 * event, so `draft`/`reviewed` never reach this read model, and a producer with
 * NO row is treated by the gate as "not gated open" (rejected).
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
    case Active = 'active';
    case Retired = 'retired';
}
