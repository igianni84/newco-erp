<?php

// Task 8.1 (operator-console-parties-membership; design D1/D3/D4/D5/D6/D7/D9; the change's CLOSING integration proof
// for the demand-side membership console) — ONE feature test driving a Profile through the WHOLE membership FSM end-to-
// end through the PAGES (not the raw Actions), exactly as a human operator would demo it, then composing the orthogonal
// Account FSM on the SAME Customer. It asserts the EMERGENT, ORDERED event sequence over the entire run (the
// closing-integration rule, knowledge/testing/rules.md), proving things that hold over the COMPOSED chain which no
// single per-task test asserts alone:
//   1. the full demand-side Profile lifecycle walks create → approve (atomic approve = activation, MVP-DEC-016) →
//      suspend → reactivate → lapse → renew → deactivate through the Filament pages, advancing the state at every leg
//      and recording EXACTLY its eight ROOT events IN ORDER — ProfileCreated, then the Customer-entity
//      OriginatingClubLocked on the first-ever approval
//      (§ 6.1, one-shot), then ProfileActivated / ProfileSuspended / ProfileReactivated / ProfileExpired (lapse — § 15.2
//      names no `ProfileLapsed`, design L3) / ProfileRenewed (the grace restore — NOT `ProfileReactivated`, design L3) /
//      ProfileInactive. The chain terminates at `deactivate` (the EVENTED terminal, ProfileInactive), which enriches the
//      ordered sequence; `cancel` is the AUDIT-ONLY sibling terminal off `active|lapsed` (records nothing — § 15.2 names
//      no `ProfileCancelled`), exhaustively covered by ProfileLifecycleConsoleTest and adding nothing to this emergent
//      set, so the linear walk takes the evented branch.
//   2. the orthogonal, AUDIT-ONLY Account FSM (suspend → reactivate → close on ViewCustomer — § 4.7) composes on top of
//      the SAME Customer and adds NOTHING to the event stream: the emergent sequence is byte-for-byte identical before
//      and after the Account chain (the strongest "non-vacuity through the tricky leg" — proving Account transitions
//      record no event AND never cascade into the Customer or its Profile, AC-K-FSM-9 / design L8).
//   3. EVERY recorded event is a Parties console-driven write carrying the operator audit envelope (module `parties`,
//      actor_role newco_ops, a non-null operator actor) — proven SET-WIDE, then concretely tied to the acting operator
//      on representative writes spanning BOTH surfaces (the create page + a view-page verb).
//
// DatabaseMigrations (mirroring the per-task console tests + ClubConsoleChainTest / CustomerConsoleChainTest): each
// console action drives a real domain action that opens its OWN DB::transaction, so the DomainEventRecorder's
// in-transaction append commits for real — the faithful production shape (RefreshDatabase would wrap every write in a
// never-committed outer transaction). The Customer / Club / Account are seeded EVENT-FREE via their factories (the
// factories bypass the actions), so the only events are the ones the console actions record. Parties enums/models/pages
// are imported freely here: the {Models, Actions, Enums} import-boundary carve-out governs OperatorPanel PRODUCTION
// code, not tests.
//
// Green on SQLite AND PG17 (the change's PG17 gate): the event sequence is read with an explicit `orderBy('id')` (the
// auto-increment append order — chronological across the separate per-verb transactions), and the uncast `actor_id`
// bigint reads back as a numeric string on PostgreSQL, so it is asserted with loose `toEqual`.

use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Pages\ViewCustomer;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProfileResource\Pages\CreateProfile;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProfileResource\Pages\ViewProfile;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Enums\AccountStatus;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Models\Account;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

it('drives the entire membership console slice end-to-end as an operator demo — the full Profile FSM through the pages plus the orthogonal Account FSM, asserting the ordered event sequence and the newco_ops envelope on every write', function () {
    // ONE operator drives the whole demo — the membership FSM is single-operator (the producer approve/decline is
    // exercised by newco_ops at admin-parity, design Non-Goals), so no distinct lineage is needed. Every event below
    // must carry this operator's id (actor_role newco_ops), resolved by the actions from the `operator` guard.
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    // A never-approved Customer (born `pending`, `originating_club_id` NULL — the factory's born-unset default, design
    // D6) and a Club, both factory-built so they record no event. The Customer is the one the create page binds the
    // Profile to AND the one whose Account the second chain drives.
    $customer = Customer::factory()->create();
    $club = Club::factory()->create();

    // The expected ORDERED Profile-FSM event sequence — eight ROOT events, one per evented leg, in append order. The
    // Customer-entity OriginatingClubLocked sits second (the first-ever approval locks the Originating Club, § 6.1);
    // every other event is a Profile-state event. Plain-string literals are the chain-test idiom (each verified to equal
    // its `*::NAME` constant) and read as "these exact events fired, in this order".
    $profileFsmEvents = [
        'ProfileCreated',
        'OriginatingClubLocked',
        'ProfileActivated',
        'ProfileSuspended',
        'ProfileReactivated',
        'ProfileExpired',
        'ProfileRenewed',
        'ProfileInactive',
    ];

    // ══ THE PROFILE FSM through the pages ════════════════════════════════════════════════════════════════════════
    // Each verb re-mounts the page (every Livewire::test re-reads the record, so the visibility gate sees the new
    // state) and routes through its domain action by the Profile id — the console writes the model NEVER (the
    // no-Eloquent-write rule); each action advances the state and records its event in its own transaction.

    // ── CREATE through the console page → a Profile born `applied`, 1 ProfileCreated.
    Livewire::test(CreateProfile::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'club_id' => $club->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $profile = Profile::query()
        ->where('customer_id', $customer->id)
        ->where('club_id', $club->id)
        ->sole();
    expect($profile->state)->toBe(ProfileState::Applied);

    // ── APPROVE — applied → active ATOMICALLY (approve = charge = activation, canon MVP-DEC-016) + the Customer's
    //    first-ever approval locks the Originating Club to THIS Club (§ 6.1) THEN the internal activation records
    //    ProfileActivated. `approved` is a transient pass-through; the approve WRITE records no Profile event, so the
    //    approve leg contributes exactly OriginatingClubLocked → ProfileActivated (both in the array below, in order).
    Livewire::test(ViewProfile::class, ['record' => $profile->id])
        ->callAction('approve')
        ->assertNotified((string) __('operator_console.profile.notifications.approved'));

    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Active)
        ->and(Customer::findOrFail($customer->id)->originating_club_id)->toBe($club->id);

    $lock = DomainEvent::query()->where('name', 'OriginatingClubLocked')->sole();
    expect($lock->entity_type)->toBe('Customer')
        ->and($lock->entity_id)->toBe((string) $customer->id);

    // (No separate ACTIVATE leg — the approve above already drove the Profile to `active` atomically (MVP-DEC-016), so
    //  the `activate` verb is hidden from `active`; the chain proceeds straight to the suspend self-edge. The
    //  Hero-Package seat gate DOES ship (parties-hero-package, MVP-DEC-017 / RM-05), but this Club is UNCAPPED: no
    //  `PARTIES_HERO_PACKAGE_CAPACITY` exists in the test environment, so capacity reads `null` and the approve above
    //  activates rather than diverting to `waiting_list`. The capped arm is driven by ProfileApprovalConsoleTest.)

    // ── SUSPEND — active → suspended + ProfileSuspended (state-preserving — design L9; the cross-entity Club-Credit
    //    preservation is pinned by ProfileStatusConsoleTest).
    Livewire::test(ViewProfile::class, ['record' => $profile->id])
        ->callAction('suspend')
        ->assertNotified((string) __('operator_console.profile.notifications.suspended'));
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Suspended);

    // ── REACTIVATE — suspended → active + ProfileReactivated (the suspension restore — NOT ProfileRenewed, design L3).
    Livewire::test(ViewProfile::class, ['record' => $profile->id])
        ->callAction('reactivate')
        ->assertNotified((string) __('operator_console.profile.notifications.reactivated'));
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Active);

    // ── LAPSE — active → lapsed + ProfileExpired (the STATE is `Lapsed`, the EVENT is ProfileExpired, design L3). The
    //    action stamps `lapsed_at = now()`, opening the 30-day grace the next leg renews within.
    Livewire::test(ViewProfile::class, ['record' => $profile->id])
        ->callAction('lapse')
        ->assertNotified((string) __('operator_console.profile.notifications.lapsed'));
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Lapsed);

    // ── RENEW — lapsed → active + ProfileRenewed. WITHIN grace: `lapsed_at` was stamped `now()` a moment ago, so the
    //    renew is comfortably inside the inclusive 30-day window (DEC-034). The grace restore records ProfileRenewed
    //    (the grace edge — design L3); the action clears `lapsed_at`.
    Livewire::test(ViewProfile::class, ['record' => $profile->id])
        ->callAction('renew')
        ->assertNotified((string) __('operator_console.profile.notifications.renewed'));
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Active);

    // ── DEACTIVATE — active → inactive + ProfileInactive. The EVENTED terminal off `active` (§ 4.2.1), chosen over the
    //    audit-only `cancel` sibling (which records nothing) so the linear walk closes on a recorded event.
    Livewire::test(ViewProfile::class, ['record' => $profile->id])
        ->callAction('deactivate')
        ->assertNotified((string) __('operator_console.profile.notifications.deactivated'));
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Inactive);

    // ── The Profile FSM emitted EXACTLY its eight events, IN ORDER. Pinning the sequence HERE (before the Account
    //    chain) localises a regression to the Profile walk — were a leg to leak or drop an event, this fails at the
    //    point of cause, before the orthogonality assertion below muddies the diagnosis.
    expect(DomainEvent::query()->orderBy('id')->pluck('name')->all())->toEqual($profileFsmEvents);

    // ══ THE ORTHOGONAL ACCOUNT FSM through ViewCustomer ══════════════════════════════════════════════════════════
    // The 1:1 Account is co-provisioned in production by CreateCustomer; the factory stands one up directly (born
    // `active`, event-free). Its FSM (suspend → reactivate → close — § 4.7) is ORTHOGONAL to the Profile/Customer FSMs
    // and AUDIT-ONLY (§ 15 names no Account event, design L8): every leg moves ONLY Account.status and records nothing.
    $account = Account::factory()->create([
        'customer_id' => $customer->id,
        'status' => AccountStatus::Active,
    ]);

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->callAction('suspendAccount')
        ->assertNotified((string) __('operator_console.customer.notifications.account_suspended'));
    expect($account->refresh()->status)->toBe(AccountStatus::Suspended);

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->callAction('reactivateAccount')
        ->assertNotified((string) __('operator_console.customer.notifications.account_reactivated'));
    expect($account->refresh()->status)->toBe(AccountStatus::Active);

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->callAction('closeAccount')
        ->assertNotified((string) __('operator_console.customer.notifications.account_closed'));
    expect($account->refresh()->status)->toBe(AccountStatus::Closed);

    // ══ Emergent event-sequence + orthogonality proof over the WHOLE demo ════════════════════════════════════════
    // (a) the Account chain added NOTHING — the ordered emergent sequence is byte-for-byte the same eight Profile
    //     events. This is the strongest orthogonality proof (AC-K-FSM-9 / design L8): three Account transitions
    //     composed on the same Customer record no event and never cascade.
    expect(DomainEvent::query()->orderBy('id')->pluck('name')->all())->toEqual($profileFsmEvents);

    // (b) …and the Account FSM never touched the Customer or its Profile — the Profile is still the terminal `inactive`
    //     and the Customer is still its born `pending` (no Account transition cascades into either FSM).
    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Inactive)
        ->and(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Pending);

    // (c) EVERY recorded event is a Parties console-driven write carrying the operator audit envelope — module
    //     `parties`, actor_role newco_ops, a non-null operator actor (no System-actor projection rows exist).
    $events = DomainEvent::query()->get();
    expect($events)->toHaveCount(8);
    foreach ($events as $event) {
        expect($event->module)->toBe('parties')
            ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
            ->and($event->actor_id)->not->toBeNull();
    }

    // (d) …and the actor_id is concretely the ACTING operator on representative writes spanning BOTH surfaces — the
    //     create page (ProfileCreated) and a view-page lifecycle verb (ProfileInactive). Loose toEqual is the proven
    //     idiom: the uncast bigint reads back as a numeric string on PG, never strict-compare it.
    $created = DomainEvent::query()->where('name', 'ProfileCreated')->sole();
    $inactive = DomainEvent::query()->where('name', 'ProfileInactive')->sole();
    expect($created->actor_id)->toEqual($operator->id)
        ->and($inactive->actor_id)->toEqual($operator->id);
});
