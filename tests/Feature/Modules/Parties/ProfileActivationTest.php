<?php

use App\Modules\Parties\Actions\ActivateProfile;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\ProfileActivated;
use App\Modules\Parties\Exceptions\IllegalProfileTransition;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the Profile activation transition (parties-membership-activation; design L4/L5/L7/L8; party-registry —
 * Requirements: Profile Activation, Demand-Side Activation Events). It drives the REAL {@see ActivateProfile} Action
 * and asserts the emergent contract:
 *   - `approved → active` is the SOLE writer of that transition and records exactly one ROOT {@see ProfileActivated}
 *     (the only Profile lifecycle event this slice records — approve/decline are audit-only, § 15.2 names no
 *     `ProfileApproved` / `ProfileRejected` — design L2);
 *   - the transition is from-state guarded: a call from any non-`approved` state (e.g. `applied`, already `active`,
 *     terminal `rejected`) throws {@see IllegalProfileTransition} before any write and the transaction rolls back
 *     (no state change, no event);
 *   - the `MembershipFeePaid` trigger is a DEFERRED MODULE-E SEAM (design L5): `ActivateProfile` is the within-module
 *     writer (the free-club / operator / test path); no Module-E event contract is fabricated and no listener wires
 *     the transition to a fee-paid signal (zero-invention — § 15.2: Module K *consumes* E's event).
 *
 * RefreshDatabase per the directory convention; the Action opens its OWN DB::transaction, so the recorder's
 * `transactionLevel() === 0` guard is satisfied by the savepoint under the wrapper (the event being recorded at all
 * is proof of the in-transaction wiring). Events are asserted BY NAME and the payload BY KEY — never a byte-compare
 * of stored jsonb (PG reorders keys — knowledge/testing trap 3) — so the file holds on PostgreSQL 17.
 */
uses(RefreshDatabase::class);

it('activates an approved Profile and records exactly one root ProfileActivated', function () {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    $profile = Profile::factory()->create([
        'customer_id' => $customer->id,
        'club_id' => $club->id,
        'state' => ProfileState::Approved,
    ]);

    $returned = app(ActivateProfile::class)->handle($profile->id);

    // The Profile transitions to `active` (returned model + the persisted row).
    expect($returned->state)->toBe(ProfileState::Active)
        ->and(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Active);

    // Exactly one domain event total — the factory bypasses the Create*/Approve actions and records nothing, so the
    // only event is the ProfileActivated from this transition (no ProfileApproved exists — § 15.2).
    expect(DomainEvent::query()->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', ProfileActivated::NAME)->count())->toBe(1);

    $event = DomainEvent::query()->where('name', ProfileActivated::NAME)->sole();

    expect($event->module)->toBe('parties')                     // Module::Parties->value
        ->and($event->entity_type)->toBe('Profile')             // the activation is a Profile-state event
        ->and($event->entity_id)->toBe((string) $profile->id)   // envelope entity_id is a string
        ->and($event->actor_role)->toBe(ActorRole::System);     // the ActorContext seam default

    // Payload asserted BY KEY (knowledge/testing trap 3 — never byte-compare PG jsonb): the {profile_id, state}
    // shape, pinned so the PII-free contract cannot silently widen. `profile_id` decodes from jsonb as a reliable
    // PHP int (trap 3) → `toBe`; `state` is the post-transition business enum value.
    expect(array_keys($event->payload))->toEqualCanonicalizing(['profile_id', 'state']);
    expect($event->payload['profile_id'])->toBe($profile->id)
        ->and($event->payload['state'])->toBe('active');

    // PII-free: no name/email/phone/DOB leaks into the 10-year audit store.
    expect($event->payload)->not->toHaveKey('name')
        ->and($event->payload)->not->toHaveKey('email');

    // The activation is a ROOT event: it records no parent in its transaction.
    expect($event->causation_id)->toBeNull()
        ->and($event->correlation_id)->toBe($event->event_id);
});

it('rejects activating a Profile not in approved, leaving it unchanged with no event', function (ProfileState $state) {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    $profile = Profile::factory()->create(['customer_id' => $customer->id, 'club_id' => $club->id, 'state' => $state]);

    expect(fn () => app(ActivateProfile::class)->handle($profile->id))
        ->toThrow(IllegalProfileTransition::class);

    // The from-state guard fires before any write and the transaction rolls back: the state is unchanged and no
    // event was recorded.
    expect(Profile::findOrFail($profile->id)->state)->toBe($state)
        ->and(DomainEvent::query()->where('name', ProfileActivated::NAME)->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'applied' => [ProfileState::Applied],     // not yet approved
    'active' => [ProfileState::Active],        // already activated — no re-activation
    'rejected' => [ProfileState::Rejected],    // terminal-for-this-application
]);

it('ships ActivateProfile as the within-module writer with the MembershipFeePaid trigger a deferred Module-E seam — no fabricated event class or listener', function () {
    // The within-module writer exists (the free-club / operator / test path drives the transition directly).
    expect(class_exists(ActivateProfile::class))->toBeTrue();

    // Zero-invention (design L5; § 15.2 — Module K *consumes* Module E's event, it does not define it): no
    // `MembershipFeePaid` event contract is fabricated in either the (unbuilt) Finance module or Parties.
    expect(class_exists('App\\Modules\\Finance\\Events\\MembershipFeePaid'))->toBeFalse()
        ->and(class_exists('App\\Modules\\Parties\\Events\\MembershipFeePaid'))->toBeFalse();

    // No Parties Event FILE is named MembershipFeePaid (assert on the file name, not contents — the Action/event
    // docblocks legitimately *mention* the seam in prose). A listener could only type-hint an event class that does
    // not exist, so the absent contract above also forecloses a listener: this slice wires no fee-paid trigger.
    $eventNames = array_map(
        static fn (string $file): string => basename($file, '.php'),
        glob(app_path('Modules/Parties/Events/*.php')) ?: [],
    );
    expect($eventNames)->not->toBeEmpty()
        ->and($eventNames)->not->toContain('MembershipFeePaid');
});
