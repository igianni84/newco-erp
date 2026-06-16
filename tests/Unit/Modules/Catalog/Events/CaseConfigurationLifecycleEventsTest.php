<?php

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\CaseConfigurationActivated;
use App\Modules\Catalog\Events\CaseConfigurationRetired;
use App\Modules\Catalog\Models\CaseConfiguration;
use Tests\TestCase;

// Pins the two Case Configuration lifecycle events (catalog-lifecycle-approval, task 4.2; design D9;
// product-catalog — Requirement: Product Lifecycle Events). `*Activated` covers the `reviewed → active`
// step, `*Retired` covers `active → retired`; both mirror the `final` NAME/ENTITY_TYPE/static payload()
// shape of CaseConfigurationCreated. A Case Configuration is a STANDALONE reference entity (no parent,
// references no party), so the transition payload is the minimal PII-free pair — the entity id + the
// lifecycle value only; the descriptive packaging fields belong to the creation record, not a transition.
//
// Booting the app (TestCase, NO RefreshDatabase/DatabaseMigrations) gives the model its enum casts while
// touching no database: the fixtures are built with factory()->make(), which never persists or queries —
// the absence of a migrated schema is itself the guard that a query would fail loudly.

uses(TestCase::class);

// An in-memory Case Configuration fixture (never saved — make() runs no query) carrying its descriptive
// packaging fields alongside the id, so the payload assertions can prove those fields never leak into a
// lifecycle event.
$caseConfiguration = fn (LifecycleState $state): CaseConfiguration => CaseConfiguration::factory()->make([
    'id' => 42,
    'name' => 'Original Wooden Case (6)',
    'units_per_case' => 6,
    'packaging_type' => 'owc',
    'lifecycle_state' => $state,
]);

it('exposes the verbatim CaseConfigurationActivated contract facets as a final class', function () {
    expect(CaseConfigurationActivated::NAME)->toBe('CaseConfigurationActivated')
        ->and(CaseConfigurationActivated::ENTITY_TYPE)->toBe('CaseConfiguration')
        ->and((new ReflectionClass(CaseConfigurationActivated::class))->isFinal())->toBeTrue();
});

it('exposes the verbatim CaseConfigurationRetired contract facets as a final class', function () {
    expect(CaseConfigurationRetired::NAME)->toBe('CaseConfigurationRetired')
        ->and(CaseConfigurationRetired::ENTITY_TYPE)->toBe('CaseConfiguration')
        ->and((new ReflectionClass(CaseConfigurationRetired::class))->isFinal())->toBeTrue();
});

it('snapshots exactly the PII-free id + lifecycle pair for CaseConfigurationActivated', function () use ($caseConfiguration) {
    $payload = CaseConfigurationActivated::payload($caseConfiguration(LifecycleState::Active));

    // "Exactly" the two keys, in order, with the post-transition lifecycle value.
    expect(array_keys($payload))->toBe(['case_configuration_id', 'lifecycle_state'])
        ->and($payload)->toBe([
            'case_configuration_id' => 42,
            'lifecycle_state' => 'active',
        ])
        // Minimal transition snapshot: no descriptive packaging fields, only the id + the enum value.
        ->and($payload)->not->toHaveKey('name')
        ->and($payload)->not->toHaveKey('units_per_case')
        ->and($payload)->not->toHaveKey('packaging_type')
        ->and(array_values($payload))->not->toContain('Original Wooden Case (6)');
});

it('snapshots exactly the PII-free id + lifecycle pair for CaseConfigurationRetired', function () use ($caseConfiguration) {
    $payload = CaseConfigurationRetired::payload($caseConfiguration(LifecycleState::Retired));

    expect(array_keys($payload))->toBe(['case_configuration_id', 'lifecycle_state'])
        ->and($payload)->toBe([
            'case_configuration_id' => 42,
            'lifecycle_state' => 'retired',
        ])
        ->and($payload)->not->toHaveKey('name')
        ->and($payload)->not->toHaveKey('units_per_case')
        ->and($payload)->not->toHaveKey('packaging_type')
        ->and(array_values($payload))->not->toContain('Original Wooden Case (6)');
});
