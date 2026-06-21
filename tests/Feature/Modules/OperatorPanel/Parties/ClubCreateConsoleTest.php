<?php

// Task 3.1 / 3.2 (operator-console-parties-supply-side; design D6/D7/D11; ADR 2026-06-19 + 2026-06-20 +
// 2026-06-21) — the Club operator console's write-through Create surface. These assertions pin the one law (the
// page NEVER saves the model; it routes the form into the Parties CreateClub action) and the audit envelope (a
// console-driven create records ClubCreated with actor_role newco_ops + the operator id, resolved automatically
// from the `operator` guard via the platform ActorContext seam — the console builds no envelope). The create form
// CONSTRUCTS the ClubRegistrationFlowType OPERAND enum (the {Models, Actions, Enums} carve-out — group 1) and
// assembles the OPTIONAL Money fee (integer minor units + ISO 4217, only when both an amount and a currency are
// supplied — D11). The form never exposes `status`: a Club is born `active` with no activate verb (design D9).
//
// DatabaseMigrations (mirroring ProducerCreateConsoleTest): the create flow drives a real domain action that
// opens its OWN DB::transaction inside Filament's create() transaction, so the DomainEventRecorder's
// in-transaction append commits for real — the faithful production shape (RefreshDatabase would wrap every write
// in a never-committed outer transaction). Parties enums/models/pages are imported freely here: the
// {Models, Actions, Enums} import-boundary carve-out governs OperatorPanel PRODUCTION code, not tests. The
// operating Producer is built by the factory, which bypasses the actions and records NO event, so the only
// recorded event is the console's ClubCreated.

use App\Modules\OperatorPanel\Filament\Resources\Parties\ClubResource\Pages\CreateClub;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Enums\ClubStatus;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Producer;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use App\Platform\Money\Currency;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

it('creates an active Club through the console, recording one ClubCreated with the operator envelope and the Money fee', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    // An existing operating Producer (factory-built → records no event), so the action's MissingClubProducer
    // pre-check passes and the only recorded event is the console's ClubCreated.
    $producer = Producer::factory()->create();

    Livewire::test(CreateClub::class)
        ->fillForm([
            'display_name' => 'Premier Cercle Console',
            'producer_id' => $producer->id,
            'registration_flow_type' => 'invitation_only',
            'amount' => '50000',
            'currency' => 'EUR',
            'generates_credit' => true,
            'invite_only' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // The write routed through the action: a Club born `active`, linked to the operating Producer, carrying the
    // assembled Money fee (50000 EUR minor units) and the two flags — the operand enum constructed from the form
    // value, the fee assembled from amount + currency.
    $club = Club::query()->where('display_name', 'Premier Cercle Console')->sole();

    expect($club->status)->toBe(ClubStatus::Active)
        ->and($club->producer_id)->toBe($producer->id)
        ->and($club->registration_flow_type->value)->toBe('invitation_only')
        ->and($club->fee)->not->toBeNull()
        ->and($club->fee?->minorUnits)->toBe(50000)
        ->and($club->fee?->currency)->toBe(Currency::EUR)
        ->and($club->generates_credit)->toBeTrue()
        ->and($club->invite_only)->toBeTrue();

    // Exactly one ClubCreated, carrying the operator audit envelope (newco_ops + the operator id) resolved by the
    // action from the `operator` guard — the console constructs no envelope itself.
    $event = DomainEvent::query()->where('name', 'ClubCreated')->sole();

    expect($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($operator->id)
        ->and($event->entity_type)->toBe('Club')
        ->and($event->entity_id)->toBe((string) $club->id);
});

it('creates a Club with a null fee when the amount and currency are left blank', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $producer = Producer::factory()->create();

    Livewire::test(CreateClub::class)
        ->fillForm([
            'display_name' => 'Cercle Sans Frais',
            'producer_id' => $producer->id,
            'registration_flow_type' => 'open_registration',
            // amount + currency left blank → the page assembles no Money, passing the action's `?Money $fee = null`
            // default (D11).
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $club = Club::query()->where('display_name', 'Cercle Sans Frais')->sole();

    expect($club->fee)->toBeNull()
        ->and($club->status)->toBe(ClubStatus::Active);
});

it('surfaces MissingClubProducer on the producer_id field for a non-existent Producer, persisting no Club and no event', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A producer_id matching no Producer → the action throws MissingClubProducer (a RuntimeException), mapped by
    // the kit base catch to a `producer_id` form error rather than an unhandled 500. The transaction rolls back:
    // no Club row, no ClubCreated event.
    Livewire::test(CreateClub::class)
        ->fillForm([
            'display_name' => 'Cercle Orphelin',
            'producer_id' => 999999,
            'registration_flow_type' => 'open_registration',
        ])
        ->call('create')
        ->assertHasFormErrors(['producer_id']);

    expect(Club::query()->where('display_name', 'Cercle Orphelin')->exists())->toBeFalse()
        ->and(DomainEvent::query()->where('name', 'ClubCreated')->exists())->toBeFalse();
});

it('exposes the Club create fields and no status field', function () {
    actingAs(Operator::factory()->create(), 'operator');

    Livewire::test(CreateClub::class)
        ->assertFormFieldExists('display_name')
        ->assertFormFieldExists('producer_id')
        ->assertFormFieldExists('registration_flow_type')
        ->assertFormFieldExists('amount')
        ->assertFormFieldExists('currency')
        ->assertFormFieldExists('generates_credit')
        ->assertFormFieldExists('invite_only')
        // A Club is born `active` by CreateClub (no activate verb — design D9), so the create form never sets
        // `status`; it advances only through the ViewClub lifecycle actions (task 4.1).
        ->assertFormFieldDoesNotExist('status');
});
