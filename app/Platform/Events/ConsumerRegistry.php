<?php

namespace App\Platform\Events;

use App\Platform\Events\Contracts\DomainEventConsumer;
use InvalidArgumentException;

/**
 * The event-name → consumers map on the provider seam (foundations-domain-events-audit,
 * design D4).
 *
 * Module service providers call register() from boot() to declare which consumers handle
 * which domain events; the DomainEventRecorder (task 3.4) reads consumersFor() inside the
 * emitting transaction to fan out one pending event_deliveries row per registered consumer.
 * Bound as a container singleton (AppServiceProvider) so every provider's boot() and the
 * delivery path share ONE instance; the consumers themselves are container-resolved at
 * delivery time — the ledger stores only the class FQCN.
 *
 * Consumer identity in the ledger is the consumer class FQCN (design D4): simple and
 * collision-free, no extra API. Trade-off accepted — renaming a consumer class orphans its
 * non-terminal event_deliveries rows, so a rename must migrate event_deliveries.consumer in
 * the same change (documented in docs/event-substrate.md, task 6.1).
 *
 * Registering the same (event, consumer) pair twice is IDEMPOTENT — the first registration
 * wins, repeats are no-ops. This keeps consumersFor() duplicate-free so the recorder never
 * attempts two identical delivery rows, which the unique (domain_event_id, consumer) index
 * would reject and so fail the whole emitting transaction.
 *
 * Delivery mode: only DeliveryMode::Inline exists at launch (design D4/D5), so $mode is the
 * forward-compatible API surface that lets module providers spell out the mode at the call
 * site today without an API change when the queue ADR lands. The queued mode is gated behind
 * the queue-driver ADR (F4–F6) as a COMPILE-TIME guarantee: the single-case enum makes a
 * non-inline registration unrepresentable, so no runtime branch exists. When DeliveryMode
 * grows a Queued case this method must reject it until queued delivery is wired — the gate
 * test in ConsumerRegistryTest tracks that boundary.
 */
class ConsumerRegistry
{
    /**
     * Event name → registered consumer FQCNs, in registration order.
     *
     * @var array<string, list<class-string<DomainEventConsumer>>>
     */
    private array $consumers = [];

    public function register(string $eventName, string $consumerClass, DeliveryMode $mode = DeliveryMode::Inline): void
    {
        if (! is_subclass_of($consumerClass, DomainEventConsumer::class)) {
            throw new InvalidArgumentException(
                "Consumer [{$consumerClass}] must implement ".DomainEventConsumer::class.'.'
            );
        }

        if (in_array($consumerClass, $this->consumers[$eventName] ?? [], true)) {
            return;
        }

        $this->consumers[$eventName][] = $consumerClass;
    }

    /**
     * @return list<class-string<DomainEventConsumer>>
     */
    public function consumersFor(string $eventName): array
    {
        return $this->consumers[$eventName] ?? [];
    }
}
