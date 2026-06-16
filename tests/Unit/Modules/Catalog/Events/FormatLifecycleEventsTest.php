<?php

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\FormatActivated;
use App\Modules\Catalog\Events\FormatRetired;
use App\Modules\Catalog\Models\Format;
use Tests\TestCase;

// Pins the two Format lifecycle events (catalog-lifecycle-approval, task 4.1; design D9; product-catalog —
// Requirement: Product Lifecycle Events). `*Activated` covers the `reviewed → active` step, `*Retired`
// covers `active → retired`; both mirror the `final` NAME/ENTITY_TYPE/static payload() shape of
// FormatCreated. A Format is a STANDALONE reference entity (no parent, references no party), so the
// transition payload is the minimal PII-free pair — the entity id + the lifecycle value only; the
// descriptive physical-measure fields belong to the creation record, not a transition.
//
// Booting the app (TestCase, NO RefreshDatabase/DatabaseMigrations) gives the model its enum casts while
// touching no database: the fixtures are built with factory()->make(), which never persists or queries —
// the absence of a migrated schema is itself the guard that a query would fail loudly.

uses(TestCase::class);

// An in-memory Format fixture (never saved — make() runs no query) carrying its descriptive physical-measure
// fields alongside the id, so the payload assertions can prove those fields never leak into a lifecycle event.
$format = fn (LifecycleState $state): Format => Format::factory()->make([
    'id' => 42,
    'name' => 'Magnum',
    'size_label' => '1.5L',
    'volume_ml' => 1500,
    'lifecycle_state' => $state,
]);

it('exposes the verbatim FormatActivated contract facets as a final class', function () {
    expect(FormatActivated::NAME)->toBe('FormatActivated')
        ->and(FormatActivated::ENTITY_TYPE)->toBe('Format')
        ->and((new ReflectionClass(FormatActivated::class))->isFinal())->toBeTrue();
});

it('exposes the verbatim FormatRetired contract facets as a final class', function () {
    expect(FormatRetired::NAME)->toBe('FormatRetired')
        ->and(FormatRetired::ENTITY_TYPE)->toBe('Format')
        ->and((new ReflectionClass(FormatRetired::class))->isFinal())->toBeTrue();
});

it('snapshots exactly the PII-free id + lifecycle pair for FormatActivated', function () use ($format) {
    $payload = FormatActivated::payload($format(LifecycleState::Active));

    // "Exactly" the two keys, in order, with the post-transition lifecycle value.
    expect(array_keys($payload))->toBe(['format_id', 'lifecycle_state'])
        ->and($payload)->toBe([
            'format_id' => 42,
            'lifecycle_state' => 'active',
        ])
        // Minimal transition snapshot: no descriptive physical-measure fields, only the id + the enum value.
        ->and($payload)->not->toHaveKey('name')
        ->and($payload)->not->toHaveKey('size_label')
        ->and($payload)->not->toHaveKey('volume_ml')
        ->and(array_values($payload))->not->toContain('Magnum');
});

it('snapshots exactly the PII-free id + lifecycle pair for FormatRetired', function () use ($format) {
    $payload = FormatRetired::payload($format(LifecycleState::Retired));

    expect(array_keys($payload))->toBe(['format_id', 'lifecycle_state'])
        ->and($payload)->toBe([
            'format_id' => 42,
            'lifecycle_state' => 'retired',
        ])
        ->and($payload)->not->toHaveKey('name')
        ->and($payload)->not->toHaveKey('size_label')
        ->and($payload)->not->toHaveKey('volume_ml')
        ->and(array_values($payload))->not->toContain('Magnum');
});
