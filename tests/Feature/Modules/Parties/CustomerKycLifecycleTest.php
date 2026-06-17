<?php

use App\Modules\Parties\Actions\RecordKycRejected;
use App\Modules\Parties\Actions\RecordKycVerified;
use App\Modules\Parties\Actions\RequireKyc;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Exceptions\IllegalKycTransition;
use App\Modules\Parties\Models\Customer;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the Customer KYC lifecycle (parties-compliance, design L2/L3; party-registry — Requirement: Customer
 * KYC Lifecycle). The KYC FSM is `not_required → pending → verified | rejected`, held in the additive nullable
 * `kyc_status` column (DEC-071 — a NULL is an un-screened Customer). {@see RequireKyc} opens it
 * (`not_required`/NULL → `pending`, raising `kyc_required`); {@see RecordKycVerified} / {@see RecordKycRejected}
 * resolve `pending → verified | rejected`. Each Action is the SOLE writer of `kyc_status`, from-state guarded
 * against a `lockForUpdate` re-read inside its own `DB::transaction`.
 *
 * Three invariants this slice must hold and this test pins (design L3 + scope guards): KYC records NO domain
 * event (the PRD § 15.1 names none — audit-only), this slice places NO `kyc` Hold (the Hold registry is the
 * deferred `parties-holds` change), and the KYC FSM is SEPARATE from the Customer status FSM (a KYC transition
 * never moves `Customer.status` off its `pending` birth state). RefreshDatabase per the task hint — each Action
 * opens its OWN transaction, so the rejected-transition rollback is a savepoint under the wrapper (the from-state
 * guard throws a PHP exception before any write, so no DB-trigger aborts the outer transaction — the verify-
 * after-throw SELECT survives on PostgreSQL 17; cross-engine close in task 6.3).
 */
uses(RefreshDatabase::class);

it('requires KYC on an un-screened Customer: NULL → pending, raises kyc_required, records no event', function () {
    $customer = Customer::factory()->create();   // born status `pending`, kyc_status NULL (un-screened)
    expect($customer->kyc_status)->toBeNull();   // precondition — DEC-071 un-screened birth
    $baseline = DomainEvent::query()->count();

    $returned = app(RequireKyc::class)->handle($customer->id);

    // The returned model and the persisted row both carry `pending` + the raised flag.
    expect($returned->kyc_status)->toBe(KycStatus::Pending)
        ->and($returned->kyc_required)->toBeTrue();

    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->kyc_status)->toBe(KycStatus::Pending)
        ->and($fresh->kyc_required)->toBeTrue();

    // KYC records NO domain event (design L3) and the Customer status FSM is untouched (separate FSM).
    expect(DomainEvent::query()->count())->toBe($baseline)
        ->and($fresh->status)->toBe(CustomerStatus::Pending);
});

it('requires KYC from the explicit not_required state: not_required → pending', function () {
    $customer = Customer::factory()->create(['kyc_status' => KycStatus::NotRequired]);

    $returned = app(RequireKyc::class)->handle($customer->id);

    expect($returned->kyc_status)->toBe(KycStatus::Pending)
        ->and($returned->kyc_required)->toBeTrue()
        ->and(Customer::findOrFail($customer->id)->kyc_status)->toBe(KycStatus::Pending);
});

it('records KYC verified: pending → verified (a cleared state), no event, status untouched', function () {
    $customer = Customer::factory()->create(['kyc_status' => KycStatus::Pending]);
    $baseline = DomainEvent::query()->count();

    $returned = app(RecordKycVerified::class)->handle($customer->id);

    // `verified` is a cleared state (the clears() truth table is pinned in ComplianceEnumsTest, task 1.1).
    expect($returned->kyc_status)->toBe(KycStatus::Verified)
        ->and(Customer::findOrFail($customer->id)->kyc_status)->toBe(KycStatus::Verified);

    expect(DomainEvent::query()->count())->toBe($baseline)
        ->and(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Pending);
});

it('records KYC rejected: pending → rejected (a blocking state), no event, no onward transition', function () {
    $customer = Customer::factory()->create(['kyc_status' => KycStatus::Pending]);
    $baseline = DomainEvent::query()->count();

    $returned = app(RecordKycRejected::class)->handle($customer->id);

    // `rejected` is a blocking state; the FSM performs no automatic onward transition (Compliance reviews it).
    // (The clears()=false truth is pinned in ComplianceEnumsTest, task 1.1.)
    expect($returned->kyc_status)->toBe(KycStatus::Rejected)
        ->and(Customer::findOrFail($customer->id)->kyc_status)->toBe(KycStatus::Rejected);

    expect(DomainEvent::query()->count())->toBe($baseline)
        ->and(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Pending);
});

it('drives require → verify without placing any kyc Hold or moving the Customer status', function () {
    $customer = Customer::factory()->create();   // NULL kyc, born status `pending`

    app(RequireKyc::class)->handle($customer->id);
    app(RecordKycVerified::class)->handle($customer->id);

    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->kyc_status)->toBe(KycStatus::Verified)
        ->and($fresh->status)->toBe(CustomerStatus::Pending);   // status FSM untouched by KYC

    // Scope guard: no `kyc` Hold is placed by this slice (the Hold registry is `parties-holds`); KYC emits no
    // domain event at all (design L3), so no `*Hold*` name — and indeed no event — can appear.
    expect(DomainEvent::query()->where('name', 'like', '%Hold%')->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('rejects RecordKycVerified from any non-pending state, leaving the row and event log unchanged', function (?KycStatus $from) {
    $customer = Customer::factory()->create(['kyc_status' => $from]);
    $baseline = DomainEvent::query()->count();

    expect(fn () => app(RecordKycVerified::class)->handle($customer->id))
        ->toThrow(IllegalKycTransition::class);

    // The from-state guard fires before any write and the transaction rolls back: kyc_status, the Customer
    // status, and the event log are all unchanged.
    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->kyc_status)->toBe($from)
        ->and($fresh->status)->toBe(CustomerStatus::Pending)
        ->and(DomainEvent::query()->count())->toBe($baseline);
})->with([
    'unset (NULL)' => [null],
    'not_required' => [KycStatus::NotRequired],
    'verified' => [KycStatus::Verified],
    'rejected' => [KycStatus::Rejected],
]);

it('rejects RecordKycRejected from any non-pending state, leaving the row and event log unchanged', function (?KycStatus $from) {
    $customer = Customer::factory()->create(['kyc_status' => $from]);
    $baseline = DomainEvent::query()->count();

    expect(fn () => app(RecordKycRejected::class)->handle($customer->id))
        ->toThrow(IllegalKycTransition::class);

    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->kyc_status)->toBe($from)
        ->and($fresh->status)->toBe(CustomerStatus::Pending)
        ->and(DomainEvent::query()->count())->toBe($baseline);
})->with([
    'unset (NULL)' => [null],
    'not_required' => [KycStatus::NotRequired],
    'verified' => [KycStatus::Verified],
    'rejected' => [KycStatus::Rejected],
]);

it('rejects RequireKyc once KYC has already been opened, leaving the row and event log unchanged', function (KycStatus $from) {
    $customer = Customer::factory()->create(['kyc_status' => $from]);
    $baseline = DomainEvent::query()->count();

    expect(fn () => app(RequireKyc::class)->handle($customer->id))
        ->toThrow(IllegalKycTransition::class);

    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->kyc_status)->toBe($from)
        ->and($fresh->status)->toBe(CustomerStatus::Pending)
        ->and(DomainEvent::query()->count())->toBe($baseline);
})->with([
    'pending' => [KycStatus::Pending],
    'verified' => [KycStatus::Verified],
    'rejected' => [KycStatus::Rejected],
]);

it('renders the un-screened (NULL) from-state with the unset sentinel, not a TypeError', function () {
    // The widened `cannotVerify(?KycStatus)` (DEC-071: kyc_status is nullable) turns a verify on an un-screened
    // Customer into a clean IllegalKycTransition carrying the `unset` token — never a TypeError on a NULL.
    $customer = Customer::factory()->create();   // NULL kyc

    expect(fn () => app(RecordKycVerified::class)->handle($customer->id))
        ->toThrow(IllegalKycTransition::class, 'unset');
});
