<?php

use App\Modules\Parties\Enums\AccountStatus;
use App\Modules\Parties\Enums\AccountType;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\PartyType;

// Pins the Parties identity & account enums (parties-core, task 1.2; design D1/D2).
// PartyType is the immutable party-type marker carrying the full BR-K-Identity-5
// domain (Module K PRD § 14.1) though only customer/supplier are produced this
// slice; CustomerStatus/AccountStatus are the verbatim state domains (§ 4.1 / § 4.7)
// stored now with no transition until parties-membership-lifecycle; AccountType is
// the personal-only-at-launch classifier (DEC-068). Each case/value map is asserted
// verbatim and order-sensitive, mirroring the Catalog EnumsTest: any drift in a case
// or its persisted token must fail here first.

it('backs PartyType with the full BR-K-Identity-5 marker domain', function () {
    $values = [];

    foreach (PartyType::cases() as $marker) {
        $values[$marker->name] = $marker->value;
    }

    expect($values)->toBe([
        'Customer' => 'customer',
        'Supplier' => 'supplier',
        'ThirdPartyOwner' => 'third_party_owner',
    ]);

    // All three markers are declared now (DEC-067) so a future party-registry
    // slice needs no enum migration, even though third_party_owner has no Party
    // entity in this change.
    expect(PartyType::cases())->toHaveCount(3);
});

it('backs CustomerStatus with the four spec Customer states', function () {
    $values = [];

    foreach (CustomerStatus::cases() as $state) {
        $values[$state->name] = $state->value;
    }

    expect($values)->toBe([
        'Pending' => 'pending',
        'Active' => 'active',
        'Suspended' => 'suspended',
        'Closed' => 'closed',
    ]);

    expect(CustomerStatus::cases())->toHaveCount(4);
});

it('backs AccountStatus with the three spec Account states', function () {
    $values = [];

    foreach (AccountStatus::cases() as $state) {
        $values[$state->name] = $state->value;
    }

    expect($values)->toBe([
        'Active' => 'active',
        'Suspended' => 'suspended',
        'Closed' => 'closed',
    ]);

    expect(AccountStatus::cases())->toHaveCount(3);
});

it('backs AccountType with personal as the only launch type', function () {
    $values = [];

    foreach (AccountType::cases() as $type) {
        $values[$type->name] = $type->value;
    }

    expect($values)->toBe([
        'Personal' => 'personal',
    ]);

    // The personal-only-at-launch guard (DEC-068): a second account type must be
    // a deliberate change here, never an accident.
    expect(AccountType::cases())->toHaveCount(1);
});

it('rejects a party-type marker outside the BR-K-Identity-5 domain', function () {
    expect(fn () => PartyType::from('producer'))->toThrow(ValueError::class);
});

it('rejects a customer status outside the spec domain', function () {
    expect(fn () => CustomerStatus::from('archived'))->toThrow(ValueError::class);
});

it('rejects an account type outside the launch set', function () {
    expect(fn () => AccountType::from('business'))->toThrow(ValueError::class);
});
