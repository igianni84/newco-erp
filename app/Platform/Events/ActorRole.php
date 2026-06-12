<?php

namespace App\Platform\Events;

/**
 * The actor provenance recorded on every envelope (foundations-domain-events-audit,
 * design D1/D2).
 *
 * `actor_role` is mandatory on both substrate tables — the audit-envelope floor of
 * CLAUDE.md invariant 8 ("every operator action records actor_role") and the
 * Domain Event Envelope requirement. The four roles are the spec's verbatim set
 * (Architecture § 5.3); the value set is enforced in the application by casting the
 * column to this enum on both engines, with a DB CHECK added on PostgreSQL only
 * (design D2 — SQLite cannot ALTER TABLE … ADD CHECK).
 *
 * - case name   = PascalCase symbol (App\Platform vocabulary)
 * - backing value = the persisted snake_case token (the column value)
 */
enum ActorRole: string
{
    case NewcoOps = 'newco_ops';
    case Producer = 'producer';
    case Customer = 'customer';
    case System = 'system';
}
