<?php

use App\Modules\Parties\Actions\ActivateProducer;
use App\Modules\Parties\Actions\RetireProducer;
use App\Modules\Parties\Enums\ClubStatus;
use App\Modules\Parties\Enums\ProducerStatus;
use App\Modules\Parties\Events\ClubSunset;
use App\Modules\Parties\Events\ProducerActivated;
use App\Modules\Parties\Events\ProducerRetired;
use App\Modules\Parties\Exceptions\IllegalProducerTransition;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Producer;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the Producer supply-side lifecycle (parties-producer-lifecycle; design L1/L2/L4/L5/L6/L8; party-registry —
 * Requirements: Producer Lifecycle, Supply-Side Lifecycle Events). It covers three things: (1) the structural
 * read the retirement cascade walks — the within-module {@see Producer::clubs()} `hasMany` (design L6), the
 * inverse of {@see Club::producer()}, scoped to the Producer's operated Clubs and boundary-clean (both entities
 * are Module K); (2) the `draft → active` transition via {@see ActivateProducer} — the SOLE writer of
 * `Producer.status` for activation and the SINGLE writer of {@see ProducerActivated}, always a root event
 * (activation is never a cascade target); and (3) the `active → retired` transition via {@see RetireProducer},
 * which records {@see ProducerRetired} (the cascade ROOT) and CASCADES sunset onto every operated Club still
 * `active`, each recording a {@see ClubSunset} caused by — and sharing the correlation of — the retirement
 * (design L5/L6). The KYC-verified precondition on activation (Module K PRD § 4.4) is a deferred seam (design
 * L8), not enforced here; the Profile leg of the § 10.2 retirement cascade is deferred too (demand-side).
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

it('retires an active Producer and cascades sunset onto its active Clubs, each ClubSunset caused by the retirement', function () {
    // The § 10.2 offboarding cascade (Producer → Club leg) — the spec scenario "Retire an active Producer
    // cascades Club sunset": GIVEN an active Producer operating two active Clubs and one already-closed Club.
    $producer = Producer::factory()->create(['status' => ProducerStatus::Active]);
    $activeA = Club::factory()->create(['producer_id' => $producer->id]);   // born active
    $activeB = Club::factory()->create(['producer_id' => $producer->id]);   // born active
    $closed = Club::factory()->create(['producer_id' => $producer->id, 'status' => ClubStatus::Closed]);

    $returned = app(RetireProducer::class)->handle($producer->id);

    // The Producer transitions to `retired` (returned model + the persisted row).
    expect($returned->status)->toBe(ProducerStatus::Retired)
        ->and(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Retired);

    // The two active Clubs are sunset; the already-closed Club is left UNCHANGED — the cascade only touches
    // active Clubs (it is idempotent over already-transitioned ones).
    expect(Club::findOrFail($activeA->id)->status)->toBe(ClubStatus::Sunset)
        ->and(Club::findOrFail($activeB->id)->status)->toBe(ClubStatus::Sunset)
        ->and(Club::findOrFail($closed->id)->status)->toBe(ClubStatus::Closed);

    // Exactly three events recorded in the one transaction — one ProducerRetired root + two cascade ClubSunset,
    // and nothing for the untouched closed Club (no ClubClosed, no demand-side event).
    expect(DomainEvent::query()->count())->toBe(3);

    $retired = DomainEvent::query()->where('name', ProducerRetired::NAME)->sole();

    expect($retired->module)->toBe('parties')                     // Module::Parties->value
        ->and($retired->entity_type)->toBe('Producer')
        ->and($retired->entity_id)->toBe((string) $producer->id)  // envelope entity_id is a string
        ->and($retired->actor_role)->toBe(ActorRole::System);     // the ActorContext seam default

    // ProducerRetired payload asserted BY KEY (knowledge/testing trap 3): the two-key PII-free shape with the
    // POST-transition status, pinned so the contract cannot silently widen.
    expect(array_keys($retired->payload))->toEqualCanonicalizing(['producer_id', 'status']);
    expect($retired->payload['producer_id'])->toBe($producer->id)
        ->and($retired->payload['status'])->toBe('retired');

    // The retirement is the ROOT of the cascade: it carries no cause and is self-correlated.
    expect($retired->causation_id)->toBeNull()
        ->and($retired->correlation_id)->toBe($retired->event_id);

    // One cascade ClubSunset per active Club (none for the closed Club), addressed at the two active Clubs.
    $sunsets = DomainEvent::query()->where('name', ClubSunset::NAME)->get();
    expect($sunsets)->toHaveCount(2)
        ->and($sunsets->pluck('entity_id')->all())
        ->toEqualCanonicalizing([(string) $activeA->id, (string) $activeB->id]);

    // Cascade causal linkage (design L5; spec "Cascade events are causally linked to the retirement"): every
    // cascade ClubSunset carries the ProducerRetired event's `id` as `causation_id` and shares its
    // `correlation_id` — the offboarding is one queryable thread in the audit log.
    foreach ($sunsets as $sunset) {
        expect($sunset->causation_id)->toBe($retired->id)
            ->and($sunset->correlation_id)->toBe($retired->correlation_id);
    }
});

it('rejects retiring a Producer not in active, rolls back, and cascades nothing', function () {
    // A `draft` Producer is not retirable (retirement is reachable only from `active`); an operated active Club
    // is present to prove the cascade does NOT run when the from-state guard rejects the call.
    $producer = Producer::factory()->create(['status' => ProducerStatus::Draft]);
    $club = Club::factory()->create(['producer_id' => $producer->id]);   // born active

    expect(fn () => app(RetireProducer::class)->handle($producer->id))
        ->toThrow(IllegalProducerTransition::class);

    // The guard fires before any write and the whole transaction rolls back together: the Producer is unchanged,
    // the operated Club is NOT sunset, and neither ProducerRetired nor any cascade ClubSunset was recorded.
    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Draft)
        ->and(Club::findOrFail($club->id)->status)->toBe(ClubStatus::Active)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('rejects retiring a Producer already in retired and records nothing', function () {
    $producer = Producer::factory()->create(['status' => ProducerStatus::Retired]);

    expect(fn () => app(RetireProducer::class)->handle($producer->id))
        ->toThrow(IllegalProducerTransition::class);

    // `retired` is terminal in the linear FSM — re-retiring is rejected by the same `=== active` guard, leaving
    // the row untouched and recording nothing.
    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Retired)
        ->and(DomainEvent::query()->where('name', ProducerRetired::NAME)->count())->toBe(0);
});

it('retires an active Producer that operates no active Clubs, recording only the root ProducerRetired', function () {
    // The cascade no-ops cleanly when there is nothing `active` to sunset: only a closed Club exists, so the
    // walk over the active-Club set is empty and a single root ProducerRetired is recorded.
    $producer = Producer::factory()->create(['status' => ProducerStatus::Active]);
    Club::factory()->create(['producer_id' => $producer->id, 'status' => ClubStatus::Closed]);

    $returned = app(RetireProducer::class)->handle($producer->id);

    expect($returned->status)->toBe(ProducerStatus::Retired);

    // Exactly one event — the root ProducerRetired — and zero cascade ClubSunset (no active Club to sunset).
    expect(DomainEvent::query()->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', ProducerRetired::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', ClubSunset::NAME)->count())->toBe(0);
});
