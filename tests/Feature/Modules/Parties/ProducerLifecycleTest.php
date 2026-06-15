<?php

use App\Modules\Parties\Actions\ActivateProducer;
use App\Modules\Parties\Enums\ProducerStatus;
use App\Modules\Parties\Events\ProducerActivated;
use App\Modules\Parties\Exceptions\IllegalProducerTransition;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Producer;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the Producer supply-side lifecycle (parties-producer-lifecycle; design L1/L2/L4/L8; party-registry —
 * Requirements: Producer Lifecycle, Supply-Side Lifecycle Events). It covers two things: (1) the structural
 * read the retirement cascade walks — the within-module {@see Producer::clubs()} `hasMany` (design L6), the
 * inverse of {@see Club::producer()}, scoped to the Producer's operated Clubs and boundary-clean (both entities
 * are Module K); and (2) the `draft → active` transition via {@see ActivateProducer} — the SOLE writer of
 * `Producer.status` for activation and the SINGLE writer of {@see ProducerActivated}, always a root event
 * (activation is never a cascade target). The KYC-verified precondition (Module K PRD § 4.4) is a deferred seam
 * (design L8), not enforced here. The `RetireProducer` transition + its Club-sunset cascade arrive in task 3.2.
 *
 * RefreshDatabase per the task hint; the transition opens its OWN DB::transaction, so the recorder's
 * `transactionLevel() === 0` guard is satisfied by the savepoint under the wrapper (the event being recorded at
 * all is itself proof of the in-transaction wiring). The relation and the status write + event envelope are
 * exercised against a real schema so the `producer_id` FK scope and the causation/correlation columns round-trip
 * on both engines (SQLite here; PostgreSQL 17 in the cross-engine close — knowledge/testing).
 */
uses(RefreshDatabase::class);

it('exposes the operated Clubs through the within-module clubs() hasMany', function () {
    $producer = Producer::factory()->create();
    Club::factory()->count(3)->create(['producer_id' => $producer->id]);

    // The relation query counts the Producer's Clubs (the read RetireProducer walks — design L6).
    expect($producer->clubs()->count())->toBe(3);

    // The lazy-loaded dynamic property hydrates an Eloquent Collection of Club models.
    expect($producer->clubs)->toBeInstanceOf(Collection::class)
        ->and($producer->clubs)->toHaveCount(3);
    expect($producer->clubs)->each->toBeInstanceOf(Club::class);
});

it('returns an empty collection for a Producer that operates no Clubs', function () {
    $producer = Producer::factory()->create();

    expect($producer->clubs()->count())->toBe(0)
        ->and($producer->clubs)->toBeEmpty();
});

it('scopes clubs() to the owning Producer — Clubs of a different Producer are excluded', function () {
    $producer = Producer::factory()->create();
    $other = Producer::factory()->create();

    Club::factory()->count(2)->create(['producer_id' => $producer->id]);
    Club::factory()->count(3)->create(['producer_id' => $other->id]);

    // The hasMany is keyed on producer_id: each Producer sees only its own Clubs (the cascade must never
    // reach across Producers).
    expect($producer->clubs()->count())->toBe(2)
        ->and($other->clubs()->count())->toBe(3)
        ->and($producer->clubs->pluck('producer_id')->unique()->values()->all())->toBe([$producer->id]);
});

it('activates a draft Producer and records a ProducerActivated in the same transaction, tagged parties and PII-free', function () {
    $producer = Producer::factory()->create();   // born `draft`

    $returned = app(ActivateProducer::class)->handle($producer->id);

    // The action returns the transitioned model, and the persisted row re-hydrates to `active`.
    expect($returned->status)->toBe(ProducerStatus::Active)
        ->and(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Active);

    // Exactly one domain event total — the factory bypasses the action and records nothing, so the only
    // event is the ProducerActivated this transition recorded.
    expect(DomainEvent::query()->count())->toBe(1);

    $event = DomainEvent::query()->where('name', ProducerActivated::NAME)->sole();

    expect($event->module)->toBe('parties')                     // Module::Parties->value
        ->and($event->entity_type)->toBe('Producer')
        ->and($event->entity_id)->toBe((string) $producer->id)  // envelope entity_id is a string
        ->and($event->actor_role)->toBe(ActorRole::System);     // the ActorContext seam default

    // Payload asserted BY KEY (knowledge/testing trap 3 — never byte-compare PG jsonb): the Producer by id and
    // the POST-transition status, and nothing more — the exact key set is pinned so the PII-free contract
    // cannot silently widen.
    expect(array_keys($event->payload))->toEqualCanonicalizing(['producer_id', 'status']);

    expect($event->payload['producer_id'])->toBe($producer->id)
        ->and($event->payload['status'])->toBe('active');

    // PII-free / transition-shaped: a Producer is not a Party (§ 4.4) and carries no personal data anyway, and
    // the structural creation fields are not the subject of a transition event — they are deliberately absent.
    expect($event->payload)->not->toHaveKey('name')
        ->and($event->payload)->not->toHaveKey('region')
        ->and($event->payload)->not->toHaveKey('country');

    // A standalone activation is a ROOT event: the recorder defaults `correlation_id` to the event's own
    // `event_id` and leaves `causation_id` null (activation is never a cascade target).
    expect($event->causation_id)->toBeNull()
        ->and($event->correlation_id)->toBe($event->event_id);
});

it('activates a draft Producer with no KYC verdict present — the KYC gate is a deferred seam', function () {
    // The KYC four-state lifecycle and its fields are owned by the future `parties-compliance` change
    // (DEC-071); they are not modelled in this slice, so no KYC verdict can exist. Activation must still
    // succeed — the KYC-verified precondition (Module K PRD § 4.4) is a documented seam tightened later, not
    // enforced here. When parties-compliance closes the seam, this scenario tightens to require a verdict.
    $producer = Producer::factory()->create();   // born `draft`, with no KYC fields modelled

    $returned = app(ActivateProducer::class)->handle($producer->id);

    expect($returned->status)->toBe(ProducerStatus::Active)
        ->and(DomainEvent::query()->where('name', ProducerActivated::NAME)->count())->toBe(1);
});

it('rejects activating a Producer already in active and records nothing', function () {
    $producer = Producer::factory()->create(['status' => ProducerStatus::Active]);

    expect(fn () => app(ActivateProducer::class)->handle($producer->id))
        ->toThrow(IllegalProducerTransition::class);

    // The from-state guard fires before any write and the transaction rolls back: the status is unchanged and
    // no ProducerActivated event was recorded.
    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Active)
        ->and(DomainEvent::query()->where('name', ProducerActivated::NAME)->count())->toBe(0);
});

it('rejects activating a Producer already in retired and records nothing', function () {
    $producer = Producer::factory()->create(['status' => ProducerStatus::Retired]);

    expect(fn () => app(ActivateProducer::class)->handle($producer->id))
        ->toThrow(IllegalProducerTransition::class);

    // `retired` is terminal in the linear FSM — activation cannot resurrect it; the guard leaves the row
    // untouched and records nothing.
    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Retired)
        ->and(DomainEvent::query()->where('name', ProducerActivated::NAME)->count())->toBe(0);
});
