<?php

// Task 3.1 / 3.2 (operator-console-parties-producer; design D1/D3/D4/D5; ADR 2026-06-19 + 2026-06-20) — the
// Producer console's write-through STATUS surface. These pin the two status verbs (activate `draft → active`,
// retire `active → retired`) the view page assembles via the SurfacesDomainActions trait — NOT the catalog
// OperatorConsoleViewRecord base (design D1), so the five catalog governance verbs (submit/reject/reopen) and a
// cascade-retire action are deliberately ABSENT (scope guard). Each action routes through a Parties domain
// action by the producer id (design D4) and NEVER writes `status` itself (the no-Eloquent-write rule); the
// console SURFACES the domain's decision — an out-of-state transition becomes the `action_failed` danger
// notification (design D5). Activation now carries a separation-of-duties floor (a distinct operator-principal
// approves, never the creator — change parties-producer-approval-sod), so activate surfaces the "second actor
// required" confirmation affordance and a creator self-approval becomes the `action_failed` notification, state
// unchanged. Retirement CASCADES sunset onto the operated active Clubs, each ClubSunset causally linked to the
// retirement (§ 10.2, Producer → Club leg).
//
// DatabaseMigrations (mirroring ProducerCreateConsoleTest + the catalog lifecycle console tests): each console
// action drives a real domain action that opens its OWN DB::transaction, so the DomainEventRecorder's
// transaction-level guard sees a real commit (level 0 → 1 → 0) — the faithful production shape (RefreshDatabase
// would wrap every write in a never-committed outer transaction). The factories bypass the actions, so they
// record NO event — the only events are the ones the console actions record. Parties enums/models are imported
// freely here: the {Models, Actions} import-boundary carve-out governs OperatorPanel PRODUCTION code, not tests.

use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerResource\Pages\ViewProducer;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Actions\CreateProducer;
use App\Modules\Parties\Enums\ClubStatus;
use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Enums\ProducerStatus;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Producer;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Filament\Actions\Action;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

it('activates a draft Producer whose KYC is cleared through the console, recording one ProducerActivated with the operator envelope', function (?KycStatus $kyc) {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    // A `draft` Producer whose KYC clears the activation gate — `verified`, `not_required`, or NULL (never
    // screened, treated as cleared for additivity, ADR 2026-06-17). The KYC gate is the domain's (design L5);
    // here it admits activation and the status transition is the subject under test.
    $producer = Producer::factory()->create(['status' => ProducerStatus::Draft, 'kyc_status' => $kyc]);

    Livewire::test(ViewProducer::class, ['record' => $producer->id])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.producer.notifications.activated'));

    // State advanced draft → active via the domain action (the console never writes `status`).
    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Active);

    // Exactly one ProducerActivated, carrying the operator audit envelope (newco_ops + the operator id) resolved
    // by the action from the `operator` guard — the console constructs no envelope itself.
    $event = DomainEvent::query()->where('name', 'ProducerActivated')->sole();

    expect($event->module)->toBe('parties')
        ->and($event->entity_type)->toBe('Producer')
        ->and($event->entity_id)->toBe((string) $producer->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($operator->id);  // loose: PG returns a numeric string for the bigint
})->with([
    'NULL kyc_status (never screened)' => [null],
    'not_required' => [KycStatus::NotRequired],
    'verified' => [KycStatus::Verified],
]);

it('retires an active Producer through the console, recording one ProducerRetired with the operator envelope', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $producer = Producer::factory()->create(['status' => ProducerStatus::Active]);

    Livewire::test(ViewProducer::class, ['record' => $producer->id])
        ->callAction('retire')
        ->assertNotified((string) __('operator_console.producer.notifications.retired'));

    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Retired);

    $event = DomainEvent::query()->where('name', 'ProducerRetired')->sole();

    expect($event->module)->toBe('parties')
        ->and($event->entity_type)->toBe('Producer')
        ->and($event->entity_id)->toBe((string) $producer->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($operator->id);
});

it('cascades sunset onto the operated active Clubs when a Producer is retired through the console, each ClubSunset caused by the retirement', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // The § 10.2 offboarding cascade (Producer → Club leg) — an active Producer operating two active Clubs and
    // one already-closed Club, retired THROUGH THE CONSOLE.
    $producer = Producer::factory()->create(['status' => ProducerStatus::Active]);
    $activeA = Club::factory()->create(['producer_id' => $producer->id]);   // born active
    $activeB = Club::factory()->create(['producer_id' => $producer->id]);   // born active
    $closed = Club::factory()->create(['producer_id' => $producer->id, 'status' => ClubStatus::Closed]);

    Livewire::test(ViewProducer::class, ['record' => $producer->id])
        ->callAction('retire')
        ->assertNotified((string) __('operator_console.producer.notifications.retired'));

    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Retired);

    // The two active Clubs are sunset; the already-closed Club is left UNCHANGED — the cascade only touches
    // active Clubs (it is idempotent over already-transitioned ones).
    expect(Club::findOrFail($activeA->id)->status)->toBe(ClubStatus::Sunset)
        ->and(Club::findOrFail($activeB->id)->status)->toBe(ClubStatus::Sunset)
        ->and(Club::findOrFail($closed->id)->status)->toBe(ClubStatus::Closed);

    // One ProducerRetired root + exactly two cascade ClubSunset, addressed at the two active Clubs (none for the
    // untouched closed Club).
    $retired = DomainEvent::query()->where('name', 'ProducerRetired')->sole();
    $sunsets = DomainEvent::query()->where('name', 'ClubSunset')->get();

    expect($sunsets)->toHaveCount(2)
        ->and($sunsets->pluck('entity_id')->all())
        ->toEqualCanonicalizing([(string) $activeA->id, (string) $activeB->id]);

    // Cascade causal linkage (design D4; § 10.2 "cascade events are causally linked to the retirement"): every
    // cascade ClubSunset carries the ProducerRetired event's `id` as `causation_id` and shares its
    // `correlation_id` — the offboarding is one queryable thread in the audit log. `causation_id` is a `bigint`
    // read back as a numeric string on PG, so it is asserted loosely; `correlation_id` is a UUID string.
    foreach ($sunsets as $sunset) {
        expect($sunset->causation_id)->toEqual($retired->id)
            ->and($sunset->correlation_id)->toBe($retired->correlation_id);
    }
});

it('surfaces an out-of-state activate as a danger notification, changing nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // An already-active Producer: activate requires `draft`, so the domain rejects the out-of-state call. The
    // console surfaces it as a danger notification; it never pre-checks the from-state (design D5).
    $producer = Producer::factory()->create(['status' => ProducerStatus::Active]);

    Livewire::test(ViewProducer::class, ['record' => $producer->id])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.producer.notifications.action_failed'));

    // Unchanged: still active, and the rejected attempt recorded NO event (its transaction rolled back).
    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Active)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('surfaces an out-of-state retire as a danger notification, changing nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A draft Producer: retire requires `active`, so the domain rejects the out-of-state call.
    $producer = Producer::factory()->create(['status' => ProducerStatus::Draft]);

    Livewire::test(ViewProducer::class, ['record' => $producer->id])
        ->callAction('retire')
        ->assertNotified((string) __('operator_console.producer.notifications.action_failed'));

    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Draft)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('exposes only the two status verbs — activate (with the second-actor SoD affordance) and retire — and none of the catalog governance verbs nor a cascade-retire action', function () {
    actingAs(Operator::factory()->create(), 'operator');
    $producer = Producer::factory()->create(['status' => ProducerStatus::Draft]);

    Livewire::test(ViewProducer::class, ['record' => $producer->id])
        // The two Producer status verbs are present …
        ->assertActionExists('activate')
        ->assertActionExists('retire')
        // … activate carries the "second actor required" confirmation affordance — Producer activation is now a
        // separation-of-duties floor (a distinct operator-principal approves, never the creator — change
        // parties-producer-approval-sod), surfaced exactly as the catalog consoles do …
        ->assertActionExists('activate', fn (Action $action): bool => $action->isConfirmationRequired()
            && $action->getModalDescription() === (string) __('operator_console.producer.affordance.second_actor'))
        // … none of the catalog governance verbs leak in (this page is NOT OperatorConsoleViewRecord — design D1) …
        ->assertActionDoesNotExist('submit')
        ->assertActionDoesNotExist('reject')
        ->assertActionDoesNotExist('reopen')
        // … and there is no separate cascade-retire action (retire cascades inside the one RetireProducer action).
        ->assertActionDoesNotExist('retireCascade');
});

it('surfaces a creator self-approval activate through the console as a danger notification, leaving the Producer draft with no ProducerActivated', function () {
    // The separation-of-duties floor (change parties-producer-approval-sod) forbids the operator who CREATED a
    // Producer from approving its own activation. A single operator both creates and tries to activate the same
    // Producer through the console: the domain throws a SeparationOfDutiesViolation (a RuntimeException), the trait
    // catches it by base type and surfaces the `action_failed` danger notification, and the rejecting action's
    // transaction rolls back — so nothing moves (the "surface, not reimplement" contract, design D5).
    $creator = Operator::factory()->create();
    actingAs($creator, 'operator');

    // Genuine creator lineage: CreateProducer records a ProducerCreated whose actor_id is $creator (resolved from
    // the operator guard), so the floor's creatorOf() recovers $creator and the same-operator activation below is a
    // self-approval. A `factory()->create()` row records no ProducerCreated (null creator) → the floor is vacuously
    // cleared, which is exactly why this case seeds the creator lineage through the real Action.
    $producer = app(CreateProducer::class)->handle(name: 'Domaine Leflaive', region: 'Burgundy', country: 'FR');

    Livewire::test(ViewProducer::class, ['record' => $producer->id])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.producer.notifications.action_failed'));

    // Unchanged: still draft, and the rejected self-approval recorded NO ProducerActivated (its transaction rolled
    // back — the SoD floor runs inside the domain transaction, before any write). The ProducerCreated from the
    // create step remains, so the assertion is scoped by name rather than a total-count of zero.
    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Draft)
        ->and(DomainEvent::query()->where('name', 'ProducerActivated')->count())->toBe(0);
});
