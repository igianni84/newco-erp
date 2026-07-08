<?php

// Task 6.1 (operator-console-parties-supply-side; design D1/D3/D4/D5/D6/D9; ADR 2026-06-21; spec — the change's
// CLOSING integration proof for the Club console) — one feature test driving a Club through the WHOLE console
// slice end-to-end through the PAGES (not the raw Actions), exactly as a human operator would demo it:
// create (CreateClub page) → sunset → close (ViewClub page). It asserts the EMERGENT event SET over the entire run
// (the closing-integration rule, knowledge/testing/rules.md), proving two things that hold over the COMPOSED chain
// which no single per-task test asserts alone:
//   1. the emergent set is EXACTLY ClubCreated / ClubSunset / ClubClosed and nothing extra leaks in — NOTHING in
//      the codebase consumes a Club lifecycle event into the recorder (grep-verified: no listener/projector
//      references ClubCreated/ClubSunset/ClubClosed; the predecessor's ProducerConsoleChainTest independently
//      pinned that ClubSunset spawns no projection event, via its toHaveCount(5)). So domain_events holds ONLY the
//      three Parties console writes — no System-actor projection rows to scope out.
//   2. EVERY recorded event is a Parties console-driven write carrying the operator audit envelope (module
//      `parties`, actor_role newco_ops, a non-null operator actor) — proven SET-WIDE, then concretely tied to the
//      acting operator on representative writes spanning BOTH surfaces (the create page + a view-page verb).
//
// DatabaseMigrations (mirroring the per-task console tests + ProducerConsoleChainTest): each console action drives
// a real domain action that opens its OWN DB::transaction, so the DomainEventRecorder's in-transaction append
// commits for real — the faithful production shape (RefreshDatabase would wrap every write in a never-committed
// outer transaction). The operating Producer is seeded EVENT-FREE via Producer::factory() (the factory bypasses
// the actions), so the only events are the ones the console actions record. Parties enums/models/pages are
// imported freely here: the {Models, Actions, Enums} import-boundary carve-out governs OperatorPanel PRODUCTION
// code, not tests.
//
// Green on SQLite AND PG17 (the change's PG17 gate): the uncast `actor_id` bigint reads back as a numeric string
// on PostgreSQL, so it is asserted with loose `toEqual`.

use App\Modules\OperatorPanel\Filament\Resources\Parties\ClubResource\Pages\CreateClub;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ClubResource\Pages\ViewClub;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Enums\ClubStatus;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Producer;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

it('drives the entire Club console slice end-to-end as an operator demo, asserting the emergent event set and the newco_ops envelope on every write', function () {
    // ONE operator drives the whole demo — Club lifecycle is single-operator (no Creator → Reviewer → Approver
    // separation of duties, design D3), so no distinct lineage is needed. Every event below must carry this
    // operator's id (actor_role newco_ops), resolved by the actions from the `operator` guard.
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    // An existing operating Producer (factory-built → records no event), so CreateClub's MissingClubProducer
    // pre-check passes and the only recorded ClubCreated is the console's.
    $producer = Producer::factory()->create();

    // ── CREATE through the console page → a Club born `active`, 1 ClubCreated.
    Livewire::test(CreateClub::class)
        ->fillForm([
            'display_name' => 'Cercle Chain',
            'producer_id' => $producer->id,
            'registration_flow_type' => 'invitation_only',
            'amount' => '50000',
            'currency' => 'EUR',
            'generates_credit' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $club = Club::query()->where('display_name', 'Cercle Chain')->sole();
    expect($club->status)->toBe(ClubStatus::Active);

    // ── SUNSET through the view page — active → sunset + exactly one ClubSunset (the console never writes `status`).
    Livewire::test(ViewClub::class, ['record' => $club->id])
        ->callAction('sunset')
        ->assertNotified((string) __('operator_console.club.notifications.sunset'));

    expect(Club::findOrFail($club->id)->status)->toBe(ClubStatus::Sunset);

    // ── CLOSE through the view page — sunset → closed + exactly one ClubClosed. Close is reachable ONLY from
    //    `sunset` (D9): the create-born `active` Club had to pass through sunset first.
    Livewire::test(ViewClub::class, ['record' => $club->id])
        ->callAction('close')
        ->assertNotified((string) __('operator_console.club.notifications.closed'));

    expect(Club::findOrFail($club->id)->status)->toBe(ClubStatus::Closed);

    // ══ Emergent event-SET proof over the WHOLE demo ═══════════════════════════════════════════════════════
    // (a) the emergent set is EXACTLY create / sunset / close — nothing consumes a Club lifecycle event into the
    //     recorder (no listener/projector records a domain event in response), so nothing else leaked in across
    //     the composed chain.
    expect(DomainEvent::query()->pluck('name')->all())
        ->toEqualCanonicalizing(['ClubCreated', 'ClubSunset', 'ClubClosed']);

    // (b) EVERY recorded event is a Parties console-driven write carrying the operator audit envelope — module
    //     `parties`, actor_role newco_ops, a non-null operator actor (no System-actor projection rows exist).
    $events = DomainEvent::query()->get();
    expect($events)->toHaveCount(3);
    foreach ($events as $event) {
        expect($event->module)->toBe('parties')
            ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
            ->and($event->actor_id)->not->toBeNull();
    }

    // (c) …and the actor_id is concretely the ACTING operator on representative writes spanning BOTH surfaces —
    //     the create page (ClubCreated) and a view-page lifecycle action (ClubClosed). Loose toEqual is the proven
    //     idiom: the uncast bigint reads back as a numeric string on PG, never strict-compare it.
    $created = DomainEvent::query()->where('name', 'ClubCreated')->sole();
    $closed = DomainEvent::query()->where('name', 'ClubClosed')->sole();
    expect($created->actor_id)->toEqual($operator->id)
        ->and($closed->actor_id)->toEqual($operator->id);
});
