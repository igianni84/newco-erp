<?php

use App\Modules\Parties\Actions\ActivateProducer;
use App\Modules\Parties\Actions\RecordCustomerScreening;
use App\Modules\Parties\Actions\RecordKycVerified;
use App\Modules\Parties\Actions\RecordProducerKycRejected;
use App\Modules\Parties\Actions\RecordProducerKycVerified;
use App\Modules\Parties\Actions\RequireKyc;
use App\Modules\Parties\Actions\RequireProducerKyc;
use App\Modules\Parties\Actions\WaiveProducerKyc;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Enums\ProducerStatus;
use App\Modules\Parties\Enums\SanctionsStatus;
use App\Modules\Parties\Enums\ScreeningTriggerSource;
use App\Modules\Parties\Events\CustomerOnboardingScreeningPassed;
use App\Modules\Parties\Events\CustomerRescreeningPassed;
use App\Modules\Parties\Events\ProducerActivated;
use App\Modules\Parties\Exceptions\IllegalProducerTransition;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Producer;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The full-chain integration proof + cross-engine close for the Parties COMPLIANCE slice (parties-compliance
 * task 6.3; design L1/L3/L4/L5; party-registry — the four ADDED Requirements (Customer KYC Lifecycle, Customer
 * Sanctions Screening Lifecycle, Producer KYC Lifecycle, Sanctions Screening Events) and both MODIFIED ones
 * (Producer Lifecycle, "Birth States Recorded, Lifecycle Transitions Deferred")). Where each sibling pins ONE
 * compliance FSM in isolation ({@see CustomerKycLifecycleTest}, {@see CustomerSanctionsLifecycleTest},
 * {@see ProducerKycLifecycleTest}, {@see ProducerLifecycleTest}) and {@see ComplianceIndependenceTest} proves their
 * mutual independence, this one drives the WHOLE slice through its real Actions in one chain — open + clear Customer
 * KYC, screen + re-screen sanctions, open + waive Producer KYC, then activate the Producer through the NEW
 * KYC-cleared gate — and asserts the emergent contract of the slice as a whole:
 *   - every FSM reaches its cleared/screened terminal and the Producer activates (the gate admits `not_required`);
 *   - the chain records EXACTLY the two sanctions completions + the one ProducerActivated — KYC is event-silent
 *     (design L3 — the PRD § 15.1 names no KYC event), no `kyc` Hold is placed (the unified Hold registry is the
 *     deferred `parties-holds`), and the demand side stays inert (no CustomerActivated / AccountActivated /
 *     ProfileActivated / ProfileApproved / OriginatingClubLocked / CustomerSegmentChanged, no Account/Profile
 *     entity-type event);
 *   - the Producer activation matrix holds, driven through the REAL Producer-KYC Actions into the gate: cleared
 *     (`verified` / `not_required` / NULL) admits, blocking (`pending` / `rejected`) rejects with the Producer left
 *     `draft` and no event (AC-K-FSM-7);
 *   - the asymmetric NULL semantics (design L1) hold on BOTH engines: a NULL-kyc Producer is CLEARED (activates)
 *     while a NULL-sanctions Customer is NOT `passed` (only an explicit screening reaches `passed`).
 *
 * Creation uses the factories deliberately (not the CreateCustomer/CreateProducer spine Actions): the spine creation
 * chain is already proven in {@see SpineCreationChainTest}, and CreateCustomer co-provisions an Account (recording
 * an Account creation event), which would muddy this slice's whole point — that the compliance chain records ONLY
 * the compliance events and touches no Account/Profile. The factories record no event, so every event observed here
 * is one a compliance Action wrote.
 *
 * This is the cross-engine gate: this file and the WHOLE Parties suite are verified green on SQLite AND on a local
 * PostgreSQL 17 before the change is declared complete (knowledge/testing/rules.md) — including the asymmetric-NULL
 * assertions (design L1). Portability: events are asserted BY NAME and counts, never a byte-compare of stored jsonb
 * (PG reorders keys — trap 3); enum columns round-trip through the model casts. RefreshDatabase per the directory
 * convention; each Action opens its OWN DB::transaction, so the recorder's `transactionLevel() === 0` guard is
 * satisfied by the savepoint under the wrapper, and a rejected activation rolls back its savepoint while the wrapper
 * survives.
 */
uses(RefreshDatabase::class);

/**
 * Drives the ENTIRE compliance slice through the real transition Actions in the task's order — Customer KYC
 * (require → verify), Customer sanctions (onboarding pass → ad-hoc re-screen pass), Producer KYC (require → waive),
 * then Producer activation through the cleared gate — and returns the two entities by key. Every leg goes through
 * the genuine Action (its own DB::transaction + the recorder for the evented sanctions/activation legs), exactly as
 * production would. The Customer and Producer are factory-born un-screened (NULL kyc / NULL sanctions); the factory
 * records no event, so every event observed by the callers is one a compliance Action wrote.
 *
 * @return array{customer: Customer, producer: Producer}
 */
function runComplianceChain(): array
{
    // 1. Stand up an un-screened Customer (status `pending`, kyc NULL, sanctions NULL) and Producer (status
    //    `draft`, kyc NULL) — the DEC-071 birth state. Factories bypass the Create* Actions (no creation event).
    $customer = Customer::factory()->create();
    $producer = Producer::factory()->create();

    // 2. Customer KYC: require (NULL → pending, sets kyc_required) then verify (pending → verified, a cleared
    //    state). Audit-only — records NO domain event (design L3).
    app(RequireKyc::class)->handle($customer->id);
    app(RecordKycVerified::class)->handle($customer->id);

    // 3. Customer sanctions: the onboarding screen passes (the FIRST screen — CustomerOnboardingScreeningPassed),
    //    then an operator ad-hoc re-screen passes again (a re-screen — CustomerRescreeningPassed). Two completion
    //    events; the onboarding-is-first guard admits the first, and the non-onboarding trigger is permissive after.
    app(RecordCustomerScreening::class)->handle($customer->id, SanctionsStatus::Passed, ScreeningTriggerSource::Onboarding);
    app(RecordCustomerScreening::class)->handle($customer->id, SanctionsStatus::Passed, ScreeningTriggerSource::ComplianceAdHoc);

    // 4. Producer KYC: require (NULL → pending) then waive (the operator deselect → not_required, a cleared state).
    //    Audit-only — records NO domain event (design L3).
    app(RequireProducerKyc::class)->handle($producer->id);
    app(WaiveProducerKyc::class)->handle($producer->id);

    // 5. Activate the Producer through the NEW KYC-cleared gate (design L5): `not_required` clears, so the
    //    activation succeeds and records the one ProducerActivated.
    app(ActivateProducer::class)->handle($producer->id);

    return ['customer' => $customer, 'producer' => $producer];
}

it('drives every compliance FSM to its cleared/screened terminal and activates the Producer through the new gate', function () {
    $chain = runComplianceChain();

    // Re-read through the models so the assertions exercise the hydration casts, not the in-memory create() values.
    $customer = Customer::findOrFail($chain['customer']->id);
    expect($customer->kyc_status)->toBe(KycStatus::Verified)                               // require → verify
        ->and($customer->sanctions_status)->toBe(SanctionsStatus::Passed)                  // onboarding → re-screen
        ->and($customer->screening_trigger_source)->toBe(ScreeningTriggerSource::ComplianceAdHoc)   // last screen's source
        ->and($customer->status)->toBe(CustomerStatus::Pending);                           // the demand-side status FSM never moved

    $producer = Producer::findOrFail($chain['producer']->id);
    expect($producer->kyc_status)->toBe(KycStatus::NotRequired)                            // require → waive (operator deselect)
        ->and($producer->status)->toBe(ProducerStatus::Active);                            // activated through the cleared gate
});

it('records exactly the two sanctions screening events and the one ProducerActivated — no KYC event, no Hold, demand side inert', function () {
    runComplianceChain();

    // EXACTLY three events: the two sanctions completions + the ProducerActivated from the gate. KYC is
    // event-silent (design L3), so neither the Customer-KYC require→verify nor the Producer-KYC require→waive
    // contributes a row. Asserted BY NAME (knowledge/testing trap 3 — never byte-compare PG jsonb).
    $expected = [
        CustomerOnboardingScreeningPassed::NAME => 1,
        CustomerRescreeningPassed::NAME => 1,
        ProducerActivated::NAME => 1,
    ];
    foreach ($expected as $name => $count) {
        expect(DomainEvent::query()->where('name', $name)->count())->toBe($count);
    }

    // Exactly these three distinct names and NO other — pinned so no surprise event can slip in. 3 rows total, all
    // module `parties`, all resolved to the System actor (the ActorContext seam default).
    expect(DomainEvent::query()->pluck('name')->unique()->values()->all())->toEqualCanonicalizing([
        CustomerOnboardingScreeningPassed::NAME,
        CustomerRescreeningPassed::NAME,
        ProducerActivated::NAME,
    ]);
    expect(DomainEvent::query()->count())->toBe(3)
        ->and(DomainEvent::query()->where('module', 'parties')->count())->toBe(3)
        ->and(DomainEvent::query()->get()->every(fn (DomainEvent $event): bool => $event->actor_role === ActorRole::System))->toBeTrue();

    // KYC is event-silent (Customer AND Producer): no event name carries "Kyc". And no `kyc` Hold is placed — the
    // unified Hold registry is the deferred `parties-holds` (design L3 / proposal slice boundary).
    expect(DomainEvent::query()->where('name', 'like', '%Kyc%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Hold%')->count())->toBe(0);

    // The demand side stays inert: no demand-side status event is recorded (party-registry MODIFIED "Birth
    // States…" — the demand-side change owns these). Asserted by EXACT name (not `like '%Activated%'`, which would
    // match the legitimate ProducerActivated).
    foreach ([
        'CustomerActivated', 'AccountActivated', 'ProfileActivated', 'ProfileApproved',
        'OriginatingClubLocked', 'CustomerSegmentChanged',
    ] as $demandSideEvent) {
        expect(DomainEvent::query()->where('name', $demandSideEvent)->count())->toBe(0);
    }

    // No event carries an Account or Profile entity type — the compliance chain touches neither. The sanctions
    // events legitimately carry the Customer type and ProducerActivated the Producer type, so those are NOT excluded.
    expect(DomainEvent::query()->whereIn('entity_type', ['Account', 'Profile'])->count())->toBe(0);
});

it('admits or blocks Producer activation per the KYC-cleared matrix, driving the real Producer-KYC Actions into the gate', function (?KycStatus $target, bool $activates) {
    // AC-K-FSM-7 (parties-compliance design L5): the activation gate admits the cleared KYC states (`verified` /
    // `not_required` / NULL) and blocks the rest (`pending` / `rejected`). Driven through the REAL Producer-KYC
    // Actions (not a factory-set column) so the integration close proves the Producer-KYC FSM COMPOSES with the
    // gate — the per-transition matrix has its canonical home in ProducerLifecycleTest.
    $producer = Producer::factory()->create();   // born `draft`, kyc NULL

    if ($target === KycStatus::Pending) {
        app(RequireProducerKyc::class)->handle($producer->id);
    } elseif ($target === KycStatus::Verified) {
        app(RequireProducerKyc::class)->handle($producer->id);
        app(RecordProducerKycVerified::class)->handle($producer->id);
    } elseif ($target === KycStatus::Rejected) {
        app(RequireProducerKyc::class)->handle($producer->id);
        app(RecordProducerKycRejected::class)->handle($producer->id);
    } elseif ($target === KycStatus::NotRequired) {
        app(RequireProducerKyc::class)->handle($producer->id);
        app(WaiveProducerKyc::class)->handle($producer->id);
    }
    // NULL: never touched — the additive birth state (DEC-071).

    // The real Producer-KYC FSM landed the intended state before the gate runs.
    expect(Producer::findOrFail($producer->id)->kyc_status)->toBe($target);

    if ($activates) {
        app(ActivateProducer::class)->handle($producer->id);
        expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Active)
            ->and(DomainEvent::query()->where('name', ProducerActivated::NAME)->count())->toBe(1);
    } else {
        // The `draft` from-state assert passes (the Producer IS draft), so the KYC-cleared gate is the sole reason
        // for the throw — its localized message names KYC, distinguishing it from the from-state guard.
        expect(fn () => app(ActivateProducer::class)->handle($producer->id))
            ->toThrow(IllegalProducerTransition::class, 'KYC');
        expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Draft)
            ->and(DomainEvent::query()->where('name', ProducerActivated::NAME)->count())->toBe(0);
    }
})->with([
    'NULL (never screened) → activates' => [null, true],
    'not_required (require → waive) → activates' => [KycStatus::NotRequired, true],
    'verified (require → verify) → activates' => [KycStatus::Verified, true],
    'pending (require) → blocked' => [KycStatus::Pending, false],
    'rejected (require → reject) → blocked' => [KycStatus::Rejected, false],
]);

it('holds the asymmetric NULL semantics — a NULL-kyc Producer is cleared (activates) while a NULL-sanctions Customer is not passed', function () {
    // design L1 — the two NULL meanings are deliberately OPPOSITE, and BOTH must hold on PostgreSQL 17 (the nullable
    // CHECK admits NULL on PG; the asymmetry is a gate/meaning concern, not a schema one).

    // (a) Producer kyc_status NULL ⇒ CLEARED: a never-screened Producer activates (additive-safety for rows created
    //     before parties-compliance — DEC-071, nullable, no backfill).
    $producer = Producer::factory()->create();   // NULL kyc_status, `draft`
    expect($producer->kyc_status)->toBeNull();
    app(ActivateProducer::class)->handle($producer->id);
    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Active);

    // (b) Customer sanctions_status NULL ⇒ NOT passed: a never-screened Customer is NOT in `passed`; `passed` is
    //     reachable ONLY through an explicit operator screening, never by default. (Module K is sanctions-blind —
    //     the purchase-gate enforcement is Module S's, § 9.3; this slice owns the state-level asymmetry.)
    $customer = Customer::factory()->create();   // NULL sanctions_status, `pending`
    expect($customer->sanctions_status)->toBeNull()
        ->and($customer->sanctions_status)->not->toBe(SanctionsStatus::Passed);

    app(RecordCustomerScreening::class)->handle($customer->id, SanctionsStatus::Passed, ScreeningTriggerSource::Onboarding);
    expect(Customer::findOrFail($customer->id)->sanctions_status)->toBe(SanctionsStatus::Passed);   // only NOW passed
});
