<?php

use App\Platform\Events\ConsumerRegistry;
use App\Platform\Events\Contracts\DomainEventConsumer;
use App\Platform\Events\DomainEvent;

// Pins the container wiring of the consumer registry (foundations-domain-events-audit,
// task 3.2; design D4: "plain singleton; consumers container-resolved at delivery time").
// Module providers resolve ONE shared registry from the container in boot() and the
// delivery path resolves that same instance, so registrations must survive across
// resolutions. Needs the booted app, hence a Feature test (Unit tests are plain PHPUnit).

it('binds ConsumerRegistry as a shared singleton', function () {
    expect(app(ConsumerRegistry::class))->toBe(app(ConsumerRegistry::class));
});

it('persists registrations across container resolutions', function () {
    $consumer = new class implements DomainEventConsumer
    {
        public function handle(DomainEvent $event): void {}
    };

    app(ConsumerRegistry::class)->register('OrderPlaced', $consumer::class);

    expect(app(ConsumerRegistry::class)->consumersFor('OrderPlaced'))->toBe([$consumer::class]);
});
