<?php

use App\Modules\Module;
use App\Modules\Parties\Actions\ActivateCustomer;
use App\Modules\Parties\Actions\ApproveProfile;
use App\Modules\Parties\Actions\CancelProfile;
use App\Modules\Parties\Actions\CloseAccount;
use App\Modules\Parties\Actions\CloseCustomer;
use App\Modules\Parties\Actions\CreateCustomer;
use App\Modules\Parties\Actions\CreateProfile;
use App\Modules\Parties\Actions\DeactivateProfile;
use App\Modules\Parties\Actions\LapseProfile;
use App\Modules\Parties\Actions\LiftHold;
use App\Modules\Parties\Actions\PlaceHold;
use App\Modules\Parties\Actions\ReactivateAccount;
use App\Modules\Parties\Actions\ReactivateProfile;
use App\Modules\Parties\Actions\RecordCustomerScreening;
use App\Modules\Parties\Actions\RenewProfile;
use App\Modules\Parties\Actions\SuspendAccount;
use App\Modules\Parties\Actions\SuspendCustomer;
use App\Modules\Parties\Actions\SuspendProfile;
use App\Modules\Parties\Enums\AccountStatus;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Enums\SanctionsStatus;
use App\Modules\Parties\Enums\ScreeningTriggerSource;
use App\Modules\Parties\Events\CustomerActivated;
use App\Modules\Parties\Events\CustomerClosed;
use App\Modules\Parties\Events\CustomerCreated;
use App\Modules\Parties\Events\CustomerHoldLifted;
use App\Modules\Parties\Events\CustomerHoldPlaced;
use App\Modules\Parties\Events\CustomerOnboardingScreeningPassed;
use App\Modules\Parties\Events\CustomerReactivated;
use App\Modules\Parties\Events\CustomerSuspended;
use App\Modules\Parties\Events\OriginatingClubLocked;
use App\Modules\Parties\Events\ProfileActivated;
use App\Modules\Parties\Events\ProfileCreated;
use App\Modules\Parties\Events\ProfileExpired;
use App\Modules\Parties\Events\ProfileInactive;
use App\Modules\Parties\Events\ProfileReactivated;
use App\Modules\Parties\Events\ProfileRenewed;
use App\Modules\Parties\Events\ProfileSuspended;
use App\Modules\Parties\Exceptions\IllegalAccountTransition;
use App\Modules\Parties\Exceptions\IllegalCustomerTransition;
use App\Modules\Parties\Exceptions\IllegalProfileTransition;
use App\Modules\Parties\Models\Account;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use App\Platform\I18n\SupportedLocale;
use App\Platform\Money\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The full-chain integration proof + cross-engine close for the Parties DEMAND-SIDE SUSPENSION slice
 * (parties-membership-suspension task 5.1; design L1–L11; party-registry — the seven ADDED Requirements (Profile
 * Suspension and Restoration, Profile Lapse and Grace Renewal, Profile Cancellation and Deactivation, Customer
 * Suspension and Closure, Account Status Lifecycle, Hold-Driven Status Coupling, Demand-Side Status Events) and both
 * MODIFIED ones (Profile — Multi-Profile Membership, "Birth States Recorded, Lifecycle Transitions Deferred")).
 * Where each sibling pins ONE transition in isolation ({@see ProfileSuspensionTest}, {@see ProfileLapseGraceTest},
 * {@see ProfileCancellationTest}, {@see CustomerSuspensionCascadeTest}, {@see CustomerClosureAndAccountStatusTest},
 * {@see HoldStatusCouplingPlaceTest}, {@see HoldStatusCouplingLiftTest}), this one drives the WHOLE slice through its
 * real Actions in one chain on a single Customer with four Club memberships, and asserts the emergent contract of the
 * slice as a whole:
 *   - every status edge lands its target state and the chain ends with the Customer + Account `closed`, P_a/P_b back
 *     to `Active` (suspended then restored), P_c `Cancelled` (lapse → cancel), P_d `Inactive` (deactivate);
 *   - the chain records EXACTLY the 29-event name-set the slice's surface produces — the Customer/Profile spine the
 *     real Create / Approve (atomic activate — MVP-DEC-016) Actions drive, the two Hold events per place/lift, and the SIX of the eight
 *     status events that are evented; `CancelProfile` and ALL Account transitions are AUDIT-ONLY (record nothing), and
 *     `ProfileExpired` (not a non-existent `ProfileLapsed`) is the lapse event — every forbidden / invented name is
 *     pinned absent so nothing out of catalog can slip in;
 *   - the Hold→`suspended` coupling drives the Customer cascade on place and the coverage-guarded restore on lift: a
 *     Profile carrying its OWN active Hold stays `Suspended` when the cascading Customer Hold lifts (P_b), restoring
 *     only when its last covering Hold goes;
 *   - a cascaded `ProfileSuspended` is a CAUSATION CHILD of the same-transaction `CustomerSuspended` root, while the
 *     directly-invoked and Profile-Hold suspensions are ROOTs — the cascade is one honest causal chain (design L11);
 *   - every from-state guard rejects an out-of-state call (incl. a past-grace renewal — the >30-day edge) leaving
 *     state and the event log untouched.
 *
 * Creation uses the REAL spine Actions (CreateCustomer/CreateProfile/ApproveProfile — the approve now atomically
 * activates, canon MVP-DEC-016 — not the factories the per-transition siblings use) so the chain's emergent event-set is genuine end-to-end (the
 * MembershipActivationChainTest philosophy). The three onboarding-acceptance moments have no production setter in this
 * slice, so the chain stands in for that writer with a direct update (no event); KYC is left NULL (cleared per
 * DEC-071) so no `kyc` Hold enters the main chain; the Holds the coupling rides on are the `admin`/`fraud` operator
 * Holds, placed and lifted through the real PlaceHold/LiftHold.
 *
 * This is the cross-engine gate: this file and the WHOLE Parties suite (+ the architecture tests) are verified green
 * on SQLite AND on a local PostgreSQL 17 before the change is declared complete (knowledge/testing/rules.md).
 * Portability: events are asserted BY NAME / count, never a byte-compare of stored jsonb (PG reorders keys — trap 3);
 * `causation_id` is an UNCAST column → a numeric string on PG while the `id` PK round-trips int, so the causation
 * proof casts `(int) $child->causation_id` (trap 6). RefreshDatabase per the directory convention; each Action opens
 * its OWN DB::transaction, so a place/lift → Suspend/Reactivate Action nests a savepoint under the place/lift transaction.
 */
uses(RefreshDatabase::class);

/**
 * Drives the ENTIRE demand-side suspension slice through its real Actions, in dependency order, on one Customer across
 * four Clubs — and returns the entities by key. Every leg goes through the genuine Action (its own DB::transaction +
 * the recorder for the evented legs), exactly as production would.
 *
 * Journeys: P_a is reused (suspend→restore, lapse→renew-in-grace, then the Customer cascade); P_b takes the
 * residual-Hold path (its own Profile Hold outlives the cascading Customer Hold); P_c lapses then cancels (terminal,
 * audit-only); P_d deactivates (terminal). Account walks suspend→restore→close (audit-only); the Customer closes last.
 *
 * @return array{customer: Customer, account: Account, profileA: Profile, profileB: Profile, profileC: Profile, profileD: Profile}
 */
function runMembershipSuspensionChain(): array
{
    // Four Clubs via the factory — supply-side fixtures that record NO event (the chain's event-set is purely the
    // Customer/Profile spine the real Actions drive + the demand-side status + Hold events).
    $clubs = Club::factory()->count(4)->create();

    // 1. Create the Customer through the REAL spine: born `pending`, co-provisions the 1:1 Account (event-silent —
    //    design D7), records CustomerCreated.
    $customer = app(CreateCustomer::class)->handle(
        email: 'collector@example.com',
        name: 'Ada Lovelace',
        preferredCurrency: Currency::EUR,
        preferredLocale: SupportedLocale::En,
    );

    // 2. The three onboarding-acceptance moments — set by the deferred registration surface or an operator (no
    //    production setter in this slice). A non-evented direct update, so it adds NO domain event.
    $customer->update([
        'email_verified_at' => now(),
        'tc_accepted_at' => now(),
        'privacy_accepted_at' => now(),
    ]);

    // 3. The onboarding sanctions screen passes → records CustomerOnboardingScreeningPassed. KYC stays NULL (cleared).
    app(RecordCustomerScreening::class)->handle($customer->id, SanctionsStatus::Passed, ScreeningTriggerSource::Onboarding);

    // 4. The explicit ActivateCustomer flips `pending → active` + records CustomerActivated (no Account transition).
    app(ActivateCustomer::class)->handle($customer->id);

    // 5. Apply to four Clubs through the REAL CreateProfile → four `applied` Profiles, four ProfileCreated.
    $profiles = $clubs->map(
        fn (Club $club): Profile => app(CreateProfile::class)->handle(customerId: $customer->id, clubId: $club->id),
    );
    [$profileA, $profileB, $profileC, $profileD] = $profiles->all();

    // 6. Approve all four → each atomically approve = charge = activation (canon MVP-DEC-016): `applied → active` in
    //    one transaction. The first approval also locks the Originating Club (one OriginatingClubLocked); all four
    //    record ProfileActivated (four total). `Approved` is a transient pass-through, never durably rested-in.
    foreach ($profiles as $profile) {
        app(ApproveProfile::class)->handle($profile->id);
    }

    // === PHASE B — P_a self-edges: suspend→restore (Active ↔ Suspended), then lapse→renew within the grace ===
    app(SuspendProfile::class)->handle($profileA->id);        // ProfileSuspended (root)
    app(ReactivateProfile::class)->handle($profileA->id);     // ProfileReactivated (root)
    app(LapseProfile::class)->handle($profileA->id);          // ProfileExpired (NOT ProfileLapsed)
    app(RenewProfile::class)->handle($profileA->id);          // ProfileRenewed (in grace — lapsed_at is `now`)
    // P_a is `Active` again.

    // === PHASE C — terminal Profiles (excluded from the later cascade) ===
    app(LapseProfile::class)->handle($profileC->id);          // ProfileExpired
    app(CancelProfile::class)->handle($profileC->id, 'producer_offboarding'); // Lapsed → Cancelled — AUDIT-ONLY (no event)
    app(DeactivateProfile::class)->handle($profileD->id);     // Active → Inactive → ProfileInactive

    // === PHASE D — Hold-driven coupling: cascade on place, coverage-guarded restore on lift ===
    // P_b's own Profile Hold suspends only P_b (BR-K-Hold-4 isolation) → ProfileSuspended (root).
    $profileHold = app(PlaceHold::class)->handle(HoldType::Admin, HoldScope::Profile, $profileB->id);
    // A Customer Hold suspends the Customer + cascade-suspends its `Active` Profiles (here only P_a; P_b already
    // Suspended is skipped) → CustomerSuspended (root) + one ProfileSuspended (causation CHILD of it).
    $customerHold = app(PlaceHold::class)->handle(HoldType::Fraud, HoldScope::Customer, $customer->id, 'compliance review');
    // Lift the Customer Hold → restore the Customer + cascade-restore P_a (now uncovered); P_b stays Suspended (its
    // own Hold still covers it) → CustomerReactivated (root) + one ProfileReactivated (child).
    app(LiftHold::class)->handle($customerHold->id, 'review cleared');
    // Lift P_b's Profile Hold (its last covering Hold) → P_b restores → ProfileReactivated (root).
    app(LiftHold::class)->handle($profileHold->id, 'profile hold cleared');
    // Customer `active`; P_a and P_b `Active`.

    // === PHASE E — Account FSM (audit-only — records NO event) ===
    $account = Account::query()->where('customer_id', $customer->id)->sole();
    app(SuspendAccount::class)->handle($account->id);         // active → suspended
    app(ReactivateAccount::class)->handle($account->id);      // suspended → active
    app(CloseAccount::class)->handle($account->id);           // active → closed

    // === PHASE F — close the Customer (terminal; § 15.1 names NO Profile cascade for closure) ===
    app(CloseCustomer::class)->handle($customer->id);         // active → closed → CustomerClosed (root)

    return [
        'customer' => $customer,
        'account' => $account,
        'profileA' => $profileA,
        'profileB' => $profileB,
        'profileC' => $profileC,
        'profileD' => $profileD,
    ];
}

it('drives the whole suspension chain through the real Actions and lands every entity in its final state', function () {
    $chain = runMembershipSuspensionChain();

    // Re-read through the models so the assertions exercise the hydration casts, not the in-memory create() values.
    expect(Customer::findOrFail($chain['customer']->id)->status)->toBe(CustomerStatus::Closed)          // closed last (no Profile cascade)
        ->and(Account::query()->whereKey($chain['account']->id)->sole()->status)->toBe(AccountStatus::Closed) // audit-only FSM walk
        ->and(Profile::findOrFail($chain['profileA']->id)->state)->toBe(ProfileState::Active)           // suspended→restored, lapsed→renewed
        ->and(Profile::findOrFail($chain['profileB']->id)->state)->toBe(ProfileState::Active)           // residual-Hold path, restored last
        ->and(Profile::findOrFail($chain['profileC']->id)->state)->toBe(ProfileState::Cancelled)        // lapse → cancel (terminal)
        ->and(Profile::findOrFail($chain['profileD']->id)->state)->toBe(ProfileState::Inactive);        // deactivate (terminal)

    // The terminal Profiles carry their reason/clear markers: P_c keeps the Producer-initiated cancellation reason.
    expect(Profile::findOrFail($chain['profileC']->id)->cancellation_reason)->toBe('producer_offboarding')
        ->and(Profile::findOrFail($chain['profileA']->id)->lapsed_at)->toBeNull();                      // RenewProfile cleared it
});

it('records exactly the emergent suspension name-set — no invented, audit-only or out-of-catalog event fires', function () {
    runMembershipSuspensionChain();

    // The exact MULTISET the whole chain records, asserted BY NAME order-insensitively (trap 3 — never byte-compare PG
    // jsonb). Six of the eight status events are evented here; `CancelProfile` and every Account transition are
    // audit-only (record nothing), so they contribute ZERO rows.
    expect(DomainEvent::query()->pluck('name')->all())->toEqualCanonicalizing([
        // — Customer spine: real CreateCustomer → onboarding screen → ActivateCustomer —
        CustomerCreated::NAME,
        CustomerOnboardingScreeningPassed::NAME,
        CustomerActivated::NAME,
        // — Profile spine: four memberships create → approve (atomic activate); the OC lock is one-shot —
        ProfileCreated::NAME, ProfileCreated::NAME, ProfileCreated::NAME, ProfileCreated::NAME,
        OriginatingClubLocked::NAME,
        ProfileActivated::NAME, ProfileActivated::NAME, ProfileActivated::NAME, ProfileActivated::NAME,
        // — P_a self-edges: suspend→restore, lapse→renew —
        ProfileSuspended::NAME, ProfileReactivated::NAME,
        ProfileExpired::NAME, ProfileRenewed::NAME,
        // — terminal Profiles: P_c lapse (ProfileExpired) then cancel (AUDIT-ONLY — nothing); P_d deactivate —
        ProfileExpired::NAME,
        ProfileInactive::NAME,
        // — Hold-driven coupling: P_b Profile Hold (suspend root); C Customer Hold (suspend root + cascade child) —
        CustomerHoldPlaced::NAME,
        ProfileSuspended::NAME,
        CustomerHoldPlaced::NAME,
        CustomerSuspended::NAME,
        ProfileSuspended::NAME,
        // — lift C's Hold: restore C + cascade-restore P_a; P_b stays Suspended (own Hold) —
        CustomerHoldLifted::NAME,
        CustomerReactivated::NAME,
        ProfileReactivated::NAME,
        // — lift P_b's Hold: restore P_b —
        CustomerHoldLifted::NAME,
        ProfileReactivated::NAME,
        // — Account FSM (suspend/restore/close): AUDIT-ONLY — no event —
        // — close the Customer: terminal, no cascade —
        CustomerClosed::NAME,
    ]);

    // 29 rows total, all module `parties`, all resolved to the System actor (the ActorContext seam default).
    expect(DomainEvent::query()->count())->toBe(29)
        ->and(DomainEvent::query()->where('module', Module::Parties->value)->count())->toBe(29)
        ->and(DomainEvent::query()->get()->every(fn (DomainEvent $event): bool => $event->actor_role === ActorRole::System))->toBeTrue();

    // Nothing outside the eight-name status set is recorded — every name the catalog never coins (the lapse state is
    // `Lapsed` but the event is `ProfileExpired`; cancellation + Account are audit-only) and every deferred-seam name
    // (WaitingList, Customer segments) is pinned absent.
    foreach ([
        'ProfileLapsed', 'ProfileCancelled', 'ProfileApproved', 'ProfileRejected',
        'AccountActivated', 'AccountSuspended', 'AccountReactivated', 'AccountClosed', 'AccountCreated',
        'WaitingListJoined', 'CustomerSegmentChanged',
    ] as $absent) {
        expect(DomainEvent::query()->where('name', $absent)->count())->toBe(0);
    }

    // No Account-entity event of ANY name (the whole Account family is audit-only — § 15 names none).
    expect(DomainEvent::query()->where('entity_type', 'Account')->count())->toBe(0);
});

it('threads a cascaded ProfileSuspended as a causation child of the same-transaction CustomerSuspended root', function () {
    runMembershipSuspensionChain();

    // The lone CustomerSuspended is a ROOT (the Customer Hold's SuspendCustomer records it with no parent), even
    // though PlaceHold invoked it — the coupling invokes, it does not thread the Hold event as a parent.
    $root = DomainEvent::query()->where('name', CustomerSuspended::NAME)->sole();
    expect($root->causation_id)->toBeNull();

    // Of the three ProfileSuspended rows, exactly the ONE cascaded inside SuspendCustomer is a causation child of the
    // root; the other two (P_a's self-edge, P_b's Profile-Hold place) are ROOTs (no causation parent). `causation_id`
    // is uncast → numeric string on PG, so cast to int before comparing to the int `id` PK (trap 6).
    $children = DomainEvent::query()->where('name', ProfileSuspended::NAME)->whereNotNull('causation_id')->get();
    $roots = DomainEvent::query()->where('name', ProfileSuspended::NAME)->whereNull('causation_id')->get();

    expect($children)->toHaveCount(1)
        ->and($roots)->toHaveCount(2)
        ->and((int) $children->sole()->causation_id)->toBe($root->id)
        ->and($children->sole()->correlation_id)->toBe($root->correlation_id);
});

it('rejects every illegal demand-side status transition from a wrong from-state, including a past-grace renewal', function () {
    // Factory-pinned from-states (factories record NO event), so the event log staying empty proves every rejection
    // wrote nothing. Each guard throws its module-specific localized exception BEFORE any state write.
    $applied = Profile::factory()->create(['state' => ProfileState::Applied]);
    $active = Profile::factory()->create(['state' => ProfileState::Active]);
    $pastGrace = Profile::factory()->create([
        'state' => ProfileState::Lapsed,
        'lapsed_at' => now()->subDays(31),     // outside the 30-day grace (DEC-034) → renewal rejected
    ]);
    $pending = Customer::factory()->create(['status' => CustomerStatus::Pending]);
    $suspendedAccount = Account::factory()->create(['status' => AccountStatus::Suspended]);

    // Profile: suspend only from Active; reactivate only from Suspended; renew only within grace.
    expect(fn () => app(SuspendProfile::class)->handle($applied->id))->toThrow(IllegalProfileTransition::class);
    expect(fn () => app(ReactivateProfile::class)->handle($active->id))->toThrow(IllegalProfileTransition::class);
    expect(fn () => app(RenewProfile::class)->handle($pastGrace->id))->toThrow(IllegalProfileTransition::class);
    // Customer: suspend only from active. Account: reactivate only from suspended (born `active`).
    expect(fn () => app(SuspendCustomer::class)->handle($pending->id))->toThrow(IllegalCustomerTransition::class);
    expect(fn () => app(SuspendAccount::class)->handle($suspendedAccount->id))->toThrow(IllegalAccountTransition::class);

    // Nothing moved, nothing recorded.
    expect(Profile::findOrFail($applied->id)->state)->toBe(ProfileState::Applied)
        ->and(Profile::findOrFail($active->id)->state)->toBe(ProfileState::Active)
        ->and(Profile::findOrFail($pastGrace->id)->state)->toBe(ProfileState::Lapsed)
        ->and(Customer::findOrFail($pending->id)->status)->toBe(CustomerStatus::Pending)
        ->and(Account::query()->whereKey($suspendedAccount->id)->sole()->status)->toBe(AccountStatus::Suspended)
        ->and(DomainEvent::query()->count())->toBe(0);
});
