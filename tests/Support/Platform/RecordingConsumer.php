<?php

namespace Tests\Support\Platform;

use App\Platform\Events\Contracts\DomainEventConsumer;
use App\Platform\Events\DomainEvent;
use Illuminate\Support\Facades\DB;

/**
 * A test-double domain-event consumer (foundations-domain-events-audit, task 4.1) that performs
 * OBSERVABLE database work only — the inline-consumer contract (design D4/D5). It appends each
 * delivered event's id to a static sink (so a test can assert handler invocation count and causal
 * order) and writes a marker row to the `cache` table (a platform table, design D9 — so a test can
 * assert the handler's DB effect committed together with the delivery's `done` flip).
 *
 * Named, not anonymous, so the container resolves it by FQCN at delivery time exactly as a real
 * module consumer is resolved. The sink is process state — tests reset it in beforeEach (the DB is
 * reset by the trait, statics are not).
 */
class RecordingConsumer implements DomainEventConsumer
{
    /** @var list<int> */
    public static array $handled = [];

    public function handle(DomainEvent $event): void
    {
        self::$handled[] = $event->id;

        DB::table('cache')->insert([
            'key' => 'consumer:recording:'.$event->id,
            'value' => 'ran',
            'expiration' => 9999999999,
        ]);
    }
}
