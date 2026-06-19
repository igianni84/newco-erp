<?php

use App\Modules\Parties\Actions\ActivateProfile;
use App\Modules\Parties\Actions\ApproveProfile;
use App\Modules\Parties\Actions\CancelProfile;
use App\Modules\Parties\Actions\CreateProfile;
use App\Modules\Parties\Actions\DeactivateProfile;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\ProfileInactive;
use App\Modules\Parties\Exceptions\DuplicateProfileForClub;
use App\Modules\Parties\Exceptions\IllegalProfileTransition;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the Profile cancel/deactivate terminal pair (parties-membership-suspension, design L1/L2/L4/L9/L10/L11;
 * party-registry — Requirements: Profile Cancellation and Deactivation, Demand-Side Status Events, and the MODIFIED
 * Profile — Multi-Profile Membership). It drives the REAL {@see CancelProfile} / {@see DeactivateProfile} Actions and
 * asserts the emergent contract:
 *   - `active | lapsed → cancelled` ({@see CancelProfile}) is AUDIT-ONLY — it writes `state = cancelled` + the optional
 *     Producer-initiated `cancellation_reason` and records NO domain event (the § 15.2 family names no
 *     `ProfileCancelled` — design L2; the append-only audit trail of the write IS the record). The DELTA across the
 *     call is ZERO events;
 *   - `active → inactive` ({@see DeactivateProfile}) records exactly one ROOT {@see ProfileInactive} ({profile_id,
 *     state}, PII-free) — the contrast with cancellation (deactivation DOES emit its § 15.2 event);
 *   - both `cancelled` and `inactive` are TERMINAL soft-delete states the partial-unique index excludes
 *     (`state NOT IN ('rejected','cancelled','inactive')`), so a terminal Profile does NOT block a fresh `applied`
 *     Profile for the same Customer–Club pair (no index migration) — while a `suspended`/`lapsed` (NON-terminal)
 *     Profile still blocks a second live Profile (the {@see DuplicateProfileForClub} contrast);
 *   - each transition is from-state guarded: a cancel from any non-`active`/`lapsed` state, or a deactivate from any
 *     non-`active` state, throws {@see IllegalProfileTransition} before any write and the transaction rolls back;
 *   - cancellation coins NO `ProfileCancelled` event class (zero-invention — the name is not even recordable).
 *
 * The happy-path Profile is driven to `active` through the GENUINE create → approve → activate Actions via the
 * file-local {@see cancellationActiveProfile()} helper — uniquely named per file (Pest helpers share ONE global
 * namespace, so a sibling's `createActiveProfile()` / `lapseGraceActiveProfile()` is neither redeclared nor relied
 * upon, keeping this file runnable in isolation). The from-state-pinned cases use the factory. RefreshDatabase per the
 * directory convention; each Action opens its OWN DB::transaction, so the recorder's `transactionLevel() === 0` guard
 * is satisfied by the savepoint under the wrapper. Events are asserted BY NAME and payloads BY KEY (never a
 * byte-compare of stored jsonb — PG reorders keys, trap 3); the `cancellation_reason` is an UNCAST plain string
 * (asserted with `->toBe(...)`, not through a cast); payload ids are compared same-source (trap 6) — so the file holds
 * on PostgreSQL 17.
 */
uses(RefreshDatabase::class);

/**
 * Drives a Profile to `active` through the real create → approve → activate Actions (each its own DB::transaction +
 * recorder), exactly as production would, and returns it alongside its Customer + Club (factory models, so their `id`s
 * are clean ints for a re-application). Uniquely named per file (the global-helper-namespace rule) so this file never
 * collides with — nor depends on the load order of — a sibling's `createActiveProfile()` / `lapseGraceActiveProfile()`.
 *
 * @return array{profile: Profile, customer: Customer, club: Club}
 */
function cancellationActiveProfile(): array
{
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();

    $profile = app(CreateProfile::class)->handle($customer->id, $club->id);   // born `applied`
    app(ApproveProfile::class)->handle($profile->id);                          // `applied → approved`
    app(ActivateProfile::class)->handle($profile->id);                         // `approved → active`

    return [
        'profile' => Profile::findOrFail($profile->id),
        'customer' => $customer,
        'club' => $club,
    ];
}

it('cancels an active Profile with a reason, records NO event, and frees the Customer–Club pair for a fresh application', function () {
    ['profile' => $profile, 'customer' => $customer, 'club' => $club] = cancellationActiveProfile();
    expect($profile->state)->toBe(ProfileState::Active)      // precondition: the setup reached `active`
        ->and($profile->cancellation_reason)->toBeNull();    // an active Profile carries no cancellation reason

    // Snapshot the world right before the cancel — AUDIT-ONLY means the DELTA across this one call is ZERO events.
    $eventsBefore = DomainEvent::query()->count();
    $customersBefore = Customer::query()->count();
    $clubsBefore = Club::query()->count();
    $profilesBefore = Profile::query()->count();

    $returned = app(CancelProfile::class)->handle($profile->id, 'producer_offboarding');

    // The Profile transitions to `cancelled` and the Producer-initiated reason is recorded (returned + persisted row).
    $persisted = Profile::findOrFail($profile->id);
    expect($returned->state)->toBe(ProfileState::Cancelled)
        ->and($persisted->state)->toBe(ProfileState::Cancelled)
        ->and($persisted->cancellation_reason)->toBe('producer_offboarding');   // UNCAST plain string

    // AUDIT-ONLY (design L2): the cancel recorded NO event — the catalog names no `ProfileCancelled`. The event log
    // and every other table are unchanged (no row created/mutated besides this Profile's state + reason).
    expect(DomainEvent::query()->count())->toBe($eventsBefore)
        ->and(DomainEvent::query()->where('name', 'ProfileCancelled')->count())->toBe(0)
        ->and(Customer::query()->count())->toBe($customersBefore)
        ->and(Club::query()->count())->toBe($clubsBefore)
        ->and(Profile::query()->count())->toBe($profilesBefore);

    // The Profile's non-lifecycle columns are untouched (same-source ids, trap 6).
    expect($persisted->customer_id)->toBe($profile->customer_id)
        ->and($persisted->club_id)->toBe($profile->club_id);

    // A terminal `cancelled` Profile does NOT block a fresh application for the same Customer–Club pair — the
    // partial-unique index excludes `{rejected, cancelled, inactive}`, so the re-application creates a new row in
    // `applied` with no index migration (the MODIFIED Multi-Profile Membership scenario).
    $fresh = app(CreateProfile::class)->handle($customer->id, $club->id);
    expect($fresh->state)->toBe(ProfileState::Applied)
        ->and($fresh->id)->not->toBe($persisted->id)              // a genuinely new Profile row
        ->and(Profile::query()->count())->toBe($profilesBefore + 1);

    // The cancel recorded none, so the only new event is the fresh create's own ProfileCreated.
    expect(DomainEvent::query()->count())->toBe($eventsBefore + 1);
});

it('cancels a lapsed Profile (the post-grace cancellation path), event-silently', function () {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    $profile = Profile::factory()->create([
        'customer_id' => $customer->id,
        'club_id' => $club->id,
        'state' => ProfileState::Lapsed,
        'lapsed_at' => now()->subDays(31),   // past the 30-day grace — the deferred scheduler's cancel path
    ]);

    $returned = app(CancelProfile::class)->handle($profile->id, 'lapsed_grace_expired');

    $persisted = Profile::findOrFail($profile->id);
    expect($returned->state)->toBe(ProfileState::Cancelled)
        ->and($persisted->state)->toBe(ProfileState::Cancelled)
        ->and($persisted->cancellation_reason)->toBe('lapsed_grace_expired')
        ->and(DomainEvent::query()->count())->toBe(0);   // factory recorded nothing + cancel is audit-only
});

it('cancels with a null reason — the cancellation reason is optional', function () {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    $profile = Profile::factory()->create([
        'customer_id' => $customer->id,
        'club_id' => $club->id,
        'state' => ProfileState::Active,
    ]);

    app(CancelProfile::class)->handle($profile->id);   // no reason argument

    $persisted = Profile::findOrFail($profile->id);
    expect($persisted->state)->toBe(ProfileState::Cancelled)
        ->and($persisted->cancellation_reason)->toBeNull()
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('deactivates an active Profile and records exactly one root ProfileInactive', function () {
    ['profile' => $profile] = cancellationActiveProfile();
    expect($profile->state)->toBe(ProfileState::Active);

    $eventsBefore = DomainEvent::query()->count();
    $profilesBefore = Profile::query()->count();

    $returned = app(DeactivateProfile::class)->handle($profile->id);

    // The Profile transitions to `inactive` (returned + persisted row).
    $persisted = Profile::findOrFail($profile->id);
    expect($returned->state)->toBe(ProfileState::Inactive)
        ->and($persisted->state)->toBe(ProfileState::Inactive);

    // State-preserving (design L9): exactly ONE new event (the ProfileInactive) and no other table mutated.
    expect(DomainEvent::query()->count())->toBe($eventsBefore + 1)
        ->and(DomainEvent::query()->where('name', ProfileInactive::NAME)->count())->toBe(1)
        ->and(Profile::query()->count())->toBe($profilesBefore);

    $event = DomainEvent::query()->where('name', ProfileInactive::NAME)->sole();
    expect($event->module)->toBe('parties')                     // Module::Parties->value
        ->and($event->entity_type)->toBe('Profile')             // a Profile-state event
        ->and($event->entity_id)->toBe((string) $profile->id)   // envelope entity_id is a string
        ->and($event->actor_role)->toBe(ActorRole::System);     // the ActorContext seam default

    // Payload asserted BY KEY (trap 3 — never byte-compare PG jsonb): the {profile_id, state} shape, pinned so the
    // PII-free contract cannot silently widen. `state` is the post-transition business enum value.
    expect(array_keys($event->payload))->toEqualCanonicalizing(['profile_id', 'state']);
    expect($event->payload['profile_id'])->toBe($profile->id)   // same-source ids (trap 6)
        ->and($event->payload['state'])->toBe('inactive');

    expect($event->payload)->not->toHaveKey('name')
        ->and($event->payload)->not->toHaveKey('email');

    // A directly-invoked deactivation is a ROOT event: it records no parent in its transaction.
    expect($event->causation_id)->toBeNull()
        ->and($event->correlation_id)->toBe($event->event_id);
});

it('a deactivated (inactive) Profile does not block a fresh application either', function () {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    $profile = Profile::factory()->create([
        'customer_id' => $customer->id,
        'club_id' => $club->id,
        'state' => ProfileState::Active,
    ]);

    app(DeactivateProfile::class)->handle($profile->id);
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Inactive);

    // `inactive` is in the index's excluded set too, so a fresh application for the same pair succeeds.
    $fresh = app(CreateProfile::class)->handle($customer->id, $club->id);
    expect($fresh->state)->toBe(ProfileState::Applied)
        ->and($fresh->id)->not->toBe($profile->id);
});

it('rejects cancelling a Profile not in active or lapsed, leaving it unchanged with no event', function (ProfileState $state) {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    $profile = Profile::factory()->create(['customer_id' => $customer->id, 'club_id' => $club->id, 'state' => $state]);

    expect(fn () => app(CancelProfile::class)->handle($profile->id, 'whatever'))
        ->toThrow(IllegalProfileTransition::class);

    // The from-state guard fires before any write and the transaction rolls back: state + reason unchanged, no event.
    $persisted = Profile::findOrFail($profile->id);
    expect($persisted->state)->toBe($state)
        ->and($persisted->cancellation_reason)->toBeNull()
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'applied' => [ProfileState::Applied],          // not yet activated
    'approved' => [ProfileState::Approved],        // the state just before active
    'rejected' => [ProfileState::Rejected],        // terminal-for-this-application
    'suspended' => [ProfileState::Suspended],      // a non-active edge — restore first, then cancel from active/lapsed
    'cancelled' => [ProfileState::Cancelled],      // already terminal — no re-cancel
    'inactive' => [ProfileState::Inactive],        // already terminal
]);

it('rejects deactivating a Profile not in active, leaving it unchanged with no event', function (ProfileState $state) {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    $profile = Profile::factory()->create(['customer_id' => $customer->id, 'club_id' => $club->id, 'state' => $state]);

    expect(fn () => app(DeactivateProfile::class)->handle($profile->id))
        ->toThrow(IllegalProfileTransition::class);

    expect(Profile::findOrFail($profile->id)->state)->toBe($state)
        ->and(DomainEvent::query()->where('name', ProfileInactive::NAME)->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'applied' => [ProfileState::Applied],          // not yet activated
    'approved' => [ProfileState::Approved],        // the state just before active
    'suspended' => [ProfileState::Suspended],      // a different non-active edge
    'lapsed' => [ProfileState::Lapsed],            // deactivation is from `active` ONLY (cancel handles lapsed)
    'cancelled' => [ProfileState::Cancelled],      // terminal soft-delete
    'inactive' => [ProfileState::Inactive],        // already inactive — no re-deactivate
]);

it('a non-terminal suspended or lapsed Profile still blocks a second live Profile for the pair', function (ProfileState $liveState) {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    Profile::factory()->create([
        'customer_id' => $customer->id,
        'club_id' => $club->id,
        'state' => $liveState,   // a NON-terminal live Profile (suspended/lapsed are excluded from the terminal set)
    ]);

    // The partial-unique index covers the non-terminal states, so CreateProfile's duplicate guard rejects a second
    // live Profile for the same pair — the contrast that proves the terminal exclusion is exact (MODIFIED scenario).
    expect(fn () => app(CreateProfile::class)->handle($customer->id, $club->id))
        ->toThrow(DuplicateProfileForClub::class);
})->with([
    'suspended' => [ProfileState::Suspended],
    'lapsed' => [ProfileState::Lapsed],
]);

it('coins no ProfileCancelled event class — cancellation is audit-only (zero-invention)', function () {
    // CancelProfile records no event (§ 15.2 names no `ProfileCancelled` — design L2). Reflect the Parties Events
    // namespace via the filesystem (the SupplyLifecycleChainTest idiom): the name is not even recordable.
    $eventFiles = glob(app_path('Modules/Parties/Events/*.php')) ?: [];
    $events = array_map(static fn (string $file): string => basename($file, '.php'), $eventFiles);
    expect($events)->not->toBeEmpty()
        ->and($events)->not->toContain('ProfileCancelled');

    // The audit-only Action ships (the within-module `→ Cancelled` writer); the AC-K-EVT-14 per-Profile cancellation
    // SIGNAL Module S consumes at Producer offboarding is a deferred Module-S seam (design L2), not a Parties event.
    expect(class_exists(CancelProfile::class))->toBeTrue();
});
