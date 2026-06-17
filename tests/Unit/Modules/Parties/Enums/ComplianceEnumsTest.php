<?php

use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Enums\SanctionsStatus;
use App\Modules\Parties\Enums\ScreeningTriggerSource;

// Pins the parties-compliance enums (parties-compliance, task 1.1; design L1/L3/L4).
// The three compliance value domains: KycStatus is the shared four-state KYC lifecycle
// (Module K PRD § 9.1 Customer / § 4.4 Producer) carrying the clears() predicate
// (cleared ≡ verified ∨ not_required); SanctionsStatus is the § 9.2 four-state sanctions
// lifecycle (separate from KYC); ScreeningTriggerSource is the § 9.2 trigger-path domain
// (DEC-030 / DEC-035). Each case/value map is asserted verbatim and order-sensitive,
// mirroring the parties-core EnumsTest: any drift in a case or its persisted token must
// fail here first.

it('backs KycStatus with the four spec KYC states', function () {
    $values = [];

    foreach (KycStatus::cases() as $state) {
        $values[$state->name] = $state->value;
    }

    expect($values)->toBe([
        'NotRequired' => 'not_required',
        'Pending' => 'pending',
        'Verified' => 'verified',
        'Rejected' => 'rejected',
    ]);

    expect(KycStatus::cases())->toHaveCount(4);
});

it('backs SanctionsStatus with the four spec sanctions states', function () {
    $values = [];

    foreach (SanctionsStatus::cases() as $state) {
        $values[$state->name] = $state->value;
    }

    expect($values)->toBe([
        'Pending' => 'pending',
        'Passed' => 'passed',
        'Failed' => 'failed',
        'UnderReview' => 'under_review',
    ]);

    expect(SanctionsStatus::cases())->toHaveCount(4);
});

it('backs ScreeningTriggerSource with the four spec trigger sources', function () {
    $values = [];

    foreach (ScreeningTriggerSource::cases() as $source) {
        $values[$source->name] = $source->value;
    }

    expect($values)->toBe([
        'Onboarding' => 'onboarding',
        'Cadence' => 'cadence',
        'AmlThreshold' => 'aml_threshold',
        'ComplianceAdHoc' => 'compliance_ad_hoc',
    ]);

    expect(ScreeningTriggerSource::cases())->toHaveCount(4);
});

it('round-trips the spec tokens through from()', function () {
    expect(KycStatus::from('not_required'))->toBe(KycStatus::NotRequired);
    expect(SanctionsStatus::from('under_review'))->toBe(SanctionsStatus::UnderReview);
    expect(ScreeningTriggerSource::from('aml_threshold'))->toBe(ScreeningTriggerSource::AmlThreshold);
});

it('clears KYC only for verified and not_required', function () {
    // The cleared-state truth table (design L1; § 4.4 — cleared ≡ verified ∨ not_required).
    expect(KycStatus::Verified->clears())->toBeTrue();
    expect(KycStatus::NotRequired->clears())->toBeTrue();
    expect(KycStatus::Pending->clears())->toBeFalse();
    expect(KycStatus::Rejected->clears())->toBeFalse();
});

it('rejects a kyc status outside the spec domain', function () {
    // `waived` is not a state — the operator-waive transitions TO not_required.
    expect(fn () => KycStatus::from('waived'))->toThrow(ValueError::class);
});

it('rejects a sanctions status outside the spec domain', function () {
    expect(fn () => SanctionsStatus::from('blocked'))->toThrow(ValueError::class);
});

it('rejects a screening trigger source outside the spec domain', function () {
    // Country-change detection is explicitly NOT a launch trigger (§ 9.2).
    expect(fn () => ScreeningTriggerSource::from('country_change'))->toThrow(ValueError::class);
});
