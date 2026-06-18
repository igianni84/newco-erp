<?php

namespace App\Modules\Parties\Events;

use App\Modules\Parties\Models\Hold;

/**
 * `CustomerHoldPlaced` — recorded when a Hold is placed on a scope (parties-holds, design L4; party-registry —
 * Requirement: Hold Events). The verbatim § 15.1 event name — one of the only two Hold events the PRD catalog
 * names. Because the catalog names no `ProfileHoldPlaced` / `AccountHoldPlaced` variant, this single name is
 * recorded for a Hold of EVERY scope (the `scope_type` + `scope_id` in the payload distinguish a Customer-,
 * Account- or Profile-scoped Hold — the zero-invention reading of AC-K-FSM-10's "or Profile/Account analogs",
 * design L4).
 *
 * Recorded by the `PlaceHold` action (task 3.1) — and, for the auto `kyc` Hold, by `RequireKyc` reusing that
 * path (task 4.1) — inside the same transaction as the `parties_holds` write, tagged module `parties`, entity
 * type `Hold`, with the actor resolved from the `ActorContext` seam. The class is the single source of truth for
 * the event's three contract facets, so the action stays thin and free of magic strings:
 *   - {@see NAME} — the canonical event name passed to the DomainEventRecorder;
 *   - {@see ENTITY_TYPE} — the envelope `entity_type` for a Hold;
 *   - {@see payload()} — the PII-free Hold payload.
 */
final class CustomerHoldPlaced
{
    /** The verbatim § 15.1 event name — the inter-module contract key recorded in `domain_events.name`. */
    public const NAME = 'CustomerHoldPlaced';

    /** The envelope `entity_type` for a Hold (the Hold is the subject; the scope is carried in the payload). */
    public const ENTITY_TYPE = 'Hold';

    /**
     * The placement payload — STRICT PII-free (decisions/2026-06-12-event-substrate-and-audit-store.md; the
     * 10-year audit store holds no personal data). It carries only the Hold `id`, its `hold_type`, the
     * polymorphic scope (`scope_type` + `scope_id`) and the controlled business `reason` (design L5 — a
     * controlled business string, never PII; system-placed Holds carry a NULL reason, so the auto `kyc` Hold's
     * payload reads `reason => null`). No name, email, phone or date of birth: the Hold model carries none, and a
     * consumer needing personal data reads it through a published read contract, never by widening this payload.
     * The `hold_type` / `scope_type` are non-nullable enum casts, read as their `->value` token directly.
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
            'reason' => $hold->reason,
        ];
    }
}
