<?php

namespace App\Platform\Events\Demo;

use App\Platform\Audit\AuditRecord;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Events\ActorContext;
use App\Platform\Events\ConsumerRegistry;
use App\Platform\Events\DomainEvent;
use App\Platform\Events\DomainEventRecorder;
use App\Platform\Events\EventDelivery;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The Workplan Phase 1 hello-world (foundations-domain-events-audit, task 5.1; design D9) — an
 * operator-runnable end-to-end demonstration of "DB + event bus + audit trail" against a migrated
 * database. In ONE transaction it makes a state change, records a synthetic domain event and an
 * audit record (so the three commit atomically); after the commit the registered {@see DemoConsumer}
 * runs inline (the recorder's post-commit hook), its DB effect visible and its delivery row `done`;
 * then it probes the immutability triggers (an UPDATE and a DELETE on `domain_events`, both rejected)
 * and prints the resulting trail, exiting 0. Reusable as a staging smoke probe at the hosting gate.
 *
 * Every identifier here is CLEARLY SYNTHETIC — `PlatformDemoRecorded`, action `platform.demo`,
 * module `'platform'` (the platform pseudo-module) — because verbatim spec event names are reserved
 * for real module events (F2+); the demo must never burn a real name. The flow is re-runnable: the
 * append-only tables simply gain another row each run (each event carries a fresh UUID, so no unique
 * collision), and the state write is an idempotent `cache` upsert.
 *
 * Registered explicitly via withCommands() in bootstrap/app.php — auto-discovery only scans
 * app/Console/Commands, and design D1 keeps platform console commands beside their concern under
 * App\Platform\Events. The demo consumer is registered on the shared {@see ConsumerRegistry}
 * singleton at the top of handle() (idempotent), so the recorder fans out to it without any
 * always-on provider wiring for what is demonstration-only code.
 */
class DemoCommand extends Command
{
    protected $signature = 'events:demo';

    protected $description = 'Run the event-substrate hello-world: record + deliver + audit + immutability probe.';

    /** The synthetic demo event name — never a verbatim spec event name (those are reserved for F2+). */
    public const EVENT_NAME = 'PlatformDemoRecorded';

    /** The synthetic audit action. */
    public const AUDIT_ACTION = 'platform.demo';

    /** The idempotent `cache` key the demo's state change writes (so a re-run upserts, never collides). */
    public const STATE_KEY = 'events:demo:state';

    private const ENTITY_TYPE = 'platform-demo';

    private const ENTITY_ID = '1';

    public function handle(
        DomainEventRecorder $recorder,
        AuditRecorder $auditRecorder,
        ConsumerRegistry $registry,
        ActorContext $actorContext,
    ): int {
        // Register the demo consumer for the synthetic event on the shared registry singleton (the
        // same instance the recorder fans out against). Idempotent, so a re-run is a no-op.
        $registry->register(self::EVENT_NAME, DemoConsumer::class);

        // 1. Atomic record: a state change + a domain event + an audit record, all in ONE transaction,
        //    so they commit or roll back together (the no-dual-write guarantee).
        /** @var array{event: DomainEvent, audit: AuditRecord} $recorded */
        $recorded = DB::transaction(function () use ($recorder, $auditRecorder, $actorContext): array {
            // (a) the state change — an idempotent cache upsert (a platform table, design D9).
            DB::table('cache')->updateOrInsert(
                ['key' => self::STATE_KEY],
                ['value' => 'demo-ran', 'expiration' => 9999999999],
            );

            // (b) the synthetic domain event — fans out a pending delivery for DemoConsumer and (post
            //     task 4.1) registers the inline post-commit delivery hook.
            $event = $recorder->record(
                name: self::EVENT_NAME,
                module: 'platform',
                actorRole: $actorContext->role(),
                actorId: $actorContext->actorId(),
                entityType: self::ENTITY_TYPE,
                entityId: self::ENTITY_ID,
                payload: ['demo' => true, 'amount_minor' => 12000, 'currency' => 'EUR', 'fx_rate' => '1.0842'],
            );

            // (c) the operator-action audit record, correlated to the event it accompanies.
            $audit = $auditRecorder->record(
                action: self::AUDIT_ACTION,
                module: 'platform',
                actorRole: $actorContext->role(),
                actorId: $actorContext->actorId(),
                entityType: self::ENTITY_TYPE,
                entityId: self::ENTITY_ID,
                before: ['state' => 'absent'],
                after: ['state' => 'demo-ran'],
                authorizationBasis: 'platform-demo',
                correlationId: $event->correlation_id,
            );

            return ['event' => $event, 'audit' => $audit];
        });

        $event = $recorded['event'];
        $audit = $recorded['audit'];

        // 2. The commit fired the inline post-commit hook, so the demo event has already been
        //    delivered — read the now-`done` ledger row.
        $delivery = EventDelivery::query()
            ->where('domain_event_id', $event->id)
            ->where('consumer', DemoConsumer::class)
            ->sole();

        // 3. Immutability probes (design D7): an UPDATE and a DELETE against the just-recorded event,
        //    both rejected by the trigger. Caught so the demo reports the rejection and continues.
        $updateProbe = $this->probeRejected(
            fn () => DB::table('domain_events')->where('id', $event->id)->update(['name' => 'tampered']),
        );
        $deleteProbe = $this->probeRejected(
            fn () => DB::table('domain_events')->where('id', $event->id)->delete(),
        );

        // 4. Print the trail.
        $this->info('NewCo ERP event-substrate demo');
        $this->line("Recorded domain event: {$event->name}");
        $this->line("  event_id: {$event->event_id}");
        $this->line('  actor_role: '.$event->actor_role->value);
        $this->line("Recorded audit record: {$audit->action} (id {$audit->id})");
        $this->line("Delivery {$delivery->consumer}: {$delivery->status->value} (attempts {$delivery->attempts})");
        $this->line("Immutability probe UPDATE domain_events: {$updateProbe}");
        $this->line("Immutability probe DELETE domain_events: {$deleteProbe}");
        $this->info('Demo complete.');

        return self::SUCCESS;
    }

    /**
     * Attempt a mutation that the immutability triggers (migration 000004; design D7) MUST reject, and
     * describe the outcome for the trail. The trigger raises a {@see QueryException} whose message
     * contains the stable token `immutable`, so the expected outcome is the caught exception; the
     * "UNEXPECTEDLY ALLOWED" branch is unreachable while the triggers stand (proven by
     * ImmutabilityTest) and exists only so the demo surfaces a regression loudly rather than silently.
     *
     * @param  Closure(): mixed  $mutation
     */
    private function probeRejected(Closure $mutation): string
    {
        try {
            $mutation();

            return 'UNEXPECTEDLY ALLOWED';
        } catch (QueryException $exception) {
            return str_contains($exception->getMessage(), 'immutable')
                ? 'rejected (immutable)'
                : 'rejected';
        }
    }
}
