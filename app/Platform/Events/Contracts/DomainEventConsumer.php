<?php

namespace App\Platform\Events\Contracts;

use App\Platform\Events\DomainEvent;

/**
 * The one interface a domain-event consumer implements (foundations-domain-events-audit,
 * design D4). F2+ module listeners implement this and register on the provider seam via
 * ConsumerRegistry::register(); the InlineDeliveryExecutor (task 4.1) resolves the
 * implementing class from the container and calls handle() once per delivered event.
 *
 * The handler receives the PERSISTED envelope (the DomainEvent model — id, name, payload,
 * occurred_at, …), not a transient object: delivery flows through the event_deliveries
 * ledger, never the framework event bus.
 *
 * Consumer obligations (documented here, detailed in docs/event-substrate.md, task 6.1):
 * - DB work only — no external I/O inside handle(). At-least-once delivery means handle()
 *   can run more than once for the same event, so it MUST be idempotent and tolerant of
 *   out-of-order arrival (e.g. a per-entity watermark). The handler's writes and the
 *   delivery's status flip share one transaction (exactly-once for DB effects, design D5).
 * - External calls follow Module E § 7's shape: handle() records INTENT in a module table,
 *   and a module-owned scheduled processor performs the call (F2+ scope).
 */
interface DomainEventConsumer
{
    public function handle(DomainEvent $event): void;
}
