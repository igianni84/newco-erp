<?php

use App\Modules\Catalog\Actions\CreateCaseConfiguration;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\CaseConfigurationCreated;
use App\Modules\Catalog\Models\CaseConfiguration;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * Pins the Case Configuration reference entity (catalog-product-spine task 2.2; design D5/D7/D8;
 * product-catalog — Requirement: Case Configuration, Spine Creation Events). Follows the Format spine
 * template: it proves the CreateCaseConfiguration action persists a `draft` entity and records
 * CaseConfigurationCreated through the platform recorder in the SAME transaction, PII-free, and that the
 * scope guard holds (no transition out of `draft`). It adds the §7-stays-downstream guard
 * (AC-0-BR-RefData-2): the entity carries no breakability attribute or column.
 *
 * RefreshDatabase (per the task hint): the action opens its OWN DB::transaction, so the recorder's
 * `transactionLevel() === 0` guard is satisfied by the savepoint even under RefreshDatabase's wrapper.
 * Portability: the recorded payload is read back through the model's `array` cast and asserted BY KEY,
 * never byte-compared (PG jsonb reorders keys — knowledge/testing trap 3).
 */
uses(RefreshDatabase::class);

it('creates a Case Configuration in draft with its packaging fields', function () {
    $configuration = app(CreateCaseConfiguration::class)->handle(
        name: 'Original Wooden Case (6)',
        unitsPerCase: 6,
        packagingType: 'owc',
    );

    // Re-fetch a fresh instance so the assertions exercise the read/hydration casts, not the in-memory
    // values from create().
    $read = CaseConfiguration::findOrFail($configuration->id);

    expect($read->name)->toBe('Original Wooden Case (6)')
        ->and($read->units_per_case)->toBe(6)
        ->and($read->packaging_type)->toBe('owc')
        ->and($read->lifecycle_state)->toBe(LifecycleState::Draft)  // born draft (design D3)
        ->and($read->version)->toBe(1);                             // §4.8 version floor, born at 1
});

it('records a CaseConfigurationCreated domain event in the same transaction, tagged catalog and PII-free', function () {
    $configuration = app(CreateCaseConfiguration::class)->handle(
        name: 'Carton (12)',
        unitsPerCase: 12,
        packagingType: 'carton',
    );

    // sole() asserts EXACTLY one CaseConfigurationCreated row exists (throws otherwise) — the one-event contract.
    $event = DomainEvent::query()->where('name', CaseConfigurationCreated::NAME)->sole();

    expect($event->module)->toBe('catalog')                         // Module::Catalog->value
        ->and($event->entity_type)->toBe('CaseConfiguration')
        ->and($event->entity_id)->toBe((string) $configuration->id) // envelope entity_id is a string
        ->and($event->actor_role)->toBe(ActorRole::System);         // the ActorContext seam default

    // Payload asserted BY KEY through the array cast (trap 3) — PII-free (a Case Configuration references no party).
    expect($event->payload['case_configuration_id'])->toBe($configuration->id)
        ->and($event->payload['name'])->toBe('Carton (12)')
        ->and($event->payload['units_per_case'])->toBe(12)
        ->and($event->payload['packaging_type'])->toBe('carton')
        ->and($event->payload['lifecycle_state'])->toBe('draft');
});

it('carries no breakability attribute or column — breakability is decided downstream (AC-0-BR-RefData-2)', function () {
    app(CreateCaseConfiguration::class)->handle(name: 'Loose', unitsPerCase: 1, packagingType: 'loose');

    // BR-RefData-2 / §7-stays-downstream: whether a case may be split at sale lives in Module A (Layer 2)
    // / Module S (Layer 3), never as a property of the Case Configuration. The table has no such column,
    // and no business field name carries the concept.
    expect(Schema::hasColumn('catalog_case_configurations', 'breakable'))->toBeFalse()
        ->and(Schema::hasColumn('catalog_case_configurations', 'breakability'))->toBeFalse();

    $columns = Schema::getColumnListing('catalog_case_configurations');

    foreach ($columns as $column) {
        expect($column)->not->toContain('break');
    }

    // The recorded payload carries no breakability key either (the event contract is PII-free AND
    // breakability-free).
    $event = DomainEvent::query()->where('name', CaseConfigurationCreated::NAME)->sole();
    expect($event->payload)->not->toHaveKey('breakable')
        ->and($event->payload)->not->toHaveKey('breakability');
});

it('records no lifecycle-transition event — the Case Configuration stays draft (scope guard)', function () {
    $configuration = app(CreateCaseConfiguration::class)->handle(
        name: 'Carton (6)',
        unitsPerCase: 6,
        packagingType: 'carton',
    );

    // Design D3 scope guard: this change defines no transition path, so only the *Created event exists —
    // never an *Activated/*Retired (the deferred catalog-lifecycle-approval change owns those).
    expect(DomainEvent::query()->where('name', 'like', '%Activated%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Retired%')->count())->toBe(0)
        ->and(CaseConfiguration::findOrFail($configuration->id)->lifecycle_state)->toBe(LifecycleState::Draft);
});

it('produces a draft Case Configuration via the factory without recording an event', function () {
    // The factory is a pure fixture: it bypasses the action, so it persists a draft Case Configuration but
    // records no CaseConfigurationCreated (later tasks lean on it to stand up prerequisites cheaply).
    $configuration = CaseConfiguration::factory()->create();

    expect($configuration->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and($configuration->version)->toBe(1)
        ->and(DomainEvent::query()->count())->toBe(0);
});
