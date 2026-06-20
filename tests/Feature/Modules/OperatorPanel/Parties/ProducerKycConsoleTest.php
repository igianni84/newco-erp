<?php

// Task 4.1 / 4.2 (operator-console-parties-producer; design D1/D3/D4/D5; ADR 2026-06-20) — the Producer console's
// KYC surface. These pin the four KYC verbs (requireKyc `not_required`/NULL → `pending`, waiveKyc any outstanding
// state → `not_required`, verifyKyc / rejectKyc `pending → verified`/`rejected`) the view page APPENDS to the same
// SurfacesDomainActions-built header-action array as the §3 status verbs — NOT the catalog OperatorConsoleViewRecord
// base (design D1). Each verb routes through a Parties domain action by the producer id (design D4) and NEVER writes
// `kyc_status` itself (the no-Eloquent-write rule); the console SURFACES the domain's decision — an out-of-state KYC
// call becomes the `action_failed` danger notification (design D5).
//
// Producer KYC is a SEPARATE FSM from the Producer status FSM and is AUDIT-ONLY (§ 4.4): each verb moves `kyc_status`
// while leaving `status` untouched, records NO domain event (the PRD § 15.1/§ 15.4 names none — the cleared semantics
// ride `ProducerActivated` at activation) and places NO Hold (the `kyc` Hold coupling is the deferred `parties-holds`
// change — scope guard). The KYC FSM gates activation: a `pending`/`rejected` `kyc_status` blocks ActivateProducer
// (design L5 / BR-K-Producer-2) — the end-to-end gate test drives pending → blocked → verify → activate succeeds.
//
// DatabaseMigrations (mirroring ProducerLifecycleConsoleTest + the catalog lifecycle console tests): each console
// action drives a real domain action that opens its OWN DB::transaction, so a rejected transition rolls back for real
// (RefreshDatabase would wrap every write in a never-committed outer transaction). The factories bypass the actions,
// so they record NO event — the only events are the ones the console actions record (here: none until activation).
// Parties enums/models (incl. Hold) are imported freely: the {Models, Actions} import-boundary carve-out governs
// OperatorPanel PRODUCTION code, not tests.

use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerResource\Pages\ViewProducer;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Enums\ProducerStatus;
use App\Modules\Parties\Models\Hold;
use App\Modules\Parties\Models\Producer;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

it('requires Producer KYC through the console, moving kyc_status to pending without an event, a Hold, or a status change', function (?KycStatus $from) {
    actingAs(Operator::factory()->create(), 'operator');

    // requireKyc is the only verb that OPENS the KYC FSM (→ pending); it is reachable from never-screened (NULL)
    // or the explicit `not_required` and leaves the status FSM on its `draft` birth state.
    $producer = Producer::factory()->create(['status' => ProducerStatus::Draft, 'kyc_status' => $from]);
    $eventBaseline = DomainEvent::query()->count();

    Livewire::test(ViewProducer::class, ['record' => $producer->id])
        ->callAction('requireKyc')
        ->assertNotified((string) __('operator_console.producer.notifications.kyc_required'));

    $fresh = Producer::findOrFail($producer->id);

    // The KYC FSM advanced to `pending`; the status FSM is untouched (a separate FSM — § 4.4) …
    expect($fresh->kyc_status)->toBe(KycStatus::Pending)
        ->and($fresh->status)->toBe(ProducerStatus::Draft)
        // … and Producer KYC is audit-only: it records NO domain event (design L3 / § 15.4) and places NO Hold.
        ->and(DomainEvent::query()->count())->toBe($eventBaseline)
        ->and(Hold::query()->count())->toBe(0);
})->with([
    'never-screened (NULL)' => [null],
    'not_required' => [KycStatus::NotRequired],
]);

it('waives Producer KYC through the console from any outstanding state, moving kyc_status to not_required without an event, a Hold, or a status change', function (KycStatus $from) {
    actingAs(Operator::factory()->create(), 'operator');

    $producer = Producer::factory()->create(['status' => ProducerStatus::Draft, 'kyc_status' => $from]);
    $eventBaseline = DomainEvent::query()->count();

    Livewire::test(ViewProducer::class, ['record' => $producer->id])
        ->callAction('waiveKyc')
        ->assertNotified((string) __('operator_console.producer.notifications.kyc_waived'));

    $fresh = Producer::findOrFail($producer->id);

    // The operator "deselect" lands `not_required` (a cleared state — the Producer then activates as if verified).
    expect($fresh->kyc_status)->toBe(KycStatus::NotRequired)
        ->and($fresh->status)->toBe(ProducerStatus::Draft)
        ->and(DomainEvent::query()->count())->toBe($eventBaseline)
        ->and(Hold::query()->count())->toBe(0);
})->with([
    'pending' => [KycStatus::Pending],
    'rejected' => [KycStatus::Rejected],
    'verified (re-deselect)' => [KycStatus::Verified],
]);

it('verifies Producer KYC through the console: pending → verified without an event, a Hold, or a status change', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $producer = Producer::factory()->create(['status' => ProducerStatus::Draft, 'kyc_status' => KycStatus::Pending]);
    $eventBaseline = DomainEvent::query()->count();

    Livewire::test(ViewProducer::class, ['record' => $producer->id])
        ->callAction('verifyKyc')
        ->assertNotified((string) __('operator_console.producer.notifications.kyc_verified'));

    $fresh = Producer::findOrFail($producer->id);

    // `verified` is a cleared state (clears the activation gate); the move is audit-only and never touches `status`.
    expect($fresh->kyc_status)->toBe(KycStatus::Verified)
        ->and($fresh->status)->toBe(ProducerStatus::Draft)
        ->and(DomainEvent::query()->count())->toBe($eventBaseline)
        ->and(Hold::query()->count())->toBe(0);
});

it('rejects Producer KYC through the console: pending → rejected without an event, a Hold, or a status change', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $producer = Producer::factory()->create(['status' => ProducerStatus::Draft, 'kyc_status' => KycStatus::Pending]);
    $eventBaseline = DomainEvent::query()->count();

    Livewire::test(ViewProducer::class, ['record' => $producer->id])
        ->callAction('rejectKyc')
        ->assertNotified((string) __('operator_console.producer.notifications.kyc_rejected'));

    $fresh = Producer::findOrFail($producer->id);

    // `rejected` is a blocking state; the move is audit-only and never touches `status` (a later waive can clear it).
    expect($fresh->kyc_status)->toBe(KycStatus::Rejected)
        ->and($fresh->status)->toBe(ProducerStatus::Draft)
        ->and(DomainEvent::query()->count())->toBe($eventBaseline)
        ->and(Hold::query()->count())->toBe(0);
});

it('surfaces an illegal KYC transition as a danger notification, changing nothing', function (string $verb, ?KycStatus $from) {
    actingAs(Operator::factory()->create(), 'operator');

    // Each verb guards its legal from-state(s) in the domain action; an out-of-state call throws
    // IllegalKycTransition (a RuntimeException), which the trait catches by base type and renders as the
    // action_failed danger title (design D5). The console never pre-checks the from-state.
    $producer = Producer::factory()->create(['status' => ProducerStatus::Draft, 'kyc_status' => $from]);

    Livewire::test(ViewProducer::class, ['record' => $producer->id])
        ->callAction($verb)
        ->assertNotified((string) __('operator_console.producer.notifications.action_failed'));

    // The rejecting action's transaction rolled back: kyc_status, status, and the (empty) event log are unchanged.
    $fresh = Producer::findOrFail($producer->id);
    expect($fresh->kyc_status)->toBe($from)
        ->and($fresh->status)->toBe(ProducerStatus::Draft)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'verify from not_required' => ['verifyKyc', KycStatus::NotRequired],
    'verify from verified' => ['verifyKyc', KycStatus::Verified],
    'verify from never-screened (NULL)' => ['verifyKyc', null],
    'reject from rejected' => ['rejectKyc', KycStatus::Rejected],
    'waive from not_required (nothing to deselect)' => ['waiveKyc', KycStatus::NotRequired],
]);

it('gates activation on cleared KYC end-to-end — pending blocks activate, verify clears it, then activate succeeds', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A `draft` Producer with KYC `pending` — a BLOCKING state (KycStatus::clears() is false), so the activation
    // gate (ActivateProducer, design L5 / BR-K-Producer-2) refuses until KYC clears.
    $producer = Producer::factory()->create(['status' => ProducerStatus::Draft, 'kyc_status' => KycStatus::Pending]);

    // 1) activate is blocked by the KYC gate → action_failed; the Producer stays `draft`, no ProducerActivated.
    Livewire::test(ViewProducer::class, ['record' => $producer->id])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.producer.notifications.action_failed'));

    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Draft)
        ->and(DomainEvent::query()->where('name', 'ProducerActivated')->count())->toBe(0);

    // 2) verify clears the gate (pending → verified, a cleared state) — audit-only, still no event.
    Livewire::test(ViewProducer::class, ['record' => $producer->id])
        ->callAction('verifyKyc')
        ->assertNotified((string) __('operator_console.producer.notifications.kyc_verified'));

    expect(Producer::findOrFail($producer->id)->kyc_status)->toBe(KycStatus::Verified)
        ->and(DomainEvent::query()->where('name', 'ProducerActivated')->count())->toBe(0);

    // 3) activate now succeeds: draft → active + exactly one ProducerActivated.
    Livewire::test(ViewProducer::class, ['record' => $producer->id])
        ->callAction('activate')
        ->assertNotified((string) __('operator_console.producer.notifications.activated'));

    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Active)
        ->and(DomainEvent::query()->where('name', 'ProducerActivated')->count())->toBe(1);
});
