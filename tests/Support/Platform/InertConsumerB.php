<?php

namespace Tests\Support\Platform;

use App\Platform\Events\Contracts\DomainEventConsumer;
use App\Platform\Events\DomainEvent;

/**
 * The second named, inert test-double consumer — see {@see InertConsumerA} for why a registered
 * consumer identity must be a named class (NUL-free FQCN) rather than an anonymous `new class`.
 */
class InertConsumerB implements DomainEventConsumer
{
    public function handle(DomainEvent $event): void {}
}
