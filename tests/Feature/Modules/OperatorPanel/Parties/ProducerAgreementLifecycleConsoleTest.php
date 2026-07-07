<?php

// Task 9.1 / 9.2 (operator-console-parties-supply-side; design D1/D3/D4/D5/D8; ADR 2026-06-19 + 2026-06-20) — the
// ProducerAgreement console's write-through STATUS surface. These pin the two status verbs (activate
// `draft → active`, terminate `active → terminated`) the ViewProducerAgreement page assembles via the
// SurfacesDomainActions trait — NOT the catalog OperatorConsoleViewRecord base (design D1), so the five catalog
// governance verbs (submit/reject/reopen) are deliberately ABSENT, and — the defining scope guard of this slice —
// there is NO `supersede` verb: supersession is the INLINE side-effect of activation (design D8;
// ActivateProducerAgreement enforces at most one active per `(producer_id, club_id)` scope and records the
// derived ProducerAgreementSuperseded itself), never a standalone operator action. Each action routes through a
// Parties domain action by the agreement id (design D4) and NEVER writes `status` itself (the no-Eloquent-write
// rule); the console SURFACES the domain's decision — an out-of-state transition becomes the `action_failed`
// danger notification (design D5). Agreement lifecycle is single-operator, so neither verb carries a "second
// actor" confirmation affordance (design D3). Terminating an agreement does NOT cascade onto its Producer (§
// 4.6.1) — the Producer FSM is independent of its agreements.
//
// DatabaseMigrations (mirroring ProducerLifecycleConsoleTest + ClubLifecycleConsoleTest): each console action
// drives a real domain action that opens its OWN DB::transaction, so the DomainEventRecorder's transaction-level
// guard sees a real commit (level 0 → 1 → 0) — the faithful production shape (RefreshDatabase would wrap every
// write in a never-committed outer transaction). The factories bypass the actions, so they record NO event — the
// only events are the ones the console actions record. Parties enums/models are imported freely here: the
// {Models, Actions, Enums} import-boundary carve-out governs OperatorPanel PRODUCTION code, not tests.

use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerAgreementResource\Pages\ViewProducerAgreement;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Enums\ProducerAgreementStatus;
use App\Modules\Parties\Enums\ProducerStatus;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Producer;
use App\Modules\Parties\Models\ProducerAgreement;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Filament\Actions\Action;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

it('activates a draft agreement with no prior active in scope through the console, recording one ProducerAgreementActivated and no supersession', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    // A lone `draft` agreement — nothing else active in its `(producer_id, club_id)` scope, so activation is a
    // plain transition with no supersession side-effect.
    $agreement = ProducerAgreement::factory()->create(['status' => ProducerAgreementStatus::Draft]);

    Livewire::test(ViewProducerAgreement::class, ['record' => $agreement->id])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.producer_agreement.notifications.activated'));

    // State advanced draft → active via the domain action (the console never writes `status`).
    expect(ProducerAgreement::findOrFail($agreement->id)->status)->toBe(ProducerAgreementStatus::Active);

    // Exactly one ProducerAgreementActivated, carrying the operator audit envelope (newco_ops + the operator id)
    // resolved by the action from the `operator` guard — the console constructs no envelope itself.
    $event = DomainEvent::query()->where('name', 'ProducerAgreementActivated')->sole();

    expect($event->module)->toBe('parties')
        ->and($event->entity_type)->toBe('ProducerAgreement')
        ->and($event->entity_id)->toBe((string) $agreement->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($operator->id);  // loose: PG returns a numeric string for the bigint

    // No prior active → no supersession (the OR-branch is NOT taken).
    expect(DomainEvent::query()->where('name', 'ProducerAgreementSuperseded')->count())->toBe(0);
});

it('supersedes a prior active agreement when a draft in the same scope is activated through the console, the supersession caused by the activation (D8 side-effect)', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // Two agreements in the SAME `(producer_id, club_id)` scope (one shared Producer, both Producer-wide — a NULL
    // club_id is the distinct Producer-wide scope): a prior `active` and a new `draft`. BR-K-Agreement-1 admits at
    // most one active per scope, so activating the draft supersedes the prior INLINE (design D8). The factories
    // record NO event — the only events are the activation + its derived supersession.
    $producer = Producer::factory()->create();
    $prior = ProducerAgreement::factory()->create([
        'producer_id' => $producer->id,
        'club_id' => null,
        'status' => ProducerAgreementStatus::Active,
    ]);
    $draft = ProducerAgreement::factory()->create([
        'producer_id' => $producer->id,
        'club_id' => null,
        'status' => ProducerAgreementStatus::Draft,
    ]);

    Livewire::test(ViewProducerAgreement::class, ['record' => $draft->id])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.producer_agreement.notifications.activated'));

    // The draft is now active; the prior was superseded in the same transaction (never momentarily double-active).
    expect(ProducerAgreement::findOrFail($draft->id)->status)->toBe(ProducerAgreementStatus::Active)
        ->and(ProducerAgreement::findOrFail($prior->id)->status)->toBe(ProducerAgreementStatus::Superseded);

    // Exactly one activation (addressed at the new agreement) and exactly one supersession (addressed at the prior).
    $activated = DomainEvent::query()->where('name', 'ProducerAgreementActivated')->sole();
    $superseded = DomainEvent::query()->where('name', 'ProducerAgreementSuperseded')->sole();

    expect($activated->entity_id)->toBe((string) $draft->id)
        ->and($superseded->entity_id)->toBe((string) $prior->id);

    // The supersession carries the operator envelope and is causally linked to the activation (design L5): it
    // threads the ProducerAgreementActivated event's `id` as `causation_id` and shares its `correlation_id` — the
    // renewal is one queryable thread. `causation_id` is a bigint read back as a numeric string on PG, so it is
    // asserted loosely; `correlation_id` is a UUID string.
    expect($superseded->module)->toBe('parties')
        ->and($superseded->entity_type)->toBe('ProducerAgreement')
        ->and($superseded->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($superseded->causation_id)->toEqual($activated->id)
        ->and($superseded->correlation_id)->toBe($activated->correlation_id);
});

it('terminates an active agreement through the console without cascading onto its Producer, recording one ProducerAgreementTerminated', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    // An `active` agreement under an `active` Producer. Terminating the agreement must NOT change the Producer
    // (§ 4.6.1 — the Producer FSM is independent of its agreements; only Producer retirement cascades).
    $producer = Producer::factory()->create(['status' => ProducerStatus::Active]);
    $agreement = ProducerAgreement::factory()->create([
        'producer_id' => $producer->id,
        'status' => ProducerAgreementStatus::Active,
    ]);

    Livewire::test(ViewProducerAgreement::class, ['record' => $agreement->id])
        ->callAction('terminate')
        ->assertNotified((string) __('operator_console.producer_agreement.notifications.terminated'));

    expect(ProducerAgreement::findOrFail($agreement->id)->status)->toBe(ProducerAgreementStatus::Terminated)
        // No cascade: the Producer is left UNCHANGED.
        ->and(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Active);

    $event = DomainEvent::query()->where('name', 'ProducerAgreementTerminated')->sole();

    expect($event->module)->toBe('parties')
        ->and($event->entity_type)->toBe('ProducerAgreement')
        ->and($event->entity_id)->toBe((string) $agreement->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($operator->id);
});

it('surfaces an out-of-state activate (a non-draft agreement) as a danger notification, changing nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // An already-active agreement: activate requires `draft`, so the domain rejects the out-of-state call. The
    // console surfaces it as a danger notification; it never pre-checks the from-state (design D5).
    $agreement = ProducerAgreement::factory()->create(['status' => ProducerAgreementStatus::Active]);

    Livewire::test(ViewProducerAgreement::class, ['record' => $agreement->id])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.producer_agreement.notifications.action_failed'));

    // Unchanged: still active, and the rejected attempt recorded NO event (its transaction rolled back).
    expect(ProducerAgreement::findOrFail($agreement->id)->status)->toBe(ProducerAgreementStatus::Active)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('surfaces a cross-shape activation conflict (a per-Club activation while a Producer-wide agreement is active) as a danger notification, changing nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // BR-K-Agreement-1 clause 2 (cross-shape mutual exclusion): a Producer with an active Producer-wide agreement.
    // Activating a draft per-Club agreement for the SAME Producer is rejected by the domain with
    // ProducerAgreementScopeConflict (a RuntimeException) — the console catches it by base type and surfaces the
    // `action_failed` danger notification, exactly like an out-of-state transition (design D5); it never pre-checks
    // the rule itself.
    $producer = Producer::factory()->create();
    $club = Club::factory()->create(['producer_id' => $producer->id]);
    ProducerAgreement::factory()->create([
        'producer_id' => $producer->id,
        'club_id' => null,
        'status' => ProducerAgreementStatus::Active,
    ]);
    $clubDraft = ProducerAgreement::factory()->create([
        'producer_id' => $producer->id,
        'club_id' => $club->id,
        'status' => ProducerAgreementStatus::Draft,
    ]);

    Livewire::test(ViewProducerAgreement::class, ['record' => $clubDraft->id])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.producer_agreement.notifications.action_failed'));

    // Unchanged: the per-Club draft stays draft and the rejected attempt recorded NO event (its transaction rolled
    // back) — only the factory-seeded active exists, and the factory records nothing.
    expect(ProducerAgreement::findOrFail($clubDraft->id)->status)->toBe(ProducerAgreementStatus::Draft)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('surfaces an out-of-state terminate (a non-active agreement) as a danger notification, changing nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A draft agreement: terminate requires `active`, so the domain rejects the out-of-state call.
    $agreement = ProducerAgreement::factory()->create(['status' => ProducerAgreementStatus::Draft]);

    Livewire::test(ViewProducerAgreement::class, ['record' => $agreement->id])
        ->callAction('terminate')
        ->assertNotified((string) __('operator_console.producer_agreement.notifications.action_failed'));

    expect(ProducerAgreement::findOrFail($agreement->id)->status)->toBe(ProducerAgreementStatus::Draft)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('exposes only the two status verbs activate + terminate (each form-less, no confirmation affordance) and none of the catalog governance verbs, nor — the defining scope guard — a supersede action', function () {
    actingAs(Operator::factory()->create(), 'operator');
    $agreement = ProducerAgreement::factory()->create(['status' => ProducerAgreementStatus::Draft]);

    Livewire::test(ViewProducerAgreement::class, ['record' => $agreement->id])
        // The two agreement status verbs are present …
        ->assertActionExists('activate')
        ->assertActionExists('terminate')
        // … each form-less, carrying NO confirmation affordance — agreement lifecycle is single-operator, not a
        // Creator → Reviewer → Approver SoD transition (design D3) …
        ->assertActionExists('activate', fn (Action $action): bool => ! $action->isConfirmationRequired())
        ->assertActionExists('terminate', fn (Action $action): bool => ! $action->isConfirmationRequired())
        // … and — the defining scope guard of this slice — NO supersede verb: supersession is the inline
        // side-effect of activation (D8), never a standalone operator action …
        ->assertActionDoesNotExist('supersede')
        // … none of the catalog governance verbs leak in (this page is NOT OperatorConsoleViewRecord — design D1).
        ->assertActionDoesNotExist('submit')
        ->assertActionDoesNotExist('reject')
        ->assertActionDoesNotExist('reopen');
});
