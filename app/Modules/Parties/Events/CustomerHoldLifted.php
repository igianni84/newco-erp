<?php

namespace App\Modules\Parties\Events;

use App\Modules\Parties\Models\Hold;

/**
 * `CustomerHoldLifted` — recorded when a Hold is lifted (parties-holds, design L4; party-registry — Requirement:
 * Hold Events). The verbatim § 15.1 event name — the second and last of the two Hold events the PRD catalog
 * names; like {@see CustomerHoldPlaced} it is recorded for a Hold of EVERY scope (the `scope_type` + `scope_id`
 * in the payload distinguish the scope — design L4). Recorded by BOTH lift paths inside the lifting transaction:
 * the operator `LiftHold` action (task 3.2 — `admin`/`fraud`/`compliance`/`credit` Holds) and the system
 * auto-lift `RecordKycVerified` drives for the `kyc` Hold (task 4.1). The per-type discipline routes which path
 * may lift which type (ADR 2026-06-18-hold-lift-discipline-per-type.md), but both record THIS one name.
 *
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Hold;
 *   - {@see payload()} — the PII-free Hold payload (the lift variant carries `lift_reason`).
 */
final class CustomerHoldLifted
{
    /** The verbatim § 15.1 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'CustomerHoldLifted';

    /** The envelope `entity_type` for a Hold (the Hold is the subject; the scope is carried in the payload). */
    public const ENTITY_TYPE = 'Hold';

    /**
     * The lift payload — STRICT PII-free, mirroring {@see CustomerHoldPlaced::payload()} but carrying the
     * `lift_reason` (the lift-time business reason) in place of the placement `reason`. Only the Hold `id`, its
     * `hold_type`, the polymorphic scope (`scope_type` + `scope_id`) and the controlled business `lift_reason`
     * (design L5 — a controlled business string, never PII) — no name, email, phone or date of birth. The
     * `hold_type` / `scope_type` are non-nullable enum casts, read as their `->value` token directly.
     *
     * @return array<string, mixed>
     */
    public static function payload(Hold $hold): array
    {
        return [
            'hold_id' => $hold->id,
            'hold_type' => $hold->hold_type->value,
            'scope_type' => $hold->scope_type->value,
            'scope_id' => $hold->scope_id,
            'lift_reason' => $hold->lift_reason,
        ];
    }
}
