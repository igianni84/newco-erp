<?php

// Task 2.1 / 2.2 (operator-console-parties-membership; design D6) — the Profile operator console's write-through
// Create surface, the membership-application create. These assertions pin the one law (the page NEVER saves the
// model; it routes the Customer + Club selects into the Parties CreateProfile action) and the audit envelope (a
// console-driven create records exactly one ProfileCreated with actor_role newco_ops + the operator id, resolved
// from the `operator` guard via the platform ActorContext seam — the console builds no envelope). A Profile is born
// `applied` (design D2/D6); the form exposes NO state/tier/role input. A duplicate non-terminal (Customer, Club)
// pair is rejected with DuplicateProfileForClub, surfaced on the `club_id` field (createRejectionField) by the kit
// base catch — no second Profile, zero new events. The list header reaches the create page through a plain link.
//
// DatabaseMigrations (mirroring CustomerCreateConsoleTest): the create flow drives a real domain action that opens
// its OWN DB::transaction inside Filament's create() transaction, so the DomainEventRecorder's in-transaction
// append commits for real (RefreshDatabase would wrap every write in a never-committed outer transaction). Parties
// enums/models/pages are imported freely here: the {Models, Actions, Enums} import-boundary carve-out governs
// OperatorPanel PRODUCTION code, not tests. A factory-built Profile bypasses CreateProfile and records no event —
// so the only recorded ProfileCreated is the console's.

use App\Modules\OperatorPanel\Filament\Resources\Parties\ProfileResource;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProfileResource\Pages\CreateProfile;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProfileResource\Pages\ListProfiles;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

it('creates an applied Profile for the Customer+Club pair through the console, recording one ProfileCreated with the operator envelope', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $customer = Customer::factory()->create();
    $club = Club::factory()->create();

    Livewire::test(CreateProfile::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'club_id' => $club->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // The write routed through the action: a Profile born `applied` for exactly that (customer, club) pair.
    $profile = Profile::query()
        ->where('customer_id', $customer->id)
        ->where('club_id', $club->id)
        ->sole();

    expect($profile->state)->toBe(ProfileState::Applied);

    // Exactly one ProfileCreated, carrying the operator audit envelope (newco_ops + the operator id) resolved by
    // the action from the `operator` guard — the console constructs no envelope itself. A factory-built
    // Customer/Club records no event, so ProfileCreated is the only event.
    $event = DomainEvent::query()->where('name', 'ProfileCreated')->sole();

    expect($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($operator->id)
        ->and($event->entity_type)->toBe('Profile')
        ->and($event->entity_id)->toBe((string) $profile->id);
});

it('surfaces DuplicateProfileForClub on the club_id field for a live (Customer, Club) pair, persisting no second Profile or event', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A live (non-terminal) Profile already holds this (customer, club) pair (factory-built → records no event), so
    // the action's BR-K-Identity-2 pre-check rejects the console's attempt.
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    Profile::factory()->create([
        'customer_id' => $customer->id,
        'club_id' => $club->id,
        'state' => ProfileState::Applied,
    ]);

    $profilesBefore = Profile::query()->count();
    $eventsBefore = DomainEvent::query()->where('name', 'ProfileCreated')->count();

    // A duplicate live pair → the action throws DuplicateProfileForClub (a RuntimeException), mapped by the kit base
    // catch to a `club_id` form error (createRejectionField). The transaction rolls back: no second Profile, no
    // event (count delta 0).
    Livewire::test(CreateProfile::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'club_id' => $club->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['club_id']);

    expect(Profile::query()->count())->toBe($profilesBefore)
        ->and(DomainEvent::query()->where('name', 'ProfileCreated')->count())->toBe($eventsBefore);
});

it('exposes the Customer and Club create selects and no state, tier or role field', function () {
    actingAs(Operator::factory()->create(), 'operator');

    Livewire::test(CreateProfile::class)
        ->assertFormFieldExists('customer_id')
        ->assertFormFieldExists('club_id')
        // A Profile is born `applied` and single-tier/role at launch (DEC-062, design D6), so the create form sets
        // none of these — the lifecycle verbs live on ViewProfile (groups 3–5).
        ->assertFormFieldDoesNotExist('state')
        ->assertFormFieldDoesNotExist('tier')
        ->assertFormFieldDoesNotExist('role');
});

it('reaches the create page through a header navigation link, never an inline CreateAction', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // The list header exposes a `create` action whose URL is the dedicated Create page — a plain link, not a
    // CreateAction (a CreateAction has no URL; it opens an inline modal that would `$record->save()`).
    Livewire::test(ListProfiles::class)
        ->assertActionExists('create')
        ->assertActionHasUrl('create', ProfileResource::getUrl('create'));
});
