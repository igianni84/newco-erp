<?php

// Task 8.1 / 8.2 (operator-console-parties-supply-side; design D2/D6/D7; ADR 2026-06-19 + 2026-06-20) — the
// ProducerAgreement operator console's write-through Create surface. These assertions pin the one law (the page
// NEVER saves the model; it routes the form into the Parties CreateProducerAgreement action) and the audit
// envelope (a console-driven create records ProducerAgreementCreated with actor_role newco_ops + the operator id,
// resolved automatically from the `operator` guard via the platform ActorContext seam — the console builds no
// envelope). The form narrows a blank Club / dates / cadence to null at the boundary. The form never exposes
// `status`: an agreement is born `draft` (design D2), with no create-time transition. The single-active-per-scope
// rule (BR-K-Agreement-1) is an ACTIVATION-time invariant, NOT enforced at create — two drafts in the same scope
// both succeed.
//
// parties-module-k-br-guards task 6.1 (canon MVP-DEC-009 / MVP-DEC-010): the settlement cadence is now a Select
// over the closed SettlementCadence set (default quarterly), NOT free text; the Club picker offers only the
// selected Producer's `active` Clubs (a sunset/closed Club is not selectable). The server-side guards in the
// action — ProducerAgreementClubNotActive (3.2) and InvalidSettlementCadence (3.1) — are the floor beneath both
// pickers, surfaced through the kit base's create-rejection catch (the same path the MissingAgreementProducer test
// below already pins).
//
// DatabaseMigrations (mirroring ClubCreateConsoleTest): the create flow drives a real domain action that opens its
// OWN DB::transaction inside Filament's create() transaction, so the DomainEventRecorder's in-transaction append
// commits for real — the faithful production shape (RefreshDatabase would wrap every write in a never-committed
// outer transaction). Parties models/pages are imported freely here: the {Models, Actions, Enums} import-boundary
// carve-out governs OperatorPanel PRODUCTION code, not tests. The Producer (and the scoped Club) are factory-built,
// bypassing the actions and recording NO event, so the only recorded event is the console's ProducerAgreementCreated.

use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerAgreementResource;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerAgreementResource\Pages\CreateProducerAgreement;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Enums\ClubStatus;
use App\Modules\Parties\Enums\ProducerAgreementStatus;
use App\Modules\Parties\Enums\SettlementCadence;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Producer;
use App\Modules\Parties\Models\ProducerAgreement;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

it('creates a draft ProducerAgreement through the console, recording one ProducerAgreementCreated with the operator envelope', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    // An existing Producer + a Club scoped under it (both factory-built → record no event), so the action's
    // MissingAgreementProducer pre-check passes and the only recorded event is the console's ProducerAgreementCreated.
    $producer = Producer::factory()->create();
    $club = Club::factory()->create(['producer_id' => $producer->id]);

    Livewire::test(CreateProducerAgreement::class)
        ->fillForm([
            'producer_id' => $producer->id,
            'club_id' => $club->id,
            'term_start' => '2026-03-01',
            'term_end' => '2026-12-31',
            'settlement_cadence' => 'quarterly',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // The write routed through the action: an agreement born `draft`, scoped to the Producer + Club, carrying the
    // term dates parsed from the DatePicker strings + the free-string settlement cadence (ids/dates/string only —
    // NO operand enum, design D7).
    $agreement = ProducerAgreement::query()->sole();

    expect($agreement->status)->toBe(ProducerAgreementStatus::Draft)
        ->and($agreement->producer_id)->toBe($producer->id)
        ->and($agreement->club_id)->toBe($club->id)
        ->and($agreement->term_start?->toDateString())->toBe('2026-03-01')
        ->and($agreement->term_end?->toDateString())->toBe('2026-12-31')
        ->and($agreement->settlement_cadence)->toBe(SettlementCadence::Quarterly);

    // Exactly one ProducerAgreementCreated, carrying the operator audit envelope (newco_ops + the operator id)
    // resolved by the action from the `operator` guard — the console constructs no envelope itself.
    $event = DomainEvent::query()->where('name', 'ProducerAgreementCreated')->sole();

    expect($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($operator->id)
        ->and($event->entity_type)->toBe('ProducerAgreement')
        ->and($event->entity_id)->toBe((string) $agreement->id);
});

it('creates a Producer-wide agreement with a null club when the Club is left blank', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $producer = Producer::factory()->create();

    Livewire::test(CreateProducerAgreement::class)
        ->fillForm([
            'producer_id' => $producer->id,
            // club_id left blank → a Producer-wide agreement (§ 4.6); the page narrows the blank value to null.
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $agreement = ProducerAgreement::query()->sole();

    expect($agreement->club_id)->toBeNull()
        ->and($agreement->status)->toBe(ProducerAgreementStatus::Draft);
});

it('surfaces MissingAgreementProducer on the producer_id field for a non-existent Producer, persisting no agreement and no event', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A producer_id matching no Producer → the action throws MissingAgreementProducer (a RuntimeException), mapped by
    // the kit base catch to a `producer_id` form error rather than an unhandled 500. The transaction rolls back: no
    // agreement row, no ProducerAgreementCreated event.
    Livewire::test(CreateProducerAgreement::class)
        ->fillForm([
            'producer_id' => 999999,
        ])
        ->call('create')
        ->assertHasFormErrors(['producer_id']);

    expect(ProducerAgreement::query()->exists())->toBeFalse()
        ->and(DomainEvent::query()->where('name', 'ProducerAgreementCreated')->exists())->toBeFalse();
});

it('allows two draft agreements in the same Producer scope, recording two ProducerAgreementCreated events', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // The single-active-per-scope rule (BR-K-Agreement-1) is an ACTIVATION-time invariant, NOT a create-time one,
    // so two drafts in the identical (producer, no club) scope both succeed (design D2 — drafts create freely).
    $producer = Producer::factory()->create();

    foreach (['monthly', 'quarterly'] as $cadence) {
        Livewire::test(CreateProducerAgreement::class)
            ->fillForm([
                'producer_id' => $producer->id,
                'settlement_cadence' => $cadence,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    expect(ProducerAgreement::query()->where('producer_id', $producer->id)->count())->toBe(2)
        ->and(DomainEvent::query()->where('name', 'ProducerAgreementCreated')->count())->toBe(2);
});

it('exposes the agreement create fields and no status field', function () {
    actingAs(Operator::factory()->create(), 'operator');

    Livewire::test(CreateProducerAgreement::class)
        ->assertFormFieldExists('producer_id')
        ->assertFormFieldExists('club_id')
        ->assertFormFieldExists('term_start')
        ->assertFormFieldExists('term_end')
        ->assertFormFieldExists('settlement_cadence')
        // An agreement is born `draft` by CreateProducerAgreement (design D2), so the create form never sets
        // `status`; it advances only through the ViewProducerAgreement lifecycle actions (task 9.1).
        ->assertFormFieldDoesNotExist('status');
});

it('offers the settlement cadence as a select over the closed set, not a free-text input (canon MVP-DEC-010/RM-22)', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // The `Select $field` type-hint IS the "it is a Select, not the former free-text TextInput" assertion — a
    // TextInput would raise a TypeError on the closure. getOptions() pins the closed SettlementCadence set, keyed by
    // the enum backing values (the operand enum drives the option set).
    Livewire::test(CreateProducerAgreement::class)
        ->assertFormFieldExists(
            'settlement_cadence',
            fn (Select $field): bool => array_keys($field->getOptions()) === ['quarterly', 'monthly', 'semi_annual'],
        );
});

it('defaults the settlement cadence to quarterly when the operator leaves it untouched', function () {
    actingAs(Operator::factory()->create(), 'operator');
    $producer = Producer::factory()->create();

    // The Select pre-fills `quarterly` (SettlementCadence::default()) — a free-text input would persist null. Fill
    // only the required Producer, leaving the cadence at its mounted default.
    Livewire::test(CreateProducerAgreement::class)
        ->fillForm(['producer_id' => $producer->id])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(ProducerAgreement::query()->sole()->settlement_cadence)->toBe(SettlementCadence::Quarterly);
});

it('offers only the selected Producer\'s active Clubs in the narrowing picker — a sunset/closed Club is not selectable (BR-K-Agreement-4)', function () {
    $producer = Producer::factory()->create();
    $active = Club::factory()->create(['producer_id' => $producer->id, 'display_name' => 'Active Circle']);
    $sunset = Club::factory()->create(['producer_id' => $producer->id, 'status' => ClubStatus::Sunset]);
    $closed = Club::factory()->create(['producer_id' => $producer->id, 'status' => ClubStatus::Closed]);
    $otherProducersClub = Club::factory()->create(); // a different Producer's active Club — out of scope

    // The picker filters to the chosen Producer's `active` Clubs only (canon MVP-DEC-009). Asserted directly on the
    // shared option helper the reactive Select + the relation manager both consume — deterministic, no Livewire
    // reactive-state fixture needed.
    $options = ProducerAgreementResource::activeClubOptions($producer->id);

    expect(array_keys($options))->toBe([$active->id])
        ->and($options)->not->toHaveKey($sunset->id)
        ->and($options)->not->toHaveKey($closed->id)
        ->and($options)->not->toHaveKey($otherProducersClub->id);

    // A null Producer (nothing chosen yet — the reactive form's initial state) yields no Clubs.
    expect(ProducerAgreementResource::activeClubOptions(null))->toBe([]);
});

it('rejects and surfaces a forced non-active Club (the ProducerAgreementClubNotActive server floor), creating no agreement or event', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $producer = Producer::factory()->create();
    $sunset = Club::factory()->create(['producer_id' => $producer->id, 'status' => ClubStatus::Sunset]);

    // The picker excludes the sunset Club, but the server-side BR-K-Agreement-4 guard is the floor if a stale/forced
    // value arrives: the action's ProducerAgreementClubNotActive (a RuntimeException) is mapped to a form error by
    // the kit base, the transaction rolls back — no agreement row, no ProducerAgreementCreated event.
    Livewire::test(CreateProducerAgreement::class)
        ->fillForm([
            'producer_id' => $producer->id,
            'club_id' => $sunset->id,
        ])
        ->call('create')
        ->assertHasFormErrors();

    expect(ProducerAgreement::query()->exists())->toBeFalse()
        ->and(DomainEvent::query()->where('name', 'ProducerAgreementCreated')->exists())->toBeFalse();
});

it('rejects and surfaces a forced out-of-set settlement cadence (the InvalidSettlementCadence server floor), creating no agreement or event', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $producer = Producer::factory()->create();

    // The Select constrains the operator to the closed set, but a forced out-of-set token still hits the action's
    // RM-22 InvalidSettlementCadence guard — surfaced by the kit base as a form error, transaction rolled back.
    Livewire::test(CreateProducerAgreement::class)
        ->fillForm([
            'producer_id' => $producer->id,
            'settlement_cadence' => 'annual',
        ])
        ->call('create')
        ->assertHasFormErrors();

    expect(ProducerAgreement::query()->exists())->toBeFalse()
        ->and(DomainEvent::query()->where('name', 'ProducerAgreementCreated')->exists())->toBeFalse();
});
