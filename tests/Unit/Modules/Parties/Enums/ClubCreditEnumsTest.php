<?php

use App\Modules\Parties\Enums\ClubCreditState;

// Pins the club-credit enum (club-credit, task 1.1; design L1/L4/L5).
// ClubCreditState is the § 11 three-state Club Credit FSM `active → redeemed | forfeited`
// carrying two predicates: isActive() (the from-state of every value-moving transition)
// and isTerminal() (absolutely terminal ≡ forfeited only — redeemed is restore-reachable).
// The case/value map is asserted verbatim and order-sensitive, mirroring the parties-core
// EnumsTest / ComplianceEnumsTest: any drift in a case or its persisted token must fail here
// first.

it('backs ClubCreditState with the three spec Club Credit states', function () {
    $values = [];

    foreach (ClubCreditState::cases() as $state) {
        $values[$state->name] = $state->value;
    }

    expect($values)->toBe([
        'Active' => 'active',
        'Redeemed' => 'redeemed',
        'Forfeited' => 'forfeited',
    ]);

    expect(ClubCreditState::cases())->toHaveCount(3);
});

it('round-trips the spec tokens through from()', function () {
    expect(ClubCreditState::from('active'))->toBe(ClubCreditState::Active);
    expect(ClubCreditState::from('redeemed'))->toBe(ClubCreditState::Redeemed);
    expect(ClubCreditState::from('forfeited'))->toBe(ClubCreditState::Forfeited);
});

it('treats only active as the live, value-mutable from-state', function () {
    // isActive() is the from-state guard of ApplyClubCredit + ForfeitClubCredit (design L4/L6).
    expect(ClubCreditState::Active->isActive())->toBeTrue();
    expect(ClubCreditState::Redeemed->isActive())->toBeFalse();
    expect(ClubCreditState::Forfeited->isActive())->toBeFalse();
});

it('treats only forfeited as absolutely terminal (redeemed is restore-reachable)', function () {
    // The terminality nuance (§ 11.3 / design L5): forfeited has no outgoing edge, but
    // redeemed → active is reachable via RestoreClubCredit, so redeemed is NOT terminal.
    expect(ClubCreditState::Forfeited->isTerminal())->toBeTrue();
    expect(ClubCreditState::Redeemed->isTerminal())->toBeFalse();
    expect(ClubCreditState::Active->isTerminal())->toBeFalse();
});

it('rejects a club credit state outside the spec domain', function () {
    // `expired`/`cancelled` are not Club Credit states — the FSM is active|redeemed|forfeited.
    expect(fn () => ClubCreditState::from('expired'))->toThrow(ValueError::class);
});
