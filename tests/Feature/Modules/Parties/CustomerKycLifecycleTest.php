<?php

use App\Modules\Parties\Actions\RecordKycRejected;
use App\Modules\Parties\Actions\RecordKycVerified;
use App\Modules\Parties\Actions\RequireKyc;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldStatus;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Events\CustomerHoldLifted;
use App\Modules\Parties\Events\CustomerHoldPlaced;
use App\Modules\Parties\Exceptions\IllegalKycTransition;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Hold;
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
 * Three invariants this slice must hold and this test pins (design L2/L7 + scope guards): KYC itself records NO
 * KYC domain event (the PRD § 15.1 names none — the kyc_status change is audit-only), the KYC FSM is SEPARATE
 * from the Customer status FSM (a KYC transition never moves `Customer.status` off its `pending` birth state),
 * and the `kyc` Hold COUPLING now holds — opening KYC (`→ pending`) auto-places a Customer-scope `kyc` Hold
 * recording `CustomerHoldPlaced`, clearing it (`→ verified`) auto-lifts that Hold recording `CustomerHoldLifted`
 * (the system lift path — the operator `LiftHold` is forbidden from a `kyc` Hold by the per-type discipline), and
 * `→ rejected` LEAVES the Hold in place (§ 9.1 — Compliance reviews case-by-case). The only events the KYC flow
 * records are therefore the coupled Hold events; KYC contributes none of its own. RefreshDatabase per the task
 * hint — each Action opens its OWN transaction, so the rejected-transition rollback is a savepoint under the
 * wrapper (the from-state guard throws a PHP exception before any write, so no DB-trigger aborts the outer
 * transaction — the verify-after-throw SELECT survives on PostgreSQL 17; cross-engine close in task 6.3).
 */
uses(RefreshDatabase::class);

it('requires KYC on an un-screened Customer: NULL → pending, raises kyc_required, auto-places the kyc Hold', function () {
    $customer = Customer::factory()->create();   // born status `pending`, kyc_status NULL (un-screened)
    expect($customer->kyc_status)->toBeNull();   // precondition — DEC-071 un-screened birth

    $returned = app(RequireKyc::class)->handle($customer->id);

    // The returned model and the persisted row both carry `pending` + the raised flag.
    expect($returned->kyc_status)->toBe(KycStatus::Pending)
        ->and($returned->kyc_required)->toBeTrue();

    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->kyc_status)->toBe(KycStatus::Pending)
        ->and($fresh->kyc_required)->toBeTrue();

    // The coupling (design L7): opening KYC auto-places exactly one active Customer-scope `kyc` Hold (reason null —
    // the type IS the reason, design L5) in the same transaction; the blocking effect is the Hold's, not the column's.
    // `sole()` asserts exactly one such Hold exists (and returns it non-null).
    $hold = Hold::query()
        ->where('scope_type', HoldScope::Customer->value)
        ->where('scope_id', $customer->id)
        ->where('hold_type', HoldType::Kyc->value)
        ->sole();
    expect($hold->status)->toBe(HoldStatus::Active)
        ->and($hold->reason)->toBeNull();

    // KYC itself records NO KYC event (design L3); the only domain event is the coupled CustomerHoldPlaced. The
    // Customer status FSM is untouched (separate FSM) — placing a Hold performs no status transition.
    expect(DomainEvent::query()->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', CustomerHoldPlaced::NAME)->count())->toBe(1)
        ->and($fresh->status)->toBe(CustomerStatus::Pending);
});

it('requires KYC from the explicit not_required state: not_required → pending', function () {
    $customer = Customer::factory()->create(['kyc_status' => KycStatus::NotRequired]);

    $returned = app(RequireKyc::class)->handle($customer->id);

    expect($returned->kyc_status)->toBe(KycStatus::Pending)
        ->and($returned->kyc_required)->toBeTrue()
        ->and(Customer::findOrFail($customer->id)->kyc_status)->toBe(KycStatus::Pending);
});

it('records KYC verified: pending → verified (a cleared state), no event when no kyc Hold exists, status untouched', function () {
    // The factory lands `pending` directly (bypassing RequireKyc), so NO `kyc` Hold exists to lift: the system
    // auto-lift in RecordKycVerified is a no-op here, recording nothing. This pins the FSM transition in isolation
    // (the require→verify coupling — place then lift — is pinned by the dedicated coupling test below).
    $customer = Customer::factory()->create(['kyc_status' => KycStatus::Pending]);
    $baseline = DomainEvent::query()->count();

    $returned = app(RecordKycVerified::class)->handle($customer->id);

    // `verified` is a cleared state (the clears() truth table is pinned in ComplianceEnumsTest, task 1.1).
    expect($returned->kyc_status)->toBe(KycStatus::Verified)
        ->and(Customer::findOrFail($customer->id)->kyc_status)->toBe(KycStatus::Verified);

    // No active `kyc` Hold to lift → no CustomerHoldLifted (and KYC itself is event-silent); status untouched.
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

it('drives require → verify auto-placing then auto-lifting the kyc Hold, without moving the Customer status', function () {
    // The coupling end-to-end (design L7; party-registry MODIFIED Customer KYC Lifecycle): require auto-places the
    // `kyc` Hold, verify auto-lifts it. The kyc_status FSM walks `NULL → pending → verified`; the Customer status FSM
    // never moves (the Hold→`suspended` coupling is deferred). KYC contributes no event of its own — the only events
    // are the coupled CustomerHoldPlaced (require) + CustomerHoldLifted (verify).
    $customer = Customer::factory()->create();   // NULL kyc, born status `pending`

    app(RequireKyc::class)->handle($customer->id);

    // After require: exactly one active `kyc` Hold + exactly one CustomerHoldPlaced (KYC itself records nothing).
    $afterRequire = Hold::query()
        ->where('scope_type', HoldScope::Customer->value)
        ->where('scope_id', $customer->id)
        ->where('hold_type', HoldType::Kyc->value)
        ->sole();
    expect($afterRequire->status)->toBe(HoldStatus::Active);
    expect(DomainEvent::query()->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', CustomerHoldPlaced::NAME)->count())->toBe(1);

    app(RecordKycVerified::class)->handle($customer->id);

    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->kyc_status)->toBe(KycStatus::Verified)
        ->and($fresh->status)->toBe(CustomerStatus::Pending);   // status FSM untouched by KYC

    // After verify: the same Hold is now `lifted` (the system lift path — the operator LiftHold is forbidden a `kyc`
    // Hold), and a single CustomerHoldLifted joins the placement — exactly two events, both coupled Hold events.
    expect(Hold::findOrFail($afterRequire->id)->status)->toBe(HoldStatus::Lifted)
        ->and(DomainEvent::query()->count())->toBe(2)
        ->and(DomainEvent::query()->where('name', CustomerHoldLifted::NAME)->count())->toBe(1);
});

it('drives require → reject leaving the kyc Hold in place, recording no lift event', function () {
    // § 9.1 — a rejection LEAVES the `kyc` Hold active (Compliance reviews case-by-case; no automatic onward
    // transition). RecordKycRejected is UNCHANGED by this change: it touches no Hold, so the Hold the require placed
    // stays `active` and no CustomerHoldLifted is recorded — only the placement event exists.
    $customer = Customer::factory()->create();   // NULL kyc, born status `pending`

    app(RequireKyc::class)->handle($customer->id);
    app(RecordKycRejected::class)->handle($customer->id);

    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->kyc_status)->toBe(KycStatus::Rejected)
        ->and($fresh->status)->toBe(CustomerStatus::Pending);   // status FSM untouched

    // The `kyc` Hold stays active; zero lift events; the only event is the placement from require.
    $hold = Hold::query()
        ->where('scope_type', HoldScope::Customer->value)
        ->where('scope_id', $customer->id)
        ->where('hold_type', HoldType::Kyc->value)
        ->sole();
    expect($hold->status)->toBe(HoldStatus::Active)
        ->and(DomainEvent::query()->where('name', CustomerHoldLifted::NAME)->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', CustomerHoldPlaced::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->count())->toBe(1);
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
