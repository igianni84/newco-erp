<?php

use App\Modules\Parties\Actions\ApproveProfile;
use App\Modules\Parties\Actions\CreateProfile;
use App\Modules\Parties\Actions\LapseProfile;
use App\Modules\Parties\Actions\RenewProfile;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\ProfileExpired;
use App\Modules\Parties\Events\ProfileRenewed;
use App\Modules\Parties\Exceptions\IllegalProfileTransition;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the Profile lapse/renew grace pair (parties-membership-suspension, design L3/L4/L5/L9/L10/L11; party-registry —
 * Requirements: Profile Lapse and Grace Renewal, Demand-Side Status Events). It drives the REAL {@see LapseProfile} /
 * {@see RenewProfile} Actions and asserts the emergent contract:
 *   - `active → lapsed` ({@see LapseProfile}) is the SOLE writer of that transition: it stamps `lapsed_at` (the
 *     grace-window anchor, DEC-034) and records exactly one ROOT {@see ProfileExpired} ({profile_id, state}, PII-free)
 *     — and crucially NO `ProfileLapsed`, a name the § 15.2 family does not coin (the naming trap, design L3);
 *   - `lapsed → active` ({@see RenewProfile}) within the 30-day grace clears `lapsed_at` and records exactly one ROOT
 *     {@see ProfileRenewed} — the § 15.2 grace event, NOT `ProfileReactivated` (the suspend-restore edge, design L3);
 *   - the 30-day grace boundary (DEC-034) is enforced in code and INCLUSIVE: a renewal exactly 30 days after
 *     `lapsed_at` still succeeds, while one second (or a day) past it is rejected with {@see IllegalProfileTransition},
 *     the Profile left `lapsed` with its anchor untouched and no event — proven on BOTH sides of the edge with a frozen
 *     clock (the SweepTest/ActivationEventsTest idiom);
 *   - each transition is from-state guarded: a lapse from any non-`active` state, or a renew from any non-`lapsed`
 *     state (or a `lapsed` state past grace), throws before any write and the transaction rolls back;
 *   - the lapse (validity-period expiry) and renewal (Module E `MembershipFeePaid`) TRIGGERS are deferred seams — the
 *     within-module writers ship, no Module-E event contract is fabricated (zero-invention, design L5).
 *
 * The lapse happy-path Profile is driven to `active` through the GENUINE create → approve → activate Actions via the
 * file-local {@see lapseGraceActiveProfile()} helper — uniquely named per file (Pest helpers share ONE global
 * namespace, so a sibling's `createActiveProfile()` is neither redeclared nor relied upon, keeping this file
 * runnable in isolation). The renew / guard cases use the factory to pin a precise from-state (and `lapsed_at`) in
 * isolation. RefreshDatabase per the directory convention; each Action opens its OWN
 * DB::transaction, so the recorder's `transactionLevel() === 0` guard is satisfied by the savepoint under the wrapper.
 * Events are asserted BY NAME and payloads BY KEY (never a byte-compare of stored jsonb — PG reorders keys, trap 3);
 * `lapsed_at` is compared as an INSTANT via `equalTo` (tz-robust across the SQLite/PG `timestamptz` asymmetry, trap 4),
 * and payload ids are compared same-source (trap 6) — so the file holds on PostgreSQL 17.
 */
uses(RefreshDatabase::class);

// Reset the frozen clock after each test so the global test-now never leaks into a sibling (the SweepTest idiom).
afterEach(fn () => CarbonImmutable::setTestNow());

/**
 * Drives a Profile to `active` through the real create → approve → activate Actions (each its own DB::transaction +
 * recorder), exactly as production would — so the lapse under test operates on a genuinely-activated membership.
 * Uniquely named per file (the global-helper-namespace rule) so this file never collides with — nor depends on the
 * load order of — the sibling ProfileSuspensionTest's `createActiveProfile()`.
 */
function lapseGraceActiveProfile(): Profile
{
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();

    $profile = app(CreateProfile::class)->handle($customer->id, $club->id);   // born `applied`
    app(ApproveProfile::class)->handle($profile->id);                          // `applied → active` (atomic approve = activation — MVP-DEC-016)

    return Profile::findOrFail($profile->id);
}

it('lapses an active Profile, stamps lapsed_at, records exactly one root ProfileExpired and no ProfileLapsed, preserving state', function () {
    $now = CarbonImmutable::parse('2026-06-19 12:00:00', 'UTC');
    CarbonImmutable::setTestNow($now);

    $profile = lapseGraceActiveProfile();
    expect($profile->state)->toBe(ProfileState::Active)   // precondition: the setup reached `active`
        ->and($profile->lapsed_at)->toBeNull();           // an active Profile carries no grace anchor

    // Snapshot the world right before the lapse — the state-preservation proof is the DELTA across this one call.
    $eventsBefore = DomainEvent::query()->count();
    $customersBefore = Customer::query()->count();
    $clubsBefore = Club::query()->count();
    $profilesBefore = Profile::query()->count();

    $returned = app(LapseProfile::class)->handle($profile->id);

    // The Profile transitions to `lapsed` and `lapsed_at` is stamped to the current moment (returned + persisted row).
    $persisted = Profile::findOrFail($profile->id);
    expect($returned->state)->toBe(ProfileState::Lapsed)
        ->and($persisted->state)->toBe(ProfileState::Lapsed)
        ->and($persisted->lapsed_at)->not->toBeNull();
    expect($persisted->lapsed_at?->equalTo($now))->toBeTrue();   // the grace anchor is `now` (instant compare, trap 4)

    // State-preserving (design L9): the lapse recorded EXACTLY ONE new event (the ProfileExpired) and mutated no other
    // table — no voucher/order/reservation/Club Credit is fabricated, and the catalog names no `ProfileLapsed` (L3).
    expect(DomainEvent::query()->count())->toBe($eventsBefore + 1)
        ->and(DomainEvent::query()->where('name', ProfileExpired::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', 'ProfileLapsed')->count())->toBe(0)
        ->and(Customer::query()->count())->toBe($customersBefore)
        ->and(Club::query()->count())->toBe($clubsBefore)
        ->and(Profile::query()->count())->toBe($profilesBefore);

    // The Profile's non-lifecycle columns are untouched.
    expect($persisted->customer_id)->toBe($profile->customer_id)
        ->and($persisted->club_id)->toBe($profile->club_id);

    $event = DomainEvent::query()->where('name', ProfileExpired::NAME)->sole();

    expect($event->module)->toBe('parties')                     // Module::Parties->value
        ->and($event->entity_type)->toBe('Profile')             // a Profile-state event
        ->and($event->entity_id)->toBe((string) $profile->id)   // envelope entity_id is a string
        ->and($event->actor_role)->toBe(ActorRole::System);     // the ActorContext seam default

    // Payload asserted BY KEY (trap 3 — never byte-compare PG jsonb): the {profile_id, state} shape, pinned so the
    // PII-free contract cannot silently widen. `state` is the post-transition business enum value. `lapsed_at` lives
    // on the row, NOT in the payload.
    expect(array_keys($event->payload))->toEqualCanonicalizing(['profile_id', 'state']);
    expect($event->payload['profile_id'])->toBe($profile->id)   // same-source ids (trap 6)
        ->and($event->payload['state'])->toBe('lapsed');

    expect($event->payload)->not->toHaveKey('name')
        ->and($event->payload)->not->toHaveKey('email');

    // A lapse is a ROOT event: it records no parent in its transaction.
    expect($event->causation_id)->toBeNull()
        ->and($event->correlation_id)->toBe($event->event_id);
});

it('renews a lapsed Profile within the grace window, clears lapsed_at, and records exactly one root ProfileRenewed', function () {
    $now = CarbonImmutable::parse('2026-06-19 12:00:00', 'UTC');
    CarbonImmutable::setTestNow($now);

    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    $profile = Profile::factory()->create([
        'customer_id' => $customer->id,
        'club_id' => $club->id,
        'state' => ProfileState::Lapsed,
        'lapsed_at' => $now->subDays(10),   // well within the 30-day grace
    ]);

    $returned = app(RenewProfile::class)->handle($profile->id);

    // The Profile transitions back to `active` and the grace anchor is cleared (returned + persisted row).
    $persisted = Profile::findOrFail($profile->id);
    expect($returned->state)->toBe(ProfileState::Active)
        ->and($persisted->state)->toBe(ProfileState::Active)
        ->and($persisted->lapsed_at)->toBeNull();

    // Exactly one domain event total — the factory bypasses the Create*/transition Actions and records nothing, so the
    // only event is the ProfileRenewed from this renewal.
    expect(DomainEvent::query()->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', ProfileRenewed::NAME)->count())->toBe(1);

    $event = DomainEvent::query()->where('name', ProfileRenewed::NAME)->sole();

    expect($event->module)->toBe('parties')
        ->and($event->entity_type)->toBe('Profile')
        ->and($event->entity_id)->toBe((string) $profile->id)
        ->and($event->actor_role)->toBe(ActorRole::System);

    expect(array_keys($event->payload))->toEqualCanonicalizing(['profile_id', 'state']);
    expect($event->payload['profile_id'])->toBe($profile->id)
        ->and($event->payload['state'])->toBe('active');   // the post-transition state

    expect($event->payload)->not->toHaveKey('name')
        ->and($event->payload)->not->toHaveKey('email');

    // A directly-invoked renewal is a ROOT event.
    expect($event->causation_id)->toBeNull()
        ->and($event->correlation_id)->toBe($event->event_id);
});

it('renews exactly at the 30-day grace edge — the boundary is inclusive', function () {
    $now = CarbonImmutable::parse('2026-06-19 12:00:00', 'UTC');
    CarbonImmutable::setTestNow($now);

    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    $profile = Profile::factory()->create([
        'customer_id' => $customer->id,
        'club_id' => $club->id,
        'state' => ProfileState::Lapsed,
        'lapsed_at' => $now->subDays(30),   // exactly the inclusive grace deadline (now == lapsed_at + 30 days)
    ]);

    $returned = app(RenewProfile::class)->handle($profile->id);

    // Renewal at exactly 30 days succeeds — DEC-034's grace window is inclusive of its last day.
    $persisted = Profile::findOrFail($profile->id);
    expect($returned->state)->toBe(ProfileState::Active)
        ->and($persisted->state)->toBe(ProfileState::Active)
        ->and($persisted->lapsed_at)->toBeNull()
        ->and(DomainEvent::query()->where('name', ProfileRenewed::NAME)->count())->toBe(1);
});

it('rejects renewal past the 30-day grace edge, leaving the Profile lapsed with its anchor intact and no event', function (CarbonImmutable $frozenNow, CarbonImmutable $lapsedAt) {
    CarbonImmutable::setTestNow($frozenNow);

    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    $profile = Profile::factory()->create([
        'customer_id' => $customer->id,
        'club_id' => $club->id,
        'state' => ProfileState::Lapsed,
        'lapsed_at' => $lapsedAt,
    ]);

    expect(fn () => app(RenewProfile::class)->handle($profile->id))
        ->toThrow(IllegalProfileTransition::class);

    // The grace guard fires before any write and the transaction rolls back: state stays `lapsed`, the anchor is
    // untouched, and no event was recorded (in production the deferred scheduler would instead cancel the Profile).
    $persisted = Profile::findOrFail($profile->id);
    expect($persisted->state)->toBe(ProfileState::Lapsed)
        ->and($persisted->lapsed_at?->equalTo($lapsedAt))->toBeTrue()
        ->and(DomainEvent::query()->where('name', ProfileRenewed::NAME)->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with(function () {
    // Built relative to a FIXED frozen `now` (CarbonImmutable::create needs no app — safe at dataset-collection time);
    // the test freezes the clock to this same instant so the boundary arithmetic is deterministic on both engines.
    $now = CarbonImmutable::parse('2026-06-19 12:00:00', 'UTC');

    return [
        'one second past the 30-day edge' => [$now, $now->subDays(30)->subSecond()],
        'a full day past the edge (31 days)' => [$now, $now->subDays(31)],
    ];
});

it('rejects lapsing a Profile not in active, leaving it unchanged with no event', function (ProfileState $state) {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    $profile = Profile::factory()->create(['customer_id' => $customer->id, 'club_id' => $club->id, 'state' => $state]);

    expect(fn () => app(LapseProfile::class)->handle($profile->id))
        ->toThrow(IllegalProfileTransition::class);

    // The from-state guard fires before any write and the transaction rolls back: the state is unchanged and no
    // event was recorded.
    expect(Profile::findOrFail($profile->id)->state)->toBe($state)
        ->and(DomainEvent::query()->where('name', ProfileExpired::NAME)->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'applied' => [ProfileState::Applied],         // not yet activated
    'approved' => [ProfileState::Approved],       // the state just before active — still not active
    'suspended' => [ProfileState::Suspended],     // a different non-active edge
    'lapsed' => [ProfileState::Lapsed],           // already lapsed — no re-lapse
    'cancelled' => [ProfileState::Cancelled],     // terminal soft-delete
]);

it('rejects renewing a Profile not in lapsed, leaving it unchanged with no event', function (ProfileState $state) {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();
    $profile = Profile::factory()->create(['customer_id' => $customer->id, 'club_id' => $club->id, 'state' => $state]);

    expect(fn () => app(RenewProfile::class)->handle($profile->id))
        ->toThrow(IllegalProfileTransition::class);

    expect(Profile::findOrFail($profile->id)->state)->toBe($state)
        ->and(DomainEvent::query()->where('name', ProfileRenewed::NAME)->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'active' => [ProfileState::Active],            // never lapsed — `ProfileRenewed` is the grace edge only
    'applied' => [ProfileState::Applied],          // never activated
    'suspended' => [ProfileState::Suspended],      // the suspend-restore edge is ReactivateProfile (design L3)
    'cancelled' => [ProfileState::Cancelled],      // terminal soft-delete
]);

it('ships LapseProfile and RenewProfile as within-module writers with no Module-E renewal trigger fabricated', function () {
    // The within-module writers exist (invoked by the operator / deferred scheduler / Module-E seams directly —
    // design L5); reflect the Actions namespace via the filesystem (the SupplyLifecycleChainTest idiom).
    $actionFiles = glob(app_path('Modules/Parties/Actions/*.php')) ?: [];
    $actions = array_map(static fn (string $file): string => basename($file, '.php'), $actionFiles);
    expect($actions)->not->toBeEmpty()
        ->and($actions)->toContain('LapseProfile')
        ->and($actions)->toContain('RenewProfile');

    // ...but NO Module-E renewal-trigger contract is fabricated (zero-invention — design L5): the Parties Events
    // namespace coins no `MembershipFeePaid` (that is a Module E event Module K will consume when Module E exists).
    $eventFiles = glob(app_path('Modules/Parties/Events/*.php')) ?: [];
    $events = array_map(static fn (string $file): string => basename($file, '.php'), $eventFiles);
    expect($events)->not->toBeEmpty()
        ->and($events)->not->toContain('MembershipFeePaid');
});
