<?php

use App\Modules\Parties\Actions\LiftHold;
use App\Modules\Parties\Actions\PlaceHold;
use App\Modules\Parties\Actions\RecordKycVerified;
use App\Modules\Parties\Actions\RequireKyc;
use App\Modules\Parties\Contracts\PartyComplianceStatusReader;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldStatus;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Enums\SanctionsStatus;
use App\Modules\Parties\Events\CustomerHoldLifted;
use App\Modules\Parties\Events\CustomerHoldPlaced;
use App\Modules\Parties\Exceptions\IllegalHoldLift;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Hold;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The full-chain integration proof + cross-engine close for the Parties HOLD slice (parties-holds task 6.3;
 * design L1/L2/L3/L6/L7; party-registry — the ADDED Requirements (Hold Registry, Hold Lifecycle and Lift
 * Discipline, Hold Events, Hold and Sanctions Read-API) and both MODIFIED ones (Customer KYC Lifecycle, "Birth
 * States Recorded — Lifecycle Transitions Deferred")). Where each sibling pins ONE facet in isolation
 * ({@see HoldLifecycleTest} place/lift, {@see HoldRegistryTest} the registry + scope guard,
 * {@see ComplianceReadApiTest} the read-API tuple + cascade, {@see CustomerKycLifecycleTest} the KYC↔Hold
 * coupling), this one drives the WHOLE slice through its real Actions in one chain and asserts the emergent
 * contract:
 *   - RequireKyc auto-places a Customer-scope `kyc` Hold (the coupling) → PlaceHold an `admin` Hold → the read-API
 *     reports the scope NOT clear and the two Customer-scope Holds CASCADE to every Profile (BR-K-Hold-3);
 *   - the lift discipline holds end-to-end (ADR 2026-06-18-hold-lift-discipline-per-type): the operator path
 *     REJECTS lifting the auto-managed `kyc` Hold (no state change, no event), the `admin` Hold lifts cleanly, and
 *     RecordKycVerified auto-lifts the `kyc` Hold via the system path (which the operator could not);
 *   - the read-API `isClear()` flips false → true across the chain — clear ONLY once sanctions is `passed` AND no
 *     active Hold remains — and the cleared state cascades back to the Profiles;
 *   - the whole chain records EXACTLY two `CustomerHoldPlaced` + two `CustomerHoldLifted` and NOTHING else: KYC is
 *     event-silent (design L3 — § 15.1 names no KYC event; no event NAME carries "Kyc"), sanctions is factory-set
 *     (no screening event), and the demand side stays inert (no CustomerActivated / AccountActivated /
 *     ProfileActivated / ProfileApproved / OriginatingClubLocked / CustomerSegmentChanged), with no scope-entity
 *     STATUS transition (the Hold→`suspended` coupling is deferred — § 10.1 / AC-K-FSM-9).
 *
 * Creation uses the factories deliberately (not the CreateCustomer/CreateProfile spine Actions — already proven in
 * {@see SpineCreationChainTest}): CreateCustomer co-provisions an Account and records a creation event, which would
 * muddy this slice's whole point — that the Hold chain records ONLY the two Hold events. The factories record no
 * event, so every event observed here is one a Hold Action wrote. Sanctions is factory-set `passed` so the standing
 * precondition for "clear" is fixed and the ACTIVE HOLDS are the only variable that flips `isClear()` (the read-API's
 * sanctions dimension is pinned in {@see ComplianceReadApiTest}).
 *
 * This is the cross-engine gate: this file and the WHOLE Parties suite are verified green on SQLite AND on a local
 * PostgreSQL 17 before the change is declared complete (knowledge/testing/rules.md) — including the polymorphic-scope
 * index and the value-set CHECKs on `parties_holds`. Portability: events are asserted BY NAME + counts, never a
 * byte-compare of stored jsonb (PG reorders keys — trap 3); enum columns round-trip through the model casts; active
 * Holds are read through the read-API (HoldType enums), never raw scalars. RefreshDatabase per the directory
 * convention — each Action opens its OWN DB::transaction, so the recorder's `transactionLevel() === 0` guard is
 * satisfied by the savepoint under the wrapper, and the rejected `kyc` operator-lift throws its app-level guard
 * BEFORE any DML, so the verify-after-throw query survives on PG (no constraint-abort — trap 5 does not apply).
 */
uses(RefreshDatabase::class);

it('drives the whole Hold chain end-to-end — coupling, cascade, lift discipline, auto-lift, and the read-API clearing', function () {
    // A sanctions-passed Customer with two Profiles, via factories (no creation events). Sanctions `passed` is the
    // standing precondition for clear; the ACTIVE HOLDS are the only variable that flips isClear() below.
    $customer = Customer::factory()->create(['sanctions_status' => SanctionsStatus::Passed]);
    $profileA = Profile::factory()->create(['customer_id' => $customer->id]);
    $profileB = Profile::factory()->create(['customer_id' => $customer->id]);

    $reader = app(PartyComplianceStatusReader::class);

    // 1. RequireKyc: kyc_status NULL → pending AND auto-places a Customer-scope `kyc` Hold (the coupling — L7).
    app(RequireKyc::class)->handle($customer->id);
    expect(Customer::findOrFail($customer->id)->kyc_status)->toBe(KycStatus::Pending)
        ->and(DomainEvent::query()->where('name', CustomerHoldPlaced::NAME)->count())->toBe(1);
    $kycHold = Hold::query()
        ->where('scope_type', HoldScope::Customer->value)
        ->where('scope_id', $customer->id)
        ->where('hold_type', HoldType::Kyc->value)
        ->where('status', HoldStatus::Active->value)
        ->sole();

    // 2. PlaceHold an `admin` Hold (the manual operator path) — a second concurrent active Hold on the same scope.
    $adminHold = app(PlaceHold::class)->handle(HoldType::Admin, HoldScope::Customer, $customer->id, 'manual review');

    // The read-API reports NOT clear (two active Holds), and the Customer-scope Holds CASCADE to both Profiles.
    $customerStatus = $reader->forCustomer($customer->id);
    expect($customerStatus->isClear())->toBeFalse()
        ->and($customerStatus->activeHoldTypes)->toContain(HoldType::Kyc)->toContain(HoldType::Admin)->toHaveCount(2);
    foreach ([$profileA, $profileB] as $profile) {
        $profileStatus = $reader->forProfile($profile->id);
        expect($profileStatus->isClear())->toBeFalse()
            ->and($profileStatus->activeHoldTypes)->toContain(HoldType::Kyc)->toContain(HoldType::Admin)->toHaveCount(2);
    }

    // 3. The operator path REJECTS lifting the auto-managed `kyc` Hold (ADR — kyc/payment are system-managed). The
    //    :type token ('kyc') is absent from the lang template, so the message-substring match proves interpolation.
    expect(fn () => app(LiftHold::class)->handle($kycHold->id, 'operator tried to lift kyc'))
        ->toThrow(IllegalHoldLift::class, HoldType::Kyc->value);
    expect(Hold::findOrFail($kycHold->id)->status)->toBe(HoldStatus::Active);   // no state change

    // 4. The `admin` Hold lifts cleanly (operator-liftable) → one CustomerHoldLifted; the `kyc` Hold remains active,
    //    so the scope is still not clear (any one Hold blocks independently — BR-K-Hold-1).
    app(LiftHold::class)->handle($adminHold->id, 'review cleared');
    $afterAdminLift = $reader->forCustomer($customer->id);
    expect(Hold::findOrFail($adminHold->id)->status)->toBe(HoldStatus::Lifted)
        ->and($afterAdminLift->activeHoldTypes)->toBe([HoldType::Kyc])
        ->and($afterAdminLift->isClear())->toBeFalse();

    // 5. RecordKycVerified: kyc_status pending → verified AND auto-lifts the Customer's active `kyc` Hold (the system
    //    path — what the operator could not do in step 3).
    app(RecordKycVerified::class)->handle($customer->id);
    expect(Customer::findOrFail($customer->id)->kyc_status)->toBe(KycStatus::Verified)
        ->and(Hold::findOrFail($kycHold->id)->status)->toBe(HoldStatus::Lifted);

    // 6. The read-API is now CLEAR — sanctions `passed` AND no active Hold — and the cleared state cascades to both
    //    Profiles (the cascade resolves at read against the now-empty active-Hold set).
    $final = $reader->forCustomer($customer->id);
    expect($final->activeHoldTypes)->toBe([])
        ->and($final->sanctionsStatus)->toBe(SanctionsStatus::Passed)
        ->and($final->isClear())->toBeTrue();
    foreach ([$profileA, $profileB] as $profile) {
        expect($reader->forProfile($profile->id)->isClear())->toBeTrue();
    }

    // SCOPE GUARD — the whole chain (place/lift + the KYC coupling, a real compliance transition) moves the KYC FSM
    // but NEVER the demand-side status FSM: the Customer is still `pending`, the Profiles still `applied`.
    expect(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Pending)
        ->and(Profile::findOrFail($profileA->id)->state)->toBe(ProfileState::Applied)
        ->and(Profile::findOrFail($profileB->id)->state)->toBe(ProfileState::Applied);
});

it('records exactly two CustomerHoldPlaced + two CustomerHoldLifted and nothing else across the chain', function () {
    // Re-drive the chain on a fresh DB (RefreshDatabase): require (kyc Hold placed) → place admin → lift admin →
    // verify (kyc Hold auto-lifted).
    $customer = Customer::factory()->create(['sanctions_status' => SanctionsStatus::Passed]);
    Profile::factory()->create(['customer_id' => $customer->id]);

    app(RequireKyc::class)->handle($customer->id);                                                 // +1 placed (kyc)
    $admin = app(PlaceHold::class)->handle(HoldType::Admin, HoldScope::Customer, $customer->id, 'review');  // +1 placed
    app(LiftHold::class)->handle($admin->id, 'cleared');                                            // +1 lifted (admin)
    app(RecordKycVerified::class)->handle($customer->id);                                          // +1 lifted (kyc auto)

    // EXACTLY two placed + two lifted, by NAME + count (trap 3 — never byte-compare PG jsonb).
    $expected = [
        CustomerHoldPlaced::NAME => 2,
        CustomerHoldLifted::NAME => 2,
    ];
    foreach ($expected as $name => $count) {
        expect(DomainEvent::query()->where('name', $name)->count())->toBe($count);
    }

    // The whole table is EXACTLY these two distinct names and four rows — no invented Hold event, no KYC event, no
    // sanctions event (factory-set), nothing extraneous (the closing integration test's event-SET assertion).
    expect(DomainEvent::query()->pluck('name')->unique()->values()->all())->toEqualCanonicalizing(array_keys($expected))
        ->and(DomainEvent::query()->whereNotIn('name', [CustomerHoldPlaced::NAME, CustomerHoldLifted::NAME])->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(4);

    // KYC itself is event-silent — no event NAME carries "Kyc" (the coupled events are CustomerHold*, not *Kyc*);
    // the only Hold events are the chain's four.
    expect(DomainEvent::query()->where('name', 'like', '%Kyc%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Hold%')->count())->toBe(4);

    // All four are module `parties`, entity_type `Hold`, resolved to the System actor (the ActorContext seam default
    // with no authenticated operator in the test).
    expect(DomainEvent::query()->where('module', 'parties')->count())->toBe(4)
        ->and(DomainEvent::query()->where('entity_type', 'Hold')->count())->toBe(4)
        ->and(DomainEvent::query()->get()->every(fn (DomainEvent $event): bool => $event->actor_role === ActorRole::System))->toBeTrue();

    // The demand side stays inert — no demand-side status event is recorded (asserted by EXACT name, not
    // `like '%Activated%'`, which would match a legitimate ProducerActivated elsewhere in the suite).
    foreach ([
        'CustomerActivated', 'AccountActivated', 'ProfileActivated', 'ProfileApproved',
        'OriginatingClubLocked', 'CustomerSegmentChanged',
    ] as $demandSideEvent) {
        expect(DomainEvent::query()->where('name', $demandSideEvent)->count())->toBe(0);
    }
});
