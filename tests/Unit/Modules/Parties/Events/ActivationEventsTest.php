<?php

use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\CustomerActivated;
use App\Modules\Parties\Events\OriginatingClubLocked;
use App\Modules\Parties\Events\ProfileActivated;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use Carbon\CarbonImmutable;
use Tests\TestCase;

// Pins the three demand-side activation events (parties-membership-activation task 1.3; design L9; party-registry —
// Requirement: Demand-Side Activation Events). Each is the verbatim § 15 name with the `final` NAME / ENTITY_TYPE /
// static payload() shape of the shipped Parties events (ProducerActivated is the mirror). All three are ROOT events
// recorded transactionally by the activation Actions (tasks 2.1–2.3, not yet shipped — this task ships only the
// classes). Every payload is PII-free: the Customer carries email/name/phone/date_of_birth, none of which may reach
// the 10-year audit store.
//
// Booting the app (TestCase, NO RefreshDatabase) gives the models their enum casts while touching no database: the
// fixtures are built with factory()->make() and EVERY FK overridden to an explicit id, so no nested Customer/Club
// factory resolves and no query runs — the absence of a migrated schema is itself the guard that a query would
// fail loudly.

uses(TestCase::class);

// Freeze the clock so OriginatingClubLocked's `locked_at` (CarbonImmutable::now()) snapshots an exact value; reset
// after each test so the global test-now never leaks (the SweepTest idiom).
afterEach(fn () => CarbonImmutable::setTestNow());

// An in-memory Customer (never saved — make() runs no query) carrying PII sentinels alongside the post-transition
// status, so the payload assertions can prove no personal field leaks. `originating_club_id` is set to a value
// DIFFERENT from the triggering Profile's club, to pin that OriginatingClubLocked reads the triggering Profile's
// club_id — not the Customer's own originating_club_id.
$customer = fn (CustomerStatus $status = CustomerStatus::Active): Customer => Customer::factory()->make([
    'id' => 7,
    'email' => 'collector@example.test',
    'name' => 'Jane Collector',
    'phone' => '+39 02 9999999',
    'status' => $status,
    'originating_club_id' => 999,
]);

// An in-memory triggering Profile (never saved) with explicit FK ids so no nested Customer/Club factory resolves.
$profile = fn (ProfileState $state = ProfileState::Active): Profile => Profile::factory()->make([
    'id' => 11,
    'customer_id' => 7,
    'club_id' => 3,
    'state' => $state,
]);

it('exposes the three verbatim § 15 activation event NAMEs', function () {
    expect(CustomerActivated::NAME)->toBe('CustomerActivated')
        ->and(ProfileActivated::NAME)->toBe('ProfileActivated')
        ->and(OriginatingClubLocked::NAME)->toBe('OriginatingClubLocked');
});

it('declares the spec entity types — Customer / Profile / Customer', function () {
    expect(CustomerActivated::ENTITY_TYPE)->toBe('Customer')
        ->and(ProfileActivated::ENTITY_TYPE)->toBe('Profile')
        ->and(OriginatingClubLocked::ENTITY_TYPE)->toBe('Customer');
});

it('declares each activation event a final class', function () {
    expect((new ReflectionClass(CustomerActivated::class))->isFinal())->toBeTrue()
        ->and((new ReflectionClass(ProfileActivated::class))->isFinal())->toBeTrue()
        ->and((new ReflectionClass(OriginatingClubLocked::class))->isFinal())->toBeTrue();
});

it('snapshots the PII-free {customer_id, status} payload for CustomerActivated', function () use ($customer) {
    $payload = CustomerActivated::payload($customer(CustomerStatus::Active));

    expect(array_keys($payload))->toBe(['customer_id', 'status'])
        ->and($payload)->toBe([
            'customer_id' => 7,
            'status' => 'active',
        ])
        ->and($payload)->not->toHaveKey('email')
        ->and($payload)->not->toHaveKey('name')
        ->and($payload)->not->toHaveKey('phone')
        ->and($payload)->not->toHaveKey('date_of_birth')
        ->and(array_values($payload))->not->toContain('Jane Collector')
        ->and(array_values($payload))->not->toContain('collector@example.test')
        ->and(array_values($payload))->not->toContain('+39 02 9999999');
});

it('snapshots the {profile_id, state} payload for ProfileActivated', function () use ($profile) {
    $payload = ProfileActivated::payload($profile(ProfileState::Active));

    expect(array_keys($payload))->toBe(['profile_id', 'state'])
        ->and($payload)->toBe([
            'profile_id' => 11,
            'state' => 'active',
        ])
        // the activation payload is the Profile id + state only — it carries no customer reference, no PII.
        ->and($payload)->not->toHaveKey('customer_id')
        ->and($payload)->not->toHaveKey('email')
        ->and($payload)->not->toHaveKey('name');
});

it('snapshots the PII-free § 6.1 payload for OriginatingClubLocked at the frozen moment', function () use ($customer, $profile) {
    $moment = CarbonImmutable::parse('2026-06-18T10:30:00+00:00');
    CarbonImmutable::setTestNow($moment);

    $payload = OriginatingClubLocked::payload($customer(), $profile());

    expect(array_keys($payload))->toBe(['customer_id', 'club_id', 'profile_id', 'locked_at'])
        ->and($payload)->toBe([
            'customer_id' => 7,
            // the TRIGGERING Profile's club (3), NOT the Customer's originating_club_id (999) — pins the source.
            'club_id' => 3,
            'profile_id' => 11,
            'locked_at' => $moment->toIso8601String(),
        ])
        ->and($payload)->not->toHaveKey('email')
        ->and($payload)->not->toHaveKey('name')
        ->and($payload)->not->toHaveKey('phone')
        ->and($payload)->not->toHaveKey('date_of_birth')
        ->and(array_values($payload))->not->toContain('Jane Collector')
        ->and(array_values($payload))->not->toContain('collector@example.test')
        ->and(array_values($payload))->not->toContain('+39 02 9999999');
});
