<?php

use App\Modules\Parties\Actions\ActivateProducerAgreement;
use App\Modules\Parties\Enums\ProducerAgreementStatus;
use App\Modules\Parties\Events\ProducerAgreementActivated;
use App\Modules\Parties\Events\ProducerAgreementSuperseded;
use App\Modules\Parties\Exceptions\IllegalProducerAgreementTransition;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Producer;
use App\Modules\Parties\Models\ProducerAgreement;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the ProducerAgreement supply-side lifecycle (parties-producer-lifecycle; design L1/L2/L4/L5/L7;
 * party-registry — Requirements: ProducerAgreement Lifecycle, Supply-Side Lifecycle Events). It covers the
 * `draft → active` transition via {@see ActivateProducerAgreement} — the SOLE writer of
 * `ProducerAgreement.status` for activation/supersession and the SINGLE writer of both
 * {@see ProducerAgreementActivated} and {@see ProducerAgreementSuperseded} — together with the scope-aware,
 * NULL-safe enforcement of BR-K-Agreement-1 (at most one active agreement per `(producer_id, club_id)` scope).
 *
 * The supersession scope is the trap this test set exists to nail: a NULL `club_id` is the DISTINCT
 * Producer-wide scope, so the prior-active lookup must use `whereNull('club_id')` (not `where('club_id', null)`,
 * which never matches on PostgreSQL — design L7). Two scenarios pin both directions: a second Producer-wide
 * activation supersedes the first (the `whereNull` must FIND the NULL row), and a Producer-wide and a
 * Club-narrowed agreement coexist as both active while activating a new one in either scope supersedes ONLY the
 * same-scope prior (the scoped predicate must NOT cross scopes). The derived `ProducerAgreementSuperseded` is
 * caused by — and shares the correlation of — the activation that drove it, and the pair references old + new in
 * its payload.
 *
 * RefreshDatabase per the task hint; the transition opens its OWN DB::transaction, so the recorder's
 * `transactionLevel() === 0` guard is satisfied by the savepoint under the wrapper. The status writes and the
 * event envelope (string `entity_id`, payload by id, the `causation_id`/`correlation_id` columns) are exercised
 * against a real schema so the scope query and the linkage round-trip on both engines (SQLite here; PostgreSQL
 * 17 in the cross-engine close — the NULL-distinctness behaviour differs by engine, so PG17 is mandatory).
 */
uses(RefreshDatabase::class);

it('activates a lone draft agreement with no prior active in scope — a root ProducerAgreementActivated, supersedes null', function () {
    $agreement = ProducerAgreement::factory()->create();   // born `draft`, Producer-wide (club_id null)

    $returned = app(ActivateProducerAgreement::class)->handle($agreement->id);

    expect($returned->status)->toBe(ProducerAgreementStatus::Active)
        ->and(ProducerAgreement::findOrFail($agreement->id)->status)->toBe(ProducerAgreementStatus::Active);

    // Exactly one domain event — the activation; no supersession (nothing prior in scope). The factory bypasses
    // the action and records nothing, so this activation is the only event.
    expect(DomainEvent::query()->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', ProducerAgreementSuperseded::NAME)->count())->toBe(0);

    $event = DomainEvent::query()->where('name', ProducerAgreementActivated::NAME)->sole();

    expect($event->module)->toBe('parties')                       // Module::Parties->value
        ->and($event->entity_type)->toBe('ProducerAgreement')
        ->and($event->entity_id)->toBe((string) $agreement->id)   // envelope entity_id is a string
        ->and($event->actor_role)->toBe(ActorRole::System);       // the ActorContext seam default

    // Payload asserted BY KEY (knowledge/testing trap 3 — never byte-compare PG jsonb): the five-key supply-side
    // shape, supersedes null (nothing replaced), pinned so the PII-free contract cannot silently widen.
    expect(array_keys($event->payload))
        ->toEqualCanonicalizing(['producer_agreement_id', 'producer_id', 'club_id', 'status', 'supersedes']);

    expect($event->payload['producer_agreement_id'])->toBe($agreement->id)
        ->and($event->payload['producer_id'])->toBe($agreement->producer_id)
        ->and($event->payload['club_id'])->toBeNull()
        ->and($event->payload['status'])->toBe('active')
        ->and($event->payload['supersedes'])->toBeNull();

    // Transition-shaped subset: the creation snapshot fields are not the subject of a transition event and are
    // deliberately absent (the immutable creation record holds them).
    expect($event->payload)->not->toHaveKey('term_start')
        ->and($event->payload)->not->toHaveKey('settlement_cadence');

    // A lone activation is a ROOT event: self-correlated, no cause.
    expect($event->causation_id)->toBeNull()
        ->and($event->correlation_id)->toBe($event->event_id);
});

it('activating a replacement Producer-wide agreement supersedes the prior active in the same scope, pairing old and new', function () {
    // Spec "Activating a replacement supersedes the prior active in the same scope": both Producer-wide
    // (club_id null), so the prior-active lookup must FIND the NULL-club_id row — `where('club_id', null)` would
    // miss it (design L7 — the core NULL-distinctness trap).
    $producer = Producer::factory()->create();

    $a = ProducerAgreement::factory()->create(['producer_id' => $producer->id]);   // draft, club_id null
    app(ActivateProducerAgreement::class)->handle($a->id);                          // A → active (root)

    $b = ProducerAgreement::factory()->create(['producer_id' => $producer->id]);   // draft, club_id null
    $returnedB = app(ActivateProducerAgreement::class)->handle($b->id);            // B → active, supersedes A

    // A is superseded, B is active (re-fetched from the DB).
    expect($returnedB->status)->toBe(ProducerAgreementStatus::Active)
        ->and(ProducerAgreement::findOrFail($a->id)->status)->toBe(ProducerAgreementStatus::Superseded)
        ->and(ProducerAgreement::findOrFail($b->id)->status)->toBe(ProducerAgreementStatus::Active);

    // Three events total: A's root activation, B's activation, A's supersession.
    expect(DomainEvent::query()->count())->toBe(3)
        ->and(DomainEvent::query()->where('name', ProducerAgreementActivated::NAME)->count())->toBe(2)
        ->and(DomainEvent::query()->where('name', ProducerAgreementSuperseded::NAME)->count())->toBe(1);

    // B's activation references the superseded A in `supersedes` (the new → old half of the pair).
    $activatedB = DomainEvent::query()
        ->where('name', ProducerAgreementActivated::NAME)
        ->where('entity_id', (string) $b->id)
        ->sole();
    expect($activatedB->payload['supersedes'])->toBe($a->id);

    // The supersession is about A, references the superseding B in `superseded_by` (the old → new half), and
    // carries the post-transition `superseded` status. Payload keys pinned.
    $superseded = DomainEvent::query()->where('name', ProducerAgreementSuperseded::NAME)->sole();
    expect($superseded->entity_type)->toBe('ProducerAgreement')
        ->and($superseded->entity_id)->toBe((string) $a->id)
        ->and(array_keys($superseded->payload))
        ->toEqualCanonicalizing(['producer_agreement_id', 'producer_id', 'club_id', 'status', 'superseded_by']);
    expect($superseded->payload['producer_agreement_id'])->toBe($a->id)
        ->and($superseded->payload['superseded_by'])->toBe($b->id)
        ->and($superseded->payload['status'])->toBe('superseded')
        ->and($superseded->payload['club_id'])->toBeNull();

    // Derived-chain linkage (design L5): the supersession is caused by — and shares the correlation of — B's
    // activation event (the renewal is one queryable thread in the audit log).
    expect($superseded->causation_id)->toBe($activatedB->id)
        ->and($superseded->correlation_id)->toBe($activatedB->correlation_id);
});

it('isolates scope: activating a Club-narrowed replacement supersedes only the same-Club prior, leaving the Producer-wide active', function () {
    // Spec "Scope isolation between Producer-wide and Club-narrowed agreements": a Producer-wide (club_id null)
    // and a Club-narrowed (club_id = C) agreement coexist as both active. Activating a new club_id = C draft
    // must supersede ONLY the club-scoped prior — the scoped predicate must NOT match the NULL-club_id row.
    $producer = Producer::factory()->create();
    $club = Club::factory()->create(['producer_id' => $producer->id]);

    $wide = ProducerAgreement::factory()->create([
        'producer_id' => $producer->id,
        'club_id' => null,
        'status' => ProducerAgreementStatus::Active,
    ]);
    $clubPrior = ProducerAgreement::factory()->create([
        'producer_id' => $producer->id,
        'club_id' => $club->id,
        'status' => ProducerAgreementStatus::Active,
    ]);

    $clubNew = ProducerAgreement::factory()->create([
        'producer_id' => $producer->id,
        'club_id' => $club->id,
    ]);   // draft
    app(ActivateProducerAgreement::class)->handle($clubNew->id);

    // Only the same-scope (club_id = C) prior is superseded; the Producer-wide agreement is UNTOUCHED.
    expect(ProducerAgreement::findOrFail($clubNew->id)->status)->toBe(ProducerAgreementStatus::Active)
        ->and(ProducerAgreement::findOrFail($clubPrior->id)->status)->toBe(ProducerAgreementStatus::Superseded)
        ->and(ProducerAgreement::findOrFail($wide->id)->status)->toBe(ProducerAgreementStatus::Active);

    // Exactly the two events the activation recorded — the new agreement's activation and the club-prior's
    // supersession; the factory-seeded actives record nothing.
    expect(DomainEvent::query()->count())->toBe(2);

    // The supersession is about the club-scoped prior, never the Producer-wide one, and carries the club scope.
    $superseded = DomainEvent::query()->where('name', ProducerAgreementSuperseded::NAME)->sole();
    expect($superseded->entity_id)->toBe((string) $clubPrior->id)
        ->and($superseded->payload['producer_agreement_id'])->toBe($clubPrior->id)
        ->and($superseded->payload['superseded_by'])->toBe($clubNew->id)
        ->and($superseded->payload['club_id'])->toBe($club->id);

    // The new activation references the club-prior in `supersedes` and carries the club_id scope.
    $activated = DomainEvent::query()
        ->where('name', ProducerAgreementActivated::NAME)
        ->where('entity_id', (string) $clubNew->id)
        ->sole();
    expect($activated->payload['supersedes'])->toBe($clubPrior->id)
        ->and($activated->payload['club_id'])->toBe($club->id);
});

it('isolates scope: activating a Producer-wide replacement supersedes only the prior Producer-wide, leaving a Club-narrowed active', function () {
    // The symmetric NULL-trap: `whereNull('club_id')` must match ONLY the NULL-club_id prior and NOT the
    // Club-narrowed active. Activating a new Producer-wide agreement supersedes the prior Producer-wide one and
    // leaves the coexisting club_id = C agreement active.
    $producer = Producer::factory()->create();
    $club = Club::factory()->create(['producer_id' => $producer->id]);

    $widePrior = ProducerAgreement::factory()->create([
        'producer_id' => $producer->id,
        'club_id' => null,
        'status' => ProducerAgreementStatus::Active,
    ]);
    $clubActive = ProducerAgreement::factory()->create([
        'producer_id' => $producer->id,
        'club_id' => $club->id,
        'status' => ProducerAgreementStatus::Active,
    ]);

    $wideNew = ProducerAgreement::factory()->create([
        'producer_id' => $producer->id,
        'club_id' => null,
    ]);   // draft
    app(ActivateProducerAgreement::class)->handle($wideNew->id);

    expect(ProducerAgreement::findOrFail($wideNew->id)->status)->toBe(ProducerAgreementStatus::Active)
        ->and(ProducerAgreement::findOrFail($widePrior->id)->status)->toBe(ProducerAgreementStatus::Superseded)
        ->and(ProducerAgreement::findOrFail($clubActive->id)->status)->toBe(ProducerAgreementStatus::Active);

    expect(DomainEvent::query()->count())->toBe(2);

    // The supersession is about the Producer-wide prior (NULL club_id), never the Club-narrowed active.
    $superseded = DomainEvent::query()->where('name', ProducerAgreementSuperseded::NAME)->sole();
    expect($superseded->entity_id)->toBe((string) $widePrior->id)
        ->and($superseded->payload['superseded_by'])->toBe($wideNew->id)
        ->and($superseded->payload['club_id'])->toBeNull();
});

it('rejects activating an agreement already in active and records nothing', function () {
    $agreement = ProducerAgreement::factory()->create(['status' => ProducerAgreementStatus::Active]);

    expect(fn () => app(ActivateProducerAgreement::class)->handle($agreement->id))
        ->toThrow(IllegalProducerAgreementTransition::class);

    // The from-state guard fires before any write and the transaction rolls back: the status is unchanged and no
    // agreement lifecycle event was recorded.
    expect(ProducerAgreement::findOrFail($agreement->id)->status)->toBe(ProducerAgreementStatus::Active)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('rejects activating a superseded agreement and records nothing', function () {
    $agreement = ProducerAgreement::factory()->create(['status' => ProducerAgreementStatus::Superseded]);

    expect(fn () => app(ActivateProducerAgreement::class)->handle($agreement->id))
        ->toThrow(IllegalProducerAgreementTransition::class);

    // `superseded` is terminal — a replaced agreement cannot be re-activated; the guard leaves the row untouched.
    expect(ProducerAgreement::findOrFail($agreement->id)->status)->toBe(ProducerAgreementStatus::Superseded)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('rejects activating a terminated agreement and records nothing', function () {
    $agreement = ProducerAgreement::factory()->create(['status' => ProducerAgreementStatus::Terminated]);

    expect(fn () => app(ActivateProducerAgreement::class)->handle($agreement->id))
        ->toThrow(IllegalProducerAgreementTransition::class);

    // `terminated` is terminal — activation cannot resurrect it; the guard leaves the row untouched.
    expect(ProducerAgreement::findOrFail($agreement->id)->status)->toBe(ProducerAgreementStatus::Terminated)
        ->and(DomainEvent::query()->count())->toBe(0);
});
