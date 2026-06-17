<?php

use App\Modules\Parties\Actions\RecordCustomerScreening;
use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Enums\SanctionsStatus;
use App\Modules\Parties\Enums\ScreeningTriggerSource;
use App\Modules\Parties\Events\CustomerOnboardingScreeningFailed;
use App\Modules\Parties\Events\CustomerOnboardingScreeningPassed;
use App\Modules\Parties\Events\CustomerRescreeningFailed;
use App\Modules\Parties\Events\CustomerRescreeningPassed;
use App\Modules\Parties\Exceptions\IllegalSanctionsTransition;
use App\Modules\Parties\Models\Customer;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the Customer sanctions-screening lifecycle (parties-compliance, design L4/L6/L8; party-registry —
 * Requirements: Customer Sanctions Screening Lifecycle, Sanctions Screening Events). {@see RecordCustomerScreening}
 * is the SOLE writer of the sanctions fields (`sanctions_status` + `last_screening_at` / `next_rescreen_at` /
 * `screening_trigger_source`, all additive nullable — DEC-071) and the SINGLE writer of the four § 15.6 events.
 * It is the first EVENTED compliance Action: unlike the audit-only KYC Actions (design L3) it injects the
 * {@see DomainEventRecorder} and resolves the operator from the {@see ActorContext} seam (System default).
 *
 * The invariants this test pins (design L4): a screening sets the verdict and stamps the window
 * (`next_rescreen_at` exactly 12 months past `last_screening_at`); a `passed`/`failed` completion records exactly
 * one event whose FAMILY is the phase (onboarding → `CustomerOnboardingScreening*`, any re-screen →
 * `CustomerRescreening*`) with a PII-free payload; an `under_review` verdict records NO event (a later resolution
 * does); the only hard guard is onboarding-is-first (`last_screening_at IS NULL`), while re-screens are admissible
 * from any prior state and can flip a verdict; and a screening is INDEPENDENT of KYC (§ 9.4 — it never touches
 * `kyc_status`). The operator ad-hoc re-screen path ships; the automated 12-month cadence job and the AML-threshold
 * scan are deferred seams (design L4/L6 — no job records these events). RefreshDatabase per the directory
 * convention; each Action opens its OWN transaction, so the rejected-onboarding rollback is a savepoint under the
 * wrapper (the guard throws before any write, so the verify-after-throw SELECT survives on PostgreSQL 17 —
 * cross-engine close in task 6.3). Payloads are asserted BY KEY, never a byte-compare of stored jsonb (PG reorders
 * keys — knowledge/testing trap 3).
 */
uses(RefreshDatabase::class);

it('records the onboarding screening: sets the verdict, stamps the 12-month window, records the matching onboarding event PII-free', function (SanctionsStatus $verdict, string $expectedEvent) {
    // Explicit PII sentinels so the payload assertion can prove no personal data leaks into the 10-year audit
    // store; `kyc_status` is seeded `verified` to prove the screening leaves the independent KYC FSM untouched.
    $customer = Customer::factory()->create([
        'email' => 'sanctions-sentinel@example.test',
        'name' => 'Sanctions Sentinel',
        'phone' => '+10000000009',
        'kyc_status' => KycStatus::Verified,
    ]);
    expect($customer->sanctions_status)->toBeNull()
        ->and($customer->last_screening_at)->toBeNull();   // precondition — un-screened birth (DEC-071)

    $returned = app(RecordCustomerScreening::class)->handle(
        $customer->id,
        $verdict,
        ScreeningTriggerSource::Onboarding,
    );

    // The returned model and the persisted row both carry the verdict, the onboarding source, and the window.
    $fresh = Customer::findOrFail($customer->id);
    expect($returned->sanctions_status)->toBe($verdict)
        ->and($fresh->sanctions_status)->toBe($verdict)
        ->and($fresh->screening_trigger_source)->toBe(ScreeningTriggerSource::Onboarding)
        ->and($fresh->last_screening_at)->not->toBeNull()
        ->and($fresh->next_rescreen_at)->not->toBeNull()
        // next_rescreen_at is exactly 12 months past last_screening_at (one captured instant — design L4).
        ->and($fresh->next_rescreen_at?->toDateTimeString())
        ->toBe($fresh->last_screening_at?->addMonths(12)?->toDateTimeString())
        // Independent of KYC (§ 9.4): the screening never touched kyc_status.
        ->and($fresh->kyc_status)->toBe(KycStatus::Verified);

    // Exactly one event — the matching onboarding completion — and nothing else (the factory records no
    // CustomerCreated, so this screening is the only event writer in the test).
    expect(DomainEvent::query()->count())->toBe(1);
    $event = DomainEvent::query()->where('name', $expectedEvent)->sole();

    // Envelope: module parties, entity Customer/<id>, resolved to the System actor (the ActorContext seam default).
    expect($event->module)->toBe('parties')
        ->and($event->entity_type)->toBe('Customer')
        ->and($event->entity_id)->toBe((string) $customer->id)
        ->and($event->actor_role)->toBe(ActorRole::System);

    // PII-free payload (decisions/2026-06-12-event-substrate-and-audit-store.md): exactly the three keys, asserted
    // BY KEY (PG reorders jsonb — trap 3), carrying the ids/enum values only — no personal data leaks.
    expect(array_keys($event->payload))->toEqualCanonicalizing(['customer_id', 'sanctions_status', 'trigger_source'])
        ->and($event->payload['customer_id'])->toBe($customer->id)
        ->and($event->payload['sanctions_status'])->toBe($verdict->value)
        ->and($event->payload['trigger_source'])->toBe(ScreeningTriggerSource::Onboarding->value);
    foreach (['email', 'name', 'phone', 'date_of_birth'] as $piiKey) {
        expect($event->payload)->not->toHaveKey($piiKey);
    }
    expect(array_values($event->payload))->not->toContain('sanctions-sentinel@example.test')
        ->and(array_values($event->payload))->not->toContain('Sanctions Sentinel');
})->with([
    'passed → CustomerOnboardingScreeningPassed' => [SanctionsStatus::Passed, CustomerOnboardingScreeningPassed::NAME],
    'failed → CustomerOnboardingScreeningFailed' => [SanctionsStatus::Failed, CustomerOnboardingScreeningFailed::NAME],
]);

it('lands under_review without recording any event, but still stamps the screening window', function () {
    $customer = Customer::factory()->create();   // un-screened

    $returned = app(RecordCustomerScreening::class)->handle(
        $customer->id,
        SanctionsStatus::UnderReview,
        ScreeningTriggerSource::Onboarding,
    );

    $fresh = Customer::findOrFail($customer->id);
    expect($returned->sanctions_status)->toBe(SanctionsStatus::UnderReview)
        ->and($fresh->sanctions_status)->toBe(SanctionsStatus::UnderReview)
        ->and($fresh->screening_trigger_source)->toBe(ScreeningTriggerSource::Onboarding)
        ->and($fresh->last_screening_at)->not->toBeNull()
        ->and($fresh->next_rescreen_at)->not->toBeNull();

    // under_review is NOT a completion (design L4; § 15.6 names only the Passed/Failed pairs) — no event recorded.
    expect(DomainEvent::query()->count())->toBe(0);
});

it('records the rescreening event when an open under_review resolves', function (SanctionsStatus $verdict, string $expectedEvent) {
    // First screen lands under_review (records nothing); the resolution is a re-screen.
    $customer = Customer::factory()->create();
    app(RecordCustomerScreening::class)->handle($customer->id, SanctionsStatus::UnderReview, ScreeningTriggerSource::Onboarding);
    expect(DomainEvent::query()->count())->toBe(0);   // under_review recorded no event

    // Resolve via an ad-hoc re-screen (a non-onboarding trigger → the rescreening family).
    app(RecordCustomerScreening::class)->handle($customer->id, $verdict, ScreeningTriggerSource::ComplianceAdHoc);

    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->sanctions_status)->toBe($verdict)
        ->and($fresh->screening_trigger_source)->toBe(ScreeningTriggerSource::ComplianceAdHoc);

    // Exactly the one resolution event (the under_review screen recorded none).
    expect(DomainEvent::query()->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', $expectedEvent)->count())->toBe(1);
})->with([
    'passed → CustomerRescreeningPassed' => [SanctionsStatus::Passed, CustomerRescreeningPassed::NAME],
    'failed → CustomerRescreeningFailed' => [SanctionsStatus::Failed, CustomerRescreeningFailed::NAME],
]);

it('records the rescreening event with its trigger source on a previously-screened Customer, flipping the verdict', function (ScreeningTriggerSource $source) {
    // Onboarding passed first (records CustomerOnboardingScreeningPassed, stamps last_screening_at).
    $customer = Customer::factory()->create();
    app(RecordCustomerScreening::class)->handle($customer->id, SanctionsStatus::Passed, ScreeningTriggerSource::Onboarding);
    expect(DomainEvent::query()->where('name', CustomerOnboardingScreeningPassed::NAME)->count())->toBe(1);

    // Re-screen to failed via a non-onboarding source — admissible from any prior state, flips passed → failed,
    // and records the rescreening family. The operator ad-hoc path ships now; the automated cadence/AML scan is a
    // deferred seam (design L4/L6 — no background job records these events, only this Action).
    app(RecordCustomerScreening::class)->handle($customer->id, SanctionsStatus::Failed, $source);

    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->sanctions_status)->toBe(SanctionsStatus::Failed)
        ->and($fresh->screening_trigger_source)->toBe($source);

    // Two events: the onboarding pass + the rescreening fail (family selected by source, not the prior state).
    expect(DomainEvent::query()->count())->toBe(2)
        ->and(DomainEvent::query()->where('name', CustomerRescreeningFailed::NAME)->count())->toBe(1);

    // The rescreening payload records the trigger source verbatim (AC-K-EVT-12a).
    $rescreen = DomainEvent::query()->where('name', CustomerRescreeningFailed::NAME)->sole();
    expect($rescreen->payload['trigger_source'])->toBe($source->value);
})->with([
    'cadence' => [ScreeningTriggerSource::Cadence],
    'aml_threshold' => [ScreeningTriggerSource::AmlThreshold],
    'compliance_ad_hoc' => [ScreeningTriggerSource::ComplianceAdHoc],
]);

it('rejects an onboarding screening on an already-screened Customer, leaving the sanctions state and event log unchanged', function () {
    // A Customer already screened (last_screening_at set) — seeded via the factory for isolation.
    $screenedAt = CarbonImmutable::parse('2026-01-01 09:00:00');
    $customer = Customer::factory()->create([
        'sanctions_status' => SanctionsStatus::Passed,
        'last_screening_at' => $screenedAt,
        'next_rescreen_at' => $screenedAt->addMonths(12),
        'screening_trigger_source' => ScreeningTriggerSource::Onboarding,
    ]);
    $baseline = DomainEvent::query()->count();   // 0 — the factory records no event

    expect(fn () => app(RecordCustomerScreening::class)->handle(
        $customer->id,
        SanctionsStatus::Failed,
        ScreeningTriggerSource::Onboarding,
    ))->toThrow(IllegalSanctionsTransition::class);

    // The onboarding-is-first guard fires before any write and the transaction rolls back: the sanctions state,
    // the window and the event log are all unchanged.
    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->sanctions_status)->toBe(SanctionsStatus::Passed)
        ->and($fresh->screening_trigger_source)->toBe(ScreeningTriggerSource::Onboarding)
        ->and($fresh->last_screening_at?->toDateTimeString())->toBe($screenedAt->toDateTimeString())
        ->and(DomainEvent::query()->count())->toBe($baseline);
});
