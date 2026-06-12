<?php

use App\Platform\Events\ConsumerRegistry;
use App\Platform\Events\Contracts\DomainEventConsumer;
use App\Platform\Events\DeliveryMode;
use App\Platform\Events\DomainEvent;

// Pins the consumer registry on the provider seam (foundations-domain-events-audit,
// task 3.2; design D4). Module providers register (event → consumer) pairs in boot(); the
// recorder (task 3.4) reads consumersFor() to fan out one pending delivery per consumer.
// Pure in-memory behavior, so the registry is newed up directly — the container-singleton
// binding is pinned by the sibling Feature test (Unit tests are plain PHPUnit, no app).

it('returns registered consumers per event name in registration order', function () {
    $registry = new ConsumerRegistry;

    $first = new class implements DomainEventConsumer
    {
        public function handle(DomainEvent $event): void {}
    };
    $second = new class implements DomainEventConsumer
    {
        public function handle(DomainEvent $event): void {}
    };

    $registry->register('OrderPlaced', $first::class);
    $registry->register('OrderPlaced', $second::class);

    expect($registry->consumersFor('OrderPlaced'))->toBe([$first::class, $second::class]);
});

it('returns an empty array for an event with no registered consumers', function () {
    expect((new ConsumerRegistry)->consumersFor('NeverRegistered'))->toBe([]);
});

it('isolates consumers by event name', function () {
    $registry = new ConsumerRegistry;
    $consumer = new class implements DomainEventConsumer
    {
        public function handle(DomainEvent $event): void {}
    };

    $registry->register('EventA', $consumer::class);

    expect($registry->consumersFor('EventA'))->toBe([$consumer::class])
        ->and($registry->consumersFor('EventB'))->toBe([]);
});

it('is idempotent on a duplicate (event, consumer) pair', function () {
    $registry = new ConsumerRegistry;
    $consumer = new class implements DomainEventConsumer
    {
        public function handle(DomainEvent $event): void {}
    };

    $registry->register('OrderPlaced', $consumer::class);
    $registry->register('OrderPlaced', $consumer::class);

    expect($registry->consumersFor('OrderPlaced'))->toBe([$consumer::class]);
});

it('rejects a consumer class that does not implement the contract', function () {
    expect(fn () => (new ConsumerRegistry)->register('OrderPlaced', stdClass::class))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a consumer class that does not exist', function () {
    expect(fn () => (new ConsumerRegistry)->register('OrderPlaced', 'App\Nonexistent\Consumer'))
        ->toThrow(InvalidArgumentException::class);
});

it('keeps Inline the only registrable delivery mode until the queue ADR', function () {
    // Compile-time gate (design D4/D5): DeliveryMode has a single case, so register()
    // cannot be handed a non-inline mode today — queued delivery is gated behind the
    // queue-driver ADR (F4–F6). When DeliveryMode::Queued is added, ConsumerRegistry must
    // reject it until queued delivery is wired, and this assertion flips to that rejection.
    expect(DeliveryMode::cases())->toBe([DeliveryMode::Inline]);

    $registry = new ConsumerRegistry;
    $consumer = new class implements DomainEventConsumer
    {
        public function handle(DomainEvent $event): void {}
    };

    // The default and the explicit Inline mode register identically.
    $registry->register('DefaultMode', $consumer::class);
    $registry->register('ExplicitInline', $consumer::class, DeliveryMode::Inline);

    expect($registry->consumersFor('DefaultMode'))->toBe([$consumer::class])
        ->and($registry->consumersFor('ExplicitInline'))->toBe([$consumer::class]);
});
