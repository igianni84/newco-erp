<?php

use App\Modules\Parties\Actions\CreateClub;
use App\Modules\Parties\Enums\ClubRegistrationFlowType;
use App\Modules\Parties\Enums\ClubStatus;
use App\Modules\Parties\Events\ClubCreated;
use App\Modules\Parties\Exceptions\MissingClubProducer;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Producer;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use App\Platform\Money\Currency;
use App\Platform\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Pins the Club — a Producer-operated membership program and the FIRST Parties spine entity with a foreign
 * key AND a Money field (parties-core task 3.1; design D2/D3/D4/D7/D9; party-registry — Requirement: Club,
 * Birth States Recorded, Spine Creation Events). It proves CreateClub persists the Club in `active` for an
 * existing operating Producer, stores the per-Club fee as integer minor units + an ISO 4217 code through the
 * MoneyCast (never a float — invariant 6), records ClubCreated through the platform recorder in the SAME
 * transaction (PII-free, the Producer by id, fee as {minor_units, currency}), rejects a missing operating
 * Producer (BR-K-Club-1), exposes no operation that reassigns the operating-Producer link (BR-K-Club-2), and
 * holds the scope guard (no transition out of `active`, no lifecycle event).
 *
 * RefreshDatabase (per the task hint): the action opens its OWN DB::transaction, so the recorder's
 * `transactionLevel() === 0` guard is satisfied by the savepoint even under the wrapper. Portability: the fee
 * is read back THROUGH the MoneyCast and asserted by Money::equals(), and the event payload BY KEY — never a
 * byte-compare of stored JSON (PG jsonb reorders keys — knowledge/testing trap 3).
 */
uses(RefreshDatabase::class);

it('creates a Club in active for an existing Producer, with the fee stored as minor units + currency', function () {
    $producer = Producer::factory()->create();

    $club = app(CreateClub::class)->handle(
        displayName: 'Margaux Cellar Club',
        producerId: $producer->id,
        registrationFlowType: ClubRegistrationFlowType::ApplicationWithApproval,
        fee: Money::of(25000, Currency::of('EUR')),
    );

    // Re-fetch so the assertions exercise the read/hydration casts, not the in-memory create() values.
    $read = Club::findOrFail($club->id);

    expect($read->display_name)->toBe('Margaux Cellar Club')
        ->and($read->producer_id)->toBe($producer->id)
        ->and($read->status)->toBe(ClubStatus::Active)                                  // born active (design D2)
        ->and($read->registration_flow_type)->toBe(ClubRegistrationFlowType::ApplicationWithApproval)
        ->and($read->generates_credit)->toBeTrue()                                      // default flag (DEC-062)
        ->and($read->version)->toBe(1)                                                  // version floor, born at 1
        ->and($read->fee)->toBeInstanceOf(Money::class)
        ->and($read->fee?->equals(Money::of(25000, Currency::of('EUR'))))->toBeTrue();  // Money round-trips by value

    // The fee is two integer/string columns on disk, NEVER a float (invariant 6, design D9). Read at the raw
    // column level via the MoneyCast `{key}_minor`/`{key}_currency` convention with ->value() (the MoneyCastTest
    // idiom); toEqual tolerates drivers that return integer columns as numeric strings.
    expect(DB::table('parties_clubs')->where('id', $club->id)->value('fee_minor'))->toEqual(25000)
        ->and(DB::table('parties_clubs')->where('id', $club->id)->value('fee_currency'))->toBe('EUR');

    // The within-module belongsTo resolves the operating Producer (relations are allowed within Module K).
    expect($read->producer->is($producer))->toBeTrue();
});

it('rejects a Club creation that names no existing operating Producer (BR-K-Club-1)', function () {
    // No Producer with this id exists — the localized domain reason fires ahead of the FK integrity error.
    expect(fn () => app(CreateClub::class)->handle(
        displayName: 'Orphan Club',
        producerId: 999_999,
        registrationFlowType: ClubRegistrationFlowType::ApplicationWithApproval,
        fee: Money::of(10000, Currency::of('EUR')),
    ))->toThrow(MissingClubProducer::class);

    // The rejected creation persisted nothing — no Club row and no event.
    expect(Club::query()->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', ClubCreated::NAME)->count())->toBe(0);
});

it('records a ClubCreated domain event in the same transaction, tagged parties and PII-free', function () {
    $producer = Producer::factory()->create();

    $club = app(CreateClub::class)->handle(
        displayName: 'Penfolds Collectors Club',
        producerId: $producer->id,
        registrationFlowType: ClubRegistrationFlowType::InvitationOnly,
        fee: Money::of(25000, Currency::of('EUR')),
    );

    // sole() asserts EXACTLY one ClubCreated row exists — the one-event contract.
    $event = DomainEvent::query()->where('name', ClubCreated::NAME)->sole();

    expect($event->module)->toBe('parties')                  // Module::Parties->value
        ->and($event->entity_type)->toBe('Club')
        ->and($event->entity_id)->toBe((string) $club->id)   // envelope entity_id is a string
        ->and($event->actor_role)->toBe(ActorRole::System);  // the ActorContext seam default

    // Payload asserted BY KEY through the array cast (trap 3): the operating Producer by id, the fee as the
    // money envelope shape {minor_units, currency}, and no personal data (a Club is a program, not a Party).
    expect($event->payload['club_id'])->toBe($club->id)
        ->and($event->payload['display_name'])->toBe('Penfolds Collectors Club')
        ->and($event->payload['producer_id'])->toBe($producer->id)
        ->and($event->payload['status'])->toBe('active')
        ->and($event->payload['registration_flow_type'])->toBe('invitation_only')
        ->and($event->payload['generates_credit'])->toBeTrue();

    // Fee asserted via the 'fee' key against the canonical Money payload shape, with toEqual (order-insensitive)
    // so PG jsonb key reordering inside the nested money object cannot break it (trap 3 — never a byte-compare).
    expect($event->payload['fee'])->toEqual(Money::of(25000, Currency::of('EUR'))->toPayload());
});

it('stores a null fee when none is supplied (the fee columns are nullable)', function () {
    $producer = Producer::factory()->create();

    $club = app(CreateClub::class)->handle(
        displayName: 'Free Membership Club',
        producerId: $producer->id,
        registrationFlowType: ClubRegistrationFlowType::LinkOnboarding,
    );

    $read = Club::findOrFail($club->id);

    // A null Money reads back as null (MoneyCast: both columns null → null), and the event carries a null fee.
    expect($read->fee)->toBeNull();

    expect(DB::table('parties_clubs')->where('id', $club->id)->value('fee_minor'))->toBeNull()
        ->and(DB::table('parties_clubs')->where('id', $club->id)->value('fee_currency'))->toBeNull();

    $event = DomainEvent::query()->where('name', ClubCreated::NAME)->sole();

    expect($event->payload['fee'])->toBeNull();
});

it('exposes no operation that reassigns the operating Producer — the link is immutable (BR-K-Club-2)', function () {
    // The operating-Producer link is set once at creation and immutable thereafter: the CreateClub action
    // surface is exactly creation (construct + handle) — there is no reassignProducer/update operation in
    // this change (design D3, the deferred parties-membership-lifecycle change owns any later mutation).
    $publicMethods = collect((new ReflectionClass(CreateClub::class))->getMethods(ReflectionMethod::IS_PUBLIC))
        ->map(fn (ReflectionMethod $method): string => $method->getName())
        ->all();

    expect($publicMethods)->toEqualCanonicalizing(['__construct', 'handle']);
});

it('records no lifecycle-transition event — the Club stays active (scope guard)', function () {
    $producer = Producer::factory()->create();

    $club = app(CreateClub::class)->handle(
        displayName: 'Steady Club',
        producerId: $producer->id,
        registrationFlowType: ClubRegistrationFlowType::ApplicationWithApproval,
        fee: Money::of(5000, Currency::of('EUR')),
    );

    // Design D2 scope guard: only the *Created event exists — never a *Sunset/*Closed (the deferred
    // parties-membership-lifecycle change owns those).
    expect(DomainEvent::query()->where('name', 'like', '%Sunset%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Closed%')->count())->toBe(0)
        ->and(Club::findOrFail($club->id)->status)->toBe(ClubStatus::Active);
});

it('produces an active Club via the factory without recording an event', function () {
    // The factory is a pure fixture: it bypasses the action (and its missing-Producer pre-check), so it
    // persists an active Club under a within-module parent Producer but records no ClubCreated (later tasks
    // lean on it to stand up a Club cheaply — ProducerAgreement, Profile).
    $club = Club::factory()->create();

    expect($club->status)->toBe(ClubStatus::Active)
        ->and($club->version)->toBe(1)
        ->and($club->fee)->toBeInstanceOf(Money::class)
        ->and(Producer::query()->whereKey($club->producer_id)->exists())->toBeTrue()  // parent Producer built
        ->and(DomainEvent::query()->count())->toBe(0);
});
