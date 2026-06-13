<?php

namespace App\Platform\Events;

use App\Platform\Events\Contracts\DomainEventConsumer;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The launch delivery engine (foundations-domain-events-audit, tasks 4.1–4.2; design D5/D6) — the
 * single code path that runs `event_deliveries` rows, shared by the inline post-commit hook (wired
 * in {@see DomainEventRecorder}) and the scheduled sweep ({@see SweepCommand}). Implements the
 * delta-spec Inline Delivery and Per-Consumer Delivery Ledger requirements: exactly-once for DB
 * effects, per-consumer failure isolation (R4), exponential backoff with dead-letter, done-is-terminal.
 *
 * Two entry points share one per-delivery core (attempt()) and one "due" definition (dueDeliveries()):
 *   - deliver(array $eventIds) — the inline hook's path: the just-committed events' deliveries, in
 *     `domain_event_id` then `id` order (recorded/causal order, Module A § 12.4).
 *   - deliverDue() — the sweep's path: ALL due deliveries, ordered `(consumer, domain_event_id)`
 *     (design D6) so each consumer's stream drains in recorded order with no per-consumer FIFO
 *     blocking — a poison row in backoff never stalls a later event for the same consumer.
 * A delivery is DUE when it is `pending` and its backoff has elapsed (NULL `available_at` = due now;
 * a future `available_at` is a row still in backoff and is skipped).
 *
 * attempt() is the exactly-once-for-DB-effects core: the consumer's handler AND the `done` status
 * flip (attempts+1) share ONE database transaction, so they commit together or not at all. A handler
 * throw rolls that transaction back — discarding any partial DB effect the handler made — and is
 * then recorded as a failure in a SEPARATE write: attempts+1, a fresh `available_at` backoff, the
 * truncated `last_error`, and status `failed` once attempts reach the configured maximum (dead-letter
 * in place). The try/catch is per delivery, so one consumer's failure never touches a sibling's row
 * nor the emitter's already-committed data (R4 mechanized). A `done` row is terminal: the due query
 * excludes it for the single-runner re-run, and under inline-vs-sweep concurrency attempt() re-reads
 * the row under a lock (skipping it if a sibling already won) while recordFailure() writes only when
 * the row is still `pending` — so a completed delivery is never re-invoked nor resurrected to
 * `pending`/`failed` by a contending runner (C1).
 *
 * Tunables come from `config/events.php` (`events.sweep.*`, task 4.2) read via Config::integer with
 * the design-D6 defaults baked into the fallbacks, so the executor stays correct even if a key is
 * unset. Consumers are container-resolved by FQCN at delivery time (design D4) — the ledger stores
 * only the class name.
 */
class InlineDeliveryExecutor
{
    public function __construct(private readonly Container $container) {}

    /**
     * Execute the due `pending` deliveries for the given domain-event ids, in causal (`id`) order.
     *
     * @param  list<int>  $domainEventIds  `domain_events.id` values whose pending deliveries to run
     */
    public function deliver(array $domainEventIds): void
    {
        if ($domainEventIds === []) {
            return;
        }

        $deliveries = $this->dueDeliveries()
            ->whereIn('domain_event_id', $domainEventIds)
            ->orderBy('domain_event_id')   // events delivered in recorded/causal order (A § 12.4)
            ->orderBy('id')                // within one event, consumers in fan-out (registration) order
            ->get();

        foreach ($deliveries as $delivery) {
            $this->attempt($delivery);
        }
    }

    /**
     * Execute ALL due deliveries — the scheduled sweep's entry point ({@see SweepCommand}, task 4.2;
     * design D6). It is the at-least-once guarantee behind the inline fast path: it picks up
     * deliveries whose inline execution never ran (a crash between the emitting commit and the hook)
     * and retryable failures whose backoff has elapsed. Ordered `(consumer, domain_event_id)` so each
     * consumer's stream drains in recorded order; a poison row still in backoff is simply not due and
     * is skipped, never blocking a later event for that same consumer (no per-consumer FIFO).
     */
    public function deliverDue(): void
    {
        $deliveries = $this->dueDeliveries()
            ->orderBy('consumer')          // sweep order (design D6): per consumer…
            ->orderBy('domain_event_id')   // …then in recorded order within that consumer
            ->get();

        foreach ($deliveries as $delivery) {
            $this->attempt($delivery);
        }
    }

    /**
     * The base query for deliveries due to run now, shared by deliver() (the inline hook's
     * event-scoped run) and deliverDue() (the sweep's global run) so the "due" definition can never
     * drift between the two paths: `pending` (a `done`/`failed` row is terminal and excluded) whose
     * backoff window has elapsed (no `available_at`, or it is now in the past).
     *
     * @return Builder<EventDelivery>
     */
    private function dueDeliveries(): Builder
    {
        $now = CarbonImmutable::now('UTC');

        return EventDelivery::query()
            ->where('status', DeliveryStatus::Pending->value)
            ->where(function (Builder $query) use ($now): void {
                // Due = no backoff clock, or its backoff has elapsed.
                $query->whereNull('available_at')->orWhere('available_at', '<=', $now);
            });
    }

    /**
     * Run one delivery: the handler and the `done` flip in a single transaction (exactly-once for DB
     * effects); on any throw, roll that back and record the failure separately so a poison consumer
     * never stalls the loop.
     */
    private function attempt(EventDelivery $delivery): void
    {
        try {
            DB::transaction(function () use ($delivery): void {
                // Re-read the row under a row-level lock inside the delivery transaction. The inline
                // hook and the scheduled sweep both run this ledger and can both pick up the same
                // `pending` row before either flips it; lockForUpdate() serializes the winner (a real
                // FOR UPDATE on PostgreSQL, a documented no-op on single-writer SQLite). If a sibling
                // already won it — the row is gone or no longer `pending` — bail without invoking the
                // handler: `done` is terminal and SHALL never re-execute (C1).
                $locked = EventDelivery::query()
                    ->whereKey($delivery->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($locked === null || $locked->status !== DeliveryStatus::Pending) {
                    return;
                }

                $consumer = $this->resolveConsumer($locked->consumer);
                $consumer->handle(DomainEvent::findOrFail($locked->domain_event_id));

                $locked->update([
                    'status' => DeliveryStatus::Done,
                    'attempts' => $locked->attempts + 1,
                ]);
            });
        } catch (Throwable $exception) {
            // The handler transaction rolled back; resync the model to its persisted (pre-attempt)
            // state before recording the failure in a fresh write, so attempts counts from the
            // committed value rather than the in-memory mutation the rolled-back update() left behind.
            $delivery->refresh();
            $this->recordFailure($delivery, $exception);
        }
    }

    private function resolveConsumer(string $consumerClass): DomainEventConsumer
    {
        $consumer = $this->container->make($consumerClass);

        if (! $consumer instanceof DomainEventConsumer) {
            throw new RuntimeException(
                "Consumer [{$consumerClass}] must implement ".DomainEventConsumer::class.'.'
            );
        }

        return $consumer;
    }

    /**
     * Record a failed attempt: increment attempts, set the next backoff window, truncate the error,
     * and dead-letter (`failed`) once the configured maximum is reached.
     */
    private function recordFailure(EventDelivery $delivery, Throwable $exception): void
    {
        $attempts = $delivery->attempts + 1;
        $maxAttempts = max(1, Config::integer('events.sweep.max_attempts', 5));

        // Conditional, `status = pending`-guarded write (NOT a model update on $delivery): if a sibling
        // runner completed this delivery `done` between the failed attempt and here, the predicate
        // matches no row, so the terminal `done` is never resurrected to `pending`/`failed` (C1). On the
        // ordinary path the row is still `pending` and the update applies exactly as before. Values are
        // passed in their stored form — a query-builder update runs no model casts, so the enum is its
        // backing string and the CarbonImmutable is formatted by the connection grammar to the same
        // `Y-m-d H:i:s` the `immutable_datetime` cast would write (the column round-trips identically on
        // both engines).
        EventDelivery::query()
            ->whereKey($delivery->getKey())
            ->where('status', DeliveryStatus::Pending->value)
            ->update([
                'attempts' => $attempts,
                'status' => ($attempts >= $maxAttempts ? DeliveryStatus::Failed : DeliveryStatus::Pending)->value,
                'available_at' => CarbonImmutable::now('UTC')->addSeconds($this->backoffSeconds($attempts)),
                'last_error' => Str::limit($exception->getMessage(), 1000),
            ]);
    }

    /**
     * Exponential backoff: base · 2^(attempts-1), capped (design D6). Defaults 30s base, 3600s cap.
     */
    private function backoffSeconds(int $attempts): int
    {
        $base = max(0, Config::integer('events.sweep.backoff_base_seconds', 30));
        $cap = max(0, Config::integer('events.sweep.backoff_cap_seconds', 3600));

        return (int) min($base * (2 ** ($attempts - 1)), $cap);
    }
}
