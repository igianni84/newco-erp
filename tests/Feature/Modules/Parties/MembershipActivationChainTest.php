<?php

use App\Modules\Module;
use App\Modules\Parties\Actions\ActivateCustomer;
use App\Modules\Parties\Actions\ApproveProfile;
use App\Modules\Parties\Actions\CreateCustomer;
use App\Modules\Parties\Actions\CreateProfile;
use App\Modules\Parties\Actions\RecordCustomerScreening;
use App\Modules\Parties\Enums\AccountStatus;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Enums\SanctionsStatus;
use App\Modules\Parties\Enums\ScreeningTriggerSource;
use App\Modules\Parties\Events\CustomerActivated;
use App\Modules\Parties\Events\CustomerCreated;
use App\Modules\Parties\Events\CustomerOnboardingScreeningPassed;
use App\Modules\Parties\Events\OriginatingClubLocked;
use App\Modules\Parties\Events\ProfileActivated;
use App\Modules\Parties\Events\ProfileCreated;
use App\Modules\Parties\Models\Account;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use App\Platform\I18n\SupportedLocale;
use App\Platform\Money\Currency;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The full-chain integration proof + cross-engine close for the Parties DEMAND-SIDE ACTIVATION slice
 * (parties-membership-activation task 3.1; design L2–L9; party-registry — the four ADDED Requirements (Profile
 * Membership Approval, Profile Activation, Customer Onboarding Activation, Demand-Side Activation Events) and both
 * MODIFIED ones (Customer Identity, "Birth States Recorded, Lifecycle Transitions Deferred")). Where each sibling
 * pins ONE transition in isolation ({@see CustomerOnboardingActivationTest}, {@see ProfileMembershipApprovalTest},
 * {@see ProfileActivationTest}), this one drives the WHOLE slice through its real Actions in one chain on a single
 * Customer — create → accept + onboarding-screen → activate the Customer → apply to two Clubs → approve both (each
 * atomically approve = charge = activation, canon MVP-DEC-016) — and asserts the emergent contract of the slice as a whole:
 *   - every transition lands its target state (Customer `active`, BOTH Clubs' Profiles `active` — each approval
 *     activates atomically) and the Originating Club locks to the FIRST approved Club;
 *   - the chain records EXACTLY the eight-event name-set the slice's surface produces — the two spine *Created the
 *     Customer/Profiles drive through their real Create* Actions, the one onboarding screening completion, and the
 *     demand-side activation events ({@see CustomerActivated} / {@see OriginatingClubLocked} /
 *     {@see ProfileActivated}) — with `ProfileCreated` recorded TWICE (two Clubs), `OriginatingClubLocked` ONCE
 *     (one-shot) and `ProfileActivated` TWICE (both approvals activate atomically — MVP-DEC-016). The approve WRITE
 *     is AUDIT-ONLY (§ 15.2 names no `ProfileApproved` / `ProfileRejected`), and the
 *     §6.1 spec signal `MembershipApprovedByProducer` is a name the codebase deliberately never records — all three
 *     are pinned absent so no invented event can slip in;
 *   - the Originating-Club lock fires exactly ONCE across two approvals and is immutable (the NULL-gate idempotency
 *     — design L3), pinned to the first approval's Club;
 *   - Customer activation performs NO Account transition — the co-provisioned billing Account stays `active`
 *     (§ 4.7 / AC-K-FSM-9), proven here in the integration context.
 *
 * Creation uses the REAL CreateCustomer/CreateProfile spine Actions (not the factories the per-transition siblings
 * use): the slice's emergent event-set INCLUDES the spine *Created, so the chain must drive them genuinely — the
 * Customer's co-provisioned Account stays event-silent (design D7), and the two factory-born Clubs record nothing
 * (supply-side fixtures). The three onboarding-acceptance moments have no production setter in this slice (design
 * L1 risk note — the deferred registration surface or an operator writes them), so the chain stands in for that
 * writer with a direct update (the same precondition the siblings set via the factory); KYC is left NULL (cleared
 * per DEC-071), so no `kyc` Hold event enters the set.
 *
 * This is the cross-engine gate: this file and the WHOLE Parties suite (+ the architecture tests) are verified green
 * on SQLite AND on a local PostgreSQL 17 before the change is declared complete (knowledge/testing/rules.md).
 * Portability: events are asserted BY NAME / count, never a byte-compare of stored jsonb (PG reorders keys — trap 3);
 * `originating_club_id` direct column reads use loose `toEqual` (an uncast bigint FK reads back as a numeric string on
 * PG, a PHP int on SQLite — trap 6), while event-payload ids stay `toBe` (jsonb decodes them as reliable PHP ints).
 * RefreshDatabase per the directory convention; each Action opens its OWN DB::transaction, so the recorder's
 * `transactionLevel() === 0` guard is satisfied by the savepoint under the wrapper.
 */
uses(RefreshDatabase::class);

/**
 * Drives the ENTIRE demand-side activation slice through its real Actions, in dependency order, on one Customer
 * across two Clubs — and returns the entities by key. Every leg goes through the genuine Action (its own
 * DB::transaction + the recorder for the evented legs), exactly as production would.
 *
 * @return array{customer: Customer, clubC: Club, clubD: Club, profileC: Profile, profileD: Profile}
 */
function runMembershipActivationChain(): array
{
    // Two Clubs via the factory — supply-side fixtures that record NO event (the chain's event-set is purely the
    // Customer/Profile *Created the real Create* Actions drive + the demand-side activation events).
    $clubC = Club::factory()->create();
    $clubD = Club::factory()->create();

    // 1. Create the Customer through the REAL spine Action: born `pending`, originating_club_id NULL, co-provisions
    //    the 1:1 Account (event-silent — design D7), records CustomerCreated. (SpineCreationChainTest proves the
    //    spine in full; here it seeds the demand-side run so the activation composes with genuine creation.)
    $customer = app(CreateCustomer::class)->handle(
        email: 'collector@example.com',
        name: 'Ada Lovelace',
        preferredCurrency: Currency::EUR,
        preferredLocale: SupportedLocale::En,
        dateOfBirth: CarbonImmutable::parse('1990-01-01'),   // an adult DOB — the age gate (task 5.1) requires one
    );

    // 2. The three onboarding-acceptance moments — set by the deferred registration surface or an operator (this
    //    slice ships the columns with no production setter — design L1). The chain stands in for that writer with a
    //    direct update; the acceptance writes are not an evented transition, so they add NO domain event.
    $customer->update([
        'email_verified_at' => now(),
        'tc_accepted_at' => now(),
        'privacy_accepted_at' => now(),
    ]);

    // 3. The onboarding sanctions screen passes → sanctions_status `passed` + records CustomerOnboardingScreeningPassed
    //    (the FIRST screen → the onboarding family). KYC is left NULL (cleared per DEC-071), so no `kyc` Hold fires.
    app(RecordCustomerScreening::class)->handle($customer->id, SanctionsStatus::Passed, ScreeningTriggerSource::Onboarding);

    // 4. All five gates now clear → the EXPLICIT ActivateCustomer flips `pending → active` + records CustomerActivated.
    //    The screening above did NOT auto-activate (design L6); only this explicit Action does. No Account transition.
    app(ActivateCustomer::class)->handle($customer->id);

    // 5. Apply to two Clubs through the REAL CreateProfile → two `applied` Profiles, two ProfileCreated.
    $profileC = app(CreateProfile::class)->handle(customerId: $customer->id, clubId: $clubC->id);
    $profileD = app(CreateProfile::class)->handle(customerId: $customer->id, clubId: $clubD->id);

    // 6. Approve the FIRST Club → ATOMIC approve = charge = activation (canon MVP-DEC-016): `applied → approved →
    //    active` in one transaction + the one-shot Originating-Club lock (OC = clubC) → OriginatingClubLocked +
    //    ProfileActivated. `Approved` is a transient pass-through, never durably rested-in.
    app(ApproveProfile::class)->handle($profileC->id);

    // 7. Approve the SECOND Club → also atomic `applied → active`, but the OC is already locked → NO OC write, NO
    //    second OriginatingClubLocked (the NULL-gate idempotency — design L3); records only its own ProfileActivated.
    //    The approve WRITE is audit-only either way (no ProfileApproved — § 15.2).
    app(ApproveProfile::class)->handle($profileD->id);

    return [
        'customer' => $customer,
        'clubC' => $clubC,
        'clubD' => $clubD,
        'profileC' => $profileC,
        'profileD' => $profileD,
    ];
}

it('drives the whole activation chain through the real Actions and lands every entity in its activated state', function () {
    $chain = runMembershipActivationChain();

    // Re-read through the models so the assertions exercise the hydration casts, not the in-memory create() values.
    $customer = Customer::findOrFail($chain['customer']->id);
    expect($customer->status)->toBe(CustomerStatus::Active)                       // pending → active (the composite gate cleared)
        ->and($customer->sanctions_status)->toBe(SanctionsStatus::Passed)         // the onboarding screen
        ->and($customer->originating_club_id)->toEqual($chain['clubC']->id);      // locked to the FIRST approved Club (trap 6 → toEqual)

    // The co-provisioned billing Account is untouched by the Customer activation (§ 4.7 / AC-K-FSM-9).
    expect(Account::query()->where('customer_id', $customer->id)->sole()->status)->toBe(AccountStatus::Active);

    // Both Clubs' Profiles reached `active` — each approval activates atomically in one transaction (MVP-DEC-016).
    expect(Profile::findOrFail($chain['profileC']->id)->state)->toBe(ProfileState::Active)
        ->and(Profile::findOrFail($chain['profileD']->id)->state)->toBe(ProfileState::Active);
});

it('records exactly the eight-event activation name-set — no ProfileApproved/ProfileRejected/invented event', function () {
    runMembershipActivationChain();

    // The exact MULTISET the whole chain records, asserted BY NAME order-insensitively (trap 3 — never byte-compare PG
    // jsonb): the two spine *Created the Customer/Profiles drive, the onboarding screening completion, and the
    // demand-side activation events. `ProfileCreated` appears TWICE (two Clubs); `OriginatingClubLocked` ONCE (the OC
    // lock is one-shot); `ProfileActivated` TWICE (canon MVP-DEC-016 — BOTH approvals activate atomically).
    expect(DomainEvent::query()->pluck('name')->all())->toEqualCanonicalizing([
        CustomerCreated::NAME,
        ProfileCreated::NAME,
        ProfileCreated::NAME,
        CustomerOnboardingScreeningPassed::NAME,
        CustomerActivated::NAME,
        OriginatingClubLocked::NAME,
        ProfileActivated::NAME,
        ProfileActivated::NAME,
    ]);

    // Eight rows total, all module `parties`, all resolved to the System actor (the ActorContext seam default — no
    // operator is authenticated in the test context).
    expect(DomainEvent::query()->count())->toBe(8)
        ->and(DomainEvent::query()->where('module', Module::Parties->value)->count())->toBe(8)
        ->and(DomainEvent::query()->get()->every(fn (DomainEvent $event): bool => $event->actor_role === ActorRole::System))->toBeTrue();

    // Approve/decline are AUDIT-ONLY (§ 15.2 names neither `ProfileApproved` nor `ProfileRejected` — design L2) and no
    // out-of-catalog approval event is invented: the §6.1 spec signal `MembershipApprovedByProducer` is a name the
    // codebase deliberately never records (the approval write IS the audit record). All three pinned absent.
    foreach (['ProfileApproved', 'ProfileRejected', 'MembershipApprovedByProducer'] as $absent) {
        expect(DomainEvent::query()->where('name', $absent)->count())->toBe(0);
    }
});

it('fires the Originating-Club lock exactly once across two club approvals, pinned to the first approved Club', function () {
    $chain = runMembershipActivationChain();

    // Two Profiles were approved, but the OC lock is one-shot: sole() asserts EXACTLY one OriginatingClubLocked,
    // carrying the Customer entity type and the FIRST-approved Club (clubC) — the locking Club is the first
    // approval's, not the second's (design L3). Payload ids decode from jsonb as reliable PHP ints (trap 3 → toBe).
    $lock = DomainEvent::query()->where('name', OriginatingClubLocked::NAME)->sole();
    expect($lock->entity_type)->toBe('Customer')
        ->and($lock->payload['customer_id'])->toBe($chain['customer']->id)
        ->and($lock->payload['club_id'])->toBe($chain['clubC']->id);

    // The persisted link matches the lock and did NOT move to clubD on the second approval — immutable, one-shot.
    // Direct column read of the uncast bigint FK → loose `toEqual` (numeric string on PG, int on SQLite — trap 6).
    expect(Customer::findOrFail($chain['customer']->id)->originating_club_id)->toEqual($chain['clubC']->id);
});
