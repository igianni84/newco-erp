<?php

use App\Modules\Parties\Enums\AccountStatus;
use App\Modules\Parties\Enums\AccountType;
use App\Modules\Parties\Enums\ClubRegistrationFlowType;
use App\Modules\Parties\Enums\ClubStatus;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\PartyType;
use App\Modules\Parties\Enums\ProducerAgreementStatus;
use App\Modules\Parties\Enums\ProducerStatus;
use App\Modules\Parties\Enums\ProfileState;

// Pins the Parties enums (parties-core, tasks 1.2 + 1.3; design D1/D2/D8).
// Identity & account (1.2): PartyType is the immutable party-type marker carrying the
// full BR-K-Identity-5 domain (Module K PRD § 14.1) though only customer/supplier are
// produced this slice; CustomerStatus/AccountStatus are the verbatim state domains
// (§ 4.1 / § 4.7) stored now with no transition until parties-membership-lifecycle;
// AccountType is the personal-only-at-launch classifier (DEC-068).
// Registry & membership (1.3): ProducerStatus (§ 4.4), ClubStatus (§ 4.3) and
// ProducerAgreementStatus (§ 4.6.1) are the verbatim status lifecycles; ProfileState is
// the nine-state § 4.2.1 membership machine whose terminal set {rejected, cancelled,
// inactive} the D8 partial-unique index excludes; ClubRegistrationFlowType is the
// four-flow § 4.3 registration classifier.
// Each case/value map is asserted verbatim and order-sensitive, mirroring the Catalog
// EnumsTest: any drift in a case or its persisted token must fail here first.

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

it('backs ProducerStatus with the three spec Producer states', function () {
    $values = [];

    foreach (ProducerStatus::cases() as $state) {
        $values[$state->name] = $state->value;
    }

    expect($values)->toBe([
        'Draft' => 'draft',
        'Active' => 'active',
        'Retired' => 'retired',
    ]);

    expect(ProducerStatus::cases())->toHaveCount(3);
});

it('backs ClubStatus with the three spec Club states', function () {
    $values = [];

    foreach (ClubStatus::cases() as $state) {
        $values[$state->name] = $state->value;
    }

    expect($values)->toBe([
        'Active' => 'active',
        'Sunset' => 'sunset',
        'Closed' => 'closed',
    ]);

    expect(ClubStatus::cases())->toHaveCount(3);
});

it('backs ClubRegistrationFlowType with the four spec registration flows', function () {
    $values = [];

    foreach (ClubRegistrationFlowType::cases() as $flow) {
        $values[$flow->name] = $flow->value;
    }

    expect($values)->toBe([
        'OpenRegistration' => 'open_registration',
        'ApplicationWithApproval' => 'application_with_approval',
        'InvitationOnly' => 'invitation_only',
        'LinkOnboarding' => 'link_onboarding',
    ]);

    expect(ClubRegistrationFlowType::cases())->toHaveCount(4);
});

it('backs ProducerAgreementStatus with the four spec agreement states', function () {
    $values = [];

    foreach (ProducerAgreementStatus::cases() as $state) {
        $values[$state->name] = $state->value;
    }

    expect($values)->toBe([
        'Draft' => 'draft',
        'Active' => 'active',
        'Superseded' => 'superseded',
        'Terminated' => 'terminated',
    ]);

    expect(ProducerAgreementStatus::cases())->toHaveCount(4);
});

it('backs ProfileState with the nine spec Profile states', function () {
    $values = [];

    foreach (ProfileState::cases() as $state) {
        $values[$state->name] = $state->value;
    }

    expect($values)->toBe([
        'Applied' => 'applied',
        'WaitingList' => 'waiting_list',
        'Approved' => 'approved',
        'Rejected' => 'rejected',
        'Active' => 'active',
        'Suspended' => 'suspended',
        'Lapsed' => 'lapsed',
        'Cancelled' => 'cancelled',
        'Inactive' => 'inactive',
    ]);

    // The nine-state § 4.2.1 machine — a state added or dropped must be deliberate.
    expect(ProfileState::cases())->toHaveCount(9);
});

it('exposes the three terminal Profile states the D8 partial-unique index excludes', function () {
    // The partial unique index on parties_profiles (design D8) excludes exactly
    // these terminal states: (customer_id, club_id) WHERE state NOT IN
    // ('rejected','cancelled','inactive'). Renaming any of these tokens must break
    // here in lockstep with the index predicate it backs.
    $terminal = [
        ProfileState::Rejected->value,
        ProfileState::Cancelled->value,
        ProfileState::Inactive->value,
    ];

    expect($terminal)->toBe(['rejected', 'cancelled', 'inactive']);
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

it('rejects a producer status outside the spec domain', function () {
    expect(fn () => ProducerStatus::from('archived'))->toThrow(ValueError::class);
});

it('rejects a club status outside the spec domain', function () {
    expect(fn () => ClubStatus::from('paused'))->toThrow(ValueError::class);
});

it('rejects a registration flow type outside the launch set', function () {
    expect(fn () => ClubRegistrationFlowType::from('referral_only'))->toThrow(ValueError::class);
});

it('rejects a producer-agreement status outside the spec domain', function () {
    expect(fn () => ProducerAgreementStatus::from('expired'))->toThrow(ValueError::class);
});

it('rejects a profile state outside the spec domain', function () {
    expect(fn () => ProfileState::from('paused'))->toThrow(ValueError::class);
});
