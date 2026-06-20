<?php

// Task 3.1 (operator-console-catalog-master; design L2/L6/L8; ADR 2026-06-19) — the operator console's FIRST
// write-through surface: the Product Master Create page. These assertions pin the one law (the page NEVER
// saves the model; it routes the form into the Catalog CreateProductMaster action) and the audit envelope
// (a console-driven create records ProductMasterCreated with actor_role newco_ops + the operator id, resolved
// automatically from the `operator` guard via the platform ActorContext seam — the console builds no
// envelope). The BR-Identity-1 identity-key collision is surfaced as a form validation error on `name`, not
// an unhandled 500. The header create affordance is a navigation LINK to this page, never an inline
// CreateAction (whose modal `$record->save()` would bypass the domain action).
//
// DatabaseMigrations (mirroring ProductMasterLifecycleTest): the create flow drives a real domain action that
// opens its OWN DB::transaction inside Filament's create() transaction, so the DomainEventRecorder's
// in-transaction append commits for real — the faithful production shape (RefreshDatabase would wrap every
// write in a never-committed outer transaction, so DB::afterCommit delivery would never fire). Catalog
// enums/models/actions are imported freely here: the {Models, Actions} import-boundary carve-out (task 1.3)
// governs OperatorPanel PRODUCTION code, not tests.

use App\Modules\Catalog\Actions\CreateProductMaster as CreateProductMasterAction;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Enums\ProducerProjectionStatus;
use App\Modules\Catalog\Models\ProducerState;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource\Pages\CreateProductMaster;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource\Pages\ListProductMasters;
use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

/** Project one `active` producer into Catalog's own read model — the create form's producer select source. */
function createConsoleProjectActiveProducer(int $producerId): void
{
    ProducerState::create([
        'producer_id' => $producerId,
        'status' => ProducerProjectionStatus::Active,
        'last_event_id' => 1,
    ]);
}

it('creates a draft Master through the console, recording one ProductMasterCreated with the operator envelope', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    createConsoleProjectActiveProducer(4242);

    Livewire::test(CreateProductMaster::class)
        ->fillForm([
            'name' => 'Château Console',
            'producer_id' => 4242,
            'appellation' => 'Pauillac',
            'region' => 'Bordeaux',
            'winery_story' => 'A console-created estate.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // The write routed through the action: a draft Master with its 1:1 WINE attribute set.
    $master = ProductMaster::query()->where('name', 'Château Console')->sole();

    expect($master->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and($master->producer_id)->toBe(4242);

    $wine = $master->wineAttributes()->sole();
    expect($wine->appellation)->toBe('Pauillac')
        ->and($wine->region)->toBe('Bordeaux')
        ->and($wine->winery_story?->resolve('en'))->toBe('A console-created estate.');

    // Exactly one ProductMasterCreated, carrying the operator audit envelope (newco_ops + the operator id)
    // resolved by the action from the `operator` guard — the console constructs no envelope itself.
    $event = DomainEvent::query()->where('name', 'ProductMasterCreated')->sole();

    expect($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($operator->id)
        ->and($event->entity_type)->toBe('ProductMaster')
        ->and($event->entity_id)->toBe((string) $master->id);
});

it('surfaces a duplicate identity key as a form validation error and records nothing new', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    createConsoleProjectActiveProducer(4242);

    // A pre-existing non-retired Master holding the WINE identity key (producer + name + appellation).
    app(CreateProductMasterAction::class)->handle(
        name: 'Château Dup',
        producerId: 4242,
        appellation: 'Margaux',
        region: 'Bordeaux',
    );

    expect(ProductMaster::query()->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', 'ProductMasterCreated')->count())->toBe(1);

    // Submitting the colliding identity through the console → a form error on `name` (the domain dedup
    // rejection, mapped to a field error — not a 500).
    Livewire::test(CreateProductMaster::class)
        ->fillForm([
            'name' => 'Château Dup',
            'producer_id' => 4242,
            'appellation' => 'Margaux',
            'region' => 'Bordeaux',
        ])
        ->call('create')
        ->assertHasFormErrors(['name']);

    // No second Master, no second event — the collision is rejected before any write.
    expect(ProductMaster::query()->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', 'ProductMasterCreated')->count())->toBe(1);
});

it('reaches the create page through a header navigation link, never an inline CreateAction', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // The list header exposes a `create` action whose URL is the dedicated Create page — a plain link, not a
    // CreateAction (a CreateAction has no URL; it opens an inline modal that would `$record->save()`).
    Livewire::test(ListProductMasters::class)
        ->assertActionExists('create')
        ->assertActionHasUrl('create', ProductMasterResource::getUrl('create'));
});
