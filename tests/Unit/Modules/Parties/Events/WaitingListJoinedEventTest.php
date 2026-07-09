<?php

use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\WaitingListJoined;
use App\Modules\Parties\Models\Profile;
use Tests\TestCase;

// Pins the Hero-Package waitlist event (parties-hero-package task 1.4; design D7; party-registry — Requirement:
// WaitingList Placement, Conversion and Decline). It is the verbatim § 15.6 name and carries the `final` NAME /
// ENTITY_TYPE / static payload() shape of the shipped Parties events (ProfileActivated / ProfileRenewed are the
// mirrors). This task ships the CLASS only: the two entry writers — CreateProfile (birth) and ApproveProfile
// (divert at capacity) — land in tasks 2.1 / 2.2, and record it as a ROOT event inside their own transaction.
//
// The payload is a superset of the sibling transition payloads: {profile_id, customer_id, club_id, state}. The
// declared consumer (HubSpot's waitlist-confirmation) has to know WHO joined WHICH Club's waitlist, so the Customer
// and Club are named — BY ID. It stays strict PII-free: the Customer carries email/name/phone/date_of_birth, none of
// which may reach the 10-year audit store, and a Profile is a membership join that carries no personal data at all.
//
// Booting the app (TestCase, NO RefreshDatabase) gives the model its enum cast while touching no database: the
// fixture is built with factory()->make() and EVERY FK overridden to an explicit id, so no nested Customer/Club
// factory resolves and no query runs — the absence of a migrated schema is itself the guard that a query would
// fail loudly.

uses(TestCase::class);

// An in-memory Profile (never saved — make() runs no query) with explicit FK ids, so no nested factory resolves.
$profile = fn (ProfileState $state = ProfileState::WaitingList): Profile => Profile::factory()->make([
    'id' => 11,
    'customer_id' => 7,
    'club_id' => 3,
    'state' => $state,
]);

it('exposes the verbatim § 15.6 event NAME', function () {
    expect(WaitingListJoined::NAME)->toBe('WaitingListJoined');
});

it('declares the Profile ENTITY_TYPE', function () {
    expect(WaitingListJoined::ENTITY_TYPE)->toBe('Profile');
});

it('is a final class', function () {
    expect((new ReflectionClass(WaitingListJoined::class))->isFinal())->toBeTrue();
});

it('snapshots the PII-free {profile_id, customer_id, club_id, state} payload', function () use ($profile) {
    $payload = WaitingListJoined::payload($profile());

    // Exactly the four contract keys, in the spec's order.
    expect(array_keys($payload))->toBe(['profile_id', 'customer_id', 'club_id', 'state'])
        ->and($payload)->toBe([
            'profile_id' => 11,
            'customer_id' => 7,
            'club_id' => 3,
            // The post-write state, as its persisted enum token — never the PascalCase case name.
            'state' => 'waiting_list',
        ])
        // PII-free: the Profile references its parties by id and names no personal field.
        ->and($payload)->not->toHaveKey('email')
        ->and($payload)->not->toHaveKey('name')
        ->and($payload)->not->toHaveKey('phone')
        ->and($payload)->not->toHaveKey('date_of_birth');

    // Defence-in-depth: ids and enum tokens only — nothing in the serialised payload is a string but the state.
    $json = json_encode($payload, JSON_THROW_ON_ERROR);
    expect($json)->toBe('{"profile_id":11,"customer_id":7,"club_id":3,"state":"waiting_list"}');
});

it('reads the state off the Profile rather than hardcoding waiting_list', function () use ($profile) {
    // The payload is a snapshot of the row the writer just wrote — pinning that it never asserts its own trigger.
    // (Both entry writers hand it a `waiting_list` Profile; a divergent state here would be a writer bug, and this
    // event must report it faithfully rather than paper over it.)
    expect(WaitingListJoined::payload($profile(ProfileState::Applied))['state'])->toBe('applied');
});
