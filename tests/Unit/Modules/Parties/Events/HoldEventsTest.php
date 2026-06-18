<?php

use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Events\CustomerHoldLifted;
use App\Modules\Parties\Events\CustomerHoldPlaced;
use App\Modules\Parties\Models\Hold;
use Tests\TestCase;

// Pins the two Hold events (parties-holds task 2.1; design L4; party-registry — Requirement: Hold Events). Each is
// the verbatim § 15.1 name with the `final` NAME / ENTITY_TYPE / static payload() shape of the shipped Parties
// events (CustomerOnboardingScreeningPassed is the mirror). The PRD catalog names ONLY these two — no
// ProfileHold*/AccountHold* — so both are recorded for a Hold of any scope, the scope carried in the payload. The
// payload is structural: ids + enum `->value` tokens + a controlled business reason, PII-free (the Hold carries no
// personal field, and none may reach the 10-year audit store). ENTITY_TYPE is `Hold`, not the scoped entity.
//
// Booting the app (TestCase, NO RefreshDatabase) gives the model its enum casts while touching no database: the
// fixture is built with factory()->make(), which never persists or queries.

uses(TestCase::class);

// An in-memory Hold (never saved — make() runs no query) on a Profile scope with deterministic id / scope / both
// reasons, so the payload assertions snapshot an exact, stable shape and prove the scope rides in the payload.
$hold = fn (): Hold => Hold::factory()->make([
    'id' => 42,
    'hold_type' => HoldType::Fraud,
    'scope_type' => HoldScope::Profile,
    'scope_id' => 7,
    'reason' => 'manual fraud review',
    'lift_reason' => 'review cleared',
]);

it('exposes the two verbatim § 15.1 Hold event NAMEs', function () {
    expect(CustomerHoldPlaced::NAME)->toBe('CustomerHoldPlaced')
        ->and(CustomerHoldLifted::NAME)->toBe('CustomerHoldLifted');
});

it('declares the Hold ENTITY_TYPE on each Hold event', function () {
    expect(CustomerHoldPlaced::ENTITY_TYPE)->toBe('Hold')
        ->and(CustomerHoldLifted::ENTITY_TYPE)->toBe('Hold');
});

it('declares each Hold event a final class', function () {
    expect((new ReflectionClass(CustomerHoldPlaced::class))->isFinal())->toBeTrue()
        ->and((new ReflectionClass(CustomerHoldLifted::class))->isFinal())->toBeTrue();
});

it('snapshots the PII-free placement payload for CustomerHoldPlaced', function () use ($hold) {
    $payload = CustomerHoldPlaced::payload($hold());

    expect(array_keys($payload))->toBe(['hold_id', 'hold_type', 'scope_type', 'scope_id', 'reason'])
        ->and($payload)->toBe([
            'hold_id' => 42,
            'hold_type' => 'fraud',
            'scope_type' => 'profile',
            'scope_id' => 7,
            'reason' => 'manual fraud review',
        ])
        ->and($payload)->not->toHaveKey('lift_reason')
        ->and($payload)->not->toHaveKey('email')
        ->and($payload)->not->toHaveKey('name')
        ->and($payload)->not->toHaveKey('phone')
        ->and($payload)->not->toHaveKey('date_of_birth');
});

it('snapshots the PII-free lift payload for CustomerHoldLifted', function () use ($hold) {
    $payload = CustomerHoldLifted::payload($hold());

    expect(array_keys($payload))->toBe(['hold_id', 'hold_type', 'scope_type', 'scope_id', 'lift_reason'])
        ->and($payload)->toBe([
            'hold_id' => 42,
            'hold_type' => 'fraud',
            'scope_type' => 'profile',
            'scope_id' => 7,
            'lift_reason' => 'review cleared',
        ])
        ->and($payload)->not->toHaveKey('reason')
        ->and($payload)->not->toHaveKey('email')
        ->and($payload)->not->toHaveKey('name')
        ->and($payload)->not->toHaveKey('phone')
        ->and($payload)->not->toHaveKey('date_of_birth');
});

it('carries a null reason in the placement payload for a system-placed Hold', function () {
    // The auto `kyc` Hold path (task 4.1): system-placed Holds carry reason = null (design L5 — the type is the
    // reason), so the placement payload reads `reason => null` while the structural keys stay intact.
    $hold = Hold::factory()->make([
        'id' => 1,
        'hold_type' => HoldType::Kyc,
        'scope_type' => HoldScope::Customer,
        'scope_id' => 99,
        'reason' => null,
    ]);

    expect(CustomerHoldPlaced::payload($hold))->toBe([
        'hold_id' => 1,
        'hold_type' => 'kyc',
        'scope_type' => 'customer',
        'scope_id' => 99,
        'reason' => null,
    ]);
});
