<?php

use App\Platform\Audit\AuditRecord;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DeliveryStatus;
use App\Platform\Events\DomainEvent;
use App\Platform\Events\EventDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Pins the three platform Eloquent models (foundations-domain-events-audit, task 3.1; design
 * D1/D2). The schema migrations (2.x) proved the columns/indexes/triggers at the DB layer; this
 * file proves the application layer on top — the casts that hydrate persisted rows into the typed
 * envelope (ActorRole / DeliveryStatus enums, CarbonImmutable clocks, jsonb ↔ array), the
 * timestamps split (append-only tables carry none; the mutable delivery ledger keeps them), and
 * the cast acting as the value-set floor (an out-of-enum status throws on hydration). DB-touching,
 * so it opts into RefreshDatabase per-file (the global binding stays commented).
 *
 * The persistence-convention arch test is unaffected: it scans App\Modules\** only, and these are
 * platform tables (unprefixed by design) — the full suite staying green is the proof.
 */

/**
 * A complete, valid attribute set for DomainEvent::create(): enum and array values are passed as
 * their PHP types (the casts encode them on write). Prefixed to avoid colliding with the sibling
 * schema tests' global helpers (Pest test-file functions share one namespace).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function modelsDomainEventAttributes(array $overrides = []): array
{
    return array_merge([
        'event_id' => (string) Str::uuid(),
        'name' => 'PlatformDemoRecorded',
        'module' => 'platform',
        'occurred_at' => now(),
        'actor_role' => ActorRole::System,
        'entity_type' => 'demo',
        'entity_id' => '1',
        'correlation_id' => (string) Str::uuid(),
        'payload' => ['fx_rate' => '1.0842'],
    ], $overrides);
}

it('round-trips a DomainEvent with its envelope cast types', function () {
    $event = DomainEvent::create(modelsDomainEventAttributes([
        'payload' => ['fx_rate' => '1.0842', 'amount_minor' => 12000, 'currency' => 'EUR'],
    ]));

    // Re-fetch a fresh instance so the assertions exercise the read/hydration casts, not the
    // values still cached in memory from create().
    $read = DomainEvent::findOrFail($event->id);

    expect($read->actor_role)->toBeInstanceOf(ActorRole::class)
        ->and($read->actor_role)->toBe(ActorRole::System)
        ->and($read->occurred_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($read->schema_version)->toBe(1)
        ->and($read->payload)->toBeArray()
        ->and($read->payload['fx_rate'])->toBe('1.0842')      // FX rate stays a decimal string
        ->and($read->payload['amount_minor'])->toBe(12000);   // money minor-units stays an int
});

it('round-trips an AuditRecord with its snapshot and actor cast types', function () {
    $record = AuditRecord::create([
        'occurred_at' => now(),
        'module' => 'platform',
        'actor_role' => ActorRole::NewcoOps,
        'entity_type' => 'voucher',
        'entity_id' => '42',
        'correlation_id' => (string) Str::uuid(),
        'action' => 'platform.demo',
        'before' => ['status' => 'active'],
        'after' => ['status' => 'cancelled'],
        'authorization_basis' => 'operator_console',
    ]);

    $read = AuditRecord::findOrFail($record->id);

    expect($read->actor_role)->toBeInstanceOf(ActorRole::class)
        ->and($read->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($read->before)->toBe(['status' => 'active'])
        ->and($read->after)->toBe(['status' => 'cancelled']);
});

it('round-trips an EventDelivery with its lifecycle cast types', function () {
    $event = DomainEvent::create(modelsDomainEventAttributes());

    $delivery = EventDelivery::create([
        'domain_event_id' => $event->id,
        'consumer' => 'App\\Modules\\Commerce\\SomeConsumer',
        'status' => DeliveryStatus::Pending,
        'available_at' => now(),
    ]);

    $read = EventDelivery::findOrFail($delivery->id);

    expect($read->status)->toBeInstanceOf(DeliveryStatus::class)
        ->and($read->status)->toBe(DeliveryStatus::Pending)
        ->and($read->available_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($read->attempts)->toEqual(0);   // uncast smallint default; loose compare spans engines
});

it('keeps timestamps only on the mutable delivery ledger, not the append-only tables', function () {
    // The append-only tables use occurred_at as their only clock (task 3.1); the mutable delivery
    // ledger keeps created_at/updated_at — without this split a create() would write columns the
    // append-only tables do not have.
    expect((new DomainEvent)->usesTimestamps())->toBeFalse()
        ->and((new AuditRecord)->usesTimestamps())->toBeFalse()
        ->and((new EventDelivery)->usesTimestamps())->toBeTrue();

    $event = DomainEvent::create(modelsDomainEventAttributes());
    $delivery = EventDelivery::create([
        'domain_event_id' => $event->id,
        'consumer' => 'App\\Modules\\Commerce\\SomeConsumer',
    ]);

    // The kept timestamps are actually populated (read via the query builder to keep the assertion
    // independent of the model's date-cast type).
    expect(DB::table('event_deliveries')->where('id', $delivery->id)->value('created_at'))
        ->not->toBeNull();
});

it('throws a ValueError when reading a delivery status outside the enum', function () {
    $event = DomainEvent::create(modelsDomainEventAttributes());

    // Plant an out-of-enum value by writing the raw column directly, bypassing the DeliveryStatus
    // cast — the way a rogue query or a future enum-narrowing could leave the ledger.
    DB::table('event_deliveries')->insert([
        'domain_event_id' => $event->id,
        'consumer' => 'App\\Modules\\Commerce\\SomeConsumer',
        'status' => 'bogus',
        'attempts' => 0,
    ]);

    $delivery = EventDelivery::query()->firstOrFail();

    // Hydrating `status` runs DeliveryStatus::from('bogus'); the cast is the application-side
    // value-set floor on both engines (design D2), so the read throws rather than yielding junk.
    expect(fn () => $delivery->status)->toThrow(ValueError::class);
});
