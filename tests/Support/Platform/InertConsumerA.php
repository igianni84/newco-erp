<?php

namespace Tests\Support\Platform;

use App\Platform\Events\Contracts\DomainEventConsumer;
use App\Platform\Events\DomainEvent;

/**
 * A named, inert test-double domain-event consumer (foundations-domain-events-audit). It implements the
 * consumer contract with a no-op handle() and exists only so a test can register two DISTINCT, NAMED
 * consumer FQCNs for one event name and assert the recorder's per-consumer fan-out.
 *
 * Why NAMED, not an anonymous `new class`: an anonymous-class FQCN is `class@anonymous\0<path>:<line>`
 * — it contains a NUL byte. PostgreSQL text/varchar truncates a value at its first NUL, so two distinct
 * anonymous consumers collapse to the SAME stored `consumer` string and then collide on the
 * unique(domain_event_id, consumer) ledger index (a false 23505). Named classes — as every real module
 * consumer is — have stable, NUL-free FQCNs that round-trip identically on SQLite and PostgreSQL.
 */
class InertConsumerA implements DomainEventConsumer
{
    public function handle(DomainEvent $event): void {}
}
