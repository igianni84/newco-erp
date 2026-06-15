<?php

use App\Modules\Catalog\Actions\CreateFormat;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\FormatCreated;
use App\Modules\Catalog\Models\Format;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the Format reference entity (catalog-product-spine task 2.1; design D5/D7/D8; product-catalog —
 * Requirement: Format, Lifecycle State Recorded, Spine Creation Events). This is the FIRST DB-touching
 * Catalog test and the template for the spine: it proves the CreateFormat action persists a `draft`
 * entity and records FormatCreated through the platform recorder in the SAME transaction, PII-free, and
 * that the scope guard holds (no transition out of `draft`, no `*Activated`/`*Retired` event).
 *
 * RefreshDatabase (per the task hint, not DatabaseMigrations): the action opens its OWN
 * DB::transaction, so the recorder's `transactionLevel() === 0` guard is satisfied by the savepoint
 * even under RefreshDatabase's wrapper — there is no level-0 path to exercise here (that is the
 * recorder's own test). Portability: the recorded payload is read back through the model's `array` cast
 * and asserted BY KEY, never byte-compared (PG jsonb reorders keys — knowledge/testing trap 3).
 */
uses(RefreshDatabase::class);

it('creates a Format in draft with its physical-measure fields', function () {
    $format = app(CreateFormat::class)->handle(name: 'Magnum', sizeLabel: '1.5L', volumeMl: 1500);

    // Re-fetch a fresh instance so the assertions exercise the read/hydration casts, not the in-memory
    // values from create().
    $read = Format::findOrFail($format->id);

    expect($read->name)->toBe('Magnum')
        ->and($read->size_label)->toBe('1.5L')
        ->and($read->volume_ml)->toBe(1500)
        ->and($read->lifecycle_state)->toBe(LifecycleState::Draft)  // born draft (design D3)
        ->and($read->version)->toBe(1);                             // §4.8 version floor, born at 1
});

it('records a FormatCreated domain event in the same transaction, tagged catalog and PII-free', function () {
    $format = app(CreateFormat::class)->handle(name: 'Bottle', sizeLabel: '750ml', volumeMl: 750);

    // sole() asserts EXACTLY one FormatCreated row exists (throws otherwise) — the one-event contract.
    $event = DomainEvent::query()->where('name', FormatCreated::NAME)->sole();

    expect($event->module)->toBe('catalog')                  // Module::Catalog->value
        ->and($event->entity_type)->toBe('Format')
        ->and($event->entity_id)->toBe((string) $format->id) // envelope entity_id is a string
        ->and($event->actor_role)->toBe(ActorRole::System);  // the ActorContext seam default

    // Payload asserted BY KEY through the array cast (trap 3) — PII-free (Format references no party).
    expect($event->payload['format_id'])->toBe($format->id)
        ->and($event->payload['name'])->toBe('Bottle')
        ->and($event->payload['size_label'])->toBe('750ml')
        ->and($event->payload['volume_ml'])->toBe(750)
        ->and($event->payload['lifecycle_state'])->toBe('draft');
});

it('records no lifecycle-transition event — the Format stays draft (scope guard)', function () {
    $format = app(CreateFormat::class)->handle(name: 'Half Bottle', sizeLabel: '375ml', volumeMl: 375);

    // Design D3 scope guard: this change defines no transition path, so only the *Created event exists —
    // never an *Activated/*Retired (the deferred catalog-lifecycle-approval change owns those).
    expect(DomainEvent::query()->where('name', 'like', '%Activated%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Retired%')->count())->toBe(0)
        ->and(Format::findOrFail($format->id)->lifecycle_state)->toBe(LifecycleState::Draft);
});

it('produces a draft Format via the factory without recording an event', function () {
    // The factory is a pure fixture: it bypasses the action, so it persists a draft Format but records
    // no FormatCreated (later tasks lean on it to stand up Format prerequisites cheaply).
    $format = Format::factory()->create();

    expect($format->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and($format->version)->toBe(1)
        ->and(DomainEvent::query()->count())->toBe(0);
});
