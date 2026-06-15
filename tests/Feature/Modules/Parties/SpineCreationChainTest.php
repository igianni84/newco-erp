<?php

use App\Modules\Module;
use App\Modules\Parties\Actions\CreateClub;
use App\Modules\Parties\Actions\CreateCustomer;
use App\Modules\Parties\Actions\CreateProducer;
use App\Modules\Parties\Actions\CreateProducerAgreement;
use App\Modules\Parties\Actions\CreateProfile;
use App\Modules\Parties\Actions\CreateSupplier;
use App\Modules\Parties\Enums\AccountStatus;
use App\Modules\Parties\Enums\ClubRegistrationFlowType;
use App\Modules\Parties\Enums\ClubStatus;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\PartyType;
use App\Modules\Parties\Enums\ProducerAgreementStatus;
use App\Modules\Parties\Enums\ProducerStatus;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\ClubCreated;
use App\Modules\Parties\Events\CustomerCreated;
use App\Modules\Parties\Events\ProducerAgreementCreated;
use App\Modules\Parties\Events\ProducerCreated;
use App\Modules\Parties\Events\ProfileCreated;
use App\Modules\Parties\Models\Account;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Producer;
use App\Modules\Parties\Models\ProducerAgreement;
use App\Modules\Parties\Models\Profile;
use App\Modules\Parties\Models\Supplier;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use App\Platform\I18n\SupportedLocale;
use App\Platform\Money\Currency;
use App\Platform\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * The full-chain integration proof for the Parties spine (parties-core task 6.2; party-registry — Requirements:
 * Birth States Recorded — Lifecycle Transitions Deferred, Spine Creation Events). Where every other Parties test
 * pins ONE entity in isolation, this one drives the WHOLE spine through its Create* actions in dependency order —
 * Producer → Club → ProducerAgreement, Customer (+ co-provisioned Account), Profile, Supplier — and asserts the
 * emergent contract of the slice as a whole:
 *   - EXACTLY the five *Created events are recorded (Customer, Profile, Producer, Club, ProducerAgreement), each
 *     tagged module `parties` and resolved to the System actor — the substrate-wiring proof: each action records
 *     through the platform DomainEventRecorder inside its own transaction;
 *   - the two deliberate event SILENCES hold — Supplier and Account record no event (the PRD § 15 catalog names
 *     neither — design D7);
 *   - NO lifecycle / *Activated / OriginatingClubLocked event is ever recorded (the design D2 scope guard — the
 *     deferred parties-membership-lifecycle change owns every transition);
 *   - every entity is born in its birth state (`pending` / `active` / `draft` / `active` / `draft` / `applied`);
 *   - the CustomerCreated payload is STRICT PII-free (no name / email / phone / date of birth).
 *
 * This is the cross-engine gate: the file (and the whole Parties suite) is verified green on SQLite AND on a
 * local PostgreSQL 17 before the change is declared complete (knowledge/testing/rules.md). Portability: the event
 * set is asserted BY NAME (never a byte-compare of stored JSON — PG jsonb reorders keys, trap 3) and the PII-free
 * payload by its EXACT key set so it cannot silently widen. The companion architecture tests (ModuleBoundariesTest,
 * ModulePersistenceConventionsTest) stay green unamended — every reference in the spine is within Module K.
 *
 * RefreshDatabase: each action opens its OWN DB::transaction, so the recorder's `transactionLevel() === 0` guard
 * is satisfied by the savepoint even under the wrapper — the events being recorded at all is itself proof of the
 * in-transaction wiring (the recorder throws if called outside a transaction).
 */
uses(RefreshDatabase::class);

/**
 * Drives the entire Parties spine through its Create* actions in dependency order and returns the created
 * entities by key. Each leg goes through the REAL action (its own DB::transaction + recorder), exactly as
 * production would — so every assertion below observes genuine substrate behaviour, never a factory shortcut
 * (factories bypass the action and record no event).
 *
 * @return array{
 *     producer: Producer,
 *     club: Club,
 *     agreement: ProducerAgreement,
 *     customer: Customer,
 *     profile: Profile,
 *     supplier: Supplier,
 * }
 */
function createPartiesSpine(): array
{
    $producer = app(CreateProducer::class)->handle(
        name: 'Chateau Margaux',
        region: 'Bordeaux',
        country: 'France',
    );

    $club = app(CreateClub::class)->handle(
        displayName: 'Margaux Cellar Club',
        producerId: $producer->id,
        registrationFlowType: ClubRegistrationFlowType::ApplicationWithApproval,
        fee: Money::of(25000, Currency::EUR),
    );

    $agreement = app(CreateProducerAgreement::class)->handle(
        producerId: $producer->id,
        clubId: $club->id,
        termStart: CarbonImmutable::parse('2026-01-01'),
        termEnd: CarbonImmutable::parse('2026-12-31'),
        settlementCadence: 'monthly',
    );

    $customer = app(CreateCustomer::class)->handle(
        email: 'collector@example.com',
        name: 'Ada Lovelace',
        preferredCurrency: Currency::EUR,
        preferredLocale: SupportedLocale::En,
        phone: '+39 02 1234567',
        dateOfBirth: CarbonImmutable::parse('1985-07-15'),
    );

    $profile = app(CreateProfile::class)->handle(
        customerId: $customer->id,
        clubId: $club->id,
    );

    $supplier = app(CreateSupplier::class)->handle(legalName: 'Negociant Imports SARL');

    return [
        'producer' => $producer,
        'club' => $club,
        'agreement' => $agreement,
        'customer' => $customer,
        'profile' => $profile,
        'supplier' => $supplier,
    ];
}

it('records exactly the five *Created events for the spine, each tagged parties, with Supplier and Account silent', function () {
    createPartiesSpine();

    // Exactly five domain events — one per evented entity. Supplier and Account are deliberately event-silent
    // (the PRD § 15 event catalog names neither — design D7), so the whole six-entity chain emits five events.
    expect(DomainEvent::query()->count())->toBe(5);

    // The five verbatim § 15.1–15.5 names, asserted order-insensitively (the chain order is an implementation
    // detail; the SET is the contract).
    expect(DomainEvent::query()->pluck('name')->all())->toEqualCanonicalizing([
        ProducerCreated::NAME,
        ClubCreated::NAME,
        ProducerAgreementCreated::NAME,
        CustomerCreated::NAME,
        ProfileCreated::NAME,
    ]);

    // Every event is tagged module `parties` and resolved to the System actor (the ActorContext seam default —
    // no operator is authenticated in the test context).
    expect(DomainEvent::query()->where('module', Module::Parties->value)->count())->toBe(5)
        ->and(DomainEvent::query()->get()->every(fn (DomainEvent $event): bool => $event->actor_role === ActorRole::System))->toBeTrue();

    // The two deliberate event silences (design D7): no event carries the Supplier or Account entity type.
    expect(DomainEvent::query()->whereIn('entity_type', ['Supplier', 'Account'])->count())->toBe(0);
});

it('records no lifecycle, activation or OriginatingClubLocked event (the design D2 scope guard)', function () {
    createPartiesSpine();

    // The creation-only slice records ONLY *Created events — never a state-change / lifecycle event. The deferred
    // parties-membership-lifecycle change owns every transition (and the OriginatingClubLocked lock at first
    // approval — § 6.1). None of the five *Created names matches any of these lifecycle tokens.
    expect(DomainEvent::query()->where('name', 'like', '%Activated%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Approved%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Suspended%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Sunset%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Retired%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Superseded%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Terminated%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'OriginatingClubLocked')->count())->toBe(0);
});

it('creates every spine entity in its birth state', function () {
    $spine = createPartiesSpine();

    // Re-fetch through the models so the assertions exercise the hydration casts, not the in-memory create()
    // values. The birth states are the verbatim § 4.x domains (design D2): pending / active / draft / active /
    // draft / applied.
    expect(Customer::findOrFail($spine['customer']->id)->status)->toBe(CustomerStatus::Pending)
        ->and(Account::query()->where('customer_id', $spine['customer']->id)->sole()->status)->toBe(AccountStatus::Active)
        ->and(Producer::findOrFail($spine['producer']->id)->status)->toBe(ProducerStatus::Draft)
        ->and(Club::findOrFail($spine['club']->id)->status)->toBe(ClubStatus::Active)
        ->and(ProducerAgreement::findOrFail($spine['agreement']->id)->status)->toBe(ProducerAgreementStatus::Draft)
        ->and(Profile::findOrFail($spine['profile']->id)->state)->toBe(ProfileState::Applied);

    // The Supplier carries the immutable `supplier` marker and — as the minimal subtype (design D1) — has NO
    // lifecycle state column at all.
    expect(Supplier::findOrFail($spine['supplier']->id)->party_type)->toBe(PartyType::Supplier)
        ->and(Schema::hasColumn('parties_suppliers', 'status'))->toBeFalse();
});

it('records a PII-free CustomerCreated and keeps the co-provisioned Account event-silent', function () {
    $spine = createPartiesSpine();

    // sole() asserts EXACTLY one CustomerCreated — the one-event-per-Customer contract, even amid the full chain.
    $event = DomainEvent::query()->where('name', CustomerCreated::NAME)->sole();

    expect($event->module)->toBe(Module::Parties->value)
        ->and($event->entity_type)->toBe('Customer')
        ->and($event->entity_id)->toBe((string) $spine['customer']->id);   // envelope entity_id is a string

    // The exact key set is pinned so the PII-free contract cannot silently widen — the Customer holds
    // email/name/phone/date_of_birth on the module table (where GDPR erasure operates), never in the audit store.
    expect(array_keys($event->payload))->toEqualCanonicalizing([
        'customer_id', 'party_type', 'status', 'preferred_currency', 'preferred_locale', 'originating_club_id',
    ]);

    expect($event->payload)->not->toHaveKey('email')
        ->and($event->payload)->not->toHaveKey('name')
        ->and($event->payload)->not->toHaveKey('phone')
        ->and($event->payload)->not->toHaveKey('date_of_birth');

    // The Account is co-provisioned (1:1) but recorded NO event (design D7) — proven here in the integration
    // context, complementing AccountTest's isolated assertion.
    expect(Account::query()->where('customer_id', $spine['customer']->id)->count())->toBe(1)
        ->and(DomainEvent::query()->where('entity_type', 'Account')->count())->toBe(0);
});
