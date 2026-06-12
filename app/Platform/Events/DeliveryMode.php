<?php

namespace App\Platform\Events;

/**
 * The delivery mode a consumer registers under (foundations-domain-events-audit,
 * design D1/D4).
 *
 * Only `Inline` exists at launch: post-commit, in-process, DB-work-only delivery
 * (design D5). The `queued` mode is deliberately NOT representable here — it
 * arrives with the queue-driver ADR (gate expected F4–F6), at which point this
 * enum grows a `Queued` case and the registry stops rejecting it. Until then a
 * single case makes "queued is gated" a compile-time guarantee, not a runtime
 * branch (the Inline Delivery and Scheduled Sweep requirement, "Queued mode is
 * gated").
 */
enum DeliveryMode: string
{
    case Inline = 'inline';
}
