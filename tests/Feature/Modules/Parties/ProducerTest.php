<?php

use App\Modules\Parties\Actions\CreateProducer;
use App\Modules\Parties\Enums\ProducerStatus;
use App\Modules\Parties\Events\ProducerCreated;
use App\Modules\Parties\Models\Producer;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use App\Platform\I18n\TranslatableText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pins the Producer — the winery identity registry and the FIRST Parties spine entity (parties-core task
 * 2.1; design D2/D3/D4/D7/D10; party-registry — Requirement: Producer Registry, Birth States Recorded,
 * Spine Creation Events). It proves the CreateProducer action persists the Producer in `draft`, holds its
 * translatable description through the TranslatableTextCast with English fallback, records ProducerCreated
 * through the platform recorder in the SAME transaction (PII-free, parties by id only), never auto-creates a
 * Supplier (BR-K-Producer-3, design D10), and holds the scope guard (no transition out of `draft`, no
 * lifecycle event).
 *
 * RefreshDatabase (per the task hint): the action opens its OWN DB::transaction, so the recorder's
 * `transactionLevel() === 0` guard is satisfied by the savepoint even under the wrapper. Portability: the
 * description is read back THROUGH the TranslatableTextCast and the event payload BY KEY — never a
 * byte-compare of stored JSON (PG jsonb reorders keys — knowledge/testing trap 3).
 */
uses(RefreshDatabase::class);

it('creates a Producer in draft with its identity attributes and translatable description', function () {
    $producer = app(CreateProducer::class)->handle(
        name: 'Château Margaux',
        region: 'Bordeaux',
        country: 'France',
        appellation: 'Margaux',
        description: TranslatableText::of(['en' => 'A First Growth estate.']),
        website: 'https://chateau-margaux.com',
    );

    // Re-fetch so the assertions exercise the read/hydration casts, not the in-memory create() values.
    $read = Producer::findOrFail($producer->id);

    expect($read->name)->toBe('Château Margaux')
        ->and($read->region)->toBe('Bordeaux')
        ->and($read->country)->toBe('France')
        ->and($read->appellation)->toBe('Margaux')
        ->and($read->website)->toBe('https://chateau-margaux.com')
        ->and($read->status)->toBe(ProducerStatus::Draft)   // born draft (design D2)
        ->and($read->version)->toBe(1);                      // version floor, born at 1
});

it('creates a Producer with only its required identity attributes (optionals stay null)', function () {
    $producer = app(CreateProducer::class)->handle(
        name: 'Domaine de la Romanée-Conti',
        region: 'Burgundy',
        country: 'France',
    );

    $read = Producer::findOrFail($producer->id);

    expect($read->status)->toBe(ProducerStatus::Draft)
        ->and($read->appellation)->toBeNull()
        ->and($read->description)->toBeNull()
        ->and($read->website)->toBeNull();
});

it('records a ProducerCreated domain event in the same transaction, tagged parties and PII-free', function () {
    $producer = app(CreateProducer::class)->handle(
        name: 'Penfolds',
        region: 'Barossa Valley',
        country: 'Australia',
        appellation: 'South Australia',
    );

    // sole() asserts EXACTLY one ProducerCreated row exists — the one-event contract.
    $event = DomainEvent::query()->where('name', ProducerCreated::NAME)->sole();

    expect($event->module)->toBe('parties')                  // Module::Parties->value
        ->and($event->entity_type)->toBe('Producer')
        ->and($event->entity_id)->toBe((string) $producer->id) // envelope entity_id is a string
        ->and($event->actor_role)->toBe(ActorRole::System);  // the ActorContext seam default

    // Payload asserted BY KEY through the array cast (trap 3); the Producer is not a Party, so the payload
    // carries only its structural identity — no party/personal data, and the translatable description is
    // read through a read contract, never restated in the event (mirrors ProductMasterCreated/winery_story).
    expect($event->payload['producer_id'])->toBe($producer->id)
        ->and($event->payload['name'])->toBe('Penfolds')
        ->and($event->payload['region'])->toBe('Barossa Valley')
        ->and($event->payload['country'])->toBe('Australia')
        ->and($event->payload['appellation'])->toBe('South Australia')
        ->and($event->payload['status'])->toBe('draft');

    expect($event->payload)->not->toHaveKey('description');
});

it('resolves the description with per-attribute English fallback (assert through the cast — trap 3)', function () {
    $producer = app(CreateProducer::class)->handle(
        name: 'Domaine Leflaive',
        region: 'Burgundy',
        country: 'France',
        appellation: 'Puligny-Montrachet',
        description: TranslatableText::of([
            'en' => 'A storied Burgundy domaine.',
            'fr' => 'Un domaine bourguignon réputé.',
        ]),
    );

    // Re-fetch so the read exercises the TranslatableTextCast (JSON column → TranslatableText), never a
    // byte-compare of the stored JSON (PG jsonb reorders keys).
    $read = Producer::findOrFail($producer->id);

    expect($read->description)->toBeInstanceOf(TranslatableText::class)
        ->and($read->description?->resolve('fr'))->toBe('Un domaine bourguignon réputé.')  // exact locale
        ->and($read->description?->resolve('it'))->toBe('A storied Burgundy domaine.')      // absent → English fallback
        ->and($read->description?->resolve('en'))->toBe('A storied Burgundy domaine.');
});

it('does not auto-create a Supplier as a side effect (BR-K-Producer-3, design D10)', function () {
    app(CreateProducer::class)->handle(
        name: 'Tenuta San Guido',
        region: 'Tuscany',
        country: 'Italy',
    );

    // D10 no-auto-cross-create: a Producer never spawns a Supplier. The parties_suppliers table arrives in
    // task 2.2; this guard asserts zero Supplier rows once the table exists and is a no-op (the table's
    // absence already proves no Supplier was created) until then — so it stays green across both iterations.
    if (Schema::hasTable('parties_suppliers')) {
        expect(DB::table('parties_suppliers')->count())->toBe(0);
    }

    // Only the Producer was created and only its Created event recorded — no cross-entity side effect.
    expect(Producer::query()->count())->toBe(1)
        ->and(DomainEvent::query()->count())->toBe(1);
});

it('records no lifecycle-transition event — the Producer stays draft (scope guard)', function () {
    $producer = app(CreateProducer::class)->handle(
        name: 'Opus One',
        region: 'Napa Valley',
        country: 'United States',
    );

    // Design D2 scope guard: only the *Created event exists — never a *Activated/*Retired (the deferred
    // parties-membership-lifecycle change owns those).
    expect(DomainEvent::query()->where('name', 'like', '%Activated%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Retired%')->count())->toBe(0)
        ->and(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Draft);
});

it('produces a draft Producer via the factory without recording an event', function () {
    // The factory is a pure fixture: it bypasses the action, so it persists a draft Producer but records no
    // ProducerCreated (later tasks lean on it to stand up a parent Producer cheaply — Club, Agreement).
    $producer = Producer::factory()->create();

    expect($producer->status)->toBe(ProducerStatus::Draft)
        ->and($producer->version)->toBe(1)
        ->and(DomainEvent::query()->count())->toBe(0);
});
