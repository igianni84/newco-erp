<?php

namespace App\Platform\Events;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The single write path for `domain_events` (foundations-domain-events-audit, task 3.4; design D3) —
 * the append-only log that is simultaneously the transactional outbox, the inter-module API record,
 * the 10-year audit log for state transitions and the financial event store (event-substrate spec,
 * Transactional Event Recording; CLAUDE.md invariant 4). There is no separate financial-event table:
 * Module E's financial event types are domain events in this same log.
 *
 * record() MUST run inside the caller's already-open transaction: it appends the event AND one
 * `pending` `event_deliveries` row per consumer registered for the event name, all in that one
 * transaction, so the state change and the events recorded with it commit or roll back together
 * (no dual-write). The transaction guard (shared {@see NotInTransactionException}) makes that rule
 * enforced, not advised — recording outside a transaction fails loudly. After the fan-out it
 * registers a post-commit hook (`DB::afterCommit` → {@see InlineDeliveryExecutor}, task 4.1) that
 * runs the just-recorded event's deliveries once the caller's transaction COMMITS — so a rolled-back
 * transaction delivers nothing (Laravel discards uncommitted after-commit callbacks). That inline
 * hook is the fast path, not the durability guarantee: the at-least-once guarantee is the scheduled
 * sweep (task 4.2) over the same `pending` ledger rows, so a crash between commit and the hook loses
 * no event. One hook is registered per record() call (each handing its own event id); multiple
 * records in one transaction attach their hooks to the same transaction record and fire FIFO in
 * recorded (= id) order, preserving causal delivery order.
 *
 * Mirrors the audit recorder's envelope-core assembly (task 3.3) and shares its transaction guard,
 * adding the event-log-only fields: an application-side UUIDv7 `event_id` (the public identity for
 * idempotency keys and cross-references), `correlation_id` defaulting to the event's OWN `event_id`
 * for a root event (NOT an independent fresh UUID — design D3; this is the one place the two
 * recorders' defaults differ), a nullable `causation_id` (the `id` of the causing event), and
 * `schema_version` 1. `module` is a plain `string`, NOT `App\Modules\Module`: the boundary law
 * forbids platform code from depending on `App\Modules` (design D1; arch test
 * `it_forbids_platform_code_from_depending_on_any_module`), so module emitters pass `Module::X->value`
 * and the platform demo passes `'platform'` — same shape as `event_deliveries.consumer` holding a
 * string FQCN. (Design D3 sketched `Module|string`; that realization is refined to `string` here
 * because D1 — the canonical boundary — outranks a D3 realization detail it conflicts with.)
 *
 * Payload discipline is the CALLER's contract (documented + tested, never coerced here): monetary
 * amounts as integer minor units + ISO 4217 currency code, FX rates as decimal strings (never
 * floats — invariants 5/6), entity ids and business data only — never PII (profile data lives in
 * module tables where GDPR erasure operates). The substrate persists the array verbatim through the
 * jsonb cast; a float in an FX field is a caller bug the F1 3/3 value objects will make
 * unrepresentable.
 */
class DomainEventRecorder
{
    public function __construct(
        private readonly ConsumerRegistry $registry,
        private readonly InlineDeliveryExecutor $executor,
    ) {}

    /**
     * Append one domain event to `domain_events` and fan out one `pending` delivery row per
     * registered consumer, all inside the caller's open transaction; returns the persisted event.
     *
     * @param  string  $name  the spec event name, verbatim
     * @param  string  $module  the emitting module's registry value (`Module::X->value`) or `'platform'`
     * @param  array<string, mixed>  $payload  entity ids + business data only — minor-units money, decimal-string FX, no PII
     * @param  string|null  $correlationId  caller-supplied correlation; defaults to the event's own `event_id` (root event)
     * @param  int|null  $causationId  the `id` of the causing event, or null for a root event
     *
     * @throws NotInTransactionException when no database transaction is active
     */
    public function record(
        string $name,
        string $module,
        ActorRole $actorRole,
        ?int $actorId,
        string $entityType,
        string $entityId,
        array $payload,
        ?string $correlationId = null,
        ?int $causationId = null,
    ): DomainEvent {
        if (DB::transactionLevel() === 0) {
            throw NotInTransactionException::forRecording('a domain event');
        }

        $eventId = (string) Str::uuid7();

        $event = DomainEvent::create([
            'event_id' => $eventId,
            'name' => $name,
            'schema_version' => 1,
            'module' => $module,
            'occurred_at' => CarbonImmutable::now('UTC'),
            'actor_role' => $actorRole,
            'actor_id' => $actorId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'correlation_id' => $correlationId ?? $eventId,
            'causation_id' => $causationId,
            'payload' => $payload,
        ]);

        foreach ($this->registry->consumersFor($name) as $consumer) {
            EventDelivery::create([
                'domain_event_id' => $event->id,
                'consumer' => $consumer,
                'status' => DeliveryStatus::Pending,
                'attempts' => 0,
            ]);
        }

        // Inline fast path: run this event's deliveries once the caller's transaction commits. On
        // rollback the callback is discarded (no delivery); the sweep (task 4.2) is the durable
        // at-least-once guarantee over the same pending rows.
        DB::afterCommit(function () use ($event): void {
            $this->executor->deliver([$event->id]);
        });

        return $event;
    }
}
