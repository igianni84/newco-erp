<?php

use App\Modules\Parties\Actions\ActivateProducerAgreement;
use App\Modules\Parties\Actions\TerminateProducerAgreement;
use App\Modules\Parties\Enums\ClubStatus;
use App\Modules\Parties\Enums\ProducerAgreementStatus;
use App\Modules\Parties\Enums\ProducerStatus;
use App\Modules\Parties\Events\ProducerAgreementActivated;
use App\Modules\Parties\Events\ProducerAgreementSuperseded;
use App\Modules\Parties\Events\ProducerAgreementTerminated;
use App\Modules\Parties\Exceptions\IllegalProducerAgreementTransition;
use App\Modules\Parties\Exceptions\ProducerAgreementScopeConflict;
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
 * It also covers the `active → terminated` transition via {@see TerminateProducerAgreement} — the SOLE writer of
 * `ProducerAgreement.status` for termination and the SINGLE writer of {@see ProducerAgreementTerminated} — a pure
 * standalone transition that records a root event, drives no derived event, and does NOT cascade to the
 * Producer's state (§ 4.6.1).
 *
 * The supersession scope is the trap this test set exists to nail: a NULL `club_id` is the DISTINCT
 * Producer-wide scope, so the prior-active lookup must use `whereNull('club_id')` (not `where('club_id', null)`,
 * which never matches on PostgreSQL — design L7). A second Producer-wide activation supersedes the first (the
 * `whereNull` must FIND the NULL row). BR-K-Agreement-1 has a SECOND clause the guards enforce here (change
 * parties-module-k-br-guards design D2/R1): the Producer-wide and per-Club shapes are MUTUALLY EXCLUSIVE on a
 * Producer at the same time — activating a per-Club agreement while a Producer-wide is `active` (or vice versa)
 * is REJECTED with a `ProducerAgreementScopeConflict`, leaving all state and the event log unchanged (this
 * REPLACES the earlier "the two shapes MAY both be active / scope isolation" claim, now inverted toward the
 * frozen rule). A same-scope renewal still activates even after its Club has `sunset` — activation applies no
 * Club-active check (that gate lives only on the creation path, BR-K-Agreement-4). The derived
 * `ProducerAgreementSuperseded` is caused by — and shares the correlation of — the activation that drove it, and
 * the pair references old + new in its payload.
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

it('rejects activating a per-Club agreement while the Producer\'s Producer-wide agreement is active (BR-K-Agreement-1 clause 2), changing nothing', function () {
    // Cross-shape mutual exclusion (change design D2/R1; AC-K-BR-Agreement-1): a Producer-wide (club_id null) and
    // a per-Club (club_id = C) agreement are mutually exclusive on the same Producer. With a Producer-wide agreement
    // already active, activating a draft per-Club agreement for that Producer is REJECTED — the operator must first
    // terminate/supersede the Producer-wide one. (This inverts the earlier "MAY both be active / scope isolation"
    // scenario — the delta replaces it.)
    $producer = Producer::factory()->create();
    $club = Club::factory()->create(['producer_id' => $producer->id]);

    $wide = ProducerAgreement::factory()->create([
        'producer_id' => $producer->id,
        'club_id' => null,
        'status' => ProducerAgreementStatus::Active,
    ]);
    $clubDraft = ProducerAgreement::factory()->create([
        'producer_id' => $producer->id,
        'club_id' => $club->id,
    ]);   // draft, opposite shape

    expect(fn () => app(ActivateProducerAgreement::class)->handle($clubDraft->id))
        ->toThrow(ProducerAgreementScopeConflict::class);

    // Rejected pre-write, transaction rolled back: the Producer-wide stays active, the per-Club stays draft, and
    // no lifecycle event was recorded (the factory-seeded active records nothing).
    expect(ProducerAgreement::findOrFail($wide->id)->status)->toBe(ProducerAgreementStatus::Active)
        ->and(ProducerAgreement::findOrFail($clubDraft->id)->status)->toBe(ProducerAgreementStatus::Draft)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('rejects activating a Producer-wide agreement while any per-Club agreement of the Producer is active (BR-K-Agreement-1 clause 2), changing nothing', function () {
    // The symmetric direction: with a per-Club agreement already active, activating a draft Producer-wide agreement
    // for that Producer is REJECTED. Activating Producer-wide is blocked by ANY active per-Club agreement of the
    // Producer (whereNotNull), so the operator must first terminate/supersede the per-Club one.
    $producer = Producer::factory()->create();
    $club = Club::factory()->create(['producer_id' => $producer->id]);

    $clubActive = ProducerAgreement::factory()->create([
        'producer_id' => $producer->id,
        'club_id' => $club->id,
        'status' => ProducerAgreementStatus::Active,
    ]);
    $wideDraft = ProducerAgreement::factory()->create([
        'producer_id' => $producer->id,
        'club_id' => null,
    ]);   // draft, opposite shape

    expect(fn () => app(ActivateProducerAgreement::class)->handle($wideDraft->id))
        ->toThrow(ProducerAgreementScopeConflict::class);

    expect(ProducerAgreement::findOrFail($clubActive->id)->status)->toBe(ProducerAgreementStatus::Active)
        ->and(ProducerAgreement::findOrFail($wideDraft->id)->status)->toBe(ProducerAgreementStatus::Draft)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('permits two per-Club agreements on different Clubs to be active — same shape, distinct scopes, no cross-shape conflict', function () {
    // Cross-shape exclusion forbids only mixing the two SHAPES; multiple per-Club agreements (one per Club) are the
    // same shape and coexist. Activating a per-Club draft while a DIFFERENT Club's per-Club agreement is active is
    // admitted, and — different scopes — supersedes nothing.
    $producer = Producer::factory()->create();
    $clubA = Club::factory()->create(['producer_id' => $producer->id]);
    $clubB = Club::factory()->create(['producer_id' => $producer->id]);

    $activeA = ProducerAgreement::factory()->create([
        'producer_id' => $producer->id,
        'club_id' => $clubA->id,
        'status' => ProducerAgreementStatus::Active,
    ]);
    $draftB = ProducerAgreement::factory()->create([
        'producer_id' => $producer->id,
        'club_id' => $clubB->id,
    ]);   // draft, different Club

    app(ActivateProducerAgreement::class)->handle($draftB->id);

    // Both per-Club agreements are active; nothing was superseded (distinct scopes). Only the activation recorded.
    expect(ProducerAgreement::findOrFail($draftB->id)->status)->toBe(ProducerAgreementStatus::Active)
        ->and(ProducerAgreement::findOrFail($activeA->id)->status)->toBe(ProducerAgreementStatus::Active)
        ->and(DomainEvent::query()->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', ProducerAgreementSuperseded::NAME)->count())->toBe(0);
});

it('activates a same-scope renewal even after its Club has sunset — activation applies no Club-active check (Agreement-4 exemption)', function () {
    // The Club-active gate lives ONLY on the creation path (BR-K-Agreement-4, task 3.2), never on activation. A
    // per-Club agreement is active; the Club then sunsets. A renewal in the SAME (producer, club) scope must still
    // activate and supersede the prior — supersession/renewal is exempt from the Club-active check.
    $producer = Producer::factory()->create();
    $club = Club::factory()->create(['producer_id' => $producer->id]);   // born active

    $prior = ProducerAgreement::factory()->create([
        'producer_id' => $producer->id,
        'club_id' => $club->id,
        'status' => ProducerAgreementStatus::Active,
    ]);
    $club->update(['status' => ClubStatus::Sunset]);

    $renewal = ProducerAgreement::factory()->create([
        'producer_id' => $producer->id,
        'club_id' => $club->id,
    ]);   // draft, same scope

    $returned = app(ActivateProducerAgreement::class)->handle($renewal->id);

    // The renewal activates and supersedes the same-scope prior, wholly unaffected by the Club's sunset.
    expect($returned->status)->toBe(ProducerAgreementStatus::Active)
        ->and(ProducerAgreement::findOrFail($prior->id)->status)->toBe(ProducerAgreementStatus::Superseded)
        ->and(ProducerAgreement::findOrFail($renewal->id)->status)->toBe(ProducerAgreementStatus::Active);

    // The activation + its derived supersession — the pair references old + new.
    expect(DomainEvent::query()->where('name', ProducerAgreementSuperseded::NAME)->count())->toBe(1);
    $superseded = DomainEvent::query()->where('name', ProducerAgreementSuperseded::NAME)->sole();
    expect($superseded->entity_id)->toBe((string) $prior->id)
        ->and($superseded->payload['superseded_by'])->toBe($renewal->id)
        ->and($superseded->payload['club_id'])->toBe($club->id);
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

it('terminates an active agreement without cascading — a root ProducerAgreementTerminated, the Producer unchanged', function () {
    // Spec "Terminate an active agreement without cascading": an `active` agreement under an `active` Producer.
    // The factory seeds both directly (bypassing the actions), so it records nothing — the termination is the
    // only event, and the Producer stays `active` (termination does NOT cascade to Producer state — § 4.6.1).
    $producer = Producer::factory()->create(['status' => ProducerStatus::Active]);
    $agreement = ProducerAgreement::factory()->create([
        'producer_id' => $producer->id,
        'status' => ProducerAgreementStatus::Active,
    ]);   // Producer-wide (club_id null)

    $returned = app(TerminateProducerAgreement::class)->handle($agreement->id);

    expect($returned->status)->toBe(ProducerAgreementStatus::Terminated)
        ->and(ProducerAgreement::findOrFail($agreement->id)->status)->toBe(ProducerAgreementStatus::Terminated);

    // NO cascade to Producer state (§ 4.6.1): the Producer FSM is independent of its agreements.
    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Active);

    // Exactly one domain event — the termination; it drives no derived event and no cascade.
    expect(DomainEvent::query()->count())->toBe(1);

    $event = DomainEvent::query()->where('name', ProducerAgreementTerminated::NAME)->sole();

    expect($event->module)->toBe('parties')                       // Module::Parties->value
        ->and($event->entity_type)->toBe('ProducerAgreement')
        ->and($event->entity_id)->toBe((string) $agreement->id)   // envelope entity_id is a string
        ->and($event->actor_role)->toBe(ActorRole::System);       // the ActorContext seam default

    // Payload asserted BY KEY (knowledge/testing trap 3 — never byte-compare PG jsonb): the four-key terminal
    // shape (no linkage field — termination pairs with nothing), pinned so the PII-free contract cannot widen.
    expect(array_keys($event->payload))
        ->toEqualCanonicalizing(['producer_agreement_id', 'producer_id', 'club_id', 'status']);

    expect($event->payload['producer_agreement_id'])->toBe($agreement->id)
        ->and($event->payload['producer_id'])->toBe($producer->id)
        ->and($event->payload['club_id'])->toBeNull()
        ->and($event->payload['status'])->toBe('terminated');

    // Transition-shaped subset: the creation snapshot fields are deliberately absent (the immutable creation
    // record holds them).
    expect($event->payload)->not->toHaveKey('term_start')
        ->and($event->payload)->not->toHaveKey('settlement_cadence');

    // Termination is a ROOT event: self-correlated, no cause (it is never part of a cascade).
    expect($event->causation_id)->toBeNull()
        ->and($event->correlation_id)->toBe($event->event_id);
});

it('rejects terminating an agreement still in draft and records nothing', function () {
    $agreement = ProducerAgreement::factory()->create();   // born `draft`

    expect(fn () => app(TerminateProducerAgreement::class)->handle($agreement->id))
        ->toThrow(IllegalProducerAgreementTransition::class);

    // The from-state guard fires before any write and the transaction rolls back: `draft` is not terminable
    // (termination is reachable only from `active`), so the status is unchanged and no event was recorded.
    expect(ProducerAgreement::findOrFail($agreement->id)->status)->toBe(ProducerAgreementStatus::Draft)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('rejects terminating a superseded agreement and records nothing', function () {
    $agreement = ProducerAgreement::factory()->create(['status' => ProducerAgreementStatus::Superseded]);

    expect(fn () => app(TerminateProducerAgreement::class)->handle($agreement->id))
        ->toThrow(IllegalProducerAgreementTransition::class);

    // `superseded` is terminal — a replaced agreement cannot be terminated; the guard leaves the row untouched.
    expect(ProducerAgreement::findOrFail($agreement->id)->status)->toBe(ProducerAgreementStatus::Superseded)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('rejects terminating an already-terminated agreement and records nothing', function () {
    $agreement = ProducerAgreement::factory()->create(['status' => ProducerAgreementStatus::Terminated]);

    expect(fn () => app(TerminateProducerAgreement::class)->handle($agreement->id))
        ->toThrow(IllegalProducerAgreementTransition::class);

    // `terminated` is terminal — termination is idempotent-by-rejection; the guard leaves the row untouched.
    expect(ProducerAgreement::findOrFail($agreement->id)->status)->toBe(ProducerAgreementStatus::Terminated)
        ->and(DomainEvent::query()->count())->toBe(0);
});
