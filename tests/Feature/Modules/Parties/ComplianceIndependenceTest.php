<?php

use App\Modules\Parties\Actions\CreateCustomer;
use App\Modules\Parties\Actions\RecordCustomerScreening;
use App\Modules\Parties\Actions\RecordKycVerified;
use App\Modules\Parties\Actions\RequireKyc;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Enums\SanctionsStatus;
use App\Modules\Parties\Enums\ScreeningTriggerSource;
use App\Modules\Parties\Events\CustomerHoldLifted;
use App\Modules\Parties\Events\CustomerHoldPlaced;
use App\Modules\Parties\Events\CustomerOnboardingScreeningPassed;
use App\Modules\Parties\Models\Customer;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The independence + scope-guard proof for the compliance slice (parties-compliance task 6.1, design L1/L3;
 * party-registry — Requirements: Customer KYC Lifecycle, Customer Sanctions Screening Lifecycle, and the MODIFIED
 * "Birth States Recorded, Lifecycle Transitions Deferred"). Where {@see CustomerKycLifecycleTest} and
 * {@see CustomerSanctionsLifecycleTest} each pin ONE FSM in isolation, this one asserts the emergent contract of
 * the slice as a whole — that the three Customer state machines are mutually independent, and that the slice's
 * scope boundary holds:
 *   - the KYC FSM, the sanctions FSM and the Customer STATUS FSM are SEPARATE and INDEPENDENT (§ 9.1/§ 9.2/§ 9.4):
 *     the (kyc × sanctions) state-pair grid persists orthogonally with the Customer status pinned to its `pending`
 *     birth, and — driven through the REAL Actions — a KYC transition moves only `kyc_status` while a sanctions
 *     screening moves only `sanctions_status`, neither touching the other FSM nor the Customer status;
 *   - KYC itself is event-silent (design L3 — the PRD § 15.1 names no KYC event), but opening/clearing Customer KYC
 *     now AUTO-PLACES then AUTO-LIFTS the coupled `kyc` Hold (parties-holds), so the compliance flow records the two
 *     Hold events plus the sanctions completion — and no event NAME contains "Kyc";
 *   - the scope guard: reflecting the Parties `Actions/` namespace, the compliance + supply-side transition Actions
 *     exist and — since parties-membership-activation — so do the demand-side activation transitions (`ApproveProfile`
 *     / `DeclineProfile` / `ActivateProfile` / `ActivateCustomer`), BUT the still-deferred demand-side status
 *     transitions do not (no `CloseCustomer` / `SuspendAccount` / `LockOriginatingClub`); `originating_club_id`'s
 *     ONLY mutation surface is the one-shot lock inside `ApproveProfile` (CreateCustomer writes it once to NULL at
 *     birth — no other Action touches it), the coupled `kyc` Hold place/lift performs NO Customer STATUS transition
 *     (the Hold→`suspended` coupling is deferred), and — driving the REAL compliance Actions — no demand-side status
 *     event (`CustomerActivated` / `ProfileActivated` / `OriginatingClubLocked` / `CustomerSegmentChanged`) is recorded.
 *
 * The EXACT-SET "only these non-Create Actions exist" whitelist has a single canonical home in
 * {@see SupplyLifecycleChainTest}; this file is its independence-angle companion and uses a forbidden-name negative
 * check (robust to future legitimate compliance Actions), so a new compliance Action need not be declared in two
 * places. SpineCreationChainTest and the architecture tests (ModuleBoundariesTest, ModulePersistenceConventionsTest)
 * stay GREEN UNAMENDED — every reference here is within Module K and this change adds no model. RefreshDatabase per
 * the directory convention; each Action opens its OWN transaction (the recorder's `transactionLevel() === 0` guard
 * is satisfied by the savepoint under the wrapper). Events are asserted BY NAME and entity types by value — never a
 * byte-compare of stored jsonb (PG reorders keys — knowledge/testing trap 3) — so the file holds on PostgreSQL 17.
 */
uses(RefreshDatabase::class);

it('records each (kyc × sanctions) pair independently, with the Customer status never moving off its pending birth', function (KycStatus $kyc, SanctionsStatus $sanctions) {
    // The (kyc × sanctions) 4-cell at the state level (spec scenario "Sanctions and KYC are independent state
    // machines"): a Customer may hold ANY combination of the two compliance states. Re-read through the model so
    // the hydration casts are exercised, not the in-memory create() values.
    $customer = Customer::factory()->create([
        'kyc_status' => $kyc,
        'sanctions_status' => $sanctions,
    ]);

    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->kyc_status)->toBe($kyc)                       // the KYC column holds its own value...
        ->and($fresh->sanctions_status)->toBe($sanctions)        // ...the sanctions column holds its own, orthogonally...
        ->and($fresh->status)->toBe(CustomerStatus::Pending);    // ...and neither moved the separate Customer status FSM.
})->with([
    'kyc pending  × sanctions pending' => [KycStatus::Pending, SanctionsStatus::Pending],
    'kyc pending  × sanctions passed' => [KycStatus::Pending, SanctionsStatus::Passed],
    'kyc verified × sanctions pending' => [KycStatus::Verified, SanctionsStatus::Pending],
    'kyc verified × sanctions passed' => [KycStatus::Verified, SanctionsStatus::Passed],
]);

it('keeps KYC, sanctions and the Customer status mutually independent across real transitions, coupling the kyc Hold but recording no demand-side event', function () {
    $customer = Customer::factory()->create();   // un-screened: kyc NULL, sanctions NULL, status `pending`
    expect($customer->kyc_status)->toBeNull()
        ->and($customer->sanctions_status)->toBeNull();

    // (1) A KYC transition moves ONLY kyc_status — sanctions and the Customer status stay put. KYC itself records no
    //     KYC event (design L3), but the coupled `kyc` Hold is auto-placed on require and auto-lifted on verify, so
    //     the two Hold events are the only events so far — and no event NAME contains "Kyc".
    app(RequireKyc::class)->handle($customer->id);
    app(RecordKycVerified::class)->handle($customer->id);

    $afterKyc = Customer::findOrFail($customer->id);
    expect($afterKyc->kyc_status)->toBe(KycStatus::Verified)
        ->and($afterKyc->sanctions_status)->toBeNull()                  // sanctions untouched by KYC (§ 9.4)
        ->and($afterKyc->status)->toBe(CustomerStatus::Pending);        // the status FSM is separate
    expect(DomainEvent::query()->count())->toBe(2)                      // exactly the coupled Hold place + lift
        ->and(DomainEvent::query()->where('name', CustomerHoldPlaced::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', CustomerHoldLifted::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', 'like', '%Kyc%')->count())->toBe(0);   // KYC itself is event-silent

    // (2) A sanctions screening moves ONLY sanctions_status — kyc_status and the Customer status stay put.
    app(RecordCustomerScreening::class)->handle($customer->id, SanctionsStatus::Passed, ScreeningTriggerSource::Onboarding);

    $afterScreening = Customer::findOrFail($customer->id);
    expect($afterScreening->sanctions_status)->toBe(SanctionsStatus::Passed)
        ->and($afterScreening->kyc_status)->toBe(KycStatus::Verified)   // KYC untouched by sanctions (§ 9.4)
        ->and($afterScreening->status)->toBe(CustomerStatus::Pending);  // the status FSM is separate

    // The sanctions completion joins the two Hold events — three in all (the onboarding pass is the only sanctions one).
    expect(DomainEvent::query()->count())->toBe(3)
        ->and(DomainEvent::query()->where('name', CustomerOnboardingScreeningPassed::NAME)->count())->toBe(1);

    // Scope guard (runtime): the coupled `kyc` Hold place/lift records the two Hold events (BR-K-Hold-1) but performs
    // NO Customer STATUS transition (the Hold→`suspended` coupling is deferred), and NO demand-side status event is
    // recorded — the demand-side change owns those (party-registry MODIFIED "Birth States…"). Asserted by EXACT name
    // (not `like '%Activated%'`, which would match legitimate supply-side activations were any present).
    expect(DomainEvent::query()->where('name', 'like', '%Hold%')->count())->toBe(2);   // 1 placed + 1 lifted
    foreach ([
        'CustomerActivated', 'AccountActivated', 'ProfileActivated', 'ProfileApproved',
        'OriginatingClubLocked', 'CustomerSegmentChanged',
    ] as $demandSideEvent) {
        expect(DomainEvent::query()->where('name', $demandSideEvent)->count())->toBe(0);
    }
    // No event in the compliance flow carries an Account or Profile entity type — the demand side stays inert (the
    // sanctions event carries the Customer type and the Hold events the `Hold` type, so neither is excluded here).
    expect(DomainEvent::query()->whereIn('entity_type', ['Account', 'Profile'])->count())->toBe(0);
});

it('exposes the compliance + supply-side transitions but no still-deferred demand-side status transition class and no Originating-Club setter (the scope guard)', function () {
    // Reflect the Parties Actions namespace: every Action is a flat class file directly under Actions/.
    $files = glob(app_path('Modules/Parties/Actions/*.php')) ?: [];
    expect($files)->not->toBeEmpty();   // the walk must have run — never a vacuous pass

    $actions = array_map(static fn (string $file): string => basename($file, '.php'), $files);

    // Genuine reflection (not a string-only scan): every Action file maps to a real class in the namespace.
    foreach ($actions as $name) {
        expect(class_exists('App\\Modules\\Parties\\Actions\\'.$name))->toBeTrue();
    }

    // The compliance FSMs (KYC + sanctions — SEPARATE from the Customer/Producer status FSMs, § 9.1/§ 9.4) and the
    // supply-side lifecycle expose their transition Actions...
    foreach ([
        'RequireKyc', 'RecordKycVerified', 'RecordKycRejected',
        'RequireProducerKyc', 'RecordProducerKycVerified', 'RecordProducerKycRejected', 'WaiveProducerKyc',
        'RecordCustomerScreening',
        'ActivateProducer', 'RetireProducer', 'ActivateProducerAgreement', 'TerminateProducerAgreement',
        'SunsetClub', 'CloseClub',
    ] as $present) {
        expect($actions)->toContain($present);
    }

    // ...but the STILL-DEFERRED demand-side STATUS transitions do not exist: the Account exposes no operation moving
    // its status out of its `active` birth, and the Customer exposes no terminal `active | suspended → closed`
    // transition (party-registry MODIFIED — `CloseCustomer` and the Account FSM remain deferred to task 3.2). The
    // now-shipped demand-side activation Actions (`ApproveProfile` / `DeclineProfile` / `ActivateProfile` /
    // `ActivateCustomer`, parties-membership-activation) AND the now-shipped Customer suspend/restore cascade
    // (`SuspendCustomer` / `ReactivateCustomer`, parties-membership-suspension task 3.1 — driven manually or by the
    // Hold coupling, never by a compliance verdict) are REMOVED from this forbidden set: their presence is pinned by
    // the EXACT-SET whitelist in SupplyLifecycleChainTest (this negative check is the independence-angle companion,
    // robust to a future legitimate compliance Action). The remaining forbidden names follow the codebase's
    // verb+Entity convention and map 1:1 to the still-deferred demand-side transitions.
    foreach ([
        'CloseCustomer',
        'ActivateAccount', 'SuspendAccount', 'CloseAccount',
        'LockOriginatingClub', 'SetOriginatingClub',
    ] as $forbidden) {
        expect($actions)->not->toContain($forbidden);
    }

    // `originating_club_id`'s mutation surface is now the one-shot lock inside ApproveProfile (BR-K-OC-2 / design L3
    // — parties-membership-activation): the deferred membership-approval write has landed. CreateCustomer's public
    // surface is still exactly creation...
    $createCustomerMethods = collect((new ReflectionClass(CreateCustomer::class))->getMethods(ReflectionMethod::IS_PUBLIC))
        ->map(static fn (ReflectionMethod $method): string => $method->getName())
        ->all();
    expect($createCustomerMethods)->toEqualCanonicalizing(['__construct', 'handle']);

    // ...and exactly two Actions write the column: CreateCustomer once (to NULL, at birth) and ApproveProfile once
    // (the one-shot lock, to the approving Club on the first approval); every OTHER Action writes it zero times (the
    // assertion targets the array-key write form `'originating_club_id' =>`, so a docblock that merely mentions the
    // column in prose — e.g. CloseClub's "seam now" note or ApproveProfile's lock prose — is not a false positive).
    foreach ($files as $file) {
        $name = basename($file, '.php');
        $source = (string) file_get_contents($file);
        $writes = substr_count($source, "'originating_club_id' =>");
        if ($name === 'CreateCustomer') {
            expect($writes)->toBe(1)
                ->and($source)->toContain("'originating_club_id' => null");   // born NULL — the birth write
        } elseif ($name === 'ApproveProfile') {
            // The Originating-Club one-shot lock (design L3) — the column's only MUTATION surface: a conditional,
            // non-NULL write (to the approving Club), never a reset to null. Gated on the link being unset, so the
            // lock is idempotent + immutable.
            expect($writes)->toBe(1)
                ->and($source)->not->toContain("'originating_club_id' => null");
        } else {
            expect($writes)->toBe(0);   // no other Action writes the Originating-Club FK
        }
    }
});
