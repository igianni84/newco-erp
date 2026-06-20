<?php

// Task 2.1 / 2.2 (operator-console-parties-producer; design D6/D8; ADR 2026-06-19 + 2026-06-20) — the Producer
// operator console's write-through Create surface. These assertions pin the one law (the page NEVER saves the
// model; it routes the form into the Parties CreateProducer action) and the audit envelope (a console-driven
// create records ProducerCreated with actor_role newco_ops + the operator id, resolved automatically from the
// `operator` guard via the platform ActorContext seam — the console builds no envelope). The create form
// exposes only the scalar identity inputs — never `status` or `kyc_status` (both FSMs advance through the
// view-page lifecycle actions, tasks 3.1/4.1). Producer ships NO create-time uniqueness guard (design D6), so
// two Producers with the same name both succeed.
//
// DatabaseMigrations (mirroring ProductMasterCreateConsoleTest): the create flow drives a real domain action
// that opens its OWN DB::transaction inside Filament's create() transaction, so the DomainEventRecorder's
// in-transaction append commits for real — the faithful production shape (RefreshDatabase would wrap every
// write in a never-committed outer transaction). Parties enums/models/pages are imported freely here: the
// {Models, Actions} import-boundary carve-out governs OperatorPanel PRODUCTION code, not tests.

use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerResource\Pages\CreateProducer;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Enums\ProducerStatus;
use App\Modules\Parties\Models\Producer;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

it('creates a draft Producer through the console, recording one ProducerCreated with the operator envelope', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    Livewire::test(CreateProducer::class)
        ->fillForm([
            'name' => 'Domaine Console',
            'region' => 'Côte de Beaune',
            'country' => 'France',
            'appellation' => 'Meursault',
            'website' => 'https://domaine-console.example',
            'description' => 'A console-created estate.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // The write routed through the action: a Producer born `draft`, never screened (kyc_status NULL), with the
    // optional attributes captured. The description is wrapped as English-baseline TranslatableText by the page.
    $producer = Producer::query()->where('name', 'Domaine Console')->sole();

    expect($producer->status)->toBe(ProducerStatus::Draft)
        ->and($producer->kyc_status)->toBeNull()
        ->and($producer->region)->toBe('Côte de Beaune')
        ->and($producer->country)->toBe('France')
        ->and($producer->appellation)->toBe('Meursault')
        ->and($producer->website)->toBe('https://domaine-console.example')
        ->and($producer->description?->resolve('en'))->toBe('A console-created estate.');

    // Exactly one ProducerCreated, carrying the operator audit envelope (newco_ops + the operator id) resolved
    // by the action from the `operator` guard — the console constructs no envelope itself.
    $event = DomainEvent::query()->where('name', 'ProducerCreated')->sole();

    expect($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($operator->id)
        ->and($event->entity_type)->toBe('Producer')
        ->and($event->entity_id)->toBe((string) $producer->id);

    // The Producer is NOT a Party (§ 4.4) and references none, so its creation payload is a pure
    // structural-identity snapshot — the producer_id plus the WINERY's own name/region/appellation/country/
    // status, and nothing else. No party name, email or phone (PII) leaks through this event.
    expect(array_keys($event->payload))
        ->toEqualCanonicalizing(['producer_id', 'name', 'region', 'appellation', 'country', 'status'])
        ->and($event->payload)->not->toHaveKey('email')
        ->and($event->payload)->not->toHaveKey('phone');
});

it('exposes the scalar identity create fields and neither a status nor a kyc_status field', function () {
    actingAs(Operator::factory()->create(), 'operator');

    Livewire::test(CreateProducer::class)
        ->assertFormFieldExists('name')
        ->assertFormFieldExists('region')
        ->assertFormFieldExists('country')
        ->assertFormFieldExists('appellation')
        ->assertFormFieldExists('description')
        ->assertFormFieldExists('website')
        // Both FSMs advance only through the view-page lifecycle actions (tasks 3.1/4.1) — the create form
        // never sets `status` (born `draft`) nor `kyc_status` (born NULL / never-screened). Design D6.
        ->assertFormFieldDoesNotExist('status')
        ->assertFormFieldDoesNotExist('kyc_status');
});

it('creates two Producers with the same name, both succeeding', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // Producer ships NO create-time uniqueness rule (design D6 — BR-K-Producer is "standalone", not a dedup),
    // so the inherited create-rejection→form-error catch never fires for it: both submissions persist.
    for ($i = 0; $i < 2; $i++) {
        Livewire::test(CreateProducer::class)
            ->fillForm([
                'name' => 'Domaine Duplicate',
                'region' => 'Bourgogne',
                'country' => 'France',
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    expect(Producer::query()->where('name', 'Domaine Duplicate')->count())->toBe(2)
        ->and(DomainEvent::query()->where('name', 'ProducerCreated')->count())->toBe(2);
});
