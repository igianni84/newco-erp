<?php

// Task 6.1 (operator-console-parties-producer; design D1/D3/D4/D5/D6; ADR 2026-06-20; spec — the change's
// CLOSING integration proof) — one feature test that drives a Producer through the WHOLE console slice
// end-to-end, exactly as a human operator would demo it through the PAGES (not the raw Actions): create
// (CreateProducer page) → requireKyc → verifyKyc → activate → (seed two operated active Clubs) → retire — and
// asserts the EMERGENT event SET over the entire run (the closing-integration rule, knowledge/testing/rules.md).
// It proves three things that hold over the COMPOSED chain which no single per-task test proves alone:
//   1. the KYC steps are EVENT-SILENT — requireKyc + verifyKyc add NO domain event (Producer KYC is audit-only,
//      § 4.4) — so the emergent set is EXACTLY create / activate / retire / sunset×2 and nothing extra leaks.
//   2. EVERY recorded event is a Parties console-driven write carrying the operator audit envelope (module
//      `parties`, actor_role newco_ops, a non-null operator actor) — proven SET-WIDE, not entity-by-entity. The
//      Catalog ProducerLifecycleProjector consumes ProducerActivated/Retired into a READ MODEL
//      (catalog_producer_states) and records NO domain event (verified: it writes ProducerState, never the
//      recorder), and nothing consumes ClubSunset — so domain_events holds ONLY the 5 Parties writes, no
//      System-actor projection rows to scope out.
//   3. the retirement CASCADE is ONE queryable thread — both ClubSunset carry the ProducerRetired event's id as
//      causation_id (§ 10.2, Producer → Club leg).
//
// DatabaseMigrations (mirroring the per-task console tests): each console action drives a real domain action that
// opens its OWN DB::transaction, so the DomainEventRecorder's in-transaction append commits for real — the
// faithful production shape (RefreshDatabase would wrap every write in a never-committed outer transaction). The
// two Clubs are seeded EVENT-FREE via Club::factory() (the SunsetClub action is NOT used to stand them up), so
// the only events are the ones the console actions record. Parties enums/models/pages are imported freely here:
// the {Models, Actions} import-boundary carve-out governs OperatorPanel PRODUCTION code, not tests.
//
// Green on SQLite AND PG17 (the change's PG17 gate): the uncast `actor_id` / `causation_id` bigints read back as
// numeric strings on PostgreSQL, so they are asserted with loose `toEqual`.

use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerResource\Pages\CreateProducer;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerResource\Pages\ViewProducer;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Enums\ClubStatus;
use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Enums\ProducerStatus;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Producer;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

it('drives the entire Producer console slice end-to-end as an operator demo, asserting the emergent event set and the newco_ops envelope on every write', function () {
    // ONE operator drives the whole demo — Producer activation is KYC-gated, not a Creator → Reviewer → Approver
    // separation-of-duties transition (design D3), so no distinct lineage is needed. Every event below must carry
    // this operator's id (actor_role newco_ops), resolved by the actions from the `operator` guard.
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    // ── CREATE through the console page → a `draft` Producer, never screened (kyc_status NULL), 1 ProducerCreated.
    Livewire::test(CreateProducer::class)
        ->fillForm([
            'name' => 'Domaine Chain',
            'region' => 'Côte de Nuits',
            'country' => 'France',
            'appellation' => 'Vosne-Romanée',
            'website' => 'https://domaine-chain.example',
            'description' => 'An estate driven end-to-end through the operator console.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $producer = Producer::query()->where('name', 'Domaine Chain')->sole();
    expect($producer->status)->toBe(ProducerStatus::Draft)
        ->and($producer->kyc_status)->toBeNull();

    // ── KYC: requireKyc (NULL → pending) then verifyKyc (pending → verified) — BOTH audit-only (NO event, § 4.4).
    Livewire::test(ViewProducer::class, ['record' => $producer->id])
        ->callAction('requireKyc')
        ->assertNotified((string) __('operator_console.producer.notifications.kyc_required'));

    Livewire::test(ViewProducer::class, ['record' => $producer->id])
        ->callAction('verifyKyc')
        ->assertNotified((string) __('operator_console.producer.notifications.kyc_verified'));

    // The KYC FSM advanced to `verified`; the status FSM is still on its `draft` birth state (a separate FSM).
    $afterKyc = Producer::findOrFail($producer->id);
    expect($afterKyc->kyc_status)->toBe(KycStatus::Verified)
        ->and($afterKyc->status)->toBe(ProducerStatus::Draft);

    // ── ACTIVATE — KYC `verified` clears the activation gate: draft → active + exactly one ProducerActivated.
    Livewire::test(ViewProducer::class, ['record' => $producer->id])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.producer.notifications.activated'));

    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Active);

    // ── Seed two operated active Clubs (event-free fixtures — the cascade's subjects), born active by the factory.
    $clubA = Club::factory()->create(['producer_id' => $producer->id]);
    $clubB = Club::factory()->create(['producer_id' => $producer->id]);

    // ── RETIRE — active → retired + the § 10.2 cascade: each operated active Club is sunset (one ClubSunset each).
    Livewire::test(ViewProducer::class, ['record' => $producer->id])
        ->callAction('retire')
        ->assertNotified((string) __('operator_console.producer.notifications.retired'));

    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Retired)
        ->and(Club::findOrFail($clubA->id)->status)->toBe(ClubStatus::Sunset)
        ->and(Club::findOrFail($clubB->id)->status)->toBe(ClubStatus::Sunset);

    // ══ Emergent event-SET proof over the WHOLE demo ═══════════════════════════════════════════════════════
    // (a) the emergent set is EXACTLY create / activate / retire / sunset×2 — the two KYC steps added NO event
    //     (audit-only, § 4.4) and the Catalog projection consumes the lifecycle events into a read model (never
    //     the recorder), so nothing else leaked in across the composed chain.
    expect(DomainEvent::query()->pluck('name')->all())
        ->toEqualCanonicalizing(['ProducerCreated', 'ProducerActivated', 'ProducerRetired', 'ClubSunset', 'ClubSunset']);

    // (b) EVERY recorded event is a Parties console-driven write carrying the operator audit envelope — module
    //     `parties`, actor_role newco_ops, a non-null operator actor (no System-actor projection rows exist: the
    //     projector writes ProducerState, never a domain event).
    $events = DomainEvent::query()->get();
    expect($events)->toHaveCount(5);
    foreach ($events as $event) {
        expect($event->module)->toBe('parties')
            ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
            ->and($event->actor_id)->not->toBeNull();
    }

    // (c) …and the actor_id is concretely the ACTING operator on representative writes spanning BOTH surfaces —
    //     the create page (ProducerCreated) and a view-page lifecycle action (ProducerRetired). Loose toEqual is
    //     the proven idiom: the uncast bigint reads back as a numeric string on PG, never strict-compare it.
    $created = DomainEvent::query()->where('name', 'ProducerCreated')->sole();
    $retired = DomainEvent::query()->where('name', 'ProducerRetired')->sole();
    expect($created->actor_id)->toEqual($operator->id)
        ->and($retired->actor_id)->toEqual($operator->id);

    // (d) the retirement cascade is ONE queryable thread — both ClubSunset carry the ProducerRetired event's id
    //     as causation_id (loose toEqual — uncast bigint, numeric string on PG).
    $sunsets = DomainEvent::query()->where('name', 'ClubSunset')->get();
    expect($sunsets)->toHaveCount(2);
    foreach ($sunsets as $sunset) {
        expect($sunset->causation_id)->toEqual($retired->id);
    }
});
