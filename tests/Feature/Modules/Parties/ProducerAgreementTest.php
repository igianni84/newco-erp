<?php

use App\Modules\Parties\Actions\CreateProducerAgreement;
use App\Modules\Parties\Enums\ProducerAgreementStatus;
use App\Modules\Parties\Enums\SettlementCadence;
use App\Modules\Parties\Events\ProducerAgreementCreated;
use App\Modules\Parties\Exceptions\MissingAgreementProducer;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Producer;
use App\Modules\Parties\Models\ProducerAgreement;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the ProducerAgreement — the NewCo↔Producer commercial agreement (parties-core task 3.2; design
 * D2/D3/D4/D7; party-registry — Requirement: ProducerAgreement, Birth States Recorded, Spine Creation Events).
 * It proves CreateProducerAgreement persists the agreement in `draft` for an existing Producer (Club narrowing
 * optional), carries its term dates + the D19 settlement-cadence seam, records ProducerAgreementCreated through
 * the platform recorder in the SAME transaction (PII-free, both parties by id, club nullable), rejects a
 * missing Producer (§ 4.6), creates draft agreements FREELY (the single-active-per-scope rule is an
 * activation-time invariant, NOT enforced here — BR-K-Agreement-1), and holds the scope guard (no transition
 * out of `draft`, no lifecycle event).
 *
 * RefreshDatabase: the action opens its OWN DB::transaction, so the recorder's `transactionLevel() === 0`
 * guard is satisfied by the savepoint even under the wrapper. Portability: term dates are read THROUGH the
 * immutable_date cast and the event payload BY KEY — never a byte-compare of stored JSON (PG jsonb reorders
 * keys — knowledge/testing trap 3).
 */
uses(RefreshDatabase::class);

it('creates a Producer-wide draft agreement with term dates and settlement cadence', function () {
    $producer = Producer::factory()->create();

    $agreement = app(CreateProducerAgreement::class)->handle(
        producerId: $producer->id,
        termStart: CarbonImmutable::parse('2026-01-01'),
        termEnd: CarbonImmutable::parse('2026-12-31'),
        settlementCadence: 'quarterly',
    );

    // Re-fetch so the assertions exercise the read/hydration casts, not the in-memory create() values.
    $read = ProducerAgreement::findOrFail($agreement->id);

    expect($read->producer_id)->toBe($producer->id)
        ->and($read->club_id)->toBeNull()                                   // Producer-wide (no Club narrowing)
        ->and($read->status)->toBe(ProducerAgreementStatus::Draft)          // born draft (design D2)
        ->and($read->term_start?->toDateString())->toBe('2026-01-01')       // term dates round-trip via the cast
        ->and($read->term_end?->toDateString())->toBe('2026-12-31')
        ->and($read->settlement_cadence)->toBe(SettlementCadence::Quarterly) // the D19 seam (closed enum, read through the cast)
        ->and($read->version)->toBe(1);                                     // version floor, born at 1

    // The required Producer resolves through the within-module belongsTo (relations are allowed within Module K).
    expect($read->producer->is($producer))->toBeTrue();
});

it('creates a draft agreement narrowed to a specific Club (the Club narrowing is optional)', function () {
    $producer = Producer::factory()->create();
    // A Club operated by the same Producer (a within-module fixture).
    $club = Club::factory()->for($producer, 'producer')->create();

    $agreement = app(CreateProducerAgreement::class)->handle(
        producerId: $producer->id,
        clubId: $club->id,
    );

    $read = ProducerAgreement::findOrFail($agreement->id);

    expect($read->producer_id)->toBe($producer->id)
        ->and($read->club_id)->toBe($club->id)                             // narrowed to one Club
        ->and($read->status)->toBe(ProducerAgreementStatus::Draft)
        ->and($read->term_start)->toBeNull()                               // term dates are optional (nullable)
        ->and($read->settlement_cadence)->toBeNull();

    // The optional narrowing Club resolves through the within-module belongsTo.
    expect($read->club?->is($club))->toBeTrue();
});

it('creates draft agreements freely — the single-active-per-scope rule is NOT enforced here (BR-K-Agreement-1)', function () {
    // BR-K-Agreement-1 ("at most one ACTIVE agreement per Producer scope") is an activation-time invariant,
    // out of this creation-only slice. Two drafts for the SAME Producer scope must BOTH persist — no rejection.
    $producer = Producer::factory()->create();

    $first = app(CreateProducerAgreement::class)->handle(producerId: $producer->id);
    $second = app(CreateProducerAgreement::class)->handle(producerId: $producer->id);

    expect($first->id)->not->toBe($second->id)
        ->and(ProducerAgreement::query()->where('producer_id', $producer->id)->count())->toBe(2)
        ->and(DomainEvent::query()->where('name', ProducerAgreementCreated::NAME)->count())->toBe(2);
});

it('rejects a ProducerAgreement creation that names no existing Producer (§ 4.6)', function () {
    // No Producer with this id exists — the localized domain reason fires ahead of the FK integrity error.
    expect(fn () => app(CreateProducerAgreement::class)->handle(
        producerId: 999_999,
    ))->toThrow(MissingAgreementProducer::class);

    // The rejected creation persisted nothing — no agreement row and no event.
    expect(ProducerAgreement::query()->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', ProducerAgreementCreated::NAME)->count())->toBe(0);
});

it('records a ProducerAgreementCreated domain event in the same transaction, tagged parties and PII-free', function () {
    $producer = Producer::factory()->create();
    $club = Club::factory()->for($producer, 'producer')->create();

    $agreement = app(CreateProducerAgreement::class)->handle(
        producerId: $producer->id,
        clubId: $club->id,
        termStart: CarbonImmutable::parse('2027-03-01'),
        termEnd: CarbonImmutable::parse('2028-02-29'),
        settlementCadence: 'monthly',
    );

    // sole() asserts EXACTLY one ProducerAgreementCreated row exists — the one-event contract.
    $event = DomainEvent::query()->where('name', ProducerAgreementCreated::NAME)->sole();

    expect($event->module)->toBe('parties')                          // Module::Parties->value
        ->and($event->entity_type)->toBe('ProducerAgreement')
        ->and($event->entity_id)->toBe((string) $agreement->id)      // envelope entity_id is a string
        ->and($event->actor_role)->toBe(ActorRole::System);          // the ActorContext seam default

    // Payload asserted BY KEY through the array cast (trap 3): both parties by id (club nullable here non-null),
    // the term dates as ISO strings, the settlement-cadence seam string — and NO personal data (a commercial
    // agreement holds none). The exact key set is pinned so the PII-free contract cannot silently widen.
    expect(array_keys($event->payload))->toEqualCanonicalizing([
        'agreement_id', 'producer_id', 'club_id', 'status', 'term_start', 'term_end', 'settlement_cadence',
    ]);

    expect($event->payload['agreement_id'])->toBe($agreement->id)
        ->and($event->payload['producer_id'])->toBe($producer->id)
        ->and($event->payload['club_id'])->toBe($club->id)
        ->and($event->payload['status'])->toBe('draft')
        ->and($event->payload['term_start'])->toBe('2027-03-01')
        ->and($event->payload['term_end'])->toBe('2028-02-29')
        ->and($event->payload['settlement_cadence'])->toBe('monthly');
});

it('carries a null club_id in the payload for a Producer-wide agreement', function () {
    $producer = Producer::factory()->create();

    app(CreateProducerAgreement::class)->handle(producerId: $producer->id);

    $event = DomainEvent::query()->where('name', ProducerAgreementCreated::NAME)->sole();

    // Producer-wide: club_id is present in the payload as null (by id only, nullable), term dates null.
    expect($event->payload['club_id'])->toBeNull()
        ->and($event->payload['term_start'])->toBeNull()
        ->and($event->payload['term_end'])->toBeNull()
        ->and($event->payload['settlement_cadence'])->toBeNull();
});

it('records no lifecycle-transition event — the agreement stays draft (scope guard)', function () {
    $producer = Producer::factory()->create();

    $agreement = app(CreateProducerAgreement::class)->handle(producerId: $producer->id);

    // Design D2 scope guard: only the *Created event exists — never a *Superseded/*Terminated (the deferred
    // parties-membership-lifecycle change owns those).
    expect(DomainEvent::query()->where('name', 'like', '%Superseded%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Terminated%')->count())->toBe(0)
        ->and(ProducerAgreement::findOrFail($agreement->id)->status)->toBe(ProducerAgreementStatus::Draft);
});

it('produces a draft agreement via the factory without recording an event', function () {
    // The factory is a pure fixture: it bypasses the action (and its missing-Producer pre-check), so it
    // persists a draft agreement under a within-module parent Producer but records no ProducerAgreementCreated.
    $agreement = ProducerAgreement::factory()->create();

    expect($agreement->status)->toBe(ProducerAgreementStatus::Draft)
        ->and($agreement->version)->toBe(1)
        ->and($agreement->club_id)->toBeNull()                                              // Producer-wide default
        ->and(Producer::query()->whereKey($agreement->producer_id)->exists())->toBeTrue()   // parent Producer built
        ->and(DomainEvent::query()->count())->toBe(0);
});
