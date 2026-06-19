<?php

use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Exceptions\IllegalCustomerTransition;
use App\Modules\Parties\Exceptions\IllegalProfileTransition;
use Tests\TestCase;

// Pins the two demand-side membership transition-guard exceptions (parties-membership-activation,
// task 1.2; design L2/L4/L6; party-registry — Requirements: Profile Membership Approval, Profile
// Activation, Customer Onboarding Activation). The activation Actions (tasks 2.x) throw these on a
// disallowed call; here we assert each named factory builds the right class with a localized reason
// that names the offending state — except gateNotMet(), which names the rule with NO interpolation
// (the acceptance values are PII). Booting the app (TestCase, NO RefreshDatabase — no DB is touched)
// makes the translator available so __() resolves the lang/en/parties.php copy instead of echoing the
// key back.

uses(TestCase::class);

// Each chosen from-state's token is ABSENT from its key's literal template, so the token's presence in
// the message proves :state was interpolated — not merely that the copy spells a similar word.

it('rejects approving a Profile that is not applied, naming the offending state', function () {
    $exception = IllegalProfileTransition::cannotApprove(ProfileState::Active);

    expect($exception)->toBeInstanceOf(IllegalProfileTransition::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('active');
});

it('rejects declining a Profile that is not applied, naming the offending state', function () {
    $exception = IllegalProfileTransition::cannotReject(ProfileState::Active);

    expect($exception)->toBeInstanceOf(IllegalProfileTransition::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('active');
});

it('rejects activating a Profile that is not approved, naming the offending state', function () {
    // `applied` is absent from the cannot_activate template ("only from approved"), so its presence
    // proves :state was interpolated.
    $exception = IllegalProfileTransition::cannotActivate(ProfileState::Applied);

    expect($exception)->toBeInstanceOf(IllegalProfileTransition::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('applied');
});

it('rejects activating a Customer that is not pending, naming the offending state', function () {
    $exception = IllegalCustomerTransition::cannotActivate(CustomerStatus::Suspended);

    expect($exception)->toBeInstanceOf(IllegalCustomerTransition::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('suspended');
});

it('rejects activating a Customer whose onboarding gate is unmet, naming the rule without any PII value', function () {
    // gateNotMet() interpolates nothing — the gate's offending acceptance values (verification / T&C /
    // privacy timestamps) are PII. The message names the rule ('onboarding') and carries NO digit, so a
    // leaked timestamp/id would fail the no-value assertion.
    $exception = IllegalCustomerTransition::gateNotMet();

    expect($exception)->toBeInstanceOf(IllegalCustomerTransition::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('onboarding');
    expect(preg_match('/\d/', $exception->getMessage()))->toBe(0);
});

it('resolves every new state-bearing membership lang key with the :state placeholder wired', function (string $key) {
    // 'retired' is a Producer-status token, deliberately ABSENT from every approval/activation template, so
    // its presence proves :state was interpolated; a missing key would make Laravel echo the key back.
    $resolved = __($key, ['state' => 'retired']);

    expect($resolved)->not->toBe($key)
        ->and($resolved)->toContain('retired');
})->with([
    'parties.profile.cannot_approve',
    'parties.profile.cannot_reject',
    'parties.profile.cannot_activate',
    'parties.customer.cannot_activate',
]);

it('resolves the onboarding gate-not-met lang key with no placeholder and no value', function () {
    $resolved = __('parties.customer.gate_not_met');

    expect($resolved)->not->toBe('parties.customer.gate_not_met')
        ->and($resolved)->toContain('onboarding');
    expect(preg_match('/\d/', $resolved))->toBe(0);
});

it('preserves the pre-existing parties lang groups', function () {
    // The membership keys are ADDED alongside the parties-core / producer-lifecycle / compliance groups —
    // not a rewrite; the pre-existing keys must still resolve (acceptance: existing groups preserved).
    expect(__('parties.producer.cannot_activate', ['state' => 'retired']))
        ->not->toBe('parties.producer.cannot_activate')
        ->toContain('retired');

    expect(__('parties.kyc.cannot_verify', ['state' => 'rejected']))
        ->not->toBe('parties.kyc.cannot_verify')
        ->toContain('rejected');

    expect(__('parties.customer.duplicate_email'))
        ->not->toBe('parties.customer.duplicate_email');

    expect(__('parties.profile.duplicate_for_club', ['customer' => 1, 'club' => 2]))
        ->not->toBe('parties.profile.duplicate_for_club');
});
