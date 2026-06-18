<?php

use App\Modules\Parties\Enums\HoldStatus;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Exceptions\IllegalHoldLift;
use Tests\TestCase;

// Pins the Hold lift-discipline exception (parties-holds, task 1.3; design L2; party-registry —
// Requirement: Hold Lifecycle and Lift Discipline). The LiftHold operator Action (task 3.2) throws this
// on a rejected lift: an auto-managed (`kyc`/`payment`) Hold type, or a Hold that is not `active`. Here
// we assert each named factory builds the right class with a localized, PII-free reason that names the
// offending type/state. Booting the app (TestCase, NO RefreshDatabase — no DB is touched) makes the
// translator available so __() resolves the lang/en/parties.php copy instead of echoing the key back.

uses(TestCase::class);

// Each chosen token is ABSENT from its key's literal template, so the token's presence in the message
// proves the placeholder was interpolated — not merely that the copy spells a similar word.

it('rejects an operator lift of an auto-managed kyc Hold, naming the offending type', function () {
    $exception = IllegalHoldLift::autoManaged(HoldType::Kyc);

    expect($exception)->toBeInstanceOf(IllegalHoldLift::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('kyc');
});

it('rejects an operator lift of an auto-managed payment Hold, naming the offending type', function () {
    $exception = IllegalHoldLift::autoManaged(HoldType::Payment);

    expect($exception)->toBeInstanceOf(IllegalHoldLift::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('payment');
});

it('rejects lifting a Hold that is not active, naming the offending state', function () {
    $exception = IllegalHoldLift::notActive(HoldStatus::Lifted);

    expect($exception)->toBeInstanceOf(IllegalHoldLift::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('lifted');
});

it('resolves the new hold lang keys with their placeholders wired', function (string $key) {
    // 'compliance' is a Hold-type token absent from both hold templates, so its presence proves the
    // placeholder was interpolated; a missing key would make Laravel echo the key back unchanged. Both
    // placeholder names are supplied — Laravel ignores the one the resolved template does not reference.
    $resolved = __($key, ['type' => 'compliance', 'state' => 'compliance']);

    expect($resolved)->not->toBe($key)
        ->and($resolved)->toContain('compliance');
})->with([
    'parties.hold.cannot_lift_auto_managed',
    'parties.hold.cannot_lift_not_active',
]);

it('preserves the pre-existing parties lang groups', function () {
    // The `hold` group is ADDED alongside the parties-core / lifecycle / compliance groups — not a
    // rewrite; pre-existing keys must still resolve (acceptance: existing groups preserved). 'suspended'
    // is a Customer-status token absent from these templates, so its presence proves :state interpolated.
    expect(__('parties.kyc.cannot_verify', ['state' => 'suspended']))
        ->not->toBe('parties.kyc.cannot_verify')
        ->toContain('suspended');

    expect(__('parties.sanctions.cannot_resolve', ['state' => 'suspended']))
        ->not->toBe('parties.sanctions.cannot_resolve')
        ->toContain('suspended');

    expect(__('parties.producer.cannot_activate', ['state' => 'retired']))
        ->not->toBe('parties.producer.cannot_activate')
        ->toContain('retired');

    expect(__('parties.customer.duplicate_email'))
        ->not->toBe('parties.customer.duplicate_email');
});
