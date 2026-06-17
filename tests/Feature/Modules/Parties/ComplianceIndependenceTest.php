<?php

use App\Modules\Parties\Actions\CreateCustomer;
use App\Modules\Parties\Actions\RecordCustomerScreening;
use App\Modules\Parties\Actions\RecordKycVerified;
use App\Modules\Parties\Actions\RequireKyc;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Enums\SanctionsStatus;
use App\Modules\Parties\Enums\ScreeningTriggerSource;
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
 *   - KYC is event-silent (design L3 — the PRD § 15.1 names no KYC event), so the only event the whole compliance
 *     flow records is the sanctions completion;
 *   - the scope guard: reflecting the Parties `Actions/` namespace, the compliance + supply-side transition Actions
 *     exist but NO demand-side STATUS transition class does (no `ActivateCustomer` / `SuspendAccount` /
 *     `ApproveProfile` / `LockOriginatingClub`), `originating_club_id` has no setter (CreateCustomer's surface is
 *     exactly creation, and it is the only Action that writes the column — only NULL, at birth), no `kyc` Hold is
 *     placed (the Hold registry is the deferred `parties-holds`), and no demand-side status event
 *     (`CustomerActivated` / `ProfileActivated` / `OriginatingClubLocked` / `CustomerSegmentChanged`) is recorded.
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

it('keeps KYC, sanctions and the Customer status mutually independent across real transitions, placing no Hold and recording no demand-side event', function () {
    $customer = Customer::factory()->create();   // un-screened: kyc NULL, sanctions NULL, status `pending`
    expect($customer->kyc_status)->toBeNull()
        ->and($customer->sanctions_status)->toBeNull();

    // (1) A KYC transition moves ONLY kyc_status — sanctions and the Customer status stay put, and KYC records no
    //     domain event (design L3 — audit only; the PRD § 15.1 names none).
    app(RequireKyc::class)->handle($customer->id);
    app(RecordKycVerified::class)->handle($customer->id);

    $afterKyc = Customer::findOrFail($customer->id);
    expect($afterKyc->kyc_status)->toBe(KycStatus::Verified)
        ->and($afterKyc->sanctions_status)->toBeNull()                  // sanctions untouched by KYC (§ 9.4)
        ->and($afterKyc->status)->toBe(CustomerStatus::Pending);        // the status FSM is separate
    expect(DomainEvent::query()->count())->toBe(0);                     // KYC is event-silent

    // (2) A sanctions screening moves ONLY sanctions_status — kyc_status and the Customer status stay put.
    app(RecordCustomerScreening::class)->handle($customer->id, SanctionsStatus::Passed, ScreeningTriggerSource::Onboarding);

    $afterScreening = Customer::findOrFail($customer->id);
    expect($afterScreening->sanctions_status)->toBe(SanctionsStatus::Passed)
        ->and($afterScreening->kyc_status)->toBe(KycStatus::Verified)   // KYC untouched by sanctions (§ 9.4)
        ->and($afterScreening->status)->toBe(CustomerStatus::Pending);  // the status FSM is separate

    // Exactly the one sanctions completion event — the onboarding pass — and nothing else (KYC contributed none).
    expect(DomainEvent::query()->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', CustomerOnboardingScreeningPassed::NAME)->count())->toBe(1);

    // Scope guard (runtime): this slice places NO `kyc` Hold (the unified Hold registry is the deferred
    // `parties-holds` change), and NO demand-side status event is recorded — the demand-side change owns those
    // (party-registry MODIFIED "Birth States…"). Asserted by EXACT name (not `like '%Activated%'`, which would
    // match the legitimate supply-side Producer/Agreement activations were any present).
    expect(DomainEvent::query()->where('name', 'like', '%Hold%')->count())->toBe(0);
    foreach ([
        'CustomerActivated', 'AccountActivated', 'ProfileActivated', 'ProfileApproved',
        'OriginatingClubLocked', 'CustomerSegmentChanged',
    ] as $demandSideEvent) {
        expect(DomainEvent::query()->where('name', $demandSideEvent)->count())->toBe(0);
    }
    // No event in the compliance flow carries an Account or Profile entity type — the demand side stays inert (the
    // sanctions event legitimately carries the Customer type, so Customer is NOT in this exclusion set).
    expect(DomainEvent::query()->whereIn('entity_type', ['Account', 'Profile'])->count())->toBe(0);
});

it('exposes the compliance + supply-side transitions but no demand-side status transition class and no Originating-Club setter (the scope guard)', function () {
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

    // ...but NO demand-side STATUS transition class exists: Customer / Account / Profile expose no operation moving
    // their status out of its birth state (party-registry MODIFIED — "demand-side status transitions do not [exist]").
    // The forbidden names follow the codebase's verb+Entity convention (cf. ActivateProducer / RetireProducer /
    // SunsetClub) and map 1:1 to the deferred demand-side events. The EXACT-SET "only these exist" whitelist lives
    // canonically in SupplyLifecycleChainTest; this negative check is its independence-angle companion (robust to a
    // future legitimate compliance Action being added without touching two files).
    foreach ([
        'ActivateCustomer', 'SuspendCustomer', 'CloseCustomer',
        'ActivateAccount', 'SuspendAccount', 'CloseAccount',
        'ApproveProfile', 'ActivateProfile', 'DeclineProfile',
        'LockOriginatingClub', 'SetOriginatingClub',
    ] as $forbidden) {
        expect($actions)->not->toContain($forbidden);
    }

    // `originating_club_id` has no setter (BR-K-OC-2 / design D6 — the one-shot OriginatingClubLocked write arrives
    // with the deferred membership-approval change). CreateCustomer's public surface is exactly creation...
    $createCustomerMethods = collect((new ReflectionClass(CreateCustomer::class))->getMethods(ReflectionMethod::IS_PUBLIC))
        ->map(static fn (ReflectionMethod $method): string => $method->getName())
        ->all();
    expect($createCustomerMethods)->toEqualCanonicalizing(['__construct', 'handle']);

    // ...and CreateCustomer is the ONLY Action that writes the column — exactly once, to NULL, at birth. No other
    // Action writes it (the assertion targets the array-key write form `'originating_club_id' =>`, so a docblock
    // that merely mentions the column in prose — e.g. CloseClub's "seam now" note — is not a false positive).
    foreach ($files as $file) {
        $source = (string) file_get_contents($file);
        $writes = substr_count($source, "'originating_club_id' =>");
        if (basename($file, '.php') === 'CreateCustomer') {
            expect($writes)->toBe(1)
                ->and($source)->toContain("'originating_club_id' => null");   // born NULL — the only write, at birth
        } else {
            expect($writes)->toBe(0);   // no mutation surface for the Originating-Club FK
        }
    }
});
