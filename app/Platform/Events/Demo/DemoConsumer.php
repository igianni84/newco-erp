<?php

namespace App\Platform\Events\Demo;

use App\Platform\Events\Contracts\DomainEventConsumer;
use App\Platform\Events\DomainEvent;
use Illuminate\Support\Facades\DB;

/**
 * The hello-world demo's inline consumer (foundations-domain-events-audit, task 5.1; design D9) — the
 * registered consumer the demo event ({@see DemoCommand::EVENT_NAME}) fans out to, proving the full
 * "DB + event bus + audit trail" pipeline end to end. Like a real module consumer it does DATABASE
 * WORK ONLY (the inline-consumer contract, design D4/D5) and is idempotent: it upserts a marker row
 * into the `cache` table (a platform table, design D9) keyed by the delivered event's UUID, so a
 * test (or a re-run of the demo) can assert the handler's DB effect committed together with the
 * delivery's `done` flip, and a double-delivery window never duplicates the effect.
 *
 * Named, not anonymous, so the container resolves it by FQCN at delivery time exactly as a real
 * module consumer is resolved (the ledger stores only the class name). Lives beside the command
 * under App\Platform\Events\Demo (design D9) — synthetic demonstration code quarantined from the
 * production substrate.
 */
class DemoConsumer implements DomainEventConsumer
{
    /**
     * The `cache`-key prefix for this consumer's observable DB effect. Keyed by the event's UUID, so
     * each delivered event gets its own marker and a re-run never collides.
     */
    public const MARKER_PREFIX = 'events:demo:consumer:';

    public function handle(DomainEvent $event): void
    {
        DB::table('cache')->updateOrInsert(
            ['key' => self::MARKER_PREFIX.$event->event_id],
            ['value' => 'handled', 'expiration' => 9999999999],
        );
    }
}
