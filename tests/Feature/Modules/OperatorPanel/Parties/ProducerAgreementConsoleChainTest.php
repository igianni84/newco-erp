<?php

// Task 11.1 (operator-console-parties-supply-side; design D1/D3/D4/D5/D8; ADR 2026-06-19 + 2026-06-20; spec — the
// change's CLOSING integration proof for the ProducerAgreement console) — one feature test driving an agreement
// renewal through the WHOLE console slice end-to-end through the PAGES (not the raw Actions), exactly as a human
// operator would demo it: create A (CreateProducerAgreement page) → activate A → create B in the SAME scope →
// activate B (which SUPERSEDES A inline) → terminate B (ViewProducerAgreement page). It asserts the EMERGENT event
// SET over the entire run (the closing-integration rule, knowledge/testing/rules.md), proving three things that
// hold over the COMPOSED chain which no single per-task test asserts alone:
//   1. the emergent set is EXACTLY create×2 / activate×2 / supersede×1 / terminate×1 and nothing extra leaks in —
//      NOTHING in the codebase consumes a ProducerAgreement lifecycle event into the recorder (grep-verified: no
//      listener/projector/subscriber references ProducerAgreementCreated/Activated/Superseded/Terminated; only the
//      event defs, the model, and the OperatorPanel pages name them). So domain_events holds ONLY the six Parties
//      console writes — no System-actor projection rows to scope out.
//   2. EVERY recorded event is a Parties console-driven write carrying the operator audit envelope (module
//      `parties`, actor_role newco_ops, a non-null operator actor) — proven SET-WIDE, then concretely tied to the
//      acting operator on representative writes spanning BOTH surfaces (the create page + a view-page verb).
//   3. supersession is ONE queryable causal thread — the single ProducerAgreementSuperseded (addressed at the
//      prior A) carries B's ProducerAgreementActivated event id as its causation_id (design D8/L5: the
//      supersession is CAUSED BY the activation that triggered it), proven END-TO-END through the pages.
//
// DatabaseMigrations (mirroring the per-task console tests + ProducerConsoleChainTest): each console action drives
// a real domain action that opens its OWN DB::transaction, so the DomainEventRecorder's in-transaction append
// commits for real — the faithful production shape (RefreshDatabase would wrap every write in a never-committed
// outer transaction). The operating Producer is seeded EVENT-FREE via Producer::factory() (the factory bypasses
// the actions), so the only events are the ones the console actions record. The two agreements share ONE Producer
// and are both Producer-wide (NULL club_id) — the same `(producer_id, club_id)` scope, so activating B supersedes
// A (BR-K-Agreement-1: at most one active per scope). They are distinguished by their free-string settlement
// cadence (`monthly` = A, `quarterly` = B), which does not affect the scope. Parties enums/models/pages are
// imported freely here: the {Models, Actions, Enums} import-boundary carve-out governs OperatorPanel PRODUCTION
// code, not tests.
//
// Green on SQLite AND PG17 (the change's PG17 gate): the uncast `actor_id` / `causation_id` bigints read back as
// numeric strings on PostgreSQL, so they are asserted with loose `toEqual`.

use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerAgreementResource\Pages\CreateProducerAgreement;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerAgreementResource\Pages\ViewProducerAgreement;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Enums\ProducerAgreementStatus;
use App\Modules\Parties\Models\Producer;
use App\Modules\Parties\Models\ProducerAgreement;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

it('drives the entire ProducerAgreement console slice end-to-end as an operator demo (create→activate→renew→supersede→terminate), asserting the emergent event set, the newco_ops envelope on every write, and the supersession causal thread', function () {
    // ONE operator drives the whole demo — agreement lifecycle is single-operator (no Creator → Reviewer →
    // Approver separation of duties, design D3), so no distinct lineage is needed. Every event below must carry
    // this operator's id (actor_role newco_ops), resolved by the actions from the `operator` guard.
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    // An existing operating Producer (factory-built → records no event), so CreateProducerAgreement's
    // MissingAgreementProducer pre-check passes and the only recorded events are the console's. Both agreements
    // are created Producer-wide (no club) under THIS Producer — the same `(producer_id, club_id)` scope.
    $producer = Producer::factory()->create();

    // ── CREATE A through the console page → a Producer-wide agreement born `draft`, 1 ProducerAgreementCreated.
    Livewire::test(CreateProducerAgreement::class)
        ->fillForm([
            'producer_id' => $producer->id,
            // club_id left blank → a Producer-wide agreement (§ 4.6).
            'settlement_cadence' => 'monthly',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $a = ProducerAgreement::query()->where('settlement_cadence', 'monthly')->sole();
    expect($a->status)->toBe(ProducerAgreementStatus::Draft)
        ->and($a->club_id)->toBeNull();

    // ── ACTIVATE A through the view page — draft → active. No prior active in scope, so a plain transition with
    //    NO supersession side-effect (one ProducerAgreementActivated, no ProducerAgreementSuperseded yet).
    Livewire::test(ViewProducerAgreement::class, ['record' => $a->id])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.producer_agreement.notifications.activated'));

    expect(ProducerAgreement::findOrFail($a->id)->status)->toBe(ProducerAgreementStatus::Active);

    // ── CREATE B through the console page in the SAME (producer, no club) scope → a second `draft` agreement.
    //    Two drafts coexist freely — single-active is an ACTIVATION-time invariant, not a create-time one (D2).
    Livewire::test(CreateProducerAgreement::class)
        ->fillForm([
            'producer_id' => $producer->id,
            'settlement_cadence' => 'quarterly',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $b = ProducerAgreement::query()->where('settlement_cadence', 'quarterly')->sole();
    expect($b->status)->toBe(ProducerAgreementStatus::Draft);

    // ── ACTIVATE B through the view page — draft → active, and because A is the prior active in the same scope,
    //    activation SUPERSEDES A inline in the same transaction (D8): B → active, A → superseded, and one
    //    derived ProducerAgreementSuperseded recorded (never momentarily double-active).
    Livewire::test(ViewProducerAgreement::class, ['record' => $b->id])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.producer_agreement.notifications.activated'));

    expect(ProducerAgreement::findOrFail($b->id)->status)->toBe(ProducerAgreementStatus::Active)
        ->and(ProducerAgreement::findOrFail($a->id)->status)->toBe(ProducerAgreementStatus::Superseded);

    // ── TERMINATE B through the view page — active → terminated + one ProducerAgreementTerminated.
    Livewire::test(ViewProducerAgreement::class, ['record' => $b->id])
        ->callAction('terminate')
        ->assertNotified((string) __('operator_console.producer_agreement.notifications.terminated'));

    expect(ProducerAgreement::findOrFail($b->id)->status)->toBe(ProducerAgreementStatus::Terminated);

    // ══ Emergent event-SET proof over the WHOLE demo ═══════════════════════════════════════════════════════
    // (a) the emergent set is EXACTLY create×2 / activate×2 / supersede×1 / terminate×1 — nothing consumes a
    //     ProducerAgreement lifecycle event into the recorder (no listener/projector records a domain event in
    //     response), so nothing else leaked in across the composed chain.
    expect(DomainEvent::query()->pluck('name')->all())
        ->toEqualCanonicalizing([
            'ProducerAgreementCreated',
            'ProducerAgreementActivated',
            'ProducerAgreementCreated',
            'ProducerAgreementActivated',
            'ProducerAgreementSuperseded',
            'ProducerAgreementTerminated',
        ]);

    // (b) EVERY recorded event is a Parties console-driven write carrying the operator audit envelope — module
    //     `parties`, actor_role newco_ops, a non-null operator actor (no System-actor projection rows exist).
    $events = DomainEvent::query()->get();
    expect($events)->toHaveCount(6);
    foreach ($events as $event) {
        expect($event->module)->toBe('parties')
            ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
            ->and($event->actor_id)->not->toBeNull();
    }

    // (c) …and the actor_id is concretely the ACTING operator on representative writes spanning BOTH surfaces —
    //     the create page (B's ProducerAgreementCreated) and a view-page lifecycle action (ProducerAgreementTerminated).
    //     Loose toEqual is the proven idiom: the uncast bigint reads back as a numeric string on PG.
    $bCreated = DomainEvent::query()
        ->where('name', 'ProducerAgreementCreated')
        ->where('entity_id', (string) $b->id)
        ->sole();
    $terminated = DomainEvent::query()->where('name', 'ProducerAgreementTerminated')->sole();
    expect($bCreated->actor_id)->toEqual($operator->id)
        ->and($terminated->actor_id)->toEqual($operator->id);

    // (d) supersession is ONE queryable causal thread — the single ProducerAgreementSuperseded is addressed at the
    //     prior A and carries B's ProducerAgreementActivated event id as its causation_id (D8/L5: the supersession
    //     is caused by the activation that triggered it). Loose toEqual — uncast bigint, numeric string on PG.
    $bActivated = DomainEvent::query()
        ->where('name', 'ProducerAgreementActivated')
        ->where('entity_id', (string) $b->id)
        ->sole();
    $superseded = DomainEvent::query()->where('name', 'ProducerAgreementSuperseded')->sole();
    expect($superseded->entity_id)->toBe((string) $a->id)
        ->and($superseded->causation_id)->toEqual($bActivated->id);
});
