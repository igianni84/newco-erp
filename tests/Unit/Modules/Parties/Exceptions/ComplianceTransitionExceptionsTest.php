<?php

use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Enums\SanctionsStatus;
use App\Modules\Parties\Exceptions\IllegalKycTransition;
use App\Modules\Parties\Exceptions\IllegalSanctionsTransition;
use Tests\TestCase;

// Pins the two compliance transition-guard exceptions (parties-compliance, task 1.3; design L2/L4;
// party-registry — Requirements: Customer/Producer KYC Lifecycle, Customer Sanctions Screening
// Lifecycle). The KYC + sanctions transition Actions (tasks 2.x–4.x) throw these on a rejected call;
// here we assert each named factory builds the right class with a localized, PII-free reason that names
// the offending state. Booting the app (TestCase, NO RefreshDatabase — no DB is touched) makes the
// translator available so __() resolves the lang/en/parties.php copy instead of echoing the key back.

uses(TestCase::class);

// Each chosen from-state's token is ABSENT from its key's literal template, so the token's presence in
// the message proves :state was interpolated — not merely that the copy spells a similar word.

it('rejects requiring KYC from a non-not_required state, naming the offending state', function () {
    $exception = IllegalKycTransition::cannotRequire(KycStatus::Verified);

    expect($exception)->toBeInstanceOf(IllegalKycTransition::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('verified');
});

it('rejects verifying KYC from a non-pending state, naming the offending state', function () {
    $exception = IllegalKycTransition::cannotVerify(KycStatus::Rejected);

    expect($exception)->toBeInstanceOf(IllegalKycTransition::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('rejected');
});

it('rejects rejecting KYC from a non-pending state, naming the offending state', function () {
    $exception = IllegalKycTransition::cannotReject(KycStatus::Verified);

    expect($exception)->toBeInstanceOf(IllegalKycTransition::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('verified');
});

it('rejects waiving KYC that is already not_required, naming the offending state', function () {
    $exception = IllegalKycTransition::cannotWaive(KycStatus::NotRequired);

    expect($exception)->toBeInstanceOf(IllegalKycTransition::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('not_required');
});

it('rejects an onboarding screening on an already-screened Customer, naming the rule', function () {
    $exception = IllegalSanctionsTransition::onboardingAlreadyScreened();

    expect($exception)->toBeInstanceOf(IllegalSanctionsTransition::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('already');
});

it('rejects resolving a screening from a non-under_review state, naming the offending state', function () {
    $exception = IllegalSanctionsTransition::cannotResolve(SanctionsStatus::Pending);

    expect($exception)->toBeInstanceOf(IllegalSanctionsTransition::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('pending');
});

it('resolves every new state-bearing compliance lang key with the :state placeholder wired', function (string $key) {
    // 'suspended' is a Customer-status token, deliberately ABSENT from every KYC/sanctions template, so
    // its presence proves :state was interpolated; a missing key would make Laravel echo the key back.
    $resolved = __($key, ['state' => 'suspended']);

    expect($resolved)->not->toBe($key)
        ->and($resolved)->toContain('suspended');
})->with([
    'parties.kyc.cannot_require',
    'parties.kyc.cannot_verify',
    'parties.kyc.cannot_reject',
    'parties.kyc.cannot_waive',
    'parties.sanctions.cannot_resolve',
]);

it('resolves the onboarding-already-screened lang key (no placeholder)', function () {
    $resolved = __('parties.sanctions.onboarding_already_screened');

    expect($resolved)->not->toBe('parties.sanctions.onboarding_already_screened')
        ->and($resolved)->toContain('already');
});

it('preserves the pre-existing parties lang groups', function () {
    // The kyc + sanctions groups are ADDED alongside the parties-core / producer-lifecycle groups — not a
    // rewrite; the pre-existing keys must still resolve (acceptance: existing groups preserved).
    expect(__('parties.producer.cannot_activate', ['state' => 'retired']))
        ->not->toBe('parties.producer.cannot_activate')
        ->toContain('retired');

    expect(__('parties.club.cannot_sunset', ['state' => 'closed']))
        ->not->toBe('parties.club.cannot_sunset')
        ->toContain('closed');

    expect(__('parties.producer_agreement.cannot_terminate', ['state' => 'draft']))
        ->not->toBe('parties.producer_agreement.cannot_terminate')
        ->toContain('draft');

    expect(__('parties.club.missing_producer', ['producer' => 7]))
        ->not->toBe('parties.club.missing_producer')
        ->toContain('7');

    expect(__('parties.customer.duplicate_email'))
        ->not->toBe('parties.customer.duplicate_email');

    expect(__('parties.profile.duplicate_for_club', ['customer' => 1, 'club' => 2]))
        ->not->toBe('parties.profile.duplicate_for_club');
});
