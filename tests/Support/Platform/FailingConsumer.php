<?php

namespace Tests\Support\Platform;

use App\Platform\Events\Contracts\DomainEventConsumer;
use App\Platform\Events\DomainEvent;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A test-double consumer (foundations-domain-events-audit, task 4.1) that writes a `cache` marker
 * and THEN throws. It proves two delta-spec properties: (a) R4 fan-out isolation — its failure
 * leaves a sibling consumer's delivery row untouched and the emitter's committed data intact; and
 * (b) exactly-once-for-DB-effects on the failure side — because the handler and the status flip
 * share one transaction, its marker write rolls back with that transaction, so no partial effect
 * survives a failed handler.
 */
class FailingConsumer implements DomainEventConsumer
{
    public const FAILURE_MESSAGE = 'failing consumer boom';

    public function handle(DomainEvent $event): void
    {
        DB::table('cache')->insert([
            'key' => 'consumer:failing:'.$event->id,
            'value' => 'should-roll-back',
            'expiration' => 9999999999,
        ]);

        throw new RuntimeException(self::FAILURE_MESSAGE);
    }
}
