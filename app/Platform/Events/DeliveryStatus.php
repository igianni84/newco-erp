<?php

namespace App\Platform\Events;

/**
 * The per-consumer delivery lifecycle in `event_deliveries` (foundations-domain-events-audit,
 * design D2/D5).
 *
 * One row per (event × registered consumer) carries one of these states (the
 * Per-Consumer Delivery Ledger requirement):
 *
 * - Pending — recorded in the emitting transaction, awaiting (or retrying) delivery.
 * - Done    — the handler ran and its status flip committed in one transaction
 *             (exactly-once for DB effects); terminal.
 * - Failed  — dead-letter: the configured maximum attempts was reached; terminal,
 *             stays for the later operator-retry surface.
 *
 * The column is cast to this enum; the value set is enforced application-side on
 * both engines (design D2).
 */
enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Done = 'done';
    case Failed = 'failed';
}
