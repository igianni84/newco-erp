<?php

use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\CustomerClosed;
use App\Modules\Parties\Events\CustomerReactivated;
use App\Modules\Parties\Events\CustomerSuspended;
use App\Modules\Parties\Events\ProfileExpired;
use App\Modules\Parties\Events\ProfileInactive;
use App\Modules\Parties\Events\ProfileReactivated;
use App\Modules\Parties\Events\ProfileRenewed;
use App\Modules\Parties\Events\ProfileSuspended;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use Tests\TestCase;

// Pins the eight demand-side STATUS events (parties-membership-suspension task 1.3; design L3/L11; party-registry —
// Requirement: Demand-Side Status Events). Each is the verbatim § 15 name with the `final` NAME / ENTITY_TYPE /
// static payload() shape of the shipped activation events (CustomerActivated / ProfileActivated are the mirror).
// This task ships only the classes — the recording Actions land in tasks 2.x–4.x. The cascade events
// (ProfileSuspended / ProfileReactivated) are root when invoked directly and causation children inside the Customer
// cascade (design L11); the class is identical either way — causation is threaded by the Action, not the payload.
//
// Every payload is PII-free: the Customer carries email/name/phone/date_of_birth, none of which may reach the
// 10-year audit store (decisions/2026-06-12-event-substrate-and-audit-store.md).
//
// Booting the app (TestCase, NO RefreshDatabase) gives the models their enum casts while touching no database: the
// fixtures are built with factory()->make() and EVERY FK overridden to an explicit id, so no nested Customer/Club
// factory resolves and no query runs — the absence of a migrated schema is itself the guard that a query would fail.

uses(TestCase::class);

// An in-memory Customer (never saved — make() runs no query) carrying PII sentinels alongside the post-transition
// status, so the payload assertions can prove no personal field leaks.
$customer = fn (CustomerStatus $status): Customer => Customer::factory()->make([
    'id' => 7,
    'email' => 'collector@example.test',
    'name' => 'Jane Collector',
    'phone' => '+39 02 9999999',
    'status' => $status,
    'originating_club_id' => 999,
]);

// An in-memory Profile (never saved) with explicit FK ids so no nested Customer/Club factory resolves.
$profile = fn (ProfileState $state): Profile => Profile::factory()->make([
    'id' => 11,
    'customer_id' => 7,
    'club_id' => 3,
    'state' => $state,
]);

it('exposes the eight verbatim § 15 status event NAMEs', function () {
    expect(CustomerSuspended::NAME)->toBe('CustomerSuspended')
        ->and(CustomerReactivated::NAME)->toBe('CustomerReactivated')
        ->and(CustomerClosed::NAME)->toBe('CustomerClosed')
        ->and(ProfileSuspended::NAME)->toBe('ProfileSuspended')
        ->and(ProfileReactivated::NAME)->toBe('ProfileReactivated')
        ->and(ProfileExpired::NAME)->toBe('ProfileExpired')
        ->and(ProfileRenewed::NAME)->toBe('ProfileRenewed')
        ->and(ProfileInactive::NAME)->toBe('ProfileInactive');
});

it('declares the spec entity types — three Customer, five Profile', function () {
    expect(CustomerSuspended::ENTITY_TYPE)->toBe('Customer')
        ->and(CustomerReactivated::ENTITY_TYPE)->toBe('Customer')
        ->and(CustomerClosed::ENTITY_TYPE)->toBe('Customer')
        ->and(ProfileSuspended::ENTITY_TYPE)->toBe('Profile')
        ->and(ProfileReactivated::ENTITY_TYPE)->toBe('Profile')
        ->and(ProfileExpired::ENTITY_TYPE)->toBe('Profile')
        ->and(ProfileRenewed::ENTITY_TYPE)->toBe('Profile')
        ->and(ProfileInactive::ENTITY_TYPE)->toBe('Profile');
});

it('declares each status event a final class', function () {
    expect((new ReflectionClass(CustomerSuspended::class))->isFinal())->toBeTrue()
        ->and((new ReflectionClass(CustomerReactivated::class))->isFinal())->toBeTrue()
        ->and((new ReflectionClass(CustomerClosed::class))->isFinal())->toBeTrue()
        ->and((new ReflectionClass(ProfileSuspended::class))->isFinal())->toBeTrue()
        ->and((new ReflectionClass(ProfileReactivated::class))->isFinal())->toBeTrue()
        ->and((new ReflectionClass(ProfileExpired::class))->isFinal())->toBeTrue()
        ->and((new ReflectionClass(ProfileRenewed::class))->isFinal())->toBeTrue()
        ->and((new ReflectionClass(ProfileInactive::class))->isFinal())->toBeTrue();
});

it('snapshots the PII-free {customer_id, status} payload for CustomerSuspended', function () use ($customer) {
    $payload = CustomerSuspended::payload($customer(CustomerStatus::Suspended));

    expect(array_keys($payload))->toBe(['customer_id', 'status'])
        ->and($payload)->toBe([
            'customer_id' => 7,
            'status' => 'suspended',
        ])
        ->and($payload)->not->toHaveKey('email')
        ->and($payload)->not->toHaveKey('name')
        ->and($payload)->not->toHaveKey('phone')
        ->and($payload)->not->toHaveKey('date_of_birth')
        ->and(array_values($payload))->not->toContain('Jane Collector')
        ->and(array_values($payload))->not->toContain('collector@example.test')
        ->and(array_values($payload))->not->toContain('+39 02 9999999');
});

it('snapshots the {customer_id, status} payload for CustomerReactivated and CustomerClosed', function () use ($customer) {
    $reactivated = CustomerReactivated::payload($customer(CustomerStatus::Active));
    $closed = CustomerClosed::payload($customer(CustomerStatus::Closed));

    expect(array_keys($reactivated))->toBe(['customer_id', 'status'])
        ->and($reactivated)->toBe(['customer_id' => 7, 'status' => 'active'])
        ->and(array_keys($closed))->toBe(['customer_id', 'status'])
        // `closed` is a status, NOT anonymisation — the payload still carries no PII.
        ->and($closed)->toBe(['customer_id' => 7, 'status' => 'closed'])
        ->and(array_values($closed))->not->toContain('Jane Collector')
        ->and(array_values($closed))->not->toContain('collector@example.test');
});

it('snapshots the {profile_id, state} payload for the suspend/restore Profile events', function () use ($profile) {
    $suspended = ProfileSuspended::payload($profile(ProfileState::Suspended));
    $reactivated = ProfileReactivated::payload($profile(ProfileState::Active));

    expect(array_keys($suspended))->toBe(['profile_id', 'state'])
        ->and($suspended)->toBe(['profile_id' => 11, 'state' => 'suspended'])
        ->and(array_keys($reactivated))->toBe(['profile_id', 'state'])
        // ProfileReactivated is the `Suspended → Active` edge — post-transition state is `active` (design L3).
        ->and($reactivated)->toBe(['profile_id' => 11, 'state' => 'active'])
        // a Profile payload carries no customer reference and no PII.
        ->and($suspended)->not->toHaveKey('customer_id')
        ->and($suspended)->not->toHaveKey('email');
});

it('snapshots the lapse/grace naming traps — ProfileExpired carries state `lapsed`, ProfileRenewed `active`', function () use ($profile) {
    // NAMING TRAP (design L3): the lapse EVENT is `ProfileExpired` and the post-transition STATE is `lapsed`
    // (there is no `ProfileLapsed`); the grace restore EVENT is `ProfileRenewed` with state `active`.
    $expired = ProfileExpired::payload($profile(ProfileState::Lapsed));
    $renewed = ProfileRenewed::payload($profile(ProfileState::Active));

    expect(array_keys($expired))->toBe(['profile_id', 'state'])
        ->and($expired)->toBe(['profile_id' => 11, 'state' => 'lapsed'])
        ->and(array_keys($renewed))->toBe(['profile_id', 'state'])
        ->and($renewed)->toBe(['profile_id' => 11, 'state' => 'active']);
});

it('snapshots the {profile_id, state} payload for ProfileInactive', function () use ($profile) {
    $inactive = ProfileInactive::payload($profile(ProfileState::Inactive));

    expect(array_keys($inactive))->toBe(['profile_id', 'state'])
        ->and($inactive)->toBe(['profile_id' => 11, 'state' => 'inactive'])
        ->and($inactive)->not->toHaveKey('customer_id')
        ->and($inactive)->not->toHaveKey('email');
});
