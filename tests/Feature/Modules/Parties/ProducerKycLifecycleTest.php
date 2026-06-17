<?php

use App\Modules\Parties\Actions\RecordProducerKycRejected;
use App\Modules\Parties\Actions\RecordProducerKycVerified;
use App\Modules\Parties\Actions\RequireKyc;
use App\Modules\Parties\Actions\RequireProducerKyc;
use App\Modules\Parties\Actions\WaiveProducerKyc;
use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Enums\ProducerStatus;
use App\Modules\Parties\Exceptions\IllegalKycTransition;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Producer;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the Producer KYC lifecycle (parties-compliance, design L2/L3; party-registry — Requirement: Producer KYC
 * Lifecycle). Producer KYC is the provenance-KYC four-state domain `not_required → pending → verified | rejected`
 * held in the additive nullable `kyc_status` column (DEC-071 — a NULL is a Producer never touched by KYC). It
 * shares the {@see KycStatus} enum and the {@see IllegalKycTransition} vocabulary with the Customer side but is a
 * DISTINCT, Producer-level FSM (§ 4.4). {@see RequireProducerKyc} opens it (`not_required`/NULL → `pending`);
 * {@see RecordProducerKycVerified} / {@see RecordProducerKycRejected} resolve `pending → verified | rejected`; and
 * the Producer-only {@see WaiveProducerKyc} is the operator "deselect" (any outstanding state → `not_required`,
 * which clears the activation gate exactly as `verified` — ADR 2026-06-17). Each Action is the SOLE writer of
 * `kyc_status`, from-state guarded against a `lockForUpdate` re-read inside its own `DB::transaction`.
 *
 * Two invariants this slice must hold and this test pins (design L3 + scope guards): Producer KYC records NO domain
 * event (the PRD § 15.1/§ 15.4 names none — the cleared semantics ride `ProducerActivated` at activation, task 5.1),
 * and the Producer KYC FSM is SEPARATE from the Producer status FSM (a KYC transition never moves `Producer.status`
 * off its `draft` birth state) AND distinct from any Customer KYC state. RefreshDatabase per the task hint — each
 * Action opens its OWN transaction, so a rejected-transition rollback is a savepoint under the wrapper (the from-
 * state guard throws before any write, so the verify-after-throw SELECT survives on PostgreSQL 17; close in 6.3).
 */
uses(RefreshDatabase::class);

it('requires Producer KYC on a never-screened Producer: NULL → pending, records no event, status stays draft', function () {
    $producer = Producer::factory()->create();   // born status `draft`, kyc_status NULL (never screened)
    expect($producer->kyc_status)->toBeNull();    // precondition — DEC-071 un-screened birth
    $baseline = DomainEvent::query()->count();

    $returned = app(RequireProducerKyc::class)->handle($producer->id);

    expect($returned->kyc_status)->toBe(KycStatus::Pending)
        ->and(Producer::findOrFail($producer->id)->kyc_status)->toBe(KycStatus::Pending);

    // Producer KYC records NO domain event (design L3) and the Producer status FSM is untouched (separate FSM).
    expect(DomainEvent::query()->count())->toBe($baseline)
        ->and(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Draft);
});

it('requires Producer KYC from the explicit not_required state: not_required → pending', function () {
    $producer = Producer::factory()->create(['kyc_status' => KycStatus::NotRequired]);

    $returned = app(RequireProducerKyc::class)->handle($producer->id);

    expect($returned->kyc_status)->toBe(KycStatus::Pending)
        ->and(Producer::findOrFail($producer->id)->kyc_status)->toBe(KycStatus::Pending);
});

it('records Producer KYC verified: pending → verified (a cleared state), no event, status stays draft', function () {
    $producer = Producer::factory()->create(['kyc_status' => KycStatus::Pending]);
    $baseline = DomainEvent::query()->count();

    $returned = app(RecordProducerKycVerified::class)->handle($producer->id);

    // `verified` is a cleared state (the clears() truth table is pinned in ComplianceEnumsTest, task 1.1).
    expect($returned->kyc_status)->toBe(KycStatus::Verified)
        ->and(Producer::findOrFail($producer->id)->kyc_status)->toBe(KycStatus::Verified);

    expect(DomainEvent::query()->count())->toBe($baseline)
        ->and(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Draft);
});

it('records Producer KYC rejected: pending → rejected (a blocking state), no event, status stays draft', function () {
    $producer = Producer::factory()->create(['kyc_status' => KycStatus::Pending]);
    $baseline = DomainEvent::query()->count();

    $returned = app(RecordProducerKycRejected::class)->handle($producer->id);

    expect($returned->kyc_status)->toBe(KycStatus::Rejected)
        ->and(Producer::findOrFail($producer->id)->kyc_status)->toBe(KycStatus::Rejected);

    expect(DomainEvent::query()->count())->toBe($baseline)
        ->and(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Draft);
});

it('waives Producer KYC to not_required from any outstanding state, records no event, status stays draft', function (?KycStatus $from) {
    $producer = Producer::factory()->create(['kyc_status' => $from]);
    $baseline = DomainEvent::query()->count();

    $returned = app(WaiveProducerKyc::class)->handle($producer->id);

    // The operator deselect lands `not_required` (a cleared state — the Producer activates as if verified).
    expect($returned->kyc_status)->toBe(KycStatus::NotRequired)
        ->and(Producer::findOrFail($producer->id)->kyc_status)->toBe(KycStatus::NotRequired);

    expect(DomainEvent::query()->count())->toBe($baseline)
        ->and(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Draft);
})->with([
    'never-screened (NULL)' => [null],
    'pending' => [KycStatus::Pending],
    'rejected' => [KycStatus::Rejected],
    'verified (re-deselect)' => [KycStatus::Verified],
]);

it('rejects RecordProducerKycVerified from any non-pending state, leaving the row and event log unchanged', function (?KycStatus $from) {
    $producer = Producer::factory()->create(['kyc_status' => $from]);
    $baseline = DomainEvent::query()->count();

    expect(fn () => app(RecordProducerKycVerified::class)->handle($producer->id))
        ->toThrow(IllegalKycTransition::class);

    // The from-state guard fires before any write and the transaction rolls back: kyc_status, the Producer status,
    // and the event log are all unchanged.
    $fresh = Producer::findOrFail($producer->id);
    expect($fresh->kyc_status)->toBe($from)
        ->and($fresh->status)->toBe(ProducerStatus::Draft)
        ->and(DomainEvent::query()->count())->toBe($baseline);
})->with([
    'never-screened (NULL)' => [null],
    'not_required' => [KycStatus::NotRequired],
    'verified' => [KycStatus::Verified],
    'rejected' => [KycStatus::Rejected],
]);

it('rejects RecordProducerKycRejected from any non-pending state, leaving the row and event log unchanged', function (?KycStatus $from) {
    $producer = Producer::factory()->create(['kyc_status' => $from]);
    $baseline = DomainEvent::query()->count();

    expect(fn () => app(RecordProducerKycRejected::class)->handle($producer->id))
        ->toThrow(IllegalKycTransition::class);

    $fresh = Producer::findOrFail($producer->id);
    expect($fresh->kyc_status)->toBe($from)
        ->and($fresh->status)->toBe(ProducerStatus::Draft)
        ->and(DomainEvent::query()->count())->toBe($baseline);
})->with([
    'never-screened (NULL)' => [null],
    'not_required' => [KycStatus::NotRequired],
    'verified' => [KycStatus::Verified],
    'rejected' => [KycStatus::Rejected],
]);

it('rejects RequireProducerKyc once KYC has already been opened, leaving the row and event log unchanged', function (KycStatus $from) {
    $producer = Producer::factory()->create(['kyc_status' => $from]);
    $baseline = DomainEvent::query()->count();

    expect(fn () => app(RequireProducerKyc::class)->handle($producer->id))
        ->toThrow(IllegalKycTransition::class);

    $fresh = Producer::findOrFail($producer->id);
    expect($fresh->kyc_status)->toBe($from)
        ->and($fresh->status)->toBe(ProducerStatus::Draft)
        ->and(DomainEvent::query()->count())->toBe($baseline);
})->with([
    'pending' => [KycStatus::Pending],
    'verified' => [KycStatus::Verified],
    'rejected' => [KycStatus::Rejected],
]);

it('rejects WaiveProducerKyc when KYC is already not_required (nothing to deselect)', function () {
    $producer = Producer::factory()->create(['kyc_status' => KycStatus::NotRequired]);
    $baseline = DomainEvent::query()->count();

    expect(fn () => app(WaiveProducerKyc::class)->handle($producer->id))
        ->toThrow(IllegalKycTransition::class);

    $fresh = Producer::findOrFail($producer->id);
    expect($fresh->kyc_status)->toBe(KycStatus::NotRequired)
        ->and($fresh->status)->toBe(ProducerStatus::Draft)
        ->and(DomainEvent::query()->count())->toBe($baseline);
});

it('renders the never-screened (NULL) from-state with the unset sentinel, not a TypeError', function () {
    // verify/reject reuse the widened `cannotVerify`/`cannotReject(?KycStatus)` factories (DEC-071 — kyc_status is
    // nullable): a verify on a never-screened Producer is a clean IllegalKycTransition carrying the `unset` token.
    $producer = Producer::factory()->create();   // NULL kyc

    expect(fn () => app(RecordProducerKycVerified::class)->handle($producer->id))
        ->toThrow(IllegalKycTransition::class, 'unset');
});

it('keeps Producer KYC distinct from Customer KYC — each is a separate, independent field', function () {
    // A Producer and a Customer, both born un-screened (NULL kyc). Driving the Producer KYC FSM must leave the
    // Customer's KYC untouched, and vice versa — the two are Producer-level / Customer-level fields on separate
    // tables (§ 4.4), sharing only the enum vocabulary.
    $producer = Producer::factory()->create();
    $customer = Customer::factory()->create();

    app(RequireProducerKyc::class)->handle($producer->id);
    app(RecordProducerKycVerified::class)->handle($producer->id);

    expect(Producer::findOrFail($producer->id)->kyc_status)->toBe(KycStatus::Verified)
        ->and(Customer::findOrFail($customer->id)->kyc_status)->toBeNull();   // the Producer transition touched no Customer

    // The converse: opening Customer KYC leaves the Producer's verified state intact.
    app(RequireKyc::class)->handle($customer->id);

    expect(Customer::findOrFail($customer->id)->kyc_status)->toBe(KycStatus::Pending)
        ->and(Producer::findOrFail($producer->id)->kyc_status)->toBe(KycStatus::Verified);
});
