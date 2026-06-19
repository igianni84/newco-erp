<?php

use App\Modules\Parties\Enums\AccountStatus;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Exceptions\IllegalAccountTransition;
use App\Modules\Parties\Exceptions\IllegalCustomerTransition;
use App\Modules\Parties\Exceptions\IllegalProfileTransition;
use Tests\TestCase;

// Pins the demand-side STATUS (suspension) transition-guard exceptions (parties-membership-suspension,
// task 1.2; design L4/L5/L7/L8; party-registry — Requirements: Profile Suspension and Restoration,
// Profile Lapse and Grace Renewal, Profile Cancellation and Deactivation, Customer Suspension and
// Closure, Account Status Lifecycle). The suspension Actions (tasks 2.x–4.x) throw these on a disallowed
// from-state; here we assert each named factory builds the right class with a localized reason that NAMES
// the offending state (the `:state` business token) and leaks NO PII. Booting the app (TestCase, NO
// RefreshDatabase — no DB is touched) makes the translator available so __() resolves the
// lang/en/parties.php copy instead of echoing the key back. Sibling: MembershipTransitionExceptionsTest
// (the activation-edge factories on the same two classes).

uses(TestCase::class);

// For each factory the chosen from-state's token is ABSENT from its key's literal template, so the token's
// presence in the resolved message proves :state was interpolated — not that the copy merely spells a
// similar word. The from-state is otherwise arbitrary (this is a unit test of the factory's message, not of
// an Action's guard). The factories' `: self` return type already guarantees the class statically (so one
// runtime instanceof per class is a belt-and-suspenders guard); this helper asserts the message contract:
// non-empty, names the interpolated business :state token, and leaks no PII — no digit (an id/phone/DOB
// would) and no '@' (an email would), the gateNotMet PII discipline of the sibling.
$assertStateRejection = function (RuntimeException $exception, string $expectedToken): void {
    expect($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain($expectedToken);
    expect(preg_match('/\d/', $exception->getMessage()))->toBe(0);
    expect($exception->getMessage())->not->toContain('@');
};

it('builds a localized Profile status-transition rejection naming the offending state, PII-free', function () use ($assertStateRejection) {
    $suspend = IllegalProfileTransition::cannotSuspend(ProfileState::Lapsed);
    expect($suspend)->toBeInstanceOf(IllegalProfileTransition::class);

    $assertStateRejection($suspend, 'lapsed');
    $assertStateRejection(IllegalProfileTransition::cannotReactivate(ProfileState::Lapsed), 'lapsed');
    $assertStateRejection(IllegalProfileTransition::cannotLapse(ProfileState::Suspended), 'suspended');
    // cannotRenew also guards the past-grace case (from-state IS lapsed); here a wrong from-state proves interpolation.
    $assertStateRejection(IllegalProfileTransition::cannotRenew(ProfileState::Suspended), 'suspended');
    $assertStateRejection(IllegalProfileTransition::cannotCancel(ProfileState::Suspended), 'suspended');
    $assertStateRejection(IllegalProfileTransition::cannotDeactivate(ProfileState::Suspended), 'suspended');
});

it('builds a localized Customer status-transition rejection naming the offending state, PII-free', function () use ($assertStateRejection) {
    $suspend = IllegalCustomerTransition::cannotSuspend(CustomerStatus::Closed);
    expect($suspend)->toBeInstanceOf(IllegalCustomerTransition::class);

    $assertStateRejection($suspend, 'closed');
    $assertStateRejection(IllegalCustomerTransition::cannotReactivate(CustomerStatus::Closed), 'closed');
    $assertStateRejection(IllegalCustomerTransition::cannotClose(CustomerStatus::Pending), 'pending');
});

it('builds a localized Account status-transition rejection naming the offending state, PII-free', function () use ($assertStateRejection) {
    // 'closed' is the only Account token absent from each template (active/suspended are named in the rules)
    // and is not a substring of 'closes' — so its presence in the message proves interpolation.
    $suspend = IllegalAccountTransition::cannotSuspend(AccountStatus::Closed);
    expect($suspend)->toBeInstanceOf(IllegalAccountTransition::class);

    $assertStateRejection($suspend, 'closed');
    $assertStateRejection(IllegalAccountTransition::cannotReactivate(AccountStatus::Closed), 'closed');
    $assertStateRejection(IllegalAccountTransition::cannotClose(AccountStatus::Closed), 'closed');
});

it('exposes IllegalAccountTransition as a RuntimeException subclass', function () {
    // Reflection (not is_subclass_of/instanceof) so the parent assertion is a real runtime check, not one
    // PHPStan constant-folds; the ReflectionClass constructor also proves the class exists at runtime.
    $reflection = new ReflectionClass(IllegalAccountTransition::class);

    expect($reflection->isSubclassOf(RuntimeException::class))->toBeTrue();
});

it('resolves every new status-transition lang key with the :state placeholder wired', function (string $key) {
    // 'retired' is a Producer-status token, deliberately ABSENT from every status-transition template, so its
    // presence proves :state was interpolated; a missing key would make Laravel echo the key back.
    $resolved = __($key, ['state' => 'retired']);

    expect($resolved)->not->toBe($key)
        ->and($resolved)->toContain('retired');
})->with([
    'parties.profile.cannot_suspend',
    'parties.profile.cannot_reactivate',
    'parties.profile.cannot_lapse',
    'parties.profile.cannot_renew',
    'parties.profile.cannot_cancel',
    'parties.profile.cannot_deactivate',
    'parties.customer.cannot_suspend',
    'parties.customer.cannot_reactivate',
    'parties.customer.cannot_close',
    'parties.account.cannot_suspend',
    'parties.account.cannot_reactivate',
    'parties.account.cannot_close',
]);

it('resolves the acceptance-cited keys with their documented tokens', function () {
    expect(__('parties.profile.cannot_suspend', ['state' => 'lapsed']))->toContain('lapsed');
    expect(__('parties.customer.cannot_close', ['state' => 'pending']))->toContain('pending');
    expect(__('parties.account.cannot_suspend', ['state' => 'closed']))->toContain('closed');
});

it('preserves the pre-existing parties lang keys and groups', function () {
    // The status keys are ADDED alongside the existing groups — not a rewrite; pre-existing keys must still
    // resolve (acceptance: existing groups/keys preserved). cannot_activate and the hold group are named by
    // the task acceptance.
    expect(__('parties.profile.cannot_activate', ['state' => 'applied']))
        ->not->toBe('parties.profile.cannot_activate')
        ->toContain('applied');

    expect(__('parties.hold.cannot_lift_not_active', ['state' => 'lifted']))
        ->not->toBe('parties.hold.cannot_lift_not_active')
        ->toContain('lifted');

    expect(__('parties.customer.gate_not_met'))
        ->not->toBe('parties.customer.gate_not_met');

    expect(__('parties.producer.cannot_activate', ['state' => 'retired']))
        ->not->toBe('parties.producer.cannot_activate')
        ->toContain('retired');
});
