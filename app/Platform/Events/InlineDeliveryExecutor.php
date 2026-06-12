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
 * The launch delivery engine (foundations-domain-events-audit, task 4.1; design D5) — the single
 * code path that runs `event_deliveries` rows, shared by the inline post-commit hook (wired in
 * {@see DomainEventRecorder}) and, post task 4.2, the scheduled sweep. Implements the delta-spec
 * Inline Delivery and Per-Consumer Delivery Ledger requirements: exactly-once for DB effects,
 * per-consumer failure isolation (R4), exponential backoff with dead-letter, and done-is-terminal.
 *
 * deliver() selects the DUE `pending` deliveries (NULL `available_at` = due now; a future
 * `available_at` is a row still in backoff and is skipped) for the given event ids, ordered by
 * `domain_event_id` then `id` — recorded/causal order (Module A § 12.4) — and runs each through
 * attempt(). The inline hook hands it the just-committed event's id; a directly-invoked deliver()
 * (and task 4.2's sweep, over its own due selection) reuses the same per-delivery logic.
 *
 * attempt() is the exactly-once-for-DB-effects core: the consumer's handler AND the `done` status
 * flip (attempts+1) share ONE database transaction, so they commit together or not at all. A handler
 * throw rolls that transaction back — discarding any partial DB effect the handler made — and is
 * then recorded as a failure in a SEPARATE write: attempts+1, a fresh `available_at` backoff, the
 * truncated `last_error`, and status `failed` once attempts reach the configured maximum (dead-letter
 * in place). The try/catch is per delivery, so one consumer's failure never touches a sibling's row
 * nor the emitter's already-committed data (R4 mechanized). A `done` row is terminal by query
 * construction (the `status = pending` filter excludes it), so re-running the executor never
 * re-invokes a completed handler.
 *
 * Tunables come from `config('events.sweep.*')` with the design-D6 defaults baked into the config()
 * fallbacks, so the executor is self-sufficient before task 4.2 adds `config/events.php` (which only
 * makes the same numbers explicit and env-overridable). Consumers are container-resolved by FQCN at
 * delivery time (design D4) — the ledger stores only the class name.
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

        $now = CarbonImmutable::now('UTC');

        $deliveries = EventDelivery::query()
            ->whereIn('domain_event_id', $domainEventIds)
            ->where('status', DeliveryStatus::Pending->value)
            ->where(function (Builder $query) use ($now): void {
                // Due = no backoff clock, or its backoff has elapsed.
                $query->whereNull('available_at')->orWhere('available_at', '<=', $now);
            })
            ->orderBy('domain_event_id')   // events delivered in recorded/causal order (A § 12.4)
            ->orderBy('id')                // within one event, consumers in fan-out (registration) order
            ->get();

        foreach ($deliveries as $delivery) {
            $this->attempt($delivery);
        }
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
                $consumer = $this->resolveConsumer($delivery->consumer);
                $consumer->handle(DomainEvent::findOrFail($delivery->domain_event_id));

                $delivery->update([
                    'status' => DeliveryStatus::Done,
                    'attempts' => $delivery->attempts + 1,
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

        $delivery->update([
            'attempts' => $attempts,
            'status' => $attempts >= $maxAttempts ? DeliveryStatus::Failed : DeliveryStatus::Pending,
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
